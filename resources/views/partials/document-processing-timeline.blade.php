@php
    $status = strtolower($status ?? $document->status ?? 'uploaded');
    $failed = $status === 'failed';
    $steps = [
        ['key' => 'uploaded', 'label' => 'Uploaded'],
        ['key' => 'text_extracted', 'label' => 'Text Extracted'],
        ['key' => 'chunked', 'label' => 'Chunks Created'],
        ['key' => 'embedded', 'label' => 'Embeddings Stored'],
        ['key' => 'ready', 'label' => 'Ready for Chat'],
    ];
    $completedByStatus = [
        'uploaded' => [],
        'processing' => [],
        'text_extracted' => ['uploaded', 'text_extracted'],
        'chunked' => ['uploaded', 'text_extracted', 'chunked'],
        'embedded' => ['uploaded', 'text_extracted', 'chunked', 'embedded'],
        'ready' => ['uploaded', 'text_extracted', 'chunked', 'embedded', 'ready'],
        'failed' => [],
    ];
    $activeByStatus = [
        'uploaded' => 'uploaded',
        'processing' => 'uploaded',
        'text_extracted' => 'chunked',
        'chunked' => 'embedded',
        'embedded' => 'ready',
    ];
    $completed = $completedByStatus[$status] ?? [];
    $active = $activeByStatus[$status] ?? null;
@endphp

<ol class="mt-5 space-y-4">
    @foreach ($steps as $step)
        @php
            $isComplete = in_array($step['key'], $completed, true);
            $isActive = ! $failed && $active === $step['key'];
        @endphp
        <li class="flex gap-3">
            @if ($failed)
                <span class="mt-0.5 grid size-7 shrink-0 place-items-center rounded-full bg-rose-50 text-xs font-semibold text-rose-700 ring-1 ring-rose-200">!</span>
            @elseif ($isComplete)
                <span class="mt-0.5 grid size-7 shrink-0 place-items-center rounded-full bg-emerald-50 text-xs font-semibold text-emerald-700 ring-1 ring-emerald-200">&#10003;</span>
            @elseif ($isActive)
                <span class="mt-0.5 grid size-7 shrink-0 place-items-center rounded-full bg-amber-50 text-amber-700 ring-1 ring-amber-200">
                    <span class="size-3 animate-spin rounded-full border-2 border-current border-r-transparent" aria-hidden="true"></span>
                </span>
            @else
                <span class="mt-0.5 grid size-7 shrink-0 place-items-center rounded-full bg-slate-50 text-slate-400 ring-1 ring-slate-200">
                    <span class="size-1.5 rounded-full bg-current"></span>
                </span>
            @endif

            <div>
                <p class="text-sm font-semibold {{ $failed ? 'text-rose-800' : 'text-slate-950' }}">{{ $step['label'] }}</p>
                <p class="mt-1 text-sm {{ $failed ? 'text-rose-700' : 'text-slate-500' }}">
                    @if ($failed)
                        Processing stopped before completion.
                    @elseif ($isComplete)
                        Completed.
                    @elseif ($isActive)
                        In progress.
                    @else
                        Waiting.
                    @endif
                </p>
            </div>
        </li>
    @endforeach
</ol>
