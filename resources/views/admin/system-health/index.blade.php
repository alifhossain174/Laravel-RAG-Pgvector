@php
    $title = 'Admin System Health';

    $badgeClasses = [
        'healthy' => 'bg-emerald-50 text-emerald-700 ring-emerald-200',
        'warning' => 'bg-amber-50 text-amber-700 ring-amber-200',
        'failed' => 'bg-rose-50 text-rose-700 ring-rose-200',
    ];
@endphp

@extends('layouts.admin')

@section('content')
    <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight text-slate-950">System Health</h1>
            <p class="mt-2 text-sm text-slate-600">Read-only checks for database, queue, storage, AI configuration, PDF tooling, and OCR readiness.</p>
        </div>
        <a href="{{ route('admin.queues.index') }}" class="rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-center text-sm font-semibold text-slate-700 hover:bg-slate-50">
            Queue dashboard
        </a>
    </div>

    <section class="mt-6 grid gap-4 sm:grid-cols-3">
        <article class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-sm font-medium text-slate-500">Healthy</p>
            <p class="mt-3 text-3xl font-semibold tracking-tight text-emerald-700">{{ number_format($summary['healthy']) }}</p>
        </article>
        <article class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-sm font-medium text-slate-500">Warnings</p>
            <p class="mt-3 text-3xl font-semibold tracking-tight text-amber-700">{{ number_format($summary['warning']) }}</p>
        </article>
        <article class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-sm font-medium text-slate-500">Failed</p>
            <p class="mt-3 text-3xl font-semibold tracking-tight text-rose-700">{{ number_format($summary['failed']) }}</p>
        </article>
    </section>

    <section class="mt-6 grid gap-6 xl:grid-cols-2">
        @foreach ($groups as $group)
            <article class="rounded-lg border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-200 px-5 py-4">
                    <h2 class="font-semibold text-slate-950">{{ $group['title'] }}</h2>
                </div>
                <div class="divide-y divide-slate-100">
                    @foreach ($group['checks'] as $check)
                        <div class="flex flex-col gap-3 px-5 py-4 sm:flex-row sm:items-start sm:justify-between">
                            <div class="min-w-0">
                                <p class="font-medium text-slate-950">{{ $check['label'] }}</p>
                                <p class="mt-1 text-sm leading-6 text-slate-600">{{ $check['message'] }}</p>
                                @if ($check['detail'])
                                    <p class="mt-1 break-words text-xs font-medium text-slate-500">{{ $check['detail'] }}</p>
                                @endif
                            </div>
                            <span class="inline-flex w-fit shrink-0 rounded-full px-2.5 py-1 text-xs font-semibold capitalize ring-1 {{ $badgeClasses[$check['status']] ?? 'bg-slate-100 text-slate-700 ring-slate-200' }}">
                                {{ $check['status'] }}
                            </span>
                        </div>
                    @endforeach
                </div>
            </article>
        @endforeach
    </section>

    <section class="mt-6 grid gap-6 lg:grid-cols-2">
        @foreach ([
            'Latest ready document' => $latestReadyDocument,
            'Latest failed document' => $latestFailedDocument,
        ] as $heading => $document)
            <article class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="font-semibold text-slate-950">{{ $heading }}</h2>

                @if ($document)
                    <dl class="mt-5 grid gap-4 text-sm sm:grid-cols-2">
                        <div class="rounded-lg bg-slate-50 p-4">
                            <dt class="text-slate-500">Document</dt>
                            <dd class="mt-1 break-words font-semibold text-slate-950">
                                <a href="{{ route('admin.documents.show', $document) }}" class="hover:text-teal-700">{{ $document->displayTitle() }}</a>
                            </dd>
                        </div>
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
                            <dt class="text-slate-500">Status</dt>
                            <dd class="mt-1 font-semibold text-slate-950">{{ $document->statusLabel() }}</dd>
                        </div>
                        <div class="rounded-lg bg-slate-50 p-4">
                            <dt class="text-slate-500">File size</dt>
                            <dd class="mt-1 font-semibold text-slate-950">{{ $document->formattedFileSize() }}</dd>
                        </div>
                        <div class="rounded-lg bg-slate-50 p-4">
                            <dt class="text-slate-500">Uploaded</dt>
                            <dd class="mt-1 font-semibold text-slate-950">{{ $document->created_at?->format('M j, Y g:i A') ?? '-' }}</dd>
                        </div>
                        <div class="rounded-lg bg-slate-50 p-4">
                            <dt class="text-slate-500">Processed</dt>
                            <dd class="mt-1 font-semibold text-slate-950">{{ $document->processed_at?->format('M j, Y g:i A') ?? '-' }}</dd>
                        </div>
                    </dl>
                @else
                    <div class="mt-5 rounded-lg bg-slate-50 p-8 text-center">
                        <p class="font-semibold text-slate-950">No document found</p>
                        <p class="mt-2 text-sm text-slate-500">There are no documents in this state yet.</p>
                    </div>
                @endif
            </article>
        @endforeach
    </section>
@endsection
