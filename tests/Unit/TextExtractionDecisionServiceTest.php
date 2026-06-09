<?php

namespace Tests\Unit;

use App\Services\TextExtractionDecisionService;
use RuntimeException;
use Tests\TestCase;

class TextExtractionDecisionServiceTest extends TestCase
{
    public function test_requires_ocr_when_native_extraction_fails(): void
    {
        $decision = app(TextExtractionDecisionService::class)->decide(
            exception: new RuntimeException('pdftotext failed')
        );

        $this->assertTrue($decision['requires_ocr']);
        $this->assertSame('native_extraction_failed', $decision['reason']);
    }

    public function test_requires_ocr_for_empty_or_low_density_text(): void
    {
        config([
            'services.ocr.minimum_text_characters' => 20,
            'services.ocr.minimum_text_density_per_page' => 10,
        ]);

        $service = app(TextExtractionDecisionService::class);

        $empty = $service->decide([]);
        $whitespace = $service->decide([
            ['page' => 1, 'content' => " \n\t "],
        ]);
        $tooShort = $service->decide([
            ['page' => 1, 'content' => 'Tiny'],
        ]);
        $lowDensity = $service->decide([
            ['page' => 1, 'content' => 'Enough text for one page'],
            ['page' => 2, 'content' => ''],
            ['page' => 3, 'content' => ''],
        ]);

        $this->assertSame('empty_or_image_only_pdf', $empty['reason']);
        $this->assertSame('mostly_whitespace', $whitespace['reason']);
        $this->assertSame('extremely_low_text', $tooShort['reason']);
        $this->assertSame('low_text_density', $lowDensity['reason']);
    }

    public function test_accepts_sufficient_native_text(): void
    {
        config([
            'services.ocr.minimum_text_characters' => 20,
            'services.ocr.minimum_text_density_per_page' => 10,
        ]);

        $decision = app(TextExtractionDecisionService::class)->decide([
            ['page' => 1, 'content' => str_repeat('Readable native text. ', 4)],
        ]);

        $this->assertFalse($decision['requires_ocr']);
        $this->assertSame('native_text_sufficient', $decision['reason']);
    }
}
