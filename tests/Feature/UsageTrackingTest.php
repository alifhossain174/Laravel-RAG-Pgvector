<?php

namespace Tests\Feature;

use App\Jobs\ProcessDocumentJob;
use App\Models\AiUsageLog;
use App\Models\Document;
use App\Models\User;
use App\Services\DocumentChunker;
use App\Services\DocumentTextExtractorService;
use App\Services\UsageTrackingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class UsageTrackingTest extends TestCase
{
    use RefreshDatabase;

    public function test_usage_tracking_service_sanitizes_sensitive_values(): void
    {
        $user = User::factory()->create();

        app(UsageTrackingService::class)->log([
            'user_id' => $user->id,
            'action_type' => 'chat_failed',
            'status' => 'failed',
            'error_message' => 'Failed with api_key=secret-value at C:\\private\\doc.pdf',
            'metadata' => [
                'token' => 'secret-token',
                'file_path' => '/var/www/storage/private.pdf',
                'nested' => [
                    'safe' => 'visible',
                    'absolute_path' => 'E:\\secret\\file.pdf',
                ],
            ],
        ]);

        $log = AiUsageLog::query()->firstOrFail();

        $this->assertSame('Failed with api_key=[hidden] at [path hidden]', $log->error_message);
        $this->assertSame('[hidden]', $log->metadata['token']);
        $this->assertSame('[hidden]', $log->metadata['file_path']);
        $this->assertSame('visible', $log->metadata['nested']['safe']);
        $this->assertSame('[hidden]', $log->metadata['nested']['absolute_path']);
    }

    public function test_document_upload_creates_usage_log(): void
    {
        Storage::fake('local');
        Queue::fake();

        $user = User::factory()->create();
        $file = UploadedFile::fake()->create('policy.pdf', 128, 'application/pdf');

        $this
            ->actingAs($user)
            ->post(route('documents.store'), [
                'title' => 'Policy',
                'document' => $file,
            ])
            ->assertRedirect();

        $document = Document::query()->firstOrFail();

        $this->assertDatabaseHas('ai_usage_logs', [
            'user_id' => $user->id,
            'document_id' => $document->id,
            'action_type' => 'document_uploaded',
            'status' => AiUsageLog::STATUS_SUCCESS,
        ]);
    }

    public function test_process_document_job_logs_processing_text_and_chunks(): void
    {
        Storage::fake('local');
        Queue::fake();

        $user = User::factory()->create();
        $path = 'documents/'.$user->id.'/policy.pdf';
        Storage::disk('local')->put($path, 'PDF bytes');

        $document = $user->documents()->create([
            'title' => 'Policy',
            'original_filename' => 'policy.pdf',
            'file_path' => $path,
            'status' => Document::STATUS_UPLOADED,
        ]);

        $this->app->instance(DocumentTextExtractorService::class, new class extends DocumentTextExtractorService
        {
            public function __construct() {}

            public function extract(Document $document, string $absolutePath): array
            {
                return [
                    ['page' => 1, 'content' => str_repeat('Readable policy text. ', 100), 'metadata' => ['extraction_method' => 'pdf_text']],
                ];
            }
        });

        (new ProcessDocumentJob($document->id))->handle(
            app(DocumentTextExtractorService::class),
            app(DocumentChunker::class)
        );

        $this->assertDatabaseHas('ai_usage_logs', [
            'document_id' => $document->id,
            'action_type' => 'document_processing_started',
        ]);
        $this->assertDatabaseHas('ai_usage_logs', [
            'document_id' => $document->id,
            'action_type' => 'text_extracted',
        ]);
        $this->assertDatabaseHas('ai_usage_logs', [
            'document_id' => $document->id,
            'action_type' => 'chunks_created',
        ]);
    }

    public function test_embedding_failure_is_logged(): void
    {
        $user = User::factory()->create();
        $document = $user->documents()->create([
            'title' => 'Chunks',
            'original_filename' => 'chunks.pdf',
            'file_path' => 'documents/'.$user->id.'/chunks.pdf',
            'status' => Document::STATUS_CHUNKED,
        ]);

        app(UsageTrackingService::class)->log([
            'user_id' => $user->id,
            'document_id' => $document->id,
            'action_type' => 'embedding_failed',
            'provider' => 'gemini',
            'model' => 'gemini-embedding-2',
            'status' => 'failed',
            'error_message' => 'Embedding failed with token=private',
        ]);

        $this->assertDatabaseHas('ai_usage_logs', [
            'document_id' => $document->id,
            'action_type' => 'embedding_failed',
            'provider' => 'gemini',
            'status' => 'failed',
        ]);
        $this->assertSame('Embedding failed with token=[hidden]', AiUsageLog::query()->latest()->firstOrFail()->error_message);
    }
}
