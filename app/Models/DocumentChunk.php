<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentChunk extends Model
{
    use HasFactory;

    protected $fillable = [
        'document_id',
        'chunk_index',
        'page_start',
        'page_end',
        'content',
        'token_count',
        'metadata',
        'embedding_provider',
        'embedding_model',
        'embedded_at',
    ];

    protected function casts(): array
    {
        return [
            'chunk_index' => 'integer',
            'page_start' => 'integer',
            'page_end' => 'integer',
            'token_count' => 'integer',
            'metadata' => 'array',
            'embedded_at' => 'datetime',
        ];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function hasEmbedding(): bool
    {
        return $this->getAttribute('embedding') !== null;
    }
}
