<?php

namespace App\Jobs;

use App\Models\Document;
use App\Services\DocumentChunker;
use App\Services\PdfExtractorService;
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
    )
    {
    }

    public function handle(PdfExtractorService $extractor, DocumentChunker $chunker): void
    {
        $document = Document::query()->find($this->documentId);

        if (! $document) {
            Log::info('Document processing skipped because document no longer exists.', [
                'document_id' => $this->documentId,
            ]);

            return;
        }

        try {
            $document->forceFill([
                'status' => Document::STATUS_PROCESSING,
                'failed_reason' => null,
                'total_chunks' => 0,
            ])->save();

            $document->chunks()->delete();

            $absolutePath = Storage::disk('local')->path($document->file_path);

            if (! is_file($absolutePath)) {
                throw new \RuntimeException('Stored PDF file was not found.');
            }

            $pages = $extractor->extractPages($absolutePath);
            $text = trim(implode("\n\n", array_column($pages, 'content')));

            if (strlen(trim($text)) < 20) {
                throw new \RuntimeException('Extracted PDF text is empty or too short to chunk.');
            }

            $chunks = $chunker->chunkPages($pages);

            if ($chunks === []) {
                throw new \RuntimeException('No chunks were created from the extracted PDF text.');
            }

            DB::transaction(function () use ($document, $chunks, $pages) {
                $document->forceFill([
                    'status' => Document::STATUS_TEXT_EXTRACTED,
                    'total_pages' => count($pages),
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
}
