<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserLimit extends Model
{
    protected $fillable = [
        'user_id',
        'daily_chat_limit',
        'daily_embedding_limit',
        'monthly_upload_limit',
        'max_documents',
        'max_storage_mb',
        'max_file_size_mb',
        'is_unlimited',
        'allowed_mime_types',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'daily_chat_limit' => 'integer',
            'daily_embedding_limit' => 'integer',
            'monthly_upload_limit' => 'integer',
            'max_documents' => 'integer',
            'max_storage_mb' => 'integer',
            'max_file_size_mb' => 'integer',
            'is_unlimited' => 'boolean',
            'allowed_mime_types' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
