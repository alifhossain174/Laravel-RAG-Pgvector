<?php

namespace App\Console\Commands;

use App\Jobs\ProcessDocumentJob;
use App\Models\Document;
use Illuminate\Console\Command;

class RechunkDocumentCommand extends Command
{
    protected $signature = 'documents:rechunk {document}';

    protected $description = 'Re-extract and rebuild chunks with page ranges without regenerating embeddings.';

    public function handle(): int
    {
        $document = Document::query()->where('ulid', $this->argument('document'))->first();

        if (! $document) {
            $this->error('Document not found.');

            return self::FAILURE;
        }

        $this->info("Rechunking document {$document->ulid}: {$document->displayTitle()}");

        ProcessDocumentJob::dispatchSync($document->id, false);

        $document->refresh();

        if ($document->status === Document::STATUS_FAILED) {
            $this->error('Rechunk failed: '.($document->failed_reason ?: 'Unknown failure.'));

            return self::FAILURE;
        }

        $this->info("Rechunk complete. Pages: ".($document->total_pages ?? '-').", chunks: {$document->total_chunks}");
        $this->warn('Embeddings were not regenerated. Run documents:embed '.$document->ulid.' --force if needed.');

        return self::SUCCESS;
    }
}
