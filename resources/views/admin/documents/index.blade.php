@php
    $title = 'Admin Documents';
@endphp

@extends('layouts.admin')

@section('content')
    <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight text-slate-950">Documents</h1>
            <p class="mt-2 text-sm text-slate-600">Search, filter, inspect, and safely manage uploaded documents across all users.</p>
        </div>
    </div>

    <form method="GET" action="{{ route('admin.documents.index') }}" class="mt-6 rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
        <div class="grid gap-3 lg:grid-cols-4">
            <input name="search" type="search" value="{{ $filters['search'] }}" placeholder="Search title, filename, owner email" class="rounded-lg border border-slate-300 px-3 py-2.5 text-sm outline-none focus:border-teal-500 focus:ring-4 focus:ring-teal-100">

            <select name="status" class="rounded-lg border border-slate-300 px-3 py-2.5 text-sm outline-none focus:border-teal-500 focus:ring-4 focus:ring-teal-100">
                <option value="">All statuses</option>
                @foreach ($statuses as $status)
                    <option value="{{ $status }}" @selected($filters['status'] === $status)>{{ str($status)->replace('_', ' ')->title() }}</option>
                @endforeach
            </select>

            <select name="owner" class="rounded-lg border border-slate-300 px-3 py-2.5 text-sm outline-none focus:border-teal-500 focus:ring-4 focus:ring-teal-100">
                <option value="">All owners</option>
                @foreach ($owners as $owner)
                    <option value="{{ $owner->id }}" @selected($filters['owner'] === $owner->id)>{{ $owner->name }} - {{ $owner->email }}</option>
                @endforeach
            </select>

            <select name="mime_type" class="rounded-lg border border-slate-300 px-3 py-2.5 text-sm outline-none focus:border-teal-500 focus:ring-4 focus:ring-teal-100">
                <option value="">All MIME types</option>
                @foreach ($mimeTypes as $mimeType)
                    <option value="{{ $mimeType }}" @selected($filters['mime_type'] === $mimeType)>{{ $mimeType }}</option>
                @endforeach
            </select>

            <select name="extension" class="rounded-lg border border-slate-300 px-3 py-2.5 text-sm outline-none focus:border-teal-500 focus:ring-4 focus:ring-teal-100">
                <option value="">All extensions</option>
                @foreach ($extensions as $extension)
                    <option value="{{ $extension }}" @selected($filters['extension'] === $extension)>{{ strtoupper($extension) }}</option>
                @endforeach
            </select>

            <input name="date_from" type="date" value="{{ $filters['date_from'] }}" class="rounded-lg border border-slate-300 px-3 py-2.5 text-sm outline-none focus:border-teal-500 focus:ring-4 focus:ring-teal-100">
            <input name="date_to" type="date" value="{{ $filters['date_to'] }}" class="rounded-lg border border-slate-300 px-3 py-2.5 text-sm outline-none focus:border-teal-500 focus:ring-4 focus:ring-teal-100">

            <div class="flex gap-2">
                <button type="submit" class="flex-1 rounded-lg bg-teal-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm shadow-teal-200 hover:bg-teal-700">
                    Apply
                </button>
                <a href="{{ route('admin.documents.index') }}" class="rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">
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
                    <th class="px-5 py-3">Document</th>
                    <th class="px-5 py-3">Owner</th>
                    <th class="px-5 py-3">Status</th>
                    <th class="px-5 py-3">MIME type</th>
                    <th class="px-5 py-3">Extension</th>
                    <th class="px-5 py-3">Size</th>
                    <th class="px-5 py-3">Pages</th>
                    <th class="px-5 py-3">Chunks</th>
                    <th class="px-5 py-3">Uploaded</th>
                    <th class="px-5 py-3">Processed</th>
                </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                @forelse ($documents as $document)
                    @php
                        $extension = strtolower(pathinfo($document->original_filename, PATHINFO_EXTENSION)) ?: 'unknown';
                    @endphp
                    <tr>
                        <td class="px-5 py-4">
                            <a href="{{ route('admin.documents.show', $document) }}" class="font-medium text-slate-950 hover:text-teal-700">{{ $document->displayTitle() }}</a>
                            <p class="mt-1 max-w-xs truncate text-xs text-slate-500">{{ $document->original_filename }}</p>
                        </td>
                        <td class="px-5 py-4">
                            @if ($document->user)
                                <a href="{{ route('admin.users.show', $document->user) }}" class="font-medium text-slate-700 hover:text-teal-700">{{ $document->user->email }}</a>
                                <p class="mt-1 text-xs text-slate-500">{{ $document->user->name }}</p>
                            @else
                                <span class="text-slate-500">Deleted user</span>
                            @endif
                        </td>
                        <td class="px-5 py-4">@include('partials.status-badge', ['status' => $document->status])</td>
                        <td class="px-5 py-4 max-w-xs truncate text-slate-600">{{ $document->mime_type ?: '-' }}</td>
                        <td class="px-5 py-4 text-slate-600">{{ strtoupper($extension) }}</td>
                        <td class="px-5 py-4 text-slate-600">{{ $document->formattedFileSize() }}</td>
                        <td class="px-5 py-4 text-slate-600">{{ $document->total_pages ?? '-' }}</td>
                        <td class="px-5 py-4 text-slate-600">{{ number_format($document->total_chunks) }}</td>
                        <td class="px-5 py-4 text-slate-600">{{ $document->created_at->format('M j, Y') }}</td>
                        <td class="px-5 py-4 text-slate-600">{{ $document->processed_at?->format('M j, Y') ?? '-' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="10" class="px-5 py-12 text-center">
                            <p class="font-semibold text-slate-950">No documents found</p>
                            <p class="mt-2 text-sm text-slate-500">Adjust the search or filters and try again.</p>
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>

        @if ($documents->hasPages())
            <div class="border-t border-slate-200 px-5 py-4">
                {{ $documents->links() }}
            </div>
        @endif
    </section>
@endsection
