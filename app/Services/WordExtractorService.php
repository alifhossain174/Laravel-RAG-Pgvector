<?php

namespace App\Services;

use PhpOffice\PhpWord\Element\AbstractContainer;
use PhpOffice\PhpWord\Element\Field;
use PhpOffice\PhpWord\Element\Link;
use PhpOffice\PhpWord\Element\ListItem;
use PhpOffice\PhpWord\Element\Table;
use PhpOffice\PhpWord\Element\Text;
use PhpOffice\PhpWord\Element\TextBreak;
use PhpOffice\PhpWord\Element\TextRun;
use PhpOffice\PhpWord\Element\Title;
use PhpOffice\PhpWord\IOFactory;
use RuntimeException;
use Throwable;

class WordExtractorService
{
    /**
     * @return array<int, array{page: null, content: string, metadata: array<string, mixed>}>
     */
    public function extractPages(string $absoluteFilePath): array
    {
        if (! is_file($absoluteFilePath) || ! is_readable($absoluteFilePath)) {
            throw new RuntimeException("Word document is not readable: {$absoluteFilePath}");
        }

        try {
            $document = IOFactory::load($absoluteFilePath, 'Word2007');
        } catch (Throwable $exception) {
            throw new RuntimeException('Unable to extract text from Word document: '.$exception->getMessage(), previous: $exception);
        }

        $lines = [];

        foreach ($document->getSections() as $section) {
            foreach ($section->getElements() as $element) {
                $this->appendElementLines($element, $lines);
            }
        }

        $content = $this->normalizeLines($lines);

        if ($content === '') {
            throw new RuntimeException('No readable text was found in this document.');
        }

        return [
            [
                'page' => null,
                'content' => $content,
                'metadata' => [
                    'page' => null,
                    'source_type' => 'docx',
                    'extraction_method' => 'word_text_extraction',
                ],
            ],
        ];
    }

    public function extractText(string $absoluteFilePath): string
    {
        return trim(implode("\n\n", array_column($this->extractPages($absoluteFilePath), 'content')));
    }

    /**
     * @param  array<int, string>  $lines
     */
    private function appendElementLines(mixed $element, array &$lines): void
    {
        if ($element instanceof TextBreak) {
            $lines[] = '';

            return;
        }

        if ($element instanceof Table) {
            $this->appendTableLines($element, $lines);

            return;
        }

        $text = $this->elementText($element);

        if ($text !== '') {
            $lines[] = $text;

            return;
        }

        if ($element instanceof AbstractContainer || method_exists($element, 'getElements')) {
            foreach ($element->getElements() as $child) {
                $this->appendElementLines($child, $lines);
            }
        }
    }

    /**
     * @param  array<int, string>  $lines
     */
    private function appendTableLines(Table $table, array &$lines): void
    {
        foreach ($table->getRows() as $row) {
            $cells = [];

            foreach ($row->getCells() as $cell) {
                $cellLines = [];

                foreach ($cell->getElements() as $element) {
                    $this->appendElementLines($element, $cellLines);
                }

                $cellText = $this->normalizeInline(implode(' ', array_filter(
                    $cellLines,
                    fn (string $line): bool => trim($line) !== ''
                )));

                if ($cellText !== '') {
                    $cells[] = $cellText;
                }
            }

            if ($cells !== []) {
                $lines[] = implode(' | ', $cells);
            }
        }
    }

    private function elementText(mixed $element): string
    {
        if ($element instanceof Title) {
            return $this->normalizeInline($this->textFromValue($element->getText()));
        }

        if ($element instanceof TextRun) {
            return $this->normalizeInline($this->textRunText($element));
        }

        if ($element instanceof ListItem) {
            $indent = str_repeat('  ', max(0, $element->getDepth()));

            return $this->normalizeInline($indent.'- '.$element->getText());
        }

        if ($element instanceof Text || $element instanceof Link || $element instanceof Field) {
            return $this->normalizeInline($this->textFromValue($element->getText()));
        }

        if (method_exists($element, 'getText') && ! method_exists($element, 'getElements')) {
            return $this->normalizeInline($this->textFromValue($element->getText()));
        }

        return '';
    }

    private function textFromValue(mixed $value): string
    {
        if ($value instanceof TextRun) {
            return $this->textRunText($value);
        }

        if (is_scalar($value) || $value === null) {
            return (string) $value;
        }

        return '';
    }

    private function textRunText(TextRun $textRun): string
    {
        $parts = [];

        foreach ($textRun->getElements() as $element) {
            $text = $this->elementText($element);

            if ($text !== '') {
                $parts[] = $text;
            }
        }

        return implode(' ', $parts);
    }

    /**
     * @param  array<int, string>  $lines
     */
    private function normalizeLines(array $lines): string
    {
        $normalized = [];

        foreach ($lines as $line) {
            $line = $this->normalizeInline($line);

            if ($line !== '') {
                $normalized[] = $line;
            }
        }

        return trim(implode("\n", $normalized));
    }

    private function normalizeInline(string $text): string
    {
        if (! mb_check_encoding($text, 'UTF-8')) {
            $text = @mb_convert_encoding($text, 'UTF-8', 'Windows-1252') ?: $text;
        }

        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = preg_replace('/[^\P{C}\n\t]/u', '', $text) ?? $text;
        $text = preg_replace('/[ \t]+/u', ' ', $text) ?? $text;
        $text = preg_replace('/ *\n */u', ' ', $text) ?? $text;

        return trim($text);
    }
}
