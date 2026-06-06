<?php

namespace App\Console\Commands;

use App\Services\GbfsIntegrationService;
use Illuminate\Console\Command;

class SyncGbfsStations extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'gbfs:sync-stations';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Synchronize static station information from active GBFS sources';

    /**
     * Execute the console command.
     */
    public function handle(GbfsIntegrationService $service): void
    {
        $this->info('Starting GBFS station synchronization...');

        $service->syncStations();

        $this->info('GBFS station synchronization completed.');
    }
}
