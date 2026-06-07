<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#0f766e">
    <title>{{ $title ?? config('app.name', 'DocuMind') }}</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <link rel="shortcut icon" href="{{ asset('favicon.ico') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-slate-50 font-sans text-slate-900 antialiased">
@php
    $isMarketing = $isMarketing ?? false;
@endphp

@if ($isMarketing)
    <div class="min-h-screen">
        @include('partials.navbar', ['marketing' => true])
        <main>
            @yield('content')
            {{ $slot ?? '' }}
        </main>
    </div>
@else
    <div class="min-h-screen lg:flex">
        @include('partials.sidebar')

        <div class="min-w-0 flex-1">
            @include('partials.navbar', ['marketing' => false])

            @isset($header)
                <header class="border-b border-slate-200 bg-white">
                    <div class="mx-auto max-w-7xl px-4 py-5 sm:px-6 lg:px-8">
                        {{ $header }}
                    </div>
                </header>
            @endisset

            <main class="mx-auto w-full {{ $mainMaxWidth ?? 'max-w-screen-2xl' }} px-4 py-6 sm:px-6 lg:px-8">
                @yield('content')
                {{ $slot ?? '' }}
            </main>
        </div>
    </div>
@endif

@if (session('success') || session('error'))
    <div id="toastNotification" class="fixed right-4 top-4 z-50 w-[calc(100%-2rem)] max-w-sm rounded-lg border bg-white px-4 py-3 text-sm font-medium shadow-lg shadow-slate-200 transition duration-300 sm:right-6 {{ session('success') ? 'border-emerald-200 text-emerald-800' : 'border-rose-200 text-rose-800' }}" role="status" aria-live="polite">
        <div class="flex items-start justify-between gap-3">
            <p>{{ session('success') ?? session('error') }}</p>
            <button type="button" data-dismiss-toast class="-mr-1 rounded-md px-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-700" aria-label="Dismiss notification">
                &times;
            </button>
        </div>
    </div>
    <script>
        (() => {
            const toast = document.getElementById('toastNotification');
            const dismissButton = toast?.querySelector('[data-dismiss-toast]');

            const dismiss = () => {
                if (!toast) {
                    return;
                }

                toast.classList.add('translate-y-2', 'opacity-0');
                setTimeout(() => toast.remove(), 300);
            };

            dismissButton?.addEventListener('click', dismiss);
            setTimeout(dismiss, 3500);
        })();
    </script>
@endif
</body>
</html>
