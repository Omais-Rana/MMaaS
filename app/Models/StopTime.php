<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

class StopTime extends Model
{
    protected $fillable = [
        'tenant_id',
        'trip_id',
        'stop_id',
        'arrival_time',
        'departure_time',
        'stop_sequence',
        'stop_headsign',
        'pickup_type',
        'drop_off_type',
    ];

    protected function casts(): array
    {
        return [
            'stop_sequence' => 'integer',
            'pickup_type' => 'integer',
            'drop_off_type' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::addGlobalScope('tenant', function (Builder $query) {
            if (Auth::check() && Auth::user()->role !== 'superadmin') {
                $tenantId = Auth::user()->isSubAdmin()
                    ? Auth::id()
                    : Auth::user()->tenant_id;

                $query->where('tenant_id', $tenantId);
            }
        });

        static::creating(function (StopTime $stopTime) {
            if (! $stopTime->tenant_id && Auth::check()) {
                $stopTime->tenant_id = Auth::user()->isSubAdmin()
                    ? Auth::id()
                    : Auth::user()->tenant_id;
            }
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tenant_id');
    }

    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }

    public function stop(): BelongsTo
    {
        return $this->belongsTo(Stop::class);
    }
}
