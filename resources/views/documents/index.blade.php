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
        <a href="{{ route('documents.create') }}" class="rounded-lg bg-teal-600 px-4 py-2.5 text-center text-sm font-semibold text-white shadow-sm shadow-teal-200 hover:bg-teal-700">
            Upload document
        </a>
    </div>

    <form id="documentSearchForm" method="GET" action="{{ route('documents.index') }}" data-live-document-search data-results-target="#documentSearchResults" data-status-target="#documentSearchStatus" class="mt-6 rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
        <div class="grid gap-3 md:grid-cols-[1fr_220px]">
            <input name="search" type="search" value="{{ $search }}" placeholder="Search by title or filename" autocomplete="off" class="rounded-lg border border-slate-300 px-3 py-2.5 text-sm outline-none focus:border-teal-500 focus:ring-4 focus:ring-teal-100">
            <select name="status" class="rounded-lg border border-slate-300 px-3 py-2.5 text-sm outline-none focus:border-teal-500 focus:ring-4 focus:ring-teal-100">
                <option value="">All statuses</option>
                @foreach ($statuses as $status)
                    <option value="{{ $status }}" @selected($selectedStatus === $status)>{{ str($status)->replace('_', ' ')->title() }}</option>
                @endforeach
            </select>
        </div>
        <p id="documentSearchStatus" class="sr-only" aria-live="polite"></p>
    </form>

    <div id="documentSearchResults">
        @include('documents.partials.results', ['documents' => $documents])
    </div>

    <script>
        (() => {
            const form = document.querySelector('[data-live-document-search]');

            if (!form) {
                return;
            }

            const results = document.querySelector(form.dataset.resultsTarget);
            const status = document.querySelector(form.dataset.statusTarget);
            const searchInput = form.querySelector('input[name="search"]');
            const statusSelect = form.querySelector('select[name="status"]');
            let timeoutId;
            let currentRequest;

            const buildUrl = () => {
                const url = new URL(form.action, window.location.origin);
                const formData = new FormData(form);

                formData.forEach((value, key) => {
                    const normalized = String(value).trim();

                    if (normalized !== '') {
                        url.searchParams.set(key, normalized);
                    }
                });

                return url;
            };

            const setStatus = (message) => {
                if (status) {
                    status.textContent = message;
                }
            };

            const loadResults = async (url) => {
                if (!results) {
                    return;
                }

                currentRequest?.abort();
                currentRequest = new AbortController();
                setStatus('Searching');

                try {
                    const response = await fetch(url, {
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        signal: currentRequest.signal,
                    });

                    if (!response.ok) {
                        throw new Error('Search failed.');
                    }

                    const payload = await response.json();
                    results.innerHTML = payload.html || '';
                    window.history.replaceState({}, '', url);
                    setStatus('Search results updated');
                } catch (error) {
                    if (error.name !== 'AbortError') {
                        setStatus('Search failed');
                    }
                }
            };

            const scheduleSearch = () => {
                clearTimeout(timeoutId);
                timeoutId = setTimeout(() => loadResults(buildUrl()), 250);
            };

            form.addEventListener('submit', (event) => {
                event.preventDefault();
                loadResults(buildUrl());
            });

            searchInput?.addEventListener('input', scheduleSearch);
            statusSelect?.addEventListener('change', () => loadResults(buildUrl()));

            results?.addEventListener('click', (event) => {
                const link = event.target.closest('[data-live-pagination] a');

                if (!link) {
                    return;
                }

                event.preventDefault();
                loadResults(new URL(link.href));
            });

            window.addEventListener('popstate', () => loadResults(new URL(window.location.href)));
        })();
    </script>
@endsection
