<?php

namespace Tests\Feature;

use App\Jobs\GenerateDocumentEmbeddingsJob;
use App\Jobs\ProcessDocumentJob;
use App\Models\Document;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminDocumentManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_search_and_filter_documents(): void
    {
        $admin = User::factory()->create([
            'is_admin' => true,
        ]);
        $owner = User::factory()->create([
            'name' => 'Document Owner',
            'email' => 'owner@example.com',
        ]);
        $otherOwner = User::factory()->create([
            'email' => 'other@example.com',
        ]);

        $owner->documents()->create([
            'title' => 'Filtered Policy',
            'original_filename' => 'filtered-policy.pdf',
            'file_path' => 'documents/'.$owner->id.'/filtered-policy.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 2048,
            'status' => Document::STATUS_FAILED,
            'total_pages' => 3,
            'total_chunks' => 7,
            'processed_at' => now(),
        ]);
        $otherOwner->documents()->create([
            'title' => 'Other Sheet',
            'original_filename' => 'other-sheet.xlsx',
            'file_path' => 'documents/'.$otherOwner->id.'/other-sheet.xlsx',
            'mime_type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'status' => Document::STATUS_READY,
        ]);

        $this
            ->actingAs($admin)
            ->get(route('admin.documents.index', [
                'search' => 'owner@example.com',
                'status' => Document::STATUS_FAILED,
                'owner' => $owner->id,
                'mime_type' => 'application/pdf',
                'extension' => 'pdf',
            ]))
            ->assertOk()
            ->assertSeeText('Filtered Policy')
            ->assertSeeText('owner@example.com')
            ->assertSee('application/pdf')
            ->assertSeeText('PDF')
            ->assertSeeText('3')
            ->assertSeeText('7')
            ->assertDontSeeText('Other Sheet');
    }

    public function test_admin_can_view_document_detail_without_storage_paths(): void
    {
        $admin = User::factory()->create([
            'is_admin' => true,
        ]);
        $owner = User::factory()->create([
            'email' => 'owner@example.com',
        ]);
        $document = $owner->documents()->create([
            'title' => 'Failed Upload',
            'description' => 'Useful admin-safe description.',
            'original_filename' => 'failed-upload.pdf',
            'file_path' => 'documents/'.$owner->id.'/private-storage-name.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 4096,
            'status' => Document::STATUS_FAILED,
            'total_pages' => 2,
            'total_chunks' => 2,
            'failed_reason' => 'Extractor failed at C:\\private\\private-storage-name.pdf',
            'processed_at' => now(),
        ]);
        $document->chunks()->create([
            'chunk_index' => 1,
            'page_start' => 1,
            'page_end' => 1,
            'token_count' => 50,
            'content' => 'First chunk preview content.',
            'metadata' => ['extraction_method' => 'pdf_text'],
            'embedded_at' => now(),
        ]);
        $document->chunks()->create([
            'chunk_index' => 2,
            'page_start' => 2,
            'page_end' => 2,
            'token_count' => 75,
            'content' => 'Second chunk preview content.',
            'metadata' => ['pages' => [['extraction_method' => 'ocr']]],
        ]);

        $this
            ->actingAs($admin)
            ->get(route('admin.documents.show', $document))
            ->assertOk()
            ->assertSeeText('Failed Upload')
            ->assertSeeText('failed-upload.pdf')
            ->assertSeeText('owner@example.com')
            ->assertSeeText('Failure reason')
            ->assertSeeText('[path hidden]')
            ->assertSeeText('Total Chunks')
            ->assertSeeText('Embedded Chunks')
            ->assertSeeText('Missing Embedding Chunks')
            ->assertSeeText('pdf_text')
            ->assertSeeText('ocr')
            ->assertSeeText('First chunk preview content.')
            ->assertDontSee($document->file_path)
            ->assertDontSee('C:\\private')
            ->assertDontSee('private-storage-name.pdf');
    }

    public function test_admin_document_actions_dispatch_jobs(): void
    {
        Queue::fake();

        $admin = User::factory()->create([
            'is_admin' => true,
        ]);
        $owner = User::factory()->create();
        $document = $owner->documents()->create([
            'title' => 'Failed Upload',
            'original_filename' => 'failed-upload.pdf',
            'file_path' => 'documents/'.$owner->id.'/failed-upload.pdf',
            'status' => Document::STATUS_FAILED,
            'failed_reason' => 'Failed before.',
        ]);
        $document->chunks()->create([
            'chunk_index' => 1,
            'content' => 'Chunk content.',
        ]);

        $this
            ->actingAs($admin)
            ->post(route('admin.documents.retry', $document))
            ->assertRedirect()
            ->assertSessionHas('success', 'Document retry queued.');

        Queue::assertPushed(ProcessDocumentJob::class, fn (ProcessDocumentJob $job) => $job->documentId === $document->id);
        $this->assertSame(Document::STATUS_UPLOADED, $document->fresh()->status);
        $this->assertNull($document->fresh()->failed_reason);

        $this
            ->actingAs($admin)
            ->post(route('admin.documents.regenerate-embeddings', $document))
            ->assertRedirect()
            ->assertSessionHas('success', 'Embedding regeneration queued.');

        Queue::assertPushed(GenerateDocumentEmbeddingsJob::class, fn (GenerateDocumentEmbeddingsJob $job) => $job->documentId === $document->id);

        $this
            ->actingAs($admin)
            ->post(route('admin.documents.reprocess', $document))
            ->assertRedirect()
            ->assertSessionHas('success', 'Full document reprocess queued.');

        Queue::assertPushed(ProcessDocumentJob::class, 2);
    }

    public function test_admin_retry_requires_failed_document_and_regeneration_requires_chunks(): void
    {
        Queue::fake();

        $admin = User::factory()->create([
            'is_admin' => true,
        ]);
        $owner = User::factory()->create();
        $document = $owner->documents()->create([
            'title' => 'Ready Upload',
            'original_filename' => 'ready-upload.pdf',
            'file_path' => 'documents/'.$owner->id.'/ready-upload.pdf',
            'status' => Document::STATUS_READY,
        ]);

        $this
            ->actingAs($admin)
            ->post(route('admin.documents.retry', $document))
            ->assertRedirect()
            ->assertSessionHas('error', 'Only failed documents can be retried.');

        $this
            ->actingAs($admin)
            ->post(route('admin.documents.regenerate-embeddings', $document))
            ->assertRedirect()
            ->assertSessionHas('error', 'This document has no chunks to embed.');

        Queue::assertNothingPushed();
    }

    public function test_admin_can_delete_document_and_stored_file(): void
    {
        Storage::fake('local');

        $admin = User::factory()->create([
            'is_admin' => true,
        ]);
        $owner = User::factory()->create();
        $path = 'documents/'.$owner->id.'/delete-me.pdf';
        Storage::disk('local')->put($path, 'PDF bytes');
        $document = $owner->documents()->create([
            'title' => 'Delete Me',
            'original_filename' => 'delete-me.pdf',
            'file_path' => $path,
            'status' => Document::STATUS_READY,
        ]);

        $this
            ->actingAs($admin)
            ->delete(route('admin.documents.destroy', $document))
            ->assertRedirect(route('admin.documents.index'));

        $this->assertDatabaseMissing('documents', ['id' => $document->id]);
        Storage::disk('local')->assertMissing($path);
    }
}
