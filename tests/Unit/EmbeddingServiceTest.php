<?php

namespace Tests\Unit;

use App\Jobs\GenerateDocumentEmbeddingsJob;
use App\Services\EmbeddingService;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class EmbeddingServiceTest extends TestCase
{
    public function test_embed_text_returns_valid_gemini_embedding(): void
    {
        config([
            'services.embedding.provider' => 'gemini',
            'services.gemini.api_key' => 'test-key',
            'services.gemini.embedding_model' => 'gemini-embedding-2',
            'services.gemini.embedding_dimensions' => 5,
        ]);

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'embedding' => [
                    'values' => [0.1, -0.2, 0.3, 0.4, -0.5],
                ],
            ]),
        ]);

        $embedding = app(EmbeddingService::class)->embedText(' A useful document chunk. ');

        $this->assertSame([0.1, -0.2, 0.3, 0.4, -0.5], $embedding);

        Http::assertSent(function ($request) {
            return $request->hasHeader('x-goog-api-key', 'test-key')
                && $request['output_dimensionality'] === 5
                && $request['content']['parts'][0]['text'] === 'A useful document chunk.';
        });
    }

    public function test_embed_text_requires_api_key(): void
    {
        config([
            'services.embedding.provider' => 'gemini',
            'services.gemini.api_key' => null,
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Gemini API key is missing');

        app(EmbeddingService::class)->embedText('hello');
    }

    public function test_format_vector_for_pgvector(): void
    {
        $this->assertSame('[0.123,-0.456,1]', GenerateDocumentEmbeddingsJob::formatVectorForPgvector([
            0.123,
            -0.456,
            1.0,
        ]));
    }
}
