<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Transit Finder</title>
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700" rel="stylesheet" />

    <!-- Leaflet CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
        integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="" />

    <!-- Leaflet JS -->
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
        integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        .autocomplete-dropdown {
            position: absolute;
            z-index: 1000;
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 0.5rem;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
            max-height: 300px;
            overflow-y: auto;
            width: 100%;
            margin-top: 0.25rem;
        }

        .autocomplete-item {
            padding: 0.75rem 1rem;
            cursor: pointer;
        }

        .autocomplete-item:hover {
            background-color: #fee2e2;
        }

        .route-card {
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .route-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
        }

        .exact-match-badge {
            animation: pulse 2s infinite;
        }

        @keyframes pulse {

            0%,
            100% {
                opacity: 1;
            }

            50% {
                opacity: 0.7;
            }
        }

        #map {
            height: 500px;
            width: 100%;
            border-radius: 0.75rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }

        .leaflet-popup-content-wrapper {
            border-radius: 0.5rem;
        }

        .leaflet-popup-content {
            margin: 0.75rem;
            font-family: 'Inter', sans-serif;
        }
    </style>
</head>

<body class="bg-gray-50">
    <header class="fixed top-0 left-0 right-0 z-50 bg-white border-b shadow-sm">
        <div class="max-w-7xl mx-auto px-6 h-16 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-red-600 rounded-lg flex items-center justify-center">
                    <svg class="h-6 w-6 text-white" fill="currentColor" viewBox="0 0 24 24">
                        <path
                            d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z" />
                    </svg>
                </div>
                <span class="text-xl font-bold">Transit Finder</span>
            </div>
            <nav class="flex gap-4">
                @auth
                    <a href="/dashboard" class="px-6 py-2.5 bg-red-600 text-white rounded-lg">Dashboard</a>
                @else
                    <a href="/login" class="px-6 py-2.5 text-gray-700">Log in</a>
                    <a href="/register" class="px-6 py-2.5 bg-red-600 text-white rounded-lg">Sign Up</a>
                @endauth
            </nav>
        </div>
    </header>

    <section class="pt-32 pb-12 px-6 bg-gradient-to-br from-red-50 via-white to-gray-50">
        <div class="max-w-7xl mx-auto">
            <div class="text-center mb-8">
                <h1 class="text-5xl font-bold mb-4">Find Your <span class="text-red-600">Perfect Route</span></h1>
                <p class="text-xl text-gray-600">Plan your journey with real-time transit information</p>
            </div>
            <div class="max-w-4xl mx-auto bg-white rounded-2xl shadow-2xl p-8">
                <div class="mb-4 flex justify-end">
                    <button type="button" id="toggle-map-selector"
                        class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-semibold flex items-center gap-2">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7" />
                        </svg>
                        Select on Map
                    </button>
                </div>

                <div id="map-selector-container" class="hidden mb-6">
                    <div class="bg-blue-50 border-2 border-blue-200 rounded-lg p-4 mb-4">
                        <p class="text-sm font-semibold text-blue-900 mb-2" id="map-instruction">
                            Click on the map to select your starting point
                        </p>
                        <div class="flex gap-2">
                            <span class="px-3 py-1 bg-green-100 text-green-800 text-sm font-semibold rounded-full"
                                id="origin-status">Origin: Not selected</span>
                            <span class="px-3 py-1 bg-orange-100 text-orange-800 text-sm font-semibold rounded-full"
                                id="dest-status">Destination: Not selected</span>
                        </div>
                    </div>
                    <div id="selection-map" style="height: 400px; border-radius: 0.5rem;"></div>
                </div>

                <form id="route-search-form" class="space-y-6">
                    <div class="grid md:grid-cols-[1fr_auto_1fr] gap-4 items-end">
                        <div class="relative">
                            <label class="block text-sm font-semibold mb-2">Starting Point</label>
                            <input type="text" id="origin" required
                                class="w-full px-4 py-3 border-2 rounded-lg focus:border-red-500 focus:ring-2 focus:ring-red-200 outline-none"
                                placeholder="Enter starting location" autocomplete="off">
                            <div id="origin-autocomplete" class="autocomplete-dropdown hidden"></div>
                        </div>
                        <button type="button" id="swap-locations"
                            class="flex-shrink-0 w-12 h-12 bg-gray-100 hover:bg-red-100 rounded-lg flex items-center justify-center transition-all hover:scale-110 active:scale-95 group"
                            title="Swap locations">
                            <svg class="w-6 h-6 text-gray-600 group-hover:text-red-600 transition-colors rotate-90"
                                fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4" />
                            </svg>
                        </button>
                        <div class="relative">
                            <label class="block text-sm font-semibold mb-2">Destination</label>
                            <input type="text" id="destination" required
                                class="w-full px-4 py-3 border-2 rounded-lg focus:border-red-500 focus:ring-2 focus:ring-red-200 outline-none"
                                placeholder="Where are you going?" autocomplete="off">
                            <div id="destination-autocomplete" class="autocomplete-dropdown hidden"></div>
                        </div>
                    </div>
                    <div class="grid md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-semibold mb-2">Date</label>
                            <input type="date" id="date" required
                                class="w-full px-4 py-3 border-2 rounded-lg focus:border-red-500 focus:ring-2 focus:ring-red-200 outline-none cursor-pointer"
                                value="{{ date('Y-m-d') }}" onclick="this.showPicker()">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold mb-2">Time</label>
                            <input type="time" id="time" required
                                class="w-full px-4 py-3 border-2 rounded-lg focus:border-red-500 focus:ring-2 focus:ring-red-200 outline-none cursor-pointer"
                                value="{{ date('H:i') }}" onclick="this.showPicker()">
                        </div>
                    </div>
                    <button type="submit"
                        class="w-full px-8 py-4 bg-red-600 hover:bg-red-700 text-white rounded-lg text-lg font-semibold">Search
                        Routes</button>
                </form>
            </div>
        </div>
    </section>

    <section id="results-section" class="py-12 px-6 hidden">
        <div class="max-w-5xl mx-auto">
            <div class="flex items-center justify-between mb-8">
                <h2 class="text-3xl font-bold">Available Routes</h2>
                <div class="flex gap-2">
                    <button id="view-list-btn" class="px-4 py-2 bg-red-600 text-white rounded-lg font-semibold">
                        <svg class="w-5 h-5 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M4 6h16M4 10h16M4 14h16M4 18h16" />
                        </svg>
                        List
                    </button>
                    <button id="view-map-btn"
                        class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg font-semibold hover:bg-gray-300">
                        <svg class="w-5 h-5 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7" />
                        </svg>
                        Map
                    </button>
                </div>
            </div>
            <div id="map-container" class="mb-8 hidden">
                <div class="bg-white rounded-lg p-4 mb-4 shadow-md flex items-center gap-6 text-sm">
                    <div class="font-semibold text-gray-700">Map Legend:</div>
                    <div class="flex items-center gap-2">
                        <div style="width: 40px; height: 3px; background: #DC2626;"></div>
                        <span class="text-gray-600">🚌 Transit (Bus/Train)</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <div style="width: 40px; height: 3px; background: #3B82F6; border-top: 3px dashed #3B82F6;">
                        </div>
                        <span class="text-gray-600">🚶 Walking</span>
                    </div>
                </div>
                <div id="map"></div>
            </div>
            <div id="loading-state" class="hidden text-center py-12">
                <div class="animate-spin rounded-full h-16 w-16 border-b-2 border-red-600 mx-auto"></div>
                <p class="mt-4 text-gray-600">Searching for routes...</p>
            </div>
            <div id="no-results" class="hidden text-center py-12 bg-white rounded-2xl shadow-xl p-12">
                <svg class="h-16 w-16 text-gray-400 mx-auto mb-4" fill="none" stroke="currentColor"
                    viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                <h3 class="text-xl font-bold mb-2">No Routes Found</h3>
                <p class="text-gray-600">Try adjusting your search criteria or selecting different times.</p>
            </div>
            <div id="routes-list" class="space-y-4"></div>
        </div>
    </section>

    <script>
        async function setupAutocomplete(inputId, dropdownId) {
            const input = document.getElementById(inputId),
                dropdown = document.getElementById(dropdownId);
            input.addEventListener('input', async function() {
                const query = this.value.trim();
                if (query.length < 2) {
                    dropdown.classList.add('hidden');
                    return;
                }
                const response = await fetch('/api/stops/search?q=' + encodeURIComponent(query));
                const stops = await response.json();
                if (stops.length === 0) {
                    dropdown.classList.add('hidden');
                    return;
                }
                dropdown.innerHTML = stops.map(s => {
                    const cityTag = s.city ? '<div class="text-xs text-gray-500 mt-0.5">' + s.city +
                        '</div>' : '';
                    return '<div class="autocomplete-item" data-name="' + s.stop_name +
                        '"><div class="font-semibold">' + s.stop_name + '</div>' + cityTag +
                        '</div>';
                }).join('');
                dropdown.classList.remove('hidden');
                dropdown.querySelectorAll('.autocomplete-item').forEach(item => {
                    item.addEventListener('click', function() {
                        input.value = this.dataset.name;
                        dropdown.classList.add('hidden');
                    });
                });
            });
            document.addEventListener('click', function(e) {
                if (!input.contains(e.target) && !dropdown.contains(e.target)) {
                    dropdown.classList.add('hidden');
                }
            });
        }

        setupAutocomplete('origin', 'origin-autocomplete');
        setupAutocomplete('destination', 'destination-autocomplete');

        // Swap origin and destination
        document.getElementById('swap-locations').addEventListener('click', function() {
            const origin = document.getElementById('origin');
            const destination = document.getElementById('destination');
            const temp = origin.value;
            origin.value = destination.value;
            destination.value = temp;
        });

        function renderTransferRoute(route, routeIndex) {
            const legsHtml = route.legs.map((leg, idx) => {
                if (leg.type === 'walk') {
                    return `
                        <div class="flex items-center gap-4 py-3">
                            <div class="flex-shrink-0 w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center">
                                <svg class="h-7 w-7 text-blue-600" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M13.5 5.5c1.1 0 2-.9 2-2s-.9-2-2-2-2 .9-2 2 .9 2 2 2zM9.8 8.9L7 23h2.1l1.8-8 2.1 2v6h2v-7.5l-2.1-2 .6-3C14.8 12 16.8 13 19 13v-2c-1.9 0-3.5-1-4.3-2.4l-1-1.6c-.4-.6-1-1-1.7-1-.3 0-.5.1-.8.1L6 8.3V13h2V9.6l1.8-.7"/>
                                </svg>
                            </div>
                            <div class="flex-1">
                                <div class="font-semibold text-blue-600">Walk ${leg.duration} min</div>
                                <div class="text-sm text-gray-600">${leg.from} → ${leg.to}</div>
                                ${leg.distance > 0 ? `<div class="text-xs text-gray-500">${leg.distance} km</div>` : ''}
                            </div>
                            <div class="flex flex-col items-end text-sm text-gray-600 gap-1">
                                <div class="font-semibold text-blue-600">${leg.start_time}</div>
                                <div class="text-xs text-gray-400">↓</div>
                                <div class="font-semibold text-gray-700">${leg.end_time}</div>
                            </div>
                        </div>
                    `;
                } else if (leg.type === 'transit') {
                    const routeBadgeStyle = leg.route_color ?
                        `background-color: #${leg.route_color}; color: ${leg.route_text_color ? '#' + leg.route_text_color : '#FFFFFF'};` :
                        'background-color: #6B7280; color: #FFFFFF;';

                    // For now default to bus icon, can be extended with leg.vehicle_type for trains/cars
                    const vehicleIcon = `
                        <svg class="h-6 w-6 text-red-600" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M12 2c-4 0-8 .5-8 4v10c0 1.5 1.5 2 2 2v1c0 .5.5 1 1 1h1c.5 0 1-.5 1-1v-1h6v1c0 .5.5 1 1 1h1c.5 0 1-.5 1-1v-1c.5 0 2-.5 2-2V6c0-3.5-4-4-8-4zm0 2c3.5 0 5.5.5 6 2H6c.5-1.5 2.5-2 6-2zM7.5 9c.8 0 1.5.7 1.5 1.5S8.3 12 7.5 12 6 11.3 6 10.5 6.7 9 7.5 9zm9 0c.8 0 1.5.7 1.5 1.5s-.7 1.5-1.5 1.5-1.5-.7-1.5-1.5.7-1.5 1.5-1.5zM6 13.5h12V16H6v-2.5z"/>
                        </svg>
                    `;

                    return `
                        <div class="flex items-start gap-4 py-3">
                            <div class="flex-shrink-0 w-12 h-12 bg-red-100 rounded-full flex items-center justify-center">
                                ${vehicleIcon}
                            </div>
                            <div class="flex-1">
                                <div class="flex items-center gap-2 mb-1">
                                    <span class="inline-block px-3 py-1 text-sm font-bold rounded" style="${routeBadgeStyle}">
                                        ${leg.route_name}
                                    </span>
                                    <span class="text-xs text-gray-500">${leg.operator_name}</span>
                                </div>
                                ${leg.trip_headsign ? `<div class="text-sm text-gray-600 mb-1">${leg.trip_headsign}</div>` : ''}
                                <div class="text-sm font-medium text-gray-900">${leg.from} → ${leg.to}</div>
                            </div>
                            <div class="flex flex-col items-end text-sm text-gray-600 gap-1">
                                <div class="font-semibold text-red-600">${leg.departure_time}</div>
                                <div class="text-xs text-gray-400">↓</div>
                                <div class="font-semibold text-gray-700">${leg.arrival_time}</div>
                            </div>
                        </div>
                    `;
                }
                return '';
            }).join('<div class="flex justify-center"><div class="w-px h-4 bg-gray-300"></div></div>');

            return `
                <div class="route-card bg-white rounded-xl shadow-lg p-6 border-2 border-orange-300">
                    <div class="flex items-start justify-between mb-4">
                        <div class="flex-1">
                            <h3 class="text-xl font-bold text-gray-900 mb-2">
                                Journey
                            </h3>
                        </div>
                    </div>
                    
                    <div class="flex items-center gap-6 bg-gray-50 rounded-lg p-4 mb-4">
                        <div class="flex-1">
                            <div class="text-sm text-gray-600 mb-1">Leave at</div>
                            <div class="text-2xl font-bold text-red-600">${route.leave_at || route.departure_time}</div>
                            ${route.leave_at && route.leave_at !== route.departure_time ? `<div class="text-xs text-gray-500 mt-1">Bus departs: ${route.departure_time}</div>` : ''}
                        </div>
                        
                        <div class="flex items-center gap-2 text-gray-400">
                            <div class="h-px w-8 bg-gray-300"></div>
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path>
                            </svg>
                            <div class="h-px w-8 bg-gray-300"></div>
                        </div>
                        
                        <div class="flex-1 text-center">
                            <div class="text-sm text-gray-600 mb-1">Duration</div>
                            <div class="text-lg font-semibold text-gray-900">${route.duration}</div>
                        </div>
                        
                        <div class="flex items-center gap-2 text-gray-400">
                            <div class="h-px w-8 bg-gray-300"></div>
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path>
                            </svg>
                            <div class="h-px w-8 bg-gray-300"></div>
                        </div>
                        
                        <div class="flex-1 text-right">
                            <div class="text-sm text-gray-600 mb-1">Arrival</div>
                            <div class="text-2xl font-bold text-green-600">${route.arrival_time}</div>
                        </div>
                    </div>
                    
                    <div class="border-t pt-4">
                        <h4 class="font-semibold text-gray-900 mb-3 flex items-center gap-2">
                            <svg class="h-5 w-5 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"></path>
                            </svg>
                            Journey Details
                        </h4>
                        <div class="space-y-1">
                            ${legsHtml}
                        </div>
                    </div>
                    
                    <button onclick="showRouteOnMap(${routeIndex})" class="mt-4 w-full px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-semibold flex items-center justify-center gap-2">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/>
                        </svg>
                        View on Map
                    </button>
                </div>
            `;
        }

        document.getElementById('route-search-form').addEventListener('submit', async function(e) {
            e.preventDefault();
            const origin = document.getElementById('origin');
            const destination = document.getElementById('destination');
            const routesList = document.getElementById('routes-list');
            const loadingState = document.getElementById('loading-state');
            const noResults = document.getElementById('no-results');

            // Show results section and loading state
            document.getElementById('results-section').classList.remove('hidden');
            loadingState.classList.remove('hidden');
            noResults.classList.add('hidden');
            routesList.innerHTML = '';

            try {
                const response = await fetch('/api/routes/search', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify({
                        origin_name: origin.value,
                        destination_name: destination.value,
                        date: document.getElementById('date').value,
                        time: document.getElementById('time').value
                    })
                });

                const data = await response.json();
                loadingState.classList.add('hidden');

                if (!data.routes || data.routes.length === 0) {
                    noResults.classList.remove('hidden');
                    return;
                }

                // Store route data for map display
                currentRouteData = data.routes;

                // Show message if no direct routes
                // Message removed - no need for transfer warning

                // Display routes in linear format
                routesList.innerHTML += data.routes.map((route, index) => {
                    // Handle transfer routes with multiple legs
                    if (route.connection_type === 'transfer' && route.legs) {
                        return renderTransferRoute(route, index);
                    }

                    // Handle direct routes
                    const exactMatchBadge = route.is_exact_match ?
                        '<span class="exact-match-badge inline-block px-3 py-1 bg-green-100 text-green-800 text-xs font-semibold rounded-full ml-2">Exact Match</span>' :
                        '';

                    const connectionBadge = route.connection_type === 'from_origin' ?
                        '<span class="inline-block px-3 py-1 bg-blue-100 text-blue-800 text-xs font-semibold rounded-full ml-2">From Origin</span>' :
                        route.connection_type === 'to_destination' ?
                        '<span class="inline-block px-3 py-1 bg-purple-100 text-purple-800 text-xs font-semibold rounded-full ml-2">To Destination</span>' :
                        '';

                    const note = route.note ?
                        `<p class="text-sm text-gray-600 mt-2">${route.note}</p>` : '';

                    // Create route badge with color if available
                    const routeBadgeStyle = route.route_color ?
                        `background-color: #${route.route_color}; color: ${route.route_text_color ? '#' + route.route_text_color : '#FFFFFF'};` :
                        'background-color: #6B7280; color: #FFFFFF;';

                    return `
                        <div class="route-card bg-white rounded-xl shadow-lg p-6 border-2 ${route.is_exact_match ? 'border-green-500' : route.connection_type !== 'direct' ? 'border-gray-300' : 'border-transparent'}">
                            <div class="flex items-start justify-between mb-4">
                                <div class="flex-1">
                                    <p class="text-sm font-semibold text-gray-700 mb-2">${route.agency_name}</p>
                                    <div class="flex items-center gap-3 mb-2">
                                        <span class="inline-block px-4 py-2 text-lg font-bold rounded-lg" style="${routeBadgeStyle}">
                                            ${route.route_name}
                                        </span>
                                        ${exactMatchBadge}
                                        ${connectionBadge}
                                    </div>
                                    ${route.trip_headsign ? `<p class="text-gray-600 mt-2">${route.trip_headsign}</p>` : ''}
                                    ${note}
                                </div>
                                ${route.stops_count !== '?' ? `<div class="flex items-center gap-2 text-sm text-gray-600">
                                                                                                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                                                                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                                                                                            </svg>
                                                                                                            <span>${route.stops_count} stops</span>
                                                                                                        </div>` : ''}
                            </div>
                            
                            <div class="flex items-center gap-6 bg-gray-50 rounded-lg p-4">
                                ${route.departure_time !== '?' ? `<div class="flex-1">
                                                                                                            <div class="text-sm text-gray-600 mb-1">Leave at</div>
                                                                                                            <div class="text-2xl font-bold text-red-600">${route.leave_at || route.departure_time}</div>
                                                                                                            ${route.leave_at && route.leave_at !== route.departure_time ? `<div class="text-xs text-gray-500 mt-1">Bus departs: ${route.departure_time}</div>` : ''}
                                                                                                        </div>` : ''}
                                
                                ${route.duration !== 'N/A' ? `<div class="flex items-center gap-2 text-gray-400">
                                                                                                            <div class="h-px w-8 bg-gray-300"></div>
                                                                                                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path>
                                                                                                            </svg>
                                                                                                            <div class="h-px w-8 bg-gray-300"></div>
                                                                                                        </div>
                                                                                                        
                                                                                                        <div class="flex-1 text-center">
                                                                                                            <div class="text-sm text-gray-600 mb-1">Duration</div>
                                                                                                            <div class="text-lg font-semibold text-gray-900">${route.duration}</div>
                                                                                                        </div>
                                                                                                        
                                                                                                        <div class="flex items-center gap-2 text-gray-400">
                                                                                                            <div class="h-px w-8 bg-gray-300"></div>
                                                                                                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path>
                                                                                                            </svg>
                                                                                                            <div class="h-px w-8 bg-gray-300"></div>
                                                                                                        </div>` : ''}
                                
                                ${route.arrival_time !== '?' ? `<div class="flex-1 text-right">
                                                                                                            <div class="text-sm text-gray-600 mb-1">Arrival</div>
                                                                                                            <div class="text-2xl font-bold text-green-600">${route.arrival_time}</div>
                                                                                                        </div>` : ''}
                            </div>
                            
                            ${route.stops && route.stops.length > 0 ? `
                                                                                                    <div class="mt-6 border-t pt-4">
                                                                                                        <h4 class="font-semibold text-gray-900 mb-3 flex items-center gap-2">
                                                                                                            <svg class="h-5 w-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"></path>
                                                                                                            </svg>
                                                                                                            All Stops on Route
                                                                                                        </h4>
                                                                                                        <div class="space-y-2 pl-7">
                                                                                                            ${route.stops.map((stop, idx) => `
                                            <div class="flex items-center gap-3 text-sm">
                                                <div class="flex-shrink-0 w-6 h-6 rounded-full bg-blue-100 text-blue-700 flex items-center justify-center font-semibold text-xs">
                                                    ${idx + 1}
                                                </div>
                                                <div class="flex-1 font-medium text-gray-900">${stop.name}</div>
                                                <div class="text-gray-600">${stop.arrival}</div>
                                            </div>
                                        `).join('')}
                                                                                                        </div>
                                                                                                    </div>
                                                                                                ` : ''}
                            
                            ${route.description ? `<p class="mt-4 text-sm text-gray-600">${route.description}</p>` : ''}
                            
                            <button onclick="showRouteOnMap(${index})" class="mt-4 w-full px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-semibold flex items-center justify-center gap-2">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/>
                                </svg>
                                View on Map
                            </button>
                        </div>
                    `;
                }).join('');
            } catch (error) {
                loadingState.classList.add('hidden');
                noResults.classList.remove('hidden');
                console.error('Error searching routes:', error);
            }
        });

        // Map functionality
        let map = null;
        let selectionMap = null;
        let currentRouteData = null;
        let geojsonData = null;
        let selectedOrigin = null;
        let selectedDestination = null;
        let originMarker = null;
        let destMarker = null;

        function initMap() {
            if (!map) {
                // Initialize map centered on France
                map = L.map('map').setView([46.603354, 1.888334], 6);

                // Add OpenStreetMap tiles
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    attribution: '© OpenStreetMap contributors',
                    maxZoom: 19
                }).addTo(map);
            }
        }

        // Initialize selection map
        document.getElementById('toggle-map-selector').addEventListener('click', function() {
            const container = document.getElementById('map-selector-container');
            const isHidden = container.classList.contains('hidden');

            if (isHidden) {
                container.classList.remove('hidden');
                this.textContent = 'Hide Map';

                setTimeout(() => {
                    if (!selectionMap) {
                        selectionMap = L.map('selection-map').setView([47.5, 6.8], 10);

                        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                            attribution: '© OpenStreetMap contributors',
                            maxZoom: 19
                        }).addTo(selectionMap);

                        // Click handler to select stops
                        selectionMap.on('click', async function(e) {
                            const lat = e.latlng.lat;
                            const lon = e.latlng.lng;

                            // Find nearest stop
                            try {
                                const response = await fetch(
                                    `/api/stops/nearest?lat=${lat}&lon=${lon}`);
                                const stop = await response.json();

                                if (!selectedOrigin) {
                                    selectedOrigin = stop;
                                    document.getElementById('origin').value = stop.stop_name;
                                    document.getElementById('origin-status').textContent =
                                        `Origin: ${stop.stop_name}`;
                                    document.getElementById('origin-status').classList.remove(
                                        'bg-green-100', 'text-green-800');
                                    document.getElementById('origin-status').classList.add(
                                        'bg-green-500', 'text-white');
                                    document.getElementById('map-instruction').textContent =
                                        'Now click to select your destination';

                                    if (originMarker) selectionMap.removeLayer(originMarker);
                                    originMarker = L.marker([stop.stop_lat, stop.stop_lon], {
                                        icon: L.divIcon({
                                            className: 'custom-div-icon',
                                            html: '<div style="background-color: #22c55e; width: 24px; height: 24px; border-radius: 50%; border: 3px solid white; box-shadow: 0 2px 4px rgba(0,0,0,0.3);"></div>',
                                            iconSize: [24, 24],
                                            iconAnchor: [12, 12]
                                        })
                                    }).addTo(selectionMap).bindPopup(
                                        `<b>Origin:</b> ${stop.stop_name}`).openPopup();
                                } else if (!selectedDestination) {
                                    selectedDestination = stop;
                                    document.getElementById('destination').value = stop
                                        .stop_name;
                                    document.getElementById('dest-status').textContent =
                                        `Destination: ${stop.stop_name}`;
                                    document.getElementById('dest-status').classList.remove(
                                        'bg-orange-100', 'text-orange-800');
                                    document.getElementById('dest-status').classList.add(
                                        'bg-orange-500', 'text-white');
                                    document.getElementById('map-instruction').textContent =
                                        'Both points selected! You can now search for routes.';

                                    if (destMarker) selectionMap.removeLayer(destMarker);
                                    destMarker = L.marker([stop.stop_lat, stop.stop_lon], {
                                        icon: L.divIcon({
                                            className: 'custom-div-icon',
                                            html: '<div style="background-color: #f97316; width: 24px; height: 24px; border-radius: 50%; border: 3px solid white; box-shadow: 0 2px 4px rgba(0,0,0,0.3);"></div>',
                                            iconSize: [24, 24],
                                            iconAnchor: [12, 12]
                                        })
                                    }).addTo(selectionMap).bindPopup(
                                        `<b>Destination:</b> ${stop.stop_name}`).openPopup();
                                } else {
                                    // Reset and start over
                                    selectedOrigin = stop;
                                    selectedDestination = null;
                                    document.getElementById('origin').value = stop.stop_name;
                                    document.getElementById('destination').value = '';
                                    document.getElementById('origin-status').textContent =
                                        `Origin: ${stop.stop_name}`;
                                    document.getElementById('dest-status').textContent =
                                        'Destination: Not selected';
                                    document.getElementById('dest-status').classList.remove(
                                        'bg-orange-500', 'text-white');
                                    document.getElementById('dest-status').classList.add(
                                        'bg-orange-100', 'text-orange-800');
                                    document.getElementById('map-instruction').textContent =
                                        'Now click to select your destination';

                                    if (originMarker) selectionMap.removeLayer(originMarker);
                                    if (destMarker) selectionMap.removeLayer(destMarker);

                                    originMarker = L.marker([stop.stop_lat, stop.stop_lon], {
                                        icon: L.divIcon({
                                            className: 'custom-div-icon',
                                            html: '<div style="background-color: #22c55e; width: 24px; height: 24px; border-radius: 50%; border: 3px solid white; box-shadow: 0 2px 4px rgba(0,0,0,0.3);"></div>',
                                            iconSize: [24, 24],
                                            iconAnchor: [12, 12]
                                        })
                                    }).addTo(selectionMap).bindPopup(
                                        `<b>Origin:</b> ${stop.stop_name}`).openPopup();
                                }
                            } catch (error) {
                                console.error('Error finding nearest stop:', error);
                                alert(
                                    'Could not find a nearby stop. Please try clicking closer to a transit stop.'
                                );
                            }
                        });
                    } else {
                        selectionMap.invalidateSize();
                    }
                }, 100);
            } else {
                container.classList.add('hidden');
                this.innerHTML =
                    '<svg class="w-5 h-5 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7" /></svg>Select on Map';
            }
        });

        async function fetchGeoJsonData() {
            if (!geojsonData) {
                try {
                    const response = await fetch('/evolity_routes.geojson');
                    geojsonData = await response.json();
                } catch (e) {
                    console.error('Failed to load GeoJSON map data', e);
                    geojsonData = null;
                }
            }
            return geojsonData;
        }

        async function displayRouteOnMap(route) {
            if (!map) initMap();

            await fetchGeoJsonData();

            // Clear existing layers
            map.eachLayer(layer => {
                if (layer instanceof L.Marker || layer instanceof L.Polyline || layer instanceof L
                    .CircleMarker) {
                    map.removeLayer(layer);
                }
            });

            const bounds = [];
            const colors = {
                walk: '#3B82F6', // blue
                transit: '#DC2626' // red
            };

            // Handle transfer routes with legs
            if (route.legs && route.legs.length > 0) {
                for (const leg of route.legs) {
                    if (leg.type === 'walk') {
                        // For walking, we need coordinates - they should be in the leg data
                        // If we have stop names, we can try to fetch coordinates
                        if (leg.from_lat && leg.from_lon && leg.to_lat && leg.to_lon) {
                            const fromCoords = [leg.from_lat, leg.from_lon];
                            const toCoords = [leg.to_lat, leg.to_lon];

                            const walkLine = L.polyline([fromCoords, toCoords], {
                                color: colors.walk,
                                weight: 4,
                                opacity: 0.7,
                                dashArray: '10, 10'
                            }).addTo(map);

                            walkLine.bindPopup(`
                                <strong>🚶 Walk ${leg.duration} min</strong><br>
                                ${leg.from} → ${leg.to}<br>
                                ${leg.distance ? leg.distance + ' km' : ''}
                            `);

                            bounds.push(fromCoords, toCoords);
                        }
                    } else if (leg.type === 'transit') {
                        // Transit segment - solid line
                        console.log('Transit leg:', leg.route_name, 'stops:', leg.stops ? leg.stops.length : 0);

                        // Primary source: Exact shape coordinates from GTFS shapes table if available
                        if (leg.shape_pts && leg.shape_pts.length > 1) {
                            console.log('Using shape coordinates for leg', leg.route_name);
                            const coords = leg.shape_pts;
                            const transitLine = L.polyline(coords, {
                                color: leg.route_color ? '#' + leg.route_color : colors.transit,
                                weight: 5,
                                opacity: 0.8
                            }).addTo(map);

                            transitLine.bindPopup(`
                                <strong>🚌 ${leg.route_name}</strong><br>
                                ${leg.operator_name || ''}<br>
                                ${leg.from} → ${leg.to}<br>
                                Duration: ${leg.duration} min
                            `);

                            bounds.push(...coords);

                            // Only add markers for first and last stop to keep map clean when using shapes
                            if (leg.stops && leg.stops.length > 1) {
                                [0, leg.stops.length - 1].forEach(stopIdx => {
                                    const stop = leg.stops[stopIdx];
                                    if (stop.lat && stop.lon) {
                                        const marker = L.circleMarker([stop.lat, stop.lon], {
                                            radius: 8,
                                            fillColor: '#DC2626',
                                            color: '#DC2626',
                                            weight: 2,
                                            opacity: 1,
                                            fillOpacity: 0.8
                                        }).addTo(map);

                                        marker.bindPopup(`
                                            <strong>${stop.name}</strong><br>
                                            ${stop.arrival ? 'Arrival: ' + stop.arrival : ''}
                                        `);
                                    }
                                });
                            }
                        } else if (leg.stops && leg.stops.length > 1) {
                            const coords = leg.stops
                                .map(stop => stop.lat && stop.lon ? [stop.lat, stop.lon] : null)
                                .filter(c => c !== null);

                            if (coords.length > 1) {
                                const transitLine = L.polyline(coords, {
                                    color: leg.route_color ? '#' + leg.route_color : colors.transit,
                                    weight: 5,
                                    opacity: 0.8
                                }).addTo(map);

                                transitLine.bindPopup(`
                                    <strong>🚌 ${leg.route_name}</strong><br>
                                    ${leg.operator_name || ''}<br>
                                    ${leg.from} → ${leg.to}<br>
                                    Duration: ${leg.duration} min
                                `);

                                bounds.push(...coords);

                                // Add markers for key stops
                                leg.stops.forEach((stop, stopIdx) => {
                                    if (stop.lat && stop.lon) {
                                        const coord = [stop.lat, stop.lon];
                                        const isFirst = stopIdx === 0;
                                        const isLast = stopIdx === leg.stops.length - 1;

                                        const marker = L.circleMarker(coord, {
                                            radius: isFirst || isLast ? 8 : 5,
                                            fillColor: isFirst || isLast ? '#DC2626' : '#FFFFFF',
                                            color: '#DC2626',
                                            weight: 2,
                                            opacity: 1,
                                            fillOpacity: 0.8
                                        }).addTo(map);

                                        marker.bindPopup(`
                                            <strong>${stop.name}</strong><br>
                                            ${stop.arrival ? 'Arrival: ' + stop.arrival : ''}
                                        `);
                                    }
                                });
                            }
                        } else if (leg.from_lat && leg.from_lon && leg.to_lat && leg.to_lon) {
                            // Fallback: Use from/to coordinates if stops not available
                            console.log('Transit leg using fallback coordinates:', leg.route_name);
                            const transitLine = L.polyline(
                                [
                                    [leg.from_lat, leg.from_lon],
                                    [leg.to_lat, leg.to_lon]
                                ], {
                                    color: leg.route_color ? '#' + leg.route_color : colors.transit,
                                    weight: 5,
                                    opacity: 0.8
                                }
                            ).addTo(map);

                            transitLine.bindPopup(`
                                <strong>🚌 ${leg.route_name}</strong><br>
                                ${leg.operator_name || ''}<br>
                                ${leg.from} → ${leg.to}<br>
                                Duration: ${leg.duration} min
                            `);

                            bounds.push([leg.from_lat, leg.from_lon], [leg.to_lat, leg.to_lon]);

                            // Add markers for start/end
                            L.circleMarker([leg.from_lat, leg.from_lon], {
                                radius: 8,
                                fillColor: '#DC2626',
                                color: '#DC2626',
                                weight: 2,
                                opacity: 1,
                                fillOpacity: 0.8
                            }).addTo(map).bindPopup(`<strong>${leg.from}</strong><br>${leg.departure_time}`);

                            L.circleMarker([leg.to_lat, leg.to_lon], {
                                radius: 8,
                                fillColor: '#DC2626',
                                color: '#DC2626',
                                weight: 2,
                                opacity: 1,
                                fillOpacity: 0.8
                            }).addTo(map).bindPopup(`<strong>${leg.to}</strong><br>${leg.arrival_time}`);
                        } else {
                            console.warn('Transit leg has no stops or coordinates:', leg.route_name);
                        }
                    }
                }
            }
            // Handle direct routes (no legs)
            else if (route.stops && route.stops.length > 0) {
                if (route.shape_pts && route.shape_pts.length > 1) {
                    const coords = route.shape_pts;
                    const routeLine = L.polyline(coords, {
                        color: route.route_color ? '#' + route.route_color : colors.transit,
                        weight: 5,
                        opacity: 0.8
                    }).addTo(map);

                    routeLine.bindPopup(`
                        <strong>🚌 ${route.route_name}</strong><br>
                        ${route.agency_name || ''}<br>
                        Duration: ${route.duration || 'N/A'}
                    `);

                    bounds.push(...coords);

                    // Add markers only for first and last
                    [0, route.stops.length - 1].forEach(stopIdx => {
                        const stop = route.stops[stopIdx];
                        if (stop && stop.lat && stop.lon) {
                            const marker = L.circleMarker([stop.lat, stop.lon], {
                                radius: 8,
                                fillColor: '#DC2626',
                                color: '#DC2626',
                                weight: 2,
                                opacity: 1,
                                fillOpacity: 0.8
                            }).addTo(map);

                            marker.bindPopup(`
                                <strong>${stop.name}</strong><br>
                                ${stop.arrival ? 'Arrival: ' + stop.arrival : ''}
                            `);
                        }
                    });
                } else {
                    const coords = route.stops
                        .map(stop => stop.lat && stop.lon ? [stop.lat, stop.lon] : null)
                        .filter(c => c !== null);

                    if (coords.length > 1) {
                        const routeLine = L.polyline(coords, {
                            color: route.route_color ? '#' + route.route_color : colors.transit,
                            weight: 5,
                            opacity: 0.8
                        }).addTo(map);

                        routeLine.bindPopup(`
                            <strong>🚌 ${route.route_name}</strong><br>
                            ${route.agency_name || ''}<br>
                            Duration: ${route.duration || 'N/A'}
                        `);

                        bounds.push(...coords);

                        // Add markers for stops
                        route.stops.forEach((stop, idx) => {
                            if (stop.lat && stop.lon) {
                                const coord = [stop.lat, stop.lon];
                                const isFirst = idx === 0;
                                const isLast = idx === route.stops.length - 1;

                                const marker = L.circleMarker(coord, {
                                    radius: isFirst || isLast ? 8 : 5,
                                    fillColor: isFirst || isLast ? '#DC2626' : '#FFFFFF',
                                    color: '#DC2626',
                                    weight: 2,
                                    opacity: 1,
                                    fillOpacity: 0.8
                                }).addTo(map);

                                marker.bindPopup(`
                                    <strong>${stop.name}</strong><br>
                                    ${stop.arrival ? 'Arrival: ' + stop.arrival : ''}
                                `);
                            }
                        });
                    }
                }
            }

            // Fit map to show all markers and routes
            if (bounds.length > 0) {
                map.fitBounds(bounds, {
                    padding: [50, 50]
                });
            } else {
                // Default to France if no coordinates
                map.setView([46.603354, 1.888334], 6);
            }
        }

        function extractCoords(stopName) {
            // Helper to extract coordinates - you'll need to enhance this
            // based on your route data structure
            return null;
        }

        // Show specific route on map
        window.showRouteOnMap = function(routeIndex) {
            if (!currentRouteData || !currentRouteData[routeIndex]) {
                return;
            }

            const route = currentRouteData[routeIndex];

            // Switch to map view
            document.getElementById('routes-list').classList.add('hidden');
            document.getElementById('map-container').classList.remove('hidden');
            document.getElementById('view-list-btn').classList.remove('bg-red-600', 'text-white');
            document.getElementById('view-list-btn').classList.add('bg-gray-200', 'text-gray-700');
            document.getElementById('view-map-btn').classList.remove('bg-gray-200', 'text-gray-700');
            document.getElementById('view-map-btn').classList.add('bg-red-600', 'text-white');

            // Initialize and display route
            initMap();
            setTimeout(() => {
                map.invalidateSize();
                displayRouteOnMap(route);
            }, 100);
        };

        // View toggle buttons
        document.getElementById('view-list-btn').addEventListener('click', function() {
            document.getElementById('routes-list').classList.remove('hidden');
            document.getElementById('map-container').classList.add('hidden');
            this.classList.remove('bg-gray-200', 'text-gray-700');
            this.classList.add('bg-red-600', 'text-white');
            document.getElementById('view-map-btn').classList.remove('bg-red-600', 'text-white');
            document.getElementById('view-map-btn').classList.add('bg-gray-200', 'text-gray-700');
        });

        document.getElementById('view-map-btn').addEventListener('click', function() {
            document.getElementById('routes-list').classList.add('hidden');
            document.getElementById('map-container').classList.remove('hidden');
            this.classList.remove('bg-gray-200', 'text-gray-700');
            this.classList.add('bg-red-600', 'text-white');
            document.getElementById('view-list-btn').classList.remove('bg-red-600', 'text-white');
            document.getElementById('view-list-btn').classList.add('bg-gray-200', 'text-gray-700');

            // Initialize map and display first route
            if (currentRouteData && currentRouteData.length > 0) {
                initMap();
                setTimeout(() => {
                    map.invalidateSize();
                    displayRouteOnMap(currentRouteData[0]);
                }, 100);
            }
        });
    </script>
</body>

</html>
