<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\GbfsStation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class SharedVehicleController extends Controller
{
    /**
     * Get all GBFS stations with their real-time cached statuses.
     */
    public function getStations(Request $request)
    {
        // Fetch all active stations with their parent source and tenant
        $stations = GbfsStation::with(['source', 'source.tenant'])
            ->whereHas('source', function ($query) {
                $query->where('is_active', true);
            })->get();

        $mergedStations = $stations->map(function (GbfsStation $station) {
            $sourceId = $station->gbfs_source_id;
            $stationId = $station->station_id;
            $cacheKey = "gbfs_station_status_{$sourceId}_{$stationId}";

            // Fetch live status from cache
            $status = Cache::get($cacheKey, []);

            return [
                'id' => $station->id,
                'station_id' => $station->station_id,
                'name' => $station->name,
                'lat' => $station->lat,
                'lon' => $station->lon,
                'capacity' => $station->capacity,
                'source_type' => $station->source->type, // 'cycle' or 'car'
                'tenant_id' => $station->source->tenant_id,
                'operator_name' => $station->source->tenant->name ?? 'Service',
                'status' => [
                    'num_bikes_available' => $status['num_bikes_available'] ?? 0,
                    'num_vehicles_available' => $status['num_bikes_available'] ?? 0,
                    'num_docks_available' => $status['num_docks_available'] ?? 0,
                    'is_installed' => $status['is_installed'] ?? false,
                    'is_renting' => $status['is_renting'] ?? false,
                    'is_returning' => $status['is_returning'] ?? false,
                    'vehicle_types_available' => $status['vehicle_types_available'] ?? [],
                    'last_reported' => $status['last_reported'] ?? null,
                ],
            ];
        });

        return response()->json([
            'data' => $mergedStations,
        ]);
    }
}
