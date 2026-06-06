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
        Schema::table('document_chunks', function (Blueprint $table) {
            if (! Schema::hasColumn('document_chunks', 'embedding_provider')) {
                $table->string('embedding_provider')->nullable()->after('metadata');
            }

            if (! Schema::hasColumn('document_chunks', 'embedding_model')) {
                $table->string('embedding_model')->nullable()->after('embedding_provider');
            }
        });

        if (DB::getDriverName() === 'pgsql' && ! Schema::hasColumn('document_chunks', 'embedding')) {
            $dimensions = max(1, (int) env('EMBEDDING_DIMENSIONS', 1536));

            DB::statement("ALTER TABLE document_chunks ADD COLUMN embedding vector({$dimensions})");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql' && Schema::hasColumn('document_chunks', 'embedding')) {
            DB::statement('ALTER TABLE document_chunks DROP COLUMN embedding');
        }

        Schema::table('document_chunks', function (Blueprint $table) {
            if (Schema::hasColumn('document_chunks', 'embedding_model')) {
                $table->dropColumn('embedding_model');
            }

            if (Schema::hasColumn('document_chunks', 'embedding_provider')) {
                $table->dropColumn('embedding_provider');
            }
        });
    }
};
