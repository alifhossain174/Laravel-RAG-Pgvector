@php
    $tone = $tone ?? 'indigo';
    $styles = [
        'indigo' => 'bg-indigo-50 text-indigo-700',
        'emerald' => 'bg-emerald-50 text-emerald-700',
        'amber' => 'bg-amber-50 text-amber-700',
        'sky' => 'bg-sky-50 text-sky-700',
    ];
@endphp

<div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
    <div class="flex items-start justify-between gap-4">
        <div>
            <p class="text-sm font-medium text-slate-500">{{ $label }}</p>
            <p class="mt-2 text-3xl font-semibold tracking-tight text-slate-950">{{ $value }}</p>
        </div>
        <span class="rounded-lg px-2.5 py-1 text-xs font-semibold {{ $styles[$tone] ?? $styles['indigo'] }}">
            {{ $meta ?? 'Live' }}
        </span>
    </div>
    @isset($helper)
        <p class="mt-4 text-sm text-slate-500">{{ $helper }}</p>
    @endisset
</div>
