<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#0f766e">
    <title>{{ $title ?? 'Admin - '.config('app.name', 'DocuMind') }}</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <link rel="shortcut icon" href="{{ asset('favicon.ico') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-slate-50 font-sans text-slate-900 antialiased">
@php
    $adminItems = [
        ['label' => 'Dashboard', 'href' => route('admin.dashboard'), 'active' => ['admin.dashboard'], 'available' => true],
        ['label' => 'Users', 'href' => route('admin.users.index'), 'active' => ['admin.users.*'], 'available' => true],
        ['label' => 'Documents', 'href' => route('admin.documents.index'), 'active' => ['admin.documents.*'], 'available' => true],
        ['label' => 'Usage Logs', 'href' => route('admin.usage-logs.index'), 'active' => ['admin.usage-logs.*'], 'available' => true],
        ['label' => 'Usage Limits', 'href' => route('admin.users.index'), 'active' => [], 'available' => true],
        ['label' => 'Queues', 'href' => route('admin.queues.index'), 'active' => ['admin.queues.*'], 'available' => true],
        ['label' => 'Failed Jobs', 'href' => route('admin.failed-jobs.index'), 'active' => ['admin.failed-jobs.*'], 'available' => true],
        ['label' => 'System Health', 'href' => route('admin.system-health.index'), 'active' => ['admin.system-health.*'], 'available' => true],
        ['label' => 'Settings', 'href' => route('admin.settings.index'), 'active' => ['admin.settings.*'], 'available' => true],
    ];
@endphp

<div class="min-h-screen lg:flex">
    <aside class="hidden min-h-screen w-72 shrink-0 border-r border-slate-200 bg-white lg:sticky lg:top-0 lg:flex lg:flex-col">
        <div class="flex items-center gap-3 border-b border-slate-200 px-6 py-5">
            <a href="{{ route('admin.dashboard') }}" class="flex items-center gap-3 font-semibold text-slate-950">
                <span class="grid size-10 place-items-center rounded-lg bg-teal-600 text-sm font-bold text-white">D</span>
                <span>DocuMind Admin</span>
            </a>
        </div>

        <nav class="flex-1 space-y-1 px-4 py-5 text-sm font-medium">
            @foreach ($adminItems as $item)
                @php
                    $active = $item['active'] !== [] && request()->routeIs(...$item['active']);
                @endphp
                <a href="{{ $item['href'] }}"
                   @if (! $item['available']) aria-disabled="true" @endif
                   class="flex items-center justify-between rounded-lg px-3 py-2.5 {{ $active ? 'bg-teal-50 text-teal-700' : ($item['available'] ? 'text-slate-600 hover:bg-slate-100 hover:text-slate-950' : 'text-slate-400') }}">
                    <span>{{ $item['label'] }}</span>
                    @if ($active)
                        <span class="size-1.5 rounded-full bg-teal-600"></span>
                    @endif
                </a>
            @endforeach
        </nav>

        <div class="border-t border-slate-200 p-4">
            <div class="mb-3">
                <p class="text-sm font-semibold text-slate-950">{{ auth()->user()->name ?? 'Admin user' }}</p>
                <p class="mt-1 truncate text-xs text-slate-500">{{ auth()->user()->email ?? 'Authenticated admin' }}</p>
            </div>
            <div class="flex gap-2">
                <a href="{{ route('dashboard') }}" class="flex-1 rounded-lg border border-slate-200 px-3 py-2 text-center text-sm font-semibold text-slate-700 hover:bg-white">App</a>
                <form method="POST" action="{{ route('logout') }}" class="flex-1">
                    @csrf
                    <button type="submit" class="w-full rounded-lg bg-slate-900 px-3 py-2 text-sm font-semibold text-white hover:bg-slate-800">Logout</button>
                </form>
            </div>
        </div>
    </aside>

    <div class="min-w-0 flex-1">
        <header class="sticky top-0 z-20 border-b border-slate-200 bg-white/95 backdrop-blur lg:hidden">
            <div class="flex items-center justify-between px-4 py-3">
                <a href="{{ route('admin.dashboard') }}" class="flex items-center gap-3 font-semibold text-slate-950">
                    <span class="grid size-9 place-items-center rounded-lg bg-teal-600 text-sm font-bold text-white">D</span>
                    <span>DocuMind Admin</span>
                </a>
                <a href="{{ route('dashboard') }}" class="rounded-lg border border-slate-200 px-3 py-2 text-sm font-semibold text-slate-700">App</a>
            </div>

            <nav class="flex gap-2 overflow-x-auto px-4 pb-3 text-sm font-medium text-slate-600">
                @foreach ($adminItems as $item)
                    <a href="{{ $item['href'] }}"
                       @if (! $item['available']) aria-disabled="true" @endif
                       class="whitespace-nowrap rounded-lg px-3 py-2 {{ $item['available'] ? 'hover:bg-slate-100 hover:text-slate-950' : 'text-slate-400' }}">
                        {{ $item['label'] }}
                    </a>
                @endforeach
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="whitespace-nowrap rounded-lg px-3 py-2 font-medium text-slate-600 hover:bg-slate-100 hover:text-slate-950">Logout</button>
                </form>
            </nav>
        </header>

        @isset($header)
            <header class="border-b border-slate-200 bg-white">
                <div class="mx-auto max-w-7xl px-4 py-5 sm:px-6 lg:px-8">
                    {{ $header }}
                </div>
            </header>
        @endisset

        <main class="mx-auto w-full max-w-screen-2xl px-4 py-6 sm:px-6 lg:px-8">
            @yield('content')
            {{ $slot ?? '' }}
        </main>
    </div>
</div>

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
