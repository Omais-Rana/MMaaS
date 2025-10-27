<?php

namespace App\Services;

use App\Models\Calendar;
use App\Models\CalendarDate;
use App\Models\Route;
use App\Models\ScheduleImport;
use App\Models\Stop;
use App\Models\StopTime;
use App\Models\Trip;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

class GtfsParserService
{
    protected ScheduleImport $import;

    protected string $extractPath;

    protected array $stats = [
        'routes' => 0,
        'stops' => 0,
        'trips' => 0,
    ];

    public function __construct(ScheduleImport $import)
    {
        $this->import = $import;
        $this->extractPath = storage_path('app/temp/gtfs-' . uniqid());
    }

    public function parse(string $filePath): array
    {
        try {
            $this->extractZip($filePath);

            DB::beginTransaction();

            $this->parseRoutes();
            $this->parseStops();
            $this->parseTrips();
            $this->parseStopTimes();
            $this->parseCalendar();
            $this->parseCalendarDates();

            DB::commit();

            $this->cleanup();

            return $this->stats;
        } catch (Exception $e) {
            DB::rollBack();
            $this->cleanup();

            throw $e;
        }
    }

    protected function extractZip(string $filePath): void
    {
        $fullPath = Storage::disk('local')->path($filePath);

        if (! file_exists($fullPath)) {
            throw new Exception("File not found: {$filePath}");
        }

        $zip = new ZipArchive;

        if ($zip->open($fullPath) !== true) {
            throw new Exception('Failed to open ZIP file');
        }

        if (! is_dir($this->extractPath)) {
            mkdir($this->extractPath, 0755, true);
        }

        $zip->extractTo($this->extractPath);
        $zip->close();
    }

    protected function parseRoutes(): void
    {
        $file = $this->extractPath . '/routes.txt';

        if (! file_exists($file)) {
            Log::warning('routes.txt not found in GTFS archive');

            return;
        }

        $rows = $this->parseCsv($file);

        foreach ($rows as $row) {
            Route::create([
                'tenant_id' => $this->import->tenant_id,
                'schedule_import_id' => $this->import->id,
                'route_id' => $row['route_id'] ?? '',
                'route_short_name' => ! empty($row['route_short_name']) ? $row['route_short_name'] : null,
                'route_long_name' => $row['route_long_name'] ?? '',
                'route_type' => $row['route_type'] ?? '',
                'route_color' => ! empty($row['route_color']) ? $row['route_color'] : null,
                'route_text_color' => ! empty($row['route_text_color']) ? $row['route_text_color'] : null,
                'route_desc' => ! empty($row['route_desc']) ? $row['route_desc'] : null,
            ]);

            $this->stats['routes']++;
        }
    }

    protected function parseStops(): void
    {
        $file = $this->extractPath . '/stops.txt';

        if (! file_exists($file)) {
            Log::warning('stops.txt not found in GTFS archive');

            return;
        }

        $rows = $this->parseCsv($file);

        foreach ($rows as $row) {
            Stop::create([
                'tenant_id' => $this->import->tenant_id,
                'schedule_import_id' => $this->import->id,
                'stop_id' => $row['stop_id'] ?? '',
                'stop_name' => $row['stop_name'] ?? '',
                'stop_desc' => ! empty($row['stop_desc']) ? $row['stop_desc'] : null,
                'stop_lat' => ! empty($row['stop_lat']) ? (float) $row['stop_lat'] : 0,
                'stop_lon' => ! empty($row['stop_lon']) ? (float) $row['stop_lon'] : 0,
                'zone_id' => ! empty($row['zone_id']) ? $row['zone_id'] : null,
                'stop_code' => ! empty($row['stop_code']) ? $row['stop_code'] : null,
                'wheelchair_boarding' => isset($row['wheelchair_boarding']) && $row['wheelchair_boarding'] !== '' ? (int) $row['wheelchair_boarding'] : 2,
            ]);

            $this->stats['stops']++;
        }
    }

    protected function parseTrips(): void
    {
        $file = $this->extractPath . '/trips.txt';

        if (! file_exists($file)) {
            Log::warning('trips.txt not found in GTFS archive');

            return;
        }

        $rows = $this->parseCsv($file);

        foreach ($rows as $row) {
            $route = Route::where('tenant_id', $this->import->tenant_id)
                ->where('route_id', $row['route_id'] ?? '')
                ->first();

            if (! $route) {
                Log::warning("Route not found for trip: {$row['trip_id']}");

                continue;
            }

            Trip::create([
                'tenant_id' => $this->import->tenant_id,
                'route_id' => $route->id,
                'schedule_import_id' => $this->import->id,
                'trip_id' => $row['trip_id'] ?? '',
                'service_id' => $row['service_id'] ?? '',
                'trip_headsign' => ! empty($row['trip_headsign']) ? $row['trip_headsign'] : null,
                'trip_short_name' => ! empty($row['trip_short_name']) ? $row['trip_short_name'] : null,
                'direction_id' => ! empty($row['direction_id']) ? (int) $row['direction_id'] : 0,
                'wheelchair_accessible' => isset($row['wheelchair_accessible']) && $row['wheelchair_accessible'] !== '' ? (int) $row['wheelchair_accessible'] : 2,
            ]);

            $this->stats['trips']++;
        }
    }

