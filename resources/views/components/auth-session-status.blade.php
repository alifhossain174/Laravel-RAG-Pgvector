@props(['status'])

@if ($status)
    <div {{ $attributes->merge(['class' => 'text-sm font-medium text-emerald-700']) }}>
        {{ $status }}
    </div>
@endif
