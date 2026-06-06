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
    ) {
    }

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
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$this->model()}:generateContent";

        try {
            $response = Http::withHeaders([
                'x-goog-api-key' => $apiKey,
                'Content-Type' => 'application/json',
            ])
                ->timeout(45)
                ->retry(3, 750)
                ->post($url, [
                    'contents' => [
                        [
                            'role' => 'user',
                            'parts' => [
                                ['text' => $prompt],
                            ],
                        ],
                    ],
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

        $answer = $this->extractAnswer($response->json());

        return [
            'answer' => $answer,
            'provider' => $this->provider(),
            'model' => $this->model(),
            'raw' => [
                'usageMetadata' => $response->json('usageMetadata'),
                'finishReason' => $response->json('candidates.0.finishReason'),
            ],
        ];
    }

    public function provider(): string
    {
        return (string) config('services.llm.provider', 'gemini');
    }

    public function model(): string
    {
        return (string) config('services.gemini.chat_model', 'gemini-2.5-flash');
    }

    private function temperature(): float
    {
        return (float) config('services.llm.temperature', 0.2);
    }

    private function maxOutputTokens(): int
    {
        return (int) config('services.llm.max_output_tokens', 1200);
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

}
