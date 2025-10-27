<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

class CalendarDate extends Model
{
    protected $fillable = [
        'tenant_id',
        'schedule_import_id',
        'service_id',
        'date',
        'exception_type',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'exception_type' => 'integer',
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

        static::creating(function (CalendarDate $calendarDate) {
            if (! $calendarDate->tenant_id && Auth::check()) {
                $calendarDate->tenant_id = Auth::user()->isSubAdmin()
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
