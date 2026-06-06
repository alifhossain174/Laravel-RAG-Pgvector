@php
    $title = 'Documents';
@endphp

@extends('layouts.app')

@section('content')
    <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight text-slate-950">Documents</h1>
            <p class="mt-2 text-sm text-slate-600">Browse uploaded files and open a document-specific chat.</p>
        </div>
        <a href="{{ route('documents.create') }}" class="rounded-lg bg-indigo-600 px-4 py-2.5 text-center text-sm font-semibold text-white shadow-sm shadow-indigo-200 hover:bg-indigo-700">
            Upload PDF
        </a>
    </div>

    <form method="GET" action="{{ route('documents.index') }}" class="mt-6 rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
        <div class="grid gap-3 md:grid-cols-[1fr_220px_140px]">
            <input name="search" type="search" value="{{ $search }}" placeholder="Search by title or filename" class="rounded-lg border border-slate-300 px-3 py-2.5 text-sm outline-none focus:border-indigo-500 focus:ring-4 focus:ring-indigo-100">
            <select name="status" class="rounded-lg border border-slate-300 px-3 py-2.5 text-sm outline-none focus:border-indigo-500 focus:ring-4 focus:ring-indigo-100">
                <option value="">All statuses</option>
                @foreach ($statuses as $status)
                    <option value="{{ $status }}" @selected($selectedStatus === $status)>{{ str($status)->replace('_', ' ')->title() }}</option>
                @endforeach
            </select>
            <button type="submit" class="rounded-lg border border-slate-300 px-3 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">Filter</button>
        </div>
    </form>

    <section class="mt-6 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        @forelse ($documents as $document)
            @include('partials.document-card', ['document' => $document])
        @empty
            <div class="rounded-lg border border-dashed border-slate-300 bg-white p-10 text-center md:col-span-2 xl:col-span-3">
                <p class="font-semibold text-slate-950">No documents found</p>
                <p class="mt-2 text-sm text-slate-500">Upload a PDF or adjust the current filter.</p>
            </div>
        @endforelse
    </section>

    @if ($documents->hasPages())
        <div class="mt-6">
            {{ $documents->links() }}
        </div>
    @endif
@endsection
