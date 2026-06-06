<div class="rounded-lg border border-indigo-100 bg-indigo-50/60 p-4">
    <div class="flex flex-wrap items-center justify-between gap-2">
        <p class="text-sm font-semibold text-slate-950">{{ $source['document'] }}</p>
        <span class="rounded-full bg-white px-2.5 py-1 text-xs font-semibold text-indigo-700 ring-1 ring-indigo-100">
            Page {{ $source['page'] }}
        </span>
    </div>
    <p class="mt-3 text-sm leading-6 text-slate-700">{{ $source['snippet'] }}</p>
</div>
