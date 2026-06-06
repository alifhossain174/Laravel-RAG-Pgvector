<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('document_chunks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('chunk_index');
            $table->unsignedInteger('page_start')->nullable();
            $table->unsignedInteger('page_end')->nullable();
            $table->longText('content');
            $table->unsignedInteger('token_count')->nullable();
            $table->json('metadata')->nullable();
            $table->string('embedding_provider')->nullable();
            $table->string('embedding_model')->nullable();
            $table->timestamp('embedded_at')->nullable();
            $table->timestamps();

            $table->unique(['document_id', 'chunk_index']);
            $table->index(['document_id', 'page_start']);
        });

        if (DB::getDriverName() === 'pgsql') {
            $dimensions = max(1, (int) env('EMBEDDING_DIMENSIONS', 1536));

            DB::statement("ALTER TABLE document_chunks ADD COLUMN embedding vector({$dimensions})");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('document_chunks');
    }
};
