<?php

namespace Tests\Feature;

use App\Jobs\GenerateDocumentEmbeddingsJob;
use App\Models\AiUsageLog;
use App\Models\Document;
use App\Models\User;
use App\Services\EmbeddingService;
use App\Services\LimitService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class UsageLimitTest extends TestCase
{
    use RefreshDatabase;

    public function test_limit_service_uses_safe_defaults_without_user_limit_row(): void
    {
        $user = User::factory()->create();

        $result = app(LimitService::class)->canSendChatMessage($user);

        $this->assertTrue($result['allowed']);
    }

    public function test_upload_is_rejected_when_monthly_upload_limit_is_exceeded(): void
    {
        Storage::fake('local');
        Queue::fake();

        $user = User::factory()->create();
        $user->limit()->create([
            'monthly_upload_limit' => 1,
        ]);
        $user->documents()->create([
            'title' => 'Existing',
            'original_filename' => 'existing.pdf',
            'file_path' => 'documents/'.$user->id.'/existing.pdf',
            'status' => Document::STATUS_READY,
        ]);

        $this
            ->actingAs($user)
            ->post(route('documents.store'), [
                'title' => 'Blocked',
                'document' => UploadedFile::fake()->create('blocked.pdf', 128, 'application/pdf'),
            ])
            ->assertSessionHasErrors(['document' => 'Monthly upload limit reached. Your account can upload 1 documents per month.']);

        $this->assertDatabaseMissing('documents', ['title' => 'Blocked']);
        Queue::assertNothingPushed();
    }

    public function test_chat_is_rejected_when_daily_chat_limit_is_exceeded(): void
    {
        $user = User::factory()->create();
        $user->limit()->create([
            'daily_chat_limit' => 1,
        ]);
        AiUsageLog::query()->create([
            'user_id' => $user->id,
            'action_type' => 'chat_request',
        ]);
        $conversation = $user->conversations()->create([
            'title' => 'Limit chat',
            'scope' => 'all',
        ]);

        $this
            ->actingAs($user)
            ->post(route('chat.messages.store', $conversation), [
                'content' => 'Can I ask another question?',
            ])
            ->assertSessionHasErrors(['content' => 'Daily chat limit reached. Your account can send 1 chat messages per day.']);

        $this->assertSame(0, $conversation->messages()->count());
    }

    public function test_embedding_job_skips_expensive_generation_when_daily_embedding_limit_is_exceeded(): void
    {
        $user = User::factory()->create();
        $user->limit()->create([
            'daily_embedding_limit' => 1,
        ]);
        AiUsageLog::query()->create([
            'user_id' => $user->id,
            'action_type' => 'embedding_generated',
            'embedding_count' => 1,
        ]);
        $document = $user->documents()->create([
            'title' => 'Embedding Limit',
            'original_filename' => 'embedding-limit.pdf',
            'file_path' => 'documents/'.$user->id.'/embedding-limit.pdf',
            'status' => Document::STATUS_CHUNKED,
        ]);
        $document->chunks()->create([
            'chunk_index' => 1,
            'content' => 'First chunk.',
        ]);
        $document->chunks()->create([
            'chunk_index' => 2,
            'content' => 'Second chunk.',
        ]);

        $this->app->instance(EmbeddingService::class, new class extends EmbeddingService
        {
            public function __construct() {}

            public function embedText(string $text): array
            {
                throw new RuntimeException('Embedding should not run when product quota is exceeded.');
            }

            public function provider(): string
            {
                return 'gemini';
            }

            public function model(): string
            {
                return 'gemini-embedding-2';
            }
        });

        (new GenerateDocumentEmbeddingsJob($document->id))->handle(app(EmbeddingService::class));

        $document->refresh();

        $this->assertSame(Document::STATUS_FAILED, $document->status);
        $this->assertSame('Daily embedding limit reached. Your account can generate 1 embeddings per day.', $document->failed_reason);
        $this->assertDatabaseHas('ai_usage_logs', [
            'document_id' => $document->id,
            'action_type' => 'embedding_failed',
            'status' => 'failed',
        ]);
    }

    public function test_admin_can_update_user_limits(): void
    {
        $admin = User::factory()->create([
            'is_admin' => true,
        ]);
        $user = User::factory()->create();

        $this
            ->actingAs($admin)
            ->patch(route('admin.users.limits.update', $user), [
                'daily_chat_limit' => 5,
                'daily_embedding_limit' => 10,
                'monthly_upload_limit' => 2,
                'max_documents' => 3,
                'max_storage_mb' => 50,
                'max_file_size_mb' => 4,
                'allowed_mime_types' => "application/pdf\ntext/csv",
                'notes' => 'Trial account',
                'is_unlimited' => '0',
            ])
            ->assertRedirect()
            ->assertSessionHas('success', 'User limits updated.');

        $limit = $user->limit()->firstOrFail();

        $this->assertSame(5, $limit->daily_chat_limit);
        $this->assertSame(['application/pdf', 'text/csv'], $limit->allowed_mime_types);
        $this->assertSame('Trial account', $limit->notes);
        $this->assertFalse($limit->is_unlimited);
    }
}
