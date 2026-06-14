<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('user_limits')) {
            return;
        }

        Schema::create('user_limits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedInteger('daily_chat_limit')->nullable();
            $table->unsignedInteger('daily_embedding_limit')->nullable();
            $table->unsignedInteger('monthly_upload_limit')->nullable();
            $table->unsignedInteger('max_documents')->nullable();
            $table->unsignedInteger('max_storage_mb')->nullable();
            $table->unsignedInteger('max_file_size_mb')->nullable();
            $table->boolean('is_unlimited')->default(false);
            $table->json('allowed_mime_types')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_limits');
    }
};
