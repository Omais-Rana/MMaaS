<?php

namespace App\Jobs;

use App\Models\GbfsSource;
use App\Services\GbfsIntegrationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SyncSingleGbfsSourceJob implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public GbfsSource $source
    ) {}

    /**
     * Execute the job.
     */
    public function handle(GbfsIntegrationService $service): void
    {
        if ($this->source->is_active) {
            $service->syncSingleSource($this->source);
        }
    }
}
