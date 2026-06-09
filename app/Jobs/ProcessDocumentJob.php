<?php

namespace App\Jobs;

use App\Models\Document;
use App\Services\DocumentChunker;
use App\Services\OcrService;
use App\Services\PdfExtractorService;
use App\Services\TextExtractionDecisionService;
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

    public function handle(
        PdfExtractorService $extractor,
        DocumentChunker $chunker,
        TextExtractionDecisionService $decisionService,
        OcrService $ocr
    ): void
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

            $nativeExtractionException = null;
            $pages = [];

            try {
                $pages = $extractor->extractPages($absolutePath);
            } catch (Throwable $exception) {
                $nativeExtractionException = $exception;

                Log::warning('Native PDF text extraction failed; checking OCR fallback.', [
                    'document_id' => $document->id,
                    'message' => $exception->getMessage(),
                ]);
            }

            $decision = $decisionService->decide($pages, $nativeExtractionException);

            if ($decision['requires_ocr']) {
                if (! $ocr->enabled()) {
                    throw $nativeExtractionException ?: new \RuntimeException($decision['message']);
                }

                Log::info('Document requires OCR fallback after native text extraction.', [
                    'document_id' => $document->id,
                    'reason' => $decision['reason'],
                    'character_count' => $decision['character_count'],
                    'page_count' => $decision['page_count'],
                ]);

                $pages = $ocr->extractPages($absolutePath);
                $ocrDecision = $decisionService->decide($pages);

                if ($ocrDecision['requires_ocr']) {
                    throw new \RuntimeException('OCR completed but extracted text is still too short to chunk.');
                }
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
