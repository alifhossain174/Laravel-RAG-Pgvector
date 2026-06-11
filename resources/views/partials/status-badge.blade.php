@php
    $status = strtolower($status ?? 'processing');
    $showSpinner = $showSpinner ?? false;
    $processingStatuses = ['uploaded', 'processing', 'text_extracted', 'chunked', 'embedded'];
    $labels = [
        'uploaded' => 'Uploaded',
        'ready' => 'Ready',
        'processing' => 'Processing',
        'text_extracted' => 'Text Extracted',
        'chunked' => 'Chunks Created',
        'embedded' => 'Embeddings Stored',
        'pending' => 'Pending',
        'failed' => 'Failed',
    ];
    $styles = [
        'uploaded' => 'bg-cyan-50 text-cyan-700 ring-cyan-200',
        'ready' => 'bg-emerald-50 text-emerald-700 ring-emerald-200',
        'processing' => 'bg-amber-50 text-amber-700 ring-amber-200',
        'text_extracted' => 'bg-cyan-50 text-cyan-700 ring-cyan-200',
        'chunked' => 'bg-teal-50 text-teal-700 ring-teal-200',
        'embedded' => 'bg-cyan-50 text-cyan-700 ring-cyan-200',
        'pending' => 'bg-cyan-50 text-cyan-700 ring-cyan-200',
        'failed' => 'bg-rose-50 text-rose-700 ring-rose-200',
    ];
@endphp

<span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold ring-1 ring-inset {{ $styles[$status] ?? $styles['processing'] }}">
    @if ($showSpinner && in_array($status, $processingStatuses, true))
        <span class="size-3 animate-spin rounded-full border-2 border-current border-r-transparent" aria-hidden="true"></span>
    @endif
    {{ $labels[$status] ?? str($status)->replace('_', ' ')->title() }}
</span>
