<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\Message;
use App\Services\LlmService;
use App\Services\RagRetrievalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Throwable;

class ConversationMessageController extends Controller
{
    public function store(
        Request $request,
        Conversation $conversation,
        RagRetrievalService $retrieval,
        LlmService $llm,
    ): RedirectResponse
    {
        $this->authorize('view', $conversation);

        $this->ensureMessageRateLimit($request, $conversation);

        $validated = $request->validate([
            'content' => ['required', 'string', 'max:4000'],
        ]);

        DB::transaction(function () use ($conversation, $validated): void {
            if (! $conversation->messages()->exists() && $this->hasGenericTitle($conversation)) {
                $conversation->forceFill([
                    'title' => $this->titleFromQuestion($validated['content']),
                ])->save();
            }

            $conversation->messages()->create([
                'role' => Message::ROLE_USER,
                'content' => $validated['content'],
            ]);
        });

        try {
            $chunks = $retrieval->retrieve($conversation, $validated['content']);

            if ($chunks === []) {
                $this->storeAssistantMessage(
                    conversation: $conversation,
                    content: 'I could not find relevant information in the selected documents.',
                    metadata: [
                        'sources' => [],
                    ]
                );

                return redirect()
                    ->route('chat.show', $conversation)
                    ->with('success', 'Message sent successfully.');
            }

            $history = $conversation
                ->messages()
                ->reorder()
                ->latest()
                ->limit(8)
                ->get()
                ->reverse()
                ->map(fn (Message $message): array => [
                    'role' => $message->role,
                    'content' => $message->content,
                ])
                ->values()
                ->all();

            $result = $llm->answerWithContext($validated['content'], $chunks, $history);

            $this->storeAssistantMessage(
                conversation: $conversation,
                content: $result['answer'],
                metadata: [
                    'provider' => $result['provider'],
                    'model' => $result['model'],
                    'sources' => $this->formatSources($chunks),
                ]
            );
        } catch (Throwable $exception) {
            Log::error('RAG chat answer generation failed.', [
                'conversation_id' => $conversation->id,
                'conversation_ulid' => $conversation->ulid,
                'user_id' => $conversation->user_id,
                'message' => $exception->getMessage(),
                'status_code' => $exception->getCode(),
            ]);

            $this->storeAssistantMessage(
                conversation: $conversation,
                content: 'Sorry, I could not generate an answer right now. Please try again.',
                metadata: [
                    'sources' => [],
                    'error' => true,
                ]
            );
        }

        return redirect()
            ->route('chat.show', $conversation)
            ->with('success', 'Message sent successfully.');
    }

    private function storeAssistantMessage(Conversation $conversation, string $content, array $metadata): void
    {
        DB::transaction(function () use ($conversation, $content, $metadata): void {
            $conversation->messages()->create([
                'role' => Message::ROLE_ASSISTANT,
                'content' => $content,
                'metadata' => $metadata,
            ]);

            $conversation->touch();
        });
    }

    private function formatSources(array $chunks): array
    {
        return collect($chunks)
            ->map(fn (array $chunk): array => [
                'chunk_id' => $chunk['chunk_id'] ?? null,
                'document_id' => $chunk['document_id'] ?? null,
                'document_title' => $chunk['document_title'] ?? 'Document source',
                'page_start' => $chunk['page_start'] ?? null,
                'page_end' => $chunk['page_end'] ?? null,
                'score' => $chunk['score'] ?? null,
                'preview' => str((string) ($chunk['content'] ?? ''))->squish()->limit(260)->toString(),
            ])
            ->values()
            ->all();
    }

    private function ensureMessageRateLimit(Request $request, Conversation $conversation): void
    {
        $limit = (int) config('services.rag.message_rate_limit_per_minute', 20);

        if ($limit <= 0) {
            return;
        }

        $key = 'rag-message:'.$conversation->user_id.':'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, $limit)) {
            throw ValidationException::withMessages([
                'content' => 'Please wait before sending another message.',
            ]);
        }

        RateLimiter::hit($key, 60);
    }

    private function hasGenericTitle(Conversation $conversation): bool
    {
        return in_array(strtolower(trim($conversation->title)), [
            'new conversation',
            'untitled conversation',
            'conversation',
        ], true);
    }

    private function titleFromQuestion(string $question): string
    {
        return str($question)
            ->squish()
            ->limit(60, '')
            ->trim(' .,;:-')
            ->whenEmpty(fn () => str('New Conversation'))
            ->toString();
    }
}
