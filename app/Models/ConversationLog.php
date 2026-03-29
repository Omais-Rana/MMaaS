<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConversationLog extends Model
{
    /** @use HasFactory<\Database\Factories\ConversationLogFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'tenant_id',
        'agent_type',
        'user_message',
        'intent',
        'intent_data',
        'agent_response',
        'tools_used',
        'retrieved_documents',
        'confidence_score',
        'tokens_used',
        'estimated_cost',
        'response_time_ms',
    ];

    protected function casts(): array
    {
        return [
            'intent_data' => 'array',
            'tools_used' => 'array',
            'retrieved_documents' => 'array',
            'confidence_score' => 'decimal:2',
            'estimated_cost' => 'decimal:6',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tenant_id');
    }
}
