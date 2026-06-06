@php
    $status = strtolower($status ?? 'processing');
    $styles = [
        'uploaded' => 'bg-sky-50 text-sky-700 ring-sky-200',
        'ready' => 'bg-emerald-50 text-emerald-700 ring-emerald-200',
        'processing' => 'bg-amber-50 text-amber-700 ring-amber-200',
        'text_extracted' => 'bg-violet-50 text-violet-700 ring-violet-200',
        'chunked' => 'bg-indigo-50 text-indigo-700 ring-indigo-200',
        'embedded' => 'bg-cyan-50 text-cyan-700 ring-cyan-200',
        'pending' => 'bg-sky-50 text-sky-700 ring-sky-200',
        'failed' => 'bg-rose-50 text-rose-700 ring-rose-200',
    ];
@endphp

<span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold capitalize ring-1 ring-inset {{ $styles[$status] ?? $styles['processing'] }}">
    {{ str_replace('_', ' ', $status) }}
</span>
