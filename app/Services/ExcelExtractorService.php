<?php

namespace App\Services;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\IReader;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use RuntimeException;
use Throwable;

class ExcelExtractorService
{
    private const ROWS_PER_SEGMENT = 50;

    /**
     * @return array<int, array{page: null, content: string, metadata: array<string, mixed>}>
     */
    public function extractPages(string $absoluteFilePath, ?string $extension = null): array
    {
        if (! is_file($absoluteFilePath) || ! is_readable($absoluteFilePath)) {
            throw new RuntimeException("Spreadsheet document is not readable: {$absoluteFilePath}");
        }

        $extension = strtolower((string) ($extension ?: pathinfo($absoluteFilePath, PATHINFO_EXTENSION)));

        return match ($extension) {
            'xlsx' => $this->extractXlsxPages($absoluteFilePath),
            'csv' => $this->extractCsvPages($absoluteFilePath),
            default => throw new RuntimeException('Unsupported spreadsheet type. Please upload XLSX or CSV.'),
        };
    }

    public function extractText(string $absoluteFilePath): string
    {
        return trim(implode("\n\n", array_column($this->extractPages($absoluteFilePath), 'content')));
    }

    /**
     * @return array<int, array{page: null, content: string, metadata: array<string, mixed>}>
     */
    private function extractXlsxPages(string $absoluteFilePath): array
    {
        try {
            $reader = IOFactory::createReader('Xlsx');
            $this->configureReader($reader);

            $spreadsheet = $reader->load($absoluteFilePath);
        } catch (Throwable $exception) {
            throw new RuntimeException('Unable to extract text from Excel document: '.$exception->getMessage(), previous: $exception);
        }

        try {
            $pages = [];

            foreach ($spreadsheet->getWorksheetIterator() as $worksheet) {
                array_push($pages, ...$this->worksheetPages($worksheet, 'xlsx'));
            }
        } finally {
            $spreadsheet->disconnectWorksheets();
        }

        if ($pages === []) {
            throw new RuntimeException('No readable spreadsheet text was found.');
        }

        return $pages;
    }

    private function configureReader(IReader $reader): void
    {
        $reader->setReadDataOnly(true);

        if (method_exists($reader, 'setReadEmptyCells')) {
            $reader->setReadEmptyCells(false);
        }

        if (method_exists($reader, 'setIncludeCharts')) {
            $reader->setIncludeCharts(false);
        }
    }

    /**
     * @return array<int, array{page: null, content: string, metadata: array<string, mixed>}>
     */
    private function worksheetPages(Worksheet $worksheet, string $sourceType): array
    {
        $highestRow = $worksheet->getHighestDataRow();
        $highestColumn = $worksheet->getHighestDataColumn();
        $columnCount = Coordinate::columnIndexFromString($highestColumn);

        if ($highestRow < 1 || $columnCount < 1) {
            return [];
        }

        $rows = [];
        $headerRowNumber = null;
        $headers = [];

        for ($rowNumber = 1; $rowNumber <= $highestRow; $rowNumber++) {
            $row = $this->normalizeRow($this->readWorksheetRow($worksheet, $rowNumber, $highestColumn));

            if ($this->rowIsEmpty($row)) {
                continue;
            }

            if ($headerRowNumber === null) {
                $headerRowNumber = $rowNumber;
                $headers = $this->headersFromRow($row, $columnCount);

                continue;
            }

            $line = $this->rowLine($rowNumber, $headers, $row);

            if ($line !== '') {
                $rows[] = [
                    'number' => $rowNumber,
                    'line' => $line,
                ];
            }
        }

        if ($headerRowNumber === null) {
            return [];
        }

        if ($rows === []) {
            return [
                $this->pageForLines(
                    $worksheet->getTitle(),
                    $sourceType,
                    $headerRowNumber,
                    $headerRowNumber,
                    [$this->headersLine($headers)]
                ),
            ];
        }

        $pages = [];

        foreach (array_chunk($rows, self::ROWS_PER_SEGMENT) as $rowChunk) {
            $firstRow = $rowChunk[0]['number'];
            $lastRow = $rowChunk[array_key_last($rowChunk)]['number'];
            $lines = [
                $this->headersLine($headers),
                ...array_column($rowChunk, 'line'),
            ];

            $pages[] = $this->pageForLines($worksheet->getTitle(), $sourceType, $firstRow, $lastRow, $lines);
        }

        return $pages;
    }

    /**
     * @return array<int, mixed>
     */
    private function readWorksheetRow(Worksheet $worksheet, int $rowNumber, string $highestColumn): array
    {
        try {
            return $worksheet->rangeToArray("A{$rowNumber}:{$highestColumn}{$rowNumber}", null, true, true, false)[0] ?? [];
        } catch (Throwable) {
            return $this->readWorksheetRowFromCells($worksheet, $rowNumber, Coordinate::columnIndexFromString($highestColumn));
        }
    }

    /**
     * @return array<int, mixed>
     */
    private function readWorksheetRowFromCells(Worksheet $worksheet, int $rowNumber, int $columnCount): array
    {
        $values = [];

        for ($column = 1; $column <= $columnCount; $column++) {
            $coordinate = Coordinate::stringFromColumnIndex($column).$rowNumber;

            try {
                $values[] = $worksheet->getCell($coordinate)->getCalculatedValue();
            } catch (Throwable) {
                $values[] = $worksheet->getCell($coordinate)->getFormattedValue();
            }
        }

        return $values;
    }

