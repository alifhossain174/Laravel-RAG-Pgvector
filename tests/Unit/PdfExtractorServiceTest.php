<?php

namespace Tests\Unit;

use App\Services\PdfExtractorService;
use ReflectionMethod;
use Tests\TestCase;

class PdfExtractorServiceTest extends TestCase
{
    public function test_normalize_removes_icon_font_artifacts_without_removing_document_text(): void
    {
        $service = new PdfExtractorService();
        $normalize = new ReflectionMethod($service, 'normalize');

        $text = "Invoice total: $1,250.00\n"
            ."\u{E001}\u{E002}\u{E003}\n"
            ."● ● ● ●\n"
            ."Valid line with punctuation, numbers 123, and € currency.\n"
            ."Broken\0control\x07characters\n"
            ."%%%%%%%\n";

        $normalized = $normalize->invoke($service, $text);

        $this->assertStringContainsString('Invoice total: $1,250.00', $normalized);
        $this->assertStringContainsString('Valid line with punctuation, numbers 123, and € currency.', $normalized);
        $this->assertStringContainsString('Brokencontrolcharacters', $normalized);
        $this->assertStringNotContainsString("\u{E001}", $normalized);
        $this->assertStringNotContainsString('● ● ● ●', $normalized);
        $this->assertStringNotContainsString('%%%%%%%', $normalized);
    }

    public function test_normalize_repairs_invalid_windows_1252_bytes(): void
    {
        $service = new PdfExtractorService();
        $normalize = new ReflectionMethod($service, 'normalize');

        $text = "Valid text before invalid byte ".chr(0x86)." and after.";
        $normalized = $normalize->invoke($service, $text);

        $this->assertTrue(mb_check_encoding($normalized, 'UTF-8'));
        $this->assertStringContainsString('Valid text before invalid byte', $normalized);
        $this->assertStringContainsString('and after.', $normalized);
    }
}
