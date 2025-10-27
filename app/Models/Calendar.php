<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

class Calendar extends Model
{
    protected $fillable = [
        'tenant_id',
        'schedule_import_id',
        'service_id',
        'monday',
        'tuesday',
        'wednesday',
        'thursday',
        'friday',
        'saturday',
        'sunday',
        'start_date',
        'end_date',
    ];

    protected function casts(): array
    {
        return [
            'monday' => 'boolean',
            'tuesday' => 'boolean',
            'wednesday' => 'boolean',
            'thursday' => 'boolean',
            'friday' => 'boolean',
            'saturday' => 'boolean',
            'sunday' => 'boolean',
            'start_date' => 'date',
            'end_date' => 'date',
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

        static::creating(function (Calendar $calendar) {
            if (! $calendar->tenant_id && Auth::check()) {
                $calendar->tenant_id = Auth::user()->isSubAdmin()
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
}
