@php
    $title = 'Admin Dashboard';

    $metricGroups = [
        ['heading' => 'Users', 'metrics' => $userMetrics],
        ['heading' => 'Documents', 'metrics' => $documentMetrics],
        ['heading' => 'Chunks', 'metrics' => $chunkMetrics],
        ['heading' => 'Conversations', 'metrics' => $conversationMetrics],
        ['heading' => 'Queues and Storage', 'metrics' => $queueMetrics],
    ];
@endphp

@extends('layouts.admin')

@section('content')
    <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight text-slate-950">Admin Dashboard</h1>
            <p class="mt-2 text-sm text-slate-600">Admin access confirmed for {{ auth()->user()->name }}.</p>
        </div>
        <a href="{{ route('dashboard') }}" class="rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-center text-sm font-semibold text-slate-700 hover:bg-slate-50">
            Return to app
        </a>
    </div>

    @foreach ($metricGroups as $group)
        <section class="mt-6">
            <div class="mb-3 flex items-center justify-between">
                <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-500">{{ $group['heading'] }}</h2>
            </div>
            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                @foreach ($group['metrics'] as $metric)
                    <article class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                        <p class="text-sm font-medium text-slate-500">{{ $metric['label'] }}</p>
                        <p class="mt-3 text-3xl font-semibold tracking-tight text-slate-950">{{ $metric['value'] }}</p>
                        <p class="mt-2 text-xs text-slate-500">{{ $metric['helper'] }}</p>
                    </article>
                @endforeach
            </div>
        </section>
    @endforeach

    <section class="mt-6 rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 px-5 py-4">
            <h2 class="font-semibold text-slate-950">File type distribution</h2>
            <p class="mt-1 text-sm text-slate-500">Grouped by original filename extension and stored MIME type.</p>
        </div>
        <div class="divide-y divide-slate-100">
            @forelse ($fileTypeDistribution as $fileType)
                <div class="flex flex-col gap-1 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p class="font-medium text-slate-950">{{ $fileType['label'] }}</p>
                        <p class="text-sm text-slate-500">{{ $fileType['mime_type'] }}</p>
                    </div>
                    <p class="text-sm font-semibold text-slate-700">{{ number_format($fileType['count']) }} documents</p>
                </div>
            @empty
                <p class="px-5 py-8 text-sm text-slate-500">No documents uploaded yet.</p>
            @endforelse
        </div>
    </section>

    <div class="mt-6 grid gap-6 xl:grid-cols-2">
        <section class="rounded-lg border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-5 py-4">
                <h2 class="font-semibold text-slate-950">Latest users</h2>
            </div>
            <div class="divide-y divide-slate-100">
                @forelse ($latestUsers as $user)
                    <div class="flex items-center justify-between gap-4 px-5 py-4">
                        <div class="min-w-0">
                            <p class="truncate font-medium text-slate-950">{{ $user->name }}</p>
                            <p class="truncate text-sm text-slate-500">{{ $user->email }}</p>
                        </div>
                        <div class="shrink-0 text-right">
                            <p class="text-sm font-medium {{ $user->is_suspended ? 'text-rose-700' : 'text-emerald-700' }}">{{ $user->is_suspended ? 'Suspended' : 'Active' }}</p>
                            <p class="text-xs text-slate-500">{{ $user->is_admin ? 'Admin' : 'User' }}</p>
                        </div>
                    </div>
                @empty
                    <p class="px-5 py-8 text-sm text-slate-500">No users yet.</p>
                @endforelse
            </div>
        </section>

        <section class="rounded-lg border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-5 py-4">
                <h2 class="font-semibold text-slate-950">Latest documents</h2>
            </div>
            <div class="divide-y divide-slate-100">
                @forelse ($latestDocuments as $document)
                    <div class="flex items-center justify-between gap-4 px-5 py-4">
                        <div class="min-w-0">
                            <p class="truncate font-medium text-slate-950">{{ $document->displayTitle() }}</p>
                            <p class="truncate text-sm text-slate-500">{{ $document->user?->email ?? 'Deleted user' }}</p>
                        </div>
                        <div class="shrink-0 text-right">
                            <p class="text-sm font-medium text-slate-700">{{ $document->statusLabel() }}</p>
                            <p class="text-xs text-slate-500">{{ $document->formattedFileSize() }}</p>
                        </div>
                    </div>
                @empty
                    <p class="px-5 py-8 text-sm text-slate-500">No documents yet.</p>
                @endforelse
            </div>
        </section>

        <section class="rounded-lg border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-5 py-4">
                <h2 class="font-semibold text-slate-950">Latest failed documents</h2>
            </div>
            <div class="divide-y divide-slate-100">
                @forelse ($latestFailedDocuments as $document)
                    <div class="flex items-center justify-between gap-4 px-5 py-4">
                        <div class="min-w-0">
                            <p class="truncate font-medium text-slate-950">{{ $document->displayTitle() }}</p>
                            <p class="truncate text-sm text-slate-500">{{ $document->user?->email ?? 'Deleted user' }}</p>
                        </div>
                        <p class="shrink-0 text-sm text-slate-500">{{ ($document->processed_at ?? $document->created_at)?->diffForHumans() }}</p>
                    </div>
                @empty
                    <p class="px-5 py-8 text-sm text-slate-500">No failed documents.</p>
                @endforelse
            </div>
        </section>

        <section class="rounded-lg border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-5 py-4">
                <h2 class="font-semibold text-slate-950">Latest conversations</h2>
            </div>
            <div class="divide-y divide-slate-100">
                @forelse ($latestConversations as $conversation)
                    <div class="flex items-center justify-between gap-4 px-5 py-4">
                        <div class="min-w-0">
                            <p class="truncate font-medium text-slate-950">{{ $conversation->title }}</p>
                            <p class="truncate text-sm text-slate-500">{{ $conversation->user?->email ?? 'Deleted user' }}</p>
                        </div>
                        <div class="shrink-0 text-right">
                            <p class="text-sm font-medium text-slate-700">{{ number_format($conversation->messages_count) }} messages</p>
                            <p class="text-xs text-slate-500">{{ str($conversation->scope)->title() }}</p>
                        </div>
                    </div>
                @empty
                    <p class="px-5 py-8 text-sm text-slate-500">No conversations yet.</p>
                @endforelse
            </div>
        </section>

        <section class="rounded-lg border border-slate-200 bg-white shadow-sm xl:col-span-2">
            <div class="border-b border-slate-200 px-5 py-4">
                <h2 class="font-semibold text-slate-950">Latest failed jobs</h2>
                <p class="mt-1 text-sm text-slate-500">Payloads and exception details are intentionally hidden.</p>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-5 py-3">Job</th>
                        <th class="px-5 py-3">Connection</th>
                        <th class="px-5 py-3">Queue</th>
                        <th class="px-5 py-3">Failed</th>
                    </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                    @forelse ($latestFailedJobs as $job)
                        <tr>
                            <td class="px-5 py-4 font-medium text-slate-950">{{ str($job->uuid)->limit(12, '') }}</td>
                            <td class="px-5 py-4 text-slate-600">{{ $job->connection }}</td>
                            <td class="px-5 py-4 text-slate-600">{{ $job->queue }}</td>
                            <td class="px-5 py-4 text-slate-600">{{ $job->failed_at?->diffForHumans() ?? '-' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-5 py-8 text-sm text-slate-500">No failed jobs.</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
@endsection
