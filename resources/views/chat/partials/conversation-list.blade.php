<div class="max-h-80 space-y-3 overflow-y-auto p-4 lg:max-h-[calc(100vh-19rem)]">
    @forelse ($conversations as $item)
        @php
            $active = $conversation?->is($item) ?? false;
            $itemScopeLabel = match (true) {
                $item->scope === \App\Models\Conversation::SCOPE_ALL => 'All documents',
                $item->documents_count === 1 => 'Single document',
                $item->documents_count > 1 => 'Multiple documents',
                default => 'No documents',
            };
        @endphp
        <article class="rounded-lg border p-4 transition {{ $active ? 'border-teal-200 bg-teal-50/70' : 'border-slate-200 bg-white hover:border-teal-200 hover:bg-teal-50/40' }}" @if ($active) data-active-conversation-card @endif>
            <div class="flex items-start gap-3">
                <a href="{{ route('chat.show', $item) }}" class="min-w-0 flex-1">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="truncate text-sm font-semibold text-slate-950" @if ($active) data-active-conversation-title @endif>{{ $item->title }}</p>
                            <p class="mt-1 text-sm leading-5 text-slate-500" @if ($active) data-active-conversation-message-count @endif>{{ $item->messages_count }} message{{ $item->messages_count === 1 ? '' : 's' }}</p>
                        </div>
                        @if ($active)
                            <span class="mt-1 size-2 shrink-0 rounded-full bg-teal-600"></span>
                        @endif
                    </div>
                    <div class="mt-3 flex flex-wrap items-center gap-2 text-xs font-medium">
                        <span class="rounded-full bg-slate-100 px-2.5 py-1 text-slate-600">{{ $itemScopeLabel }}</span>
                        <span class="text-slate-400" @if ($active) data-active-conversation-updated @endif>{{ $item->updated_at->diffForHumans() }}</span>
                    </div>
                </a>

                <div class="shrink-0">
                    <form method="POST" action="{{ route('chat.destroy', $item) }}" onsubmit="return confirm('Delete this conversation and all of its messages?');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="rounded-lg border border-rose-200 bg-white px-2.5 py-1.5 text-xs font-semibold text-rose-700 hover:bg-rose-50">
                            Delete
                        </button>
                    </form>
                </div>
            </div>
        </article>
    @empty
        <div class="rounded-lg border border-dashed border-slate-300 p-6 text-center">
            <p class="font-semibold text-slate-950">No conversations yet</p>
            <p class="mt-2 text-sm leading-6 text-slate-500">Create a conversation and choose which ready documents it can use.</p>
            <button type="button" data-open-conversation-modal class="mt-4 rounded-lg bg-teal-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm shadow-teal-200 hover:bg-teal-700">
                New Conversation
            </button>
        </div>
    @endforelse
</div>

@if ($conversations->hasPages())
    <div class="border-t border-slate-200 px-4 py-3" data-live-pagination>
        {{ $conversations->links() }}
    </div>
@endif
