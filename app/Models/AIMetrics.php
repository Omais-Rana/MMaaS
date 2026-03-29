<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AIMetrics extends Model
{
    /** @use HasFactory<\Database\Factories\AIMetricsFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'tenant_id',
        'conversation_log_id',
        'agent_type',
        'intent',
        'input_tokens',
        'output_tokens',
        'total_tokens',
        'estimated_cost',
        'latency_ms',
        'model_version',
        'temperature',
        'success',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'estimated_cost' => 'decimal:6',
            'temperature' => 'decimal:2',
            'success' => 'boolean',
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

    public function conversationLog(): BelongsTo
    {
        return $this->belongsTo(ConversationLog::class);
    }

    /**
     * Calculate cost based on token usage (Mistral pricing)
     * Adjust prices based on actual Mistral rates
     */
    public static function calculateCost(int $inputTokens, int $outputTokens): float
    {
        // Mistral Large pricing (approximate as of March 2026)
        $inputCostPer1kTokens = 0.002;
        $outputCostPer1kTokens = 0.006;

        return ($inputTokens / 1000) * $inputCostPer1kTokens + ($outputTokens / 1000) * $outputCostPer1kTokens;
    }
}
