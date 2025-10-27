<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Auth;

class Stop extends Model
{
    protected $fillable = [
        'tenant_id',
        'schedule_import_id',
        'stop_id',
        'stop_name',
        'stop_desc',
        'stop_lat',
        'stop_lon',
        'zone_id',
        'stop_code',
        'wheelchair_boarding',
    ];

    protected function casts(): array
    {
        return [
            'stop_lat' => 'decimal:8',
            'stop_lon' => 'decimal:8',
            'wheelchair_boarding' => 'integer',
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

        static::creating(function (Stop $stop) {
            if (! $stop->tenant_id && Auth::check()) {
                $stop->tenant_id = Auth::user()->isSubAdmin()
                    ? Auth::id()
                    : Auth::user()->tenant_id;
            }
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tenant_id');
    }

    public function scheduleImport(): BelongsTo
    {
        return $this->belongsTo(ScheduleImport::class);
    }

    public function stopTimes(): HasMany
    {
        return $this->hasMany(StopTime::class);
    }
}
