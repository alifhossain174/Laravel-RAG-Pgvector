@php
    $title = 'Admin Document Details';
@endphp

@extends('layouts.admin')

@section('content')
    <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
        <div>
            <a href="{{ route('admin.documents.index') }}" class="text-sm font-semibold text-teal-700 hover:text-teal-800">Back to documents</a>
            <h1 class="mt-2 text-2xl font-semibold tracking-tight text-slate-950">{{ $document->displayTitle() }}</h1>
            <p class="mt-2 text-sm text-slate-600">{{ $document->original_filename }}</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <form method="POST" action="{{ route('admin.documents.retry', $document) }}">
                @csrf
                <button type="submit" class="rounded-lg border border-amber-200 bg-white px-4 py-2.5 text-sm font-semibold text-amber-700 hover:bg-amber-50">
                    Retry failed
                </button>
            </form>
            <form method="POST" action="{{ route('admin.documents.regenerate-embeddings', $document) }}">
                @csrf
                <button type="submit" class="rounded-lg border border-teal-200 bg-white px-4 py-2.5 text-sm font-semibold text-teal-700 hover:bg-teal-50">
                    Regenerate embeddings
                </button>
            </form>
            <form method="POST" action="{{ route('admin.documents.reprocess', $document) }}">
                @csrf
                <button type="submit" class="rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                    Reprocess
                </button>
            </form>
            <form method="POST" action="{{ route('admin.documents.destroy', $document) }}" onsubmit="return confirm('Delete this document and its stored file?');">
                @csrf
                @method('DELETE')
                <button type="submit" class="rounded-lg bg-rose-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm shadow-rose-200 hover:bg-rose-700">
                    Delete
                </button>
            </form>
        </div>
    </div>

    <section class="mt-6 grid gap-6 lg:grid-cols-[0.9fr_1.4fr]">
        <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <h2 class="font-semibold text-slate-950">Metadata</h2>
                    <p class="mt-2 text-sm text-slate-500">Owner and processing state for this document.</p>
                </div>
                @include('partials.status-badge', ['status' => $document->status])
            </div>
            <dl class="mt-6 grid grid-cols-2 gap-4 text-sm">
                <div class="rounded-lg bg-slate-50 p-4">
                    <dt class="text-slate-500">Owner</dt>
                    <dd class="mt-1 break-words font-semibold text-slate-950">
                        @if ($document->user)
                            <a href="{{ route('admin.users.show', $document->user) }}" class="hover:text-teal-700">{{ $document->user->email }}</a>
                        @else
                            Deleted user
                        @endif
                    </dd>
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
                    <dt class="text-slate-500">Extension</dt>
                    <dd class="mt-1 font-semibold text-slate-950">{{ strtoupper($extension) }}</dd>
                </div>
                <div class="rounded-lg bg-slate-50 p-4">
                    <dt class="text-slate-500">Pages</dt>
                    <dd class="mt-1 font-semibold text-slate-950">{{ $document->total_pages ?? '-' }}</dd>
                </div>
                <div class="rounded-lg bg-slate-50 p-4">
                    <dt class="text-slate-500">Chunks</dt>
                    <dd class="mt-1 font-semibold text-slate-950">{{ number_format($document->total_chunks) }}</dd>
                </div>
                <div class="rounded-lg bg-slate-50 p-4">
                    <dt class="text-slate-500">Uploaded</dt>
                    <dd class="mt-1 font-semibold text-slate-950">{{ $document->created_at->format('M j, Y g:i A') }}</dd>
                </div>
                <div class="rounded-lg bg-slate-50 p-4">
                    <dt class="text-slate-500">Processed</dt>
                    <dd class="mt-1 font-semibold text-slate-950">{{ $document->processed_at?->format('M j, Y g:i A') ?? '-' }}</dd>
                </div>
            </dl>

            @if ($failedReason)
                <div class="mt-5 rounded-lg border border-rose-200 bg-rose-50 p-4">
                    <p class="text-sm font-semibold text-rose-800">Failure reason</p>
                    <p class="mt-2 text-sm leading-6 text-rose-700">{{ $failedReason }}</p>
                </div>
            @endif
        </div>

        <div>
            <section class="grid gap-4 sm:grid-cols-3">
                @foreach ($chunkMetrics as $metric)
                    <article class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                        <p class="text-sm font-medium text-slate-500">{{ $metric['label'] }}</p>
                        <p class="mt-3 text-3xl font-semibold tracking-tight text-slate-950">{{ number_format($metric['value']) }}</p>
                    </article>
                @endforeach
            </section>

            <section class="mt-6 rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="font-semibold text-slate-950">Description</h2>
                <p class="mt-2 text-sm leading-6 text-slate-600">{{ $document->description ?: 'No description provided.' }}</p>
            </section>
        </div>
    </section>

    <section class="mt-6 rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="flex flex-col justify-between gap-2 border-b border-slate-200 px-5 py-4 sm:flex-row sm:items-center">
            <h2 class="font-semibold text-slate-950">Chunk preview</h2>
            <p class="text-sm text-slate-500">Showing first {{ $chunks->count() }} chunks</p>
        </div>
        <div class="divide-y divide-slate-100">
            @forelse ($chunks as $chunk)
                @php
                    $metadata = is_array($chunk->metadata) ? $chunk->metadata : [];
                    $extractionMethod = $metadata['extraction_method']
                        ?? ($metadata['pages'][0]['extraction_method'] ?? null)
                        ?? (isset($metadata['extraction_methods']) && is_array($metadata['extraction_methods']) ? implode(', ', $metadata['extraction_methods']) : null);
                @endphp
                <article class="p-5">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700">Chunk {{ $chunk->chunk_index }}</span>
                        <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700">Pages {{ $chunk->page_start ?? '-' }}-{{ $chunk->page_end ?? '-' }}</span>
                        <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700">{{ $chunk->token_count ?? 0 }} tokens</span>
                        <span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $chunk->embedded_at ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700' }}">
                            {{ $chunk->embedded_at ? 'Embedded' : 'Missing embedding' }}
                        </span>
                        @if ($extractionMethod)
                            <span class="rounded-full bg-cyan-50 px-2.5 py-1 text-xs font-semibold text-cyan-700">{{ $extractionMethod }}</span>
                        @endif
                    </div>
                    <p class="mt-3 text-sm leading-6 text-slate-700">{{ str($chunk->content)->limit(700) }}</p>
                </article>
            @empty
                <div class="p-10 text-center">
                    <p class="font-semibold text-slate-950">No chunks created yet</p>
                    <p class="mt-2 text-sm text-slate-500">Retry or reprocess the document if processing did not complete.</p>
                </div>
            @endforelse
        </div>
    </section>
@endsection
