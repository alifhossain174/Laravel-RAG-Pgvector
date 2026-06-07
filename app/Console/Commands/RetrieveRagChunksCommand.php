<?php

namespace App\Console\Commands;

use App\Models\Conversation;
use App\Services\RagRetrievalService;
use Illuminate\Console\Command;

class RetrieveRagChunksCommand extends Command
{
    protected $signature = 'rag:retrieve {conversation_id} {question} {--limit=}';

    protected $description = 'Retrieve relevant document chunks for a conversation question using pgvector.';

    public function handle(RagRetrievalService $retrieval): int
    {
        $conversation = $this->findConversation((string) $this->argument('conversation_id'));

        if (! $conversation) {
            $this->error('Conversation not found. Pass a conversation ULID or numeric database id.');

            return self::FAILURE;
        }

        $question = (string) $this->argument('question');
        $limit = $this->option('limit') === null ? null : (int) $this->option('limit');

        $documents = $retrieval->searchableDocumentIds($conversation);

        $this->info('Question: '.$question);
        $this->line('Conversation: '.$conversation->title.' ['.$conversation->ulid.']');
        $this->line('Scope: '.$conversation->scope);
        $this->line('Top K: '.$retrieval->limitForQuestion($question, $limit));
        $this->line('Max context chars: '.config('services.rag.max_context_chars', 24000));
        $this->newLine();

        $this->line('Selected documents:');

        if ($documents->isEmpty()) {
            $this->warn('  No ready documents are available in this conversation scope.');
        } else {
            $conversation->user
                ->documents()
                ->whereIn('id', $documents->all())
                ->orderBy('title')
                ->get()
                ->each(function ($document): void {
                    $this->line('  - '.$document->displayTitle().' (#'.$document->id.')');
                });
        }

        $this->newLine();
        $this->info('Top chunks:');

        $results = $retrieval->retrieve($conversation, $question, $limit);

        if ($results === []) {
            $this->warn('  No matching embedded chunks found.');

            return self::SUCCESS;
        }

        foreach ($results as $index => $result) {
            $this->line(sprintf(
                '%d. %s | %s | score %.6f',
                $index + 1,
                $result['document_title'],
                $this->pageLabel($result['page_start'], $result['page_end']),
                $result['score']
            ));

            $this->line('   '.str($result['content'])->squish()->limit(220));
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
            return 'Page unknown';
        }

        if ($pageStart !== null && ($pageEnd === null || $pageStart === $pageEnd)) {
            return 'Page '.$pageStart;
        }

        return 'Pages '.$pageStart.'-'.$pageEnd;
    }
}
