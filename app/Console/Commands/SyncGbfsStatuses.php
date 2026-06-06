<?php

namespace App\Console\Commands;

use App\Services\GbfsIntegrationService;
use Illuminate\Console\Command;

class SyncGbfsStatuses extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'gbfs:sync-statuses';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Synchronize live availability metrics from active GBFS sources and push to cache';

    /**
     * Execute the console command.
     */
    public function handle(GbfsIntegrationService $service): void
    {
        $service->syncStatuses();
    }
}
