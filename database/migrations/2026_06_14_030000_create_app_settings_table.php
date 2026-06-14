<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('app_settings')) {
            Schema::create('app_settings', function (Blueprint $table) {
                $table->id();
                $table->string('key')->unique();
                $table->longText('value')->nullable();
                $table->string('type')->default('string');
                $table->string('group')->default('General');
                $table->string('label');
                $table->text('description')->nullable();
                $table->boolean('is_public')->default(false);
                $table->timestamps();
            });

            return;
        }

        Schema::table('app_settings', function (Blueprint $table) {
            if (! Schema::hasColumn('app_settings', 'key')) {
                $table->string('key')->unique();
            }

            if (! Schema::hasColumn('app_settings', 'value')) {
                $table->longText('value')->nullable();
            }

            if (! Schema::hasColumn('app_settings', 'type')) {
                $table->string('type')->default('string');
            }

            if (! Schema::hasColumn('app_settings', 'group')) {
                $table->string('group')->default('General');
            }

            if (! Schema::hasColumn('app_settings', 'label')) {
                $table->string('label')->default('');
            }

            if (! Schema::hasColumn('app_settings', 'description')) {
                $table->text('description')->nullable();
            }

            if (! Schema::hasColumn('app_settings', 'is_public')) {
                $table->boolean('is_public')->default(false);
            }

            if (! Schema::hasColumn('app_settings', 'created_at')) {
                $table->timestamps();
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_settings');
    }
};
