@php
    $title = 'Admin Failed Jobs';
@endphp

@extends('layouts.admin')

@section('content')
    <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight text-slate-950">Failed Jobs</h1>
            <p class="mt-2 text-sm text-slate-600">Review failed queue records without exposing payloads or full stack traces.</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('admin.queues.index') }}" class="rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-center text-sm font-semibold text-slate-700 hover:bg-slate-50">
                Queue overview
            </a>
            <form method="POST" action="{{ route('admin.failed-jobs.retry-all') }}">
                @csrf
                <button type="submit" class="rounded-lg bg-teal-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm shadow-teal-200 hover:bg-teal-700">
                    Retry all
                </button>
            </form>
        </div>
    </div>

    <section class="mt-6 rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="flex flex-col justify-between gap-2 border-b border-slate-200 px-5 py-4 sm:flex-row sm:items-center">
            <div>
                <h2 class="font-semibold text-slate-950">Failed job records</h2>
                <p class="mt-1 text-sm text-slate-500">{{ number_format($failedJobsCount) }} total failed jobs. Payloads and full traces are hidden.</p>
            </div>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-5 py-3">ID</th>
                    <th class="px-5 py-3">UUID</th>
                    <th class="px-5 py-3">Connection</th>
                    <th class="px-5 py-3">Queue</th>
                    <th class="px-5 py-3">Failed At</th>
                    <th class="px-5 py-3">Exception Preview</th>
                    <th class="px-5 py-3 text-right">Actions</th>
                </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                @forelse ($failedJobs as $job)
                    <tr>
                        <td class="px-5 py-4 font-medium text-slate-950">{{ $job->id }}</td>
                        <td class="px-5 py-4 font-mono text-xs text-slate-600">{{ $job->uuid }}</td>
                        <td class="px-5 py-4 text-slate-600">{{ $job->connection }}</td>
                        <td class="px-5 py-4 text-slate-600">{{ $job->queue }}</td>
                        <td class="px-5 py-4 text-slate-600">{{ $job->failed_at?->format('M j, Y g:i A') ?? '-' }}</td>
                        <td class="max-w-lg px-5 py-4 text-slate-600">{{ $job->exception_preview }}</td>
                        <td class="px-5 py-4">
                            <div class="flex flex-wrap justify-end gap-2">
                                <form method="POST" action="{{ route('admin.failed-jobs.retry', $job->id) }}">
                                    @csrf
                                    <button type="submit" class="rounded-lg border border-teal-200 px-3 py-2 text-xs font-semibold text-teal-700 hover:bg-teal-50">
                                        Retry
                                    </button>
                                </form>
                                <form method="POST" action="{{ route('admin.failed-jobs.destroy', $job->id) }}">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="rounded-lg border border-rose-200 px-3 py-2 text-xs font-semibold text-rose-700 hover:bg-rose-50">
                                        Delete
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-5 py-12 text-center">
                            <p class="font-semibold text-slate-950">No failed jobs</p>
                            <p class="mt-2 text-sm text-slate-500">Failed queue jobs will appear here when Laravel records them.</p>
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>

        @if ($failedJobs->hasPages())
            <div class="border-t border-slate-200 px-5 py-4">
                {{ $failedJobs->links() }}
            </div>
        @endif
    </section>
@endsection
