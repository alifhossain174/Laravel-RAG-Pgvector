<?php

namespace Tests\Unit;

use App\Services\WordExtractorService;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use Tests\TestCase;

class WordExtractorServiceTest extends TestCase
{
    public function test_extracts_paragraph_headings_and_table_text_from_docx(): void
    {
        $path = $this->makeDocxFixture();

        $pages = app(WordExtractorService::class)->extractPages($path);

        $this->assertCount(1, $pages);
        $this->assertNull($pages[0]['page']);
        $this->assertSame('docx', $pages[0]['metadata']['source_type']);
        $this->assertSame('word_text_extraction', $pages[0]['metadata']['extraction_method']);
        $this->assertStringContainsString('Mobile Purchase Notes', $pages[0]['content']);
        $this->assertStringContainsString('Budget phones should balance total price and battery life.', $pages[0]['content']);
        $this->assertStringContainsString('Model | Battery', $pages[0]['content']);
        $this->assertStringContainsString('Xiaomi 17T | 6500mAh', $pages[0]['content']);

        @unlink($path);
    }

    private function makeDocxFixture(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'documind-word-');
        @unlink($path);
        $path .= '.docx';

        $phpWord = new PhpWord();
        $section = $phpWord->addSection();
        $section->addTitle('Mobile Purchase Notes', 1);
        $section->addText('Budget phones should balance total price and battery life.');

        $table = $section->addTable();
        $table->addRow();
        $table->addCell()->addText('Model');
        $table->addCell()->addText('Battery');
        $table->addRow();
        $table->addCell()->addText('Xiaomi 17T');
        $table->addCell()->addText('6500mAh');

        IOFactory::createWriter($phpWord, 'Word2007')->save($path);

        return $path;
    }
}