    /**
     * @return array<int, array{page: null, content: string, metadata: array<string, mixed>}>
     */
    private function extractCsvPages(string $absoluteFilePath): array
    {
        $delimiter = $this->detectCsvDelimiter($absoluteFilePath);
        $handle = fopen($absoluteFilePath, 'rb');

        if ($handle === false) {
            throw new RuntimeException("CSV document is not readable: {$absoluteFilePath}");
        }

        $headerRowNumber = null;
        $headers = [];
        $rows = [];
        $rowNumber = 0;

        try {
            while (($row = fgetcsv($handle, null, $delimiter)) !== false) {
                $rowNumber++;
                $row = $this->normalizeRow($row);

                if ($this->rowIsEmpty($row)) {
                    continue;
                }

                if ($headerRowNumber === null) {
                    $headerRowNumber = $rowNumber;
                    $headers = $this->headersFromRow($row, count($row));

                    continue;
                }

                $line = $this->rowLine($rowNumber, $headers, $row);

                if ($line !== '') {
                    $rows[] = [
                        'number' => $rowNumber,
                        'line' => $line,
                    ];
                }
            }
        } finally {
            fclose($handle);
        }

        if ($headerRowNumber === null) {
            throw new RuntimeException('No readable spreadsheet text was found.');
        }

        if ($rows === []) {
            return [
                $this->pageForLines('CSV', 'csv', $headerRowNumber, $headerRowNumber, [$this->headersLine($headers)]),
            ];
        }

        $pages = [];

        foreach (array_chunk($rows, self::ROWS_PER_SEGMENT) as $rowChunk) {
            $firstRow = $rowChunk[0]['number'];
            $lastRow = $rowChunk[array_key_last($rowChunk)]['number'];
            $lines = [
                $this->headersLine($headers),
                ...array_column($rowChunk, 'line'),
            ];

            $pages[] = $this->pageForLines('CSV', 'csv', $firstRow, $lastRow, $lines);
        }

        return $pages;
    }

    private function detectCsvDelimiter(string $absoluteFilePath): string
    {
        $handle = fopen($absoluteFilePath, 'rb');

        if ($handle === false) {
            return ',';
        }

        $sampleLines = [];

        try {
            while (($line = fgets($handle)) !== false && count($sampleLines) < 5) {
                $line = trim($line);

                if ($line !== '') {
                    $sampleLines[] = $line;
                }
            }
        } finally {
            fclose($handle);
        }

        $delimiters = [',', ';', "\t", '|'];
        $scores = array_fill_keys($delimiters, 0);

        foreach ($sampleLines as $line) {
            foreach ($delimiters as $delimiter) {
                $scores[$delimiter] += max(0, count(str_getcsv($line, $delimiter)) - 1);
            }
        }

        arsort($scores);

        return (string) array_key_first($scores);
    }

    /**
     * @param  array<int, mixed>  $row
     * @return array<int, string>
     */
    private function normalizeRow(array $row): array
    {
        return array_map(fn (mixed $value): string => $this->normalizeInline($this->stringValue($value)), array_values($row));
    }

    private function stringValue(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_scalar($value)) {
            return (string) $value;
        }

        return '';
    }

    /**
     * @param  array<int, string>  $row
     */
    private function rowIsEmpty(array $row): bool
    {
        foreach ($row as $value) {
            if ($value !== '') {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<int, string>  $row
     * @return array<int, string>
     */
    private function headersFromRow(array $row, int $columnCount): array
    {
        $headers = [];

        for ($index = 0; $index < $columnCount; $index++) {
            $header = $row[$index] ?? '';
            $headers[] = $header !== '' ? $header : 'Column '.Coordinate::stringFromColumnIndex($index + 1);
        }

        return $headers;
    }

    /**
     * @param  array<int, string>  $headers
     */
    private function headersLine(array $headers): string
    {
        return 'Headers: '.implode('; ', $headers);
    }

    /**
     * @param  array<int, string>  $headers
     * @param  array<int, string>  $row
     */
    private function rowLine(int $rowNumber, array $headers, array $row): string
    {
        $pairs = [];
        $columnCount = max(count($headers), count($row));

        for ($index = 0; $index < $columnCount; $index++) {
            $value = $row[$index] ?? '';

            if ($value === '') {
                continue;
            }

            $header = $headers[$index] ?? 'Column '.Coordinate::stringFromColumnIndex($index + 1);
            $pairs[] = "{$header} = {$value}";
        }

        if ($pairs === []) {
            return '';
        }

        return 'Row '.$rowNumber.': '.implode('; ', $pairs);
    }

    /**
     * @param  array<int, string>  $lines
     * @return array{page: null, content: string, metadata: array<string, mixed>}
     */
    private function pageForLines(string $sheetName, string $sourceType, int $rowStart, int $rowEnd, array $lines): array
    {
        $content = trim(implode("\n", [
            'Sheet: '.$sheetName,
            ...array_filter($lines, fn (string $line): bool => $line !== ''),
        ]));

        return [
            'page' => null,
            'content' => $content,
            'metadata' => [
                'page' => null,
                'source_type' => $sourceType,
                'extraction_method' => 'spreadsheet_text_extraction',
                'sheet_name' => $sheetName,
                'row_start' => $rowStart,
                'row_end' => $rowEnd,
            ],
        ];
    }

    private function normalizeInline(string $text): string
    {
        if (! mb_check_encoding($text, 'UTF-8')) {
            $text = @mb_convert_encoding($text, 'UTF-8', 'Windows-1252') ?: $text;
        }

        $text = preg_replace('/^\xEF\xBB\xBF/u', '', $text) ?? $text;
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = preg_replace('/[^\P{C}\n\t]/u', '', $text) ?? $text;
        $text = preg_replace('/[ \t]+/u', ' ', $text) ?? $text;
        $text = preg_replace('/ *\n */u', ' ', $text) ?? $text;

        return trim($text);
    }
}
