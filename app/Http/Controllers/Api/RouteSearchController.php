<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Stop;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RouteSearchController extends Controller
{
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

        // Use the user's selected time for filtering
        $minimumDepartureTime = $time;

        // Get day of week (0=Sunday, 1=Monday, etc.)
        $dayOfWeek = date('w', strtotime($date));
        $dayNames = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];

        // Get database IDs for origin stops using LIKE for fuzzy matching
        // stop_times.stop_id references stops.id, not stops.stop_id
        $originStopIds = Stop::withoutGlobalScopes()
            ->where('stop_name', 'LIKE', '%' . $originName . '%')
            ->pluck('id')
            ->toArray();

        // Get database IDs for destination stops
        $destStopIds = Stop::withoutGlobalScopes()
            ->where('stop_name', 'LIKE', '%' . $destinationName . '%')
            ->pluck('id')
            ->toArray();

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
            $date
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
     * Find routes with transfers and walking connections
     */
    private function findTransferRoutes($originStopIds, $destStopIds, $originName, $destinationName, $time, $activeServiceIds, $minimumDepartureTime = null, $date = null)
    {
        $maxWalkingDistance = 1.5; // 1.5 km - reasonable walking distance
        $walkingSpeedKmh = 6.5; // 6.5 km/h - accounts for straight-line vs actual walking distance
        $minTransferTime = 5; // 5 minutes minimum for transfers
        $maxTransferWaitTime = 15; // Maximum 15 minutes wait at transfer point

        $originStops = DB::table('stops')
            ->whereIn('id', $originStopIds)
            ->select('id', 'stop_name', 'stop_lat', 'stop_lon')
            ->get();

        $destStops = DB::table('stops')
            ->whereIn('id', $destStopIds)
            ->select('id', 'stop_name', 'stop_lat', 'stop_lon')
            ->get();

        if ($originStops->isEmpty() || $destStops->isEmpty()) {
            return [];
        }

        $journeys = [];

        // Use the user's selected time for SQL queries - we'll filter out past routes later
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
        $routeOptions = []; // Group routes by route name to find best option

        // Group direct routes by route name
        foreach ($directRoutes as $route) {
            $routeKey = $route->route_short_name . '_' . $route->operator_name;

            $originWalkDist = $this->getWalkingDistanceById($originStops[0]->id, $route->origin_stop_id);
            $destWalkDist = $this->getWalkingDistanceById($route->dest_stop_id, $destStops[0]->id);
            $totalWalkDist = $originWalkDist + $destWalkDist;

            // Store this route option with its walking distance
            if (! isset($routeOptions[$routeKey]) || $totalWalkDist < $routeOptions[$routeKey]['walk_dist']) {
                $routeOptions[$routeKey] = [
                    'route' => $route,
                    'walk_dist' => $totalWalkDist,
                    'origin_walk' => $originWalkDist,
                    'dest_walk' => $destWalkDist,
                ];
            }
        }

        // Now build journeys from the best option per route
        foreach ($routeOptions as $option) {
            $route = $option['route'];
            $originWalkDist = $option['origin_walk'];
            $destWalkDist = $option['dest_walk'];
            $totalWalkDist = $option['walk_dist'];

            $legs = [];

            // Only add origin walk if there's actual distance
            // Transit leg
            $busDepartureTime = substr($route->departure_time, 0, 5);
            $busArrivalTime = substr($route->arrival_time, 0, 5);

            if ($originWalkDist > 0.05) {
                $walkDuration = $this->calculateWalkingTimeById($originStops[0]->id, $route->origin_stop_id, $walkingSpeedKmh);

                // Add 2-minute buffer for boarding - walk must end at least 2 min before bus departs
                $walkEndTime = date('H:i', strtotime($busDepartureTime) - (2 * 60));
                $walkStartTime = date('H:i', strtotime($walkEndTime) - ($walkDuration * 60));

                // Skip if walk end time is after bus departure (impossible)
                if (strtotime($walkEndTime) >= strtotime($busDepartureTime)) {
                    continue;
                }

                $originCoords = ['lat' => $originStops[0]->stop_lat, 'lon' => $originStops[0]->stop_lon];
                $destCoords = $this->getStopCoordsById($route->origin_stop_id);

                $legs[] = [
                    'type' => 'walk',
                    'from' => $originName,
                    'to' => $this->getStopNameById($route->origin_stop_id),
                    'from_lat' => $originCoords['lat'],
                    'from_lon' => $originCoords['lon'],
                    'to_lat' => $destCoords['lat'],
                    'to_lon' => $destCoords['lon'],
                    'distance' => $originWalkDist,
                    'duration' => $walkDuration,
                    'start_time' => $walkStartTime,
                    'end_time' => $walkEndTime,
                ];
            }

            // Fetch stops for this transit leg
            $transitStops = [];
            try {
                $transitStops = DB::table('stop_times')
                    ->join('stops', 'stop_times.stop_id', '=', 'stops.id')
                    ->where('stop_times.trip_id', $route->trip_id)
                    ->where('stop_times.stop_sequence', '>=', $route->origin_sequence)
                    ->where('stop_times.stop_sequence', '<=', $route->dest_sequence)
                    ->orderBy('stop_times.stop_sequence')
                    ->select(
                        'stops.stop_name',
                        'stops.stop_lat',
                        'stops.stop_lon',
                        'stop_times.arrival_time',
                        'stop_times.departure_time'
                    )
                    ->get()
                    ->values()
                    ->map(function ($stop, $index) {
                        return [
                            'name' => $stop->stop_name,
                            'lat' => $stop->stop_lat,
                            'lon' => $stop->stop_lon,
                            'arrival' => substr($stop->arrival_time, 0, 5),
                            'departure' => substr($stop->departure_time, 0, 5),
                            'is_intermediate' => false,
                        ];
                    })
                    ->toArray();
                foreach ($transitStops as $i => &$s) {
                    $s['is_intermediate'] = ($i > 0 && $i < count($transitStops) - 1);
                }
                unset($s);
            } catch (\Exception $e) {
                $transitStops = [];
            }

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

            // Get coordinates for transit leg endpoints
            $fromCoords = $this->getStopCoordsById($route->origin_stop_id);
            $toCoords = $this->getStopCoordsById($route->dest_stop_id);

            $legs[] = [
                'type' => 'transit',
                'route_name' => $route->route_short_name,
                'route_long_name' => $route->route_long_name,
                'route_color' => $route->route_color,
                'route_text_color' => $route->route_text_color,
                'operator_name' => $route->operator_name,
                'trip_headsign' => $route->trip_headsign,
                'from' => $this->getStopNameById($route->origin_stop_id),
                'to' => $route->dest_stop_name,
                'from_lat' => $fromCoords['lat'],
                'from_lon' => $fromCoords['lon'],
                'to_lat' => $toCoords['lat'],
                'to_lon' => $toCoords['lon'],
                'departure_time' => $busDepartureTime,
                'arrival_time' => $busArrivalTime,
                'duration' => round((strtotime($busArrivalTime) - strtotime($busDepartureTime)) / 60),
                'stops' => $transitStops,
                'shape_pts' => $transitShapePoints,
            ];

            // Only add destination walk if there's actual distance
            if ($destWalkDist > 0.05) {
                $walkDuration = $this->calculateWalkingTimeById($route->dest_stop_id, $destStops[0]->id, $walkingSpeedKmh);
                $walkStartTime = $busArrivalTime;

                $fromCoords = $this->getStopCoordsById($route->dest_stop_id);
                $toCoords = ['lat' => $destStops[0]->stop_lat, 'lon' => $destStops[0]->stop_lon];

                $legs[] = [
                    'type' => 'walk',
                    'from' => $this->getStopNameById($route->dest_stop_id),
                    'to' => $destinationName,
                    'from_lat' => $fromCoords['lat'],
                    'from_lon' => $fromCoords['lon'],
                    'to_lat' => $toCoords['lat'],
                    'to_lon' => $toCoords['lon'],
                    'distance' => $destWalkDist,
                    'duration' => $walkDuration,
                    'start_time' => $walkStartTime,
                    'end_time' => date('H:i', strtotime($walkStartTime) + ($walkDuration * 60)),
                ];
            }

            $journey = $this->buildJourney($legs, $time);

            // Google-style scoring: Prioritize FASTEST total journey time
            // Calculate total journey time in minutes
            $totalTimeMinutes = (strtotime($journey['arrival_time']) - strtotime($journey['leave_at'])) / 60;

            // Base score: 10000 - (total time in minutes * 50) - higher score = faster journey
            // Heavy transfer penalty: each transfer = -200 points (equivalent to 4 minutes slower)
            // This ensures simple direct routes with short walks beat complex transfers
            $transferPenalty = $journey['transfers'] * 200;
            $walkingPenalty = $totalWalkDist * 10;
            $journey['score'] = 10000 - ($totalTimeMinutes * 50) - $transferPenalty - $walkingPenalty;

            // Filter out journeys where leave_at time has already passed
            if ($minimumDepartureTime) {
                // Compare times properly - add today's date for accurate comparison
                $leaveAtDateTime = \Carbon\Carbon::parse($date . ' ' . $journey['leave_at']);
                $minimumDateTime = \Carbon\Carbon::parse($date . ' ' . $minimumDepartureTime);

                if ($leaveAtDateTime->lt($minimumDateTime)) {
                    continue;
                }
            }

            $foundJourneys[] = $journey;
        }

        // STRATEGY 1.5: Direct routes with minimal walking
        // Continue collecting more route options across different times
        $nearbyOriginStops = $this->findNearbyStops($originStops, $maxWalkingDistance);
        $nearbyDestStops = $this->findNearbyStops($destStops, $maxWalkingDistance);

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

        // Process nearby routes same way - find best walking option per route
        $nearbyRouteOptions = [];
        foreach ($nearbyDirectRoutes as $route) {
            $routeKey = $route->route_short_name . '_' . $route->operator_name;

            $originWalkDist = $this->getWalkingDistanceById($originStops[0]->id, $route->origin_stop_id);
            $destWalkDist = $this->getWalkingDistanceById($route->dest_stop_id, $destStops[0]->id);
            $totalWalkDist = $originWalkDist + $destWalkDist;

            if (! isset($nearbyRouteOptions[$routeKey]) || $totalWalkDist < $nearbyRouteOptions[$routeKey]['walk_dist']) {
                $nearbyRouteOptions[$routeKey] = [
                    'route' => $route,
                    'walk_dist' => $totalWalkDist,
                    'origin_walk' => $originWalkDist,
                    'dest_walk' => $destWalkDist,
                ];
            }
        }

        // Add nearby route options
        foreach ($nearbyRouteOptions as $option) {
            $route = $option['route'];
            $originWalkDist = $option['origin_walk'];
            $destWalkDist = $option['dest_walk'];
            $totalWalkDist = $option['walk_dist'];

            $legs = [];

            $busDepartureTime = substr($route->departure_time, 0, 5);
            $busArrivalTime = substr($route->arrival_time, 0, 5);

            if ($originWalkDist > 0.05) {
                $walkDuration = $this->calculateWalkingTimeById($originStops[0]->id, $route->origin_stop_id, $walkingSpeedKmh);

                // Add 2-minute buffer for boarding - walk must end at least 2 min before bus departs
                $walkEndTime = date('H:i', strtotime($busDepartureTime) - (2 * 60));
                $walkStartTime = date('H:i', strtotime($walkEndTime) - ($walkDuration * 60));

                // Skip if walk end time is after bus departure (impossible)
                if (strtotime($walkEndTime) >= strtotime($busDepartureTime)) {
                    continue;
                }

                $originCoords = ['lat' => $originStops[0]->stop_lat, 'lon' => $originStops[0]->stop_lon];
                $destCoords = $this->getStopCoordsById($route->origin_stop_id);

                $legs[] = [
                    'type' => 'walk',
                    'from' => $originName,
                    'to' => $this->getStopNameById($route->origin_stop_id),
                    'from_lat' => $originCoords['lat'],
                    'from_lon' => $originCoords['lon'],
                    'to_lat' => $destCoords['lat'],
                    'to_lon' => $destCoords['lon'],
                    'distance' => $originWalkDist,
                    'duration' => $walkDuration,
                    'start_time' => $walkStartTime,
                    'end_time' => $walkEndTime,
                ];
            }

            // Fetch stops for this transit leg
            $transitStops = [];
            try {
                $transitStops = DB::table('stop_times')
                    ->join('stops', 'stop_times.stop_id', '=', 'stops.id')
                    ->where('stop_times.trip_id', $route->trip_id)
                    ->where('stop_times.stop_sequence', '>=', $route->origin_sequence)
                    ->where('stop_times.stop_sequence', '<=', $route->dest_sequence)
                    ->orderBy('stop_times.stop_sequence')
                    ->select(
                        'stops.stop_name',
                        'stops.stop_lat',
                        'stops.stop_lon',
                        'stop_times.arrival_time',
                        'stop_times.departure_time'
                    )
                    ->get()
                    ->values()
                    ->map(function ($stop, $index) {
                        return [
                            'name' => $stop->stop_name,
                            'lat' => $stop->stop_lat,
                            'lon' => $stop->stop_lon,
                            'arrival' => substr($stop->arrival_time, 0, 5),
                            'departure' => substr($stop->departure_time, 0, 5),
                            'is_intermediate' => false,
                        ];
                    })
                    ->toArray();
                foreach ($transitStops as $i => &$s) {
                    $s['is_intermediate'] = ($i > 0 && $i < count($transitStops) - 1);
                }
                unset($s);
            } catch (\Exception $e) {
                $transitStops = [];
            }

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

            // Get coordinates for transit leg endpoints
            $fromCoords = $this->getStopCoordsById($route->origin_stop_id);
            $toCoords = $this->getStopCoordsById($route->dest_stop_id);

            $legs[] = [
                'type' => 'transit',
                'route_name' => $route->route_short_name,
                'route_long_name' => $route->route_long_name,
                'route_color' => $route->route_color,
                'route_text_color' => $route->route_text_color,
                'operator_name' => $route->operator_name,
                'trip_headsign' => $route->trip_headsign,
                'from' => $this->getStopNameById($route->origin_stop_id),
                'to' => $route->dest_stop_name,
                'from_lat' => $fromCoords['lat'],
                'from_lon' => $fromCoords['lon'],
                'to_lat' => $toCoords['lat'],
                'to_lon' => $toCoords['lon'],
                'departure_time' => $busDepartureTime,
                'arrival_time' => $busArrivalTime,
                'duration' => round((strtotime($busArrivalTime) - strtotime($busDepartureTime)) / 60),
                'stops' => $transitStops,
                'shape_pts' => $transitShapePoints,
            ];

            if ($destWalkDist > 0.05) {
                $walkDuration = $this->calculateWalkingTimeById($route->dest_stop_id, $destStops[0]->id, $walkingSpeedKmh);
                $walkStartTime = $busArrivalTime;

                $fromCoords = $this->getStopCoordsById($route->dest_stop_id);
                $toCoords = ['lat' => $destStops[0]->stop_lat, 'lon' => $destStops[0]->stop_lon];

                $legs[] = [
                    'type' => 'walk',
                    'from' => $route->dest_stop_name,
                    'to' => $destinationName,
                    'from_lat' => $fromCoords['lat'],
                    'from_lon' => $fromCoords['lon'],
                    'to_lat' => $toCoords['lat'],
                    'to_lon' => $toCoords['lon'],
                    'distance' => $destWalkDist,
                    'duration' => $walkDuration,
                    'start_time' => $walkStartTime,
                    'end_time' => date('H:i', strtotime($walkStartTime) + ($walkDuration * 60)),
                ];
            }

            $journey = $this->buildJourney($legs, $time);

            // Google-style scoring: Prioritize FASTEST total journey time
            $totalTimeMinutes = (strtotime($journey['arrival_time']) - strtotime($journey['leave_at'])) / 60;
            $transferPenalty = $journey['transfers'] * 200;
            $walkingPenalty = $totalWalkDist * 10;
            $journey['score'] = 10000 - ($totalTimeMinutes * 50) - $transferPenalty - $walkingPenalty;

            // Filter out journeys where leave_at time has already passed
            if ($minimumDepartureTime) {
                // Compare times properly - add today's date for accurate comparison
                $leaveAtDateTime = \Carbon\Carbon::parse($date . ' ' . $journey['leave_at']);
                $minimumDateTime = \Carbon\Carbon::parse($date . ' ' . $minimumDepartureTime);
                if ($leaveAtDateTime->lt($minimumDateTime)) {
                    continue;
                }
            }

            $foundJourneys[] = $journey;
        }

        // STRATEGY 2: One transfer routes - find bus-to-bus transfers
        // Include nearby destination stops from the start so second leg can alight near (not just at) destination
        $expandedDestStopIds = array_unique(array_merge($destStopIds, array_column($nearbyDestStops, 'id')));

        // Use the actual user time - no offset needed. Strategy 1/1.5 handles direct routes;
        // Strategy 2 must search the same time window to find valid transfer connections.
        $sqlMinTimeWithBuffer = $sqlMinTime;
        $seenRoutes = [];
        $transferRoutes = DB::table('stop_times as st_origin')
            ->join('trips as t1', 'st_origin.trip_id', '=', 't1.id')
            ->join('routes as r1', 't1.route_id', '=', 'r1.id')
            ->join('users as u1', 'r1.tenant_id', '=', 'u1.id')
            ->join('stop_times as st_transfer', function ($join) {
                $join->on('st_origin.trip_id', '=', 'st_transfer.trip_id')
                    ->whereRaw('st_transfer.stop_sequence > st_origin.stop_sequence');
            })
            ->whereIn('st_origin.stop_id', $originStopIds) // Prefer exact origin stops
            ->whereIn('t1.service_id', $activeServiceIds)
            ->where('st_origin.departure_time', '>=', $sqlMinTimeWithBuffer)
            ->where('st_origin.departure_time', '<=', date('H:i:s', strtotime($sqlMinTimeWithBuffer) + 10800))
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
            ->orderBy('st_origin.departure_time')
            ->limit(100)
            ->get();

        foreach ($transferRoutes as $firstLeg) {
            // Find nearby transfer points
            $nearbyTransfer = $this->findNearbyStopsFromCoords(
                DB::table('stops')->where('id', $firstLeg->transfer_stop_id)->value('stop_lat'),
                DB::table('stops')->where('id', $firstLeg->transfer_stop_id)->value('stop_lon'),
                $maxWalkingDistance
            );

            $transferStopIds = array_unique(array_merge(
                [$firstLeg->transfer_stop_id],
                array_column($nearbyTransfer, 'id')
            ));

            $minDepartureTime = date('H:i:s', strtotime($firstLeg->transfer_arrival) + ($minTransferTime * 60));

            // Calculate time window for connecting routes
            $minDepartureTime = date('H:i:s', strtotime($firstLeg->transfer_arrival) + ($minTransferTime * 60));
            $maxDepartureTime = date('H:i:s', strtotime($firstLeg->transfer_arrival) + ($maxTransferWaitTime * 60));

            $secondLegOptions = DB::table('stop_times as st2_start')
                ->join('trips as t2', 'st2_start.trip_id', '=', 't2.id')
                ->join('routes as r2', 't2.route_id', '=', 'r2.id')
                ->join('users as u2', 'r2.tenant_id', '=', 'u2.id')
                ->join('stop_times as st2_end', function ($join) use ($expandedDestStopIds) {
                    $join->on('st2_start.trip_id', '=', 'st2_end.trip_id')
                        ->whereIn('st2_end.stop_id', $expandedDestStopIds)
                        ->whereRaw('st2_end.stop_sequence > st2_start.stop_sequence');
                })
                ->whereIn('t2.service_id', $activeServiceIds)
                ->whereIn('st2_start.stop_id', $transferStopIds)
                ->where('st2_start.departure_time', '>=', $minDepartureTime)
                ->where('st2_start.departure_time', '<=', $maxDepartureTime) // Max 15 min wait
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
                ->get();

            // From all matching end stops, pick the one that minimises total arrival time at destination
            // (bus arrival + walk to true destination). This ensures we pick ACROPOLE over LUDWIGSBURG
            // even if LUDWIGSBURG appears earlier in the trip sequence.
            // Ties in arrival time are broken by minimum transfer walk distance — so boarding the
            // connecting bus at the stop closest to where the first leg alighted (e.g. CARRONS→CARRONS
            // rather than CARRONS→RUE DE BELFORT) is always preferred.
            $secondLeg = null;
            $bestArrivalAtDest = PHP_INT_MAX;
            $bestTransferWalk = PHP_INT_MAX;
            foreach ($secondLegOptions as $option) {
                $optWalkDist = $this->getWalkingDistanceById($option->final_stop_id, $destStops[0]->id);
                $optWalkMin = (int) round($optWalkDist / $walkingSpeedKmh * 60);
                $optArrival = strtotime($option->final_arrival) + ($optWalkMin * 60);
                $optTransferWalk = $this->getWalkingDistanceById($firstLeg->transfer_stop_id, $option->second_origin_stop_id);
                if (
                    $optArrival < $bestArrivalAtDest ||
                    ($optArrival === $bestArrivalAtDest && $optTransferWalk < $bestTransferWalk)
                ) {
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

                $transferWalkDist = $this->getWalkingDistanceById($firstLeg->transfer_stop_id, $secondLeg->second_origin_stop_id);

                // Fetch stops for first transit leg
                $firstLegStops = [];
                try {
                    $firstLegStops = DB::table('stop_times')
                        ->join('stops', 'stop_times.stop_id', '=', 'stops.id')
                        ->where('stop_times.trip_id', $firstLeg->trip_id)
                        ->where('stop_times.stop_sequence', '>=', $firstLeg->origin_sequence)
                        ->where('stop_times.stop_sequence', '<=', $firstLeg->transfer_sequence)
                        ->orderBy('stop_times.stop_sequence')
                        ->select(
                            'stops.stop_name',
                            'stops.stop_lat',
                            'stops.stop_lon',
                            'stop_times.arrival_time',
                            'stop_times.departure_time'
                        )
                        ->get()
                        ->values()
                        ->map(function ($stop, $index) {
                            return [
                                'name' => $stop->stop_name,
                                'lat' => $stop->stop_lat,
                                'lon' => $stop->stop_lon,
                                'arrival' => substr($stop->arrival_time, 0, 5),
                                'departure' => substr($stop->departure_time, 0, 5),
                                'is_intermediate' => false,
                            ];
                        })
                        ->toArray();
                    foreach ($firstLegStops as $i => &$s) {
                        $s['is_intermediate'] = ($i > 0 && $i < count($firstLegStops) - 1);
                    }
                    unset($s);
                } catch (\Exception $e) {
                    $firstLegStops = [];
                }

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

                // Get coordinates for first transit leg endpoints
                $firstFromCoords = $this->getStopCoordsById($firstLeg->origin_stop_id);
                $firstToCoords = $this->getStopCoordsById($firstLeg->transfer_stop_id);

                $legs = [
                    [
                        'type' => 'transit',
                        'route_name' => $firstLeg->first_route,
                        'route_long_name' => $firstLeg->first_route_long,
                        'route_color' => $firstLeg->first_color,
                        'route_text_color' => $firstLeg->first_text_color,
                        'operator_name' => $firstLeg->first_operator,
                        'trip_headsign' => $firstLeg->first_headsign,
                        'from' => $this->getStopNameById($firstLeg->origin_stop_id),
                        'to' => $this->getStopNameById($firstLeg->transfer_stop_id),
                        'from_lat' => $firstFromCoords['lat'],
                        'from_lon' => $firstFromCoords['lon'],
                        'to_lat' => $firstToCoords['lat'],
                        'to_lon' => $firstToCoords['lon'],
                        'departure_time' => substr($firstLeg->departure_time, 0, 5),
                        'arrival_time' => substr($firstLeg->transfer_arrival, 0, 5),
                        'duration' => round((strtotime($firstLeg->transfer_arrival) - strtotime($firstLeg->departure_time)) / 60),
                        'stops' => $firstLegStops,
                        'shape_pts' => $firstShapePoints,
                    ],
                ];

                // Only add walking between stops if there's real distance
                if ($transferWalkDist > 0.05) {
                    $walkDuration = $this->calculateWalkingTimeById($firstLeg->transfer_stop_id, $secondLeg->second_origin_stop_id, $walkingSpeedKmh);
                    $walkStartTime = substr($firstLeg->transfer_arrival, 0, 5);
                    $walkEndTime = date('H:i', strtotime($walkStartTime) + ($walkDuration * 60));

                    // CRITICAL: Validate that second bus departs AFTER walk ends
                    $secondBusDeparture = substr($secondLeg->second_departure, 0, 5);
                    if (strtotime($secondBusDeparture) < strtotime($walkEndTime)) {
                        // Impossible! Bus leaves before walk ends - skip this route
                        continue;
                    }

                    $fromCoords = $this->getStopCoordsById($firstLeg->transfer_stop_id);
                    $toCoords = $this->getStopCoordsById($secondLeg->second_origin_stop_id);

                    $legs[] = [
                        'type' => 'walk',
                        'from' => $this->getStopNameById($firstLeg->transfer_stop_id),
                        'to' => $this->getStopNameById($secondLeg->second_origin_stop_id),
                        'from_lat' => $fromCoords['lat'],
                        'from_lon' => $fromCoords['lon'],
                        'to_lat' => $toCoords['lat'],
                        'to_lon' => $toCoords['lon'],
                        'distance' => $transferWalkDist,
                        'duration' => $walkDuration,
                        'start_time' => $walkStartTime,
                        'end_time' => $walkEndTime,
                    ];
                }

                // Fetch stops for second transit leg
                $secondLegStops = [];
                try {
                    $secondLegStops = DB::table('stop_times')
                        ->join('stops', 'stop_times.stop_id', '=', 'stops.id')
                        ->where('stop_times.trip_id', $secondLeg->second_trip_id)
                        ->where('stop_times.stop_sequence', '>=', $secondLeg->second_origin_sequence)
                        ->where('stop_times.stop_sequence', '<=', $secondLeg->final_stop_sequence)
                        ->orderBy('stop_times.stop_sequence')
                        ->select(
                            'stops.stop_name',
                            'stops.stop_lat',
                            'stops.stop_lon',
                            'stop_times.arrival_time',
                            'stop_times.departure_time'
                        )
                        ->get()
                        ->values()
                        ->map(function ($stop, $index) {
                            return [
                                'name' => $stop->stop_name,
                                'lat' => $stop->stop_lat,
                                'lon' => $stop->stop_lon,
                                'arrival' => substr($stop->arrival_time, 0, 5),
                                'departure' => substr($stop->departure_time, 0, 5),
                                'is_intermediate' => false,
                            ];
                        })
                        ->toArray();
                    foreach ($secondLegStops as $i => &$s) {
                        $s['is_intermediate'] = ($i > 0 && $i < count($secondLegStops) - 1);
                    }
                    unset($s);
                } catch (\Exception $e) {
                    $secondLegStops = [];
                }

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

                // Get coordinates for second transit leg endpoints
                $secondFromCoords = $this->getStopCoordsById($secondLeg->second_origin_stop_id);
                $secondToCoords = $this->getStopCoordsById($secondLeg->final_stop_id);

                $legs[] = [
                    'type' => 'transit',
                    'route_name' => $secondLeg->second_route_name,
                    'route_long_name' => $secondLeg->second_route_long_name,
                    'route_color' => $secondLeg->second_route_color,
                    'route_text_color' => $secondLeg->second_text_color,
                    'operator_name' => $secondLeg->second_operator_name,
                    'trip_headsign' => $secondLeg->second_trip_headsign,
                    'from' => $this->getStopNameById($secondLeg->second_origin_stop_id),
                    'to' => $this->getStopNameById($secondLeg->final_stop_id),
                    'from_lat' => $secondFromCoords['lat'],
                    'from_lon' => $secondFromCoords['lon'],
                    'to_lat' => $secondToCoords['lat'],
                    'to_lon' => $secondToCoords['lon'],
                    'departure_time' => substr($secondLeg->second_departure, 0, 5),
                    'arrival_time' => substr($secondLeg->final_arrival, 0, 5),
                    'duration' => round((strtotime($secondLeg->final_arrival) - strtotime($secondLeg->second_departure)) / 60),
                    'stops' => $secondLegStops,
                    'shape_pts' => $secondShapePoints,
                ];

                // Add final walk leg if the second leg alights at a nearby (non-exact) destination stop
                $finalWalkDist = 0.0;
                if (! in_array($secondLeg->final_stop_id, $destStopIds)) {
                    $finalWalkDist = $this->getWalkingDistanceById($secondLeg->final_stop_id, $destStops[0]->id);
                    if ($finalWalkDist > 0.05) {
                        $finalWalkDuration = $this->calculateWalkingTimeById($secondLeg->final_stop_id, $destStops[0]->id, $walkingSpeedKmh);
                        $finalWalkStart = substr($secondLeg->final_arrival, 0, 5);
                        $finalWalkEnd = date('H:i', strtotime($finalWalkStart) + ($finalWalkDuration * 60));
                        $finalFromCoords = $this->getStopCoordsById($secondLeg->final_stop_id);
                        $legs[] = [
                            'type' => 'walk',
                            'from' => $this->getStopNameById($secondLeg->final_stop_id),
                            'to' => $destinationName,
                            'from_lat' => $finalFromCoords['lat'],
                            'from_lon' => $finalFromCoords['lon'],
                            'to_lat' => $destStops[0]->stop_lat,
                            'to_lon' => $destStops[0]->stop_lon,
                            'distance' => $finalWalkDist,
                            'duration' => $finalWalkDuration,
                            'start_time' => $finalWalkStart,
                            'end_time' => $finalWalkEnd,
                        ];
                    }
                }

                $journey = $this->buildJourney($legs, $time);

                // Google-style scoring: Prioritize FASTEST total journey time
                $totalTimeMinutes = (strtotime($journey['arrival_time']) - strtotime($journey['leave_at'])) / 60;
                $transferPenalty = $journey['transfers'] * 200;
                $walkingPenalty = ($transferWalkDist + $finalWalkDist) * 10;
                $journey['score'] = 10000 - ($totalTimeMinutes * 50) - $transferPenalty - $walkingPenalty;

                // Filter out journeys where leave_at time has already passed
                if ($minimumDepartureTime && strtotime($journey['leave_at']) < strtotime($minimumDepartureTime)) {
                    continue;
                }

                $foundJourneys[] = $journey;

                if (count($foundJourneys) >= 20) {
                    break;
                }
            }
        }

        // If still no transfer routes found to exact destination, try nearby destinations (within 2km)
        if (empty($foundJourneys) && $expandedDestStopIds === $destStopIds) {
            foreach ($destStopIds as $destId) {
                $destStop = DB::table('stops')->where('id', $destId)->first();
                if ($destStop) {
                    $nearbyDests = $this->findNearbyStopsFromCoords(
                        $destStop->stop_lat,
                        $destStop->stop_lon,
                        2.0 // 2km for destinations
                    );
                    foreach ($nearbyDests as $nearby) {
                        if (! in_array($nearby->id, $expandedDestStopIds)) {
                            $expandedDestStopIds[] = $nearby->id;
                        }
                    }
                }
            }

            // Retry transfer search with expanded destinations
            if ($expandedDestStopIds !== $destStopIds) {
                $walkingSpeedKmh = 6.5;
                $trueDestStop = DB::table('stops')->whereIn('id', $destStopIds)->first();

                $expandedJourneys = $this->findTransferRoutes(
                    $originStopIds,
                    $expandedDestStopIds,
                    $originName,
                    $destinationName,
                    $time,
                    $activeServiceIds,
                    $minimumDepartureTime,
                    $date
                );

                // Post-process: if the last leg ends at an expanded (non-exact) dest stop,
                // add a final walk leg from that stop to the actual destination.
                if ($trueDestStop) {
                    foreach ($expandedJourneys as &$journey) {
                        $lastLeg = end($journey['legs']);
                        if (! $lastLeg) {
                            continue;
                        }

                        $latDiff = abs($lastLeg['to_lat'] - $trueDestStop->stop_lat);
                        $lonDiff = abs($lastLeg['to_lon'] - $trueDestStop->stop_lon) * cos(deg2rad((float) $trueDestStop->stop_lat));
                        $distKm = sqrt($latDiff * $latDiff + $lonDiff * $lonDiff) * 111.0;

                        if ($distKm > 0.05) {
                            $walkDuration = (int) round($distKm / $walkingSpeedKmh * 60);
                            $walkStart = $lastLeg['arrival_time'] ?? $lastLeg['end_time'] ?? '00:00';
                            $walkEnd = date('H:i', strtotime($walkStart) + ($walkDuration * 60));

                            $journey['legs'][] = [
                                'type' => 'walk',
                                'from' => $lastLeg['to'],
                                'to' => $destinationName,
                                'from_lat' => $lastLeg['to_lat'],
                                'from_lon' => $lastLeg['to_lon'],
                                'to_lat' => (float) $trueDestStop->stop_lat,
                                'to_lon' => (float) $trueDestStop->stop_lon,
                                'distance' => round($distKm, 3),
                                'duration' => $walkDuration,
                                'start_time' => $walkStart,
                                'end_time' => $walkEnd,
                            ];

                            $journey['arrival_time'] = $walkEnd;
                        }
                    }
                    unset($journey);
                }

                return $expandedJourneys;
            }
        }

        return $this->sortAndLimitJourneys($foundJourneys, 5);
    }

    private function sortAndLimitJourneys($journeys, $limit)
    {
        // Google-style grouping: Group routes by departure hour and select best route per hour
        // This gives users options across different time slots

        $routesByHour = [];

        foreach ($journeys as $journey) {
            // Extract leave_at hour (when user needs to leave) for grouping
            // This ensures we show routes across different departure times, not different bus times
            $leaveAtHour = substr($journey['leave_at'], 0, 2);

            // Store the best route for this hour (highest score = best)
            if (! isset($routesByHour[$leaveAtHour])) {
                $routesByHour[$leaveAtHour] = $journey;
            } else {
                // Compare scores - keep the better one
                $existingScore = $routesByHour[$leaveAtHour]['score'] ?? 0;
                $newScore = $journey['score'] ?? 0;

                if ($newScore > $existingScore) {
                    $routesByHour[$leaveAtHour] = $journey;
                } elseif ($newScore === $existingScore) {
                    // If same score, prefer fewer transfers
                    if ($journey['transfers'] < $routesByHour[$leaveAtHour]['transfers']) {
                        $routesByHour[$leaveAtHour] = $journey;
                    }
                }
            }
        }

        // Sort hours chronologically and return best route per hour
        ksort($routesByHour);
        $result = array_values($routesByHour);

        return array_slice($result, 0, $limit);
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

    private function getStopNameById($stopId)
    {
        $stop = DB::table('stops')->where('id', $stopId)->first();

        return $stop ? $stop->stop_name : 'Unknown';
    }

    private function getStopCoordsById($stopId)
    {
        $stop = DB::table('stops')->where('id', $stopId)->select('stop_lat', 'stop_lon')->first();

        return $stop ? ['lat' => $stop->stop_lat, 'lon' => $stop->stop_lon] : null;
    }

    private function getWalkingDistanceById($fromStopId, $toStopId)
    {
        if ($fromStopId == $toStopId) {
            return 0;
        }

        $stops = DB::table('stops')->whereIn('id', [$fromStopId, $toStopId])->get();
        if ($stops->count() < 2) {
            return 0;
        }

        $from = $stops->where('id', $fromStopId)->first();
        $to = $stops->where('id', $toStopId)->first();

        $earthRadius = 6371;
        $lat1 = deg2rad($from->stop_lat);
        $lon1 = deg2rad($from->stop_lon);
        $lat2 = deg2rad($to->stop_lat);
        $lon2 = deg2rad($to->stop_lon);

        $distance = $earthRadius * acos(
            cos($lat1) * cos($lat2) * cos($lon2 - $lon1) + sin($lat1) * sin($lat2)
        );

        return round($distance, 2);
    }

    private function calculateWalkingTimeById($fromStopId, $toStopId, $speedKmh)
    {
        $distance = $this->getWalkingDistanceById($fromStopId, $toStopId);
        if ($distance == 0) {
            return 0;
        }

        return max(1, round(($distance / $speedKmh) * 60));
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
    private function getClippedShapePoints(string $shapeId, float $originLat, float $originLon, float $destLat, float $destLon): array
    {
        $rawPoints = DB::table('shapes')
            ->where('shape_id', $shapeId)
            ->orderBy('shape_pt_sequence')
            ->get(['shape_pt_lat', 'shape_pt_lon'])
            ->map(fn($pt) => [(float) $pt->shape_pt_lat, (float) $pt->shape_pt_lon])
            ->toArray();

        return $this->clipShapeToStops($rawPoints, $originLat, $originLon, $destLat, $destLon);
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
        $routeShapes = \Illuminate\Support\Facades\DB::select('
            SELECT r.route_short_name, MAX(r.route_long_name) as route_long_name, MAX(r.route_color) as route_color, t.shape_id 
            FROM routes r 
            JOIN trips t ON r.id = t.route_id 
            WHERE t.shape_id IS NOT NULL 
            GROUP BY r.route_short_name, t.shape_id
        ');

        $points = \Illuminate\Support\Facades\DB::table('shapes')
            ->orderBy('shape_id')
            ->orderBy('shape_pt_sequence')
            ->get(['shape_id', 'shape_pt_lat', 'shape_pt_lon']);

        $shapePoints = [];
        foreach ($points as $pt) {
            $shapePoints[$pt->shape_id][] = [$pt->shape_pt_lat, $pt->shape_pt_lon];
        }

        $result = [];
        foreach ($routeShapes as $rs) {
            if (isset($shapePoints[$rs->shape_id])) {
                $result[] = [
                    'route_short_name' => $rs->route_short_name,
                    'route_long_name' => $rs->route_long_name,
                    'route_color' => $rs->route_color,
                    'shape_pts' => $shapePoints[$rs->shape_id],
                ];
            }
        }

        return response()->json(['routes' => $result]);
    }
}
