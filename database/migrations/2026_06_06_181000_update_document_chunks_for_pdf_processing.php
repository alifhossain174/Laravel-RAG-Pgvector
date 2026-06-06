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
        if (Schema::hasColumn('document_chunks', 'page_number') && ! Schema::hasColumn('document_chunks', 'page_start')) {
            if (DB::getDriverName() === 'pgsql') {
                DB::statement('ALTER TABLE document_chunks RENAME COLUMN page_number TO page_start');
            } else {
                Schema::table('document_chunks', function (Blueprint $table) {
                    $table->renameColumn('page_number', 'page_start');
                });
            }
        }

        Schema::table('document_chunks', function (Blueprint $table) {
            if (! Schema::hasColumn('document_chunks', 'page_start')) {
                $table->unsignedInteger('page_start')->nullable()->after('chunk_index');
            }

            if (! Schema::hasColumn('document_chunks', 'page_end')) {
                $table->unsignedInteger('page_end')->nullable()->after('page_start');
            }
        });

        if (DB::getDriverName() === 'pgsql' && ! Schema::hasColumn('document_chunks', 'embedding')) {
            DB::statement('ALTER TABLE document_chunks ADD COLUMN embedding vector(1536)');
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
            if (Schema::hasColumn('document_chunks', 'page_end')) {
                $table->dropColumn('page_end');
            }
        });
    }
};
