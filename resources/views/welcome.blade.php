@php
    $isMarketing = true;
    $title = 'Chat with your PDFs';
    $features = [
        ['title' => 'PDF Upload', 'body' => 'Bring contracts, manuals, reports, policies, and research papers into one searchable workspace.'],
        ['title' => 'AI Question Answering', 'body' => 'Ask plain-language questions and receive concise answers drafted from the selected document.'],
        ['title' => 'Source-based Answers', 'body' => 'Every response can point back to the page and excerpt that informed the answer.'],
        ['title' => 'Secure Document Storage', 'body' => 'A future-ready interface for controlled document access and private knowledge workflows.'],
    ];
    $steps = ['Upload PDF', 'System processes document', 'Ask questions', 'Get answers with sources'];
@endphp

@extends('layouts.app')

@section('content')
    <section class="bg-white">
        <div class="mx-auto grid max-w-7xl items-center gap-12 px-4 py-16 sm:px-6 lg:grid-cols-[1.05fr_0.95fr] lg:px-8 lg:py-20">
            <div>
                <p class="text-sm font-semibold uppercase tracking-[0.2em] text-indigo-700">Document intelligence</p>
                <h1 class="mt-5 max-w-3xl text-4xl font-semibold tracking-tight text-slate-950 sm:text-5xl lg:text-6xl">
                    Chat with your PDFs
                </h1>
                <p class="mt-6 max-w-2xl text-lg leading-8 text-slate-600">
                    Upload documents, let the system prepare searchable chunks, then ask questions and review source-backed answers in a focused SaaS workspace.
                </p>
                <div class="mt-8 flex flex-col gap-3 sm:flex-row">
                    <a href="{{ route('documents.create') }}" class="rounded-lg bg-indigo-600 px-5 py-3 text-center text-sm font-semibold text-white shadow-sm shadow-indigo-200 hover:bg-indigo-700">
                        Upload Document
                    </a>
                    <a href="{{ route('dashboard') }}" class="rounded-lg border border-slate-300 bg-white px-5 py-3 text-center text-sm font-semibold text-slate-800 hover:bg-slate-50">
                        View Dashboard
                    </a>
                </div>
            </div>

            <div class="rounded-lg border border-slate-200 bg-slate-50 p-4 shadow-sm">
                <div class="rounded-lg bg-white p-5 shadow-sm">
                    <div class="flex items-center justify-between border-b border-slate-200 pb-4">
                        <div>
                            <p class="text-sm font-semibold text-slate-950">Procurement Policy.pdf</p>
                            <p class="mt-1 text-xs text-slate-500">Ready for chat</p>
                        </div>
                        @include('partials.status-badge', ['status' => 'ready'])
                    </div>
                    <div class="mt-5 space-y-4">
                        <div class="ml-auto max-w-[80%] rounded-lg bg-indigo-600 px-4 py-3 text-sm text-white">
                            What approvals are required above $50,000?
                        </div>
                        <div class="max-w-[88%] rounded-lg border border-slate-200 bg-white px-4 py-3 text-sm leading-6 text-slate-700">
                            Purchases above $50,000 require department head approval and finance review before vendor onboarding.
                            <div class="mt-3 rounded-md bg-indigo-50 p-3 text-xs text-indigo-800">
                                Source: Procurement Policy.pdf, page 8
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="border-y border-slate-200 bg-slate-50 py-14">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ($features as $feature)
                    <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                        <h2 class="text-base font-semibold text-slate-950">{{ $feature['title'] }}</h2>
                        <p class="mt-3 text-sm leading-6 text-slate-600">{{ $feature['body'] }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    <section class="bg-white py-14">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
                <div>
                    <p class="text-sm font-semibold uppercase tracking-[0.2em] text-indigo-700">Workflow</p>
                    <h2 class="mt-3 text-2xl font-semibold tracking-tight text-slate-950">From uploaded file to cited answer</h2>
                </div>
                <p class="max-w-xl text-sm leading-6 text-slate-600">This frontend is ready for the processing pipeline that extracts text, creates chunks, stores embeddings, and retrieves sources.</p>
            </div>

            <div class="mt-8 grid gap-4 md:grid-cols-4">
                @foreach ($steps as $index => $step)
                    <div class="rounded-lg border border-slate-200 bg-slate-50 p-5">
                        <span class="grid size-9 place-items-center rounded-lg bg-indigo-600 text-sm font-semibold text-white">{{ $index + 1 }}</span>
                        <p class="mt-5 font-semibold text-slate-950">{{ $step }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    <section class="bg-slate-950 py-14 text-white">
        <div class="mx-auto grid max-w-7xl gap-6 px-4 sm:px-6 lg:grid-cols-3 lg:px-8">
            <div class="lg:col-span-2">
                <p class="text-sm font-semibold uppercase tracking-[0.2em] text-indigo-300">Usage placeholder</p>
                <h2 class="mt-3 text-2xl font-semibold tracking-tight">Simple plans for document-heavy teams</h2>
                <p class="mt-4 max-w-2xl text-sm leading-6 text-slate-300">Pricing can later connect to document limits, question volume, embedding storage, or organization seats.</p>
            </div>
            <div class="rounded-lg border border-white/10 bg-white/5 p-5">
                <p class="text-sm text-slate-300">Starter workspace</p>
                <p class="mt-3 text-3xl font-semibold">$0</p>
                <p class="mt-3 text-sm text-slate-300">Static placeholder for future billing and usage controls.</p>
            </div>
        </div>
    </section>

    <footer class="border-t border-slate-200 bg-white py-8">
        <div class="mx-auto flex max-w-7xl flex-col gap-3 px-4 text-sm text-slate-500 sm:flex-row sm:items-center sm:justify-between sm:px-6 lg:px-8">
            <p>RAG Docs frontend prototype</p>
            <p>Built with Laravel Blade and Tailwind CSS</p>
        </div>
    </footer>
@endsection
