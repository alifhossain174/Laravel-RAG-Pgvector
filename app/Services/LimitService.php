<?php

namespace App\Services;

use App\Models\AiUsageLog;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;

class LimitService
{
    public const DEFAULTS = [
        'daily_chat_limit' => 1000,
        'daily_embedding_limit' => 100000,
        'monthly_upload_limit' => 1000,
        'max_documents' => 10000,
        'max_storage_mb' => 10240,
        'max_file_size_mb' => 20,
        'allowed_mime_types' => null,
        'is_unlimited' => false,
    ];

    public function limitsFor(User $user): array
    {
        $limit = $user->relationLoaded('limit') ? $user->limit : $user->limit()->first();

        if (! $limit) {
            return self::DEFAULTS;
        }

        return [
            'daily_chat_limit' => $limit->daily_chat_limit ?? self::DEFAULTS['daily_chat_limit'],
            'daily_embedding_limit' => $limit->daily_embedding_limit ?? self::DEFAULTS['daily_embedding_limit'],
            'monthly_upload_limit' => $limit->monthly_upload_limit ?? self::DEFAULTS['monthly_upload_limit'],
            'max_documents' => $limit->max_documents ?? self::DEFAULTS['max_documents'],
            'max_storage_mb' => $limit->max_storage_mb ?? self::DEFAULTS['max_storage_mb'],
            'max_file_size_mb' => $limit->max_file_size_mb ?? self::DEFAULTS['max_file_size_mb'],
            'allowed_mime_types' => $limit->allowed_mime_types,
            'is_unlimited' => $limit->is_unlimited,
        ];
    }

    public function canUpload(User $user, UploadedFile $file): array
    {
        foreach ([
            $this->canCreateDocument($user),
            $this->canUseStorage($user, (int) $file->getSize()),
            $this->canUseFileSize($user, (int) $file->getSize()),
            $this->canUseMimeType($user, (string) $file->getClientMimeType()),
            $this->canUseMonthlyUpload($user),
        ] as $result) {
            if (! $result['allowed']) {
                return $result;
            }
        }

        return $this->allow();
    }

    public function canCreateDocument(User $user): array
    {
        $limits = $this->limitsFor($user);

        if ($limits['is_unlimited']) {
            return $this->allow();
        }

        $limit = (int) $limits['max_documents'];
        $current = $user->documents()->count();

        if ($limit > 0 && $current >= $limit) {
            return $this->deny("Document limit reached. Your account can store up to {$limit} documents.");
        }

        return $this->allow();
    }

    public function canUseStorage(User $user, int $additionalBytes = 0): array
    {
        $limits = $this->limitsFor($user);

        if ($limits['is_unlimited']) {
            return $this->allow();
        }

        $limitMb = (int) $limits['max_storage_mb'];
        $limitBytes = $limitMb * 1024 * 1024;
        $currentBytes = (int) $user->documents()->sum('file_size');

        if ($limitMb > 0 && ($currentBytes + $additionalBytes) > $limitBytes) {
            return $this->deny("Storage limit reached. Your account can use up to {$limitMb} MB.");
        }

        return $this->allow();
    }

    public function canSendChatMessage(User $user): array
    {
        $limits = $this->limitsFor($user);

        if ($limits['is_unlimited']) {
            return $this->allow();
        }

        $limit = (int) $limits['daily_chat_limit'];
        $used = $this->usageCount($user, 'chat_request');

        if ($limit > 0 && $used >= $limit) {
            return $this->deny("Daily chat limit reached. Your account can send {$limit} chat messages per day.");
        }

        return $this->allow();
    }

    public function canGenerateEmbeddings(User $user, int $embeddingCount = 1): array
    {
        $limits = $this->limitsFor($user);

        if ($limits['is_unlimited']) {
            return $this->allow();
        }

        $limit = (int) $limits['daily_embedding_limit'];
        $used = $this->embeddingUsageCount($user);

        if ($limit > 0 && ($used + max(1, $embeddingCount)) > $limit) {
            return $this->deny("Daily embedding limit reached. Your account can generate {$limit} embeddings per day.");
        }

        return $this->allow();
    }

    public function defaults(): array
    {
        return self::DEFAULTS;
    }

    private function canUseFileSize(User $user, int $bytes): array
    {
        $limits = $this->limitsFor($user);

        if ($limits['is_unlimited']) {
            return $this->allow();
        }

        $limitMb = (int) $limits['max_file_size_mb'];

        if ($limitMb > 0 && $bytes > ($limitMb * 1024 * 1024)) {
            return $this->deny("File size limit exceeded. Your account can upload files up to {$limitMb} MB.");
        }

        return $this->allow();
    }

    private function canUseMimeType(User $user, string $mimeType): array
    {
        $limits = $this->limitsFor($user);

        if ($limits['is_unlimited']) {
            return $this->allow();
        }

        $allowed = $limits['allowed_mime_types'];

        if (! is_array($allowed) || $allowed === []) {
            return $this->allow();
        }

        if (! in_array($mimeType, $allowed, true)) {
            return $this->deny('This file type is not enabled for your account.');
        }

        return $this->allow();
    }

    private function canUseMonthlyUpload(User $user): array
    {
        $limits = $this->limitsFor($user);

        if ($limits['is_unlimited']) {
            return $this->allow();
        }

        $limit = (int) $limits['monthly_upload_limit'];
        $used = $user->documents()
            ->where('created_at', '>=', now()->startOfMonth())
            ->count();

        if ($limit > 0 && $used >= $limit) {
            return $this->deny("Monthly upload limit reached. Your account can upload {$limit} documents per month.");
        }

        return $this->allow();
    }

    private function usageCount(User $user, string $actionType): int
    {
        if (! Schema::hasTable('ai_usage_logs')) {
            return 0;
        }

        return AiUsageLog::query()
            ->where('user_id', $user->id)
            ->where('action_type', $actionType)
            ->where('created_at', '>=', now()->startOfDay())
            ->count();
    }

    private function embeddingUsageCount(User $user): int
    {
        if (! Schema::hasTable('ai_usage_logs')) {
            return 0;
        }

        return (int) AiUsageLog::query()
            ->where('user_id', $user->id)
            ->where('action_type', 'embedding_generated')
            ->where('created_at', '>=', now()->startOfDay())
            ->sum('embedding_count');
    }

    private function allow(): array
    {
        return ['allowed' => true, 'message' => null];
    }

    private function deny(string $message): array
    {
        return ['allowed' => false, 'message' => $message];
    }
}
