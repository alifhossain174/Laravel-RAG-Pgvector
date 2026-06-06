<?php

namespace App\Services;

use RuntimeException;
use Spatie\PdfToText\Pdf;
use Throwable;

class PdfExtractorService
{
    public function extractText(string $absoluteFilePath): string
    {
        $pages = $this->extractPages($absoluteFilePath);

        return trim(implode("\n\n", array_column($pages, 'content')));
    }

    public function extractPages(string $absoluteFilePath): array
    {
        if (! is_file($absoluteFilePath) || ! is_readable($absoluteFilePath)) {
            throw new RuntimeException("PDF file is not readable: {$absoluteFilePath}");
        }

        $text = $this->extractRawText($absoluteFilePath);
        $rawPages = preg_split('/\f/u', $text) ?: [$text];
        $pages = [];

        foreach ($rawPages as $index => $rawPage) {
            $content = $this->normalize($rawPage);

            if ($content === '') {
                continue;
            }

            $pages[] = [
                'page' => $index + 1,
                'content' => $content,
            ];
        }

        if ($pages === []) {
            throw new RuntimeException('PDF text extraction returned empty content.');
        }

        return $pages;
    }

    private function extractRawText(string $absoluteFilePath): string
    {
        $binary = config('services.pdftotext.binary');
        $binary = is_string($binary) && trim($binary) !== '' ? trim($binary) : null;

        try {
            $text = Pdf::getText(
                pdf: $absoluteFilePath,
                binPath: $binary,
                options: ['layout'],
                timeout: 120
            );
        } catch (Throwable $exception) {
            throw new RuntimeException('Unable to extract text from PDF: '.$exception->getMessage(), previous: $exception);
        }

        return $text;
    }

    private function normalize(string $text): string
    {
        $text = $this->ensureUtf8($text);
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        // PDF icon fonts often extract as private-use Unicode glyphs or repeated symbol noise.
        // These are not real document text and would otherwise pollute chunks and retrieval.
        $text = preg_replace('/[\x{E000}-\x{F8FF}\x{FFFD}]/u', '', $text) ?? $text;
        $text = preg_replace('/[^\P{C}\n\t]/u', '', $text) ?? $text;

        $lines = preg_split('/\n/', $text) ?: [];
        $lines = array_map(function (string $line): string {
            $line = preg_replace('/[ \t]+/u', ' ', $line) ?? $line;
            $line = trim($line);

            if ($line === '') {
                return '';
            }

            // Symbol-only lines usually come from PDF icon fonts, bullets, badges, or UI glyphs.
            // Keep lines that contain letters or numbers so normal punctuation/currency text survives.
            if (! preg_match('/[\p{L}\p{N}]/u', $line)) {
                return '';
            }

            $line = preg_replace('/([^\p{L}\p{N}\p{P}\p{Sc}\s])\1{2,}/u', '$1', $line) ?? $line;

            return $line;
        }, $lines);

        $text = implode("\n", $lines);
        $text = preg_replace('/[ \t]+/u', ' ', $text) ?? $text;
        $text = preg_replace('/ *\n */u', "\n", $text) ?? $text;
        $text = preg_replace("/\n{3,}/u", "\n\n", $text) ?? $text;

        return trim($text);
    }

    private function ensureUtf8(string $text): string
    {
        if (mb_check_encoding($text, 'UTF-8')) {
            return $text;
        }

        $encoding = mb_detect_encoding($text, ['UTF-8', 'Windows-1252', 'ISO-8859-1'], true) ?: 'Windows-1252';
        $converted = @mb_convert_encoding($text, 'UTF-8', $encoding);

        if (is_string($converted) && mb_check_encoding($converted, 'UTF-8')) {
            return $converted;
        }

        $stripped = @iconv('UTF-8', 'UTF-8//IGNORE', $text);

        if (is_string($stripped)) {
            return $stripped;
        }

        return preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F-\xFF]/', '', $text) ?? '';
    }
}
