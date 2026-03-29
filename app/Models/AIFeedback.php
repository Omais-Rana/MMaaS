<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AIFeedback extends Model
{
    /** @use HasFactory<\Database\Factories\AIFeedbackFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'conversation_log_id',
        'is_helpful',
        'feedback_reason',
        'feedback_text',
    ];

    protected function casts(): array
    {
        return [
            'is_helpful' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function conversationLog(): BelongsTo
    {
        return $this->belongsTo(ConversationLog::class);
    }
}
