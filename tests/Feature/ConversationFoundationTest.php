<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\User;
use App\Services\LlmService;
use App\Services\RagRetrievalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class ConversationFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_selected_document_conversation(): void
    {
        $user = User::factory()->create();

        $document = $user->documents()->create([
            'title' => 'Policy',
            'original_filename' => 'policy.pdf',
            'file_path' => 'documents/'.$user->id.'/policy.pdf',
            'status' => 'ready',
        ]);

        $response = $this->actingAs($user)->post(route('chat.store'), [
            'title' => 'Policy questions',
            'scope' => Conversation::SCOPE_SELECTED,
            'document_ids' => [$document->id],
        ]);

        $conversation = Conversation::query()->firstOrFail();

        $response->assertRedirect(route('chat.show', $conversation));
        $this->assertSame($user->id, $conversation->user_id);
        $this->assertSame(Conversation::SCOPE_SELECTED, $conversation->scope);
        $this->assertDatabaseHas('conversation_documents', [
            'conversation_id' => $conversation->id,
            'document_id' => $document->id,
        ]);
    }

    public function test_user_can_create_all_documents_conversation_without_pivot_rows(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('chat.store'), [
            'title' => 'All document questions',
            'scope' => Conversation::SCOPE_ALL,
        ]);

        $conversation = Conversation::query()->firstOrFail();

        $response->assertRedirect(route('chat.show', $conversation));
        $this->assertSame(Conversation::SCOPE_ALL, $conversation->scope);
        $this->assertDatabaseCount('conversation_documents', 0);
    }

    public function test_conversation_routes_use_ulid_and_reject_database_id(): void
    {
        $user = User::factory()->create();

        $conversation = $user->conversations()->create([
            'title' => 'Route key check',
            'scope' => Conversation::SCOPE_ALL,
        ]);

        $this->assertNotEmpty($conversation->ulid);
        $this->assertStringEndsWith('/chat/'.$conversation->ulid, route('chat.show', $conversation));

        $this
            ->actingAs($user)
            ->get('/chat/'.$conversation->ulid)
            ->assertOk();

        $this
            ->actingAs($user)
            ->get('/chat/'.$conversation->id)
            ->assertNotFound();
    }

    public function test_conversation_search_is_case_insensitive(): void
    {
        $user = User::factory()->create();

        $user->conversations()->create([
            'title' => 'Mobile Purchase',
            'scope' => Conversation::SCOPE_ALL,
        ]);

        $user->conversations()->create([
            'title' => 'Laptop Comparison',
            'scope' => Conversation::SCOPE_ALL,
        ]);

        $this
            ->actingAs($user)
            ->get(route('chat.index', ['search' => 'mobile']))
            ->assertOk()
            ->assertSee('Mobile Purchase')
            ->assertDontSee('Laptop Comparison');
    }

    public function test_conversation_search_returns_ajax_results(): void
    {
        $user = User::factory()->create();

        $user->conversations()->create([
            'title' => 'Mobile Purchase',
            'scope' => Conversation::SCOPE_ALL,
        ]);

        $user->conversations()->create([
            'title' => 'Laptop Comparison',
            'scope' => Conversation::SCOPE_ALL,
        ]);

        $response = $this
            ->actingAs($user)
            ->getJson(route('chat.index', ['search' => 'mobile']));

        $response
            ->assertOk()
            ->assertJsonStructure(['html']);

        $this->assertStringContainsString('Mobile Purchase', $response->json('html'));
        $this->assertStringNotContainsString('Laptop Comparison', $response->json('html'));
    }

    public function test_user_cannot_access_another_users_conversation(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $conversation = $otherUser->conversations()->create([
            'title' => 'Private conversation',
            'scope' => Conversation::SCOPE_ALL,
        ]);

        $this
            ->actingAs($user)
            ->get(route('chat.show', $conversation))
            ->assertForbidden();

        $this
            ->actingAs($user)
            ->delete(route('chat.destroy', $conversation))
            ->assertForbidden();
    }

    public function test_selected_documents_must_be_ready_and_owned_by_user(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $processingDocument = $user->documents()->create([
            'title' => 'Processing',
            'original_filename' => 'processing.pdf',
            'file_path' => 'documents/'.$user->id.'/processing.pdf',
            'status' => 'processing',
        ]);

        $otherDocument = $otherUser->documents()->create([
            'title' => 'Other',
            'original_filename' => 'other.pdf',
            'file_path' => 'documents/'.$otherUser->id.'/other.pdf',
            'status' => 'ready',
        ]);

        $this
            ->actingAs($user)
            ->post(route('chat.store'), [
                'title' => 'Invalid scope',
                'scope' => Conversation::SCOPE_SELECTED,
                'document_ids' => [$processingDocument->id, $otherDocument->id],
            ])
            ->assertSessionHasErrors('document_ids.0');

        $this->assertDatabaseCount('conversations', 0);
    }

    public function test_posting_message_saves_user_message_and_rag_answer_with_sources(): void
    {
        $user = User::factory()->create();

        $conversation = $user->conversations()->create([
            'title' => 'Message test',
            'scope' => Conversation::SCOPE_ALL,
        ]);

        $chunks = [
            [
                'chunk_id' => 12,
                'document_id' => 3,
                'document_title' => 'SEO Report',
                'page_start' => 2,
                'page_end' => 3,
                'content' => 'The title tag should be shortened and made more descriptive.',
                'score' => 0.91,
            ],
        ];

        $this->mock(RagRetrievalService::class, function ($mock) use ($chunks) {
            $mock->shouldReceive('retrieve')
                ->once()
                ->with(Mockery::type(Conversation::class), 'What are the deadlines?')
                ->andReturn($chunks);
        });

        $this->mock(LlmService::class, function ($mock) {
            $mock->shouldReceive('answerWithContext')
                ->once()
                ->andReturn([
                    'answer' => 'The title tag should be shortened [SEO Report, pages 2-3].',
                    'provider' => 'gemini',
                    'model' => 'gemini-2.5-flash',
                ]);
        });

        $this
            ->actingAs($user)
            ->post(route('chat.messages.store', $conversation), [
                'content' => 'What are the deadlines?',
            ])
            ->assertRedirect(route('chat.show', $conversation));

        $this->assertDatabaseHas('messages', [
            'conversation_id' => $conversation->id,
            'role' => 'user',
            'content' => 'What are the deadlines?',
        ]);

        $this->assertDatabaseHas('messages', [
            'conversation_id' => $conversation->id,
            'role' => 'assistant',
            'content' => 'The title tag should be shortened [SEO Report, pages 2-3].',
        ]);

        $assistantMessage = $conversation->messages()
            ->where('role', 'assistant')
            ->firstOrFail();

        $this->assertSame('gemini', $assistantMessage->metadata['provider']);
        $this->assertSame('gemini-2.5-flash', $assistantMessage->metadata['model']);
        $this->assertSame(12, $assistantMessage->metadata['sources'][0]['chunk_id']);
        $this->assertSame('SEO Report', $assistantMessage->metadata['sources'][0]['document_title']);
        $this->assertSame('The title tag should be shortened and made more descriptive.', $assistantMessage->metadata['sources'][0]['preview']);
    }

    public function test_posting_message_saves_no_retrieval_answer_when_no_chunks_found(): void
    {
        $user = User::factory()->create();

        $conversation = $user->conversations()->create([
            'title' => 'No chunks test',
            'scope' => Conversation::SCOPE_ALL,
        ]);

        $this->mock(RagRetrievalService::class, function ($mock) {
            $mock->shouldReceive('retrieve')
                ->once()
                ->andReturn([]);
        });

        $this->mock(LlmService::class, function ($mock) {
            $mock->shouldNotReceive('answerWithContext');
        });

        $this
            ->actingAs($user)
            ->post(route('chat.messages.store', $conversation), [
                'content' => 'What are the deadlines?',
            ])
            ->assertRedirect(route('chat.show', $conversation));

        $assistantMessage = $conversation->messages()
            ->where('role', 'assistant')
            ->firstOrFail();

        $this->assertSame('I could not find relevant information in the selected documents.', $assistantMessage->content);
        $this->assertSame([], $assistantMessage->metadata['sources']);
    }

    public function test_posting_message_saves_safe_error_answer_when_llm_fails(): void
    {
        $user = User::factory()->create();

        $conversation = $user->conversations()->create([
            'title' => 'Error test',
            'scope' => Conversation::SCOPE_ALL,
        ]);

        $this->mock(RagRetrievalService::class, function ($mock) {
            $mock->shouldReceive('retrieve')
                ->once()
                ->andReturn([
                    [
                        'chunk_id' => 5,
                        'document_id' => 7,
                        'document_title' => 'Policy',
                        'page_start' => 1,
                        'page_end' => 1,
                        'content' => 'Policy content.',
                        'score' => 0.8,
                    ],
                ]);
        });

        $this->mock(LlmService::class, function ($mock) {
            $mock->shouldReceive('answerWithContext')
                ->once()
                ->andThrow(new RuntimeException('Provider secret failure details.'));
        });

        $this
            ->actingAs($user)
            ->post(route('chat.messages.store', $conversation), [
                'content' => 'What are the deadlines?',
            ])
            ->assertRedirect(route('chat.show', $conversation));

        $assistantMessage = $conversation->messages()
            ->where('role', 'assistant')
            ->firstOrFail();

        $this->assertSame('Sorry, I could not generate an answer right now. Please try again.', $assistantMessage->content);
        $this->assertTrue($assistantMessage->metadata['error']);
        $this->assertSame([], $assistantMessage->metadata['sources']);
    }

    public function test_first_question_updates_generic_conversation_title(): void
    {
        $user = User::factory()->create();

        $conversation = $user->conversations()->create([
            'title' => 'New Conversation',
            'scope' => Conversation::SCOPE_ALL,
        ]);

        $this->mock(RagRetrievalService::class, function ($mock) {
            $mock->shouldReceive('retrieve')
                ->once()
                ->andReturn([]);
        });

        $this->mock(LlmService::class, function ($mock) {
            $mock->shouldNotReceive('answerWithContext');
        });

        $this
            ->actingAs($user)
            ->post(route('chat.messages.store', $conversation), [
                'content' => 'What actions are required before renewal?',
            ])
            ->assertRedirect(route('chat.show', $conversation));

        $conversation->refresh();

        $this->assertSame('What actions are required before renewal?', $conversation->title);
    }

    public function test_message_content_has_four_thousand_character_limit(): void
    {
        $user = User::factory()->create();

        $conversation = $user->conversations()->create([
            'title' => 'Limit test',
            'scope' => Conversation::SCOPE_ALL,
        ]);

        $this
            ->actingAs($user)
            ->post(route('chat.messages.store', $conversation), [
                'content' => str_repeat('a', 4001),
            ])
            ->assertSessionHasErrors('content');
    }

    public function test_chat_show_renders_assistant_markdown_as_structured_html(): void
    {
        $user = User::factory()->create();

        $conversation = $user->conversations()->create([
            'title' => 'Markdown rendering',
            'scope' => Conversation::SCOPE_ALL,
        ]);

        $conversation->messages()->create([
            'role' => 'assistant',
            'content' => implode("\n", [
                '**Summary**',
                '',
                '| Field | Value |',
                '| --- | --- |',
                '| Grade | C [SEO Report, page 1] |',
            ]),
        ]);

        $this
            ->actingAs($user)
            ->get(route('chat.show', $conversation))
            ->assertOk()
            ->assertSee('<strong>Summary</strong>', false)
            ->assertSee('<table>', false)
            ->assertSee('<td>Grade</td>', false);
    }
}
