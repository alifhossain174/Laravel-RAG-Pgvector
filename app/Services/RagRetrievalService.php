<?php

namespace App\Services;

use App\Jobs\GenerateDocumentEmbeddingsJob;
use App\Models\Conversation;
use App\Models\Document;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class RagRetrievalService
{
    private SettingsService $settings;

    public function __construct(
        private readonly EmbeddingService $embeddings,
        ?SettingsService $settings = null,
    ) {
        $this->settings = $settings ?? app(SettingsService::class);
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
        $limit = $this->limitForQuestion($question, $limit);
        $documentIds = $this->searchableDocumentIds($conversation);

        if ($documentIds->isEmpty()) {
            return [];
        }

        $queryEmbedding = $this->embeddings->embedQuery($question);
        $queryVector = GenerateDocumentEmbeddingsJob::formatVectorForPgvector($queryEmbedding);
        $maxDistance = $this->maxDistance();

        /*
         * Keep this in pgvector's index-friendly nearest-neighbor shape:
         * ORDER BY embedding <=> query_vector ASC with a bounded LIMIT.
         * The optional max-distance cutoff is applied after this scan so the
         * HNSW cosine index can still be considered for the ORDER BY operator.
         */
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

        $candidates = $query
            ->get()
            ->reject(fn (object $row): bool => $maxDistance !== null && (float) $row->distance > $maxDistance)
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

    public function limitForQuestion(string $question, ?int $limit = null): int
    {
        if ($limit !== null) {
            return $this->clampLimit($limit);
        }

        $configuredLimit = $this->settings->ragTopK();

        if ($this->isBroadSummaryQuestion($question)) {
            $configuredLimit = max($configuredLimit, $this->settings->ragSummaryTopK());
        }

        return $this->clampLimit($configuredLimit);
    }

    private function maxDistance(): ?float
    {
        return $this->settings->ragRetrievalMaxDistance();
    }

    private function clampLimit(int $limit): int
    {
        return max(1, min($limit, 20));
    }

    private function isBroadSummaryQuestion(string $question): bool
    {
        $question = str($question)->lower()->squish()->toString();

        return str_contains($question, 'summarize')
            || str_contains($question, 'summary')
            || str_contains($question, 'overview')
            || str_contains($question, 'main points')
            || str_contains($question, 'key points')
            || str_contains($question, 'whole document')
            || str_contains($question, 'entire document');
    }

    private function candidateLimit(int $limit): int
    {
        return min(max($limit * 4, $limit), 80);
    }

    private function maxContextChars(): int
    {
        return $this->settings->ragMaxContextChars();
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
