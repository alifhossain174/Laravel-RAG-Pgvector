@php
    $title = 'Document Details';
    $timeline = [
        ['label' => 'Uploaded', 'step' => 1, 'statuses' => ['uploaded', 'processing', 'text_extracted', 'chunked', 'embedded', 'ready']],
        ['label' => 'Text Extracted', 'step' => 2, 'statuses' => ['text_extracted', 'chunked', 'embedded', 'ready']],
        ['label' => 'Chunks Created', 'step' => 3, 'statuses' => ['chunked', 'embedded', 'ready']],
        ['label' => 'Embeddings Stored', 'step' => 4, 'statuses' => ['embedded', 'ready']],
        ['label' => 'Ready for Chat', 'step' => 5, 'statuses' => ['ready']],
    ];
@endphp

@extends('layouts.app')

@section('content')
    <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight text-slate-950">{{ $document->displayTitle() }}</h1>
            <p class="mt-2 text-sm text-slate-600">Document details, processing status, and chunk previews.</p>
        </div>
        <div class="flex flex-col gap-3 sm:flex-row">
            <form method="POST" action="{{ route('documents.destroy', $document) }}" onsubmit="return confirm('Delete this document and its stored PDF?');">
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
                @include('partials.status-badge', ['status' => $document->status])
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
                    <dd class="mt-1 font-semibold text-slate-950">{{ $document->total_pages ?? '-' }}</dd>
                </div>
                <div class="rounded-lg bg-slate-50 p-4">
                    <dt class="text-slate-500">Chunks</dt>
                    <dd class="mt-1 font-semibold text-slate-950">{{ $document->total_chunks }}</dd>
                </div>
                <div class="rounded-lg bg-slate-50 p-4">
                    <dt class="text-slate-500">Processed</dt>
                    <dd class="mt-1 font-semibold text-slate-950">{{ $document->processed_at?->format('M j, Y g:i A') ?? '-' }}</dd>
                </div>
                <div class="rounded-lg bg-slate-50 p-4">
                    <dt class="text-slate-500">Status</dt>
                    <dd class="mt-1 font-semibold text-slate-950">{{ $document->statusLabel() }}</dd>
                </div>
            </dl>

            @if ($document->status === 'failed' && $document->failed_reason)
                <div class="mt-5 rounded-lg border border-rose-200 bg-rose-50 p-4">
                    <p class="text-sm font-semibold text-rose-800">Failure reason</p>
                    <p class="mt-2 text-sm leading-6 text-rose-700">{{ $document->failed_reason }}</p>
                </div>
            @endif
        </div>

        <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <p class="font-semibold text-slate-950">Processing timeline</p>
            <ol class="mt-5 space-y-4">
                @foreach ($timeline as $item)
                    @php
                        $complete = in_array($document->status, $item['statuses'], true);
                    @endphp
                    <li class="flex gap-3">
                        <span class="mt-0.5 grid size-7 shrink-0 place-items-center rounded-full text-xs font-semibold ring-1 {{ $complete ? 'bg-emerald-50 text-emerald-700 ring-emerald-200' : 'bg-slate-50 text-slate-500 ring-slate-200' }}">
                            {{ $complete ? 'OK' : $item['step'] }}
                        </span>
                        <div>
                            <p class="text-sm font-semibold text-slate-950">{{ $item['label'] }}</p>
                            <p class="mt-1 text-sm text-slate-500">
                                @if ($complete)
                                    Completed for the current document status.
                                @else
                                    Waiting for this step to complete.
                                @endif
                            </p>
                        </div>
                    </li>
                @endforeach
            </ol>
        </div>
    </section>

    <section class="mt-6 rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="flex flex-col justify-between gap-2 border-b border-slate-200 px-5 py-4 sm:flex-row sm:items-center">
            <h2 class="font-semibold text-slate-950">Chunk preview</h2>
            <p class="text-sm text-slate-500">
                Showing first {{ $chunks->count() }} of {{ $document->total_chunks }} chunks
            </p>
        </div>
        <div class="divide-y divide-slate-100">
            @forelse ($chunks as $chunk)
                <article class="p-5">
                    @php
                        $pageLabel = 'Pages -';

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
                        <span class="rounded-full bg-indigo-50 px-2.5 py-1 text-xs font-semibold text-indigo-700">{{ $pageLabel }}</span>
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
@endsection
