<?php

namespace App\Services;

class DocumentChunker
{
    public function chunk(string $text, int $chunkSize = 3000, int $overlap = 300): array
    {
        return $this->chunkPages([
            ['page' => null, 'content' => $text],
        ], $chunkSize, $overlap);
    }

    public function chunkPages(array $pages, int $chunkSize = 3000, int $overlap = 300): array
    {
        [$text, $pageRanges] = $this->flattenPages($pages);
        $text = trim($text);

        if ($text === '') {
            return [];
        }

        return $this->buildChunks($text, $pageRanges, $chunkSize, $overlap);
    }

    private function buildChunks(string $text, array $pageRanges, int $chunkSize, int $overlap): array
    {
        $text = trim($text);

        if ($text === '') {
            return [];
        }

        $chunks = [];
        $length = mb_strlen($text, 'UTF-8');
        $start = 0;
        $index = 1;

        while ($start < $length) {
            $targetEnd = min($start + $chunkSize, $length);
            $end = $this->findBoundary($text, $start, $targetEnd, $chunkSize);

            if ($end <= $start) {
                $end = $targetEnd;
            }

            $content = trim(mb_substr($text, $start, $end - $start, 'UTF-8'));

            if ($content !== '') {
                [$pageStart, $pageEnd, $sourcePages] = $this->pageRangeForOffsets($pageRanges, $start, $end);
                $extractionMethods = $this->extractionMethods($sourcePages);

                $chunks[] = [
                    'chunk_index' => $index,
                    'page_start' => $pageStart,
                    'page_end' => $pageEnd,
                    'content' => $content,
                    'token_count' => (int) ceil(mb_strlen($content, 'UTF-8') / 4),
                    'metadata' => [
                        'chunk_size' => $chunkSize,
                        'chunk_overlap' => $overlap,
                        'source' => $this->sourceForMethods($extractionMethods),
                        'extraction_methods' => $extractionMethods,
                        'pages' => $sourcePages,
                    ],
                ];

                $index++;
            }

            if ($end >= $length) {
                break;
            }

            $nextStart = max($end - $overlap, $start + 1);
            $start = $this->trimStartToWordBoundary($text, $nextStart, $length);
        }

        return $chunks;
    }

    private function flattenPages(array $pages): array
    {
        $text = '';
        $pageRanges = [];

        foreach ($pages as $page) {
            $content = trim((string) ($page['content'] ?? ''));

            if ($content === '') {
                continue;
            }

            if ($text !== '') {
                $text .= "\n\n";
            }

            $start = mb_strlen($text, 'UTF-8');
            $text .= $content;
            $end = mb_strlen($text, 'UTF-8');

            if (isset($page['page']) && is_numeric($page['page'])) {
                $pageNumber = (int) $page['page'];
                $metadata = is_array($page['metadata'] ?? null) ? $page['metadata'] : [];

                $pageRanges[] = [
                    'page' => $pageNumber,
                    'start' => $start,
                    'end' => $end,
                    'metadata' => array_merge([
                        'page' => $pageNumber,
                        'extraction_method' => 'native',
                    ], $metadata),
                ];
            }
        }

        return [$text, $pageRanges];
    }

    private function pageRangeForOffsets(array $pageRanges, int $start, int $end): array
    {
        $pages = [];
        $sourcePages = [];

        foreach ($pageRanges as $range) {
            if ($range['end'] <= $start || $range['start'] >= $end) {
                continue;
            }

            $pages[] = $range['page'];
            $sourcePages[] = $range['metadata'] ?? [
                'page' => $range['page'],
                'extraction_method' => 'native',
            ];
        }

        if ($pages === []) {
            return [null, null, []];
        }

        return [min($pages), max($pages), $this->uniqueSourcePages($sourcePages)];
    }

    private function extractionMethods(array $sourcePages): array
    {
        $methods = [];

        foreach ($sourcePages as $page) {
            $method = (string) ($page['extraction_method'] ?? '');

            if ($method !== '' && ! in_array($method, $methods, true)) {
                $methods[] = $method;
            }
        }

        return $methods;
    }

    private function sourceForMethods(array $methods): string
    {
        if ($methods === ['ocr']) {
            return 'pdf_ocr';
        }

        if (in_array('ocr', $methods, true)) {
            return 'pdf_mixed_extraction';
        }

        return 'pdf_text_extraction';
    }

    private function uniqueSourcePages(array $sourcePages): array
    {
        $unique = [];
        $seen = [];

        foreach ($sourcePages as $page) {
            $pageNumber = $page['page'] ?? null;
            $method = (string) ($page['extraction_method'] ?? 'native');
            $key = $pageNumber.'|'.$method;

            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $unique[] = [
                'page' => is_numeric($pageNumber) ? (int) $pageNumber : null,
                'extraction_method' => $method,
            ];
        }

        return $unique;
    }

    private function findBoundary(string $text, int $start, int $targetEnd, int $chunkSize): int
    {
        if ($targetEnd >= mb_strlen($text, 'UTF-8')) {
            return $targetEnd;
        }

        $minimum = $start + (int) floor($chunkSize * 0.6);
        $candidate = mb_substr($text, $start, $targetEnd - $start, 'UTF-8');

        foreach (["\n\n", ". ", "! ", "? ", "\n"] as $boundary) {
            $position = mb_strrpos($candidate, $boundary, 0, 'UTF-8');

            if ($position !== false) {
                $end = $start + $position + mb_strlen($boundary, 'UTF-8');

                if ($end >= $minimum) {
                    return $end;
                }
            }
        }

        return $targetEnd;
    }

    private function trimStartToWordBoundary(string $text, int $start, int $length): int
    {
        while ($start < $length && preg_match('/\s/u', mb_substr($text, $start, 1, 'UTF-8')) === 1) {
            $start++;
        }

        return $start;
    }
}
