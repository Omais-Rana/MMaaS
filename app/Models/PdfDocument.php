<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PdfDocument extends Model
{
    /** @use HasFactory<\Database\Factories\PdfDocumentFactory> */
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'title',
        'description',
        'file_path',
        'original_filename',
        'mime_type',
        'file_size',
        'total_chunks',
        'is_processed',
        'processed_at',
        'processing_error',
    ];

    protected function casts(): array
    {
        return [
            'is_processed' => 'boolean',
            'processed_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tenant_id');
    }

    public function vectorDocuments(): HasMany
    {
        return $this->hasMany(VectorDocument::class, 'source_id')
            ->where('source_type', 'pdf');
    }

    /**
     * Mark as processed and store chunks count
     */
    public function markAsProcessed(int $chunkCount): void
    {
        $this->update([
            'is_processed' => true,
            'total_chunks' => $chunkCount,
            'processed_at' => now(),
            'processing_error' => null,
        ]);
    }

    /**
     * Mark as failed and store error
     */
    public function markAsFailed(string $error): void
    {
        $this->update([
            'is_processed' => false,
            'processing_error' => $error,
        ]);
    }
}
