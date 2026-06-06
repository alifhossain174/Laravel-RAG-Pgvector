<?php

namespace App\Services;

use App\Jobs\GenerateDocumentEmbeddingsJob;
use App\Models\Conversation;
use App\Models\Document;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class RagRetrievalService
{
    public function __construct(
        private readonly EmbeddingService $embeddings,
    ) {
    }

    /**
     * @return array<int, array{
     *     chunk_id: int,
     *     document_id: int,
     *     document_title: string,
     *     page_start: int|null,
     *     page_end: int|null,
     *     content: string,
     *     score: float
     * }>
     */
    public function retrieve(Conversation $conversation, string $question, ?int $limit = null): array
    {
        $limit = $this->topK($limit);
        $documentIds = $this->searchableDocumentIds($conversation);

        if ($documentIds->isEmpty()) {
            return [];
        }

        $queryEmbedding = $this->embeddings->embedQuery($question);
        $queryVector = GenerateDocumentEmbeddingsJob::formatVectorForPgvector($queryEmbedding);

        $query = DB::table('document_chunks as dc')
            ->join('documents as d', 'd.id', '=', 'dc.document_id')
            ->whereNotNull('dc.embedding')
            ->where('d.user_id', $conversation->user_id)
            ->where('d.status', Document::STATUS_READY)
            ->whereIn('d.id', $documentIds->all())
            ->select([
                'dc.id as chunk_id',
                'dc.document_id',
                'd.title',
                'd.original_filename',
                'dc.page_start',
                'dc.page_end',
                'dc.content',
            ])
            ->selectRaw('(dc.embedding <=> ?::vector) as distance', [$queryVector])
            ->orderByRaw('dc.embedding <=> ?::vector', [$queryVector])
            ->limit($this->candidateLimit($limit));

        $maxDistance = $this->maxDistance();

        if ($maxDistance !== null) {
            $query->whereRaw('(dc.embedding <=> ?::vector) <= ?', [$queryVector, $maxDistance]);
        }

        $candidates = $query
            ->get()
            ->map(fn (object $row): array => [
                'chunk_id' => (int) $row->chunk_id,
                'document_id' => (int) $row->document_id,
                'document_title' => $row->title ?: $row->original_filename,
                'page_start' => $row->page_start === null ? null : (int) $row->page_start,
                'page_end' => $row->page_end === null ? null : (int) $row->page_end,
                'content' => (string) $row->content,
                'score' => round(1 - (float) $row->distance, 6),
            ])
            ->sortByDesc('score')
            ->values();

        return $this->selectFinalChunks($candidates, $limit);
    }

    /**
     * @return Collection<int, int>
     */
    public function searchableDocumentIds(Conversation $conversation): Collection
    {
        if ($conversation->usesAllDocuments()) {
            return $conversation->user
                ->documents()
                ->where('status', Document::STATUS_READY)
                ->pluck('id')
                ->map(fn (mixed $id): int => (int) $id);
        }

        return $conversation
            ->documents()
            ->where('documents.status', Document::STATUS_READY)
            ->pluck('documents.id')
            ->map(fn (mixed $id): int => (int) $id);
    }

    private function maxDistance(): ?float
    {
        $value = config('services.rag.retrieval_max_distance');

        if ($value === null || $value === '') {
            return null;
        }

        return (float) $value;
    }

    private function topK(?int $limit): int
    {
        $limit = $limit ?: (int) config('services.rag.top_k', 6);

        return max(1, min($limit, 20));
    }

    private function candidateLimit(int $limit): int
    {
        return min(max($limit * 4, $limit), 80);
    }

    private function maxContextChars(): int
    {
        $value = (int) config('services.rag.max_context_chars', 12000);

        return max(1000, $value);
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $candidates
     * @return array<int, array<string, mixed>>
     */
    private function selectFinalChunks(Collection $candidates, int $limit): array
    {
        $selected = collect();
        $deferredDuplicates = collect();
        $pageKeys = [];
        $usedChars = 0;
        $maxChars = $this->maxContextChars();

        foreach ($candidates as $chunk) {
            $pageKey = $this->pageKey($chunk);

            if ($pageKey !== null && isset($pageKeys[$pageKey])) {
                $deferredDuplicates->push($chunk);

                continue;
            }

            $prepared = $this->fitChunkToBudget($chunk, $usedChars, $maxChars);

            if ($prepared === null) {
                break;
            }

            $selected->push($prepared);
            $usedChars += strlen($prepared['content']);

            if ($pageKey !== null) {
                $pageKeys[$pageKey] = true;
            }

            if ($selected->count() >= $limit) {
                break;
            }
        }

        foreach ($deferredDuplicates as $chunk) {
            if ($selected->count() >= $limit) {
                break;
            }

            $prepared = $this->fitChunkToBudget($chunk, $usedChars, $maxChars);

            if ($prepared === null) {
                break;
            }

            $selected->push($prepared);
            $usedChars += strlen($prepared['content']);
        }

        return $selected
            ->sortByDesc('score')
            ->values()
            ->all();
    }

    private function pageKey(array $chunk): ?string
    {
        if (($chunk['page_start'] ?? null) === null && ($chunk['page_end'] ?? null) === null) {
            return null;
        }

        return implode(':', [
            $chunk['document_id'],
            $chunk['page_start'] ?? 'unknown',
            $chunk['page_end'] ?? $chunk['page_start'] ?? 'unknown',
        ]);
    }

    private function fitChunkToBudget(array $chunk, int $usedChars, int $maxChars): ?array
    {
        $remaining = $maxChars - $usedChars;

        if ($remaining <= 0) {
            return null;
        }

        $content = (string) $chunk['content'];

        if (strlen($content) > $remaining) {
            if ($remaining < 300) {
                return null;
            }

            $chunk['content'] = str($content)->limit($remaining)->toString();
        }

        return $chunk;
    }
}
