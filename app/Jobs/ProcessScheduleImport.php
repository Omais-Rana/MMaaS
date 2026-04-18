<?php

namespace App\Jobs;

use App\Models\ScheduleImport;
use App\Services\GtfsParserService;
use Exception;
use Filament\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ProcessScheduleImport implements ShouldQueue
{
    use Queueable;

    public int $timeout = 600; // 10 minutes

    public int $tries = 3;

    public function __construct(
        public ScheduleImport $scheduleImport,
        public string $filePath
    ) {}

    public function handle(): void
    {
        // Disable time limit and increase memory for large GTFS files
        set_time_limit(0);
        ini_set('memory_limit', '512M');

        try {
            $this->scheduleImport->update([
                'status' => 'processing',
            ]);

            $parser = new GtfsParserService($this->scheduleImport);
            $stats = $parser->parse($this->filePath);

            $this->scheduleImport->update([
                'status' => 'completed',
                'total_routes' => $stats['routes'],
                'total_stops' => $stats['stops'],
                'total_trips' => $stats['trips'],
                'total_shapes' => $stats['shapes'],
            ]);

            Cache::forget('route_shapes:all');
            Cache::forget('route_shapes:tenant:'.$this->scheduleImport->tenant_id);
            Cache::forget('territories:list');

            $this->sendSuccessNotification($stats);
        } catch (Exception $e) {
            Log::error('Schedule import failed', [
                'import_id' => $this->scheduleImport->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->scheduleImport->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            $this->sendFailureNotification($e->getMessage());

            throw $e;
        }
    }

    protected function sendSuccessNotification(array $stats): void
    {
        Notification::make()
            ->title('Schedule Import Completed')
            ->body("Successfully processed {$stats['routes']} routes, {$stats['stops']} stops, {$stats['trips']} trips, and {$stats['shapes']} shapes. New records added and existing records updated.")
            ->success()
            ->sendToDatabase($this->scheduleImport->tenant);
    }

    protected function sendFailureNotification(string $error): void
    {
        Notification::make()
            ->title('Schedule Import Failed')
            ->body("The schedule import failed: {$error}")
            ->danger()
            ->sendToDatabase($this->scheduleImport->tenant);
    }

    public function failed(Exception $exception): void
    {
        Log::error('Schedule import job failed permanently', [
            'import_id' => $this->scheduleImport->id,
            'error' => $exception->getMessage(),
        ]);

        $this->scheduleImport->update([
            'status' => 'failed',
            'error_message' => $exception->getMessage(),
        ]);

        $this->sendFailureNotification($exception->getMessage());
    }
}
