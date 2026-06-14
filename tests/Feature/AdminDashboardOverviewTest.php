<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Document;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdminDashboardOverviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_dashboard_shows_overview_metrics_without_sensitive_details(): void
    {
        $admin = User::factory()->create([
            'is_admin' => true,
        ]);
        $activeUser = User::factory()->create();
        $suspendedUser = User::factory()->create([
            'is_suspended' => true,
        ]);

        $readyDocument = $activeUser->documents()->create([
            'title' => 'Ready Policy',
            'original_filename' => 'ready-policy.pdf',
            'file_path' => 'documents/'.$activeUser->id.'/private-ready-policy.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 2048,
            'status' => Document::STATUS_READY,
        ]);
        $failedDocument = $suspendedUser->documents()->create([
            'title' => 'Failed Report',
            'original_filename' => 'failed-report.docx',
            'file_path' => 'documents/'.$suspendedUser->id.'/private-failed-report.docx',
            'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'file_size' => 1024,
            'status' => Document::STATUS_FAILED,
            'failed_reason' => 'Secret path C:\\private\\failed-report.docx',
            'processed_at' => now(),
        ]);

        $readyDocument->chunks()->create([
            'chunk_index' => 1,
            'content' => 'Embedded content.',
            'embedded_at' => now(),
        ]);
        $failedDocument->chunks()->create([
            'chunk_index' => 1,
            'content' => 'Missing embedding content.',
        ]);

        $conversation = Conversation::query()->create([
            'user_id' => $activeUser->id,
            'title' => 'Procurement chat',
            'scope' => Conversation::SCOPE_ALL,
        ]);
        Message::query()->create([
            'conversation_id' => $conversation->id,
            'role' => Message::ROLE_USER,
            'content' => 'Question content should not appear on the overview.',
        ]);

        DB::table('jobs')->insert([
            'queue' => 'default',
            'payload' => 'queued-secret-payload',
            'attempts' => 0,
            'reserved_at' => null,
            'available_at' => now()->timestamp,
            'created_at' => now()->timestamp,
        ]);
        DB::table('failed_jobs')->insert([
            'uuid' => '11111111-2222-3333-4444-555555555555',
            'connection' => 'database',
            'queue' => 'default',
            'payload' => 'failed-secret-payload',
            'exception' => 'Exception with api-key-secret',
            'failed_at' => now(),
        ]);

        $this
            ->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSeeText('Total Users')
            ->assertSeeText('3')
            ->assertSeeText('Suspended Users')
            ->assertSeeText('1')
            ->assertSeeText('Total Documents')
            ->assertSeeText('Ready')
            ->assertSeeText('Failed')
            ->assertSeeText('Total Chunks')
            ->assertSeeText('Embedded Chunks')
            ->assertSeeText('Missing Embedding Chunks')
            ->assertSeeText('Total Conversations')
            ->assertSeeText('Total Messages')
            ->assertSeeText('Pending Jobs')
            ->assertSeeText('Failed Jobs')
            ->assertSeeText('3.0 KB')
            ->assertSeeText('PDF')
            ->assertSeeText('DOCX')
            ->assertSeeText('Ready Policy')
            ->assertSeeText('Failed Report')
            ->assertSeeText('Procurement chat')
            ->assertSeeText('11111111-222')
            ->assertDontSee('private-ready-policy.pdf')
            ->assertDontSee('private-failed-report.docx')
            ->assertDontSee('queued-secret-payload')
            ->assertDontSee('failed-secret-payload')
            ->assertDontSee('api-key-secret')
            ->assertDontSee('Question content should not appear');
    }
}
