<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessDocumentJob;
use App\Models\Document;
use App\Services\LimitService;
use App\Services\SettingsService;
use App\Services\UsageTrackingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class DocumentController extends Controller
{
    public function create(Request $request): View
    {
        $recentDocuments = $request->user()
            ->documents()
            ->latest()
            ->limit(5)
            ->get();

        return view('documents.upload', [
            'recentDocuments' => $recentDocuments,
        ]);
    }

    public function store(Request $request, UsageTrackingService $usage, LimitService $limits, SettingsService $settings): RedirectResponse
    {
        if (! $settings->uploadsEnabled()) {
            throw ValidationException::withMessages([
                'document' => 'Document uploads are currently disabled.',
            ]);
        }

        $file = $request->file('document');

        if ($file && strtolower($file->getClientOriginalExtension()) === 'doc') {
            throw ValidationException::withMessages([
                'document' => 'Legacy .doc files are not supported yet. Please upload PDF, DOCX, XLSX, or CSV.',
            ]);
        }

        $allowedExtensions = $settings->allowedUploadExtensions();
        $allowedMimeTypes = $settings->allowedUploadMimeTypes();
        $allowedLabel = $settings->allowedUploadLabel() ?: 'supported';

        $validated = $request->validate([
            'title' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'document' => [
                'required',
                'file',
                'mimes:'.implode(',', $allowedExtensions),
                'mimetypes:'.implode(',', $allowedMimeTypes),
                'max:'.$settings->maxUploadKilobytes(),
            ],
        ], [
            'document.mimes' => "Please upload a {$allowedLabel} document.",
            'document.mimetypes' => "Please upload a {$allowedLabel} document.",
            'document.max' => 'Please upload a document no larger than '.$settings->maxUploadMegabytes().' MB.',
        ]);

        $limitCheck = $limits->canUpload($request->user(), $file);

        if (! $limitCheck['allowed']) {
            throw ValidationException::withMessages([
                'document' => $limitCheck['message'],
            ]);
        }

        $path = $file->store('documents/'.$request->user()->id, 'local');

        $document = $request->user()->documents()->create([
            'title' => $validated['title'] ?? null,
            'description' => $validated['description'] ?? null,
            'original_filename' => $file->getClientOriginalName(),
            'file_path' => $path,
            'mime_type' => $file->getClientMimeType(),
            'file_size' => $file->getSize(),
            'status' => Document::STATUS_UPLOADED,
            'total_pages' => null,
            'total_chunks' => 0,
        ]);

        $usage->log([
            'user_id' => $request->user()->id,
            'document_id' => $document->id,
            'action_type' => 'document_uploaded',
            'metadata' => [
                'mime_type' => $document->mime_type,
                'extension' => strtolower($file->getClientOriginalExtension()),
                'file_size' => $document->file_size,
            ],
        ]);

        ProcessDocumentJob::dispatch($document->id);

        return redirect()
            ->route('documents.show', $document)
            ->with('success', 'Document uploaded successfully. Processing has started.');
    }

    public function index(Request $request): View|JsonResponse
    {
        $search = trim((string) $request->query('search'));
        $status = $request->query('status');

        $documents = $request->user()
            ->documents()
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $operator = $this->caseInsensitiveLikeOperator($query);

                    $query->where('title', $operator, '%'.$search.'%')
                        ->orWhere('original_filename', $operator, '%'.$search.'%');
                });
            })
            ->when(in_array($status, Document::STATUSES, true), function ($query) use ($status) {
                $query->where('status', $status);
            })
            ->latest()
            ->paginate(9)
            ->withQueryString();

        $viewData = [
            'documents' => $documents,
            'statuses' => Document::STATUSES,
            'search' => $search,
            'selectedStatus' => in_array($status, Document::STATUSES, true) ? $status : null,
        ];

        if ($request->expectsJson()) {
            return response()->json([
                'html' => view('documents.partials.results', $viewData)->render(),
            ]);
        }

        return view('documents.index', $viewData);
    }

    public function show(Document $document): View
    {
        $this->authorize('view', $document);

        $document->load(['chunks' => fn ($query) => $query->limit(5)]);

        return view('documents.show', [
            'document' => $document,
            'chunks' => $document->chunks,
        ]);
    }

    public function status(Request $request, Document $document): JsonResponse
    {
        $this->authorize('view', $document);

        $document->refresh();

        return response()->json([
            'id' => $document->id,
            'status' => $document->status,
            'status_label' => $this->statusLabel($document),
            'status_badge_html' => view('partials.status-badge', [
                'status' => $document->status,
                'showSpinner' => true,
            ])->render(),
            'timeline_html' => view('partials.document-processing-timeline', [
                'document' => $document,
            ])->render(),
            'total_pages' => $document->total_pages,
            'total_chunks' => $document->total_chunks,
            'processed_at' => $document->processed_at?->format('M j, Y g:i A'),
            'processed_at_relative' => $document->processed_at?->diffForHumans(),
            'failed_reason' => $document->failed_reason,
            'is_ready' => $document->status === Document::STATUS_READY,
            'is_failed' => $document->status === Document::STATUS_FAILED,
            'can_chat' => $document->status === Document::STATUS_READY,
            'updated_at' => $document->updated_at?->toIso8601String(),
        ]);
    }

    public function destroy(Document $document): RedirectResponse
    {
        $this->authorize('delete', $document);

        Storage::disk('local')->delete($document->file_path);
        $document->delete();

        return redirect()
            ->route('documents.index')
            ->with('success', 'Document deleted successfully.');
    }

    private function caseInsensitiveLikeOperator($query): string
    {
        return $query->getConnection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';
    }

    private function statusLabel(Document $document): string
    {
        return $document->statusLabel();
    }
}
