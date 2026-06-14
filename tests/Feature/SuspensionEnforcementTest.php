<?php

namespace Tests\Feature;

use App\Jobs\GenerateDocumentEmbeddingsJob;
use App\Jobs\ProcessDocumentJob;
use App\Models\Document;
use App\Models\User;
use App\Services\DocumentChunker;
use App\Services\DocumentTextExtractorService;
use App\Services\EmbeddingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class SuspensionEnforcementTest extends TestCase
{
    use RefreshDatabase;

    public function test_suspended_user_is_redirected_to_notice_from_app_routes(): void
    {
        $user = User::factory()->create([
            'is_suspended' => true,
        ]);

        $this
            ->actingAs($user)
            ->get(route('dashboard'))
            ->assertRedirect(route('account.suspended', absolute: false))
            ->assertSessionHas('error');
    }

    public function test_suspended_user_can_view_notice_and_logout(): void
    {
        $user = User::factory()->create([
            'is_suspended' => true,
        ]);

        $this
            ->actingAs($user)
            ->get(route('account.suspended'))
            ->assertOk()
            ->assertSee('Account suspended');

        $this
            ->actingAs($user)
            ->post(route('logout'))
            ->assertRedirect('/');
    }

    public function test_admin_cannot_suspend_self(): void
    {
        $admin = User::factory()->create([
            'is_admin' => true,
        ]);

        $this
            ->actingAs($admin)
            ->patch(route('admin.users.suspension.update', $admin))
            ->assertRedirect()
            ->assertSessionHas('error', 'You cannot suspend your own admin account.');

        $this->assertFalse($admin->fresh()->is_suspended);
    }

    public function test_admin_can_suspend_and_restore_another_user(): void
    {
        $admin = User::factory()->create([
            'is_admin' => true,
        ]);
        $user = User::factory()->create();

        $this
            ->actingAs($admin)
            ->patch(route('admin.users.suspension.update', $user))
            ->assertRedirect()
            ->assertSessionHas('success', 'User account suspended.');

        $this->assertTrue($user->fresh()->is_suspended);

        $this
            ->actingAs($admin)
            ->delete(route('admin.users.suspension.destroy', $user))
            ->assertRedirect()
            ->assertSessionHas('success', 'User account restored.');

        $this->assertFalse($user->fresh()->is_suspended);
    }

    public function test_process_document_job_skips_expensive_work_for_suspended_owner(): void
    {
        $user = User::factory()->create([
            'is_suspended' => true,
        ]);
        $document = $user->documents()->create([
            'title' => 'Suspended Upload',
            'original_filename' => 'suspended.pdf',
            'file_path' => 'documents/'.$user->id.'/suspended.pdf',
            'status' => Document::STATUS_UPLOADED,
        ]);

        $this->app->instance(DocumentTextExtractorService::class, new class extends DocumentTextExtractorService
        {
            public function __construct() {}

            public function extract(Document $document, string $absolutePath): array
            {
                throw new RuntimeException('Extractor should not run for suspended users.');
            }
        });

        (new ProcessDocumentJob($document->id))->handle(
            app(DocumentTextExtractorService::class),
            app(DocumentChunker::class)
        );

        $document->refresh();

        $this->assertSame(Document::STATUS_UPLOADED, $document->status);
        $this->assertSame(0, $document->chunks()->count());
    }

    public function test_embedding_job_skips_expensive_work_for_suspended_owner(): void
    {
        $user = User::factory()->create([
            'is_suspended' => true,
        ]);
        $document = $user->documents()->create([
            'title' => 'Suspended Chunks',
            'original_filename' => 'suspended.pdf',
            'file_path' => 'documents/'.$user->id.'/suspended.pdf',
            'status' => Document::STATUS_CHUNKED,
        ]);
        $document->chunks()->create([
            'chunk_index' => 1,
            'content' => 'This content should not be embedded while the user is suspended.',
        ]);

        $this->app->instance(EmbeddingService::class, new class extends EmbeddingService
        {
            public function __construct() {}

            public function embedText(string $text): array
            {
                throw new RuntimeException('Embedding should not run for suspended users.');
            }
        });

        (new GenerateDocumentEmbeddingsJob($document->id))->handle(app(EmbeddingService::class));

        $this->assertSame(Document::STATUS_CHUNKED, $document->fresh()->status);
        $this->assertNull($document->chunks()->firstOrFail()->embedded_at);
    }
}
