<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SyncSingleGbfsSourceJob implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public \App\Models\GbfsSource $source
    ) {
    }

    /**
     * Execute the job.
     */
    public function handle(\App\Services\GbfsIntegrationService $service): void
    {
        if ($this->source->is_active) {
            $service->syncSingleSource($this->source);
        }
    }
}
