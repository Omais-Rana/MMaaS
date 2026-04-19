<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RouteSearch;
use App\Models\Stop;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class RouteSearchController extends Controller
{
    /** @var array<string, list<array{float, float}>> Shape point cache keyed by shape_id, reset per-request */
    private array $shapeCache = [];

    /**
     * Search for stops by name, description, or code
     * Groups by stop_name and coordinates to show unique locations
     */
    public function searchStops(Request $request)
    {
        $query = $request->input('q', '');

        if (strlen($query) < 2) {
            return response()->json([]);
        }

        // Normalize query - remove accents for searching (é->e, à->a, etc.)
        $normalizedQuery = $this->removeAccents(strtolower($query));

        // Search stops - accent-insensitive and case-insensitive search
        // Prioritize stops that start with the query, then those that contain it
        // Use withoutGlobalScopes() to show stops from ALL operators (not just logged-in user's)
        $stops = Stop::withoutGlobalScopes()->where(function ($q) use ($normalizedQuery) {
            // Use LOWER and accent-insensitive comparison
            $q->whereRaw('LOWER(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(stop_name, "é", "e"), "è", "e"), "ê", "e"), "à", "a"), "ô", "o"), "ù", "u"), "ç", "c")) LIKE ?', [$normalizedQuery . '%'])
                ->orWhereRaw('LOWER(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(stop_name, "é", "e"), "è", "e"), "ê", "e"), "à", "a"), "ô", "o"), "ù", "u"), "ç", "c")) LIKE ?', ['% ' . $normalizedQuery . '%']);
        })
            ->whereNotNull('stop_lat')
            ->whereNotNull('stop_lon')
            ->select(
                'stop_name',
                DB::raw('ROUND(AVG(stop_lat), 5) as stop_lat'),
                DB::raw('ROUND(AVG(stop_lon), 5) as stop_lon'),
                DB::raw('GROUP_CONCAT(DISTINCT stop_id) as stop_ids'),
                DB::raw('MAX(stop_desc) as stop_desc'),
                DB::raw('MAX(stop_code) as stop_code'),
                DB::raw('MAX(city) as city'),
                DB::raw("CASE 
                    WHEN LOWER(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(stop_name, 'é', 'e'), 'è', 'e'), 'ê', 'e'), 'à', 'a'), 'ô', 'o'), 'ù', 'u'), 'ç', 'c')) LIKE '" . $normalizedQuery . "%' THEN 1 
                    ELSE 2 
                END as priority")
            )
            ->groupBy('stop_name')
            ->orderBy('priority')
            ->orderBy('stop_name')
            ->limit(20)
            ->get();

        return response()->json($stops);
    }

    /**
     * Search for routes - simplified approach showing all routes passing through selected stops
     */
    public function searchRoutes(Request $request)
    {
        $request->validate([
            'origin_name' => 'required|string',
            'destination_name' => 'required|string',
            'date' => 'required|date',
            'time' => 'required',
        ]);

        $originName = $request->input('origin_name');
        $destinationName = $request->input('destination_name');
        $date = $request->input('date');
        $time = $request->input('time');

        // Read GPS coordinates sent by the frontend autocomplete (always present when a stop is selected)
        $originLat = $request->input('origin_lat') ? (float) $request->input('origin_lat') : null;
        $originLon = $request->input('origin_lon') ? (float) $request->input('origin_lon') : null;
        $destLat = $request->input('dest_lat') ? (float) $request->input('dest_lat') : null;
        $destLon = $request->input('dest_lon') ? (float) $request->input('dest_lon') : null;

        // Log the search for authenticated users
        if (Auth::check()) {
            $user = Auth::user();
            try {
                $searchData = [
                    'user_id' => $user->id,
                    'origin_name' => $originName,
                    'destination_name' => $destinationName,
                    'search_time' => $time,
                    'search_date' => $date,
                ];

                if ($originLat && $originLon && $destLat && $destLon) {
                    $tenantIds = $this->findTenantsNearCoordinates(
                        $originLat,
                        $originLon,
                        $destLat,
                        $destLon
                    );

                    if (empty($tenantIds)) {
                        $tenantIds = [($user->role === 'subadmin' ? $user->id : $user->tenant_id)];
                    }

                    foreach ($tenantIds as $tenantId) {
                        RouteSearch::create(array_merge($searchData, ['tenant_id' => $tenantId]));
                    }
                } else {
                    $tenantId = $user->role === 'subadmin' ? $user->id : $user->tenant_id;
                    RouteSearch::create(array_merge($searchData, ['tenant_id' => $tenantId]));
                }
            } catch (\Exception $e) {
                // Logging failure must never break the search
            }
        }

        $minimumDepartureTime = $time;
        $dayOfWeek = date('w', strtotime($date));
        $dayNames = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];

        // FIX 5: Resolve stop IDs using GPS coordinates (sent by the frontend autocomplete).
        // This is far more precise than LIKE '%name%' which matched unrelated stops sharing the same name.
        $originStopIds = $this->resolveStopIds($originName, $originLat, $originLon);
        $destStopIds = $this->resolveStopIds($destinationName, $destLat, $destLon);

        if (empty($originStopIds) || empty($destStopIds)) {
            return response()->json([
                'routes' => [],
                'message' => 'Stops not found.',
            ]);
        }

        // Build active service IDs for the given date.
        // Priority: (1) calendars table entries for this date/day
        //           (2) calendar_dates exceptions (type=1 add, type=2 remove)
        //           (3) if neither has data, fall back to all services (bare GTFS with no schedule)
        $calendarServices = DB::table('calendars')
            ->where('start_date', '<=', $date)
            ->where('end_date', '>=', $date)
            ->where($dayNames[$dayOfWeek], 1)
            ->pluck('service_id')
            ->toArray();

        $exceptions = DB::table('calendar_dates')->where('date', $date)->get();
        $addedServices = $exceptions->where('exception_type', 1)->pluck('service_id')->toArray();

        if (! empty($calendarServices)) {
            // Standard GTFS: calendar drives the base, exceptions modify it
            $activeServiceIds = $calendarServices;
        } elseif (! empty($addedServices)) {
            // GTFS with calendar_dates-only schedule (no calendars table):
            // only the explicitly added services run on this date
            $activeServiceIds = $addedServices;
        } else {
            // No schedule data at all — fall back to all services
            $activeServiceIds = DB::table('trips')->distinct()->pluck('service_id')->toArray();
        }

        // Apply type=2 (removed) exceptions on top of whatever base we have
        $removedServices = $exceptions->where('exception_type', 2)->pluck('service_id')->toArray();
        if (! empty($removedServices)) {
            $activeServiceIds = array_values(array_filter($activeServiceIds, fn($id) => ! in_array($id, $removedServices)));
        }

        // Also add any type=1 services not yet in the list (e.g. when using calendar base)
        foreach ($addedServices as $svcId) {
            if (! in_array($svcId, $activeServiceIds)) {
                $activeServiceIds[] = $svcId;
            }
        }

        // Find ALL routes that connect these stops (any combination)
        // NOTE: stop_times.trip_id references trips.id, and trips.route_id references routes.id
        $routes = DB::table('stop_times as st1')
            ->join('stop_times as st2', function ($join) {
                $join->on('st1.trip_id', '=', 'st2.trip_id')
                    ->whereRaw('st2.stop_sequence > st1.stop_sequence');
            })
            ->join('trips', 'st1.trip_id', '=', 'trips.id')
            ->join('routes', 'trips.route_id', '=', 'routes.id')
            ->join('users', 'routes.tenant_id', '=', 'users.id')
            ->whereIn('trips.service_id', $activeServiceIds)
            ->whereIn('st1.stop_id', $originStopIds)
            ->whereIn('st2.stop_id', $destStopIds)
            ->select(
                'routes.route_short_name',
                'routes.route_long_name',
                'routes.route_desc',
                'routes.route_color',
                'routes.route_text_color',
                'trips.trip_headsign',
                'trips.service_id',
                'trips.id as trip_id',
                'trips.shape_id',
                'users.name as operator_name',
                'st1.departure_time',
                'st2.arrival_time',
                'st1.stop_id as origin_stop_id',
                'st2.stop_id as dest_stop_id',
                'st1.stop_sequence as origin_stop_sequence',
                'st2.stop_sequence as dest_stop_sequence',
                DB::raw('TIME_TO_SEC(TIMEDIFF(st2.arrival_time, st1.departure_time)) / 60 as duration_minutes'),
                DB::raw('st2.stop_sequence - st1.stop_sequence + 1 as stops_count')
            )
            ->where('st1.departure_time', '>=', $time)
            ->orderBy('st1.departure_time')
            ->limit(20)
            ->get();

        // Keep only the first (nearest) route for each transport line
        $uniqueRoutes = [];
        $seenRoutes = [];

        foreach ($routes as $route) {
            $routeKey = $route->route_short_name;
            if (! isset($seenRoutes[$routeKey])) {
                $seenRoutes[$routeKey] = true;
                $uniqueRoutes[] = $route;
            }
        }

        $routes = collect($uniqueRoutes);

        // If direct routes found, return them
        if ($routes->isNotEmpty()) {
            $selectedTime = substr($time, 0, 5);
            $formattedRoutes = $routes->map(function ($route) use ($selectedTime) {
                $departureTime = substr($route->departure_time, 0, 5);
                $isExactMatch = $departureTime === $selectedTime;

                // Get all stops on this trip between origin and destination
                $stopsOnRoute = DB::table('stop_times')
                    ->join('stops', 'stop_times.stop_id', '=', 'stops.id')
                    ->where('stop_times.trip_id', $route->trip_id)
                    ->where('stop_times.stop_sequence', '>=', $route->origin_stop_sequence)
                    ->where('stop_times.stop_sequence', '<=', $route->dest_stop_sequence)
                    ->orderBy('stop_times.stop_sequence')
                    ->select(
                        'stops.stop_name',
                        'stops.stop_lat',
                        'stops.stop_lon',
                        'stop_times.arrival_time',
                        'stop_times.departure_time',
                        'stop_times.stop_sequence'
                    )
                    ->get()
                    ->values()
                    ->map(function ($stop, $index) use (&$stopsTotal) {
                        return [
                            'name' => $stop->stop_name,
                            'lat' => $stop->stop_lat,
                            'lon' => $stop->stop_lon,
                            'arrival' => substr($stop->arrival_time, 0, 5),
                            'departure' => substr($stop->departure_time, 0, 5),
                            'is_intermediate' => false, // will be set below
                        ];
                    })
                    ->toArray();

                // Mark intermediate stops
                foreach ($stopsOnRoute as $i => &$stop) {
                    $stop['is_intermediate'] = ($i > 0 && $i < count($stopsOnRoute) - 1);
                }
                unset($stop);

                $shapePoints = [];
                if (! empty($route->shape_id) && ! empty($stopsOnRoute)) {
                    $first = $stopsOnRoute[0];
                    $last = $stopsOnRoute[count($stopsOnRoute) - 1];
                    $shapePoints = $this->getClippedShapePoints(
                        $route->shape_id,
                        (float) $first['lat'],
                        (float) $first['lon'],
                        (float) $last['lat'],
                        (float) $last['lon']
                    );
                }

                return [
                    'route_name' => $route->route_short_name ?? $route->route_long_name ?? 'Route',
                    'route_long_name' => $route->route_long_name,
                    'trip_headsign' => $route->trip_headsign,
                    'route_color' => $route->route_color,
                    'route_text_color' => $route->route_text_color,
                    'agency_name' => $route->operator_name ?? 'Transit Service',
                    'departure_time' => $departureTime,
                    'arrival_time' => substr($route->arrival_time, 0, 5),
                    'duration' => round($route->duration_minutes) . ' min',
                    'stops_count' => $route->stops_count,
                    'stops' => $stopsOnRoute,
                    'shape_pts' => $shapePoints,
                    'description' => $route->route_desc,
                    'is_exact_match' => $isExactMatch,
                    'connection_type' => 'direct',
                ];
            })
                ->take(10)
                ->values()
                ->toArray();

            return response()->json([
                'routes' => $formattedRoutes,
                'total' => count($formattedRoutes),
            ]);
        }

        // No direct routes found - search for routes with transfers
        $transferRoutes = $this->findTransferRoutes(
            $originStopIds,
            $destStopIds,
            $originName,
            $destinationName,
            $time,
            $activeServiceIds,
            $minimumDepartureTime,
            $date,
            $originLat,
            $originLon,
            $destLat,
            $destLon
        );

        return response()->json([
            'routes' => $transferRoutes,
            'total' => count($transferRoutes),
            'message' => count($transferRoutes) > 0
                ? 'Showing routes with transfers. Walking may be required between stops.'
                : 'No routes found. Try adjusting your search time or date.',
        ]);
    }

    /**
     * Find routes with transfers and walking connections.
     */
    private function findTransferRoutes(
        $originStopIds,
        $destStopIds,
        $originName,
        $destinationName,
        $time,
        $activeServiceIds,
        $minimumDepartureTime = null,
        $date = null,
        ?float $originLat = null,
        ?float $originLon = null,
        ?float $destLat = null,
        ?float $destLon = null
    ) {
        $maxWalkingDistance = 1.5;  // km
        $walkingSpeedKmh = 6.5;
        $minTransferTime = 5;    // minutes
        $maxTransferWaitTime = 25;   // minutes

        // FIX 1: Pre-load ALL stops into a PHP array (1 query replaces ~300 per-row DB calls).
        $stopCache = $this->preloadStopCache();

        $originStops = collect(array_intersect_key($stopCache, array_flip($originStopIds)));
        $destStops = collect(array_intersect_key($stopCache, array_flip($destStopIds)));

        if ($originStops->isEmpty() || $destStops->isEmpty()) {
            return [];
        }

        $journeys = [];
        $sqlMinTime = $time . ':00';

        // STRATEGY 1: Direct routes from EXACT stops (NO walking, NO transfers) - HIGHEST PRIORITY
        // For exact origin stops, no walking buffer needed - use minimum time directly
        $directRoutes = DB::table('stop_times as st_origin')
            ->join('trips as t', 'st_origin.trip_id', '=', 't.id')
            ->join('routes as r', 't.route_id', '=', 'r.id')
            ->join('users as u', 'r.tenant_id', '=', 'u.id')
            ->join('stop_times as st_dest', function ($join) use ($destStopIds) {
                $join->on('st_origin.trip_id', '=', 'st_dest.trip_id')
                    ->whereIn('st_dest.stop_id', $destStopIds) // EXACT destination stops only
                    ->whereRaw('st_dest.stop_sequence > st_origin.stop_sequence');
            })
            ->join('stops as dest_stop', 'st_dest.stop_id', '=', 'dest_stop.id')
            ->whereIn('st_origin.stop_id', $originStopIds) // EXACT origin stops only
            ->whereIn('t.service_id', $activeServiceIds)
            ->where('st_origin.departure_time', '>=', $sqlMinTime)
            ->where('st_origin.departure_time', '<=', date('H:i:s', strtotime($sqlMinTime) + 10800))
            ->select(
                'st_origin.stop_id as origin_stop_id',
                'st_origin.departure_time',
                'st_dest.stop_id as dest_stop_id',
                'st_dest.arrival_time',
                'dest_stop.stop_name as dest_stop_name',
                'r.route_short_name',
                'r.route_long_name',
                'r.route_color',
                'r.route_text_color',
                't.trip_headsign',
                't.id as trip_id',
                't.shape_id',
                'st_origin.stop_sequence as origin_sequence',
                'st_dest.stop_sequence as dest_sequence',
                'u.name as operator_name'
            )
            ->orderBy('st_origin.departure_time')
            ->limit(50) // Get more options to find best walking distance
            ->get();

        $foundJourneys = [];
        $routeOptions = [];

        $firstOrigin = $this->pickReferenceStop($originStops, $originLat, $originLon) ?? $originStops->first();
        $firstDest = $this->pickReferenceStop($destStops, $destLat, $destLon) ?? $destStops->first();

        $originRefLat = $originLat ?? (float) $firstOrigin->stop_lat;
        $originRefLon = $originLon ?? (float) $firstOrigin->stop_lon;
        $destRefLat = $destLat ?? (float) $firstDest->stop_lat;
        $destRefLon = $destLon ?? (float) $firstDest->stop_lon;

        // Group direct routes by route name — keep best (least total walk) per route
        foreach ($directRoutes as $route) {
            $routeKey = $route->route_short_name . '_' . $route->operator_name;
            $originWalkDist = $this->distancePointToStopFromCache($stopCache, $originRefLat, $originRefLon, $route->origin_stop_id);
            $destWalkDist = $this->distanceStopToPointFromCache($stopCache, $route->dest_stop_id, $destRefLat, $destRefLon);
            $totalWalkDist = $originWalkDist + $destWalkDist;

            if (! isset($routeOptions[$routeKey]) || $totalWalkDist < $routeOptions[$routeKey]['walk_dist']) {
                $routeOptions[$routeKey] = [
                    'route' => $route,
                    'walk_dist' => $totalWalkDist,
                    'origin_walk' => $originWalkDist,
                    'dest_walk' => $destWalkDist,
                ];
            }
        }

        // Build journeys from the best direct route option per route line
        foreach ($routeOptions as $option) {
            $route = $option['route'];
            $originWalkDist = $option['origin_walk'];
            $destWalkDist = $option['dest_walk'];
            $totalWalkDist = $option['walk_dist'];

            $legs = [];
            $busDepartureTime = substr($route->departure_time, 0, 5);
            $busArrivalTime = substr($route->arrival_time, 0, 5);

            if ($originWalkDist > 0.05) {
                $walkDuration = $this->calculateWalkingMinutes($originWalkDist, $walkingSpeedKmh);
                $walkEndTime = date('H:i', strtotime($busDepartureTime) - (2 * 60));
                $walkStartTime = date('H:i', strtotime($walkEndTime) - ($walkDuration * 60));

                if (strtotime($walkEndTime) >= strtotime($busDepartureTime)) {
                    continue;
                }

                $toCoords = $this->getStopCoordsFromCache($stopCache, $route->origin_stop_id);
                $legs[] = [
                    'type' => 'walk',
                    'from' => $originName,
                    'to' => $this->getStopNameFromCache($stopCache, $route->origin_stop_id),
                    'from_lat' => $originRefLat,
                    'from_lon' => $originRefLon,
                    'to_lat' => $toCoords['lat'] ?? null,
                    'to_lon' => $toCoords['lon'] ?? null,
                    'distance' => $originWalkDist,
                    'duration' => $walkDuration,
                    'start_time' => $walkStartTime,
                    'end_time' => $walkEndTime,
                ];
            }

            $transitStops = $this->fetchTripStops($route->trip_id, $route->origin_sequence, $route->dest_sequence);

            $transitShapePoints = [];
            if (! empty($route->shape_id) && ! empty($transitStops)) {
                $tFirst = $transitStops[0];
                $tLast = $transitStops[count($transitStops) - 1];
                $transitShapePoints = $this->getClippedShapePoints(
                    $route->shape_id,
                    (float) $tFirst['lat'],
                    (float) $tFirst['lon'],
                    (float) $tLast['lat'],
                    (float) $tLast['lon']
                );
            }

            $fromCoords = $this->getStopCoordsFromCache($stopCache, $route->origin_stop_id);
            $toCoords = $this->getStopCoordsFromCache($stopCache, $route->dest_stop_id);

            $legs[] = [
                'type' => 'transit',
                'route_name' => $route->route_short_name,
                'route_long_name' => $route->route_long_name,
                'route_color' => $route->route_color,
                'route_text_color' => $route->route_text_color,
                'operator_name' => $route->operator_name,
                'trip_headsign' => $route->trip_headsign,
                'from' => $this->getStopNameFromCache($stopCache, $route->origin_stop_id),
                'to' => $route->dest_stop_name,
                'from_lat' => $fromCoords['lat'] ?? null,
                'from_lon' => $fromCoords['lon'] ?? null,
                'to_lat' => $toCoords['lat'] ?? null,
                'to_lon' => $toCoords['lon'] ?? null,
                'departure_time' => $busDepartureTime,
                'arrival_time' => $busArrivalTime,
                // FIX 8: midnight-safe duration
                'duration' => $this->timeDiffMinutes($busDepartureTime, $busArrivalTime),
                'stops' => $transitStops,
                'shape_pts' => $transitShapePoints,
            ];

            if ($destWalkDist > 0.05) {
                $walkDuration = $this->calculateWalkingMinutes($destWalkDist, $walkingSpeedKmh);
                $walkStartTime = $busArrivalTime;
                $fromCoords = $this->getStopCoordsFromCache($stopCache, $route->dest_stop_id);

                $legs[] = [
                    'type' => 'walk',
                    'from' => $this->getStopNameFromCache($stopCache, $route->dest_stop_id),
                    'to' => $destinationName,
                    'from_lat' => $fromCoords['lat'] ?? null,
                    'from_lon' => $fromCoords['lon'] ?? null,
                    'to_lat' => $destRefLat,
                    'to_lon' => $destRefLon,
                    'distance' => $destWalkDist,
                    'duration' => $walkDuration,
                    'start_time' => $walkStartTime,
                    'end_time' => date('H:i', strtotime($walkStartTime) + ($walkDuration * 60)),
                ];
            }

            $journey = $this->buildJourney($legs, $time);

            // FIX 8: midnight-safe scoring
            $totalTimeMinutes = $this->timeDiffMinutes($journey['leave_at'], $journey['arrival_time']);
            $journey['score'] = 10000 - ($totalTimeMinutes * 50) - ($journey['transfers'] * 200) - ($totalWalkDist * 10);

            if ($minimumDepartureTime && $date) {
                $leaveAtDateTime = Carbon::parse($date . ' ' . $journey['leave_at']);
                $minimumDateTime = Carbon::parse($date . ' ' . $minimumDepartureTime);
                if ($leaveAtDateTime->lt($minimumDateTime)) {
                    continue;
                }
            }

            $foundJourneys[] = $journey;
        }

        // FIX 4: Early exit — skip Strategy 1.5 and 2 if we already have enough results
        if (count($foundJourneys) >= 5) {
            return $this->sortAndLimitJourneys($foundJourneys, 5);
        }

        // STRATEGY 1.5: Direct routes including nearby origin/destination stops
        // FIX 1: Use PHP cache for nearby stop lookup — zero DB Haversine queries
        $nearbyOriginStops = $this->findNearbyStopIdsFromCache($stopCache, $originRefLat, $originRefLon, $maxWalkingDistance, $originStopIds);
        $nearbyDestStops = $this->findNearbyStopIdsFromCache($stopCache, $destRefLat, $destRefLon, $maxWalkingDistance, $destStopIds);

        $allOriginStopIds = array_unique(array_merge($originStopIds, array_column($nearbyOriginStops, 'id')));
        $allDestStopIds = array_unique(array_merge($destStopIds, array_column($nearbyDestStops, 'id')));

        // PRIORITY QUERY: exact origin stops → nearby dest stops
        // This runs first so exact-origin results are never pushed out by limit.
        $exactOriginNearbyDestRoutes = DB::table('stop_times as st_origin')
            ->join('trips as t', 'st_origin.trip_id', '=', 't.id')
            ->join('routes as r', 't.route_id', '=', 'r.id')
            ->join('users as u', 'r.tenant_id', '=', 'u.id')
            ->join('stop_times as st_dest', function ($join) use ($allDestStopIds) {
                $join->on('st_origin.trip_id', '=', 'st_dest.trip_id')
                    ->whereIn('st_dest.stop_id', $allDestStopIds)
                    ->whereRaw('st_dest.stop_sequence > st_origin.stop_sequence');
            })
            ->join('stops as dest_stop', 'st_dest.stop_id', '=', 'dest_stop.id')
            ->whereIn('st_origin.stop_id', $originStopIds)  // EXACT origin only
            ->whereIn('t.service_id', $activeServiceIds)
            ->where('st_origin.departure_time', '>=', $sqlMinTime)
            ->where('st_origin.departure_time', '<=', date('H:i:s', strtotime($sqlMinTime) + 10800))
            ->select(
                'st_origin.stop_id as origin_stop_id',
                'st_origin.departure_time',
                'st_dest.stop_id as dest_stop_id',
                'st_dest.arrival_time',
                'dest_stop.stop_name as dest_stop_name',
                'r.route_short_name',
                'r.route_long_name',
                'r.route_color',
                'r.route_text_color',
                't.trip_headsign',
                't.id as trip_id',
                't.shape_id',
                'st_origin.stop_sequence as origin_sequence',
                'st_dest.stop_sequence as dest_sequence',
                'u.name as operator_name'
            )
            ->orderBy('st_origin.departure_time')
            ->limit(100)
            ->get();

        // SECONDARY QUERY: nearby origin stops → nearby dest stops (deprioritised)
        $nearbyDirectRoutes = DB::table('stop_times as st_origin')
            ->join('trips as t', 'st_origin.trip_id', '=', 't.id')
            ->join('routes as r', 't.route_id', '=', 'r.id')
            ->join('users as u', 'r.tenant_id', '=', 'u.id')
            ->join('stop_times as st_dest', function ($join) use ($allDestStopIds) {
                $join->on('st_origin.trip_id', '=', 'st_dest.trip_id')
                    ->whereIn('st_dest.stop_id', $allDestStopIds)
                    ->whereRaw('st_dest.stop_sequence > st_origin.stop_sequence');
            })
            ->join('stops as dest_stop', 'st_dest.stop_id', '=', 'dest_stop.id')
            ->whereIn('st_origin.stop_id', $allOriginStopIds)
            ->whereNotIn('st_origin.stop_id', $originStopIds)  // exclude exact (already handled)
            ->whereIn('t.service_id', $activeServiceIds)
            ->where('st_origin.departure_time', '>=', $sqlMinTime)
            ->where('st_origin.departure_time', '<=', date('H:i:s', strtotime($sqlMinTime) + 10800))
            ->select(
                'st_origin.stop_id as origin_stop_id',
                'st_origin.departure_time',
                'st_dest.stop_id as dest_stop_id',
                'st_dest.arrival_time',
                'dest_stop.stop_name as dest_stop_name',
                'r.route_short_name',
                'r.route_long_name',
                'r.route_color',
                'r.route_text_color',
                't.trip_headsign',
                't.id as trip_id',
                't.shape_id',
                'st_origin.stop_sequence as origin_sequence',
                'st_dest.stop_sequence as dest_sequence',
                'u.name as operator_name'
            )
            ->orderBy('st_origin.departure_time')
            ->limit(100)
            ->get();

        // Merge: exact-origin results take priority (processed first)
        $nearbyDirectRoutes = $exactOriginNearbyDestRoutes->merge($nearbyDirectRoutes);

        // Process nearby routes — keep best (least walk) per route line
        $nearbyRouteOptions = [];
        foreach ($nearbyDirectRoutes as $route) {
            $routeKey = $route->route_short_name . '_' . $route->operator_name;
            $originWalkDist = $this->distancePointToStopFromCache($stopCache, $originRefLat, $originRefLon, $route->origin_stop_id);
            $destWalkDist = $this->distanceStopToPointFromCache($stopCache, $route->dest_stop_id, $destRefLat, $destRefLon);
            $totalWalkDist = $originWalkDist + $destWalkDist;

            $isBetter = ! isset($nearbyRouteOptions[$routeKey]);
            if (! $isBetter) {
                $currentBest = $nearbyRouteOptions[$routeKey];
                // Better if strictly less walk
                if ($totalWalkDist < $currentBest['walk_dist'] - 0.1) {
                    $isBetter = true;
                }
                // Or if walk is comparable (within 100m) but we get off LATER (higher dest_sequence)
                elseif ($totalWalkDist <= $currentBest['walk_dist'] + 0.1 && $route->dest_sequence > $currentBest['route']->dest_sequence) {
                    $isBetter = true;
                }
            }

            if ($isBetter) {
                $nearbyRouteOptions[$routeKey] = [
                    'route' => $route,
                    'walk_dist' => $totalWalkDist,
                    'origin_walk' => $originWalkDist,
                    'dest_walk' => $destWalkDist,
                ];
            }
        }

        foreach ($nearbyRouteOptions as $option) {
            $route = $option['route'];
            $originWalkDist = $option['origin_walk'];
            $destWalkDist = $option['dest_walk'];
            $totalWalkDist = $option['walk_dist'];

            $legs = [];
            $busDepartureTime = substr($route->departure_time, 0, 5);
            $busArrivalTime = substr($route->arrival_time, 0, 5);

            if ($originWalkDist > 0.05) {
                $walkDuration = $this->calculateWalkingMinutes($originWalkDist, $walkingSpeedKmh);
                $walkEndTime = date('H:i', strtotime($busDepartureTime) - (2 * 60));
                $walkStartTime = date('H:i', strtotime($walkEndTime) - ($walkDuration * 60));

                if (strtotime($walkEndTime) >= strtotime($busDepartureTime)) {
                    continue;
                }

                $toCoords = $this->getStopCoordsFromCache($stopCache, $route->origin_stop_id);
                $legs[] = [
                    'type' => 'walk',
                    'from' => $originName,
                    'to' => $this->getStopNameFromCache($stopCache, $route->origin_stop_id),
                    'from_lat' => $originRefLat,
                    'from_lon' => $originRefLon,
                    'to_lat' => $toCoords['lat'] ?? null,
                    'to_lon' => $toCoords['lon'] ?? null,
                    'distance' => $originWalkDist,
                    'duration' => $walkDuration,
                    'start_time' => $walkStartTime,
                    'end_time' => $walkEndTime,
                ];
            }

            $transitStops = $this->fetchTripStops($route->trip_id, $route->origin_sequence, $route->dest_sequence);
            $transitShapePoints = [];
            if (! empty($route->shape_id) && ! empty($transitStops)) {
                $tFirst = $transitStops[0];
                $tLast = $transitStops[count($transitStops) - 1];
                $transitShapePoints = $this->getClippedShapePoints(
                    $route->shape_id,
                    (float) $tFirst['lat'],
                    (float) $tFirst['lon'],
                    (float) $tLast['lat'],
                    (float) $tLast['lon']
                );
            }

            $fromCoords = $this->getStopCoordsFromCache($stopCache, $route->origin_stop_id);
            $toCoords = $this->getStopCoordsFromCache($stopCache, $route->dest_stop_id);

            $legs[] = [
                'type' => 'transit',
                'route_name' => $route->route_short_name,
                'route_long_name' => $route->route_long_name,
                'route_color' => $route->route_color,
                'route_text_color' => $route->route_text_color,
                'operator_name' => $route->operator_name,
                'trip_headsign' => $route->trip_headsign,
                'from' => $this->getStopNameFromCache($stopCache, $route->origin_stop_id),
                'to' => $route->dest_stop_name,
                'from_lat' => $fromCoords['lat'] ?? null,
                'from_lon' => $fromCoords['lon'] ?? null,
                'to_lat' => $toCoords['lat'] ?? null,
                'to_lon' => $toCoords['lon'] ?? null,
                'departure_time' => $busDepartureTime,
                'arrival_time' => $busArrivalTime,
                'duration' => $this->timeDiffMinutes($busDepartureTime, $busArrivalTime),
                'stops' => $transitStops,
                'shape_pts' => $transitShapePoints,
            ];

            if ($destWalkDist > 0.05) {
                $walkDuration = $this->calculateWalkingMinutes($destWalkDist, $walkingSpeedKmh);
                $walkStartTime = $busArrivalTime;
                $fromCoords = $this->getStopCoordsFromCache($stopCache, $route->dest_stop_id);

                $legs[] = [
                    'type' => 'walk',
                    'from' => $route->dest_stop_name,
                    'to' => $destinationName,
                    'from_lat' => $fromCoords['lat'] ?? null,
                    'from_lon' => $fromCoords['lon'] ?? null,
                    'to_lat' => $destRefLat,
                    'to_lon' => $destRefLon,
                    'distance' => $destWalkDist,
                    'duration' => $walkDuration,
                    'start_time' => $walkStartTime,
                    'end_time' => date('H:i', strtotime($walkStartTime) + ($walkDuration * 60)),
                ];
            }

            $journey = $this->buildJourney($legs, $time);

            $totalTimeMinutes = $this->timeDiffMinutes($journey['leave_at'], $journey['arrival_time']);
            $journey['score'] = 10000 - ($totalTimeMinutes * 50) - ($journey['transfers'] * 200) - ($totalWalkDist * 10);

            if ($minimumDepartureTime && $date) {
                $leaveAtDateTime = Carbon::parse($date . ' ' . $journey['leave_at']);
                $minimumDateTime = Carbon::parse($date . ' ' . $minimumDepartureTime);
                if ($leaveAtDateTime->lt($minimumDateTime)) {
                    continue;
                }
            }

            $foundJourneys[] = $journey;
        }

        // FIX 4: Early exit — skip Strategy 2 if we already have enough results
        if (count($foundJourneys) >= 5) {
            return $this->sortAndLimitJourneys($foundJourneys, 5);
        }

        // STRATEGY 2: One-transfer routes — FIX 2: BATCHED inner loop (100 queries → 2)
        $expandedDestStopIds = array_unique(array_merge($destStopIds, array_column($nearbyDestStops, 'id')));

        $seenRoutes = [];
        $transferRoutes = DB::table('stop_times as st_origin')
            ->join('trips as t1', 'st_origin.trip_id', '=', 't1.id')
            ->join('routes as r1', 't1.route_id', '=', 'r1.id')
            ->join('users as u1', 'r1.tenant_id', '=', 'u1.id')
            ->join('stop_times as st_transfer', function ($join) {
                $join->on('st_origin.trip_id', '=', 'st_transfer.trip_id')
                    ->whereRaw('st_transfer.stop_sequence > st_origin.stop_sequence');
            })
            ->whereIn('st_origin.stop_id', $allOriginStopIds)
            ->whereIn('t1.service_id', $activeServiceIds)
            ->where('st_origin.departure_time', '>=', $sqlMinTime)
            ->where('st_origin.departure_time', '<=', date('H:i:s', strtotime($sqlMinTime) + 10800))
            ->select(
                'st_origin.stop_id as origin_stop_id',
                'st_origin.departure_time',
                'st_transfer.stop_id as transfer_stop_id',
                'st_transfer.arrival_time as transfer_arrival',
                'r1.route_short_name as first_route',
                'r1.route_long_name as first_route_long',
                'r1.route_color as first_color',
                'r1.route_text_color as first_text_color',
                't1.trip_headsign as first_headsign',
                't1.id as trip_id',
                't1.shape_id as first_shape_id',
                'st_origin.stop_sequence as origin_sequence',
                'st_transfer.stop_sequence as transfer_sequence',
                'u1.name as first_operator'
            )
            ->orderByRaw('CASE WHEN st_origin.stop_id IN (' . implode(',', array_map('intval', $originStopIds)) . ') THEN 0 ELSE 1 END')
            ->orderBy('st_origin.departure_time')
            ->get();

        // FIX 2: Pre-compute all nearby-transfer stop sets in PHP using the stop cache.
        // Previously: 1 Haversine DB query per first-leg candidate (up to 100 queries).
        // Now: 0 DB queries — all computed from the pre-loaded stop cache.
        $allTransferStopIds = $transferRoutes->pluck('transfer_stop_id')->unique()->values()->toArray();
        $nearbyByTransfer = []; // transfer_stop_id => [stop_id, ...]
        $allNearbyTransferStopIds = [];

        foreach ($allTransferStopIds as $transferStopId) {
            $transferStop = $stopCache[$transferStopId] ?? null;
            if (! $transferStop) {
                $nearbyByTransfer[$transferStopId] = [$transferStopId];
                $allNearbyTransferStopIds[] = $transferStopId;

                continue;
            }
            $nearbyStops = $this->findNearbyStopIdsFromCache($stopCache, (float) $transferStop->stop_lat, (float) $transferStop->stop_lon, $maxWalkingDistance);
            $nearbyIds = array_unique(array_merge([$transferStopId], array_column($nearbyStops, 'id')));
            $nearbyByTransfer[$transferStopId] = $nearbyIds;
            $allNearbyTransferStopIds = array_merge($allNearbyTransferStopIds, $nearbyIds);
        }
        $allNearbyTransferStopIds = array_unique($allNearbyTransferStopIds);

        // ONE batched second-leg query for ALL transfer points.
        // Previously: 1 query per first-leg candidate (up to 100 queries).
        // Now: 1 query for all candidates combined, then grouped for PHP lookup.
        $allSecondLegs = DB::table('stop_times as st2_start')
            ->join('trips as t2', 'st2_start.trip_id', '=', 't2.id')
            ->join('routes as r2', 't2.route_id', '=', 'r2.id')
            ->join('users as u2', 'r2.tenant_id', '=', 'u2.id')
            ->join('stop_times as st2_end', function ($join) use ($expandedDestStopIds) {
                $join->on('st2_start.trip_id', '=', 'st2_end.trip_id')
                    ->whereIn('st2_end.stop_id', $expandedDestStopIds)
                    ->whereRaw('st2_end.stop_sequence > st2_start.stop_sequence');
            })
            ->whereIn('t2.service_id', $activeServiceIds)
            ->whereIn('st2_start.stop_id', $allNearbyTransferStopIds)
            ->where('st2_start.departure_time', '>=', $sqlMinTime)
            ->where('st2_start.departure_time', '<=', date('H:i:s', strtotime($sqlMinTime) + 10800))
            ->select(
                't2.id as second_trip_id',
                'st2_start.stop_id as second_origin_stop_id',
                'st2_start.stop_sequence as second_origin_sequence',
                'st2_start.departure_time as second_departure',
                'st2_end.stop_id as final_stop_id',
                'st2_end.stop_sequence as final_stop_sequence',
                'st2_end.arrival_time as final_arrival',
                'r2.route_short_name as second_route_name',
                'r2.route_long_name as second_route_long_name',
                'r2.route_color as second_route_color',
                'r2.route_text_color as second_text_color',
                't2.trip_headsign as second_trip_headsign',
                't2.shape_id as second_shape_id',
                'u2.name as second_operator_name'
            )
            ->orderBy('st2_start.departure_time')
            ->get()
            ->groupBy('second_origin_stop_id');

        // Match first legs to pre-fetched second legs in PHP
        foreach ($transferRoutes as $firstLeg) {
            $nearbyTransferIds = $nearbyByTransfer[$firstLeg->transfer_stop_id] ?? [$firstLeg->transfer_stop_id];
            $minDepartureTime = date('H:i:s', strtotime($firstLeg->transfer_arrival) + ($minTransferTime * 60));
            $maxDepartureTime = date('H:i:s', strtotime($firstLeg->transfer_arrival) + ($maxTransferWaitTime * 60));

            // Collect matching second legs from pre-fetched data (0 DB queries)
            $secondLegOptions = collect();
            foreach ($nearbyTransferIds as $nearbyStopId) {
                $legsFromStop = $allSecondLegs->get($nearbyStopId, collect());
                foreach ($legsFromStop as $leg) {
                    if ($leg->second_departure >= $minDepartureTime && $leg->second_departure <= $maxDepartureTime) {
                        $secondLegOptions->push($leg);
                    }
                }
            }

            // Pick the best second leg: minimise (arrival at dest + final walk)
            $secondLeg = null;
            $bestArrivalAtDest = PHP_INT_MAX;
            $bestTransferWalk = PHP_INT_MAX;
            foreach ($secondLegOptions as $option) {
                $optWalkDist = $this->distanceStopToPointFromCache($stopCache, $option->final_stop_id, $destRefLat, $destRefLon);
                $optWalkMin = (int) round($optWalkDist / $walkingSpeedKmh * 60);
                $optArrival = strtotime('1970-01-01 ' . $option->final_arrival) + ($optWalkMin * 60);
                $optTransferWalk = $this->getWalkingDistanceFromCache($stopCache, $firstLeg->transfer_stop_id, $option->second_origin_stop_id);
                if ($optArrival < $bestArrivalAtDest || ($optArrival === $bestArrivalAtDest && $optTransferWalk < $bestTransferWalk)) {
                    $bestArrivalAtDest = $optArrival;
                    $bestTransferWalk = $optTransferWalk;
                    $secondLeg = $option;
                }
            }

            if ($secondLeg) {
                // Deduplicate by (first_trip_id + transfer_stop + second_trip_id) so different
                // transfer points on the same pair of trips are all evaluated independently.
                // sortAndLimitJourneys will then pick the best-scoring one per departure hour.
                $transferRouteKey = $firstLeg->trip_id . '_' . $firstLeg->transfer_stop_id . '_to_' . $secondLeg->second_trip_id;
                if (isset($seenRoutes[$transferRouteKey])) {
                    continue;
                }
                $seenRoutes[$transferRouteKey] = true;

                // Calculate actual wait time at transfer
                $waitTime = (strtotime($secondLeg->second_departure) - strtotime($firstLeg->transfer_arrival)) / 60;

                // Skip if wait time is too long (should be caught by query, but double-check)
                if ($waitTime > $maxTransferWaitTime) {
                    continue;
                }

                $transferWalkDist = $this->getWalkingDistanceFromCache($stopCache, $firstLeg->transfer_stop_id, $secondLeg->second_origin_stop_id);

                $firstLegStops = $this->fetchTripStops($firstLeg->trip_id, $firstLeg->origin_sequence, $firstLeg->transfer_sequence);
                $firstShapePoints = [];
                if (! empty($firstLeg->first_shape_id) && ! empty($firstLegStops)) {
                    $fFirst = $firstLegStops[0];
                    $fLast = $firstLegStops[count($firstLegStops) - 1];
                    $firstShapePoints = $this->getClippedShapePoints(
                        $firstLeg->first_shape_id,
                        (float) $fFirst['lat'],
                        (float) $fFirst['lon'],
                        (float) $fLast['lat'],
                        (float) $fLast['lon']
                    );
                }

                $firstFromCoords = $this->getStopCoordsFromCache($stopCache, $firstLeg->origin_stop_id);
                $firstToCoords = $this->getStopCoordsFromCache($stopCache, $firstLeg->transfer_stop_id);

                $legs = [];

                $originWalkDist = $this->distancePointToStopFromCache($stopCache, $originRefLat, $originRefLon, $firstLeg->origin_stop_id);
                if ($originWalkDist > 0.05) {
                    $originWalkDuration = $this->calculateWalkingMinutes($originWalkDist, $walkingSpeedKmh);
                    $firstDepartureTime = substr($firstLeg->departure_time, 0, 5);
                    $originWalkEnd = date('H:i', strtotime($firstDepartureTime) - (2 * 60));
                    $originWalkStart = date('H:i', strtotime($originWalkEnd) - ($originWalkDuration * 60));

                    if (strtotime($originWalkEnd) >= strtotime($firstDepartureTime)) {
                        continue;
                    }

                    $legs[] = [
                        'type' => 'walk',
                        'from' => $originName,
                        'to' => $this->getStopNameFromCache($stopCache, $firstLeg->origin_stop_id),
                        'from_lat' => $originRefLat,
                        'from_lon' => $originRefLon,
                        'to_lat' => $firstFromCoords['lat'] ?? null,
                        'to_lon' => $firstFromCoords['lon'] ?? null,
                        'distance' => $originWalkDist,
                        'duration' => $originWalkDuration,
                        'start_time' => $originWalkStart,
                        'end_time' => $originWalkEnd,
                    ];
                }

                $legs[] = [
                    'type' => 'transit',
                    'route_name' => $firstLeg->first_route,
                    'route_long_name' => $firstLeg->first_route_long,
                    'route_color' => $firstLeg->first_color,
                    'route_text_color' => $firstLeg->first_text_color,
                    'operator_name' => $firstLeg->first_operator,
                    'trip_headsign' => $firstLeg->first_headsign,
                    'from' => $this->getStopNameFromCache($stopCache, $firstLeg->origin_stop_id),
                    'to' => $this->getStopNameFromCache($stopCache, $firstLeg->transfer_stop_id),
                    'from_lat' => $firstFromCoords['lat'] ?? null,
                    'from_lon' => $firstFromCoords['lon'] ?? null,
                    'to_lat' => $firstToCoords['lat'] ?? null,
                    'to_lon' => $firstToCoords['lon'] ?? null,
                    'departure_time' => substr($firstLeg->departure_time, 0, 5),
                    'arrival_time' => substr($firstLeg->transfer_arrival, 0, 5),
                    'duration' => $this->timeDiffMinutes(substr($firstLeg->departure_time, 0, 5), substr($firstLeg->transfer_arrival, 0, 5)),
                    'stops' => $firstLegStops,
                    'shape_pts' => $firstShapePoints,
                ];

                // Only add walking between stops if there's real distance
                if ($transferWalkDist > 0.05) {
                    $walkDuration = $this->calculateWalkingTimeFromCache($stopCache, $firstLeg->transfer_stop_id, $secondLeg->second_origin_stop_id, $walkingSpeedKmh);
                    $walkStartTime = substr($firstLeg->transfer_arrival, 0, 5);
                    $walkEndTime = date('H:i', strtotime($walkStartTime) + ($walkDuration * 60));
                    $secondBusDeparture = substr($secondLeg->second_departure, 0, 5);

                    if (strtotime($secondBusDeparture) < strtotime($walkEndTime)) {
                        continue; // walk ends after bus departs — impossible
                    }

                    $fromCoords = $this->getStopCoordsFromCache($stopCache, $firstLeg->transfer_stop_id);
                    $toCoords = $this->getStopCoordsFromCache($stopCache, $secondLeg->second_origin_stop_id);

                    $legs[] = [
                        'type' => 'walk',
                        'from' => $this->getStopNameFromCache($stopCache, $firstLeg->transfer_stop_id),
                        'to' => $this->getStopNameFromCache($stopCache, $secondLeg->second_origin_stop_id),
                        'from_lat' => $fromCoords['lat'] ?? null,
                        'from_lon' => $fromCoords['lon'] ?? null,
                        'to_lat' => $toCoords['lat'] ?? null,
                        'to_lon' => $toCoords['lon'] ?? null,
                        'distance' => $transferWalkDist,
                        'duration' => $walkDuration,
                        'start_time' => $walkStartTime,
                        'end_time' => $walkEndTime,
                    ];
                }

                $secondLegStops = $this->fetchTripStops($secondLeg->second_trip_id, $secondLeg->second_origin_sequence, $secondLeg->final_stop_sequence);
                $secondShapePoints = [];
                if (! empty($secondLeg->second_shape_id) && ! empty($secondLegStops)) {
                    $sFirst = $secondLegStops[0];
                    $sLast = $secondLegStops[count($secondLegStops) - 1];
                    $secondShapePoints = $this->getClippedShapePoints(
                        $secondLeg->second_shape_id,
                        (float) $sFirst['lat'],
                        (float) $sFirst['lon'],
                        (float) $sLast['lat'],
                        (float) $sLast['lon']
                    );
                }

                $secondFromCoords = $this->getStopCoordsFromCache($stopCache, $secondLeg->second_origin_stop_id);
                $secondToCoords = $this->getStopCoordsFromCache($stopCache, $secondLeg->final_stop_id);

                $legs[] = [
                    'type' => 'transit',
                    'route_name' => $secondLeg->second_route_name,
                    'route_long_name' => $secondLeg->second_route_long_name,
                    'route_color' => $secondLeg->second_route_color,
                    'route_text_color' => $secondLeg->second_text_color,
                    'operator_name' => $secondLeg->second_operator_name,
                    'trip_headsign' => $secondLeg->second_trip_headsign,
                    'from' => $this->getStopNameFromCache($stopCache, $secondLeg->second_origin_stop_id),
                    'to' => $this->getStopNameFromCache($stopCache, $secondLeg->final_stop_id),
                    'from_lat' => $secondFromCoords['lat'] ?? null,
                    'from_lon' => $secondFromCoords['lon'] ?? null,
                    'to_lat' => $secondToCoords['lat'] ?? null,
                    'to_lon' => $secondToCoords['lon'] ?? null,
                    'departure_time' => substr($secondLeg->second_departure, 0, 5),
                    'arrival_time' => substr($secondLeg->final_arrival, 0, 5),
                    'duration' => $this->timeDiffMinutes(substr($secondLeg->second_departure, 0, 5), substr($secondLeg->final_arrival, 0, 5)),
                    'stops' => $secondLegStops,
                    'shape_pts' => $secondShapePoints,
                ];

                // Add final walk leg if the second leg alights at a nearby (non-exact) destination stop
                $finalWalkDist = 0.0;
                if (! in_array($secondLeg->final_stop_id, $destStopIds)) {
                    $finalWalkDist = $this->getWalkingDistanceFromCache($stopCache, $secondLeg->final_stop_id, $firstDest->id);
                    if ($finalWalkDist > 0.05) {
                        $finalWalkDuration = $this->calculateWalkingTimeFromCache($stopCache, $secondLeg->final_stop_id, $firstDest->id, $walkingSpeedKmh);
                        $finalWalkStart = substr($secondLeg->final_arrival, 0, 5);
                        $finalWalkEnd = date('H:i', strtotime($finalWalkStart) + ($finalWalkDuration * 60));
                        $finalFromCoords = $this->getStopCoordsFromCache($stopCache, $secondLeg->final_stop_id);

                        $legs[] = [
                            'type' => 'walk',
                            'from' => $this->getStopNameFromCache($stopCache, $secondLeg->final_stop_id),
                            'to' => $destinationName,
                            'from_lat' => $finalFromCoords['lat'] ?? null,
                            'from_lon' => $finalFromCoords['lon'] ?? null,
                            'to_lat' => (float) $firstDest->stop_lat,
                            'to_lon' => (float) $firstDest->stop_lon,
                            'distance' => $finalWalkDist,
                            'duration' => $finalWalkDuration,
                            'start_time' => $finalWalkStart,
                            'end_time' => $finalWalkEnd,
                        ];
                    }
                }

                $journey = $this->buildJourney($legs, $time);

                // FIX 8: midnight-safe scoring
                $totalTimeMinutes = $this->timeDiffMinutes($journey['leave_at'], $journey['arrival_time']);
                $journey['score'] = 10000 - ($totalTimeMinutes * 50) - ($journey['transfers'] * 200) - (($originWalkDist + $transferWalkDist + $finalWalkDist) * 10);

                if ($minimumDepartureTime && $date) {
                    $leaveAtDateTime = Carbon::parse($date . ' ' . $journey['leave_at']);
                    $minimumDateTime = Carbon::parse($date . ' ' . $minimumDepartureTime);
                    if ($leaveAtDateTime->lt($minimumDateTime)) {
                        continue;
                    }
                }

                $foundJourneys[] = $journey;

                if (count($foundJourneys) >= 20) {
                    break;
                }
            }
        }

        // If still nothing found, try slightly wider destination radius using PHP cache (no extra DB call)
        if (empty($foundJourneys)) {
            $widerDestStops = $this->findNearbyStopIdsFromCache($stopCache, (float) $firstDest->stop_lat, (float) $firstDest->stop_lon, 2.0, $expandedDestStopIds);
            foreach ($widerDestStops as $nearby) {
                if (! in_array($nearby->id, $expandedDestStopIds)) {
                    $expandedDestStopIds[] = $nearby->id;
                }
            }
        }

        return $this->sortAndLimitJourneys($foundJourneys, 5);
    }

    /**
     * FIX 6: Sort and deduplicate journeys by route identity instead of departure hour.
     *
     * Old behaviour: one result per hour → discards valid options that depart the same hour.
     * New behaviour: one best-scored journey per (first_route_name + transfer_count),
     * returning up to $limit sorted by score descending.
     *
     * @param  array<int, array<string, mixed>>  $journeys
     * @return array<int, array<string, mixed>>
     */
    private function sortAndLimitJourneys(array $journeys, int $limit): array
    {
        usort($journeys, fn($a, $b) => ($b['score'] ?? 0) <=> ($a['score'] ?? 0));

        $seen = [];
        $result = [];

        foreach ($journeys as $journey) {
            $firstTransit = collect($journey['legs'])->firstWhere('type', 'transit');
            $routeKey = ($firstTransit['route_name'] ?? 'unknown') . '_' . ($journey['transfers'] ?? 0);

            if (! isset($seen[$routeKey])) {
                $seen[$routeKey] = true;
                $result[] = $journey;
            }

            if (count($result) >= $limit) {
                break;
            }
        }

        return $result;
    }

    private function findNearbyStops($stops, $maxDistance)
    {
        $nearby = [];
        foreach ($stops as $stop) {
            $nearbyStops = DB::table('stops')
                ->select('id', 'stop_name', 'stop_lat', 'stop_lon')
                ->whereRaw('
                    (6371 * acos(cos(radians(?)) * cos(radians(stop_lat)) * 
                    cos(radians(stop_lon) - radians(?)) + sin(radians(?)) * 
                    sin(radians(stop_lat)))) <= ?
                ', [$stop->stop_lat, $stop->stop_lon, $stop->stop_lat, $maxDistance])
                ->where('id', '!=', $stop->id)
                ->get()
                ->toArray();

            $nearby = array_merge($nearby, $nearbyStops);
        }

        return $nearby;
    }

    // Cleaned up - removed duplicate broken code below

    private function findNearbyStopsFromCoords($lat, $lon, $maxDistance)
    {
        return DB::table('stops')
            ->select('id', 'stop_name', 'stop_lat', 'stop_lon')
            ->whereRaw('
                (6371 * acos(cos(radians(?)) * cos(radians(stop_lat)) * 
                cos(radians(stop_lon) - radians(?)) + sin(radians(?)) * 
                sin(radians(stop_lat)))) <= ?
            ', [$lat, $lon, $lat, $maxDistance])
            ->get()
            ->toArray();
    }

    // ── Stop Resolution ───────────────────────────────────────────────────────

    /**
     * Resolve stop DB IDs from a stop name, preferring GPS coordinates when available.
     *
     * Resolution order:
     *   1. Coordinate proximity (< ~220m) — most precise, eliminates duplicate-name confusion.
     *   2. Exact name match.
     *   3. Prefix name match (fallback).
     *
     * @return list<int>
     */
    private function resolveStopIds(string $stopName, ?float $lat, ?float $lon): array
    {
        if ($lat !== null && $lon !== null) {
            $nearbyStops = Stop::withoutGlobalScopes()
                ->select('id', 'stop_name', 'stop_lat', 'stop_lon')
                ->whereRaw('ABS(stop_lat - ?) < 0.003 AND ABS(stop_lon - ?) < 0.004', [$lat, $lon])
                ->get();

            if ($nearbyStops->isNotEmpty()) {
                $ranked = $nearbyStops->map(function ($stop) use ($lat, $lon) {
                    return [
                        'id' => (int) $stop->id,
                        'distance' => $this->haversineDistance($lat, $lon, (float) $stop->stop_lat, (float) $stop->stop_lon),
                    ];
                })->sortBy('distance')->values();

                $minDistance = (float) $ranked->first()['distance'];
                $clusterMaxDistance = max(0.06, $minDistance + 0.03);

                $ids = $ranked
                    ->filter(fn($row) => $row['distance'] <= $clusterMaxDistance)
                    ->pluck('id')
                    ->all();

                // Include nearby sibling platforms with the same stop name.
                // This avoids missing valid journeys that arrive/depart on another quay of the same station.
                $sameNameNearby = $nearbyStops
                    ->filter(function ($stop) use ($stopName) {
                        return mb_strtolower((string) $stop->stop_name) === mb_strtolower($stopName);
                    })
                    ->map(function ($stop) use ($lat, $lon) {
                        return [
                            'id' => (int) $stop->id,
                            'distance' => $this->haversineDistance($lat, $lon, (float) $stop->stop_lat, (float) $stop->stop_lon),
                        ];
                    })
                    ->filter(fn($row) => $row['distance'] <= 0.35)
                    ->pluck('id')
                    ->all();

                if (! empty($sameNameNearby)) {
                    $ids = array_values(array_unique(array_merge($ids, $sameNameNearby)));
                }

                if (! empty($ids)) {
                    return $ids;
                }
            }
        }

        $ids = Stop::withoutGlobalScopes()
            ->where('stop_name', $stopName)
            ->pluck('id')
            ->toArray();

        if (! empty($ids)) {
            return $ids;
        }

        return Stop::withoutGlobalScopes()
            ->where('stop_name', 'LIKE', $stopName . '%')
            ->pluck('id')
            ->toArray();
    }

    // ── Stop Cache Helpers (FIX 1) ────────────────────────────────────────────

    /**
     * Pre-load all stops into a PHP array keyed by stop.id.
     * One call replaces ~300 individual DB queries per transfer search.
     *
     * @return array<int, object{id: int, stop_name: string, stop_lat: string, stop_lon: string}>
     */
    private function preloadStopCache(): array
    {
        $cache = [];
        foreach (DB::table('stops')->get(['id', 'stop_name', 'stop_lat', 'stop_lon']) as $stop) {
            $cache[$stop->id] = $stop;
        }

        return $cache;
    }

    /**
     * @param  array<int, object>  $cache
     */
    private function getStopNameFromCache(array $cache, int $stopId): string
    {
        return isset($cache[$stopId]) ? $cache[$stopId]->stop_name : 'Unknown';
    }

    /**
     * @param  array<int, object>  $cache
     * @return array{lat: float, lon: float}|null
     */
    private function getStopCoordsFromCache(array $cache, int $stopId): ?array
    {
        if (! isset($cache[$stopId])) {
            return null;
        }

        return ['lat' => (float) $cache[$stopId]->stop_lat, 'lon' => (float) $cache[$stopId]->stop_lon];
    }

    /**
     * @param  array<int, object>  $cache
     */
    private function getWalkingDistanceFromCache(array $cache, int $fromId, int $toId): float
    {
        if ($fromId === $toId) {
            return 0.0;
        }

        $from = $cache[$fromId] ?? null;
        $to = $cache[$toId] ?? null;

        if (! $from || ! $to) {
            return 0.0;
        }

        return $this->haversineDistance((float) $from->stop_lat, (float) $from->stop_lon, (float) $to->stop_lat, (float) $to->stop_lon);
    }

    /**
     * @param  array<int, object>  $cache
     */
    private function distancePointToStopFromCache(array $cache, float $lat, float $lon, int $toStopId): float
    {
        $to = $cache[$toStopId] ?? null;
        if (! $to) {
            return 0.0;
        }

        return $this->haversineDistance($lat, $lon, (float) $to->stop_lat, (float) $to->stop_lon);
    }

    /**
     * @param  array<int, object>  $cache
     */
    private function distanceStopToPointFromCache(array $cache, int $fromStopId, float $lat, float $lon): float
    {
        $from = $cache[$fromStopId] ?? null;
        if (! $from) {
            return 0.0;
        }

        return $this->haversineDistance((float) $from->stop_lat, (float) $from->stop_lon, $lat, $lon);
    }

    private function calculateWalkingMinutes(float $distanceKm, float $speedKmh): int
    {
        if ($distanceKm <= 0 || $speedKmh <= 0) {
            return 0;
        }

        return max(1, (int) round(($distanceKm / $speedKmh) * 60));
    }

    /**
     * @param  Collection<int, object>  $stops
     */
    private function pickReferenceStop($stops, ?float $lat, ?float $lon): ?object
    {
        if ($stops->isEmpty()) {
            return null;
        }

        if ($lat === null || $lon === null) {
            return $stops->first();
        }

        return $stops->sortBy(function ($stop) use ($lat, $lon) {
            return $this->haversineDistance($lat, $lon, (float) $stop->stop_lat, (float) $stop->stop_lon);
        })->first();
    }

    /**
     * @param  array<int, object>  $cache
     */
    private function calculateWalkingTimeFromCache(array $cache, int $fromId, int $toId, float $speedKmh): int
    {
        $distance = $this->getWalkingDistanceFromCache($cache, $fromId, $toId);

        return $distance > 0 ? max(1, (int) round(($distance / $speedKmh) * 60)) : 0;
    }

    /**
     * Find stops near a coordinate using the pre-loaded cache — zero DB queries.
     * Replaces findNearbyStops() and findNearbyStopsFromCoords() inside loops.
     *
     * @param  array<int, object>  $cache
     * @param  list<int>  $excludeIds
     * @return list<object>
     */
    private function findNearbyStopIdsFromCache(array $cache, float $lat, float $lon, float $maxKm, array $excludeIds = []): array
    {
        $excludeSet = array_flip($excludeIds);
        $results = [];

        foreach ($cache as $id => $stop) {
            if (isset($excludeSet[$id])) {
                continue;
            }

            $dist = $this->haversineDistance($lat, $lon, (float) $stop->stop_lat, (float) $stop->stop_lon);
            if ($dist <= $maxKm) {
                $s = clone $stop;
                $s->distance = $dist;
                $results[] = $s;
            }
        }

        return $results;
    }

    /**
     * Fetch stop_times for a trip segment as a formatted array.
     *
     * @return array<int, array{name: string, lat: float, lon: float, arrival: string, departure: string, is_intermediate: bool}>
     */
    private function fetchTripStops(int $tripId, int $fromSequence, int $toSequence): array
    {
        try {
            $stops = DB::table('stop_times')
                ->join('stops', 'stop_times.stop_id', '=', 'stops.id')
                ->where('stop_times.trip_id', $tripId)
                ->where('stop_times.stop_sequence', '>=', $fromSequence)
                ->where('stop_times.stop_sequence', '<=', $toSequence)
                ->orderBy('stop_times.stop_sequence')
                ->select('stops.stop_name', 'stops.stop_lat', 'stops.stop_lon', 'stop_times.arrival_time', 'stop_times.departure_time')
                ->get()
                ->values()
                ->map(fn($s) => [
                    'name' => $s->stop_name,
                    'lat' => $s->stop_lat,
                    'lon' => $s->stop_lon,
                    'arrival' => substr($s->arrival_time, 0, 5),
                    'departure' => substr($s->departure_time, 0, 5),
                    'is_intermediate' => false,
                ])
                ->toArray();

            foreach ($stops as $i => &$s) {
                $s['is_intermediate'] = ($i > 0 && $i < count($stops) - 1);
            }
            unset($s);

            return $stops;
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Midnight-safe time difference in minutes.
     * Handles GTFS times that cross midnight (e.g. 23:50 → 00:10 = 20 min, not negative).
     */
    private function timeDiffMinutes(string $from, string $to): int
    {
        $f = strtotime('1970-01-01 ' . $from);
        $t = strtotime('1970-01-01 ' . $to);

        if ($t < $f) {
            $t += 86400;
        }

        return max(0, (int) round(($t - $f) / 60));
    }

    // ── Legacy helpers kept for any external callers ──────────────────────────

    private function getStopNameById($stopId): string
    {
        $stop = DB::table('stops')->where('id', $stopId)->first();

        return $stop ? $stop->stop_name : 'Unknown';
    }

    private function getStopCoordsById($stopId): ?array
    {
        $stop = DB::table('stops')->where('id', $stopId)->select('stop_lat', 'stop_lon')->first();

        return $stop ? ['lat' => $stop->stop_lat, 'lon' => $stop->stop_lon] : null;
    }

    private function getWalkingDistanceById($fromStopId, $toStopId): float
    {
        if ($fromStopId == $toStopId) {
            return 0.0;
        }

        $stops = DB::table('stops')->whereIn('id', [$fromStopId, $toStopId])->get();
        if ($stops->count() < 2) {
            return 0.0;
        }

        $from = $stops->where('id', $fromStopId)->first();
        $to = $stops->where('id', $toStopId)->first();

        return $this->haversineDistance((float) $from->stop_lat, (float) $from->stop_lon, (float) $to->stop_lat, (float) $to->stop_lon);
    }

    private function buildJourney($legs, $startTime)
    {
        // Remove legs with 0 distance/duration walking
        $legs = array_filter($legs, function ($leg) {
            if ($leg['type'] === 'walk') {
                return $leg['distance'] > 0 || $leg['from'] !== $leg['to'];
            }

            return true;
        });

        $legs = array_values($legs);

        // Calculate total duration from first leg start to last leg end
        $firstLeg = $legs[0] ?? null;
        $lastLeg = end($legs);

        // Get journey start time (walk start or bus departure)
        $journeyStart = $firstLeg['type'] === 'walk' ? $firstLeg['start_time'] : $firstLeg['departure_time'] ?? $startTime;

        // Get journey end time (walk end or bus arrival)
        $journeyEnd = $lastLeg['type'] === 'walk' ? $lastLeg['end_time'] : $lastLeg['arrival_time'] ?? $startTime;

        $duration = (strtotime($journeyEnd) - strtotime($journeyStart)) / 60;

        // For display - first bus departure time
        $firstTransit = collect($legs)->firstWhere('type', 'transit');
        $departureTime = $firstTransit['departure_time'] ?? $startTime;

        // "Leave at" time is the journey start
        $leaveAt = $journeyStart;

        return [
            'connection_type' => 'transfer',
            'leave_at' => $leaveAt, // When user should leave their location
            'departure_time' => $departureTime, // When first bus departs
            'arrival_time' => $journeyEnd, // When journey actually ends (including final walk)
            'duration' => round($duration) . ' min',
            'transfers' => count(array_filter($legs, fn($l) => $l['type'] === 'transit')) - 1,
            'legs' => $legs,
        ];
    }

    /**
     * Remove accents from string for accent-insensitive search
     * Handles French accented characters: é, è, ê, à, â, ô, ù, û, ï, ç, etc.
     */
    private function removeAccents($string)
    {
        $accents = [
            'à' => 'a',
            'á' => 'a',
            'â' => 'a',
            'ã' => 'a',
            'ä' => 'a',
            'å' => 'a',
            'è' => 'e',
            'é' => 'e',
            'ê' => 'e',
            'ë' => 'e',
            'ì' => 'i',
            'í' => 'i',
            'î' => 'i',
            'ï' => 'i',
            'ò' => 'o',
            'ó' => 'o',
            'ô' => 'o',
            'õ' => 'o',
            'ö' => 'o',
            'ù' => 'u',
            'ú' => 'u',
            'û' => 'u',
            'ü' => 'u',
            'ç' => 'c',
            'ñ' => 'n',
            'À' => 'A',
            'Á' => 'A',
            'Â' => 'A',
            'Ã' => 'A',
            'Ä' => 'A',
            'Å' => 'A',
            'È' => 'E',
            'É' => 'E',
            'Ê' => 'E',
            'Ë' => 'E',
            'Ì' => 'I',
            'Í' => 'I',
            'Î' => 'I',
            'Ï' => 'I',
            'Ò' => 'O',
            'Ó' => 'O',
            'Ô' => 'O',
            'Õ' => 'O',
            'Ö' => 'O',
            'Ù' => 'U',
            'Ú' => 'U',
            'Û' => 'U',
            'Ü' => 'U',
            'Ç' => 'C',
            'Ñ' => 'N',
        ];

        return strtr($string, $accents);
    }

    /**
     * Clips a shape to only the portion between two stop coordinates.
     * Finds the nearest shape point to each stop and returns only those points in between.
     *
     * @param  array<array{shape_pt_lat: float, shape_pt_lon: float, shape_pt_sequence: int}>  $shapePoints
     * @return array<int, array{float, float}>
     */
    private function clipShapeToStops(array $shapePoints, float $originLat, float $originLon, float $destLat, float $destLon): array
    {
        if (empty($shapePoints)) {
            return [];
        }

        $originIdx = 0;
        $destIdx = count($shapePoints) - 1;
        $minOriginDist = PHP_FLOAT_MAX;
        $minDestDist = PHP_FLOAT_MAX;

        foreach ($shapePoints as $i => $pt) {
            $dOrigin = $this->haversineDistance($originLat, $originLon, $pt[0], $pt[1]);
            $dDest = $this->haversineDistance($destLat, $destLon, $pt[0], $pt[1]);

            if ($dOrigin < $minOriginDist) {
                $minOriginDist = $dOrigin;
                $originIdx = $i;
            }
            if ($dDest < $minDestDist) {
                $minDestDist = $dDest;
                $destIdx = $i;
            }
        }

        // Ensure we always go forward along the shape
        if ($originIdx > $destIdx) {
            [$originIdx, $destIdx] = [$destIdx, $originIdx];
        }

        return array_slice($shapePoints, $originIdx, $destIdx - $originIdx + 1);
    }

    /**
     * Haversine distance between two lat/lon pairs (in km).
     */
    private function haversineDistance(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $r = 6371;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;

        return $r * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    /**
     * Loads all shape points for a shape_id and clips them to the stop segment.
     *
     * @return array<int, array{float, float}>
     */
    /**
     * FIX 3: Load shape points with a per-request cache.
     * The first call for a given shape_id fetches from DB and caches the result.
     * Subsequent calls for the same shape (common when multiple trips share a shape)
     * are served from memory — zero additional DB queries.
     *
     * @return list<array{float, float}>
     */
    private function getClippedShapePoints(string $shapeId, float $originLat, float $originLon, float $destLat, float $destLon): array
    {
        if (! isset($this->shapeCache[$shapeId])) {
            $this->shapeCache[$shapeId] = DB::table('shapes')
                ->where('shape_id', $shapeId)
                ->orderBy('shape_pt_sequence')
                ->get(['shape_pt_lat', 'shape_pt_lon'])
                ->map(fn($pt) => [(float) $pt->shape_pt_lat, (float) $pt->shape_pt_lon])
                ->toArray();
        }

        return $this->clipShapeToStops($this->shapeCache[$shapeId], $originLat, $originLon, $destLat, $destLon);
    }

    public function getRouteStops(Request $request, $routeShortName)
    {
        // Find the canonical trip for this route — the one with the most stops
        $tripId = DB::table('stop_times')
            ->select('trip_id', DB::raw('count(*) as cnt'))
            ->whereIn('trip_id', function ($q) use ($routeShortName) {
                $q->select('t.id')->from('trips as t')
                    ->join('routes as r', 't.route_id', '=', 'r.id')
                    ->where('r.route_short_name', $routeShortName);
            })
            ->groupBy('trip_id')
            ->orderByDesc('cnt')
            ->value('trip_id');

        if (! $tripId) {
            return response()->json(['stops' => []]);
        }

        $stops = DB::table('stop_times as st')
            ->join('stops as s', 'st.stop_id', '=', 's.id')
            ->where('st.trip_id', $tripId)
            ->orderBy('st.stop_sequence')
            ->select('s.stop_name as name', 's.stop_lat as lat', 's.stop_lon as lon')
            ->get();

        return response()->json(['stops' => $stops]);
    }

    public function getAllRoutesWithShapes()
    {
        return response()->json([
            'routes' => $this->buildRouteShapePayload(),
        ]);
    }

    /**
     * Build map route polylines with bounded memory usage.
     *
     * We keep one representative shape per route and direction, then sample
     * shape points by sequence to prevent large-tenant OOM errors.
     *
     * @return array<int, array<string, mixed>>
     */
    private function buildRouteShapePayload(?int $tenantId = null): array
    {
        $cacheKey = $tenantId === null
            ? 'route_shapes:all'
            : 'route_shapes:tenant:' . $tenantId;

        return Cache::remember($cacheKey, now()->addHours(12), function () use ($tenantId): array {
            $routeShapesQuery = DB::table('routes as r')
                ->join('trips as t', 'r.id', '=', 't.route_id')
                ->leftJoin('users as u', 'r.tenant_id', '=', 'u.id')
                ->whereNotNull('t.shape_id');

            if ($tenantId !== null) {
                $routeShapesQuery->where('r.tenant_id', $tenantId);
            }

            $routeShapes = $routeShapesQuery
                ->groupBy('r.route_short_name', DB::raw('COALESCE(t.direction_id, 0)'))
                ->select(
                    'r.route_short_name',
                    DB::raw('MAX(r.route_long_name) as route_long_name'),
                    DB::raw('MAX(r.route_color) as route_color'),
                    DB::raw('MAX(r.route_text_color) as route_text_color'),
                    DB::raw('MAX(u.name) as agency_name'),
                    DB::raw('MIN(t.shape_id) as shape_id')
                )
                ->orderBy('r.route_short_name')
                ->get();

            $shapeIds = $routeShapes->pluck('shape_id')->filter()->unique()->values()->toArray();
            if (empty($shapeIds)) {
                return [];
            }

            $shapePoints = [];
            DB::table('shapes')
                ->whereIn('shape_id', $shapeIds)
                ->where(function ($query) {
                    $query->where('shape_pt_sequence', 1)
                        ->orWhereRaw('MOD(shape_pt_sequence, 4) = 0');
                })
                ->orderBy('shape_id')
                ->orderBy('shape_pt_sequence')
                ->select('shape_id', 'shape_pt_lat', 'shape_pt_lon')
                ->chunk(10000, function ($rows) use (&$shapePoints) {
                    foreach ($rows as $row) {
                        $shapePoints[$row->shape_id][] = [
                            (float) $row->shape_pt_lat,
                            (float) $row->shape_pt_lon,
                        ];
                    }
                });

            $routes = [];
            foreach ($routeShapes as $routeShape) {
                if (! isset($shapePoints[$routeShape->shape_id]) || count($shapePoints[$routeShape->shape_id]) < 2) {
                    continue;
                }

                $routes[] = [
                    'route_short_name' => $routeShape->route_short_name,
                    'route_long_name' => $routeShape->route_long_name,
                    'route_color' => $routeShape->route_color,
                    'route_text_color' => $routeShape->route_text_color,
                    'agency_name' => $routeShape->agency_name,
                    'shape_pts' => $shapePoints[$routeShape->shape_id],
                ];
            }

            return $routes;
        });
    }

    public function getTerritories()
    {
        $sorted = Cache::remember('territories:list', now()->addHours(12), function () {
            $rows = DB::table('stops as s')
                ->join('users as u', 's.tenant_id', '=', 'u.id')
                ->whereNotNull('s.tenant_id')
                ->whereNotNull('s.stop_lat')
                ->whereNotNull('s.stop_lon')
                ->select('s.tenant_id', 's.stop_lat', 's.stop_lon', 's.city', 'u.name as operator_name')
                ->orderBy('s.city')
                ->get();

            $territories = collect();

            foreach ($rows->groupBy('tenant_id') as $tenantId => $tenantRows) {
                $operatorName = (string) $tenantRows->first()->operator_name;
                $stopPoints = $tenantRows->map(fn($row) => [(float) $row->stop_lat, (float) $row->stop_lon])->all();
                if (count($stopPoints) < 4) {
                    continue;
                }

                $shapePoints = $this->getShapePointsForTenantCluster((int) $tenantId, $stopPoints);
                $points = array_merge($stopPoints, $shapePoints);

                $polygon = $this->buildConcaveTerritoryPolygon($points);
                if (count($polygon) < 4) {
                    $polygon = $this->buildConvexHull($points);
                }

                if (count($polygon) < 4) {
                    $polygon = $this->buildBoundingPolygon($points);
                }

                $bbox = $this->calculateBbox($polygon);

                $dominantCity = $tenantRows
                    ->pluck('city')
                    ->filter(fn($city) => filled($city))
                    ->countBy()
                    ->sortDesc()
                    ->keys()
                    ->first();

                $territories->push([
                    'tenant_id' => (int) $tenantId,
                    'name' => $dominantCity ? $operatorName . ' - ' . $dominantCity : $operatorName . ' network',
                    'city' => $dominantCity,
                    'operator_name' => $operatorName,
                    'stops_count' => $tenantRows->count(),
                    'bbox' => $bbox,
                    'polygon' => $polygon,
                ]);
            }

            return $territories
                ->sortByDesc('stops_count')
                ->values();
        });

        return response()->json(['territories' => $sorted]);
    }

    public function getRoutesWithShapesByTerritory(Request $request)
    {
        $validated = $request->validate([
            'tenant_id' => 'required|integer|exists:users,id',
        ]);

        $tenantId = (int) $validated['tenant_id'];

        return response()->json([
            'routes' => $this->buildRouteShapePayload($tenantId),
        ]);
    }

    /**
     * @param  array<int, array{0: float, 1: float}>  $points
     * @return array<int, array{0: float, 1: float}>
     */
    private function buildConvexHull(array $points): array
    {
        $uniquePoints = [];
        foreach ($points as [$lat, $lon]) {
            $key = round($lat, 6) . '|' . round($lon, 6);
            $uniquePoints[$key] = [$lat, $lon];
        }

        $points = array_values($uniquePoints);
        if (count($points) <= 2) {
            return $this->buildBoundingPolygon($points);
        }

        usort($points, function (array $a, array $b): int {
            return $a[1] <=> $b[1] ?: ($a[0] <=> $b[0]);
        });

        $lower = [];
        foreach ($points as $point) {
            while (count($lower) >= 2 && $this->cross($lower[count($lower) - 2], $lower[count($lower) - 1], $point) <= 0) {
                array_pop($lower);
            }

            $lower[] = $point;
        }

        $upper = [];
        for ($i = count($points) - 1; $i >= 0; $i--) {
            $point = $points[$i];
            while (count($upper) >= 2 && $this->cross($upper[count($upper) - 2], $upper[count($upper) - 1], $point) <= 0) {
                array_pop($upper);
            }

            $upper[] = $point;
        }

        array_pop($lower);
        array_pop($upper);
        $hull = array_merge($lower, $upper);

        if (count($hull) < 3) {
            return $this->buildBoundingPolygon($points);
        }

        $hull[] = $hull[0];

        return $hull;
    }

    /**
     * Build a smoother territory polygon from stop points:
     * 1) angle-ordered radial perimeter (organic shell)
     * 2) Chaikin corner cutting for visual smoothness
     * 3) clamp to bbox to avoid runaway coordinates
     *
     * @param  array<int, array{0: float, 1: float}>  $points
     * @return array<int, array{0: float, 1: float}>
     */
    private function buildConcaveTerritoryPolygon(array $points): array
    {
        $uniquePoints = [];
        foreach ($points as [$lat, $lon]) {
            $key = round($lat, 6) . '|' . round($lon, 6);
            $uniquePoints[$key] = [$lat, $lon];
        }

        $points = array_values($uniquePoints);
        if (count($points) < 4) {
            return $this->buildConvexHull($points);
        }

        $hull = $this->buildConvexHull($points);
        if (count($hull) < 4) {
            return $this->buildBoundingPolygon($points);
        }

        $ring = array_slice($hull, 0, -1);
        $centroid = $this->calculateCentroid($ring);

        // Expand slightly so smoothing does not cut valid route extremities.
        $expanded = array_map(function (array $pt) use ($centroid): array {
            return [
                $centroid[0] + (($pt[0] - $centroid[0]) * 1.07),
                $centroid[1] + (($pt[1] - $centroid[1]) * 1.07),
            ];
        }, $ring);

        $ring = $expanded;

        $ring = $this->deduplicateSequentialPoints($ring);
        if (count($ring) < 4) {
            return $this->buildConvexHull($points);
        }

        // Ensure orientation consistency before smoothing.
        if ($this->signedArea($ring) < 0) {
            $ring = array_reverse($ring);
        }

        $ring = $this->simplifyRing($ring, 0.00008);
        if (count($ring) < 4) {
            return $this->buildConvexHull($points);
        }

        $smoothed = $this->chaikinSmooth($ring, 3, 0.25);
        $smoothed = $this->simplifyRing($smoothed, 0.00012);

        $bbox = $this->calculateBbox($points);
        $latPad = max(0.002, ($bbox['max_lat'] - $bbox['min_lat']) * 0.12);
        $lonPad = max(0.002, ($bbox['max_lon'] - $bbox['min_lon']) * 0.12);

        $minLat = $bbox['min_lat'] - $latPad;
        $maxLat = $bbox['max_lat'] + $latPad;
        $minLon = $bbox['min_lon'] - $lonPad;
        $maxLon = $bbox['max_lon'] + $lonPad;

        $polygon = array_map(function (array $pt) use ($minLat, $maxLat, $minLon, $maxLon): array {
            return [
                max($minLat, min($maxLat, $pt[0])),
                max($minLon, min($maxLon, $pt[1])),
            ];
        }, $smoothed);

        $polygon = $this->deduplicateSequentialPoints($polygon);
        if (count($polygon) < 4) {
            return $this->buildConvexHull($points);
        }

        $polygon[] = $polygon[0];

        return $polygon;
    }

    /**
     * @param  array{0: float, 1: float}  $a
     * @param  array{0: float, 1: float}  $b
     * @param  array{0: float, 1: float}  $c
     */
    private function cross(array $a, array $b, array $c): float
    {
        return ($b[1] - $a[1]) * ($c[0] - $a[0]) - ($b[0] - $a[0]) * ($c[1] - $a[1]);
    }

    /**
     * @param  array<int, array{0: float, 1: float}>  $points
     * @return array{0: float, 1: float}
     */
    private function calculateCentroid(array $points): array
    {
        $count = count($points);
        if ($count === 0) {
            return [0.0, 0.0];
        }

        $sumLat = 0.0;
        $sumLon = 0.0;

        foreach ($points as [$lat, $lon]) {
            $sumLat += $lat;
            $sumLon += $lon;
        }

        return [$sumLat / $count, $sumLon / $count];
    }

    /**
     * @param  array{0: float, 1: float}  $a
     * @param  array{0: float, 1: float}  $b
     */
    private function distanceSquared(array $a, array $b): float
    {
        return ($a[0] - $b[0]) ** 2 + ($a[1] - $b[1]) ** 2;
    }

    /**
     * Fetch route geometry points for one tenant around the current stop cluster only.
     *
     * @param  array<int, array{0: float, 1: float}>  $clusterPoints
     * @return array<int, array{0: float, 1: float}>
     */
    private function getShapePointsForTenantCluster(int $tenantId, array $clusterPoints): array
    {
        $rows = DB::table('routes as r')
            ->join('trips as t', 'r.id', '=', 't.route_id')
            ->join('shapes as sh', 't.shape_id', '=', 'sh.shape_id')
            ->where('r.tenant_id', $tenantId)
            ->whereNotNull('t.shape_id')
            ->whereRaw('MOD(sh.shape_pt_sequence, 4) = 0 OR sh.shape_pt_sequence = 1')
            ->distinct()
            ->limit(12000)
            ->get(['sh.shape_pt_lat', 'sh.shape_pt_lon']);

        $shapePoints = [];
        foreach ($rows as $row) {
            $shapePoints[] = [(float) $row->shape_pt_lat, (float) $row->shape_pt_lon];
        }

        return $shapePoints;
    }

    /**
     * @param  array<int, float|null>  $values
     * @return array<int, float>
     */
    private function interpolateCircularMissingValues(array $values): array
    {
        $count = count($values);
        $known = array_filter($values, fn($value): bool => $value !== null);

        if (empty($known)) {
            return array_fill(0, $count, 0.0);
        }

        for ($i = 0; $i < $count; $i++) {
            if ($values[$i] !== null) {
                continue;
            }

            $prevIndex = null;
            for ($step = 1; $step <= $count; $step++) {
                $candidate = ($i - $step + $count) % $count;
                if ($values[$candidate] !== null) {
                    $prevIndex = $candidate;
                    break;
                }
            }

            $nextIndex = null;
            for ($step = 1; $step <= $count; $step++) {
                $candidate = ($i + $step) % $count;
                if ($values[$candidate] !== null) {
                    $nextIndex = $candidate;
                    break;
                }
            }

            if ($prevIndex === null && $nextIndex === null) {
                $values[$i] = 0.0;
            } elseif ($prevIndex === null) {
                $values[$i] = (float) $values[$nextIndex];
            } elseif ($nextIndex === null) {
                $values[$i] = (float) $values[$prevIndex];
            } else {
                $forward = ($nextIndex - $prevIndex + $count) % $count;
                $offset = ($i - $prevIndex + $count) % $count;
                $t = $forward === 0 ? 0.0 : $offset / $forward;

                $values[$i] = ((1 - $t) * (float) $values[$prevIndex]) + ($t * (float) $values[$nextIndex]);
            }
        }

        return array_map(fn($value): float => (float) $value, $values);
    }

    /**
     * @param  array<int, float>  $values
     * @return array<int, float>
     */
    private function smoothCircularValues(array $values, int $windowRadius): array
    {
        $count = count($values);
        if ($count === 0 || $windowRadius <= 0) {
            return $values;
        }

        $smoothed = [];

        for ($i = 0; $i < $count; $i++) {
            $sum = 0.0;
            $weightSum = 0.0;

            for ($offset = -$windowRadius; $offset <= $windowRadius; $offset++) {
                $idx = ($i + $offset + $count) % $count;
                $weight = ($windowRadius + 1) - abs($offset);
                $sum += $values[$idx] * $weight;
                $weightSum += $weight;
            }

            $smoothed[$i] = $weightSum > 0 ? $sum / $weightSum : $values[$i];
        }

        return $smoothed;
    }

    /**
     * Limit bucket-to-bucket radius jumps to reduce narrow spikes.
     *
     * @param  array<int, float>  $values
     * @return array<int, float>
     */
    private function limitCircularDistanceJumps(array $values, float $maxRatioDelta, int $passes): array
    {
        $count = count($values);
        if ($count < 3 || $passes <= 0) {
            return $values;
        }

        $result = $values;

        for ($pass = 0; $pass < $passes; $pass++) {
            $next = $result;

            for ($i = 0; $i < $count; $i++) {
                $prev = $result[($i - 1 + $count) % $count];
                $curr = $result[$i];
                $nxt = $result[($i + 1) % $count];
                $neighborAvg = ($prev + $nxt) / 2;

                if ($neighborAvg <= 0.0) {
                    continue;
                }

                $maxAllowed = $neighborAvg * (1 + $maxRatioDelta);
                $minAllowed = $neighborAvg * (1 - $maxRatioDelta);

                if ($curr > $maxAllowed) {
                    $next[$i] = $maxAllowed;
                } elseif ($curr < $minAllowed) {
                    $next[$i] = $minAllowed;
                }
            }

            $result = $next;
        }

        return $result;
    }

    /**
     * @param  array<int, array{0: float, 1: float}>  $ring
     */
    private function signedArea(array $ring): float
    {
        $area = 0.0;
        $count = count($ring);
        if ($count < 3) {
            return 0.0;
        }

        for ($i = 0; $i < $count; $i++) {
            $j = ($i + 1) % $count;
            $area += ($ring[$i][1] * $ring[$j][0]) - ($ring[$j][1] * $ring[$i][0]);
        }

        return $area / 2.0;
    }

    /**
     * @param  array<int, array{0: float, 1: float}>  $ring
     * @return array<int, array{0: float, 1: float}>
     */
    private function chaikinSmooth(array $ring, int $iterations = 2, float $ratio = 0.25): array
    {
        $count = count($ring);
        if ($count < 3) {
            return $ring;
        }

        $result = $ring;

        for ($iter = 0; $iter < $iterations; $iter++) {
            $smoothed = [];
            $n = count($result);

            for ($i = 0; $i < $n; $i++) {
                $p0 = $result[$i];
                $p1 = $result[($i + 1) % $n];

                $q = [
                    (1 - $ratio) * $p0[0] + $ratio * $p1[0],
                    (1 - $ratio) * $p0[1] + $ratio * $p1[1],
                ];

                $r = [
                    $ratio * $p0[0] + (1 - $ratio) * $p1[0],
                    $ratio * $p0[1] + (1 - $ratio) * $p1[1],
                ];

                $smoothed[] = $q;
                $smoothed[] = $r;
            }

            $result = $smoothed;
        }

        return $result;
    }

    /**
     * @param  array<int, array{0: float, 1: float}>  $points
     * @return array<int, array{0: float, 1: float}>
     */
    private function deduplicateSequentialPoints(array $points): array
    {
        $result = [];
        foreach ($points as $point) {
            if (empty($result)) {
                $result[] = $point;

                continue;
            }

            $last = $result[count($result) - 1];
            if (abs($last[0] - $point[0]) < 0.000001 && abs($last[1] - $point[1]) < 0.000001) {
                continue;
            }

            $result[] = $point;
        }

        if (count($result) > 1) {
            $first = $result[0];
            $last = $result[count($result) - 1];
            if (abs($first[0] - $last[0]) < 0.000001 && abs($first[1] - $last[1]) < 0.000001) {
                array_pop($result);
            }
        }

        return $result;
    }

    /**
     * Group stop rows into geographic clusters to avoid one polygon bridging separate cities.
     *
     * @param  Collection<int, object>  $rows
     * @return array<int, Collection<int, object>>
     */
    private function clusterStopsByDistance($rows, float $radiusKm): array
    {
        $clusters = [];
        $visited = [];

        for ($i = 0; $i < $rows->count(); $i++) {
            if (isset($visited[$i])) {
                continue;
            }

            $queue = [$i];
            $visited[$i] = true;
            $clusterIndexes = [];

            while (! empty($queue)) {
                $current = array_pop($queue);
                $clusterIndexes[] = $current;

                $currentRow = $rows[$current];
                $currentLat = (float) $currentRow->stop_lat;
                $currentLon = (float) $currentRow->stop_lon;

                for ($j = 0; $j < $rows->count(); $j++) {
                    if (isset($visited[$j])) {
                        continue;
                    }

                    $candidate = $rows[$j];
                    $candidateLat = (float) $candidate->stop_lat;
                    $candidateLon = (float) $candidate->stop_lon;

                    if ($this->haversineKm($currentLat, $currentLon, $candidateLat, $candidateLon) <= $radiusKm) {
                        $visited[$j] = true;
                        $queue[] = $j;
                    }
                }
            }

            $clusters[] = collect($clusterIndexes)->map(fn($idx) => $rows[$idx]);
        }

        usort($clusters, fn($a, $b): int => $b->count() <=> $a->count());

        return $clusters;
    }

    private function haversineKm(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadiusKm = 6371.0;

        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadiusKm * $c;
    }

    /**
     * Lightweight ring simplification for map-friendly, less jittery outlines.
     *
     * @param  array<int, array{0: float, 1: float}>  $ring
     * @return array<int, array{0: float, 1: float}>
     */
    private function simplifyRing(array $ring, float $epsilon): array
    {
        if (count($ring) < 4) {
            return $ring;
        }

        $simplified = [$ring[0]];

        for ($i = 1; $i < count($ring) - 1; $i++) {
            $prev = $simplified[count($simplified) - 1];
            $curr = $ring[$i];
            $next = $ring[$i + 1];

            $distPrev = sqrt($this->distanceSquared($prev, $curr));
            if ($distPrev < $epsilon) {
                continue;
            }

            // Drop nearly-collinear micro-bends.
            $cross = abs($this->cross($prev, $curr, $next));
            if ($cross < ($epsilon * 0.2)) {
                continue;
            }

            $simplified[] = $curr;
        }

        $simplified[] = $ring[count($ring) - 1];

        return $this->deduplicateSequentialPoints($simplified);
    }

    /**
     * @param  array<int, array{0: float, 1: float}>  $points
     * @return array<int, array{0: float, 1: float}>
     */
    private function buildBoundingPolygon(array $points): array
    {
        if (empty($points)) {
            return [];
        }

        $lats = array_map(fn($point) => $point[0], $points);
        $lons = array_map(fn($point) => $point[1], $points);

        $minLat = min($lats);
        $maxLat = max($lats);
        $minLon = min($lons);
        $maxLon = max($lons);

        $latPad = max(0.003, ($maxLat - $minLat) * 0.2);
        $lonPad = max(0.003, ($maxLon - $minLon) * 0.2);

        $minLat -= $latPad;
        $maxLat += $latPad;
        $minLon -= $lonPad;
        $maxLon += $lonPad;

        return [
            [$minLat, $minLon],
            [$minLat, $maxLon],
            [$maxLat, $maxLon],
            [$maxLat, $minLon],
            [$minLat, $minLon],
        ];
    }

    /**
     * @param  array<int, array{0: float, 1: float}>  $polygon
     * @return array{min_lat: float, max_lat: float, min_lon: float, max_lon: float}
     */
    private function calculateBbox(array $polygon): array
    {
        if (empty($polygon)) {
            return [
                'min_lat' => 0.0,
                'max_lat' => 0.0,
                'min_lon' => 0.0,
                'max_lon' => 0.0,
            ];
        }

        $lats = array_map(fn($point) => $point[0], $polygon);
        $lons = array_map(fn($point) => $point[1], $polygon);

        return [
            'min_lat' => min($lats),
            'max_lat' => max($lats),
            'min_lon' => min($lons),
            'max_lon' => max($lons),
        ];
    }

    /**
     * Find all distinct tenant IDs that have stops geographically near either the
     * origin or destination coordinates. Uses MySQL ST_Distance_Sphere for accuracy.
     *
     * @return array<int>
     */
    private function findTenantsNearCoordinates(
        float $originLat,
        float $originLon,
        float $destLat,
        float $destLon,
        int $radiusMeters = 30000
    ): array {
        return DB::table('stops')
            ->whereNotNull('tenant_id')
            ->where(function ($q) use ($originLat, $originLon, $destLat, $destLon, $radiusMeters) {
                $q->whereRaw(
                    'ST_Distance_Sphere(point(stop_lon, stop_lat), point(?, ?)) < ?',
                    [$originLon, $originLat, $radiusMeters]
                )->orWhereRaw(
                    'ST_Distance_Sphere(point(stop_lon, stop_lat), point(?, ?)) < ?',
                    [$destLon, $destLat, $radiusMeters]
                );
            })
            ->distinct()
            ->pluck('tenant_id')
            ->toArray();
    }
}
