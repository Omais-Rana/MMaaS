<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Facades\Auth;

class ScheduleImport extends Model
{
    use LogsActivity;

    protected $fillable = [
        'tenant_id',
        'filename',
        'status',
        'error_message',
        'total_routes',
        'total_stops',
        'total_trips',
    ];

    protected function casts(): array
    {
        return [
            //
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

        static::creating(function (ScheduleImport $import) {
            if (! $import->tenant_id && Auth::check()) {
                $import->tenant_id = Auth::user()->isSubAdmin()
                    ? Auth::id()
                    : Auth::user()->tenant_id;
            }
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tenant_id');
    }

    public function routes(): HasMany
    {
        return $this->hasMany(Route::class);
    }

    public function stops(): HasMany
    {
        return $this->hasMany(Stop::class);
    }

    public function trips(): HasMany
    {
        return $this->hasMany(Trip::class);
    }

    public function calendars(): HasMany
    {
        return $this->hasMany(Calendar::class);
    }

    public function calendarDates(): HasMany
    {
        return $this->hasMany(CalendarDate::class);
    }

    public function stopTimes(): HasManyThrough
    {
        return $this->hasManyThrough(StopTime::class, Trip::class);
    }
}
