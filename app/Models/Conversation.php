<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Conversation extends Model
{
    use HasFactory;

    public const SCOPE_SELECTED = 'selected';
    public const SCOPE_ALL = 'all';

    public const SCOPES = [
        self::SCOPE_SELECTED,
        self::SCOPE_ALL,
    ];

    protected $fillable = [
        'ulid',
        'user_id',
        'title',
        'scope',
    ];

    protected static function booted(): void
    {
        static::creating(function (Conversation $conversation): void {
            if (blank($conversation->ulid)) {
                $conversation->ulid = (string) Str::ulid();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'ulid';
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function documents(): BelongsToMany
    {
        return $this->belongsToMany(Document::class, 'conversation_documents')
            ->withTimestamps();
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class)->orderBy('created_at');
    }

    public function usesAllDocuments(): bool
    {
        return $this->scope === self::SCOPE_ALL;
    }
}
