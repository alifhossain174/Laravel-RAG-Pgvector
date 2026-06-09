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

    public function __construct(
        private readonly PdfExtractorService $pdfExtractor,
        private readonly WordExtractorService $wordExtractor,
        private readonly TextExtractionDecisionService $decisionService,
        private readonly OcrService $ocr,
    ) {
    }

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
            throw new RuntimeException('Legacy .doc files are not supported yet. Please upload PDF or DOCX.');
        }

        if ($this->isPdf($mimeType, $extension)) {
            return $this->extractPdf($document, $absolutePath);
        }

        if ($this->isDocx($mimeType, $extension)) {
            return $this->wordExtractor->extractPages($absolutePath);
        }

        throw new RuntimeException('Unsupported document type. Please upload PDF or DOCX.');
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

        $pages = $this->ocr->extractPages($absolutePath);
        $ocrDecision = $this->decisionService->decide($pages);

        if ($ocrDecision['requires_ocr']) {
            throw new RuntimeException('OCR completed but extracted text is still too short to chunk.');
        }

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
}
