<?php

namespace App\Services;

use App\Models\GbfsSource;
use App\Models\GbfsStation;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GbfsIntegrationService
{
    /**
     * Synchronize static station data for all active GBFS sources.
     */
    public function syncStations(): void
    {
        /** @var \Illuminate\Database\Eloquent\Collection<int, \App\Models\GbfsSource> $sources */
        $sources = GbfsSource::query()->where('is_active', true)->get();

        foreach ($sources as $source) {
            try {
                $this->syncSourceStations($source);
            } catch (\Exception $e) {
                Log::error("Failed to sync GBFS stations for source {$source->id}: " . $e->getMessage());
            }
        }
    }

    /**
     * Synchronize live status data for all active GBFS sources and push to cache.
     */
    public function syncStatuses(): void
    {
        /** @var \Illuminate\Database\Eloquent\Collection<int, \App\Models\GbfsSource> $sources */
        $sources = GbfsSource::query()->where('is_active', true)->get();

        foreach ($sources as $source) {
            try {
                $this->syncSourceStatuses($source);
            } catch (\Exception $e) {
                Log::error("Failed to sync GBFS statuses for source {$source->id}: " . $e->getMessage());
            }
        }
    }

    /**
     * Synchronize a single GBFS source explicitly (both stations and statuses).
     */
    public function syncSingleSource(GbfsSource $source): void
    {
        $this->syncSourceStations($source);
        $this->syncSourceStatuses($source);
    }

    protected function syncSourceStations(GbfsSource $source): void
    {
        $stationInfoUrl = $this->getFeedUrl($source, 'station_information');

        if (! $stationInfoUrl) {
            Log::warning("No station_information feed found for GBFS source {$source->id}");
            return;
        }

        // Fetch station information
        $response = Http::timeout(10)->get($stationInfoUrl);

        if (!$response->successful()) {
            throw new \Exception("HTTP request failed with status {$response->status()}");
        }

        $infoResponse = $response->json();
        $stations = $infoResponse['data']['stations'] ?? [];

        if (!is_array($stations)) {
            return;
        }

        foreach ($stations as $stationData) {
            if (!isset($stationData['station_id'])) {
                continue;
            }

            GbfsStation::query()->updateOrCreate(
                [
                    'gbfs_source_id' => $source->id,
                    'station_id' => (string) $stationData['station_id'],
                ],
                [
                    'tenant_id' => $source->tenant_id,
                    'name' => $stationData['name'] ?? $stationData['StationName'] ?? 'Unknown Station',
                    'lat' => $stationData['lat'] ?? 0,
                    'lon' => $stationData['lon'] ?? 0,
                    'capacity' => $stationData['capacity'] ?? null,
                ]
            );
        }
    }

    protected function syncSourceStatuses(GbfsSource $source): void
    {
        $stationStatusUrl = $this->getFeedUrl($source, 'station_status');

        if (! $stationStatusUrl) {
            Log::warning("No station_status feed found for GBFS source {$source->id}");
            return;
        }

        // Fetch station statuses
        $response = Http::timeout(10)->get($stationStatusUrl);

        if (!$response->successful()) {
            throw new \Exception("HTTP request failed with status {$response->status()}");
        }

        $statusResponse = $response->json();
        $stations = $statusResponse['data']['stations'] ?? [];

        if (!is_array($stations)) {
            return;
        }

        // Determine TTL from response, fallback to 60 seconds
        $ttl = $statusResponse['ttl'] ?? 60;
        // If TTL is 0 or negative, set to 60s default
        if (!is_numeric($ttl) || $ttl <= 0) {
            $ttl = 60;
        }

        $lastUpdated = $statusResponse['last_updated'] ?? time();

        foreach ($stations as $stationStatus) {
            if (!isset($stationStatus['station_id'])) {
                continue;
            }

            $stationId = (string) $stationStatus['station_id'];
            $cacheKey = "gbfs_station_status_{$source->id}_{$stationId}";

            Cache::forever($cacheKey, [
                'num_bikes_available' => $stationStatus['num_bikes_available'] ?? $stationStatus['num_vehicles_available'] ?? 0,
                'num_docks_available' => $stationStatus['num_docks_available'] ?? 0,
                'is_installed' => $stationStatus['is_installed'] ?? false,
                'is_renting' => $stationStatus['is_renting'] ?? false,
                'is_returning' => $stationStatus['is_returning'] ?? false,
                'vehicle_types_available' => $stationStatus['vehicle_types_available'] ?? [],
                'last_reported' => $stationStatus['last_reported'] ?? $lastUpdated,
            ]);
        }
    }

    protected function getFeedUrl(GbfsSource $source, string $feedName): ?string
    {
        $response = Http::timeout(10)->get($source->url);

        if (!$response->successful()) {
            throw new \Exception("Discovery HTTP request failed with status {$response->status()}");
        }

        $discoveryResponse = $response->json();
        $data = $discoveryResponse['data'] ?? [];
        $feeds = $data['feeds'] ?? [];

        if (empty($feeds) && is_array($data)) {
            // Dynamically locate the first language array if not found in root (GBFS v1 compat)
            foreach ($data as $lang => $langData) {
                if (is_array($langData) && isset($langData['feeds'])) {
                    $feeds = $langData['feeds'];
                    break;
                }
            }
        }

        if (!is_array($feeds)) {
            return null;
        }

        foreach ($feeds as $feed) {
            if (isset($feed['name']) && $feed['name'] === $feedName && isset($feed['url'])) {
                return $feed['url'];
            }
        }

        return null;
    }
}
