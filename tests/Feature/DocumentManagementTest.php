<?php

namespace Tests\Feature;

use App\Jobs\ProcessDocumentJob;
use App\Jobs\GenerateDocumentEmbeddingsJob;
use App\Models\Document;
use App\Models\User;
use App\Services\DocumentChunker;
use App\Services\OcrService;
use App\Services\PdfExtractorService;
use App\Services\TextExtractionDecisionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class DocumentManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_verified_user_can_upload_pdf_document(): void
    {
        Storage::fake('local');
        Queue::fake();

        $user = User::factory()->create();
        $file = UploadedFile::fake()->create('policy.pdf', 128, 'application/pdf');

        $response = $this
            ->actingAs($user)
            ->post(route('documents.store'), [
                'title' => 'Procurement Policy',
                'description' => 'Internal purchasing rules.',
                'document' => $file,
            ]);

        $document = Document::query()->firstOrFail();

        $response->assertRedirect(route('documents.show', $document));
        $this->assertSame($user->id, $document->user_id);
        $this->assertSame('uploaded', $document->status);
        $this->assertNull($document->total_pages);
        $this->assertSame(0, $document->total_chunks);
        Storage::disk('local')->assertExists($document->file_path);
        Queue::assertPushed(ProcessDocumentJob::class, fn (ProcessDocumentJob $job) => $job->documentId === $document->id);
    }

    public function test_process_document_job_extracts_text_and_creates_chunks(): void
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
            'status' => 'uploaded',
        ]);

        $this->app->instance(PdfExtractorService::class, new class extends PdfExtractorService {
            public function extractPages(string $absoluteFilePath): array
            {
                return [
                    ['page' => 1, 'content' => str_repeat('This is page one policy text. ', 80)],
                    ['page' => 2, 'content' => str_repeat('This is page two policy text. ', 80)],
                ];
            }
        });

        (new ProcessDocumentJob($document->id))->handle(
            app(PdfExtractorService::class),
            app(DocumentChunker::class),
            app(TextExtractionDecisionService::class),
            app(OcrService::class)
        );

        $document->refresh();

        $this->assertSame('chunked', $document->status);
        $this->assertGreaterThan(0, $document->total_chunks);
        $this->assertNotNull($document->processed_at);
        $this->assertDatabaseHas('document_chunks', [
            'document_id' => $document->id,
            'chunk_index' => 1,
            'page_start' => 1,
        ]);
        Queue::assertPushed(GenerateDocumentEmbeddingsJob::class, fn (GenerateDocumentEmbeddingsJob $job) => $job->documentId === $document->id);
    }

    public function test_process_document_job_uses_ocr_fallback_for_scanned_pdf(): void
    {
        Storage::fake('local');
        Queue::fake();

        $user = User::factory()->create();
        $path = 'documents/'.$user->id.'/scan.pdf';
        Storage::disk('local')->put($path, 'PDF bytes');

        $document = $user->documents()->create([
            'title' => 'Scanned Policy',
            'original_filename' => 'scan.pdf',
            'file_path' => $path,
            'status' => 'uploaded',
        ]);

        $this->app->instance(PdfExtractorService::class, new class extends PdfExtractorService {
            public function extractPages(string $absoluteFilePath): array
            {
                throw new RuntimeException('PDF text extraction returned empty content.');
            }
        });

        $this->app->instance(OcrService::class, new class extends OcrService {
            public function __construct()
            {
            }

            public function enabled(): bool
            {
                return true;
            }

            public function extractPages(string $absolutePdfPath): array
            {
                return [
                    [
                        'page' => 1,
                        'content' => str_repeat('This scanned policy text came from OCR. ', 80),
                        'metadata' => [
                            'page' => 1,
                            'extraction_method' => 'ocr',
                        ],
                    ],
                ];
            }
        });

        (new ProcessDocumentJob($document->id))->handle(
            app(PdfExtractorService::class),
            app(DocumentChunker::class),
            app(TextExtractionDecisionService::class),
            app(OcrService::class)
        );

        $document->refresh();
        $chunk = $document->chunks()->firstOrFail();

        $this->assertSame('chunked', $document->status);
        $this->assertSame(1, $document->total_pages);
        $this->assertSame('pdf_ocr', $chunk->metadata['source']);
        $this->assertSame(['ocr'], $chunk->metadata['extraction_methods']);
        $this->assertSame('ocr', $chunk->metadata['pages'][0]['extraction_method']);
        Queue::assertPushed(GenerateDocumentEmbeddingsJob::class, fn (GenerateDocumentEmbeddingsJob $job) => $job->documentId === $document->id);
    }

    public function test_documents_index_only_shows_current_users_documents(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $user->documents()->create([
            'title' => 'Visible Document',
            'original_filename' => 'visible.pdf',
            'file_path' => 'documents/'.$user->id.'/visible.pdf',
            'status' => 'uploaded',
        ]);

        $otherUser->documents()->create([
            'title' => 'Hidden Document',
            'original_filename' => 'hidden.pdf',
            'file_path' => 'documents/'.$otherUser->id.'/hidden.pdf',
            'status' => 'uploaded',
        ]);

        $response = $this->actingAs($user)->get(route('documents.index'));

        $response->assertOk();
        $response->assertSee('Visible Document');
        $response->assertDontSee('Hidden Document');
    }

    public function test_documents_index_search_is_case_insensitive(): void
    {
        $user = User::factory()->create();

        $user->documents()->create([
            'title' => 'Mobile Purchase',
            'original_filename' => 'Budget-Phones.pdf',
            'file_path' => 'documents/'.$user->id.'/budget-phones.pdf',
            'status' => 'ready',
        ]);

        $user->documents()->create([
            'title' => 'Laptop Comparison',
            'original_filename' => 'laptops.pdf',
            'file_path' => 'documents/'.$user->id.'/laptops.pdf',
            'status' => 'ready',
        ]);

        $this
            ->actingAs($user)
            ->get(route('documents.index', ['search' => 'mobile']))
            ->assertOk()
            ->assertSee('Mobile Purchase')
            ->assertDontSee('Laptop Comparison');

        $this
            ->actingAs($user)
            ->get(route('documents.index', ['search' => 'budget-phones']))
            ->assertOk()
            ->assertSee('Mobile Purchase')
            ->assertDontSee('Laptop Comparison');
    }

    public function test_documents_index_search_returns_ajax_results(): void
    {
        $user = User::factory()->create();

        $user->documents()->create([
            'title' => 'Mobile Purchase',
            'original_filename' => 'budget-phones.pdf',
            'file_path' => 'documents/'.$user->id.'/budget-phones.pdf',
            'status' => 'ready',
        ]);

        $user->documents()->create([
            'title' => 'Laptop Comparison',
            'original_filename' => 'laptops.pdf',
            'file_path' => 'documents/'.$user->id.'/laptops.pdf',
            'status' => 'ready',
        ]);

        $response = $this
            ->actingAs($user)
            ->getJson(route('documents.index', ['search' => 'mobile']));

        $response
            ->assertOk()
            ->assertJsonStructure(['html']);

        $this->assertStringContainsString('Mobile Purchase', $response->json('html'));
        $this->assertStringNotContainsString('Laptop Comparison', $response->json('html'));
    }

    public function test_user_cannot_view_another_users_document(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $document = $otherUser->documents()->create([
            'title' => 'Private Document',
            'original_filename' => 'private.pdf',
            'file_path' => 'documents/'.$otherUser->id.'/private.pdf',
            'status' => 'uploaded',
        ]);

        $this
            ->actingAs($user)
            ->get(route('documents.show', $document))
            ->assertForbidden();
    }

    public function test_document_routes_use_ulid_instead_of_database_id(): void
    {
        $user = User::factory()->create();

        $document = $user->documents()->create([
            'title' => 'Public Route Key',
            'original_filename' => 'public-route-key.pdf',
            'file_path' => 'documents/'.$user->id.'/public-route-key.pdf',
            'status' => 'uploaded',
        ]);

        $this->assertNotEmpty($document->ulid);
        $this->assertStringEndsWith('/documents/'.$document->ulid, route('documents.show', $document));

        $this
            ->actingAs($user)
            ->get('/documents/'.$document->ulid)
            ->assertOk();

        $this
            ->actingAs($user)
            ->get('/documents/'.$document->id)
            ->assertNotFound();
    }

    public function test_chat_workspace_is_conversation_centric(): void
    {
        $user = User::factory()->create();

        $user->documents()->create([
            'title' => 'Workspace Document',
            'original_filename' => 'workspace-document.pdf',
            'file_path' => 'documents/'.$user->id.'/workspace-document.pdf',
            'status' => 'ready',
            'total_pages' => 8,
        ]);

        $response = $this->actingAs($user)->get(route('chat.index'));

        $response->assertOk();
        $response->assertSee('Conversations');
        $response->assertSee('New conversation');
        $response->assertSee('Workspace Document');
        $response->assertSee('Select all documents');
    }

    public function test_owner_can_delete_document_and_stored_file(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        $path = 'documents/'.$user->id.'/delete-me.pdf';
        Storage::disk('local')->put($path, 'PDF bytes');

        $document = $user->documents()->create([
            'title' => 'Delete Me',
            'original_filename' => 'delete-me.pdf',
            'file_path' => $path,
            'status' => 'uploaded',
        ]);

        $this
            ->actingAs($user)
            ->delete(route('documents.destroy', $document))
            ->assertRedirect(route('documents.index'));

        $this->assertDatabaseMissing('documents', ['id' => $document->id]);
        Storage::disk('local')->assertMissing($path);
    }
}
