@php
    $marketing = $marketing ?? false;
    $navItems = [
        ['label' => 'Dashboard', 'href' => route('dashboard')],
        ['label' => 'Documents', 'href' => route('documents.index')],
        ['label' => 'Chat', 'href' => route('chat.index')],
        ['label' => 'Upload', 'href' => route('documents.create')],
    ];
@endphp

@if ($marketing)
    <header class="border-b border-slate-200 bg-white/90 backdrop-blur">
        <div class="mx-auto flex max-w-7xl items-center justify-between px-4 py-4 sm:px-6 lg:px-8">
            <a href="{{ route('home') }}" class="flex items-center gap-3 font-semibold text-slate-950">
                <span class="grid size-9 place-items-center rounded-lg bg-teal-600 text-sm font-bold text-white">D</span>
                <span>DocuMind</span>
            </a>

            <nav class="hidden items-center gap-7 text-sm font-medium text-slate-600 md:flex">
                <a href="{{ route('dashboard') }}" class="hover:text-teal-700">Dashboard</a>
                <a href="{{ route('documents.index') }}" class="hover:text-teal-700">Documents</a>
                <a href="{{ route('chat.index') }}" class="hover:text-teal-700">Chat</a>
            </nav>

            <div class="flex items-center gap-3">
                @auth
                    <a href="{{ route('documents.create') }}" class="rounded-lg bg-teal-600 px-4 py-2 text-sm font-semibold text-white shadow-sm shadow-teal-200 hover:bg-teal-700">
                        Upload document
                    </a>
                @else
                    <a href="{{ route('login') }}" class="hidden text-sm font-semibold text-slate-700 hover:text-teal-700 sm:inline">Log in</a>
                    <a href="{{ route('register') }}" class="rounded-lg bg-teal-600 px-4 py-2 text-sm font-semibold text-white shadow-sm shadow-teal-200 hover:bg-teal-700">
                        Register
                    </a>
                @endauth
            </div>
        </div>
    </header>
@else
    <header class="sticky top-0 z-20 border-b border-slate-200 bg-white/95 backdrop-blur lg:hidden">
        <div class="flex items-center justify-between px-4 py-3">
            <a href="{{ route('dashboard') }}" class="flex items-center gap-3 font-semibold text-slate-950">
                <span class="grid size-9 place-items-center rounded-lg bg-teal-600 text-sm font-bold text-white">D</span>
                <span>DocuMind</span>
            </a>
            <a href="{{ route('documents.create') }}" class="rounded-lg bg-teal-600 px-3 py-2 text-sm font-semibold text-white">Upload</a>
        </div>

        <nav class="flex gap-2 overflow-x-auto px-4 pb-3 text-sm font-medium text-slate-600">
            @foreach ($navItems as $item)
                <a href="{{ $item['href'] }}" class="whitespace-nowrap rounded-lg px-3 py-2 hover:bg-slate-100 hover:text-slate-950">
                    {{ $item['label'] }}
                </a>
            @endforeach
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="whitespace-nowrap rounded-lg px-3 py-2 font-medium text-slate-600 hover:bg-slate-100 hover:text-slate-950">Logout</button>
            </form>
        </nav>
    </header>
@endif
