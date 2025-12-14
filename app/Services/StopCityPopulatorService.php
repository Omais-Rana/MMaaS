<?php

namespace App\Services;

use App\Models\Stop;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class StopCityPopulatorService
{
    public function populateCitiesForStops(Collection $stops): int
    {
        if ($stops->isEmpty()) {
            return 0;
        }

        Log::info("Starting city population for {$stops->count()} stops");

        // Group stops by proximity (within ~1km)
        $groups = $this->groupStopsByProximity($stops);

        Log::info("Grouped into {$groups->count()} location clusters");

        $updated = 0;

        foreach ($groups as $group) {
            // Use the first stop in the group as representative
            $representative = $group->first();

            // Get city name via reverse geocoding
            $city = $this->getCityFromCoordinates(
                $representative->stop_lat,
                $representative->stop_lon
            );

            if ($city) {
                // Update all stops in this group
                Stop::withoutGlobalScopes()
                    ->whereIn('id', $group->pluck('id'))
                    ->update(['city' => $city]);

                $updated += $group->count();

                Log::info("Updated {$group->count()} stops with city: {$city}");
            }

            // Rate limiting - be nice to the API
            usleep(200000); // 0.2 second delay
        }

        Log::info("Successfully updated {$updated} stops with city information");

        return $updated;
    }

    private function groupStopsByProximity(Collection $stops): Collection
    {
        $groups = collect();
        $processed = [];

        foreach ($stops as $stop) {
            if (in_array($stop->id, $processed)) {
                continue;
            }

            // Find all stops within ~1km of this stop
            $group = collect([$stop]);
            $processed[] = $stop->id;

            foreach ($stops as $otherStop) {
                if (in_array($otherStop->id, $processed)) {
                    continue;
                }

                $distance = $this->haversineDistance(
                    $stop->stop_lat,
                    $stop->stop_lon,
                    $otherStop->stop_lat,
                    $otherStop->stop_lon
                );

                // Group stops within 1km
                if ($distance <= 1.0) {
                    $group->push($otherStop);
                    $processed[] = $otherStop->id;
                }
            }

            $groups->push($group);
        }

        return $groups;
    }

    private function haversineDistance($lat1, $lon1, $lat2, $lon2): float
    {
        $earthRadius = 6371; // km

        $lat1 = deg2rad($lat1);
        $lon1 = deg2rad($lon1);
        $lat2 = deg2rad($lat2);
        $lon2 = deg2rad($lon2);

        $dlat = $lat2 - $lat1;
        $dlon = $lon2 - $lon1;

        $a = sin($dlat / 2) * sin($dlat / 2) +
            cos($lat1) * cos($lat2) *
            sin($dlon / 2) * sin($dlon / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }

    private function getCityFromCoordinates($lat, $lon): ?string
    {
        try {
            // Using Nominatim (OpenStreetMap) for reverse geocoding
            $response = Http::timeout(10)
                ->withHeaders([
                    'User-Agent' => 'TransportScheduleApp/1.0',
                ])
                ->get('https://nominatim.openstreetmap.org/reverse', [
                    'lat' => $lat,
                    'lon' => $lon,
                    'format' => 'json',
                    'addressdetails' => 1,
                    'accept-language' => 'fr',
                ]);

            if ($response->successful()) {
                $data = $response->json();
                $address = $data['address'] ?? [];

                // Try to get city from various possible fields
                $city = $address['city']
                    ?? $address['town']
                    ?? $address['village']
                    ?? $address['municipality']
                    ?? $address['county']
                    ?? null;

                if ($city) {
                    Log::info("Geocoded city: {$city} for coordinates ({$lat}, {$lon})");
                }

                return $city;
            }

            Log::warning("Geocoding API returned non-successful status for ({$lat}, {$lon})");
            return null;
        } catch (\Exception $e) {
            Log::error("Failed to geocode coordinates ({$lat}, {$lon}): {$e->getMessage()}");
            return null;
        }
    }
}
