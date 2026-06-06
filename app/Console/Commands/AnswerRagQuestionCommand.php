<?php

namespace App\Console\Commands;

use App\Models\Conversation;
use App\Services\LlmService;
use App\Services\RagRetrievalService;
use Illuminate\Console\Command;

class AnswerRagQuestionCommand extends Command
{
    protected $signature = 'rag:answer {conversation_id} {question} {--limit=}';

    protected $description = 'Retrieve relevant chunks and generate a grounded answer with the configured LLM.';

    public function handle(RagRetrievalService $retrieval, LlmService $llm): int
    {
        $conversation = $this->findConversation((string) $this->argument('conversation_id'));

        if (! $conversation) {
            $this->error('Conversation not found. Pass a conversation ULID or numeric database id.');

            return self::FAILURE;
        }

        $question = (string) $this->argument('question');
        $limit = $this->option('limit') === null ? null : max(1, (int) $this->option('limit'));

        $conversation->load([
            'messages' => fn ($query) => $query->reorder()->latest()->limit(8),
        ]);

        $history = $conversation->messages
            ->reverse()
            ->map(fn ($message): array => [
                'role' => $message->role,
                'content' => $message->content,
            ])
            ->values()
            ->all();

        $this->info('Question: '.$question);
        $this->line('Conversation: '.$conversation->title.' ['.$conversation->ulid.']');
        $this->line('Provider/model: '.$llm->provider().' / '.$llm->model());
        $this->line('Top K: '.($limit ?: config('services.rag.top_k', 6)));
        $this->line('Max context chars: '.config('services.rag.max_context_chars', 12000));
        $this->newLine();

        $chunks = $retrieval->retrieve($conversation, $question, $limit);

        if ($chunks === []) {
            $this->warn('No relevant embedded chunks were retrieved.');
        }

        $result = $llm->answerWithContext($question, $chunks, $history);

        $this->info('Answer:');
        $this->line($result['answer']);
        $this->newLine();

        $this->info('Citations/source chunks used:');

        if ($chunks === []) {
            $this->warn('  No source chunks used.');

            return self::SUCCESS;
        }

        foreach ($chunks as $index => $chunk) {
            $this->line(sprintf(
                '%d. %s | %s | score %.6f',
                $index + 1,
                $chunk['document_title'],
                $this->pageLabel($chunk['page_start'], $chunk['page_end']),
                $chunk['score']
            ));
            $this->line('   '.str($chunk['content'])->squish()->limit(220));
        }

        return self::SUCCESS;
    }

    private function findConversation(string $key): ?Conversation
    {
        $query = Conversation::query()->with('user');

        if (ctype_digit($key)) {
            return $query->whereKey((int) $key)->first();
        }

        return $query->where('ulid', $key)->first();
    }

    private function pageLabel(?int $pageStart, ?int $pageEnd): string
    {
        if ($pageStart === null && $pageEnd === null) {
            return 'Pages pending';
        }

        if ($pageStart !== null && ($pageEnd === null || $pageStart === $pageEnd)) {
            return 'Page '.$pageStart;
        }

        return 'Pages '.$pageStart.'-'.$pageEnd;
    }
}
