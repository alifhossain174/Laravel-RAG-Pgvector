@if ($message->role === \App\Models\Message::ROLE_USER)
    <article class="flex justify-end">
        <div class="max-w-2xl">
            <p class="mb-1 text-right text-xs font-semibold text-slate-500">You</p>
            <div class="rounded-lg bg-teal-600 px-4 py-3 text-sm leading-6 text-white shadow-sm shadow-teal-200">
                {{ $message->content }}
            </div>
        </div>
    </article>
@else
    <article class="max-w-3xl">
        <p class="mb-1 text-xs font-semibold text-slate-500">Assistant</p>
        <div class="overflow-x-auto rounded-lg border border-slate-200 bg-white px-4 py-3 text-sm leading-6 text-slate-700 shadow-sm">
            @include('partials.markdown-message', ['content' => $message->content])
        </div>
        @if (($message->metadata['truncated'] ?? false) === true)
            <div class="mt-2 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs font-medium leading-5 text-amber-800">
                This answer reached the model output limit and may be incomplete. Ask a more focused follow-up for the remaining details.
            </div>
        @endif
        @php
            $sources = $message->metadata['sources'] ?? [];
        @endphp
        @if (($message->metadata['error'] ?? false) === true)
            <p class="mt-2 text-xs font-medium text-rose-700">Generation failed safely. Try again when the provider is available.</p>
        @elseif ($sources !== [])
            <details class="mt-3 rounded-lg border border-teal-100 bg-teal-50/40 p-3">
                <summary class="cursor-pointer text-sm font-semibold text-teal-800">
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
