<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserPreference extends Model
{
    /** @use HasFactory<\Database\Factories\UserPreferenceFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'favorite_route_id',
        'favorite_origin_name',
        'favorite_destination_name',
        'preferred_times',
        'accessibility_required',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'preferred_times' => 'array',
            'accessibility_required' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
