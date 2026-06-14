<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AiUsageLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminUsageLogController extends Controller
{
    public function index(Request $request): View
    {
        $validated = $request->validate([
            'user' => ['nullable', 'integer', 'exists:users,id'],
            'action_type' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', 'in:success,failed'],
            'provider' => ['nullable', 'string', 'max:255'],
            'model' => ['nullable', 'string', 'max:255'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ]);

        $query = AiUsageLog::query()
            ->with(['user:id,name,email', 'document:id,ulid,title,original_filename', 'conversation:id,ulid,title'])
            ->latest();

        if (! empty($validated['user'])) {
            $query->where('user_id', $validated['user']);
        }

        foreach (['action_type', 'status', 'provider', 'model'] as $filter) {
            if (! empty($validated[$filter])) {
                $query->where($filter, $validated[$filter]);
            }
        }

        if (! empty($validated['date_from'])) {
            $query->whereDate('created_at', '>=', $validated['date_from']);
        }

        if (! empty($validated['date_to'])) {
            $query->whereDate('created_at', '<=', $validated['date_to']);
        }

        return view('admin.usage-logs.index', [
            'usageLogs' => $query->paginate(20)->withQueryString(),
            'users' => User::query()
                ->whereHas('aiUsageLogs')
                ->orderBy('name')
                ->get(['id', 'name', 'email']),
            'actionTypes' => AiUsageLog::query()->distinct()->orderBy('action_type')->pluck('action_type'),
            'providers' => AiUsageLog::query()->whereNotNull('provider')->distinct()->orderBy('provider')->pluck('provider'),
            'models' => AiUsageLog::query()->whereNotNull('model')->distinct()->orderBy('model')->pluck('model'),
            'filters' => [
                'user' => isset($validated['user']) ? (int) $validated['user'] : null,
                'action_type' => $validated['action_type'] ?? null,
                'status' => $validated['status'] ?? null,
                'provider' => $validated['provider'] ?? null,
                'model' => $validated['model'] ?? null,
                'date_from' => $validated['date_from'] ?? null,
                'date_to' => $validated['date_to'] ?? null,
            ],
            'summary' => [
                'total' => AiUsageLog::query()->count(),
                'failed' => AiUsageLog::query()->where('status', AiUsageLog::STATUS_FAILED)->count(),
                'input_tokens' => (int) AiUsageLog::query()->sum('input_tokens'),
                'output_tokens' => (int) AiUsageLog::query()->sum('output_tokens'),
                'embeddings' => (int) AiUsageLog::query()->sum('embedding_count'),
            ],
        ]);
    }

    public function show(AiUsageLog $aiUsageLog): View
    {
        $aiUsageLog->load(['user:id,name,email', 'document:id,ulid,title,original_filename', 'conversation:id,ulid,title', 'message:id,role']);

        return view('admin.usage-logs.show', [
            'usageLog' => $aiUsageLog,
            'metadataJson' => json_encode($this->sanitizeArray($aiUsageLog->metadata ?? []), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
            'errorPreview' => $this->sanitizeString($aiUsageLog->error_message),
        ]);
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

            if (preg_match('/api[_-]?key|token|secret|password|payload|file_path|absolute_path|path/i', $key) === 1) {
                $sanitized[$key] = '[hidden]';
            } elseif (is_array($value)) {
                $sanitized[$key] = $this->sanitizeArray($value);
            } elseif (is_string($value)) {
                $sanitized[$key] = $this->sanitizeString($value);
            } else {
                $sanitized[$key] = $value;
            }
        }

        return $sanitized;
    }

    private function sanitizeString(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = preg_replace('/\b[A-Za-z]:[\\\\\/][^\s]+/u', '[path hidden]', $value) ?? $value;
        $value = preg_replace('/(?<!:)\/(?:[^\s\/]+\/)+[^\s]+/u', '[path hidden]', $value) ?? $value;
        $value = preg_replace('/(?i)(api[_-]?key|token|secret|password)(\s*[:=]\s*)[^\s,;]+/u', '$1$2[hidden]', $value) ?? $value;

        return str($value)->limit(1000)->toString();
    }
}
