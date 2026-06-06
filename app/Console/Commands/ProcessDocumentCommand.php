<?php

namespace App\Console\Commands;

use App\Jobs\ProcessDocumentJob;
use App\Models\Document;
use Illuminate\Console\Command;

class ProcessDocumentCommand extends Command
{
    protected $signature = 'documents:process {document}';

    protected $description = 'Process a PDF document by extracting text and creating chunks.';

    public function handle(): int
    {
        $document = Document::query()->where('ulid', $this->argument('document'))->first();

        if (! $document) {
            $this->error('Document not found.');

            return self::FAILURE;
        }

        $this->info("Processing document {$document->ulid}: {$document->displayTitle()}");

        ProcessDocumentJob::dispatchSync($document->id);

        $document->refresh();

        if ($document->status === Document::STATUS_FAILED) {
            $this->error('Processing failed: '.($document->failed_reason ?: 'Unknown failure.'));

            return self::FAILURE;
        }

        $this->info("Processing complete. Status: {$document->statusLabel()}, chunks: {$document->total_chunks}");

        return self::SUCCESS;
    }
}