    protected function parseStopTimes(): void
    {
        $file = $this->extractPath . '/stop_times.txt';

        if (! file_exists($file)) {
            Log::warning('stop_times.txt not found in GTFS archive');

            return;
        }

        $rows = $this->parseCsv($file);

        // Cache trips and stops to avoid repeated queries
        $tripCache = Trip::where('tenant_id', $this->import->tenant_id)
            ->get()
            ->keyBy('trip_id');

        $stopCache = Stop::where('tenant_id', $this->import->tenant_id)
            ->get()
            ->keyBy('stop_id');

        $batchData = [];
        $batchSize = 500;

        foreach ($rows as $row) {
            $trip = $tripCache->get($row['trip_id'] ?? '');
            $stop = $stopCache->get($row['stop_id'] ?? '');

            if (! $trip || ! $stop) {
                Log::warning("Trip or Stop not found for stop_time: {$row['trip_id']}");

                continue;
            }

            $batchData[] = [
                'tenant_id' => $this->import->tenant_id,
                'trip_id' => $trip->id,
                'stop_id' => $stop->id,
                'arrival_time' => $this->normalizeTime($row['arrival_time'] ?? '00:00:00'),
                'departure_time' => $this->normalizeTime($row['departure_time'] ?? '00:00:00'),
                'stop_sequence' => ! empty($row['stop_sequence']) ? (int) $row['stop_sequence'] : 0,
                'stop_headsign' => ! empty($row['stop_headsign']) ? $row['stop_headsign'] : null,
                'pickup_type' => ! empty($row['pickup_type']) ? (int) $row['pickup_type'] : 0,
                'drop_off_type' => ! empty($row['drop_off_type']) ? (int) $row['drop_off_type'] : 0,
                'created_at' => now(),
                'updated_at' => now(),
            ];

            // Insert in batches
            if (count($batchData) >= $batchSize) {
                StopTime::insert($batchData);
                $batchData = [];
            }
        }

        // Insert remaining records
        if (! empty($batchData)) {
            StopTime::insert($batchData);
        }
    }

    protected function parseCalendar(): void
    {
        $file = $this->extractPath . '/calendar.txt';

        if (! file_exists($file)) {
            Log::warning('calendar.txt not found in GTFS archive');

            return;
        }

        $rows = $this->parseCsv($file);

        foreach ($rows as $row) {
            Calendar::create([
                'tenant_id' => $this->import->tenant_id,
                'schedule_import_id' => $this->import->id,
                'service_id' => $row['service_id'] ?? '',
                'monday' => ($row['monday'] ?? '0') === '1',
                'tuesday' => ($row['tuesday'] ?? '0') === '1',
                'wednesday' => ($row['wednesday'] ?? '0') === '1',
                'thursday' => ($row['thursday'] ?? '0') === '1',
                'friday' => ($row['friday'] ?? '0') === '1',
                'saturday' => ($row['saturday'] ?? '0') === '1',
                'sunday' => ($row['sunday'] ?? '0') === '1',
                'start_date' => $this->parseDate($row['start_date'] ?? ''),
                'end_date' => $this->parseDate($row['end_date'] ?? ''),
            ]);
        }
    }

    protected function parseCalendarDates(): void
    {
        $file = $this->extractPath . '/calendar_dates.txt';

        if (! file_exists($file)) {
            Log::warning('calendar_dates.txt not found in GTFS archive');

            return;
        }

        $rows = $this->parseCsv($file);

        foreach ($rows as $row) {
            CalendarDate::create([
                'tenant_id' => $this->import->tenant_id,
                'schedule_import_id' => $this->import->id,
                'service_id' => $row['service_id'] ?? '',
                'date' => $this->parseDate($row['date'] ?? ''),
                'exception_type' => ! empty($row['exception_type']) ? (int) $row['exception_type'] : 1,
            ]);
        }
    }

    protected function parseCsv(string $file): array
    {
        $rows = [];
        $headers = [];

        if (($handle = fopen($file, 'r')) !== false) {
            $headers = fgetcsv($handle);

            while (($data = fgetcsv($handle)) !== false) {
                if (count($headers) === count($data)) {
                    $rows[] = array_combine($headers, $data);
                }
            }

            fclose($handle);
        }

        return $rows;
    }

    protected function normalizeTime(string $time): string
    {
        // GTFS allows times > 24:00:00 for trips that continue past midnight
        if (preg_match('/^(\d+):(\d+):(\d+)$/', $time, $matches)) {
            $hours = (int) $matches[1];
            $minutes = (int) $matches[2];
            $seconds = (int) $matches[3];

            // Convert times over 24 hours to valid time format
            if ($hours >= 24) {
                $hours = $hours % 24;
            }

            return sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);
        }

        return '00:00:00';
    }

    protected function parseDate(string $date): string
    {
        // GTFS dates are in YYYYMMDD format
        if (preg_match('/^(\d{4})(\d{2})(\d{2})$/', $date, $matches)) {
            return $matches[1] . '-' . $matches[2] . '-' . $matches[3];
        }

        return now()->format('Y-m-d');
    }

    protected function cleanup(): void
    {
        if (is_dir($this->extractPath)) {
            $this->deleteDirectory($this->extractPath);
        }
    }

    protected function deleteDirectory(string $dir): bool
    {
        if (! file_exists($dir)) {
            return true;
        }

        if (! is_dir($dir)) {
            return unlink($dir);
        }

        foreach (scandir($dir) as $item) {
            if ($item == '.' || $item == '..') {
                continue;
            }

            if (! $this->deleteDirectory($dir . DIRECTORY_SEPARATOR . $item)) {
                return false;
            }
        }

        return rmdir($dir);
    }
}
