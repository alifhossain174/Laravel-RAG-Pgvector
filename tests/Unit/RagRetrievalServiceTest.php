<?php

namespace Tests\Unit;

use App\Services\EmbeddingService;
use App\Services\RagRetrievalService;
use Mockery;
use Tests\TestCase;

class RagRetrievalServiceTest extends TestCase
{
    public function test_summary_questions_use_summary_retrieval_limit(): void
    {
        config([
            'services.rag.top_k' => 6,
            'services.rag.summary_top_k' => 12,
        ]);

        $service = new RagRetrievalService(Mockery::mock(EmbeddingService::class));

        $this->assertSame(12, $service->limitForQuestion('Summarize this document'));
        $this->assertSame(12, $service->limitForQuestion('What are the key points?'));
        $this->assertSame(6, $service->limitForQuestion('What is the renewal deadline?'));
        $this->assertSame(3, $service->limitForQuestion('Summarize this document', 3));
    }
}
