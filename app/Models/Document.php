<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Document extends Model
{
    use HasFactory;

    public const STATUS_UPLOADED = 'uploaded';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_TEXT_EXTRACTED = 'text_extracted';
    public const STATUS_CHUNKED = 'chunked';
    public const STATUS_EMBEDDED = 'embedded';
    public const STATUS_READY = 'ready';
    public const STATUS_FAILED = 'failed';

    public const STATUSES = [
        self::STATUS_UPLOADED,
        self::STATUS_PROCESSING,
        self::STATUS_TEXT_EXTRACTED,
        self::STATUS_CHUNKED,
        self::STATUS_EMBEDDED,
        self::STATUS_READY,
        self::STATUS_FAILED,
    ];

    protected $fillable = [
        'ulid',
        'user_id',
        'title',
        'description',
        'original_filename',
        'file_path',
        'mime_type',
        'file_size',
        'status',
        'total_pages',
        'total_chunks',
        'processed_at',
        'failed_reason',
    ];

    protected function casts(): array
    {
        return [
            'file_size' => 'integer',
            'total_pages' => 'integer',
            'total_chunks' => 'integer',
            'processed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Document $document): void {
            if (blank($document->ulid)) {
                $document->ulid = (string) Str::ulid();
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

    public function chunks(): HasMany
    {
        return $this->hasMany(DocumentChunk::class)->orderBy('chunk_index');
    }

    public function documentChunks(): HasMany
    {
        return $this->chunks();
    }

    public function conversations(): BelongsToMany
    {
        return $this->belongsToMany(Conversation::class, 'conversation_documents')
            ->withTimestamps();
    }

    public function displayTitle(): string
    {
        return $this->title ?: $this->original_filename;
    }

    public function formattedFileSize(): string
    {
        if ($this->file_size === null) {
            return '-';
        }

        $units = ['B', 'KB', 'MB', 'GB'];
        $size = (float) $this->file_size;
        $unit = 0;

        while ($size >= 1024 && $unit < count($units) - 1) {
            $size /= 1024;
            $unit++;
        }

        return sprintf('%s %s', number_format($size, $unit === 0 ? 0 : 1), $units[$unit]);
    }

    public function statusLabel(): string
    {
        return str($this->status)->replace('_', ' ')->title()->toString();
    }
}
