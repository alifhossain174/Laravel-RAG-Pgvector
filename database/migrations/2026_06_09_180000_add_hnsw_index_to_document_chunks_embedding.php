<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add an approximate nearest-neighbor index for production RAG retrieval.
     */
    public function up(): void
    {
        if (! $this->canCreateHnswIndex()) {
            return;
        }

        /*
         * RagRetrievalService ranks chunks with:
         *
         *     ORDER BY document_chunks.embedding <=> ?::vector LIMIT ...
         *
         * The <=> operator is pgvector cosine distance, so vector_cosine_ops is
         * the matching HNSW operator class. HNSW is used for low-latency top-k
         * retrieval as the chunk table grows, without requiring IVFFlat training.
         */
        DB::statement(<<<'SQL'
            CREATE INDEX IF NOT EXISTS document_chunks_embedding_hnsw_index
            ON document_chunks
            USING hnsw (embedding vector_cosine_ops)
        SQL);
    }

    /**
     * Drop the ANN index without touching stored embeddings.
     */
    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('DROP INDEX IF EXISTS document_chunks_embedding_hnsw_index');
    }

    private function canCreateHnswIndex(): bool
    {
        if (DB::getDriverName() !== 'pgsql') {
            return false;
        }

        if (! Schema::hasTable('document_chunks') || ! Schema::hasColumn('document_chunks', 'embedding')) {
            return false;
        }

        $result = DB::selectOne(
            "SELECT EXISTS (SELECT 1 FROM pg_am WHERE amname = 'hnsw') AS installed"
        );

        return $this->truthy($result->installed ?? false);
    }

    private function truthy(mixed $value): bool
    {
        return $value === true
            || $value === 1
            || $value === '1'
            || $value === 't'
            || $value === 'true';
    }
};
