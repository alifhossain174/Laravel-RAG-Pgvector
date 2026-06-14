<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class EmbeddingService
{
    public function __construct(
        private readonly GeminiRateLimitService $rateLimits,
        private readonly SettingsService $settings,
    ) {}

    public function embedText(string $text): array
    {
        return $this->embed($text);
    }

    public function embedQuery(string $query): array
    {
        return $this->embed($query);
    }

    public function dimensions(): int
    {
        return $this->settings->embeddingDimensions();
    }

    public function provider(): string
    {
        return (string) config('services.embedding.provider', 'gemini');
    }

    public function model(): string
    {
        return $this->settings->embeddingModel();
    }

    private function embed(string $input): array
    {
        if ($this->provider() !== 'gemini') {
            throw new RuntimeException("Unsupported embedding provider [{$this->provider()}].");
        }

        $text = $this->normalizeInput($input);

        if ($text === '') {
            throw new RuntimeException('Cannot embed empty text.');
        }

        $apiKey = config('services.gemini.api_key');

        if (! is_string($apiKey) || trim($apiKey) === '') {
            throw new RuntimeException('Gemini API key is missing. Set GEMINI_API_KEY in your environment.');
        }

        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$this->model()}:embedContent";
        $this->rateLimits->consumeOrFail(
            model: $this->model(),
            tokens: $this->rateLimits->estimateTextTokens($text),
            label: 'Gemini Embedding'
        );

        try {
            $response = Http::withHeaders([
                'x-goog-api-key' => $apiKey,
                'Content-Type' => 'application/json',
            ])
                ->timeout(30)
                ->retry(3, 500)
                ->post($url, [
                    'content' => [
                        'parts' => [
                            ['text' => $text],
                        ],
                    ],
                    'output_dimensionality' => $this->dimensions(),
                ]);
        } catch (Throwable $exception) {
            Log::error('Gemini embedding request failed before receiving a response.', [
                'provider' => $this->provider(),
                'model' => $this->model(),
                'message' => $exception->getMessage(),
            ]);

            throw new RuntimeException('Gemini embedding request failed: '.$exception->getMessage(), previous: $exception);
        }

        if (! $response->successful()) {
            Log::error('Gemini embedding request returned an error response.', [
                'provider' => $this->provider(),
                'model' => $this->model(),
                'status' => $response->status(),
                'body' => str($response->body())->limit(1000)->toString(),
            ]);

            throw new RuntimeException("Gemini embedding request failed with HTTP {$response->status()}.", $response->status());
        }

        $values = $response->json('embedding.values');

        if (! is_array($values)) {
            throw new RuntimeException('Gemini embedding response did not contain embedding values.');
        }

        if (count($values) !== $this->dimensions()) {
            throw new RuntimeException(sprintf(
                'Gemini embedding dimension mismatch. Expected %d values, received %d.',
                $this->dimensions(),
                count($values)
            ));
        }

        return array_map(function (mixed $value): float {
            if (! is_numeric($value)) {
                throw new RuntimeException('Gemini embedding response contained a non-numeric value.');
            }

            return (float) $value;
        }, $values);
    }

    private function normalizeInput(string $input): string
    {
        $input = str_replace(["\r\n", "\r"], "\n", $input);
        $input = preg_replace('/[ \t]+/u', ' ', $input) ?? $input;
        $input = preg_replace('/ *\n */u', "\n", $input) ?? $input;
        $input = preg_replace("/\n{3,}/u", "\n\n", $input) ?? $input;

        return trim($input);
    }
}
