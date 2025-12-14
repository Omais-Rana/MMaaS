<?php

namespace App\Jobs;

use App\Models\ScheduleImport;
use App\Models\Stop;
use App\Services\StopCityPopulatorService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class PopulateStopCities implements ShouldQueue
{
    use Queueable;

    public int $timeout = 1800; // 30 minutes for API calls

    public int $tries = 1; // Don't retry if it fails

    public function __construct(
        public ScheduleImport $scheduleImport
    ) {}

    public function handle(): void
    {
        try {
            Log::info('Starting background city population', [
                'schedule_import_id' => $this->scheduleImport->id,
            ]);

            // Get stops without city information from this import
            $stops = Stop::withoutGlobalScopes()
                ->where('schedule_import_id', $this->scheduleImport->id)
                ->whereNull('city')
                ->get();

            if ($stops->isEmpty()) {
                Log::info('No stops need city information');
                return;
            }

            $populator = new StopCityPopulatorService();
            $updated = $populator->populateCitiesForStops($stops);

            Log::info('Background city population completed', [
                'schedule_import_id' => $this->scheduleImport->id,
                'stops_updated' => $updated,
            ]);
        } catch (\Exception $e) {
            Log::error('Background city population failed', [
                'schedule_import_id' => $this->scheduleImport->id,
                'error' => $e->getMessage(),
            ]);
            // Don't throw - let the import succeed even if city population fails
        }
    }
}
