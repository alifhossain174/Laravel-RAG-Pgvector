<?php

namespace App\Jobs;

use App\Models\Document;
use App\Services\DocumentChunker;
use App\Services\DocumentTextExtractorService;
use App\Services\UsageTrackingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ProcessDocumentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(
        public readonly int $documentId,
        public readonly bool $dispatchEmbeddings = true
    ) {}

    public function handle(DocumentTextExtractorService $extractor, DocumentChunker $chunker, ?UsageTrackingService $usage = null): void
    {
        $usage ??= app(UsageTrackingService::class);

        $document = Document::query()->find($this->documentId);

        if (! $document) {
            Log::info('Document processing skipped because document no longer exists.', [
                'document_id' => $this->documentId,
            ]);

            return;
        }

        if ($document->user()->where('is_suspended', true)->exists()) {
            Log::info('Document processing skipped because the owning user is suspended.', [
                'document_id' => $document->id,
                'user_id' => $document->user_id,
            ]);

            return;
        }

        try {
            $document->forceFill([
                'status' => Document::STATUS_PROCESSING,
                'failed_reason' => null,
                'total_chunks' => 0,
            ])->save();

            $usage->log([
                'user_id' => $document->user_id,
                'document_id' => $document->id,
                'action_type' => 'document_processing_started',
            ]);

            $document->chunks()->delete();

            $absolutePath = Storage::disk('local')->path($document->file_path);

            if (! is_file($absolutePath)) {
                throw new \RuntimeException('Stored document file was not found.');
            }

            $pages = $extractor->extract($document, $absolutePath);

            $usage->log([
                'user_id' => $document->user_id,
                'document_id' => $document->id,
                'action_type' => 'text_extracted',
                'metadata' => [
                    'page_count' => $this->totalPages($pages),
                    'character_count' => $this->characterCount($pages),
                    'extraction_methods' => $this->extractionMethods($pages),
                ],
            ]);

            $chunks = $chunker->chunkPages($pages);

            if ($chunks === []) {
                throw new \RuntimeException('No readable text was found in this document.');
            }

            DB::transaction(function () use ($document, $chunks, $pages) {
                $document->forceFill([
                    'status' => Document::STATUS_TEXT_EXTRACTED,
                    'total_pages' => $this->totalPages($pages),
                ])->save();

                foreach ($chunks as $chunk) {
                    $document->chunks()->create($chunk);
                }

                $document->forceFill([
                    'status' => Document::STATUS_CHUNKED,
                    'total_chunks' => count($chunks),
                    'processed_at' => now(),
                    'failed_reason' => null,
                ])->save();
            });

            $usage->log([
                'user_id' => $document->user_id,
                'document_id' => $document->id,
                'action_type' => 'chunks_created',
                'metadata' => [
                    'chunk_count' => count($chunks),
                    'page_count' => $this->totalPages($pages),
                ],
            ]);

            if ($this->dispatchEmbeddings) {
                GenerateDocumentEmbeddingsJob::dispatch($document->id);
            }
        } catch (Throwable $exception) {
            Log::error('Document processing failed.', [
                'document_id' => $this->documentId,
                'message' => $exception->getMessage(),
                'exception' => $exception,
            ]);

            Document::query()
                ->whereKey($this->documentId)
                ->update([
                    'status' => Document::STATUS_FAILED,
                    'failed_reason' => $this->cleanFailureReason($exception->getMessage()),
                    'processed_at' => now(),
                ]);
        }
    }

    private function cleanFailureReason(string $message): string
    {
        if (! mb_check_encoding($message, 'UTF-8')) {
            $message = @mb_convert_encoding($message, 'UTF-8', 'Windows-1252') ?: 'Document processing failed.';
        }

        $message = preg_replace('/[\x{E000}-\x{F8FF}\x{FFFD}]/u', '', $message) ?? $message;
        $message = preg_replace('/[^\P{C}\n\t]/u', '', $message) ?? $message;
        $message = trim($message);

        return str($message === '' ? 'Document processing failed.' : $message)->limit(1000)->toString();
    }

    private function totalPages(array $pages): ?int
    {
        $pageNumbers = collect($pages)
            ->pluck('page')
            ->filter(fn (mixed $page): bool => is_numeric($page))
            ->map(fn (mixed $page): int => (int) $page)
            ->unique()
            ->values();

        return $pageNumbers->isEmpty() ? null : $pageNumbers->count();
    }

    private function characterCount(array $pages): int
    {
        return collect($pages)
            ->sum(fn (array $page): int => mb_strlen((string) ($page['content'] ?? '')));
    }

    private function extractionMethods(array $pages): array
    {
        return collect($pages)
            ->flatMap(function (array $page): array {
                $metadata = is_array($page['metadata'] ?? null) ? $page['metadata'] : [];

                return array_filter([
                    $metadata['extraction_method'] ?? null,
                    $metadata['source'] ?? null,
                ]);
            })
            ->unique()
            ->values()
            ->all();
    }
}
