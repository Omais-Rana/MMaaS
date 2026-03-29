<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VectorDocument extends Model
{
    /** @use HasFactory<\Database\Factories\VectorDocumentFactory> */
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'source_type',
        'source_id',
        'content_chunk',
        'content_hash',
        'embedding',
        'embedding_model',
        'metadata',
        'chunk_index',
        'similarity_threshold',
    ];

    protected function casts(): array
    {
        return [
            'embedding' => 'array',
            'metadata' => 'array',
            'similarity_threshold' => 'decimal:2',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tenant_id');
    }

    /**
     * Scope: Get documents for a specific tenant
     */
    public function scopeForTenant($query, ?int $tenantId)
    {
        if (! $tenantId) {
            return $query->whereNull('tenant_id');
        }

        return $query->where('tenant_id', $tenantId)->orWhereNull('tenant_id');
    }

    /**
     * Scope: Get documents by source type
     */
    public function scopeBySourceType($query, string $sourceType)
    {
        return $query->where('source_type', $sourceType);
    }
}
