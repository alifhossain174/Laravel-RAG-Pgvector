@php
    $title = 'Document Details';
@endphp

@extends('layouts.app')

@section('content')
    <div data-document-status-poller data-document-id="{{ $document->id }}" data-document-status-url="{{ route('documents.status', $document) }}" data-document-current-status="{{ $document->status }}">
    <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight text-slate-950">{{ $document->displayTitle() }}</h1>
            <p class="mt-2 text-sm text-slate-600">Document details, processing status, and chunk previews.</p>
        </div>
        <div class="flex flex-col gap-3 sm:flex-row">
            <a href="{{ route('chat.create') }}" data-document-chat-action data-document-ready-href="{{ route('chat.create') }}" aria-disabled="{{ $document->status === \App\Models\Document::STATUS_READY ? 'false' : 'true' }}" class="w-full rounded-lg bg-teal-600 px-4 py-2.5 text-center text-sm font-semibold text-white shadow-sm shadow-teal-200 hover:bg-teal-700 sm:w-auto {{ $document->status === \App\Models\Document::STATUS_READY ? '' : 'pointer-events-none cursor-not-allowed opacity-50' }}">
                Start chat
            </a>
            <form method="POST" action="{{ route('documents.destroy', $document) }}" onsubmit="return confirm('Delete this document and its stored file?');">
                @csrf
                @method('DELETE')
                <button type="submit" class="w-full rounded-lg border border-rose-200 px-4 py-2.5 text-sm font-semibold text-rose-700 hover:bg-rose-50 sm:w-auto">
                    Delete
                </button>
            </form>
        </div>
    </div>

    <section class="mt-6 grid gap-6 lg:grid-cols-[0.9fr_1.4fr]">
        <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <p class="font-semibold text-slate-950">Document details</p>
                    <p class="mt-2 text-sm leading-6 text-slate-600">{{ $document->description ?: 'No description provided.' }}</p>
                </div>
                <span data-document-status-badge>
                    @include('partials.status-badge', ['status' => $document->status, 'showSpinner' => true])
                </span>
            </div>
            <dl class="mt-6 grid grid-cols-2 gap-4 text-sm">
                <div class="rounded-lg bg-slate-50 p-4">
                    <dt class="text-slate-500">Original filename</dt>
                    <dd class="mt-1 break-words font-semibold text-slate-950">{{ $document->original_filename }}</dd>
                </div>
                <div class="rounded-lg bg-slate-50 p-4">
                    <dt class="text-slate-500">File size</dt>
                    <dd class="mt-1 font-semibold text-slate-950">{{ $document->formattedFileSize() }}</dd>
                </div>
                <div class="rounded-lg bg-slate-50 p-4">
                    <dt class="text-slate-500">MIME type</dt>
                    <dd class="mt-1 break-words font-semibold text-slate-950">{{ $document->mime_type ?: '-' }}</dd>
                </div>
                <div class="rounded-lg bg-slate-50 p-4">
                    <dt class="text-slate-500">Uploaded</dt>
                    <dd class="mt-1 font-semibold text-slate-950">{{ $document->created_at->format('M j, Y g:i A') }}</dd>
                </div>
                <div class="rounded-lg bg-slate-50 p-4">
                    <dt class="text-slate-500">Pages</dt>
                    <dd class="mt-1 font-semibold text-slate-950" data-document-total-pages>{{ $document->total_pages ?? '-' }}</dd>
                </div>
                <div class="rounded-lg bg-slate-50 p-4">
                    <dt class="text-slate-500">Chunks</dt>
                    <dd class="mt-1 font-semibold text-slate-950" data-document-total-chunks>{{ $document->total_chunks }}</dd>
                </div>
                <div class="rounded-lg bg-slate-50 p-4">
                    <dt class="text-slate-500">Processed</dt>
                    <dd class="mt-1 font-semibold text-slate-950" data-document-processed-at>{{ $document->processed_at?->format('M j, Y g:i A') ?? '-' }}</dd>
                </div>
                <div class="rounded-lg bg-slate-50 p-4">
                    <dt class="text-slate-500">Status</dt>
                    <dd class="mt-1 font-semibold text-slate-950" data-document-status-label>{{ $document->statusLabel() }}</dd>
                </div>
            </dl>

            <div class="mt-5 rounded-lg border border-rose-200 bg-rose-50 p-4 {{ $document->status === 'failed' && $document->failed_reason ? '' : 'hidden' }}" data-document-failed-reason>
                <p class="text-sm font-semibold text-rose-800">Failure reason</p>
                <p class="mt-2 text-sm leading-6 text-rose-700" data-document-failed-reason-text>{{ $document->failed_reason }}</p>
            </div>
        </div>

        <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <p class="font-semibold text-slate-950">Processing timeline</p>
            <div data-document-processing-timeline>
                @include('partials.document-processing-timeline', ['document' => $document])
            </div>
        </div>
    </section>

    <section class="mt-6 rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="flex flex-col justify-between gap-2 border-b border-slate-200 px-5 py-4 sm:flex-row sm:items-center">
            <h2 class="font-semibold text-slate-950">Chunk preview</h2>
            <p class="text-sm text-slate-500">
                Showing first {{ $chunks->count() }} of <span data-document-total-chunks>{{ $document->total_chunks }}</span> chunks
            </p>
        </div>
        <div class="divide-y divide-slate-100">
            @forelse ($chunks as $chunk)
                <article class="p-5">
                    @php
                        $pageLabel = 'Document source';

                        if ($chunk->page_start && $chunk->page_end && $chunk->page_start === $chunk->page_end) {
                            $pageLabel = 'Page '.$chunk->page_start;
                        } elseif ($chunk->page_start && $chunk->page_end) {
                            $pageLabel = 'Pages '.$chunk->page_start.'-'.$chunk->page_end;
                        } elseif ($chunk->page_start) {
                            $pageLabel = 'Page '.$chunk->page_start;
                        }
                    @endphp
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700">Chunk {{ $chunk->chunk_index }}</span>
                        <span class="rounded-full bg-teal-50 px-2.5 py-1 text-xs font-semibold text-teal-700">{{ $pageLabel }}</span>
                        <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700">{{ $chunk->token_count ?? 0 }} tokens</span>
                        <span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $chunk->hasEmbedding() ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700' }}">
                            Embedded: {{ $chunk->hasEmbedding() ? 'Yes' : 'No' }}
                        </span>
                    </div>
                    <p class="mt-3 text-sm leading-6 text-slate-700">{{ str($chunk->content)->limit(700) }}</p>
                </article>
            @empty
                <div class="p-10 text-center">
                    <p class="font-semibold text-slate-950">No chunks created yet.</p>
                    <p class="mt-2 text-sm text-slate-500">Processing may still be running.</p>
                </div>
            @endforelse
        </div>
    </section>
    </div>
@endsection
