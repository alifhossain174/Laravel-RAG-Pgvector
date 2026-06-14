<?php

namespace App\Services;

use App\Models\AiUsageLog;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

class UsageTrackingService
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function log(array $attributes): ?AiUsageLog
    {
        try {
            if (! Schema::hasTable('ai_usage_logs')) {
                return null;
            }

            return AiUsageLog::query()->create([
                'user_id' => $attributes['user_id'] ?? null,
                'document_id' => $attributes['document_id'] ?? null,
                'conversation_id' => $attributes['conversation_id'] ?? null,
                'message_id' => $attributes['message_id'] ?? null,
                'action_type' => (string) ($attributes['action_type'] ?? 'unknown'),
                'provider' => $this->nullableString($attributes['provider'] ?? null),
                'model' => $this->nullableString($attributes['model'] ?? null),
                'input_tokens' => $this->nullableInteger($attributes['input_tokens'] ?? null),
                'output_tokens' => $this->nullableInteger($attributes['output_tokens'] ?? null),
                'embedding_count' => $this->nullableInteger($attributes['embedding_count'] ?? null),
                'status' => (string) ($attributes['status'] ?? AiUsageLog::STATUS_SUCCESS),
                'error_message' => $this->sanitizeString($attributes['error_message'] ?? null, 1000),
                'metadata' => $this->sanitizeMetadata($attributes['metadata'] ?? null),
            ]);
        } catch (Throwable $exception) {
            Log::warning('AI usage logging failed.', [
                'action_type' => $attributes['action_type'] ?? null,
                'message' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return $this->sanitizeString($value, 255);
    }

    private function nullableInteger(mixed $value): ?int
    {
        return is_numeric($value) ? max(0, (int) $value) : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function sanitizeMetadata(mixed $metadata): ?array
    {
        if (! is_array($metadata)) {
            return null;
        }

        return $this->sanitizeArray($metadata);
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function sanitizeArray(array $values): array
    {
        $sanitized = [];

        foreach ($values as $key => $value) {
            $key = is_string($key) ? $key : (string) $key;

            if ($this->isSensitiveKey($key)) {
                $sanitized[$key] = '[hidden]';

                continue;
            }

            if (is_array($value)) {
                $sanitized[$key] = $this->sanitizeArray($value);
            } elseif (is_string($value)) {
                $sanitized[$key] = $this->sanitizeString($value, 500);
            } elseif (is_scalar($value) || $value === null) {
                $sanitized[$key] = $value;
            } else {
                $sanitized[$key] = '[unsupported]';
            }
        }

        return $sanitized;
    }

    private function sanitizeString(mixed $value, int $limit): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = (string) $value;
        $value = preg_replace('/\b[A-Za-z]:[\\\\\/][^\s]+/u', '[path hidden]', $value) ?? $value;
        $value = preg_replace('/(?<!:)\/(?:[^\s\/]+\/)+[^\s]+/u', '[path hidden]', $value) ?? $value;
        $value = preg_replace('/(?i)(api[_-]?key|token|secret|password)(\s*[:=]\s*)[^\s,;]+/u', '$1$2[hidden]', $value) ?? $value;

        return str($value)->limit($limit)->toString();
    }

    private function isSensitiveKey(string $key): bool
    {
        return preg_match('/api[_-]?key|token|secret|password|payload|file_path|absolute_path|path/i', $key) === 1;
    }
}
