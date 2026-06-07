@php
    $title = 'Document Chat';
    $selectedDocumentCount = $scopedDocuments->count();
    $isAllScope = $conversation?->scope === \App\Models\Conversation::SCOPE_ALL;
    $scopeBadgeLabel = $conversation
        ? ($isAllScope ? 'All documents' : ($selectedDocumentCount === 1 ? 'Single document' : 'Multiple documents'))
        : null;
    $scopeSummary = $conversation
        ? ($isAllScope ? 'All ready documents' : $selectedDocumentCount.' selected document'.($selectedDocumentCount === 1 ? '' : 's'))
        : null;
    $suggestions = $conversation && ! $isAllScope && $selectedDocumentCount === 1
        ? ['Summarize this document', 'What are the key points?', 'What are the important dates or deadlines?', 'What actions are required?']
        : ['Summarize selected documents', 'What are the key points?', 'What are the important dates or deadlines?', 'What actions are required?', 'Compare information across selected documents'];
@endphp

@extends('layouts.app')

@section('content')
    <div class="grid min-w-0 min-h-[calc(100vh-9rem)] gap-6 overflow-x-hidden lg:grid-cols-[minmax(0,360px)_minmax(0,1fr)]">
        <aside class="min-w-0 rounded-lg border border-slate-200 bg-white shadow-sm lg:sticky lg:top-6 lg:max-h-[calc(100vh-3rem)]">
            <div class="border-b border-slate-200 p-5">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <h1 class="font-semibold text-slate-950">Conversations</h1>
                        <p class="mt-1 text-sm text-slate-500">Chat across one, many, or all ready documents.</p>
                    </div>
                    <button type="button" data-open-conversation-modal class="rounded-lg bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm shadow-indigo-200 hover:bg-indigo-700">
                        New
                    </button>
                </div>

                <form method="GET" action="{{ route('chat.index') }}" class="mt-4 flex gap-2">
                    <label class="min-w-0 flex-1">
                        <span class="sr-only">Search conversations</span>
                        <input name="search" type="search" value="{{ $search }}" placeholder="Search conversations" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm outline-none focus:border-indigo-500 focus:ring-4 focus:ring-indigo-100">
                    </label>
                    <button type="submit" class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                        Search
                    </button>
                </form>
            </div>

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
                    <article class="rounded-lg border p-4 transition {{ $active ? 'border-indigo-200 bg-indigo-50/70' : 'border-slate-200 bg-white hover:border-indigo-200 hover:bg-indigo-50/40' }}">
                        <div class="flex items-start gap-3">
                            <a href="{{ route('chat.show', $item) }}" class="min-w-0 flex-1">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-semibold text-slate-950">{{ $item->title }}</p>
                                        <p class="mt-1 text-sm leading-5 text-slate-500">{{ $item->messages_count }} message{{ $item->messages_count === 1 ? '' : 's' }}</p>
                                    </div>
                                    @if ($active)
                                        <span class="mt-1 size-2 shrink-0 rounded-full bg-indigo-600"></span>
                                    @endif
                                </div>
                                <div class="mt-3 flex flex-wrap items-center gap-2 text-xs font-medium">
                                    <span class="rounded-full bg-slate-100 px-2.5 py-1 text-slate-600">{{ $itemScopeLabel }}</span>
                                    <span class="text-slate-400">{{ $item->updated_at->diffForHumans() }}</span>
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
                        <button type="button" data-open-conversation-modal class="mt-4 rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm shadow-indigo-200 hover:bg-indigo-700">
                            New Conversation
                        </button>
                    </div>
                @endforelse
            </div>

            @if ($conversations->hasPages())
                <div class="border-t border-slate-200 px-4 py-3">
                    {{ $conversations->links() }}
                </div>
            @endif
        </aside>

        <section class="min-w-0 overflow-hidden flex min-h-[calc(100vh-9rem)] flex-col rounded-lg border border-slate-200 bg-white shadow-sm">
            @if ($conversation)
                <div class="border-b border-slate-200 px-5 py-4">
                    <div class="flex flex-col justify-between gap-4 xl:flex-row xl:items-start">
                        <div class="min-w-0">
                            <p class="text-xs font-semibold uppercase tracking-wide text-indigo-700">Document Chat</p>
                            <h2 class="mt-1 break-words text-xl font-semibold tracking-tight text-slate-950">{{ $conversation->title }}</h2>
                            <div class="mt-3 flex flex-wrap items-center gap-2 text-xs font-semibold">
                                <span class="rounded-full bg-indigo-50 px-2.5 py-1 text-indigo-700 ring-1 ring-indigo-100">{{ $scopeBadgeLabel }}</span>
                                <span class="rounded-full bg-slate-100 px-2.5 py-1 text-slate-600">{{ $scopeSummary }}</span>
                                <span class="rounded-full bg-slate-100 px-2.5 py-1 text-slate-600">Updated {{ $conversation->updated_at->diffForHumans() }}</span>
                            </div>
                        </div>

                        <div class="flex min-w-0 flex-col gap-3 xl:w-[28rem] xl:max-w-full">
                            <details class="min-w-0 rounded-lg border border-slate-200 bg-slate-50 p-3">
                                <summary class="cursor-pointer list-none text-sm font-semibold text-slate-800">
                                    View selected documents
                                </summary>
                                <div class="mt-3 max-h-72 space-y-3 overflow-y-auto">
                                    @forelse ($scopedDocuments as $document)
                                        <article class="rounded-lg border border-slate-200 bg-white p-3">
                                            <div class="flex items-start justify-between gap-3">
                                                <div class="min-w-0">
                                                    <p class="truncate text-sm font-semibold text-slate-950">{{ $document->displayTitle() }}</p>
                                                    <p class="mt-1 text-xs text-slate-500">Processed {{ $document->processed_at?->diffForHumans() ?? '-' }}</p>
                                                </div>
                                                @include('partials.status-badge', ['status' => $document->status])
                                            </div>
                                            <dl class="mt-3 grid grid-cols-2 gap-2 text-xs">
                                                <div class="rounded-lg bg-slate-50 p-2">
                                                    <dt class="text-slate-500">Pages</dt>
                                                    <dd class="mt-1 font-semibold text-slate-900">{{ $document->total_pages ?? '-' }}</dd>
                                                </div>
                                                <div class="rounded-lg bg-slate-50 p-2">
                                                    <dt class="text-slate-500">Chunks</dt>
                                                    <dd class="mt-1 font-semibold text-slate-900">{{ $document->total_chunks }}</dd>
                                                </div>
                                            </dl>
                                        </article>
                                    @empty
                                        <div class="rounded-lg border border-dashed border-slate-300 bg-white p-4 text-sm text-slate-500">
                                            No ready documents are currently in this conversation scope.
                                        </div>
                                    @endforelse
                                </div>
                            </details>

                        </div>
                    </div>
                </div>

                <div id="messageThread" class="min-w-0 flex-1 space-y-6 overflow-y-auto bg-slate-50/60 p-5">
                    @forelse ($conversation->messages as $message)
                        @if ($message->role === \App\Models\Message::ROLE_USER)
                            <article class="flex justify-end">
                                <div class="max-w-2xl">
                                    <p class="mb-1 text-right text-xs font-semibold text-slate-500">You</p>
                                    <div class="rounded-lg bg-indigo-600 px-4 py-3 text-sm leading-6 text-white shadow-sm shadow-indigo-200">
                                        {{ $message->content }}
                                    </div>
                                </div>
                            </article>
                        @else
                            <article class="max-w-3xl">
                                <p class="mb-1 text-xs font-semibold text-slate-500">Assistant</p>
                                <div class="rounded-lg border border-slate-200 bg-white px-4 py-3 text-sm leading-6 text-slate-700 shadow-sm">
                                    {{ $message->content }}
                                </div>
                                @php
                                    $sources = $message->metadata['sources'] ?? [];
                                @endphp
                                @if (($message->metadata['error'] ?? false) === true)
                                    <p class="mt-2 text-xs font-medium text-rose-700">Generation failed safely. Try again when the provider is available.</p>
                                @elseif ($sources !== [])
                                    <details class="mt-3 rounded-lg border border-indigo-100 bg-indigo-50/40 p-3" {{ count($sources) <= 2 ? 'open' : '' }}>
                                        <summary class="cursor-pointer text-sm font-semibold text-indigo-800">
                                            Sources used ({{ count($sources) }})
                                        </summary>
                                        <div class="mt-3 grid gap-3 md:grid-cols-2">
                                            @foreach ($sources as $source)
                                                @php
                                                    $pageStart = $source['page_start'] ?? null;
                                                    $pageEnd = $source['page_end'] ?? null;
                                                    $pageLabel = 'Page unknown';

                                                    if ($pageStart && $pageEnd && (int) $pageStart === (int) $pageEnd) {
                                                        $pageLabel = 'Page '.$pageStart;
                                                    } elseif ($pageStart && $pageEnd) {
                                                        $pageLabel = 'Pages '.$pageStart.'-'.$pageEnd;
                                                    } elseif ($pageStart) {
                                                        $pageLabel = 'Page '.$pageStart;
                                                    }
                                                @endphp
                                                @include('partials.citation-card', [
                                                    'documentTitle' => $source['document_title'] ?? 'Document source',
                                                    'pageLabel' => $pageLabel,
                                                    'chunkPreview' => $source['preview'] ?? 'Source preview unavailable.',
                                                    'relevanceScore' => isset($source['score']) ? number_format((float) $source['score'], 3) : 'Pending',
                                                ])
                                            @endforeach
                                        </div>
                                    </details>
                                @endif
                            </article>
                        @endif
                    @empty
                        <div class="grid min-h-80 place-items-center rounded-lg border border-dashed border-slate-300 bg-white p-8 text-center">
                            <div>
                                <p class="font-semibold text-slate-950">No messages yet</p>
                                <p class="mt-2 text-sm leading-6 text-slate-500">Ask the first question to start this conversation.</p>
                            </div>
                        </div>
                    @endforelse

                    <template id="assistantLoadingTemplate">
                        <article class="max-w-3xl">
                            <p class="mb-1 text-xs font-semibold text-slate-500">Assistant</p>
                            <div class="rounded-lg border border-slate-200 bg-white px-4 py-3 text-sm leading-6 text-slate-500 shadow-sm">
                                Preparing a response...
                            </div>
                        </article>
                    </template>

                    @if ($conversation->messages->where('role', \App\Models\Message::ROLE_ASSISTANT)->isEmpty())
                        <div class="max-w-4xl">
                            <div class="mb-3 flex items-center justify-between gap-3">
                                <p class="text-sm font-semibold text-slate-950">Sources and citations</p>
                                <span class="rounded-full bg-white px-2.5 py-1 text-xs font-semibold text-slate-500 ring-1 ring-slate-200">Waiting for answer</span>
                            </div>
                            <div class="rounded-lg border border-dashed border-indigo-200 bg-indigo-50/50 p-4">
                                <p class="text-sm font-semibold text-slate-950">Citations will appear under assistant answers</p>
                                <p class="mt-2 text-sm leading-6 text-slate-600">Ask a question to retrieve matching chunks from the selected documents.</p>
                            </div>
                        </div>
                    @endif
                </div>

                <div class="sticky bottom-0 z-10 min-w-0 border-t border-slate-200 bg-white p-4">
                    <div class="mb-3 flex max-w-full gap-2 overflow-x-auto overscroll-x-contain pb-1">
                        @foreach ($suggestions as $suggestion)
                            <button type="button" data-suggested-prompt="{{ $suggestion }}" class="whitespace-nowrap rounded-full border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                                {{ $suggestion }}
                            </button>
                        @endforeach
                    </div>

                    <form id="chatMessageForm" method="POST" action="{{ route('chat.messages.store', $conversation) }}" class="flex min-w-0 flex-col gap-3 sm:flex-row">
                        @csrf
                        <input id="messageInput" name="content" type="text" value="{{ old('content') }}" maxlength="4000" required placeholder="Ask a question about the selected documents" class="min-w-0 flex-1 rounded-lg border border-slate-300 px-3 py-3 text-sm outline-none focus:border-indigo-500 focus:ring-4 focus:ring-indigo-100">
                        <button id="sendMessageButton" type="submit" class="rounded-lg bg-indigo-600 px-5 py-3 text-sm font-semibold text-white shadow-sm shadow-indigo-200 hover:bg-indigo-700 disabled:cursor-not-allowed disabled:bg-indigo-400">
                            <span data-send-label>Send</span>
                        </button>
                    </form>
                    @error('content')
                        <p class="mt-2 text-sm text-rose-600">{{ $message }}</p>
                    @enderror
                </div>
            @else
                <div class="grid flex-1 place-items-center p-8 text-center">
                    <div class="max-w-md">
                        <div class="mx-auto grid size-12 place-items-center rounded-lg bg-indigo-50 text-sm font-bold text-indigo-700 ring-1 ring-indigo-100">C</div>
                        <h2 class="mt-5 text-xl font-semibold tracking-tight text-slate-950">Create a conversation</h2>
                        <p class="mt-2 text-sm leading-6 text-slate-500">Choose one document, several ready documents, or all ready documents before asking questions.</p>
                        <button type="button" data-open-conversation-modal class="mt-5 rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm shadow-indigo-200 hover:bg-indigo-700">
                            New Conversation
                        </button>
                    </div>
                </div>
            @endif
        </section>
    </div>

    <div id="conversationModal" class="fixed inset-0 z-40 {{ (($openCreateConversationModal ?? false) || $errors->has('title') || $errors->has('scope') || $errors->has('document_ids') || $errors->has('document_ids.*')) ? '' : 'hidden' }}">
        <div class="absolute inset-0 bg-slate-950/40" data-close-conversation-modal></div>
        <div class="relative mx-auto flex min-h-full w-full max-w-2xl items-center px-4 py-8">
            <div class="w-full rounded-lg border border-slate-200 bg-white shadow-xl">
                <div class="flex items-start justify-between gap-4 border-b border-slate-200 px-5 py-4">
                    <div>
                        <h2 class="font-semibold text-slate-950">New conversation</h2>
                        <p class="mt-1 text-sm text-slate-500">Choose the ready documents this conversation can use.</p>
                    </div>
                    <button type="button" data-close-conversation-modal class="rounded-lg border border-slate-200 px-3 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-50">
                        Close
                    </button>
                </div>

                <form method="POST" action="{{ route('chat.store') }}" class="p-5">
                    @csrf

                    <label class="block">
                        <span class="text-sm font-medium text-slate-700">Conversation title</span>
                        <input name="title" type="text" value="{{ old('title') }}" placeholder="Example: Contract renewal questions" class="mt-2 w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm outline-none focus:border-indigo-500 focus:ring-4 focus:ring-indigo-100">
                        @error('title')
                            <p class="mt-2 text-sm text-rose-600">{{ $message }}</p>
                        @enderror
                    </label>

                    <fieldset class="mt-5">
                        <legend class="text-sm font-medium text-slate-700">Document selection mode</legend>
                        <div class="mt-2 grid gap-3 sm:grid-cols-2">
                            <label class="flex cursor-pointer gap-3 rounded-lg border border-slate-200 p-4 hover:bg-slate-50">
                                <input name="scope" value="selected" type="radio" class="mt-1 size-4 border-slate-300 text-indigo-600 focus:ring-indigo-500" @checked(old('scope', \App\Models\Conversation::SCOPE_SELECTED) === \App\Models\Conversation::SCOPE_SELECTED)>
                                <span>
                                    <span class="block text-sm font-semibold text-slate-950">Selected documents</span>
                                    <span class="mt-1 block text-sm text-slate-500">Pick one or more ready PDFs.</span>
                                </span>
                            </label>

                            <label class="flex cursor-pointer gap-3 rounded-lg border border-slate-200 p-4 hover:bg-slate-50">
                                <input id="allDocumentsScope" name="scope" value="all" type="radio" class="mt-1 size-4 border-slate-300 text-indigo-600 focus:ring-indigo-500" @checked(old('scope') === \App\Models\Conversation::SCOPE_ALL)>
                                <span>
                                    <span class="block text-sm font-semibold text-slate-950">All ready documents</span>
                                    <span class="mt-1 block text-sm text-slate-500">Use the full ready-document set.</span>
                                </span>
                            </label>
                        </div>
                        @error('scope')
                            <p class="mt-2 text-sm text-rose-600">{{ $message }}</p>
                        @enderror
                    </fieldset>

                    <div class="mt-5">
                        <div class="flex items-center justify-between gap-3">
                            <p class="text-sm font-medium text-slate-700">Ready documents</p>
                            <label class="flex items-center gap-2 text-sm font-semibold text-indigo-700">
                                <input id="selectAllDocuments" type="checkbox" class="size-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                                <span>Select all documents</span>
                            </label>
                        </div>
                        <div class="mt-2 rounded-lg border border-slate-200">
                            <div class="max-h-72 divide-y divide-slate-100 overflow-y-auto">
                                @forelse ($documents as $document)
                                    <label class="flex items-start gap-3 px-4 py-3 text-sm hover:bg-slate-50">
                                        <input name="document_ids[]" value="{{ $document->id }}" type="checkbox" class="document-choice mt-1 size-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500" @checked(in_array($document->id, old('document_ids', [])))>
                                        <span>
                                            <span class="block font-medium text-slate-950">{{ $document->displayTitle() }}</span>
                                            <span class="mt-1 block text-xs text-slate-500">{{ $document->total_pages ?? '-' }} pages - {{ $document->statusLabel() }}</span>
                                        </span>
                                    </label>
                                @empty
                                    <div class="px-4 py-6 text-sm text-slate-500">
                                        No ready documents yet. Upload and process PDFs before creating a document-scoped conversation.
                                    </div>
                                @endforelse
                            </div>
                        </div>
                        @error('document_ids')
                            <p class="mt-2 text-sm text-rose-600">{{ $message }}</p>
                        @enderror
                        @error('document_ids.*')
                            <p class="mt-2 text-sm text-rose-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                        <button type="button" data-close-conversation-modal class="rounded-lg border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                            Cancel
                        </button>
                        <button type="submit" class="rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm shadow-indigo-200 hover:bg-indigo-700">
                            Create Conversation
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        (() => {
            const modal = document.getElementById('conversationModal');
            const openButtons = document.querySelectorAll('[data-open-conversation-modal]');
            const closeButtons = document.querySelectorAll('[data-close-conversation-modal]');
            const selectAll = document.getElementById('selectAllDocuments');
            const documentChoices = document.querySelectorAll('.document-choice');
            const selectedScope = document.querySelector('input[name="scope"][value="selected"]');
            const allScope = document.querySelector('input[name="scope"][value="all"]');
            const messageInput = document.getElementById('messageInput');
            const messageForm = document.getElementById('chatMessageForm');
            const sendButton = document.getElementById('sendMessageButton');
            const sendLabel = sendButton?.querySelector('[data-send-label]');
            const messageThread = document.getElementById('messageThread');
            const loadingTemplate = document.getElementById('assistantLoadingTemplate');
            const promptButtons = document.querySelectorAll('[data-suggested-prompt]');

            const scrollToBottom = () => {
                if (messageThread) {
                    messageThread.scrollTop = messageThread.scrollHeight;
                }
            };

            openButtons.forEach((button) => {
                button.addEventListener('click', () => modal.classList.remove('hidden'));
            });

            closeButtons.forEach((button) => {
                button.addEventListener('click', () => modal.classList.add('hidden'));
            });

            selectAll?.addEventListener('change', (event) => {
                documentChoices.forEach((choice) => {
                    choice.checked = event.target.checked;
                });

                if (event.target.checked && selectedScope) {
                    selectedScope.checked = true;
                }
            });

            allScope?.addEventListener('change', () => {
                if (allScope.checked) {
                    documentChoices.forEach((choice) => {
                        choice.checked = false;
                    });

                    if (selectAll) {
                        selectAll.checked = false;
                    }
                }
            });

            promptButtons.forEach((button) => {
                button.addEventListener('click', () => {
                    if (messageInput) {
                        messageInput.value = button.dataset.suggestedPrompt;
                        messageInput.focus();
                        messageForm?.requestSubmit();
                    }
                });
            });

            messageForm?.addEventListener('submit', () => {
                if (sendButton) {
                    sendButton.disabled = true;
                }

                if (sendLabel) {
                    sendLabel.textContent = 'Sending...';
                }

                if (messageThread && loadingTemplate) {
                    messageThread.appendChild(loadingTemplate.content.cloneNode(true));
                    scrollToBottom();
                }
            });

            scrollToBottom();
        })();
    </script>
@endsection
