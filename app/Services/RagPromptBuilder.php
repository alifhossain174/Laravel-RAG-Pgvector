<?php

namespace App\Services;

class RagPromptBuilder
{
    /**
     * @param  array<int, array<string, mixed>>  $retrievedChunks
     * @param  array<int, array{role?: string, content?: string}>  $conversationHistory
     */
    public function build(string $question, array $retrievedChunks, array $conversationHistory = []): string
    {
        return implode("\n\n", [
            $this->systemInstruction(),
            "Recent conversation history:\n".$this->formatConversationHistory($conversationHistory),
            "Selected document context:\n".$this->formatRetrievedChunks($retrievedChunks),
            "Question:\n".trim($question),
        ]);
    }

    public function systemInstruction(): string
    {
        return <<<TEXT
You are a concise document question-answering assistant.

Rules:
- Answer only from the selected document context.
- Do not invent facts or use outside knowledge.
- If the context does not answer the question, say: "I could not find this information in the selected documents."
- Include citations where possible using [Document Title, page X] or [Document Title, pages X-Y].
- If page information is missing, cite the document title only.
- Be concise but helpful.
- Prefer bullet points for summaries, lists, deadlines, requirements, and action items.
- Format answers in GitHub-flavored Markdown.
- Use compact Markdown tables when the answer compares items, lists fields, or presents structured results.
- Keep table cells short and include citations in the relevant row or bullet when possible.
- Always finish the final section; do not end with an empty heading or unfinished bullet.
- For document summaries, provide a complete but compact overview with key findings and recommended actions.
TEXT;
    }

    /**
     * @param  array<int, array<string, mixed>>  $retrievedChunks
     */
    private function formatRetrievedChunks(array $retrievedChunks): string
    {
        if ($retrievedChunks === []) {
            return 'No document context was retrieved.';
        }

        return collect($retrievedChunks)
            ->values()
            ->map(function (array $chunk, int $index): string {
                $documentTitle = (string) ($chunk['document_title'] ?? 'Untitled document');
                $pageLabel = $this->pageLabel($chunk['page_start'] ?? null, $chunk['page_end'] ?? null);
                $score = array_key_exists('score', $chunk) ? (string) $chunk['score'] : 'unknown';
                $content = trim((string) ($chunk['content'] ?? ''));

                return sprintf(
                    "[Context %d]\nDocument: %s\nPage range: %s\nRelevance score: %s\nContent:\n%s",
                    $index + 1,
                    $documentTitle,
                    $pageLabel,
                    $score,
                    $content
                );
            })
            ->implode("\n\n");
    }

    /**
     * @param  array<int, array{role?: string, content?: string}>  $conversationHistory
     */
    private function formatConversationHistory(array $conversationHistory): string
    {
        if ($conversationHistory === []) {
            return 'No recent messages.';
        }

        return collect($conversationHistory)
            ->take(-8)
            ->map(function (array $message): string {
                $role = strtolower((string) ($message['role'] ?? 'message'));
                $content = str((string) ($message['content'] ?? ''))->squish()->limit(700)->toString();

                return ucfirst($role).': '.$content;
            })
            ->implode("\n");
    }

    private function pageLabel(mixed $pageStart, mixed $pageEnd): string
    {
        if ($pageStart === null && $pageEnd === null) {
            return 'Page unknown';
        }

        if ($pageStart !== null && ($pageEnd === null || (int) $pageStart === (int) $pageEnd)) {
            return 'page '.(int) $pageStart;
        }

        return 'pages '.(int) $pageStart.'-'.(int) $pageEnd;
    }
}
