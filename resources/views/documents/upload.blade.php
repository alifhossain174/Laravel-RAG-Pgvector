@php
    $title = 'Upload Document';
@endphp

@extends('layouts.app')

@section('content')
    <div>
        <h1 class="text-2xl font-semibold tracking-tight text-slate-950">Upload document</h1>
        <p class="mt-2 text-sm text-slate-600">Upload a PDF to start automatic text extraction, chunking, and embedding generation.</p>
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-[1.4fr_0.9fr]">
        <form method="POST" action="{{ route('documents.store') }}" enctype="multipart/form-data" class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            @csrf

            <label for="document" class="block cursor-pointer rounded-lg border-2 border-dashed border-indigo-200 bg-indigo-50/40 p-8 text-center transition hover:border-indigo-300 hover:bg-indigo-50">
                <div class="mx-auto grid size-12 place-items-center rounded-lg bg-white text-sm font-bold text-indigo-700 shadow-sm">PDF</div>
                <p class="mt-4 font-semibold text-slate-950">Choose a PDF to upload</p>
                <p class="mt-2 text-sm text-slate-500">PDF only. Maximum file size is 20MB.</p>
                <span class="mt-5 inline-flex rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-800 hover:bg-slate-50">
                    Browse files
                </span>
                <input id="document" name="document" type="file" accept="application/pdf,.pdf" required class="sr-only">
            </label>
            @error('document')
                <p class="mt-2 text-sm text-rose-600">{{ $message }}</p>
            @enderror

            <div class="mt-6 grid gap-5">
                <label class="block">
                    <span class="text-sm font-medium text-slate-700">Document title</span>
                    <input name="title" type="text" value="{{ old('title') }}" class="mt-2 w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm outline-none focus:border-indigo-500 focus:ring-4 focus:ring-indigo-100" placeholder="Optional title">
                    @error('title')
                        <p class="mt-2 text-sm text-rose-600">{{ $message }}</p>
                    @enderror
                </label>

                <label class="block">
                    <span class="text-sm font-medium text-slate-700">Description</span>
                    <textarea name="description" rows="4" class="mt-2 w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm outline-none focus:border-indigo-500 focus:ring-4 focus:ring-indigo-100" placeholder="Optional document description">{{ old('description') }}</textarea>
                    @error('description')
                        <p class="mt-2 text-sm text-rose-600">{{ $message }}</p>
                    @enderror
                </label>
            </div>

            <div class="mt-6 rounded-lg border border-slate-200 bg-slate-50 p-4">
                <div class="flex items-center justify-between text-sm">
                    <span class="font-medium text-slate-700">Processing starts after upload</span>
                    @include('partials.status-badge', ['status' => 'uploaded'])
                </div>
                <p class="mt-3 text-sm leading-6 text-slate-500">The PDF is stored privately, then a queued job extracts pages, creates chunks, and generates embeddings. The document status updates as processing completes.</p>
            </div>

            <button type="submit" class="mt-6 w-full rounded-lg bg-indigo-600 px-4 py-3 text-sm font-semibold text-white shadow-sm shadow-indigo-200 hover:bg-indigo-700">
                Upload PDF
            </button>
        </form>

        <aside class="rounded-lg border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-5 py-4">
                <h2 class="font-semibold text-slate-950">Recent uploads</h2>
            </div>
            <div class="divide-y divide-slate-100">
                @forelse ($recentDocuments as $document)
                    <div class="p-5">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="text-sm font-semibold text-slate-950">{{ $document->displayTitle() }}</p>
                                <p class="mt-1 text-sm text-slate-500">{{ $document->formattedFileSize() }} - {{ $document->created_at->diffForHumans() }}</p>
                            </div>
                            @include('partials.status-badge', ['status' => $document->status])
                        </div>
                    </div>
                @empty
                    <div class="p-8 text-center">
                        <p class="font-semibold text-slate-950">No uploads yet</p>
                        <p class="mt-2 text-sm text-slate-500">Recent files will appear here after your first PDF upload.</p>
                    </div>
                @endforelse
            </div>
        </aside>
    </div>
@endsection
