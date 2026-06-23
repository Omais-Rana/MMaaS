<?php

namespace App\Console\Commands;

use App\Jobs\ProcessScheduleImport;
use App\Models\ScheduleImport;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use ZipArchive;

class DownloadFilteredSncfCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:download-filtered-sncf {--bbox=47.3,6.5,47.9,7.1 : Bounding box minLat,minLon,maxLat,maxLon}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Downloads the national SNCF GTFS, filters it by a bounding box, and imports it.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $bboxStr = $this->option('bbox');
        $bbox = array_map('floatval', explode(',', $bboxStr));
        if (count($bbox) !== 4) {
            $this->error('Invalid bbox format. Use minLat,minLon,maxLat,maxLon');

            return;
        }

        [$minLat, $minLon, $maxLat, $maxLon] = $bbox;
        $this->info("Using bounding box: Lat [$minLat to $maxLat], Lon [$minLon to $maxLon]");

        $url = 'https://eu.ftp.opendatasoft.com/sncf/plandata/Export_OpenData_SNCF_GTFS_NewTripId.zip';
        $finalZipPath = 'imports/sncf-filtered-'.time().'.zip';
        
        $tempZipPath = Storage::path('temp_sncf.zip');
        $extractDir = Storage::path('temp_sncf_extract');
        $filteredDir = Storage::path('temp_sncf_filtered');

        // 1. Download
        $this->info('Downloading National SNCF GTFS (this may take a while)...');
        try {
            $response = Http::withOptions([
                'sink' => $tempZipPath,
                'timeout' => 600, // 10 minutes
            ])->get($url);

            if (! $response->successful()) {
                $this->error('Failed to download GTFS. Status: '.$response->status());

                return;
            }
        } catch (\Exception $e) {
            $this->error('Download failed: '.$e->getMessage());

            return;
        }

        // 2. Extract
        $this->info('Extracting GTFS...');
        Storage::makeDirectory('temp_sncf_extract');
        Storage::makeDirectory('temp_sncf_filtered');

        $zip = new ZipArchive;
        if ($zip->open($tempZipPath) === true) {
            $zip->extractTo($extractDir);
            $zip->close();
        } else {
            $this->error('Failed to extract zip file.');

            return;
        }

        // 3. Filter
        $this->info('Filtering GTFS data by bounding box...');

        // Keep track of valid IDs
        $validStopIds = [];
        $validTripIds = [];
        $validRouteIds = [];
        $validServiceIds = [];
        $validShapeIds = [];
        $validAgencyIds = [];

        // stops.txt
        $this->info('Filtering stops...');
        $stopsIn = fopen("$extractDir/stops.txt", 'r');
        $stopsOut = fopen("$filteredDir/stops.txt", 'w');
        $header = fgetcsv($stopsIn);
        fputcsv($stopsOut, $header);

        $latIdx = array_search('stop_lat', $header);
        $lonIdx = array_search('stop_lon', $header);
        $idIdx = array_search('stop_id', $header);

        while (($row = fgetcsv($stopsIn)) !== false) {
            if (! isset($row[$latIdx]) || ! isset($row[$lonIdx])) {
                continue;
            }

            $lat = (float) $row[$latIdx];
            $lon = (float) $row[$lonIdx];

            if ($lat >= $minLat && $lat <= $maxLat && $lon >= $minLon && $lon <= $maxLon) {
                fputcsv($stopsOut, $row);
                $validStopIds[$row[$idIdx]] = true;
            }
        }
        fclose($stopsIn);
        fclose($stopsOut);

        if (empty($validStopIds)) {
            $this->error('No stops found in the bounding box.');
            $this->cleanup($tempZipPath, $extractDir, $filteredDir);

            return;
        }
        $this->info('Found '.count($validStopIds).' stops.');

        // stop_times.txt
        $this->info('Filtering stop_times...');
        $this->filterFileBySet("$extractDir/stop_times.txt", "$filteredDir/stop_times.txt", 'stop_id', $validStopIds, 'trip_id', $validTripIds);

        // trips.txt
        $this->info('Filtering trips...');
        $this->filterFileBySet("$extractDir/trips.txt", "$filteredDir/trips.txt", 'trip_id', $validTripIds, 'route_id', $validRouteIds);

        // Also extract service_id and shape_id from trips
        $tripsIn = fopen("$extractDir/trips.txt", 'r');
        $header = fgetcsv($tripsIn);
        $tripIdx = array_search('trip_id', $header);
        $serviceIdx = array_search('service_id', $header);
        $shapeIdx = array_search('shape_id', $header);

        while (($row = fgetcsv($tripsIn)) !== false) {
            if (isset($row[$tripIdx]) && isset($validTripIds[$row[$tripIdx]])) {
                if ($serviceIdx !== false && isset($row[$serviceIdx]) && $row[$serviceIdx] !== '') {
                    $validServiceIds[$row[$serviceIdx]] = true;
                }
                if ($shapeIdx !== false && isset($row[$shapeIdx]) && $row[$shapeIdx] !== '') {
                    $validShapeIds[$row[$shapeIdx]] = true;
                }
            }
        }
        fclose($tripsIn);

        // routes.txt
        $this->info('Filtering routes...');
        $this->filterFileBySet("$extractDir/routes.txt", "$filteredDir/routes.txt", 'route_id', $validRouteIds, 'agency_id', $validAgencyIds);

        // shapes.txt
        $this->info('Filtering shapes...');
        if (file_exists("$extractDir/shapes.txt")) {
            $this->filterFileBySet("$extractDir/shapes.txt", "$filteredDir/shapes.txt", 'shape_id', $validShapeIds);
        }

        // calendar.txt
        $this->info('Filtering calendars...');
        if (file_exists("$extractDir/calendar.txt")) {
            $this->filterFileBySet("$extractDir/calendar.txt", "$filteredDir/calendar.txt", 'service_id', $validServiceIds);
        }

        // calendar_dates.txt
        if (file_exists("$extractDir/calendar_dates.txt")) {
            $this->filterFileBySet("$extractDir/calendar_dates.txt", "$filteredDir/calendar_dates.txt", 'service_id', $validServiceIds);
        }

        // agency.txt
        $this->info('Filtering agencies...');
        if (file_exists("$extractDir/agency.txt")) {
            $this->filterFileBySet("$extractDir/agency.txt", "$filteredDir/agency.txt", 'agency_id', $validAgencyIds);
        }

        // Copy other small files unconditionally if they exist and are useful
        $smallFiles = ['transfers.txt', 'feed_info.txt', 'frequencies.txt'];
        foreach ($smallFiles as $f) {
            if (file_exists("$extractDir/$f")) {
                copy("$extractDir/$f", "$filteredDir/$f");
            }
        }

        // 4. Re-package
        $this->info('Zipping filtered GTFS...');
        $filteredZip = new ZipArchive;
        Storage::makeDirectory('imports');

        if ($filteredZip->open(Storage::path($finalZipPath), ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
            $files = scandir($filteredDir);
            foreach ($files as $file) {
                if ($file !== '.' && $file !== '..') {
                    $filteredZip->addFile("$filteredDir/$file", $file);
                }
            }
            $filteredZip->close();
        } else {
            $this->error('Failed to create filtered zip.');
            $this->cleanup($tempZipPath, $extractDir, $filteredDir);

            return;
        }

        // 5. Cleanup
        $this->cleanup($tempZipPath, $extractDir, $filteredDir);
        $this->info("Saved filtered zip to: $finalZipPath");

        // 6. Database Import
        $this->info('Setting up SNCF Tenant and Schedule Import...');

        // Ensure SNCF tenant exists
        $tenant = User::firstOrCreate(
            ['email' => 'sncf@example.com'],
            [
                'name' => 'SNCF',
                'password' => bcrypt(Str::random(16)),
                'role' => 'subadmin',
                'is_active' => true,
            ]
        );

        $import = ScheduleImport::create([
            'tenant_id' => $tenant->id,
            'filename' => $finalZipPath,
            'status' => 'pending',
        ]);

        $this->info("Dispatching import job...");
        ProcessScheduleImport::dispatch($import, $finalZipPath);

        $this->info('Done! The filtered GTFS will now be imported in the background.');
    }

    private function filterFileBySet($inPath, $outPath, $filterCol, $validSet, $collectCol = null, &$collectedSet = [])
    {
        if (! file_exists($inPath)) {
            return;
        }

        $in = fopen($inPath, 'r');
        $out = fopen($outPath, 'w');

        $header = fgetcsv($in);
        fputcsv($out, $header);

        $filterIdx = array_search($filterCol, $header);
        $collectIdx = $collectCol ? array_search($collectCol, $header) : false;

        if ($filterIdx === false) {
            fclose($in);
            fclose($out);

            return;
        }

        while (($row = fgetcsv($in)) !== false) {
            if (isset($row[$filterIdx]) && isset($validSet[$row[$filterIdx]])) {
                fputcsv($out, $row);
                if ($collectIdx !== false && isset($row[$collectIdx])) {
                    $collectedSet[$row[$collectIdx]] = true;
                }
            }
        }

        fclose($in);
        fclose($out);
    }

    private function cleanup($zipPath, $extractDir, $filteredDir)
    {
        if (file_exists($zipPath)) {
            unlink($zipPath);
        }
        Storage::deleteDirectory('temp_sncf_extract');
        Storage::deleteDirectory('temp_sncf_filtered');
    }
}
