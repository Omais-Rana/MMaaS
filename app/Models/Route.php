<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Auth;

class Route extends Model
{
    protected $fillable = [
        'tenant_id',
        'schedule_import_id',
        'route_id',
        'route_short_name',
        'route_long_name',
        'route_type',
        'route_color',
        'route_text_color',
        'route_desc',
    ];

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

        static::creating(function (Route $route) {
            if (! $route->tenant_id && Auth::check()) {
                $route->tenant_id = Auth::user()->isSubAdmin()
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

    public function trips(): HasMany
    {
        return $this->hasMany(Trip::class);
    }
}
