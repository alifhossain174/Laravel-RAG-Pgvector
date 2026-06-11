@php
    $title = 'DocuMind Chat';
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
    $chatBlocked = ($geminiQuota['enabled'] ?? false) && ! ($geminiQuota['can_ask'] ?? true);
    $chatBlockedMessage = $geminiQuota['blocked_message'] ?? 'Gemini free-tier limit reached. Try again later.';
@endphp

@extends('layouts.app')

@section('content')
    <div class="grid min-w-0 gap-6 overflow-x-hidden lg:h-[calc(100vh-5.5rem)] lg:grid-cols-[minmax(0,360px)_minmax(0,1fr)] lg:overflow-hidden">
        <aside class="min-w-0 rounded-lg border border-slate-200 bg-white shadow-sm lg:h-full lg:overflow-hidden">
            <div class="border-b border-slate-200 p-5">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <h1 class="font-semibold text-slate-950">Conversations</h1>
                        <p class="mt-1 text-sm text-slate-500">Chat across one, many, or all ready documents.</p>
                    </div>
                    <button type="button" data-open-conversation-modal class="rounded-lg bg-teal-600 px-3 py-2 text-sm font-semibold text-white shadow-sm shadow-teal-200 hover:bg-teal-700">
                        New
                    </button>
                </div>

                <form id="conversationSearchForm" method="GET" action="{{ $conversation ? route('chat.show', $conversation) : route('chat.index') }}" data-live-conversation-search data-results-target="#conversationSearchResults" data-status-target="#conversationSearchStatus" class="mt-4">
                    <label class="min-w-0 flex-1">
                        <span class="sr-only">Search conversations</span>
                        <input name="search" type="search" value="{{ $search }}" placeholder="Search conversations" autocomplete="off" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm outline-none focus:border-teal-500 focus:ring-4 focus:ring-teal-100">
                    </label>
                    <p id="conversationSearchStatus" class="sr-only" aria-live="polite"></p>
                </form>
            </div>

            <div id="conversationSearchResults">
                @include('chat.partials.conversation-list', [
                    'conversations' => $conversations,
                    'conversation' => $conversation,
                ])
            </div>
        </aside>

        <section class="min-w-0 overflow-hidden flex min-h-[calc(100vh-9rem)] flex-col rounded-lg border border-slate-200 bg-white shadow-sm lg:h-full lg:min-h-0">
            @if ($conversation)
                <div class="border-b border-slate-200 px-5 py-4">
                    <div class="flex flex-col justify-between gap-4 xl:flex-row xl:items-start">
                        <div class="min-w-0">
                            <p class="text-xs font-semibold uppercase tracking-wide text-teal-700">DocuMind Chat</p>
                            <h2 data-conversation-heading class="mt-1 break-words text-xl font-semibold tracking-tight text-slate-950">{{ $conversation->title }}</h2>
                            <div class="mt-3 flex flex-wrap items-center gap-2 text-xs font-semibold">
                                <span class="rounded-full bg-teal-50 px-2.5 py-1 text-teal-700 ring-1 ring-teal-100">{{ $scopeBadgeLabel }}</span>
                                <span class="rounded-full bg-slate-100 px-2.5 py-1 text-slate-600">{{ $scopeSummary }}</span>
                                <span data-conversation-updated class="rounded-full bg-slate-100 px-2.5 py-1 text-slate-600">Updated {{ $conversation->updated_at->diffForHumans() }}</span>
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

                <div id="messageThread" class="min-w-0 flex flex-1 flex-col overflow-y-auto overscroll-contain bg-slate-50/60 p-5">
                    @forelse ($conversation->messages as $message)
                        @include('partials.chat-message', ['message' => $message])
                    @empty
                        <div data-empty-chat-state class="flex flex-1 items-center justify-center rounded-lg border border-dashed border-slate-300 bg-white text-center">
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

                </div>

                <div class="sticky bottom-0 z-10 min-w-0 border-t border-slate-200 bg-white p-4">
                    <div class="mb-3 flex max-w-full gap-2 overflow-x-auto overscroll-x-contain pb-1">
                        @foreach ($suggestions as $suggestion)
                            <button type="button" data-suggested-prompt="{{ $suggestion }}" @disabled($chatBlocked) class="whitespace-nowrap rounded-full border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 disabled:cursor-not-allowed disabled:bg-slate-100 disabled:text-slate-400">
                                {{ $suggestion }}
                            </button>
                        @endforeach
                    </div>

                    <form id="chatMessageForm" method="POST" action="{{ route('chat.messages.store', $conversation) }}" class="flex min-w-0 flex-col gap-3 sm:flex-row">
                        @csrf
                        <input id="messageInput" name="content" type="text" value="{{ old('content') }}" maxlength="4000" required placeholder="{{ $chatBlocked ? 'Gemini limit reached. Try again after reset.' : 'Ask a question about the selected documents' }}" @disabled($chatBlocked) class="min-w-0 flex-1 rounded-lg border border-slate-300 px-3 py-3 text-sm outline-none focus:border-teal-500 focus:ring-4 focus:ring-teal-100 disabled:cursor-not-allowed disabled:bg-slate-100 disabled:text-slate-400">
                        <button id="sendMessageButton" type="submit" @disabled($chatBlocked) class="rounded-lg bg-teal-600 px-5 py-3 text-sm font-semibold text-white shadow-sm shadow-teal-200 hover:bg-teal-700 disabled:cursor-not-allowed disabled:bg-teal-400">
                            <span data-send-label>Send</span>
                        </button>
                    </form>
                    @if ($chatBlocked)
                        <p id="chatFormStatus" class="mt-2 text-sm font-medium text-amber-700">{{ $chatBlockedMessage }}</p>
                    @else
                        <p id="chatFormStatus" class="mt-2 hidden text-sm font-medium"></p>
                    @endif
                    @error('content')
                        <p id="chatFormError" class="mt-2 text-sm text-rose-600">{{ $message }}</p>
                    @else
                        <p id="chatFormError" class="mt-2 hidden text-sm text-rose-600"></p>
                    @enderror
                </div>
            @else
                <div class="grid flex-1 place-items-center p-8 text-center">
                    <div class="max-w-md">
                        <div class="mx-auto grid size-12 place-items-center rounded-lg bg-teal-50 text-sm font-bold text-teal-700 ring-1 ring-teal-100">?</div>
                        <h2 class="mt-5 text-xl font-semibold tracking-tight text-slate-950">Create a conversation</h2>
                        <p class="mt-2 text-sm leading-6 text-slate-500">Choose one document, several ready documents, or all ready documents before asking questions.</p>
                        <button type="button" data-open-conversation-modal class="mt-5 rounded-lg bg-teal-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm shadow-teal-200 hover:bg-teal-700">
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
                        <input name="title" type="text" value="{{ old('title') }}" placeholder="Example: Contract renewal questions" class="mt-2 w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm outline-none focus:border-teal-500 focus:ring-4 focus:ring-teal-100">
                        @error('title')
                            <p class="mt-2 text-sm text-rose-600">{{ $message }}</p>
                        @enderror
                    </label>

                    <fieldset class="mt-5">
                        <legend class="text-sm font-medium text-slate-700">Document selection mode</legend>
                        <div class="mt-2 grid gap-3 sm:grid-cols-2">
                            <label class="flex cursor-pointer gap-3 rounded-lg border border-slate-200 p-4 hover:bg-slate-50">
                                <input name="scope" value="selected" type="radio" class="mt-1 size-4 border-slate-300 text-teal-600 focus:ring-teal-500" @checked(old('scope', \App\Models\Conversation::SCOPE_SELECTED) === \App\Models\Conversation::SCOPE_SELECTED)>
                                <span>
                                    <span class="block text-sm font-semibold text-slate-950">Selected documents</span>
                                    <span class="mt-1 block text-sm text-slate-500">Pick one or more ready documents.</span>
                                </span>
                            </label>

                            <label class="flex cursor-pointer gap-3 rounded-lg border border-slate-200 p-4 hover:bg-slate-50">
                                <input id="allDocumentsScope" name="scope" value="all" type="radio" class="mt-1 size-4 border-slate-300 text-teal-600 focus:ring-teal-500" @checked(old('scope') === \App\Models\Conversation::SCOPE_ALL)>
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
                            <label class="flex items-center gap-2 text-sm font-semibold text-teal-700">
                                <input id="selectAllDocuments" type="checkbox" class="size-4 rounded border-slate-300 text-teal-600 focus:ring-teal-500">
                                <span>Select all documents</span>
                            </label>
                        </div>
                        <div class="mt-2 rounded-lg border border-slate-200">
                            <div class="max-h-72 divide-y divide-slate-100 overflow-y-auto">
                                @forelse ($documents as $document)
                                    <label class="flex items-start gap-3 px-4 py-3 text-sm hover:bg-slate-50">
                                        <input name="document_ids[]" value="{{ $document->id }}" type="checkbox" class="document-choice mt-1 size-4 rounded border-slate-300 text-teal-600 focus:ring-teal-500" @checked(in_array($document->id, old('document_ids', [])))>
                                        <span>
                                            <span class="block font-medium text-slate-950">{{ $document->displayTitle() }}</span>
                                            <span class="mt-1 block text-xs text-slate-500">{{ $document->total_pages ?? '-' }} pages - {{ $document->statusLabel() }}</span>
                                        </span>
                                    </label>
                                @empty
                                    <div class="px-4 py-6 text-sm text-slate-500">
                                        No ready documents yet. Upload and process documents before creating a document-scoped conversation.
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
                        <button type="submit" class="rounded-lg bg-teal-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm shadow-teal-200 hover:bg-teal-700">
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
            const conversationSearchForm = document.querySelector('[data-live-conversation-search]');
            const conversationSearchResults = conversationSearchForm ? document.querySelector(conversationSearchForm.dataset.resultsTarget) : null;
            const conversationSearchStatus = conversationSearchForm ? document.querySelector(conversationSearchForm.dataset.statusTarget) : null;
            const conversationSearchInput = conversationSearchForm?.querySelector('input[name="search"]');
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
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
            const quotaCard = document.getElementById('geminiQuotaCard');
            const formError = document.getElementById('chatFormError');
            const formStatus = document.getElementById('chatFormStatus');
            const conversationHeading = document.querySelector('[data-conversation-heading]');
            const conversationUpdated = document.querySelector('[data-conversation-updated]');
            let conversationSearchTimeout;
            let conversationSearchRequest;

            const scrollToBottom = () => {
                if (messageThread) {
                    requestAnimationFrame(() => {
                        messageThread.scrollTop = messageThread.scrollHeight;
                    });
                }
            };

            const escapeHtml = (value) => value
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');

            const userMessageHtml = (content) => `
                <article class="flex justify-end">
                    <div class="max-w-2xl">
                        <p class="mb-1 text-right text-xs font-semibold text-slate-500">You</p>
                        <div class="rounded-lg bg-teal-600 px-4 py-3 text-sm leading-6 text-white shadow-sm shadow-teal-200">
                            ${escapeHtml(content)}
                        </div>
                    </div>
                </article>
            `;

            const appendHtml = (html) => {
                const template = document.createElement('template');
                template.innerHTML = html.trim();
                const node = template.content.firstElementChild;

                if (node && messageThread) {
                    messageThread.appendChild(node);
                }

                return node;
            };

            const clearFormMessages = () => {
                if (formError) {
                    formError.textContent = '';
                    formError.classList.add('hidden');
                }
            };

            const showFormError = (message) => {
                if (formError) {
                    formError.textContent = message;
                    formError.classList.remove('hidden');
                }
            };

            const setSubmitting = (submitting) => {
                if (sendButton) {
                    sendButton.disabled = submitting;
                }

                if (sendLabel) {
                    sendLabel.textContent = submitting ? 'Sending...' : 'Send';
                }

                promptButtons.forEach((button) => {
                    button.disabled = submitting;
                });
            };

            const setChatAvailability = (quota) => {
                const blocked = Boolean(quota?.enabled && !quota?.can_ask);
                const message = quota?.blocked_message || 'Gemini free-tier limit reached. Try again later.';

                if (messageInput) {
                    messageInput.disabled = blocked;
                    messageInput.placeholder = blocked ? 'Gemini limit reached. Try again after reset.' : 'Ask a question about the selected documents';
                }

                if (sendButton) {
                    sendButton.disabled = blocked;
                }

                promptButtons.forEach((button) => {
                    button.disabled = blocked;
                });

                if (formStatus) {
                    formStatus.textContent = blocked ? message : '';
                    formStatus.classList.toggle('hidden', !blocked);
                    formStatus.classList.toggle('text-amber-700', blocked);
                }
            };

            const updateConversationMeta = (conversation) => {
                if (!conversation) {
                    return;
                }

                if (conversationHeading) {
                    conversationHeading.textContent = conversation.title;
                }

                if (conversationUpdated) {
                    conversationUpdated.textContent = `Updated ${conversation.updated_label}`;
                }

                const activeConversationTitle = document.querySelector('[data-active-conversation-title]');
                const activeConversationMessageCount = document.querySelector('[data-active-conversation-message-count]');
                const activeConversationUpdated = document.querySelector('[data-active-conversation-updated]');

                if (activeConversationTitle) {
                    activeConversationTitle.textContent = conversation.title;
                }

                if (activeConversationMessageCount) {
                    activeConversationMessageCount.textContent = conversation.messages_label;
                }

                if (activeConversationUpdated) {
                    activeConversationUpdated.textContent = conversation.updated_label;
                }
            };

            openButtons.forEach((button) => {
                button.addEventListener('click', () => modal.classList.remove('hidden'));
            });

            document.addEventListener('click', (event) => {
                if (event.target.closest('[data-open-conversation-modal]')) {
                    modal.classList.remove('hidden');
                }
            });

            closeButtons.forEach((button) => {
                button.addEventListener('click', () => modal.classList.add('hidden'));
            });

            const buildConversationSearchUrl = () => {
                const url = new URL(conversationSearchForm.action, window.location.origin);
                const search = conversationSearchInput?.value.trim() || '';

                if (search !== '') {
                    url.searchParams.set('search', search);
                } else {
                    url.searchParams.delete('search');
                }

                return url;
            };

            const setConversationSearchStatus = (message) => {
                if (conversationSearchStatus) {
                    conversationSearchStatus.textContent = message;
                }
            };

            const loadConversationSearchResults = async (url) => {
                if (!conversationSearchResults) {
                    return;
                }

                conversationSearchRequest?.abort();
                conversationSearchRequest = new AbortController();
                setConversationSearchStatus('Searching');

                try {
                    const response = await fetch(url, {
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        signal: conversationSearchRequest.signal,
                    });

                    if (!response.ok) {
                        throw new Error('Search failed.');
                    }

                    const payload = await response.json();
                    conversationSearchResults.innerHTML = payload.html || '';
                    window.history.replaceState({}, '', url);
                    setConversationSearchStatus('Search results updated');
                } catch (error) {
                    if (error.name !== 'AbortError') {
                        setConversationSearchStatus('Search failed');
                    }
                }
            };

            const scheduleConversationSearch = () => {
                clearTimeout(conversationSearchTimeout);
                conversationSearchTimeout = setTimeout(() => loadConversationSearchResults(buildConversationSearchUrl()), 250);
            };

            conversationSearchForm?.addEventListener('submit', (event) => {
                event.preventDefault();
                loadConversationSearchResults(buildConversationSearchUrl());
            });

            conversationSearchInput?.addEventListener('input', scheduleConversationSearch);

            conversationSearchResults?.addEventListener('click', (event) => {
                const link = event.target.closest('[data-live-pagination] a');

                if (!link) {
                    return;
                }

                event.preventDefault();
                loadConversationSearchResults(new URL(link.href));
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

            messageForm?.addEventListener('submit', async (event) => {
                event.preventDefault();

                if (!messageInput || !messageThread || !loadingTemplate) {
                    messageForm.submit();
                    return;
                }

                const content = messageInput.value.trim();

                if (content === '') {
                    return;
                }

                clearFormMessages();
                setSubmitting(true);
                const formData = new FormData(messageForm);

                document.querySelector('[data-empty-chat-state]')?.remove();

                const userNode = appendHtml(userMessageHtml(content));
                messageInput.value = '';

                const loadingNode = loadingTemplate.content.firstElementChild.cloneNode(true);
                messageThread.appendChild(loadingNode);
                scrollToBottom();

                try {
                    const response = await fetch(messageForm.action, {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            ...(csrfToken ? {'X-CSRF-TOKEN': csrfToken} : {}),
                        },
                        body: formData,
                    });

                    const payload = await response.json();

                    if (!response.ok) {
                        const message = payload?.message || payload?.errors?.content?.[0] || 'Message could not be sent. Please try again.';
                        throw new Error(message);
                    }

                    if (payload.assistant_html) {
                        const template = document.createElement('template');
                        template.innerHTML = payload.assistant_html.trim();
                        loadingNode.replaceWith(template.content);
                    } else {
                        loadingNode.remove();
                    }

                    if (quotaCard && Object.prototype.hasOwnProperty.call(payload, 'quota_html')) {
                        quotaCard.innerHTML = payload.quota_html;
                    }

                    updateConversationMeta(payload.conversation);
                    setChatAvailability(payload.quota);
                } catch (error) {
                    userNode?.remove();
                    loadingNode.remove();
                    messageInput.value = content;
                    showFormError(error.message || 'Message could not be sent. Please try again.');
                } finally {
                    if (!messageInput.disabled) {
                        setSubmitting(false);
                        messageInput.focus();
                    } else if (sendLabel) {
                        sendLabel.textContent = 'Send';
                    }

                    scrollToBottom();
                }
            });

            scrollToBottom();
            window.addEventListener('load', scrollToBottom);
            setTimeout(scrollToBottom, 100);
        })();
    </script>
@endsection
