<?php

namespace App\Models;

use App\Jobs\SyncSingleGbfsSourceJob;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Auth;

class GbfsSource extends Model
{
    protected $fillable = [
        'tenant_id',
        'name',
        'url',
        'type',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
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

        static::creating(function (GbfsSource $source) {
            if (! $source->tenant_id && Auth::check()) {
                $source->tenant_id = Auth::user()->isSubAdmin()
                    ? Auth::id()
                    : Auth::user()->tenant_id;
            }
        });

        static::saved(function (GbfsSource $source) {
            if ($source->wasRecentlyCreated || $source->wasChanged('url') || $source->wasChanged('is_active')) {
                SyncSingleGbfsSourceJob::dispatch($source)->afterResponse();
            }
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tenant_id');
    }

    public function stations(): HasMany
    {
        return $this->hasMany(GbfsStation::class);
    }
}
