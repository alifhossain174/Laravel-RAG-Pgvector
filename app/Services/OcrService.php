<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use RuntimeException;
use thiagoalessio\TesseractOCR\TesseractOCR;
use Throwable;

class OcrService
{
    public function __construct(
        private readonly PdfImageConverterService $converter,
        private readonly SettingsService $settings,
    ) {}

    public function enabled(): bool
    {
        return $this->settings->ocrEnabled();
    }

    /**
     * @return array<int, array{page: int, content: string, metadata: array{page: int, extraction_method: string}}>
     */
    public function extractPages(string $absolutePdfPath): array
    {
        if (! $this->enabled()) {
            throw new RuntimeException('OCR is disabled. Set OCR_ENABLED=true to process scanned PDFs.');
        }

        $conversion = $this->converter->convertToPngPages($absolutePdfPath);

        try {
            $pages = [];

            foreach ($conversion['images'] as $image) {
                $page = (int) $image['page'];
                $path = (string) $image['path'];

                try {
                    $content = $this->normalize($this->extractImageText($path));
                } catch (Throwable $exception) {
                    Log::error('OCR failed for PDF page image.', [
                        'pdf_path' => $absolutePdfPath,
                        'page' => $page,
                        'image_path' => $path,
                        'message' => $exception->getMessage(),
                    ]);

                    throw new RuntimeException("OCR failed for page {$page}: ".$exception->getMessage(), previous: $exception);
                }

                if ($content === '') {
                    continue;
                }

                $pages[] = [
                    'page' => $page,
                    'content' => $content,
                    'metadata' => [
                        'page' => $page,
                        'extraction_method' => 'ocr',
                    ],
                ];
            }

            if ($pages === []) {
                throw new RuntimeException('OCR completed but no readable text was found.');
            }

            return $pages;
        } finally {
            $this->converter->cleanup((string) $conversion['temporary_directory']);
        }
    }

    private function extractImageText(string $imagePath): string
    {
        if (! class_exists(TesseractOCR::class)) {
            throw new RuntimeException(
                'The Tesseract OCR PHP wrapper is not available. Run composer install or composer dump-autoload, then restart the Laravel queue worker.'
            );
        }

        $ocr = new TesseractOCR($imagePath);

        $binary = config('services.ocr.tesseract_binary');

        if (is_string($binary) && trim($binary) !== '') {
            $ocr->executable(trim($binary));
        }

        $languages = $this->languages();

        if ($languages !== []) {
            $ocr->lang(...$languages);
        }

        return $ocr->run($this->timeout());
    }

    private function normalize(string $text): string
    {
        if (! mb_check_encoding($text, 'UTF-8')) {
            $text = @mb_convert_encoding($text, 'UTF-8', 'Windows-1252') ?: $text;
        }

        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = preg_replace('/[^\P{C}\n\t]/u', '', $text) ?? $text;
        $text = preg_replace('/[ \t]+/u', ' ', $text) ?? $text;
        $text = preg_replace('/ *\n */u', "\n", $text) ?? $text;
        $text = preg_replace("/\n{3,}/u", "\n\n", $text) ?? $text;

        return trim($text);
    }

    private function languages(): array
    {
        $language = $this->settings->ocrLanguage();

        if (! is_string($language) || trim($language) === '') {
            return [];
        }

        return array_values(array_filter(preg_split('/[+,;]/', $language) ?: []));
    }

    private function timeout(): int
    {
        return max(1, (int) config('services.ocr.tesseract_timeout', 120));
    }
}
