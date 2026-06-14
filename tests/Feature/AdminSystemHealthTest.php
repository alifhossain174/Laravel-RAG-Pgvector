<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdminSystemHealthTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_system_health_without_sensitive_details(): void
    {
        config([
            'services.gemini.api_key' => 'super-secret-gemini-key',
            'services.pdftotext.binary' => 'C:\\secret\\pdftotext.exe',
            'services.ocr.tesseract_binary' => 'C:\\secret\\tesseract.exe',
            'services.ocr.pdftoppm_binary' => 'C:\\secret\\pdftoppm.exe',
        ]);

        $admin = User::factory()->create([
            'is_admin' => true,
        ]);
        $user = User::factory()->create([
            'email' => 'reader@example.com',
        ]);

        $user->documents()->create([
            'title' => 'Ready Handbook',
            'original_filename' => 'ready-handbook.pdf',
            'file_path' => 'C:\\private\\ready-handbook.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 4096,
            'status' => Document::STATUS_READY,
            'processed_at' => now(),
        ]);
        $user->documents()->create([
            'title' => 'Failed Scanned Report',
            'original_filename' => 'failed-scanned-report.pdf',
            'file_path' => '/var/private/failed-scanned-report.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 2048,
            'status' => Document::STATUS_FAILED,
            'failed_reason' => 'OCR failed at /var/private/failed-scanned-report.pdf with token=secret',
        ]);

        DB::table('failed_jobs')->insert([
            'uuid' => '11111111-2222-3333-4444-555555555555',
            'connection' => 'database',
            'queue' => 'default',
            'payload' => 'payload-with-secret-token',
            'exception' => 'RuntimeException with api_key=hidden-value at C:\\private\\job.php',
            'failed_at' => now(),
        ]);

        $this
            ->actingAs($admin)
            ->get(route('admin.system-health.index'))
            ->assertOk()
            ->assertSeeText('System Health')
            ->assertSeeText('Database and Vector Search')
            ->assertSeeText('Queues')
            ->assertSeeText('AI and RAG Configuration')
            ->assertSeeText('PDF Tools')
            ->assertSeeText('OCR')
            ->assertSeeText('Latest ready document')
            ->assertSeeText('Latest failed document')
            ->assertSeeText('Ready Handbook')
            ->assertSeeText('Failed Scanned Report')
            ->assertSeeText('reader@example.com')
            ->assertSeeText('Configured.')
            ->assertSeeText('PDFTOTEXT_PATH configured.')
            ->assertSeeText('TESSERACT_PATH configured.')
            ->assertSeeText('PDFTOPPM_PATH configured.')
            ->assertDontSee('super-secret-gemini-key')
            ->assertDontSee('C:\\secret')
            ->assertDontSee('C:\\private\\ready-handbook.pdf')
            ->assertDontSee('/var/private/failed-scanned-report.pdf')
            ->assertDontSee('payload-with-secret-token')
            ->assertDontSee('hidden-value')
            ->assertDontSee('job.php')
            ->assertDontSee('token=secret');
    }

    public function test_non_admin_cannot_view_system_health(): void
    {
        $user = User::factory()->create();

        $this
            ->actingAs($user)
            ->get(route('admin.system-health.index'))
            ->assertForbidden();
    }
}
