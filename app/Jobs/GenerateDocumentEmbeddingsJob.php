<?php

namespace App\Jobs;

use App\Models\Document;
use App\Services\EmbeddingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class GenerateDocumentEmbeddingsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(public readonly int $documentId) {}

    public function backoff(): array
    {
        return [10, 30, 60];
    }

    public function handle(EmbeddingService $embeddings): void
    {
        $document = Document::query()->find($this->documentId);

        if (! $document) {
            Log::info('Embedding generation skipped because document no longer exists.', [
                'document_id' => $this->documentId,
            ]);

            return;
        }

        if ($document->user()->where('is_suspended', true)->exists()) {
            Log::info('Embedding generation skipped because the owning user is suspended.', [
                'document_id' => $document->id,
                'user_id' => $document->user_id,
            ]);

            return;
        }

        if ($document->chunks()->count() === 0) {
            Log::info('Embedding generation skipped because document has no chunks.', [
                'document_id' => $document->id,
            ]);

            return;
        }

        try {
            $query = $document->chunks()
                ->whereNull('embedding')
                ->orderBy('chunk_index');

            if (! $query->exists()) {
                $document->forceFill([
                    'status' => Document::STATUS_READY,
                    'processed_at' => now(),
                    'failed_reason' => null,
                ])->save();

                return;
            }

            $query->chunkById(25, function ($chunks) use ($embeddings) {
                foreach ($chunks as $chunk) {
                    $embedding = $embeddings->embedText($chunk->content);

                    $this->storeEmbedding(
                        chunkId: $chunk->id,
                        embedding: $embedding,
                        provider: $embeddings->provider(),
                        model: $embeddings->model()
                    );

                    usleep(200_000);
                }
            });

            $document->forceFill([
                'status' => Document::STATUS_EMBEDDED,
                'processed_at' => now(),
                'failed_reason' => null,
            ])->save();

            $document->forceFill([
                'status' => Document::STATUS_READY,
                'processed_at' => now(),
            ])->save();
        } catch (Throwable $exception) {
            Log::error('Document embedding generation failed.', [
                'document_id' => $this->documentId,
                'message' => $exception->getMessage(),
                'status_code' => $exception->getCode(),
            ]);

            Document::query()
                ->whereKey($this->documentId)
                ->update([
                    'status' => Document::STATUS_FAILED,
                    'failed_reason' => $exception->getMessage(),
                    'processed_at' => now(),
                ]);

            if ($exception->getCode() === 429 || $exception->getCode() >= 500) {
                throw $exception;
            }
        }
    }

    public static function formatVectorForPgvector(array $embedding): string
    {
        $values = array_map(function (mixed $value): string {
            if (! is_numeric($value)) {
                throw new \RuntimeException('Embedding vector contains a non-numeric value.');
            }

            return rtrim(rtrim(sprintf('%.10F', (float) $value), '0'), '.');
        }, $embedding);

        return '['.implode(',', $values).']';
    }

    private function storeEmbedding(int $chunkId, array $embedding, string $provider, string $model): void
    {
        $now = now();
        $vector = self::formatVectorForPgvector($embedding);

        DB::statement(
            'UPDATE document_chunks SET embedding = ?::vector, embedded_at = ?, embedding_provider = ?, embedding_model = ?, updated_at = ? WHERE id = ?',
            [$vector, $now, $provider, $model, $now, $chunkId]
        );
    }
}
