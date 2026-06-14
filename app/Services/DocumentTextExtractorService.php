<?php

namespace App\Services;

use App\Models\Document;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class DocumentTextExtractorService
{
    public const PDF_MIME_TYPE = 'application/pdf';

    public const DOCX_MIME_TYPE = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';

    public const XLSX_MIME_TYPE = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';

    public function __construct(
        private readonly PdfExtractorService $pdfExtractor,
        private readonly WordExtractorService $wordExtractor,
        private readonly ExcelExtractorService $excelExtractor,
        private readonly TextExtractionDecisionService $decisionService,
        private readonly OcrService $ocr,
        private readonly UsageTrackingService $usage,
    ) {}

    /**
     * @return array<int, array{page: int|null, content: string, metadata?: array<string, mixed>}>
     */
    public function extract(Document $document, string $absolutePath): array
    {
        if (! is_file($absolutePath)) {
            throw new RuntimeException('Stored document file was not found.');
        }

        $extension = $this->extension($document, $absolutePath);
        $mimeType = strtolower((string) $document->mime_type);

        if ($extension === 'doc') {
            throw new RuntimeException('Legacy .doc files are not supported yet. Please upload PDF, DOCX, XLSX, or CSV.');
        }

        if ($this->isPdf($mimeType, $extension)) {
            return $this->extractPdf($document, $absolutePath);
        }

        if ($this->isDocx($mimeType, $extension)) {
            return $this->wordExtractor->extractPages($absolutePath);
        }

        if ($this->isSpreadsheet($mimeType, $extension)) {
            return $this->excelExtractor->extractPages($absolutePath, $this->spreadsheetExtension($mimeType, $extension));
        }

        throw new RuntimeException('Unsupported document type. Please upload PDF, DOCX, XLSX, or CSV.');
    }

    /**
     * @return array<int, array{page: int, content: string, metadata?: array<string, mixed>}>
     */
    private function extractPdf(Document $document, string $absolutePath): array
    {
        $nativeExtractionException = null;
        $pages = [];

        try {
            $pages = $this->pdfExtractor->extractPages($absolutePath);
        } catch (Throwable $exception) {
            $nativeExtractionException = $exception;

            Log::warning('Native PDF text extraction failed; checking OCR fallback.', [
                'document_id' => $document->id,
                'message' => $exception->getMessage(),
            ]);
        }

        $decision = $this->decisionService->decide($pages, $nativeExtractionException);

        if (! $decision['requires_ocr']) {
            return $pages;
        }

        if (! $this->ocr->enabled()) {
            throw $nativeExtractionException ?: new RuntimeException($decision['message']);
        }

        Log::info('Document requires OCR fallback after native text extraction.', [
            'document_id' => $document->id,
            'reason' => $decision['reason'],
            'character_count' => $decision['character_count'],
            'page_count' => $decision['page_count'],
        ]);

        $this->usage->log([
            'user_id' => $document->user_id,
            'document_id' => $document->id,
            'action_type' => 'ocr_started',
            'metadata' => [
                'reason' => $decision['reason'],
                'native_character_count' => $decision['character_count'],
                'native_page_count' => $decision['page_count'],
            ],
        ]);

        $pages = $this->ocr->extractPages($absolutePath);
        $ocrDecision = $this->decisionService->decide($pages);

        if ($ocrDecision['requires_ocr']) {
            throw new RuntimeException('OCR completed but extracted text is still too short to chunk.');
        }

        $this->usage->log([
            'user_id' => $document->user_id,
            'document_id' => $document->id,
            'action_type' => 'ocr_completed',
            'metadata' => [
                'character_count' => $ocrDecision['character_count'],
                'page_count' => $ocrDecision['page_count'],
            ],
        ]);

        return $pages;
    }

    private function extension(Document $document, string $absolutePath): string
    {
        $name = $document->original_filename ?: basename($absolutePath);

        return strtolower(pathinfo($name, PATHINFO_EXTENSION));
    }

    private function isPdf(string $mimeType, string $extension): bool
    {
        return $extension === 'pdf' || $mimeType === self::PDF_MIME_TYPE;
    }

    private function isDocx(string $mimeType, string $extension): bool
    {
        return $extension === 'docx' || $mimeType === self::DOCX_MIME_TYPE;
    }

    private function isSpreadsheet(string $mimeType, string $extension): bool
    {
        if ($extension === 'xlsx' || $mimeType === self::XLSX_MIME_TYPE) {
            return true;
        }

        return $extension === 'csv';
    }

    private function spreadsheetExtension(string $mimeType, string $extension): string
    {
        if ($extension === 'xlsx' || $extension === 'csv') {
            return $extension;
        }

        return $mimeType === self::XLSX_MIME_TYPE ? 'xlsx' : 'csv';
    }
}
