<?php

namespace App\Console\Commands;

use App\Models\Stop;
use App\Services\StopCityPopulatorService;
use Illuminate\Console\Command;

class PopulateStopCities extends Command
{
    protected $signature = 'stops:populate-cities {--tenant_id=}';

    protected $description = 'Populate city names for stops using reverse geocoding efficiently';

    public function handle()
    {
        $query = Stop::withoutGlobalScopes()->whereNull('city');

        if ($this->option('tenant_id')) {
            $query->where('tenant_id', $this->option('tenant_id'));
        }

        $stops = $query->get();

        if ($stops->isEmpty()) {
            $this->info('All stops already have city information.');
            return 0;
        }

        $this->info("Processing {$stops->count()} stops...");

        $populator = new StopCityPopulatorService();
        $updated = $populator->populateCitiesForStops($stops);

        $this->info("Successfully updated {$updated} stops with city information.");

        return 0;
    }
}
