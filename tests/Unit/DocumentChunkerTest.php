<?php

namespace Tests\Unit;

use App\Services\DocumentChunker;
use Tests\TestCase;

class DocumentChunkerTest extends TestCase
{
    public function test_chunk_pages_sets_page_start_and_page_end(): void
    {
        $chunks = app(DocumentChunker::class)->chunkPages([
            ['page' => 5, 'content' => str_repeat('Page five sentence. ', 20)],
            ['page' => 6, 'content' => str_repeat('Page six sentence. ', 20)],
        ], chunkSize: 900, overlap: 100);

        $this->assertNotEmpty($chunks);
        $this->assertSame(5, $chunks[0]['page_start']);
        $this->assertSame(6, $chunks[0]['page_end']);
        $this->assertSame('pdf_text_extraction', $chunks[0]['metadata']['source']);
        $this->assertSame(['native'], $chunks[0]['metadata']['extraction_methods']);
    }

    public function test_chunk_pages_preserves_ocr_extraction_metadata(): void
    {
        $chunks = app(DocumentChunker::class)->chunkPages([
            [
                'page' => 3,
                'content' => str_repeat('Scanned page text. ', 40),
                'metadata' => [
                    'page' => 3,
                    'extraction_method' => 'ocr',
                ],
            ],
        ], chunkSize: 900, overlap: 100);

        $this->assertNotEmpty($chunks);
        $this->assertSame('pdf_ocr', $chunks[0]['metadata']['source']);
        $this->assertSame(['ocr'], $chunks[0]['metadata']['extraction_methods']);
        $this->assertSame([
            ['page' => 3, 'extraction_method' => 'ocr'],
        ], $chunks[0]['metadata']['pages']);
    }

    public function test_chunk_pages_does_not_split_multibyte_characters(): void
    {
        $chunks = app(DocumentChunker::class)->chunkPages([
            ['page' => 1, 'content' => str_repeat('Fare details ➔ taxes ● commission € amount. ', 30)],
        ], chunkSize: 95, overlap: 10);

        $this->assertNotEmpty($chunks);

        foreach ($chunks as $chunk) {
            $this->assertTrue(mb_check_encoding($chunk['content'], 'UTF-8'));
        }
    }
}
