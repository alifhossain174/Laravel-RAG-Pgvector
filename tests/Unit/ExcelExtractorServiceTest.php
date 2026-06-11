<?php

namespace Tests\Unit;

use App\Services\ExcelExtractorService;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Tests\TestCase;

class ExcelExtractorServiceTest extends TestCase
{
    public function test_extracts_xlsx_sheet_name_and_header_value_pairs(): void
    {
        $path = $this->makeXlsxFixture();

        try {
            $pages = app(ExcelExtractorService::class)->extractPages($path, 'xlsx');
        } finally {
            @unlink($path);
        }

        $this->assertCount(1, $pages);
        $this->assertSame(null, $pages[0]['page']);
        $this->assertStringContainsString('Sheet: Invoices', $pages[0]['content']);
        $this->assertStringContainsString('Invoice No = INV-001', $pages[0]['content']);
        $this->assertStringContainsString('Customer = John Doe', $pages[0]['content']);
        $this->assertSame('xlsx', $pages[0]['metadata']['source_type']);
        $this->assertSame('spreadsheet_text_extraction', $pages[0]['metadata']['extraction_method']);
        $this->assertSame('Invoices', $pages[0]['metadata']['sheet_name']);
        $this->assertSame(2, $pages[0]['metadata']['row_start']);
        $this->assertSame(2, $pages[0]['metadata']['row_end']);
    }

    public function test_extracts_csv_header_value_pairs(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'documind-csv-');
        file_put_contents($path, "Invoice No,Customer,Amount\nINV-001,John Doe,250.00\n");

        try {
            $pages = app(ExcelExtractorService::class)->extractPages($path, 'csv');
        } finally {
            @unlink($path);
        }

        $this->assertCount(1, $pages);
        $this->assertStringContainsString('Sheet: CSV', $pages[0]['content']);
        $this->assertStringContainsString('Amount = 250.00', $pages[0]['content']);
        $this->assertSame('csv', $pages[0]['metadata']['source_type']);
        $this->assertSame('CSV', $pages[0]['metadata']['sheet_name']);
        $this->assertSame(2, $pages[0]['metadata']['row_start']);
        $this->assertSame(2, $pages[0]['metadata']['row_end']);
    }

    public function test_empty_csv_throws_clear_exception(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'documind-empty-csv-');
        file_put_contents($path, "\n\n");

        try {
            $this->expectExceptionMessage('No readable spreadsheet text was found.');

            app(ExcelExtractorService::class)->extractPages($path, 'csv');
        } finally {
            @unlink($path);
        }
    }

    private function makeXlsxFixture(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'documind-xlsx-');
        @unlink($path);
        $path .= '.xlsx';

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Invoices');
        $sheet->fromArray([
            ['Invoice No', 'Customer', 'Amount'],
            ['INV-001', 'John Doe', 250.00],
        ]);

        IOFactory::createWriter($spreadsheet, 'Xlsx')->save($path);
        $spreadsheet->disconnectWorksheets();

        return $path;
    }
}
