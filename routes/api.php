<?php

use App\Http\Controllers\Api\RouteSearchController;
use App\Models\Stop;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('/routes/search', [RouteSearchController::class, 'searchRoutes']);
Route::get('/territories/{tenantId}/stops', [RouteSearchController::class, 'getTerritoryStops']);
Route::get('/parking-spots', [RouteSearchController::class, 'getParkingSpots']);

Route::get('/stops/nearest', function (Request $request) {
    $lat = $request->input('lat');
    $lon = $request->input('lon');

    if (! $lat || ! $lon) {
        return response()->json(['error' => 'Latitude and longitude required'], 400);
    }

    // Find nearest stop within 500m
    $nearestStop = Stop::withoutGlobalScopes()
        ->selectRaw('id, stop_name, city, stop_lat, stop_lon, 
            (6371 * acos(cos(radians(?)) * cos(radians(stop_lat)) * 
            cos(radians(stop_lon) - radians(?)) + 
            sin(radians(?)) * sin(radians(stop_lat)))) AS distance', [$lat, $lon, $lat])
        ->having('distance', '<=', 0.5)
        ->orderBy('distance')
        ->first();

    if (! $nearestStop) {
        return response()->json(['error' => 'No stop found within 500m'], 404);
    }

    return response()->json($nearestStop);
});
