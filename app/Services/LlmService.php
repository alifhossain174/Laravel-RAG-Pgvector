<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class LlmService
{
    public function __construct(
        private readonly RagPromptBuilder $promptBuilder,
        private readonly GeminiRateLimitService $rateLimits,
        private readonly SettingsService $settings,
    ) {}

    /**
     * @param  array<int, array{
     *     chunk_id?: int,
     *     document_id?: int,
     *     document_title?: string,
     *     page_start?: int|null,
     *     page_end?: int|null,
     *     content?: string,
     *     score?: float
     * }>  $retrievedChunks
     * @param  array<int, array{role?: string, content?: string}>  $conversationHistory
     * @return array{answer: string, provider: string, model: string, raw?: array<string, mixed>}
     */
    public function answerWithContext(string $question, array $retrievedChunks, array $conversationHistory = []): array
    {
        if ($this->provider() !== 'gemini') {
            throw new RuntimeException("Unsupported LLM provider [{$this->provider()}].");
        }

        $question = trim($question);

        if ($question === '') {
            throw new RuntimeException('Cannot answer an empty question.');
        }

        $apiKey = config('services.gemini.api_key');

        if (! is_string($apiKey) || trim($apiKey) === '') {
            throw new RuntimeException('Gemini API key is missing. Set GEMINI_API_KEY in your environment.');
        }

        $prompt = $this->promptBuilder->build($question, $retrievedChunks, $conversationHistory);
        $contents = [
            $this->textContent('user', $prompt),
        ];

        $payload = $this->sendGenerateContentRequest($apiKey, $contents);
        $answer = $this->extractAnswer($payload);
        $finishReason = $this->finishReason($payload);
        $continuationUsed = false;
        $continuationFinishReason = null;
        $truncated = $finishReason === 'MAX_TOKENS';

        for ($attempt = 0; $truncated && $attempt < $this->continuationAttempts(); $attempt++) {
            $continuationUsed = true;

            try {
                $continuationPayload = $this->sendGenerateContentRequest($apiKey, [
                    $this->textContent('user', $prompt),
                    $this->textContent('model', $answer),
                    $this->textContent('user', $this->continuationInstruction()),
                ]);

                $answer = $this->joinAnswerParts($answer, $this->extractAnswer($continuationPayload));
                $continuationFinishReason = $this->finishReason($continuationPayload);
                $truncated = $continuationFinishReason === 'MAX_TOKENS';
            } catch (Throwable $exception) {
                Log::warning('Gemini chat continuation failed after max token stop.', [
                    'provider' => $this->provider(),
                    'model' => $this->model(),
                    'message' => $exception->getMessage(),
                ]);

                break;
            }
        }

        if ($truncated) {
            $answer = $this->joinAnswerParts(
                $answer,
                '> This answer reached the model output limit and may be incomplete. Ask a narrower follow-up question for the remaining details.'
            );
        }

        return [
            'answer' => $answer,
            'provider' => $this->provider(),
            'model' => $this->model(),
            'raw' => [
                'usageMetadata' => $payload['usageMetadata'] ?? null,
                'finishReason' => $finishReason,
                'continuationFinishReason' => $continuationFinishReason,
                'continuationUsed' => $continuationUsed,
                'truncated' => $truncated,
            ],
        ];
    }

    public function provider(): string
    {
        return (string) config('services.llm.provider', 'gemini');
    }

    public function model(): string
    {
        return $this->settings->chatModel();
    }

    private function temperature(): float
    {
        return $this->settings->llmTemperature();
    }

    private function maxOutputTokens(): int
    {
        return $this->settings->maxOutputTokens();
    }

    private function continuationAttempts(): int
    {
        return max(0, min((int) config('services.llm.continuation_attempts', 1), 2));
    }

    /**
     * @param  array<int, array{role: string, parts: array<int, array{text: string}>}>  $contents
     * @return array<string, mixed>
     */
    private function sendGenerateContentRequest(string $apiKey, array $contents): array
    {
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$this->model()}:generateContent";
        $this->rateLimits->consumeOrFail(
            model: $this->model(),
            tokens: $this->estimateRequestTokens($contents),
            label: 'Gemini chat'
        );

        try {
            $response = Http::withHeaders([
                'x-goog-api-key' => $apiKey,
                'Content-Type' => 'application/json',
            ])
                ->timeout(60)
                ->retry(3, 750)
                ->post($url, [
                    'contents' => $contents,
                    'generationConfig' => [
                        'temperature' => $this->temperature(),
                        'maxOutputTokens' => $this->maxOutputTokens(),
                    ],
                ]);
        } catch (Throwable $exception) {
            Log::error('Gemini chat request failed before receiving a response.', [
                'provider' => $this->provider(),
                'model' => $this->model(),
                'message' => $exception->getMessage(),
            ]);

            throw new RuntimeException('Gemini chat request failed: '.$exception->getMessage(), previous: $exception);
        }

        if (! $response->successful()) {
            Log::error('Gemini chat request returned an error response.', [
                'provider' => $this->provider(),
                'model' => $this->model(),
                'status' => $response->status(),
                'body' => str($response->body())->limit(1000)->toString(),
            ]);

            throw new RuntimeException("Gemini chat request failed with HTTP {$response->status()}.", $response->status());
        }

        $payload = $response->json();

        if (! is_array($payload)) {
            throw new RuntimeException('Gemini chat response was not a valid JSON object.');
        }

        return $payload;
    }

    private function estimateRequestTokens(array $contents): int
    {
        $text = collect($contents)
            ->flatMap(fn (array $content): array => $content['parts'] ?? [])
            ->map(fn (array $part): string => (string) ($part['text'] ?? ''))
            ->implode("\n");

        return $this->rateLimits->estimateTextTokens($text) + $this->maxOutputTokens();
    }

    /**
     * @return array{role: string, parts: array<int, array{text: string}>}
     */
    private function textContent(string $role, string $text): array
    {
        return [
            'role' => $role,
            'parts' => [
                ['text' => $text],
            ],
        ];
    }

    private function continuationInstruction(): string
    {
        return 'Continue the answer from exactly where it stopped. Do not repeat earlier content. Complete any unfinished heading, bullet list, or table. Keep using citations from the selected document context.';
    }

    private function joinAnswerParts(string $answer, string $continuation): string
    {
        return trim($answer)."\n\n".trim($continuation);
    }

    private function extractAnswer(array $payload): string
    {
        $parts = data_get($payload, 'candidates.0.content.parts');

        if (! is_array($parts)) {
            throw new RuntimeException('Gemini chat response did not contain answer parts.');
        }

        $answer = collect($parts)
            ->map(fn (mixed $part): string => is_array($part) ? (string) ($part['text'] ?? '') : '')
            ->filter()
            ->implode("\n");

        if (trim($answer) === '') {
            throw new RuntimeException('Gemini chat response was empty.');
        }

        return trim($answer);
    }

    private function finishReason(array $payload): ?string
    {
        $finishReason = data_get($payload, 'candidates.0.finishReason');

        return is_string($finishReason) ? $finishReason : null;
    }
}
