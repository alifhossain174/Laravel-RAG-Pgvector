<article class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm transition hover:border-teal-200 hover:shadow-md" data-document-status-poller data-document-id="{{ $document->id }}" data-document-status-url="{{ route('documents.status', $document) }}" data-document-current-status="{{ $document->status }}">
    <div class="flex items-start justify-between gap-4">
        <div>
            <p class="text-sm font-semibold text-slate-950">{{ $document->displayTitle() }}</p>
            <p class="mt-1 text-sm text-slate-500">{{ $document->description ?: 'No description provided.' }}</p>
        </div>
        <span data-document-status-badge>
            @include('partials.status-badge', ['status' => $document->status, 'showSpinner' => true])
        </span>
    </div>

    <dl class="mt-5 grid grid-cols-2 gap-3 text-sm sm:grid-cols-4">
        <div>
            <dt class="text-slate-500">Pages</dt>
            <dd class="mt-1 font-semibold text-slate-900" data-document-total-pages>{{ $document->total_pages ?? '-' }}</dd>
        </div>
        <div>
            <dt class="text-slate-500">Chunks</dt>
            <dd class="mt-1 font-semibold text-slate-900" data-document-total-chunks>{{ $document->total_chunks }}</dd>
        </div>
        <div>
            <dt class="text-slate-500">Size</dt>
            <dd class="mt-1 font-semibold text-slate-900">{{ $document->formattedFileSize() }}</dd>
        </div>
        <div>
            <dt class="text-slate-500">Uploaded</dt>
            <dd class="mt-1 font-semibold text-slate-900">{{ $document->created_at->diffForHumans() }}</dd>
        </div>
    </dl>

    <div class="mt-5 flex flex-wrap gap-2">
        <a href="{{ route('documents.show', $document) }}" class="rounded-lg border border-slate-200 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">View</a>
        <a href="{{ route('chat.create') }}" data-document-chat-action data-document-ready-href="{{ route('chat.create') }}" aria-disabled="{{ $document->status === \App\Models\Document::STATUS_READY ? 'false' : 'true' }}" class="rounded-lg border border-teal-200 px-3 py-2 text-sm font-semibold text-teal-700 hover:bg-teal-50 {{ $document->status === \App\Models\Document::STATUS_READY ? '' : 'pointer-events-none cursor-not-allowed opacity-50' }}">Chat</a>
        <form method="POST" action="{{ route('documents.destroy', $document) }}" onsubmit="return confirm('Delete this document and its stored file?');">
            @csrf
            @method('DELETE')
            <button type="submit" class="rounded-lg border border-rose-200 px-3 py-2 text-sm font-semibold text-rose-700 hover:bg-rose-50">Delete</button>
        </form>
    </div>
</article>
