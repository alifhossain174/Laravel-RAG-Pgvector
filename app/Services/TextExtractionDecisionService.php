<?php

namespace App\Services;

use Throwable;

class TextExtractionDecisionService
{
    /**
     * @return array{
     *     requires_ocr: bool,
     *     reason: string,
     *     message: string,
     *     character_count: int,
     *     page_count: int,
     *     text_density_per_page: float
     * }
     */
    public function decide(array $pages = [], ?Throwable $exception = null): array
    {
        $pageCount = count($pages);
        $characterCount = $this->characterCount($pages);
        $density = $pageCount === 0 ? 0.0 : round($characterCount / $pageCount, 2);

        if ($exception !== null) {
            return $this->decision(
                true,
                'native_extraction_failed',
                'Native PDF text extraction failed and OCR is required.',
                $characterCount,
                $pageCount,
                $density
            );
        }

        if ($pages === []) {
            return $this->decision(
                true,
                'empty_or_image_only_pdf',
                'PDF text extraction returned no readable pages and OCR is required.',
                $characterCount,
                $pageCount,
                $density
            );
        }

        if ($characterCount === 0) {
            return $this->decision(
                true,
                'mostly_whitespace',
                'PDF text extraction returned only whitespace and OCR is required.',
                $characterCount,
                $pageCount,
                $density
            );
        }

        if ($characterCount < $this->minimumTextCharacters()) {
            return $this->decision(
                true,
                'extremely_low_text',
                'Extracted PDF text is below the configured minimum threshold and OCR is required.',
                $characterCount,
                $pageCount,
                $density
            );
        }

        if ($density < $this->minimumTextDensityPerPage()) {
            return $this->decision(
                true,
                'low_text_density',
                'Extracted PDF text density is too low and OCR is required.',
                $characterCount,
                $pageCount,
                $density
            );
        }

        return $this->decision(
            false,
            'native_text_sufficient',
            'Native PDF text extraction is sufficient.',
            $characterCount,
            $pageCount,
            $density
        );
    }

    public function requiresOcr(array $pages = [], ?Throwable $exception = null): bool
    {
        return $this->decide($pages, $exception)['requires_ocr'];
    }

    private function characterCount(array $pages): int
    {
        $text = trim(implode("\n\n", array_map(
            fn (array $page): string => (string) ($page['content'] ?? ''),
            $pages
        )));

        $text = preg_replace('/\s+/u', '', $text) ?? '';

        return mb_strlen($text, 'UTF-8');
    }

    private function minimumTextCharacters(): int
    {
        return max(1, (int) config('services.ocr.minimum_text_characters', 20));
    }

    private function minimumTextDensityPerPage(): int
    {
        return max(1, (int) config('services.ocr.minimum_text_density_per_page', 10));
    }

    private function decision(
        bool $requiresOcr,
        string $reason,
        string $message,
        int $characterCount,
        int $pageCount,
        float $density
    ): array {
        return [
            'requires_ocr' => $requiresOcr,
            'reason' => $reason,
            'message' => $message,
            'character_count' => $characterCount,
            'page_count' => $pageCount,
            'text_density_per_page' => $density,
        ];
    }
}
