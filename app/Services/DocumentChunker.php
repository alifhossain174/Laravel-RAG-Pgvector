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
                [$pageStart, $pageEnd] = $this->pageRangeForOffsets($pageRanges, $start, $end);

                $chunks[] = [
                    'chunk_index' => $index,
                    'page_start' => $pageStart,
                    'page_end' => $pageEnd,
                    'content' => $content,
                    'token_count' => (int) ceil(mb_strlen($content, 'UTF-8') / 4),
                    'metadata' => [
                        'chunk_size' => $chunkSize,
                        'chunk_overlap' => $overlap,
                        'source' => 'pdf_text_extraction',
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
                $pageRanges[] = [
                    'page' => (int) $page['page'],
                    'start' => $start,
                    'end' => $end,
                ];
            }
        }

        return [$text, $pageRanges];
    }

    private function pageRangeForOffsets(array $pageRanges, int $start, int $end): array
    {
        $pages = [];

        foreach ($pageRanges as $range) {
            if ($range['end'] <= $start || $range['start'] >= $end) {
                continue;
            }

            $pages[] = $range['page'];
        }

        if ($pages === []) {
            return [null, null];
        }

        return [min($pages), max($pages)];
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
