@php
    $title = 'Admin Usage Log Details';
@endphp

@extends('layouts.admin')

@section('content')
    <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
        <div>
            <a href="{{ route('admin.usage-logs.index') }}" class="text-sm font-semibold text-teal-700 hover:text-teal-800">Back to usage logs</a>
            <h1 class="mt-2 text-2xl font-semibold tracking-tight text-slate-950">{{ str($usageLog->action_type)->replace('_', ' ')->title() }}</h1>
            <p class="mt-2 text-sm text-slate-600">{{ $usageLog->created_at->format('M j, Y g:i A') }}</p>
        </div>
        <span class="inline-flex w-fit rounded-full px-2.5 py-1 text-xs font-semibold {{ $usageLog->status === 'failed' ? 'bg-rose-50 text-rose-700 ring-1 ring-rose-200' : 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200' }}">
            {{ str($usageLog->status)->title() }}
        </span>
    </div>

    <section class="mt-6 grid gap-6 lg:grid-cols-[0.8fr_1.2fr]">
        <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="font-semibold text-slate-950">Context</h2>
            <dl class="mt-5 grid gap-4 text-sm">
                <div class="rounded-lg bg-slate-50 p-4">
                    <dt class="text-slate-500">User</dt>
                    <dd class="mt-1 font-semibold text-slate-950">{{ $usageLog->user?->email ?? 'System or deleted user' }}</dd>
                </div>
                <div class="rounded-lg bg-slate-50 p-4">
                    <dt class="text-slate-500">Document</dt>
                    <dd class="mt-1 font-semibold text-slate-950">{{ $usageLog->document?->title ?: ($usageLog->document?->original_filename ?? '-') }}</dd>
                </div>
                <div class="rounded-lg bg-slate-50 p-4">
                    <dt class="text-slate-500">Conversation</dt>
                    <dd class="mt-1 font-semibold text-slate-950">{{ $usageLog->conversation?->title ?? '-' }}</dd>
                </div>
                <div class="rounded-lg bg-slate-50 p-4">
                    <dt class="text-slate-500">Message</dt>
                    <dd class="mt-1 font-semibold text-slate-950">{{ $usageLog->message?->role ? str($usageLog->message->role)->title() : '-' }}</dd>
                </div>
            </dl>
        </div>

        <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="font-semibold text-slate-950">Usage</h2>
            <dl class="mt-5 grid gap-4 text-sm sm:grid-cols-2">
                <div class="rounded-lg bg-slate-50 p-4">
                    <dt class="text-slate-500">Provider</dt>
                    <dd class="mt-1 font-semibold text-slate-950">{{ $usageLog->provider ?: '-' }}</dd>
                </div>
                <div class="rounded-lg bg-slate-50 p-4">
                    <dt class="text-slate-500">Model</dt>
                    <dd class="mt-1 break-words font-semibold text-slate-950">{{ $usageLog->model ?: '-' }}</dd>
                </div>
                <div class="rounded-lg bg-slate-50 p-4">
                    <dt class="text-slate-500">Input tokens</dt>
                    <dd class="mt-1 font-semibold text-slate-950">{{ $usageLog->input_tokens === null ? '-' : number_format($usageLog->input_tokens) }}</dd>
                </div>
                <div class="rounded-lg bg-slate-50 p-4">
                    <dt class="text-slate-500">Output tokens</dt>
                    <dd class="mt-1 font-semibold text-slate-950">{{ $usageLog->output_tokens === null ? '-' : number_format($usageLog->output_tokens) }}</dd>
                </div>
                <div class="rounded-lg bg-slate-50 p-4">
                    <dt class="text-slate-500">Embedding count</dt>
                    <dd class="mt-1 font-semibold text-slate-950">{{ $usageLog->embedding_count === null ? '-' : number_format($usageLog->embedding_count) }}</dd>
                </div>
            </dl>
        </div>
    </section>

    @if ($errorPreview)
        <section class="mt-6 rounded-lg border border-rose-200 bg-rose-50 p-5 shadow-sm">
            <h2 class="font-semibold text-rose-900">Error preview</h2>
            <p class="mt-2 text-sm leading-6 text-rose-800">{{ $errorPreview }}</p>
        </section>
    @endif

    <section class="mt-6 rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 px-5 py-4">
            <h2 class="font-semibold text-slate-950">Metadata</h2>
            <p class="mt-1 text-sm text-slate-500">Sensitive keys and path-like values are redacted.</p>
        </div>
        <pre class="overflow-x-auto whitespace-pre-wrap p-5 text-sm leading-6 text-slate-700">{{ $metadataJson ?: '{}' }}</pre>
    </section>
@endsection
