<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Auth;

class Trip extends Model
{
    protected $fillable = [
        'tenant_id',
        'route_id',
        'schedule_import_id',
        'trip_id',
        'service_id',
        'trip_headsign',
        'trip_short_name',
        'direction_id',
        'shape_id',
        'wheelchair_accessible',
    ];

    protected function casts(): array
    {
        return [
            'direction_id' => 'integer',
            'wheelchair_accessible' => 'integer',
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

        static::creating(function (Trip $trip) {
            if (! $trip->tenant_id && Auth::check()) {
                $trip->tenant_id = Auth::user()->isSubAdmin()
                    ? Auth::id()
                    : Auth::user()->tenant_id;
            }
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tenant_id');
    }

    public function route(): BelongsTo
    {
        return $this->belongsTo(Route::class);
    }

    public function scheduleImport(): BelongsTo
    {
        return $this->belongsTo(ScheduleImport::class);
    }

    public function stopTimes(): HasMany
    {
        return $this->hasMany(StopTime::class)->orderBy('stop_sequence');
    }
}
