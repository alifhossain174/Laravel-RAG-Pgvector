<?php

namespace App\Services;

use App\Exceptions\GeminiRateLimitExceededException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class GeminiRateLimitService
{
    public function consumeOrFail(string $model, int $tokens, string $label): void
    {
        $status = $this->statusForModel($model, $label);

        if (! $status['limited']) {
            return;
        }

        $tokens = max(0, $tokens);

        if ($status['minute']['requests']['remaining'] < 1) {
            throw new GeminiRateLimitExceededException($this->message($label, 'minute request', $status['minute']['resets_at']));
        }

        if ($status['day']['requests']['remaining'] < 1) {
            throw new GeminiRateLimitExceededException($this->message($label, 'daily request', $status['day']['resets_at']));
        }

        if ($tokens > 0 && $status['minute']['tokens']['limit'] !== null && $status['minute']['tokens']['remaining'] < $tokens) {
            throw new GeminiRateLimitExceededException($this->message($label, 'minute token', $status['minute']['resets_at']));
        }

        $this->increment($this->minuteRequestKey($model), 1, $this->secondsUntilNextMinute());
        $this->increment($this->dayRequestKey($model), 1, $this->secondsUntilNextDay());

        if ($tokens > 0) {
            $this->increment($this->minuteTokenKey($model), $tokens, $this->secondsUntilNextMinute());
        }
    }

    public function chatQuestionCheck(string $question): array
    {
        $embeddingModel = (string) config('services.gemini.embedding_model', 'gemini-embedding-2');
        $chatModel = (string) config('services.gemini.chat_model', 'gemini-2.5-flash');
        $embeddingTokens = $this->estimateTextTokens($question);
        $chatTokens = $this->estimatedChatTokens($question);

        $embedding = $this->availabilityFor($embeddingModel, $embeddingTokens, 'Gemini Embedding');
        $chat = $this->availabilityFor($chatModel, $chatTokens, 'Gemini chat');

        if (! $embedding['allowed']) {
            return $embedding;
        }

        if (! $chat['allowed']) {
            return $chat;
        }

        return [
            'allowed' => true,
            'message' => null,
            'retry_at' => null,
        ];
    }

    public function chatSnapshot(): array
    {
        $embeddingStatus = $this->statusForModel(
            (string) config('services.gemini.embedding_model', 'gemini-embedding-2'),
            'Gemini Embedding'
        );
        $chatStatus = $this->statusForModel(
            (string) config('services.gemini.chat_model', 'gemini-2.5-flash'),
            'Gemini chat'
        );

        $canAsk = $this->availabilityFromStatus($embeddingStatus, 1, 'Gemini Embedding');

        if ($canAsk['allowed']) {
            $canAsk = $this->availabilityFromStatus($chatStatus, $this->estimatedChatTokens(''), 'Gemini chat');
        }

        return [
            'enabled' => $this->enabled(),
            'can_ask' => $canAsk['allowed'],
            'blocked_message' => $canAsk['message'],
            'embedding' => $embeddingStatus,
            'chat' => $chatStatus,
        ];
    }

    public function statusForModel(string $model, string $label): array
    {
        $limits = $this->limitsForModel($model);
        $now = now();

        if (! $this->enabled() || $limits === []) {
            return [
                'label' => $label,
                'model' => $model,
                'limited' => false,
            ];
        }

        $minuteRequests = (int) Cache::get($this->minuteRequestKey($model), 0);
        $minuteTokens = (int) Cache::get($this->minuteTokenKey($model), 0);
        $dayRequests = (int) Cache::get($this->dayRequestKey($model), 0);

        return [
            'label' => $label,
            'model' => $model,
            'limited' => true,
            'minute' => [
                'resets_at' => $now->copy()->addMinute()->startOfMinute(),
                'requests' => $this->usage($minuteRequests, $limits['rpm'] ?? null),
                'tokens' => $this->usage($minuteTokens, $limits['tpm'] ?? null),
            ],
            'day' => [
                'resets_at' => $now->copy()->addDay()->startOfDay(),
                'requests' => $this->usage($dayRequests, $limits['rpd'] ?? null),
            ],
        ];
    }

    public function estimateTextTokens(string $text): int
    {
        return max(1, (int) ceil(mb_strlen($text, 'UTF-8') / 4));
    }

    private function estimatedChatTokens(string $question): int
    {
        $contextTokens = (int) ceil(((int) config('services.rag.max_context_chars', 24000)) / 4);
        $outputTokens = (int) config('services.llm.max_output_tokens', 3000);

        return $this->estimateTextTokens($question) + $contextTokens + $outputTokens;
    }

    private function availabilityFor(string $model, int $tokens, string $label): array
    {
        return $this->availabilityFromStatus($this->statusForModel($model, $label), $tokens, $label);
    }

    private function availabilityFromStatus(array $status, int $tokens, string $label): array
    {
        if (! ($status['limited'] ?? false)) {
            return [
                'allowed' => true,
                'message' => null,
                'retry_at' => null,
            ];
        }

        if ($status['minute']['requests']['remaining'] < 1) {
            return $this->blocked($label, 'minute request', $status['minute']['resets_at']);
        }

        if ($status['day']['requests']['remaining'] < 1) {
            return $this->blocked($label, 'daily request', $status['day']['resets_at']);
        }

        if ($tokens > 0 && $status['minute']['tokens']['limit'] !== null && $status['minute']['tokens']['remaining'] < $tokens) {
            return $this->blocked($label, 'minute token', $status['minute']['resets_at']);
        }

        return [
            'allowed' => true,
            'message' => null,
            'retry_at' => null,
        ];
    }

    private function blocked(string $label, string $limit, Carbon $retryAt): array
    {
        return [
            'allowed' => false,
            'message' => $this->message($label, $limit, $retryAt),
            'retry_at' => $retryAt,
        ];
    }

    private function message(string $label, string $limit, Carbon $retryAt): string
    {
        return "{$label} {$limit} limit reached. Try again after ".$retryAt->format('M j, Y g:i A').'.';
    }

    private function usage(int $used, mixed $limit): array
    {
        $limit = $limit === null || $limit === '' ? null : (int) $limit;

        return [
            'used' => $used,
            'limit' => $limit,
            'remaining' => $limit === null ? null : max(0, $limit - $used),
            'percent' => $limit === null || $limit <= 0 ? 0 : min(100, (int) round(($used / $limit) * 100)),
        ];
    }

    private function limitsForModel(string $model): array
    {
        $models = config('services.gemini.rate_limits.models', []);
        $limits = is_array($models) && array_key_exists($model, $models) ? $models[$model] : [];

        return is_array($limits) ? $limits : [];
    }

    private function enabled(): bool
    {
        return (bool) config('services.gemini.rate_limits.enabled', true);
    }

    private function increment(string $key, int $amount, int $ttl): void
    {
        Cache::add($key, 0, $ttl);
        Cache::increment($key, $amount);
    }

    private function minuteRequestKey(string $model): string
    {
        return $this->key($model, 'rpm', now()->format('YmdHi'));
    }

    private function minuteTokenKey(string $model): string
    {
        return $this->key($model, 'tpm', now()->format('YmdHi'));
    }

    private function dayRequestKey(string $model): string
    {
        return $this->key($model, 'rpd', now()->format('Ymd'));
    }

    private function key(string $model, string $bucket, string $window): string
    {
        $project = Str::slug((string) config('services.gemini.rate_limits.project_key', config('app.name', 'laravel')));

        return 'gemini-rate-limit:'.$project.':'.Str::slug($model).':'.$bucket.':'.$window;
    }

    private function secondsUntilNextMinute(): int
    {
        return max(1, now()->diffInSeconds(now()->copy()->addMinute()->startOfMinute()->addSeconds(5)));
    }

    private function secondsUntilNextDay(): int
    {
        return max(1, now()->diffInSeconds(now()->copy()->addDay()->startOfDay()->addMinutes(5)));
    }
}
