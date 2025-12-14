<?php

namespace App\Services;

use App\Jobs\PopulateStopCities;
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

            // Queue city population to run in background (non-blocking)
            PopulateStopCities::dispatch($this->import);
            Log::info('City population job queued for background processing');

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
            // Validate required field
            if (empty($row['route_id'])) {
                throw new Exception('Invalid GTFS data: route_id is required but missing in routes.txt');
            }

            // Use updateOrCreate for smart merge
            Route::updateOrCreate(
                [
                    'tenant_id' => $this->import->tenant_id,
                    'schedule_import_id' => $this->import->id,
                    'route_id' => $row['route_id'],
                ],
                [
                    'route_short_name' => ! empty($row['route_short_name']) ? $row['route_short_name'] : null,
                    'route_long_name' => $row['route_long_name'] ?? '',
                    'route_type' => $row['route_type'] ?? '',
                    'route_color' => ! empty($row['route_color']) ? $row['route_color'] : null,
                    'route_text_color' => ! empty($row['route_text_color']) ? $row['route_text_color'] : null,
                    'route_desc' => ! empty($row['route_desc']) ? $row['route_desc'] : null,
                ]
            );

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
        $rowNumber = 1; // Start at 1 because header is row 0

        foreach ($rows as $row) {
            $rowNumber++;

            // Validate required field - trim and check
            $stopId = trim($row['stop_id'] ?? '');

            if ($stopId === '' || $stopId === null) {
                Log::warning('Skipping stop with empty stop_id', [
                    'row_number' => $rowNumber,
                    'stop_name' => $row['stop_name'] ?? 'unknown',
                ]);
                
                // Skip this stop instead of failing the entire import
                continue;
            }

            // Use updateOrCreate for smart merge
            Stop::updateOrCreate(
                [
                    'tenant_id' => $this->import->tenant_id,
                    'schedule_import_id' => $this->import->id,
                    'stop_id' => $stopId,
                ],
                [
                    'stop_name' => $row['stop_name'] ?? '',
                    'stop_desc' => ! empty($row['stop_desc']) ? $row['stop_desc'] : null,
                    'stop_lat' => ! empty($row['stop_lat']) ? (float) $row['stop_lat'] : 0,
                    'stop_lon' => ! empty($row['stop_lon']) ? (float) $row['stop_lon'] : 0,
                    'zone_id' => ! empty($row['zone_id']) ? $row['zone_id'] : null,
                    'stop_code' => ! empty($row['stop_code']) ? $row['stop_code'] : null,
                    'wheelchair_boarding' => isset($row['wheelchair_boarding']) && $row['wheelchair_boarding'] !== '' ? (int) $row['wheelchair_boarding'] : 2,
                ]
            );

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
            // Validate required field
            if (empty($row['trip_id'])) {
                throw new Exception('Invalid GTFS data: trip_id is required but missing in trips.txt');
            }

            $route = Route::where('tenant_id', $this->import->tenant_id)
                ->where('route_id', $row['route_id'] ?? '')
                ->first();

            if (! $route) {
                Log::warning("Route not found for trip: {$row['trip_id']}");

                continue;
            }

            // Use updateOrCreate for smart merge
            Trip::updateOrCreate(
                [
                    'tenant_id' => $this->import->tenant_id,
                    'schedule_import_id' => $this->import->id,
                    'trip_id' => $row['trip_id'],
                ],
                [
                    'route_id' => $route->id,
                    'service_id' => $row['service_id'] ?? '',
                    'trip_headsign' => ! empty($row['trip_headsign']) ? $row['trip_headsign'] : null,
                    'trip_short_name' => ! empty($row['trip_short_name']) ? $row['trip_short_name'] : null,
                    'direction_id' => ! empty($row['direction_id']) ? (int) $row['direction_id'] : 0,
                    'wheelchair_accessible' => isset($row['wheelchair_accessible']) && $row['wheelchair_accessible'] !== '' ? (int) $row['wheelchair_accessible'] : 2,
                ]
            );

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

        foreach ($rows as $row) {
            $trip = $tripCache->get($row['trip_id'] ?? '');
            $stop = $stopCache->get($row['stop_id'] ?? '');

            if (! $trip || ! $stop) {
                Log::warning("Trip or Stop not found for stop_time: {$row['trip_id']}");

                continue;
            }

            // Use updateOrCreate for smart merge
            // Stop times are unique by trip_id, stop_id, and stop_sequence
            StopTime::updateOrCreate(
                [
                    'tenant_id' => $this->import->tenant_id,
                    'trip_id' => $trip->id,
                    'stop_id' => $stop->id,
                    'stop_sequence' => ! empty($row['stop_sequence']) ? (int) $row['stop_sequence'] : 0,
                ],
                [
                    'arrival_time' => $this->normalizeTime($row['arrival_time'] ?? '00:00:00'),
                    'departure_time' => $this->normalizeTime($row['departure_time'] ?? '00:00:00'),
                    'stop_headsign' => ! empty($row['stop_headsign']) ? $row['stop_headsign'] : null,
                    'pickup_type' => ! empty($row['pickup_type']) ? (int) $row['pickup_type'] : 0,
                    'drop_off_type' => ! empty($row['drop_off_type']) ? (int) $row['drop_off_type'] : 0,
                ]
            );
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
            // Use updateOrCreate for smart merge
            Calendar::updateOrCreate(
                [
                    'tenant_id' => $this->import->tenant_id,
                    'schedule_import_id' => $this->import->id,
                    'service_id' => $row['service_id'] ?? '',
                ],
                [
                    'monday' => ($row['monday'] ?? '0') === '1',
                    'tuesday' => ($row['tuesday'] ?? '0') === '1',
                    'wednesday' => ($row['wednesday'] ?? '0') === '1',
                    'thursday' => ($row['thursday'] ?? '0') === '1',
                    'friday' => ($row['friday'] ?? '0') === '1',
                    'saturday' => ($row['saturday'] ?? '0') === '1',
                    'sunday' => ($row['sunday'] ?? '0') === '1',
                    'start_date' => $this->parseDate($row['start_date'] ?? ''),
                    'end_date' => $this->parseDate($row['end_date'] ?? ''),
                ]
            );
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
            // Use updateOrCreate for smart merge
            CalendarDate::updateOrCreate(
                [
                    'tenant_id' => $this->import->tenant_id,
                    'schedule_import_id' => $this->import->id,
                    'service_id' => $row['service_id'] ?? '',
                    'date' => $this->parseDate($row['date'] ?? ''),
                ],
                [
                    'exception_type' => ! empty($row['exception_type']) ? (int) $row['exception_type'] : 1,
                ]
            );
        }
    }

    protected function parseCsv(string $file): array
    {
        $rows = [];
        $headers = [];

        if (($handle = fopen($file, 'r')) !== false) {
            $headers = fgetcsv($handle);

            // Remove UTF-8 BOM from first header if present
            if (! empty($headers[0])) {
                $headers[0] = str_replace("\xEF\xBB\xBF", '', $headers[0]);
            }

            // Trim header values
            $headers = array_map('trim', $headers);

            // Debug: Log headers for stops.txt
            if (str_contains($file, 'stops.txt')) {
                Log::info('Parsing stops.txt - Headers', [
                    'headers' => $headers,
                    'header_count' => count($headers),
                    'stop_id_index' => array_search('stop_id', $headers),
                ]);
            }

            $lineNumber = 1;
            while (($data = fgetcsv($handle)) !== false) {
                $lineNumber++;

                // Trim data values
                $data = array_map('trim', $data);

                // Debug first few rows of stops.txt
                if (str_contains($file, 'stops.txt') && $lineNumber <= 5) {
                    Log::info("Parsing stops.txt - Line {$lineNumber}", [
                        'data' => $data,
                        'data_count' => count($data),
                    ]);
                }

                // Pad data array if it has fewer columns than headers
                if (count($data) < count($headers)) {
                    $data = array_pad($data, count($headers), '');
                }

                // Truncate data array if it has more columns than headers
                if (count($data) > count($headers)) {
                    $data = array_slice($data, 0, count($headers));
                }

                $rows[] = array_combine($headers, $data);
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
