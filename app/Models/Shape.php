<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Shape extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'schedule_import_id',
        'shape_id',
        'shape_pt_lat',
        'shape_pt_lon',
        'shape_pt_sequence',
        'shape_dist_traveled',
    ];

    protected function casts(): array
    {
        return [
            'shape_pt_lat' => 'float',
            'shape_pt_lon' => 'float',
            'shape_pt_sequence' => 'integer',
            'shape_dist_traveled' => 'float',
        ];
    }

    public function scheduleImport(): BelongsTo
    {
        return $this->belongsTo(ScheduleImport::class);
    }
}
