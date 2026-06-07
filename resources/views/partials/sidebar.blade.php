@php
    $items = [
        ['label' => 'Dashboard', 'href' => route('dashboard'), 'active' => ['dashboard']],
        ['label' => 'Upload Document', 'href' => route('documents.create'), 'active' => ['documents.create']],
        ['label' => 'Documents', 'href' => route('documents.index'), 'active' => ['documents.index', 'documents.show']],
        ['label' => 'DocuMind Chat', 'href' => route('chat.index'), 'active' => ['chat.index', 'chat.show']],
    ];
@endphp

<aside class="hidden min-h-screen w-72 shrink-0 border-r border-slate-200 bg-white lg:sticky lg:top-0 lg:flex lg:flex-col">
    <div class="flex items-center gap-3 border-b border-slate-200 px-6 py-5">
        <a href="{{ route('dashboard') }}" class="flex items-center gap-3 font-semibold text-slate-950">
            <span class="grid size-10 place-items-center rounded-lg bg-teal-600 text-sm font-bold text-white">D</span>
            <span>DocuMind</span>
        </a>
    </div>

    <nav class="flex-1 space-y-1 px-4 py-5 text-sm font-medium">
        @foreach ($items as $item)
            @php
                $active = request()->routeIs(...$item['active']);
            @endphp
            <a href="{{ $item['href'] }}" class="flex items-center justify-between rounded-lg px-3 py-2.5 {{ $active ? 'bg-teal-50 text-teal-700' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-950' }}">
                <span>{{ $item['label'] }}</span>
                @if ($active)
                    <span class="size-1.5 rounded-full bg-teal-600"></span>
                @endif
            </a>
        @endforeach
    </nav>

    <div id="geminiQuotaCard">
        @include('partials.gemini-quota-card', ['geminiQuota' => $geminiQuota ?? null])
    </div>

    <div class="border-t border-slate-200 p-4">
        <div class="mb-3">
            <p class="text-sm font-semibold text-slate-950">{{ auth()->user()->name ?? 'Workspace user' }}</p>
            <p class="mt-1 truncate text-xs text-slate-500">{{ auth()->user()->email ?? 'Authenticated workspace' }}</p>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('profile.edit') }}" class="flex-1 rounded-lg border border-slate-200 px-3 py-2 text-center text-sm font-semibold text-slate-700 hover:bg-white">Profile</a>
            <form method="POST" action="{{ route('logout') }}" class="flex-1">
                @csrf
                <button type="submit" class="w-full rounded-lg bg-slate-900 px-3 py-2 text-sm font-semibold text-white hover:bg-slate-800">Logout</button>
            </form>
        </div>
    </div>
</aside>
