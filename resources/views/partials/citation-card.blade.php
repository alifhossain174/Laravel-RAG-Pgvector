@php
    $documentTitle = $documentTitle ?? 'Document source';
    $pageLabel = $pageLabel ?? 'Page unknown';
    $chunkPreview = $chunkPreview ?? 'Source excerpt unavailable.';
    $relevanceScore = $relevanceScore ?? 'N/A';
    $showScore = $showScore ?? true;
@endphp

<article class="min-w-0 rounded-lg border border-indigo-100 bg-indigo-50/60 p-4">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div class="min-w-0">
            <p class="truncate text-sm font-semibold text-slate-950">{{ $documentTitle }}</p>
            <p class="mt-1 text-xs font-medium text-indigo-700">{{ $pageLabel }}</p>
        </div>
        @if ($showScore)
            <span class="rounded-full bg-white px-2.5 py-1 text-xs font-semibold text-slate-600 ring-1 ring-indigo-100">
                Score: {{ $relevanceScore }}
            </span>
        @endif
    </div>
    <p class="mt-3 text-sm leading-6 text-slate-700">{{ $chunkPreview }}</p>
</article>
