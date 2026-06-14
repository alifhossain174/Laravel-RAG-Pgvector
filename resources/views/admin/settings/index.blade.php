@php
    $title = 'Admin Settings';
@endphp

@extends('layouts.admin')

@section('content')
    <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight text-slate-950">Settings</h1>
            <p class="mt-2 text-sm text-slate-600">Manage global platform controls, upload limits, RAG behavior, AI model settings, OCR thresholds, and default user limits.</p>
        </div>
        <a href="{{ route('admin.system-health.index') }}" class="rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-center text-sm font-semibold text-slate-700 hover:bg-slate-50">
            System health
        </a>
    </div>

    <form method="POST" action="{{ route('admin.settings.update') }}" class="mt-6 space-y-6">
        @csrf
        @method('PATCH')

        @if ($errors->any())
            <div class="rounded-lg border border-rose-200 bg-rose-50 p-4 text-sm font-medium text-rose-700">
                {{ $errors->first() }}
            </div>
        @endif

        @foreach ($groups as $group => $settings)
            <section class="rounded-lg border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-200 px-5 py-4">
                    <h2 class="font-semibold text-slate-950">{{ $group }}</h2>
                </div>
                <div class="grid gap-5 p-5 lg:grid-cols-2">
                    @foreach ($settings as $setting)
                        @php
                            $field = 'settings.'.$setting['key'];
                            $name = 'settings['.$setting['key'].']';
                            $value = old($field, $setting['field_value']);
                        @endphp

                        @if ($setting['type'] === 'boolean')
                            <label class="flex items-start gap-3 rounded-lg bg-slate-50 p-4">
                                <input type="hidden" name="{{ $name }}" value="0">
                                <input type="checkbox" name="{{ $name }}" value="1" @checked((bool) $value) class="mt-1 rounded border-slate-300 text-teal-600 focus:ring-teal-500">
                                <span>
                                    <span class="block text-sm font-semibold text-slate-950">{{ $setting['label'] }}</span>
                                    <span class="mt-1 block text-sm leading-6 text-slate-500">{{ $setting['description'] }}</span>
                                </span>
                            </label>
                        @elseif ($setting['type'] === 'array')
                            <label class="block lg:col-span-2">
                                <span class="text-sm font-semibold text-slate-950">{{ $setting['label'] }}</span>
                                <textarea name="{{ $name }}" rows="5" class="mt-2 w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm outline-none focus:border-teal-500 focus:ring-4 focus:ring-teal-100">{{ $value }}</textarea>
                                <span class="mt-1 block text-sm leading-6 text-slate-500">{{ $setting['description'] }}</span>
                            </label>
                        @else
                            <label class="block">
                                <span class="text-sm font-semibold text-slate-950">{{ $setting['label'] }}</span>
                                <input name="{{ $name }}"
                                       type="{{ in_array($setting['type'], ['integer', 'decimal'], true) ? 'number' : 'text' }}"
                                       @if ($setting['type'] === 'decimal') step="0.01" @endif
                                       value="{{ $value }}"
                                       class="mt-2 w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm outline-none focus:border-teal-500 focus:ring-4 focus:ring-teal-100">
                                <span class="mt-1 block text-sm leading-6 text-slate-500">{{ $setting['description'] }}</span>
                            </label>
                        @endif
                    @endforeach
                </div>
            </section>
        @endforeach

        <div class="sticky bottom-0 z-10 -mx-4 border-t border-slate-200 bg-white/95 px-4 py-4 backdrop-blur sm:-mx-6 sm:px-6 lg:-mx-8 lg:px-8">
            <div class="mx-auto flex max-w-screen-2xl justify-end">
                <button type="submit" class="rounded-lg bg-teal-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm shadow-teal-200 hover:bg-teal-700">
                    Save settings
                </button>
            </div>
        </div>
    </form>
@endsection
