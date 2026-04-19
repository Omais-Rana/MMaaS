<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RouteSearch extends Model
{
    protected $fillable = [
        'user_id',
        'origin_name',
        'destination_name',
        'search_time',
        'search_date',
    ];

    protected function casts(): array
    {
        return [
            'search_date' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
