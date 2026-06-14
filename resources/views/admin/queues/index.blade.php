@php
    $title = 'Admin Queues';
@endphp

@extends('layouts.admin')

@section('content')
    <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight text-slate-950">Queues</h1>
            <p class="mt-2 text-sm text-slate-600">Monitor database queue backlog, reserved jobs, and recent failures.</p>
        </div>
        <a href="{{ route('admin.failed-jobs.index') }}" class="rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-center text-sm font-semibold text-slate-700 hover:bg-slate-50">
            Failed jobs
        </a>
    </div>

    <section class="mt-6 grid gap-4 sm:grid-cols-3">
        <article class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-sm font-medium text-slate-500">Failed Jobs</p>
            <p class="mt-3 text-3xl font-semibold tracking-tight text-slate-950">{{ number_format($failedJobsCount) }}</p>
            <p class="mt-2 text-xs text-slate-500">Records in failed_jobs</p>
        </article>
        <article class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-sm font-medium text-slate-500">Latest Failure</p>
            <p class="mt-3 text-2xl font-semibold tracking-tight text-slate-950">{{ $latestFailedJobTime?->diffForHumans() ?? '-' }}</p>
            <p class="mt-2 text-xs text-slate-500">{{ $latestFailedJobTime?->format('M j, Y g:i A') ?? 'No failures recorded' }}</p>
        </article>
        <article class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-sm font-medium text-slate-500">Queue Driver Note</p>
            <p class="mt-3 text-sm font-semibold text-slate-950">Database queue view</p>
            <p class="mt-2 text-xs leading-5 text-slate-500">Counts are based on the jobs and failed_jobs tables. Horizon can be added later for Redis-backed queue telemetry.</p>
        </article>
    </section>

    @if (! $hasJobsTable)
        <div class="mt-6 rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
            The jobs table is not available, so pending and reserved queue counts cannot be shown.
        </div>
    @endif

    @if (! $hasFailedJobsTable)
        <div class="mt-6 rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
            The failed_jobs table is not available, so failed job counts cannot be shown.
        </div>
    @endif

    <section class="mt-6 rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 px-5 py-4">
            <h2 class="font-semibold text-slate-950">Queue backlog by queue</h2>
            <p class="mt-1 text-sm text-slate-500">Pending jobs are unreserved. Reserved jobs have a reserved_at timestamp.</p>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-5 py-3">Queue</th>
                    <th class="px-5 py-3">Pending</th>
                    <th class="px-5 py-3">Reserved</th>
                    <th class="px-5 py-3">Total</th>
                    <th class="px-5 py-3">Next Available</th>
                    <th class="px-5 py-3">Latest Created</th>
                </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                @forelse ($queueStats as $queue)
                    <tr>
                        <td class="px-5 py-4 font-medium text-slate-950">{{ $queue->queue }}</td>
                        <td class="px-5 py-4 text-slate-600">{{ number_format($queue->pending_jobs) }}</td>
                        <td class="px-5 py-4 text-slate-600">{{ number_format($queue->reserved_jobs) }}</td>
                        <td class="px-5 py-4 text-slate-600">{{ number_format($queue->total_jobs) }}</td>
                        <td class="px-5 py-4 text-slate-600">{{ $queue->next_available_at?->format('M j, Y g:i A') ?? '-' }}</td>
                        <td class="px-5 py-4 text-slate-600">{{ $queue->latest_created_at?->format('M j, Y g:i A') ?? '-' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-5 py-12 text-center">
                            <p class="font-semibold text-slate-950">No queued jobs</p>
                            <p class="mt-2 text-sm text-slate-500">The database queue table has no pending or reserved jobs.</p>
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection
