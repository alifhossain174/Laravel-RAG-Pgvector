<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasColumn('documents', 'ulid')) {
            return;
        }

        Schema::table('documents', function (Blueprint $table) {
            $table->ulid('ulid')->nullable()->after('id');
        });

        DB::table('documents')
            ->whereNull('ulid')
            ->orderBy('id')
            ->chunkById(100, function ($documents): void {
                foreach ($documents as $document) {
                    DB::table('documents')
                        ->where('id', $document->id)
                        ->update(['ulid' => (string) Str::ulid()]);
                }
            });

        Schema::table('documents', function (Blueprint $table) {
            $table->unique('ulid');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE documents ALTER COLUMN ulid SET NOT NULL');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasColumn('documents', 'ulid')) {
            return;
        }

        Schema::table('documents', function (Blueprint $table) {
            $table->dropUnique(['ulid']);
            $table->dropColumn('ulid');
        });
    }
};
