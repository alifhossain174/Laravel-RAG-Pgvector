<?php

namespace App\Console\Commands;

use App\Jobs\ProcessDocumentJob;
use App\Models\Document;
use Illuminate\Console\Command;

class RechunkAllDocumentsCommand extends Command
{
    protected $signature = 'documents:rechunk-all';

    protected $description = 'Re-extract and rebuild chunks with page ranges for all documents without regenerating embeddings.';

    public function handle(): int
    {
        $count = 0;

        Document::query()
            ->orderBy('id')
            ->each(function (Document $document) use (&$count) {
                $this->line("Rechunking document {$document->ulid}: {$document->displayTitle()}");

                ProcessDocumentJob::dispatchSync($document->id, false);
                $count++;
            });

        $this->info("Rechunked {$count} document(s).");
        $this->warn('Embeddings were not regenerated. Run documents:embed {document_ulid} --force if needed.');

        return self::SUCCESS;
    }
}
