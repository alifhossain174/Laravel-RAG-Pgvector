@php
    $title = 'Dashboard';
    $stats = [
        ['label' => 'Total Documents', 'value' => $totalDocuments, 'meta' => 'Uploads', 'helper' => 'Documents uploaded by you.', 'tone' => 'teal'],
        ['label' => 'Ready Documents', 'value' => $readyDocuments, 'meta' => 'Ready', 'helper' => 'Documents ready for grounded chat answers.', 'tone' => 'emerald'],
        ['label' => 'Processing or Uploaded', 'value' => $pendingDocuments, 'meta' => 'Queue', 'helper' => 'Documents waiting for extraction, chunks, or embeddings.', 'tone' => 'amber'],
        ['label' => 'Total Questions Asked', 'value' => $totalQuestions, 'meta' => 'Chat', 'helper' => 'Questions submitted across your conversations.', 'tone' => 'cyan'],
    ];
@endphp

@extends('layouts.app')

@section('content')
    <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight text-slate-950">Dashboard</h1>
            <p class="mt-2 text-sm text-slate-600">Monitor your documents, processing status, and chat activity.</p>
        </div>
        <div class="flex flex-col gap-3 sm:flex-row">
            <a href="{{ route('documents.index') }}" class="rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-center text-sm font-semibold text-slate-700 hover:bg-slate-50">
                View All Documents
            </a>
            <a href="{{ route('documents.create') }}" class="rounded-lg bg-teal-600 px-4 py-2.5 text-center text-sm font-semibold text-white shadow-sm shadow-teal-200 hover:bg-teal-700">
                Upload Document
            </a>
        </div>
    </div>

    <section class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ($stats as $stat)
            @include('partials.stats-card', $stat)
        @endforeach
    </section>

    <section class="mt-6 rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4">
            <h2 class="font-semibold text-slate-950">Recently uploaded documents</h2>
            <a href="{{ route('documents.index') }}" class="text-sm font-semibold text-teal-700 hover:text-teal-800">View all</a>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-5 py-3">Document</th>
                    <th class="px-5 py-3">Status</th>
                    <th class="px-5 py-3">Pages</th>
                    <th class="px-5 py-3">Chunks</th>
                    <th class="px-5 py-3">Uploaded</th>
                </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                @forelse ($recentDocuments as $document)
                    <tr>
                        <td class="px-5 py-4">
                            <a href="{{ route('documents.show', $document) }}" class="font-medium text-slate-950 hover:text-teal-700">{{ $document->displayTitle() }}</a>
                            <p class="mt-1 text-xs text-slate-500">{{ $document->formattedFileSize() }}</p>
                        </td>
                        <td class="px-5 py-4">@include('partials.status-badge', ['status' => $document->status])</td>
                        <td class="px-5 py-4 text-slate-600">{{ $document->total_pages ?? '-' }}</td>
                        <td class="px-5 py-4 text-slate-600">{{ $document->total_chunks }}</td>
                        <td class="px-5 py-4 text-slate-600">{{ $document->created_at->diffForHumans() }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-5 py-12 text-center">
                            <p class="font-semibold text-slate-950">No documents yet</p>
                            <p class="mt-2 text-sm text-slate-500">Upload your first document to start building a searchable knowledge base.</p>
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection
