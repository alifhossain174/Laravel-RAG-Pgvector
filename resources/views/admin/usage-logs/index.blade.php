@php
    $title = 'Admin Usage Logs';
@endphp

@extends('layouts.admin')

@section('content')
    <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight text-slate-950">Usage Logs</h1>
            <p class="mt-2 text-sm text-slate-600">Review AI lifecycle events across uploads, processing, embeddings, and chat.</p>
        </div>
    </div>

    <section class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
        <article class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-sm font-medium text-slate-500">Total Logs</p>
            <p class="mt-3 text-3xl font-semibold tracking-tight text-slate-950">{{ number_format($summary['total']) }}</p>
        </article>
        <article class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-sm font-medium text-slate-500">Failed Logs</p>
            <p class="mt-3 text-3xl font-semibold tracking-tight text-slate-950">{{ number_format($summary['failed']) }}</p>
        </article>
        <article class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-sm font-medium text-slate-500">Input Tokens</p>
            <p class="mt-3 text-3xl font-semibold tracking-tight text-slate-950">{{ number_format($summary['input_tokens']) }}</p>
        </article>
        <article class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-sm font-medium text-slate-500">Output Tokens</p>
            <p class="mt-3 text-3xl font-semibold tracking-tight text-slate-950">{{ number_format($summary['output_tokens']) }}</p>
        </article>
        <article class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-sm font-medium text-slate-500">Embeddings</p>
            <p class="mt-3 text-3xl font-semibold tracking-tight text-slate-950">{{ number_format($summary['embeddings']) }}</p>
        </article>
    </section>

    <form method="GET" action="{{ route('admin.usage-logs.index') }}" class="mt-6 rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
        <div class="grid gap-3 lg:grid-cols-4">
            <select name="user" class="rounded-lg border border-slate-300 px-3 py-2.5 text-sm outline-none focus:border-teal-500 focus:ring-4 focus:ring-teal-100">
                <option value="">All users</option>
                @foreach ($users as $user)
                    <option value="{{ $user->id }}" @selected($filters['user'] === $user->id)>{{ $user->name }} - {{ $user->email }}</option>
                @endforeach
            </select>

            <select name="action_type" class="rounded-lg border border-slate-300 px-3 py-2.5 text-sm outline-none focus:border-teal-500 focus:ring-4 focus:ring-teal-100">
                <option value="">All actions</option>
                @foreach ($actionTypes as $actionType)
                    <option value="{{ $actionType }}" @selected($filters['action_type'] === $actionType)>{{ str($actionType)->replace('_', ' ')->title() }}</option>
                @endforeach
            </select>

            <select name="status" class="rounded-lg border border-slate-300 px-3 py-2.5 text-sm outline-none focus:border-teal-500 focus:ring-4 focus:ring-teal-100">
                <option value="">All statuses</option>
                <option value="success" @selected($filters['status'] === 'success')>Success</option>
                <option value="failed" @selected($filters['status'] === 'failed')>Failed</option>
            </select>

            <select name="provider" class="rounded-lg border border-slate-300 px-3 py-2.5 text-sm outline-none focus:border-teal-500 focus:ring-4 focus:ring-teal-100">
                <option value="">All providers</option>
                @foreach ($providers as $provider)
                    <option value="{{ $provider }}" @selected($filters['provider'] === $provider)>{{ $provider }}</option>
                @endforeach
            </select>

            <select name="model" class="rounded-lg border border-slate-300 px-3 py-2.5 text-sm outline-none focus:border-teal-500 focus:ring-4 focus:ring-teal-100">
                <option value="">All models</option>
                @foreach ($models as $model)
                    <option value="{{ $model }}" @selected($filters['model'] === $model)>{{ $model }}</option>
                @endforeach
            </select>

            <input name="date_from" type="date" value="{{ $filters['date_from'] }}" class="rounded-lg border border-slate-300 px-3 py-2.5 text-sm outline-none focus:border-teal-500 focus:ring-4 focus:ring-teal-100">
            <input name="date_to" type="date" value="{{ $filters['date_to'] }}" class="rounded-lg border border-slate-300 px-3 py-2.5 text-sm outline-none focus:border-teal-500 focus:ring-4 focus:ring-teal-100">

            <div class="flex gap-2">
                <button type="submit" class="flex-1 rounded-lg bg-teal-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm shadow-teal-200 hover:bg-teal-700">
                    Apply
                </button>
                <a href="{{ route('admin.usage-logs.index') }}" class="rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                    Reset
                </a>
            </div>
        </div>
        @if ($errors->any())
            <p class="mt-3 text-sm font-medium text-rose-700">{{ $errors->first() }}</p>
        @endif
    </form>

    <section class="mt-6 rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-5 py-3">When</th>
                    <th class="px-5 py-3">Action</th>
                    <th class="px-5 py-3">User</th>
                    <th class="px-5 py-3">Provider / Model</th>
                    <th class="px-5 py-3">Tokens</th>
                    <th class="px-5 py-3">Embeddings</th>
                    <th class="px-5 py-3">Status</th>
                    <th class="px-5 py-3 text-right">Details</th>
                </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                @forelse ($usageLogs as $log)
                    <tr>
                        <td class="px-5 py-4 text-slate-600">{{ $log->created_at->format('M j, Y g:i A') }}</td>
                        <td class="px-5 py-4 font-medium text-slate-950">{{ str($log->action_type)->replace('_', ' ')->title() }}</td>
                        <td class="px-5 py-4">
                            @if ($log->user)
                                <a href="{{ route('admin.users.show', $log->user) }}" class="font-medium text-slate-700 hover:text-teal-700">{{ $log->user->email }}</a>
                                <p class="mt-1 text-xs text-slate-500">{{ $log->user->name }}</p>
                            @else
                                <span class="text-slate-500">System or deleted user</span>
                            @endif
                        </td>
                        <td class="px-5 py-4 text-slate-600">
                            <p>{{ $log->provider ?: '-' }}</p>
                            <p class="mt-1 text-xs text-slate-500">{{ $log->model ?: '-' }}</p>
                        </td>
                        <td class="px-5 py-4 text-slate-600">
                            <p>In: {{ $log->input_tokens === null ? '-' : number_format($log->input_tokens) }}</p>
                            <p class="mt-1 text-xs text-slate-500">Out: {{ $log->output_tokens === null ? '-' : number_format($log->output_tokens) }}</p>
                        </td>
                        <td class="px-5 py-4 text-slate-600">{{ $log->embedding_count === null ? '-' : number_format($log->embedding_count) }}</td>
                        <td class="px-5 py-4">
                            <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $log->status === 'failed' ? 'bg-rose-50 text-rose-700 ring-1 ring-rose-200' : 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200' }}">
                                {{ str($log->status)->title() }}
                            </span>
                        </td>
                        <td class="px-5 py-4 text-right">
                            <a href="{{ route('admin.usage-logs.show', $log) }}" class="text-sm font-semibold text-teal-700 hover:text-teal-800">View</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="px-5 py-12 text-center">
                            <p class="font-semibold text-slate-950">No usage logs found</p>
                            <p class="mt-2 text-sm text-slate-500">Usage logs will appear as users upload documents, process files, generate embeddings, and chat.</p>
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>

        @if ($usageLogs->hasPages())
            <div class="border-t border-slate-200 px-5 py-4">
                {{ $usageLogs->links() }}
            </div>
        @endif
    </section>
@endsection
