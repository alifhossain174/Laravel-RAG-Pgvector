<?php

namespace App\Console\Commands;

use App\Jobs\GenerateDocumentEmbeddingsJob;
use App\Models\Document;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class EmbedDocumentCommand extends Command
{
    protected $signature = 'documents:embed {document} {--force : Clear existing embeddings before dispatching the job}';

    protected $description = 'Generate embeddings for a processed document.';

    public function handle(): int
    {
        $document = Document::query()->where('ulid', $this->argument('document'))->first();

        if (! $document) {
            $this->error('Document not found.');

            return self::FAILURE;
        }

        if ($document->chunks()->count() === 0) {
            $this->error('Document has no chunks. Process the PDF before embedding.');

            return self::FAILURE;
        }

        if ($this->option('force')) {
            DB::statement(
                'UPDATE document_chunks SET embedding = NULL, embedded_at = NULL, embedding_provider = NULL, embedding_model = NULL, updated_at = ? WHERE document_id = ?',
                [now(), $document->id]
            );

            $this->info('Existing embeddings cleared.');
        }

        $pending = $document->chunks()->whereNull('embedding')->count();

        if ($pending === 0) {
            $this->info('No missing embeddings. Use --force to regenerate existing embeddings.');

            return self::SUCCESS;
        }

        GenerateDocumentEmbeddingsJob::dispatch($document->id);

        $this->info("Embedding job dispatched for document {$document->ulid}. Pending chunks: {$pending}");

        return self::SUCCESS;
    }
}
