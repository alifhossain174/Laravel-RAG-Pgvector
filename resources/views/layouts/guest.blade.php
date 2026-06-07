<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $attributes->get('title', config('app.name', 'DocuMind')) }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-slate-50 font-sans text-slate-900 antialiased">
<main class="min-h-screen px-5 py-8 sm:px-8 lg:px-12">
    <div class="mx-auto grid min-h-[calc(100vh-4rem)] w-full max-w-6xl items-center gap-8 lg:grid-cols-[minmax(0,1fr)_460px] xl:gap-12">
        <section class="hidden rounded-lg border border-slate-200 bg-white p-8 shadow-sm lg:flex lg:min-h-[620px] lg:flex-col xl:p-10">
            <a href="{{ route('home') }}" class="flex items-center gap-3 font-semibold text-slate-950">
                <span class="grid size-10 place-items-center rounded-lg bg-teal-600 text-sm font-bold text-white">D</span>
                <span>DocuMind</span>
            </a>

            <div class="flex flex-1 items-center">
                <div class="max-w-xl">
                    <p class="text-sm font-semibold uppercase tracking-[0.2em] text-teal-700">Document intelligence</p>
                    <h1 class="mt-5 text-4xl font-semibold tracking-tight text-slate-950">Source-backed answers for every PDF workspace.</h1>
                    <p class="mt-5 text-base leading-7 text-slate-600">
                        Sign in to manage uploads, review processing status, and ask document-specific questions with citations.
                    </p>

                    <div class="mt-8 rounded-lg border border-slate-200 bg-slate-50 p-4">
                        <div class="rounded-lg bg-white p-4 shadow-sm">
                            <p class="text-sm font-semibold text-slate-950">Verified document workspace</p>
                            <p class="mt-2 text-sm text-slate-600">Ready for source-backed document chat</p>
                            <div class="mt-4 h-2 rounded-full bg-slate-200">
                                <div class="h-2 w-3/4 rounded-full bg-teal-600"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="w-full">
            <div class="mx-auto w-full max-w-md">
                <div class="mb-8 lg:hidden">
                    <a href="{{ route('home') }}" class="flex items-center justify-center gap-3 font-semibold text-slate-950">
                        <span class="grid size-10 place-items-center rounded-lg bg-teal-600 text-sm font-bold text-white">D</span>
                        <span>DocuMind</span>
                    </a>
                </div>

                <div class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
                    <div class="mb-6">
                        <p class="text-sm font-semibold uppercase tracking-[0.18em] text-teal-700">{{ $attributes->get('eyebrow', 'Workspace access') }}</p>
                        <h2 class="mt-3 text-2xl font-semibold tracking-tight text-slate-950">{{ $attributes->get('heading', $attributes->get('title', 'Welcome')) }}</h2>
                        @if ($attributes->has('description'))
                            <p class="mt-2 text-sm leading-6 text-slate-600">{{ $attributes->get('description') }}</p>
                        @endif
                    </div>

                    {{ $slot }}
                </div>
            </div>
        </section>
    </div>
</main>
</body>
</html>
