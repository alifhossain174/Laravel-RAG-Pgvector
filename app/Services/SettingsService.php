<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class SettingsService
{
    private const CACHE_KEY = 'app_settings.values';

    /**
     * @return array<string, array<string, mixed>>
     */
    public function definitions(): array
    {
        return [
            'uploads_enabled' => $this->definition('Platform Controls', 'Uploads enabled', 'Allow users to upload documents.', 'boolean', true),
            'chat_enabled' => $this->definition('Platform Controls', 'Chat enabled', 'Allow users to create conversations and send chat messages.', 'boolean', true),
            'registration_enabled' => $this->definition('Platform Controls', 'Registration enabled', 'Allow public user registration.', 'boolean', true),

            'max_file_size_mb' => $this->definition('Upload Limits', 'Max file size MB', 'Maximum upload size for each document.', 'integer', 20, ['min' => 1, 'max' => 100000]),
            'allowed_mime_types' => $this->definition('Upload Limits', 'Allowed MIME types', 'One MIME type per line. Only app-supported document types are accepted.', 'array', $this->supportedUploadMimeTypes()),

            'rag_top_k' => $this->definition('RAG Settings', 'RAG top K', 'Default number of chunks retrieved for focused questions.', 'integer', (int) config('services.rag.top_k', 6), ['min' => 1, 'max' => 20]),
            'rag_summary_top_k' => $this->definition('RAG Settings', 'Summary top K', 'Minimum retrieval limit for broad summary questions.', 'integer', (int) config('services.rag.summary_top_k', 12), ['min' => 1, 'max' => 20]),
            'rag_max_context_chars' => $this->definition('RAG Settings', 'Max context chars', 'Maximum retrieved context characters sent to the chat model.', 'integer', (int) config('services.rag.max_context_chars', 24000), ['min' => 1000, 'max' => 200000]),
            'rag_retrieval_max_distance' => $this->definition('RAG Settings', 'Retrieval max distance', 'Optional pgvector distance cutoff. Leave blank to disable cutoff.', 'decimal', config('services.rag.retrieval_max_distance'), ['nullable' => true, 'min' => 0, 'max' => 2]),
            'message_rate_limit_per_minute' => $this->definition('RAG Settings', 'Message rate limit per minute', 'Per-user/IP chat message throttle. Zero disables this throttle.', 'integer', (int) config('services.rag.message_rate_limit_per_minute', 20), ['min' => 0, 'max' => 1000]),

            'embedding_model' => $this->definition('AI Settings', 'Embedding model', 'Gemini embedding model name. API keys remain in .env.', 'string', (string) config('services.gemini.embedding_model', 'gemini-embedding-2'), ['max' => 255]),
            'embedding_dimensions' => $this->definition('AI Settings', 'Embedding dimensions', 'Output dimensionality for Gemini embeddings.', 'integer', (int) config('services.gemini.embedding_dimensions', 1536), ['min' => 1, 'max' => 4096]),
            'chat_model' => $this->definition('AI Settings', 'Chat model', 'Gemini chat model name. API keys remain in .env.', 'string', (string) config('services.gemini.chat_model', 'gemini-2.5-flash'), ['max' => 255]),
            'llm_temperature' => $this->definition('AI Settings', 'LLM temperature', 'Generation temperature for chat responses.', 'decimal', (float) config('services.llm.temperature', 0.2), ['min' => 0, 'max' => 2]),
            'max_output_tokens' => $this->definition('AI Settings', 'Max output tokens', 'Maximum output tokens for each chat model response.', 'integer', (int) config('services.llm.max_output_tokens', 3000), ['min' => 1, 'max' => 200000]),

            'ocr_enabled' => $this->definition('OCR Settings', 'OCR enabled', 'Allow OCR fallback for scanned PDFs.', 'boolean', (bool) config('services.ocr.enabled', true)),
            'ocr_min_text_characters' => $this->definition('OCR Settings', 'Minimum text characters', 'Native PDF extraction below this character count triggers OCR.', 'integer', (int) config('services.ocr.minimum_text_characters', 20), ['min' => 1, 'max' => 100000]),
            'ocr_min_text_density_per_page' => $this->definition('OCR Settings', 'Minimum text density per page', 'Native PDF extraction below this per-page density triggers OCR.', 'integer', (int) config('services.ocr.minimum_text_density_per_page', 10), ['min' => 1, 'max' => 100000]),
            'ocr_pdf_dpi' => $this->definition('OCR Settings', 'OCR PDF DPI', 'DPI used when converting PDF pages to images for OCR.', 'integer', (int) config('services.ocr.pdf_dpi', 200), ['min' => 72, 'max' => 600]),
            'ocr_language' => $this->definition('OCR Settings', 'OCR language', 'Tesseract language code or language list.', 'string', (string) config('services.ocr.language', 'eng'), ['max' => 100]),

            'default_daily_chat_limit' => $this->definition('Default User Limits', 'Daily chat limit', 'Default daily chat quota for users without a custom limit row.', 'integer', LimitService::DEFAULTS['daily_chat_limit'], ['min' => 0, 'max' => 1000000]),
            'default_daily_embedding_limit' => $this->definition('Default User Limits', 'Daily embedding limit', 'Default daily embedding quota for users without a custom limit row.', 'integer', LimitService::DEFAULTS['daily_embedding_limit'], ['min' => 0, 'max' => 100000000]),
            'default_monthly_upload_limit' => $this->definition('Default User Limits', 'Monthly upload limit', 'Default monthly upload quota for users without a custom limit row.', 'integer', LimitService::DEFAULTS['monthly_upload_limit'], ['min' => 0, 'max' => 1000000]),
            'default_max_documents' => $this->definition('Default User Limits', 'Max documents', 'Default stored document limit for users without a custom limit row.', 'integer', LimitService::DEFAULTS['max_documents'], ['min' => 0, 'max' => 1000000]),
            'default_max_storage_mb' => $this->definition('Default User Limits', 'Max storage MB', 'Default storage quota for users without a custom limit row.', 'integer', LimitService::DEFAULTS['max_storage_mb'], ['min' => 0, 'max' => 10000000]),
            'default_max_file_size_mb' => $this->definition('Default User Limits', 'Max file size MB', 'Default per-file quota for users without a custom limit row.', 'integer', LimitService::DEFAULTS['max_file_size_mb'], ['min' => 0, 'max' => 100000]),
        ];
    }

    /**
     * @return array<string, array<int, array<string, mixed>>>
     */
    public function groupedSettings(): array
    {
        return collect($this->definitions())
            ->map(function (array $definition, string $key): array {
                $definition['key'] = $key;
                $definition['value'] = $this->get($key);
                $definition['field_value'] = $this->fieldValue($definition['value'], $definition['type']);

                return $definition;
            })
            ->groupBy('group')
            ->all();
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function validationRules(): array
    {
        $rules = [];

        foreach ($this->definitions() as $key => $definition) {
            $rules["settings.{$key}"] = $this->rulesForDefinition($definition);
        }

        return $rules;
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    public function update(array $settings): void
    {
        foreach ($this->definitions() as $key => $definition) {
            $value = $this->normalizeValue($key, $settings[$key] ?? null, $definition);

            Setting::query()->updateOrCreate(
                ['key' => $key],
                [
                    'value' => json_encode($value),
                    'type' => $definition['type'],
                    'group' => $definition['group'],
                    'label' => $definition['label'],
                    'description' => $definition['description'],
                    'is_public' => (bool) $definition['is_public'],
                ]
            );
        }

        $this->clearCache();
    }

    public function clearCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    public function get(string $key): mixed
    {
        $definition = $this->definitions()[$key] ?? null;

        if (! $definition) {
            return null;
        }

        return $this->values()[$key] ?? $definition['default'];
    }

    public function uploadsEnabled(): bool
    {
        return (bool) $this->get('uploads_enabled');
    }

    public function chatEnabled(): bool
    {
        return (bool) $this->get('chat_enabled');
    }

    public function registrationEnabled(): bool
    {
        return (bool) $this->get('registration_enabled');
    }

    public function maxUploadMegabytes(): int
    {
        return max(1, (int) $this->get('max_file_size_mb'));
    }

    public function maxUploadKilobytes(): int
    {
        return $this->maxUploadMegabytes() * 1024;
    }

    /**
     * @return array<int, string>
     */
    public function allowedUploadMimeTypes(): array
    {
        $allowed = $this->normalizeMimeTypeList($this->get('allowed_mime_types'));
        $supported = $this->supportedUploadMimeTypes();

        return array_values(array_intersect($supported, $allowed === [] ? $supported : $allowed));
    }

    /**
     * @return array<int, string>
     */
    public function allowedUploadExtensions(): array
    {
        $allowedMimes = $this->allowedUploadMimeTypes();

        return collect($this->supportedUploadTypes())
            ->filter(fn (array $type): bool => array_intersect($type['mime_types'], $allowedMimes) !== [])
            ->pluck('extension')
            ->unique()
            ->values()
            ->all();
    }

    public function allowedUploadLabel(): string
    {
        return collect($this->supportedUploadTypes())
            ->filter(fn (array $type): bool => in_array($type['extension'], $this->allowedUploadExtensions(), true))
            ->pluck('label')
            ->unique()
            ->join(', ', ' or ');
    }

    public function ragTopK(): int
    {
        return max(1, min((int) $this->get('rag_top_k'), 20));
    }

    public function ragSummaryTopK(): int
    {
        return max(1, min((int) $this->get('rag_summary_top_k'), 20));
    }

    public function ragMaxContextChars(): int
    {
        return max(1000, (int) $this->get('rag_max_context_chars'));
    }

    public function ragRetrievalMaxDistance(): ?float
    {
        $value = $this->get('rag_retrieval_max_distance');

        return $value === null || $value === '' ? null : (float) $value;
    }

    public function messageRateLimitPerMinute(): int
    {
        return max(0, (int) $this->get('message_rate_limit_per_minute'));
    }

    public function embeddingModel(): string
    {
        return (string) $this->get('embedding_model');
    }

    public function embeddingDimensions(): int
    {
        return max(1, (int) $this->get('embedding_dimensions'));
    }

    public function chatModel(): string
    {
        return (string) $this->get('chat_model');
    }

    public function llmTemperature(): float
    {
        return max(0.0, min((float) $this->get('llm_temperature'), 2.0));
    }

    public function maxOutputTokens(): int
    {
        return max(1, (int) $this->get('max_output_tokens'));
    }

    public function ocrEnabled(): bool
    {
        return (bool) $this->get('ocr_enabled');
    }

    public function ocrMinimumTextCharacters(): int
    {
        return max(1, (int) $this->get('ocr_min_text_characters'));
    }

    public function ocrMinimumTextDensityPerPage(): int
    {
        return max(1, (int) $this->get('ocr_min_text_density_per_page'));
    }

    public function ocrPdfDpi(): int
    {
        return max(72, (int) $this->get('ocr_pdf_dpi'));
    }

    public function ocrLanguage(): string
    {
        return (string) $this->get('ocr_language');
    }

    /**
     * @return array<string, mixed>
     */
    public function defaultUserLimits(): array
    {
        return [
            'daily_chat_limit' => (int) $this->get('default_daily_chat_limit'),
            'daily_embedding_limit' => (int) $this->get('default_daily_embedding_limit'),
            'monthly_upload_limit' => (int) $this->get('default_monthly_upload_limit'),
            'max_documents' => (int) $this->get('default_max_documents'),
            'max_storage_mb' => (int) $this->get('default_max_storage_mb'),
            'max_file_size_mb' => (int) $this->get('default_max_file_size_mb'),
        ];
    }

    /**
     * @return array<int, array{extension: string, label: string, mime_types: array<int, string>}>
     */
    private function supportedUploadTypes(): array
    {
        return [
            ['extension' => 'pdf', 'label' => 'PDF', 'mime_types' => ['application/pdf']],
            ['extension' => 'docx', 'label' => 'DOCX', 'mime_types' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document']],
            ['extension' => 'xlsx', 'label' => 'XLSX', 'mime_types' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']],
            ['extension' => 'csv', 'label' => 'CSV', 'mime_types' => ['text/csv', 'application/csv', 'text/plain', 'application/vnd.ms-excel']],
        ];
    }

    /**
     * @return array<int, string>
     */
    private function supportedUploadMimeTypes(): array
    {
        return collect($this->supportedUploadTypes())
            ->flatMap(fn (array $type): array => $type['mime_types'])
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function values(): array
    {
        if (! Schema::hasTable('app_settings')) {
            return [];
        }

        return Cache::rememberForever(self::CACHE_KEY, function (): array {
            return Setting::query()
                ->pluck('value', 'key')
                ->mapWithKeys(fn (?string $value, string $key): array => [$key => $this->decodeValue($value)])
                ->all();
        });
    }

    private function decodeValue(?string $value): mixed
    {
        if ($value === null || $value === '') {
            return null;
        }

        $decoded = json_decode($value, true);

        return json_last_error() === JSON_ERROR_NONE ? $decoded : $value;
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    private function definition(string $group, string $label, string $description, string $type, mixed $default, array $options = []): array
    {
        return [
            'group' => $group,
            'label' => $label,
            'description' => $description,
            'type' => $type,
            'default' => $default,
            'is_public' => false,
            'options' => $options,
        ];
    }

    /**
     * @return array<int, string>
     */
    private function rulesForDefinition(array $definition): array
    {
        $type = $definition['type'];
        $options = $definition['options'];

        return match ($type) {
            'boolean' => ['nullable', 'boolean'],
            'integer' => ['required', 'integer', 'min:'.($options['min'] ?? 0), 'max:'.($options['max'] ?? 1000000)],
            'decimal' => array_values(array_filter([
                ($options['nullable'] ?? false) ? 'nullable' : 'required',
                'numeric',
                isset($options['min']) ? 'min:'.$options['min'] : null,
                isset($options['max']) ? 'max:'.$options['max'] : null,
            ])),
            'array' => ['nullable', 'string', 'max:5000'],
            default => ['required', 'string', 'max:'.($options['max'] ?? 255)],
        };
    }

    private function normalizeValue(string $key, mixed $value, array $definition): mixed
    {
        return match ($definition['type']) {
            'boolean' => (bool) $value,
            'integer' => (int) $value,
            'decimal' => $value === null || $value === '' ? null : (float) $value,
            'array' => $this->normalizeArraySetting($key, $value),
            default => trim((string) $value),
        };
    }

    private function normalizeArraySetting(string $key, mixed $value): array
    {
        $values = $this->normalizeMimeTypeList($value);

        if ($key === 'allowed_mime_types') {
            $unsupported = array_values(array_diff($values, $this->supportedUploadMimeTypes()));

            if ($unsupported !== []) {
                throw ValidationException::withMessages([
                    "settings.{$key}" => 'Unsupported MIME type: '.$unsupported[0],
                ]);
            }
        }

        return $values;
    }

    /**
     * @return array<int, string>
     */
    private function normalizeMimeTypeList(mixed $value): array
    {
        if (is_array($value)) {
            $items = $value;
        } else {
            $items = preg_split('/[\s,]+/', (string) $value) ?: [];
        }

        return collect($items)
            ->map(fn (mixed $item): string => strtolower(trim((string) $item)))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function fieldValue(mixed $value, string $type): mixed
    {
        if ($type === 'array') {
            return implode("\n", is_array($value) ? $value : []);
        }

        return $value;
    }
}
