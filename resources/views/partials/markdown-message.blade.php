@php
    $markdownContent = trim((string) ($content ?? ''));
    $renderedMarkdown = \Illuminate\Support\Str::markdown($markdownContent, [
        'html_input' => 'strip',
        'allow_unsafe_links' => false,
    ]);
@endphp

<div class="rag-markdown">
    {!! $renderedMarkdown !!}
</div>
