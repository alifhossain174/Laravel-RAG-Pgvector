<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\GenerateDocumentEmbeddingsJob;
use App\Jobs\ProcessDocumentJob;
use App\Models\Document;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AdminDocumentController extends Controller
{
    public function index(Request $request): View
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', Rule::in(Document::STATUSES)],
            'owner' => ['nullable', 'integer', 'exists:users,id'],
            'mime_type' => ['nullable', 'string', 'max:255'],
            'extension' => ['nullable', 'string', 'max:20', 'regex:/^[A-Za-z0-9]+$/'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ]);

        $search = trim((string) ($validated['search'] ?? ''));
        $query = Document::query()
            ->with('user:id,name,email')
            ->latest();

        if ($search !== '') {
            $operator = $this->searchOperator();
            $like = '%'.$search.'%';

            $query->where(function ($query) use ($operator, $like): void {
                $query
                    ->where('title', $operator, $like)
                    ->orWhere('original_filename', $operator, $like)
                    ->orWhereHas('user', fn ($query) => $query->where('email', $operator, $like));
            });
        }

        if (! empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        if (! empty($validated['owner'])) {
            $query->where('user_id', $validated['owner']);
        }

        if (! empty($validated['mime_type'])) {
            $query->where('mime_type', $validated['mime_type']);
        }

        if (! empty($validated['extension'])) {
            $query->whereRaw('LOWER(original_filename) LIKE ?', ['%.'.strtolower($validated['extension'])]);
        }

        if (! empty($validated['date_from'])) {
            $query->whereDate('created_at', '>=', $validated['date_from']);
        }

        if (! empty($validated['date_to'])) {
            $query->whereDate('created_at', '<=', $validated['date_to']);
        }

        return view('admin.documents.index', [
            'documents' => $query->paginate(15)->withQueryString(),
            'statuses' => Document::STATUSES,
            'owners' => User::query()
                ->whereHas('documents')
                ->orderBy('name')
                ->get(['id', 'name', 'email']),
            'mimeTypes' => Document::query()
                ->whereNotNull('mime_type')
                ->distinct()
                ->orderBy('mime_type')
                ->pluck('mime_type'),
            'extensions' => $this->extensionOptions(),
            'filters' => [
                'search' => $search,
                'status' => $validated['status'] ?? null,
                'owner' => isset($validated['owner']) ? (int) $validated['owner'] : null,
                'mime_type' => $validated['mime_type'] ?? null,
                'extension' => $validated['extension'] ?? null,
                'date_from' => $validated['date_from'] ?? null,
                'date_to' => $validated['date_to'] ?? null,
            ],
        ]);
    }

    public function show(Document $document): View
    {
        $document->load('user:id,name,email,is_admin,is_suspended');

        $totalChunks = $document->chunks()->count();
        $embeddedChunks = $document->chunks()->whereNotNull('embedded_at')->count();

        return view('admin.documents.show', [
            'document' => $document,
            'extension' => $this->extension($document),
            'failedReason' => $this->sanitizeFailureReason($document->failed_reason),
            'chunkMetrics' => [
                ['label' => 'Total Chunks', 'value' => $totalChunks],
                ['label' => 'Embedded Chunks', 'value' => $embeddedChunks],
                ['label' => 'Missing Embedding Chunks', 'value' => max(0, $totalChunks - $embeddedChunks)],
            ],
            'chunks' => $document->chunks()
                ->limit(20)
                ->get(['id', 'document_id', 'chunk_index', 'page_start', 'page_end', 'token_count', 'metadata', 'content', 'embedded_at']),
        ]);
    }

    public function retry(Document $document): RedirectResponse
    {
        if ($document->status !== Document::STATUS_FAILED) {
            return back()->with('error', 'Only failed documents can be retried.');
        }

        $this->queueProcessing($document);

        return back()->with('success', 'Document retry queued.');
    }

    public function regenerateEmbeddings(Document $document): RedirectResponse
    {
        if ($document->chunks()->count() === 0) {
            return back()->with('error', 'This document has no chunks to embed.');
        }

        GenerateDocumentEmbeddingsJob::dispatch($document->id);

        return back()->with('success', 'Embedding regeneration queued.');
    }

    public function reprocess(Document $document): RedirectResponse
    {
        $this->queueProcessing($document);

        return back()->with('success', 'Full document reprocess queued.');
    }

    public function destroy(Document $document): RedirectResponse
    {
        Storage::disk('local')->delete($document->file_path);
        $document->delete();

        return redirect()
            ->route('admin.documents.index')
            ->with('success', 'Document deleted successfully.');
    }

    private function queueProcessing(Document $document): void
    {
        $document->forceFill([
            'status' => Document::STATUS_UPLOADED,
            'failed_reason' => null,
        ])->save();

        ProcessDocumentJob::dispatch($document->id);
    }

    private function extensionOptions(): Collection
    {
        return Document::query()
            ->pluck('original_filename')
            ->map(fn (string $filename): string => $this->extensionFromFilename($filename))
            ->filter()
            ->unique()
            ->sort()
            ->values();
    }

    private function extension(Document $document): string
    {
        return $this->extensionFromFilename($document->original_filename) ?: 'unknown';
    }

    private function extensionFromFilename(string $filename): string
    {
        return strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    }

    private function sanitizeFailureReason(?string $reason): ?string
    {
        if (! $reason) {
            return null;
        }

        $reason = preg_replace('/\b[A-Za-z]:[\\\\\/][^\s]+/u', '[path hidden]', $reason) ?? $reason;
        $reason = preg_replace('/(?<!:)\/(?:[^\s\/]+\/)+[^\s]+/u', '[path hidden]', $reason) ?? $reason;

        return str($reason)->limit(1000)->toString();
    }

    private function searchOperator(): string
    {
        return Document::query()->getConnection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';
    }
}
