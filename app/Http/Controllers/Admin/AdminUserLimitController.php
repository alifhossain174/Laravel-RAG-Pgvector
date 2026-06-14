<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AdminUserLimitController extends Controller
{
    public function update(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'daily_chat_limit' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'daily_embedding_limit' => ['nullable', 'integer', 'min:0', 'max:100000000'],
            'monthly_upload_limit' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'max_documents' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'max_storage_mb' => ['nullable', 'integer', 'min:0', 'max:10000000'],
            'max_file_size_mb' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'allowed_mime_types' => ['nullable', 'string', 'max:5000'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'is_unlimited' => ['nullable', 'boolean'],
        ]);

        $user->limit()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'daily_chat_limit' => $validated['daily_chat_limit'] ?? null,
                'daily_embedding_limit' => $validated['daily_embedding_limit'] ?? null,
                'monthly_upload_limit' => $validated['monthly_upload_limit'] ?? null,
                'max_documents' => $validated['max_documents'] ?? null,
                'max_storage_mb' => $validated['max_storage_mb'] ?? null,
                'max_file_size_mb' => $validated['max_file_size_mb'] ?? null,
                'is_unlimited' => (bool) ($validated['is_unlimited'] ?? false),
                'allowed_mime_types' => $this->mimeTypes($validated['allowed_mime_types'] ?? null),
                'notes' => $validated['notes'] ?? null,
            ]
        );

        return back()->with('success', 'User limits updated.');
    }

    private function mimeTypes(?string $value): ?array
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $mimeTypes = collect(preg_split('/[\s,]+/', $value) ?: [])
            ->map(fn (string $mimeType): string => strtolower(trim($mimeType)))
            ->filter()
            ->unique()
            ->values()
            ->all();

        return $mimeTypes === [] ? null : $mimeTypes;
    }
}
