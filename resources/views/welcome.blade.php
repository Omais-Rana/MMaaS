<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Transit Finder</title>
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700" rel="stylesheet" />

    <!-- Leaflet CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin="" />

    <!-- Leaflet JS -->
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin=""></script>

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
            cursor: pointer;
        }

        .route-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
        }

        .route-card.is-selected {
            outline: 2px solid #ef4444;
            outline-offset: -2px;
            border-color: transparent;
        }

        .leaflet-popup-content-wrapper {
            border-radius: 0.5rem;
        }

        .leaflet-popup-content {
            margin: 0.75rem;
            font-family: 'Inter', sans-serif;
        }

        /* Loading overlay for the whole app */
        #loading-overlay {
            display: none;
            position: absolute;
            inset: 0;
            background: rgba(255, 255, 255, 0.7);
            z-index: 50;
            align-items: center;
            justify-content: center;
        }

        #loading-overlay.active {
            display: flex;
        }

        /* Bus info cards */
        .bus-card {
            transition: background-color 0.15s, border-color 0.15s;
        }

        .bus-card:hover {
            background-color: #f9fafb;
        }

        .bus-card.bus-card-selected {
            border-color: #ef4444 !important;
            background-color: #fff7f7;
        }

        .bus-card-chevron {
            transition: transform 0.2s;
        }

        .bus-card.bus-card-selected .bus-card-chevron {
            transform: rotate(180deg);
        }

        .line-clamp-2 {
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
    </style>
</head>

<body class="bg-gray-50 h-screen overflow-hidden flex flex-col md:flex-row">

    <!-- Left Pane: Search & Results -->
    <div
        class="w-full md:w-[450px] lg:w-[500px] flex-shrink-0 h-full overflow-y-auto bg-white flex flex-col z-20 shadow-2xl relative">
        <div id="loading-overlay">
            <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-red-600"></div>
        </div>

        <div class="p-6 bg-gradient-to-br from-red-50 to-white border-b">
            <div class="flex items-center gap-3 mb-6">
                <div
                    class="w-10 h-10 bg-red-600 rounded-lg flex items-center justify-center shadow-lg shadow-red-200 shrink-0">
                    <svg class="h-6 w-6 text-white" fill="currentColor" viewBox="0 0 24 24">
                        <path
                            d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z" />
                    </svg>
                </div>
                <h1
                    class="text-2xl font-bold bg-clip-text text-transparent bg-gradient-to-r from-red-600 to-red-900 flex-1">
                    Transit Finder</h1>

                @auth
                    @php
                        $dashboardUrl = match (auth()->user()->role) {
                            'superadmin' => '/user',
                            'subadmin', 'employee' => '/panel',
                            default => '/portal',
                        };
                    @endphp
                    <div class="flex items-center gap-2">
                        <a href="{{ $dashboardUrl }}"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-red-600 hover:bg-red-700 text-white text-xs font-semibold transition-colors shadow-sm"
                            title="Go to dashboard">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                            </svg>
                            Dashboard
                        </a>
                        <form method="POST" action="{{ $dashboardUrl }}/logout" class="inline">
                            @csrf
                            <button type="submit"
                                class="inline-flex items-center justify-center w-8 h-8 rounded-lg border border-gray-200 hover:border-red-300 hover:bg-red-50 text-gray-400 hover:text-red-600 transition-colors"
                                title="Sign out">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                                </svg>
                            </button>
                        </form>
                    </div>
                @else
                    <div class="flex items-center gap-2">
                        <a href="/portal/login"
                            class="px-3 py-1.5 rounded-lg border border-gray-200 hover:border-red-300 hover:bg-red-50 text-gray-600 hover:text-red-600 text-xs font-semibold transition-colors">
                            Sign In
                        </a>
                        <a href="/portal/register"
                            class="px-3 py-1.5 rounded-lg bg-red-600 hover:bg-red-700 text-white text-xs font-semibold transition-colors shadow-sm">
                            Sign Up
                        </a>
                    </div>
                @endauth
            </div>

            <form id="route-search-form" class="space-y-4">
                <div class="relative">
                    <label class="block text-xs font-semibold text-gray-600 uppercase tracking-wider mb-1">Starting
                        Point</label>
                    <input type="text" id="origin" required
                        class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-lg focus:bg-white focus:border-red-500 focus:ring-2 focus:ring-red-200 outline-none transition-all"
                        placeholder="e.g. Gare, Mairie..." autocomplete="off">
                    <div id="origin-autocomplete" class="autocomplete-dropdown hidden"></div>
                </div>

                <div class="flex justify-center -my-2 relative z-10">
                    <button type="button" id="swap-locations"
                        class="w-8 h-8 bg-white border border-gray-200 hover:border-red-300 hover:bg-red-50 rounded-full flex items-center justify-center transition-all shadow-sm group">
                        <svg class="w-4 h-4 text-gray-500 group-hover:text-red-600 transition-colors rotate-90"
                            fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4" />
                        </svg>
                    </button>
                </div>

                <div class="relative">
                    <label
                        class="block text-xs font-semibold text-gray-600 uppercase tracking-wider mb-1">Destination</label>
                    <input type="text" id="destination" required
                        class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-lg focus:bg-white focus:border-red-500 focus:ring-2 focus:ring-red-200 outline-none transition-all"
                        placeholder="Where to?" autocomplete="off">
                    <div id="destination-autocomplete" class="autocomplete-dropdown hidden"></div>
                </div>

                <div class="grid grid-cols-2 gap-3 pt-2">
                    <div>
                        <label
                            class="block text-xs font-semibold text-gray-600 uppercase tracking-wider mb-1">Date</label>
                        <input type="date" id="date" required
                            class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-lg focus:bg-white focus:border-red-500 focus:ring-2 focus:ring-red-200 outline-none cursor-pointer text-sm"
                            value="{{ date('Y-m-d') }}" onclick="try{this.showPicker()}catch(e){}"
                            onfocus="try{this.showPicker()}catch(e){}">
                    </div>
                    <div>
                        <label
                            class="block text-xs font-semibold text-gray-600 uppercase tracking-wider mb-1">Time</label>
                        <input type="time" id="time" required
                            class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-lg focus:bg-white focus:border-red-500 focus:ring-2 focus:ring-red-200 outline-none cursor-pointer text-sm"
                            value="{{ date('H:i') }}" onclick="try{this.showPicker()}catch(e){}"
                            onfocus="try{this.showPicker()}catch(e){}">
                    </div>
                </div>

                <button type="submit"
                    class="w-full mt-2 px-6 py-3 bg-red-600 hover:bg-red-700 text-white rounded-lg font-semibold shadow-md shadow-red-200 transition-colors flex justify-center items-center gap-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                    </svg>
                    Search Routes
                </button>
                <div class="text-center pt-2">
                    <button type="button" id="reset-search"
                        class="text-xs text-gray-500 hover:text-red-600 underline">Reset map to all routes</button>
                </div>
            </form>
        </div>

        <div id="results-wrapper" class="flex-1 p-6 bg-gray-50">
            <div id="initial-state">
                <div class="mb-4">
                    <h2 class="text-sm font-semibold text-gray-700 uppercase tracking-wider">Available Routes</h2>
                    <p class="text-xs text-gray-400 mt-0.5">Click a route to highlight it on the map</p>
                </div>
                <div id="bus-cards-list" class="space-y-2 pb-10">
                    <div class="text-center py-8 text-gray-400 text-sm">Loading routes&hellip;</div>
                </div>
            </div>

            <div id="no-results"
                class="hidden text-center py-12 bg-white rounded-xl shadow-sm border border-gray-100 p-8">
                <svg class="h-12 w-12 text-red-400 mx-auto mb-3" fill="none" stroke="currentColor"
                    viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                <h3 class="text-lg font-bold mb-1 text-gray-800">No Routes Found</h3>
                <p class="text-sm text-gray-500">Try adjusting your search criteria or selecting different times.</p>
            </div>

            <div id="routes-list" class="space-y-4 hidden pb-10"></div>
        </div>
    </div>

    <!-- Right Pane: Map -->
    <div class="flex-1 h-full relative z-0 bg-gray-200">
        <div id="map" class="absolute inset-0"></div>


    </div>

    <script>
        // --- AUTOCOMPLETE LOGIC ---
        async function setupAutocomplete(inputId, dropdownId) {
            const input = document.getElementById(inputId),
                dropdown = document.getElementById(dropdownId);
            input.addEventListener('input', async function() {
                selectedCoords[inputId] = null; // User is typing manually — clear any stored coords
                const query = this.value.trim();
                if (query.length < 2) {
                    dropdown.classList.add('hidden');
                    return;
                }
                try {
                    const response = await fetch('/api/stops/search?q=' + encodeURIComponent(query));
                    const stops = await response.json();
                    if (stops.length === 0) {
                        dropdown.classList.add('hidden');
                        return;
                    }
                    dropdown.innerHTML = stops.map(s => {
                        const cityTag = s.city ? '<div class="text-[10px] text-gray-400 mt-0.5">' +
                            s.city + '</div>' : '';
                        return '<div class="autocomplete-item border-b border-gray-50 last:border-0" data-name="' +
                            s.stop_name + '" data-lat="' + (s.stop_lat || '') + '" data-lon="' + (s
                                .stop_lon || '') + '">' +
                            '<div class="font-medium text-sm text-gray-800">' + s.stop_name +
                            '</div>' + cityTag +
                            '</div>';
                    }).join('');
                    dropdown.classList.remove('hidden');
                    dropdown.querySelectorAll('.autocomplete-item').forEach(item => {
                        item.addEventListener('click', function() {
                            input.value = this.dataset.name;
                            if (this.dataset.lat && this.dataset.lon) {
                                selectedCoords[inputId] = {
                                    lat: this.dataset.lat,
                                    lon: this.dataset.lon
                                };
                            }
                            dropdown.classList.add('hidden');
                        });
                    });
                } catch (e) {
                    console.error('Autocomplete API error', e);
                }
            });
            document.addEventListener('click', function(e) {
                if (!input.contains(e.target) && !dropdown.contains(e.target)) {
                    dropdown.classList.add('hidden');
                }
            });
        }

        // Stores {lat, lon} for each selected autocomplete stop (reset when user types manually)
        let selectedCoords = {
            origin: null,
            destination: null
        };

        setupAutocomplete('origin', 'origin-autocomplete');
        setupAutocomplete('destination', 'destination-autocomplete');

        document.getElementById('swap-locations').addEventListener('click', function() {
            const origin = document.getElementById('origin');
            const destination = document.getElementById('destination');
            const temp = origin.value;
            origin.value = destination.value;
            destination.value = temp;
            const tempCoords = selectedCoords.origin;
            selectedCoords.origin = selectedCoords.destination;
            selectedCoords.destination = tempCoords;
        });

        // --- MAP LOGIC ---
        let map = null;
        let allRoutesLayer = L.layerGroup();
        let searchResultsLayer = L.layerGroup();
        let defaultRouteStopsLayer = L.layerGroup();
        let currentRouteData = null;
        let allRoutesShapes = {}; // Cache: route_short_name -> full shape_pts array
        let routeStopsCache = {}; // Cache: routeName -> stops array
        let selectedDefaultPolyline = null; // Currently pinned polyline in default view
        let allRoutesMetaData = {}; // Cache: routeName -> {color, longName}
        let allRoutePolylines = {}; // Cache: routeName -> [polyline, ...]
        let selectedBusCard = null; // Currently selected bus info card element
        let selectedBusRouteName = null; // Route name of selected bus card

        function initMap() {
            if (!map) {
                map = L.map('map', {
                    zoomControl: false // Move zoom control
                }).setView([47.5, 6.8], 10);

                L.control.zoom({
                    position: 'topright'
                }).addTo(map);

                // Grayscale base map
                L.tileLayer('https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png', {
                    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OSM</a> contributors &copy; <a href="https://carto.com/attributions">CARTO</a>',
                    subdomains: 'abcd',
                    maxZoom: 20
                }).addTo(map);

                allRoutesLayer.addTo(map);
                searchResultsLayer.addTo(map);
                defaultRouteStopsLayer.addTo(map);
            }
        }

        async function fetchRouteStops(routeName) {
            if (routeStopsCache[routeName]) return routeStopsCache[routeName];
            try {
                const res = await fetch('/api/routes/' + encodeURIComponent(routeName) + '/stops');
                const data = await res.json();
                routeStopsCache[routeName] = data.stops || [];
            } catch (e) {
                routeStopsCache[routeName] = [];
            }
            return routeStopsCache[routeName];
        }

        function showDefaultRouteStops(stops, color) {
            defaultRouteStopsLayer.clearLayers();
            if (!stops || !stops.length) return;
            stops.forEach((stop, i) => {
                const isEndpoint = i === 0 || i === stops.length - 1;
                L.circleMarker([stop.lat, stop.lon], {
                        radius: isEndpoint ? 7 : 4,
                        color: color,
                        fillColor: isEndpoint ? color : '#fff',
                        fillOpacity: 1,
                        weight: 2
                    }).bindTooltip(stop.name, {
                        sticky: true,
                        className: 'font-sans text-sm'
                    })
                    .addTo(defaultRouteStopsLayer);
            });
        }

        function renderBusInfoPanel() {
            const container = document.getElementById('bus-cards-list');
            if (!container) return;
            container.innerHTML = '';

            const routeNames = Object.keys(allRoutesMetaData).sort((a, b) => {
                // Sort: pure numbers first (numerically), then alphanumeric
                const numA = parseInt(a, 10);
                const numB = parseInt(b, 10);
                if (!isNaN(numA) && !isNaN(numB)) return numA - numB;
                if (!isNaN(numA)) return -1;
                if (!isNaN(numB)) return 1;
                return a.localeCompare(b);
            });

            if (routeNames.length === 0) {
                container.innerHTML = '<div class="text-center py-8 text-gray-400 text-sm">No routes available.</div>';
                return;
            }

            routeNames.forEach(routeName => {
                const meta = allRoutesMetaData[routeName];
                const card = document.createElement('div');
                card.className = 'bus-card bg-white rounded-lg border border-gray-200 shadow-sm cursor-pointer';
                card.dataset.route = routeName;

                card.innerHTML = `
                    <div class="bus-card-header flex items-center gap-3 p-3">
                        <span class="inline-flex items-center justify-center min-w-[2.75rem] h-7 px-2 rounded text-white text-xs font-bold shrink-0" style="background:${meta.color}">${routeName}</span>
                        <div class="min-w-0 flex-1">
                            <div class="text-xs font-medium text-gray-700 leading-tight line-clamp-2">${meta.longName}</div>
                        </div>
                        <svg class="bus-card-chevron w-4 h-4 text-gray-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                        </svg>
                    </div>
                    <div class="bus-card-stops hidden border-t border-gray-100 max-h-56 overflow-y-auto"></div>
                `;

                card.addEventListener('click', () => selectBusCard(routeName, card));
                container.appendChild(card);
            });
        }

        async function selectBusCard(routeName, cardEl) {
            const isSameCard = selectedBusCard === cardEl;

            // Deselect previously selected card
            if (selectedBusCard) {
                selectedBusCard.classList.remove('bus-card-selected');
                const prevStops = selectedBusCard.querySelector('.bus-card-stops');
                if (prevStops) prevStops.classList.add('hidden');
                const prevRoute = selectedBusCard.dataset.route;
                if (allRoutePolylines[prevRoute]) {
                    allRoutePolylines[prevRoute].forEach(p => p.setStyle({
                        weight: 2,
                        opacity: 0.6
                    }));
                }
                selectedBusCard = null;
                selectedBusRouteName = null;
                selectedDefaultPolyline = null;
                defaultRouteStopsLayer.clearLayers();
            }

            // If same card was clicked again, just deselect (toggle off)
            if (isSameCard) return;

            // Select the new card
            selectedBusCard = cardEl;
            selectedBusRouteName = routeName;
            cardEl.classList.add('bus-card-selected');

            // Highlight all polylines for this route on the map
            if (allRoutePolylines[routeName]) {
                allRoutePolylines[routeName].forEach(p => {
                    p.setStyle({
                        weight: 5,
                        opacity: 1
                    });
                    p.bringToFront();
                });
                const bounds = L.featureGroup(allRoutePolylines[routeName]).getBounds();
                if (bounds.isValid()) {
                    map.fitBounds(bounds, {
                        padding: [40, 40]
                    });
                }
            }

            // Fetch and display stops in the card
            const stopsEl = cardEl.querySelector('.bus-card-stops');
            if (stopsEl) {
                stopsEl.innerHTML = '<div class="px-3 py-2 text-xs text-gray-400">Loading stops&hellip;</div>';
                stopsEl.classList.remove('hidden');

                const stops = await fetchRouteStops(routeName);
                const meta = allRoutesMetaData[routeName];

                if (stops && stops.length > 0) {
                    showDefaultRouteStops(stops, meta ? meta.color : '#9CA3AF');

                    stopsEl.innerHTML = stops.map((s, i) => `
                        <div class="flex items-start gap-2 px-3 py-1.5 ${i < stops.length - 1 ? 'border-b border-gray-50' : ''}">
                            <div class="flex flex-col items-center shrink-0 mt-1">
                                <div class="w-2 h-2 rounded-full border-2 ${i === 0 || i === stops.length - 1 ? 'border-red-400 bg-red-400' : 'border-gray-300 bg-white'}"></div>
                                ${i < stops.length - 1 ? '<div class="w-px h-3 bg-gray-200 mt-0.5"></div>' : ''}
                            </div>
                            <span class="text-xs text-gray-600 leading-tight pb-1">${s.name}</span>
                        </div>
                    `).join('');
                } else {
                    stopsEl.innerHTML =
                        '<div class="px-3 py-2 text-xs text-gray-400">No stop information available.</div>';
                }
            }
        }

        async function loadAllShapes() {
            try {
                const res = await fetch('/api/routes/all-shapes');
                const data = await res.json();

                if (data && data.routes && Array.isArray(data.routes)) {
                    allRoutesLayer.clearLayers();
                    allRoutesShapes = {};
                    data.routes.forEach(r => {
                        if (r.shape_pts && r.shape_pts.length > 1) {
                            // Cache ALL shapes per route (each direction has its own shape)
                            const rName = r.route_short_name || r.route_name;
                            if (rName) {
                                if (!allRoutesShapes[rName]) allRoutesShapes[rName] = [];
                                allRoutesShapes[rName].push(r.shape_pts);
                            }
                            let color = r.route_color ? '#' + r.route_color : '#9CA3AF';

                            // Store route metadata (first occurrence wins)
                            if (rName && !allRoutesMetaData[rName]) {
                                allRoutesMetaData[rName] = {
                                    color: color,
                                    longName: r.route_long_name || r.route_name || rName
                                };
                                allRoutePolylines[rName] = [];
                            }

                            let polyline = L.polyline(r.shape_pts, {
                                color: color,
                                weight: 2,
                                opacity: 0.6,
                                routeName: rName,
                                routeColor: color
                            });

                            let routeTitle = r.route_name || r.route_short_name || 'Route';
                            let agName = r.agency_name ?
                                `<br><span style="font-size:11px;color:#666;">${r.agency_name}</span>` : '';

                            polyline.bindTooltip(`<b>${routeTitle}</b>${agName}<br><span style="font-size:10px;color:#16a34a">🌿 CNG · Natural Gas</span>`, {
                                sticky: true,
                                className: 'font-sans'
                            });

                            polyline.on('mouseover', async function() {
                                this.setStyle({
                                    weight: 5,
                                    opacity: 1
                                });
                                this.bringToFront();
                                const stops = await fetchRouteStops(this.options.routeName);
                                showDefaultRouteStops(stops, this.options.routeColor);
                            });
                            polyline.on('mouseout', function() {
                                if (selectedDefaultPolyline !== this) {
                                    this.setStyle({
                                        weight: 2,
                                        opacity: 0.6
                                    });
                                }
                                if (selectedDefaultPolyline === null) {
                                    defaultRouteStopsLayer.clearLayers();
                                }
                            });
                            polyline.on('click', async function(e) {
                                L.DomEvent.stopPropagation(e);
                                if (selectedDefaultPolyline === this) {
                                    // Deselect
                                    selectedDefaultPolyline = null;
                                    this.setStyle({
                                        weight: 2,
                                        opacity: 0.6
                                    });
                                    defaultRouteStopsLayer.clearLayers();
                                } else {
                                    // Select new
                                    if (selectedDefaultPolyline) {
                                        selectedDefaultPolyline.setStyle({
                                            weight: 2,
                                            opacity: 0.6
                                        });
                                    }
                                    selectedDefaultPolyline = this;
                                    this.setStyle({
                                        weight: 5,
                                        opacity: 1
                                    });
                                    this.bringToFront();
                                    const stops = await fetchRouteStops(this.options.routeName);
                                    showDefaultRouteStops(stops, this.options.routeColor);
                                }
                            });

                            allRoutesLayer.addLayer(polyline);
                            // Store polyline reference for bus card highlighting
                            if (rName && allRoutePolylines[rName]) {
                                allRoutePolylines[rName].push(polyline);
                            }
                        }
                    });

                    if (allRoutesLayer.getLayers().length > 0) {
                        let layerGroupBounds = L.featureGroup(allRoutesLayer.getLayers()).getBounds();
                        if (layerGroupBounds.isValid()) {
                            map.fitBounds(layerGroupBounds, {
                                padding: [50, 50]
                            });
                        }
                    }

                    renderBusInfoPanel();
                }
            } catch (e) {
                console.error('Failed to load all shapes:', e);
            }
        }

        // ── EVOLITY CO2 HELPERS ──
        // Haversine distance in km over an array of [lat, lon] points
        function haversineKm(pts) {
            if (!pts || pts.length < 2) return 0;
            let total = 0;
            for (let i = 1; i < pts.length; i++) {
                const [lat1, lon1] = pts[i - 1];
                const [lat2, lon2] = pts[i];
                const R = 6371;
                const dLat = (lat2 - lat1) * Math.PI / 180;
                const dLon = (lon2 - lon1) * Math.PI / 180;
                const a = Math.sin(dLat / 2) ** 2 +
                    Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) *
                    Math.sin(dLon / 2) ** 2;
                total += R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
            }
            return total;
        }

        // Constants: Evolity GNV bus vs average car
        const CO2_CAR_G_PER_KM = 120;   // g CO2/km (ADEME France baseline)
        const CO2_BUS_G_PER_KM = 32;    // g CO2/km – Evolity CNG/GNV (Iveco Crealis)
        const CO2_SAVED_G_PER_KM = CO2_CAR_G_PER_KM - CO2_BUS_G_PER_KM; // 88g/km

        function co2Badge(distKm) {
            if (!distKm || distKm <= 0) return '';
            const savedG = distKm * CO2_SAVED_G_PER_KM;
            const savedLabel = savedG >= 1000
                ? (savedG / 1000).toFixed(2).replace(/\.?0+$/, '') + ' kg'
                : Math.round(savedG) + ' g';
            const busCo2G = distKm * CO2_BUS_G_PER_KM;
            const busCo2Label = busCo2G >= 1000
                ? (busCo2G / 1000).toFixed(2).replace(/\.?0+$/, '') + ' kg'
                : Math.round(busCo2G) + ' g';
            return `<div class="flex items-center gap-2 mt-2 px-3 py-2 bg-green-50 border border-green-100 rounded-lg">
                <span class="text-green-600 text-xs">🌿</span>
                <div class="flex-1 min-w-0">
                    <span class="text-[11px] font-semibold text-green-700">Natural Gas Bus (CNG) · ${(distKm).toFixed(1)} km</span>
                    <div class="text-[10px] text-green-600 mt-0.5">This trip emits <strong>${busCo2Label} CO₂</strong> · Saves <strong class="text-green-700">${savedLabel} CO₂</strong> vs car</div>
                </div>
            </div>`;
        }

        function co2PopupLine(distKm) {
            if (!distKm || distKm <= 0) return '';
            const savedG = Math.round(distKm * CO2_SAVED_G_PER_KM);
            const savedLabel = savedG >= 1000
                ? (savedG / 1000).toFixed(2).replace(/\.?0+$/, '') + ' kg'
                : savedG + ' g';
            return `<div style="margin-top:6px;padding:4px 6px;background:#f0fdf4;border-radius:6px;border:1px solid #bbf7d0;font-size:11px;color:#16a34a">🌿 <strong>Evolity CNG</strong> · ${(distKm).toFixed(1)} km · saves <strong>${savedLabel} CO₂</strong> vs car</div>`;
        }

        // Initialize Map & Load shapes
        document.addEventListener('DOMContentLoaded', () => {
            initMap();
            loadAllShapes();
        });

        // Search form reset
        document.getElementById('reset-search').addEventListener('click', () => {
            document.getElementById('origin').value = '';
            document.getElementById('destination').value = '';
            document.getElementById('routes-list').classList.add('hidden');
            document.getElementById('initial-state').classList.remove('hidden');
            document.getElementById('no-results').classList.add('hidden');

            searchResultsLayer.clearLayers();
            defaultRouteStopsLayer.clearLayers();
            if (selectedDefaultPolyline) {
                selectedDefaultPolyline.setStyle({
                    weight: 2,
                    opacity: 0.6
                });
                selectedDefaultPolyline = null;
            }
            // Reset bus card selection
            if (selectedBusCard) {
                selectedBusCard.classList.remove('bus-card-selected');
                const stopsEl = selectedBusCard.querySelector('.bus-card-stops');
                if (stopsEl) stopsEl.classList.add('hidden');
            }
            if (selectedBusRouteName && allRoutePolylines[selectedBusRouteName]) {
                allRoutePolylines[selectedBusRouteName].forEach(p => p.setStyle({
                    weight: 2,
                    opacity: 0.6
                }));
            }
            selectedBusCard = null;
            selectedBusRouteName = null;
            if (!map.hasLayer(allRoutesLayer)) {
                map.addLayer(allRoutesLayer);
            }
            if (allRoutesLayer.getLayers().length > 0) {
                map.fitBounds(L.featureGroup(allRoutesLayer.getLayers()).getBounds(), {
                    padding: [50, 50]
                });
            }
        });

        // --- RESULTS RENDERING & HIGHLIGHT LOGIC ---
        function renderTransferRoute(route, routeIndex) {
            const legsHtml = route.legs.map((leg, idx) => {
                if (leg.type === 'walk') {
                    return `
                        <div class="flex items-center gap-3 py-2 border-l-2 border-blue-400 pl-3 ml-2 my-1">
                            <div class="flex-1">
                                <div class="text-xs font-semibold text-blue-600 uppercase tracking-wide">Walk ${leg.duration} min</div>
                                <div class="text-xs text-gray-600 truncate">${leg.from} → ${leg.to}</div>
                            </div>
                        </div>
                    `;
                } else if (leg.type === 'transit') {
                    const routeBadgeStyle = leg.route_color ?
                        `background-color: #${leg.route_color}; color: ${leg.route_text_color ? '#' + leg.route_text_color : '#FFFFFF'};` :
                        'background-color: #6B7280; color: #FFFFFF;';

                    const midStops = leg.stops ? leg.stops.filter(s => s.is_intermediate) : [];
                    const midStopsHtml = midStops.length > 0 ? `
                        <details class="mt-1">
                            <summary class="text-[10px] text-gray-400 cursor-pointer select-none hover:text-gray-600">${midStops.length} intermediate stop${midStops.length > 1 ? 's' : ''}</summary>
                            <div class="mt-1 ml-2 space-y-0.5 border-l border-gray-200 pl-2">
                                ${midStops.map(s => `<div class="flex justify-between text-[10px] text-gray-500"><span>${s.name}</span><span class="font-mono text-gray-400">${s.arrival}</span></div>`).join('')}
                            </div>
                        </details>
                    ` : '';

                    const legShapePts = leg.shape_pts && leg.shape_pts.length > 1 ? leg.shape_pts
                        : (leg.stops && leg.stops.length > 1 ? leg.stops.map(s => [parseFloat(s.lat), parseFloat(s.lon)]) : null);
                    const legDistKm = haversineKm(legShapePts);

                    return `
                        <div class="flex items-start gap-3 py-3 border-l-2 border-red-500 pl-3 ml-2 relative">
                            <div class="absolute w-2 h-2 rounded-full bg-white border-2 border-red-500 -left-[5px] top-4"></div>
                            
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2 mb-1">
                                    <span class="inline-block px-1.5 py-0.5 text-xs font-bold rounded shadow-sm" style="${routeBadgeStyle}">
                                        ${leg.route_name}
                                    </span>
                                </div>
                                <div class="text-xs font-medium text-gray-900 truncate">${leg.from} → ${leg.to}</div>
                                ${midStopsHtml}
                                ${co2Badge(legDistKm)}
                            </div>
                            <div class="flex flex-col items-end text-xs text-gray-600 gap-0.5 whitespace-nowrap">
                                <div class="font-bold text-red-600">${leg.departure_time}</div>
                                <div class="font-bold text-gray-700">${leg.arrival_time}</div>
                            </div>
                        </div>
                    `;
                }
                return '';
            }).join('');

            return `
                <div class="route-card bg-white rounded-xl shadow-sm border border-orange-100 hover:border-orange-300 p-4 transition-all" onclick="showRouteOnMap(${routeIndex})">
                    <div class="flex items-center gap-4 bg-gray-50/80 rounded-lg p-3 mb-3 border border-gray-100">
                        <div class="flex-1">
                            <div class="text-xs text-gray-500 uppercase tracking-wider">Departs</div>
                            <div class="text-xl font-bold text-red-600">${route.leave_at || route.departure_time}</div>
                        </div>
                        <div class="flex-1 text-center border-l border-r border-gray-200 px-2">
                            <div class="text-xs text-gray-500 uppercase tracking-wider">Duration</div>
                            <div class="text-sm font-semibold text-gray-800">${route.duration}</div>
                        </div>
                        <div class="flex-1 text-right">
                            <div class="text-xs text-gray-500 uppercase tracking-wider">Arrives</div>
                            <div class="text-xl font-bold text-green-600">${route.arrival_time}</div>
                        </div>
                    </div>
                    <div class="space-y-0 text-sm">
                        ${legsHtml}
                    </div>
                </div>
            `;
        }

        document.getElementById('route-search-form').addEventListener('submit', async function(e) {
            e.preventDefault();
            const origin = document.getElementById('origin');
            const destination = document.getElementById('destination');
            const routesList = document.getElementById('routes-list');
            const overlay = document.getElementById('loading-overlay');
            const noResults = document.getElementById('no-results');
            const initBtn = document.getElementById('initial-state');

            initBtn.classList.add('hidden');
            overlay.classList.add('active');
            noResults.classList.add('hidden');
            routesList.innerHTML = '';
            routesList.classList.add('hidden');

            if (map.hasLayer(allRoutesLayer)) {
                map.removeLayer(allRoutesLayer);
            }
            searchResultsLayer.clearLayers();

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
                        time: document.getElementById('time').value,
                        origin_lat: selectedCoords.origin ? selectedCoords.origin.lat : null,
                        origin_lon: selectedCoords.origin ? selectedCoords.origin.lon : null,
                        dest_lat: selectedCoords.destination ? selectedCoords.destination.lat :
                            null,
                        dest_lon: selectedCoords.destination ? selectedCoords.destination.lon :
                            null
                    })
                });

                const data = await response.json();
                overlay.classList.remove('active');

                if (!data.routes || data.routes.length === 0) {
                    noResults.classList.remove('hidden');
                    return;
                }

                currentRouteData = data.routes;

                routesList.innerHTML = data.routes.map((route, index) => {
                    if (route.connection_type === 'transfer' && route.legs) {
                        return renderTransferRoute(route, index);
                    }

                    const exactMatchBadge = route.is_exact_match ?
                        '<span class="inline-block px-2 py-0.5 bg-green-100 text-green-800 text-[10px] font-bold rounded uppercase tracking-wider">Exact Focus</span>' :
                        '';

                    const connectionBadge = route.connection_type === 'from_origin' ?
                        '<span class="inline-block px-2 py-0.5 bg-blue-100 text-blue-800 text-[10px] font-bold rounded uppercase tracking-wider">Partial: Origin</span>' :
                        route.connection_type === 'to_destination' ?
                        '<span class="inline-block px-2 py-0.5 bg-purple-100 text-purple-800 text-[10px] font-bold rounded uppercase tracking-wider">Partial: Dest</span>' :
                        '';

                    const routeBadgeStyle = route.route_color ?
                        `background-color: #${route.route_color}; color: ${route.route_text_color ? '#' + route.route_text_color : '#FFFFFF'};` :
                        'background-color: #6B7280; color: #FFFFFF;';

                    const directMidStops = route.stops ? route.stops.filter(s => s.is_intermediate) :
                [];
                    const directMidStopsHtml = directMidStops.length > 0 ? `
                        <details class="mt-2">
                            <summary class="text-[10px] text-gray-400 cursor-pointer select-none hover:text-gray-600">${directMidStops.length} intermediate stop${directMidStops.length > 1 ? 's' : ''}</summary>
                            <div class="mt-1 ml-2 space-y-0.5 border-l border-gray-200 pl-2">
                                ${directMidStops.map(s => `<div class="flex justify-between text-[10px] text-gray-500"><span>${s.name}</span><span class="font-mono text-gray-400">${s.arrival}</span></div>`).join('')}
                            </div>
                        </details>
                    ` : '';

                    const directShapePts = route.shape_pts && route.shape_pts.length > 1 ? route.shape_pts
                        : (route.stops && route.stops.length > 1 ? route.stops.map(s => [parseFloat(s.lat), parseFloat(s.lon)]) : null);
                    const directDistKm = haversineKm(directShapePts);

                    return `
                        <div class="route-card bg-white rounded-xl shadow-sm border border-gray-100 p-4 transition-all" onclick="showRouteOnMap(${index})">
                            <div class="flex items-center gap-2 mb-3">
                                <span class="inline-block px-2 py-1 text-sm font-bold rounded shadow-sm" style="${routeBadgeStyle}">
                                    ${route.route_name}
                                </span>
                                ${exactMatchBadge}
                                ${connectionBadge}
                            </div>
                            
                            <div class="flex items-center gap-4 bg-gray-50/80 rounded-lg p-3">
                                ${route.departure_time !== '?' ? `
                                                                    <div class="flex-1">
                                                                        <div class="text-xs text-gray-500 uppercase tracking-wider">Departs</div>
                                                                        <div class="text-xl font-bold text-red-600">${route.leave_at || route.departure_time}</div>
                                                                    </div>` : ''}
                                
                                ${route.duration !== 'N/A' ? `
                                                                    <div class="flex-1 text-center border-l border-r border-gray-200 px-2">
                                                                        <div class="text-xs text-gray-500 uppercase tracking-wider">Duration</div>
                                                                        <div class="text-sm font-semibold text-gray-800">${route.duration}</div>
                                                                    </div>` : ''}
                                
                                ${route.arrival_time !== '?' ? `
                                                                    <div class="flex-1 text-right">
                                                                        <div class="text-xs text-gray-500 uppercase tracking-wider">Arrives</div>
                                                                        <div class="text-xl font-bold text-green-600">${route.arrival_time}</div>
                                                                    </div>` : ''}
                            </div>
                            ${directMidStopsHtml}
                            ${co2Badge(directDistKm)}
                        </div>
                    `;
                }).join('');

                routesList.classList.remove('hidden');

                if (data.routes && data.routes.length > 0) {
                    showRouteOnMap(0);
                }

            } catch (error) {
                overlay.classList.remove('active');
                noResults.classList.remove('hidden');
                console.error('Error searching routes:', error);
            }
        });

        // Clip a single shape array to the nearest points for a from/to coord pair
        function clipOneShape(shapePts, fromLat, fromLon, toLat, toLon) {
            if (!shapePts || shapePts.length < 2) return null;
            let originIdx = 0,
                destIdx = shapePts.length - 1;
            let minO = Infinity,
                minD = Infinity;
            for (let i = 0; i < shapePts.length; i++) {
                const pt = shapePts[i];
                const dO = Math.hypot(pt[0] - fromLat, pt[1] - fromLon);
                const dD = Math.hypot(pt[0] - toLat, pt[1] - toLon);
                if (dO < minO) {
                    minO = dO;
                    originIdx = i;
                }
                if (dD < minD) {
                    minD = dD;
                    destIdx = i;
                }
            }
            if (originIdx > destIdx)[originIdx, destIdx] = [destIdx, originIdx];
            const clipped = shapePts.slice(originIdx, destIdx + 1);
            return clipped.length > 1 ? {
                pts: clipped,
                dist: minO + minD
            } : null;
        }

        // Try all cached shapes for a route and return the best-matching clip
        function clipCachedShape(shapesOrPts, fromLat, fromLon, toLat, toLon) {
            if (!shapesOrPts) return null;
            // Support both array-of-shapes (new) and single array (legacy)
            const list = Array.isArray(shapesOrPts[0]) && !Array.isArray(shapesOrPts[0][0]) ? [
                    shapesOrPts
                ] // single flat array of [lat,lon] pairs
                :
                shapesOrPts; // array of shape arrays
            let best = null;
            for (const shape of list) {
                const result = clipOneShape(shape, fromLat, fromLon, toLat, toLon);
                if (result && (!best || result.dist < best.dist)) best = result;
            }
            return best ? best.pts : null;
        }

        window.showRouteOnMap = function(routeIndex) {
            if (!currentRouteData || !currentRouteData[routeIndex]) return;

            const route = currentRouteData[routeIndex];
            searchResultsLayer.clearLayers();

            document.querySelectorAll('.route-card').forEach((card, idx) => {
                if (idx === routeIndex) {
                    card.classList.add('is-selected');
                } else {
                    card.classList.remove('is-selected');
                }
            });

            const bounds = [];
            const colors = {
                walk: '#3B82F6',
                transit: '#DC2626'
            };

            function addBounds(lat, lon) {
                if (lat != null && lon != null) bounds.push([parseFloat(lat), parseFloat(lon)]);
            }

            function resolveTransitShape(leg) {
                // 1. Use clipped shape from API if available and meaningful
                if (leg.shape_pts && leg.shape_pts.length > 2) return leg.shape_pts;
                // 2. Fall back to cached full shape, clipped to this leg's endpoints
                const cached = allRoutesShapes[leg.route_name];
                if (cached && leg.from_lat && leg.to_lat) {
                    const clipped = clipCachedShape(cached, parseFloat(leg.from_lat), parseFloat(leg.from_lon),
                        parseFloat(leg.to_lat), parseFloat(leg.to_lon));
                    if (clipped) return clipped;
                }
                // 3. Fall back to stop coords polyline
                if (leg.stops && leg.stops.length > 1) {
                    return leg.stops.map(s => s.lat && s.lon ? [parseFloat(s.lat), parseFloat(s.lon)] : null).filter(
                        Boolean);
                }
                // 4. Last resort: straight line
                return leg.from_lat ? [
                    [parseFloat(leg.from_lat), parseFloat(leg.from_lon)],
                    [parseFloat(leg.to_lat), parseFloat(leg.to_lon)]
                ] : null;
            }

            if (route.legs && route.legs.length > 0) {
                for (const leg of route.legs) {
                    if (leg.type === 'walk') {
                        if (leg.from_lat && leg.from_lon && leg.to_lat && leg.to_lon) {
                            L.polyline(
                                    [
                                        [leg.from_lat, leg.from_lon],
                                        [leg.to_lat, leg.to_lon]
                                    ], {
                                        color: colors.walk,
                                        weight: 4,
                                        opacity: 0.7,
                                        dashArray: '6, 8'
                                    }
                                ).addTo(searchResultsLayer)
                                .bindPopup(`<strong>🚶 Walk ${leg.duration} min</strong><br>${leg.from} → ${leg.to}`);
                            addBounds(leg.from_lat, leg.from_lon);
                            addBounds(leg.to_lat, leg.to_lon);
                        }
                    } else if (leg.type === 'transit') {
                        const pathColor = leg.route_color ? '#' + leg.route_color : colors.transit;
                        addBounds(leg.from_lat, leg.from_lon);
                        addBounds(leg.to_lat, leg.to_lon);

                        const shapePts = resolveTransitShape(leg);
                        if (shapePts && shapePts.length > 1) {
                            const legMapDistKm = haversineKm(shapePts);
                            L.polyline(shapePts, {
                                    color: pathColor,
                                    weight: 6,
                                    opacity: 0.9
                                })
                                .addTo(searchResultsLayer)
                                .bindPopup(`<strong>🚌 ${leg.route_name}</strong><br>${leg.from} → ${leg.to}${co2PopupLine(legMapDistKm)}`);
                            shapePts.forEach(pt => bounds.push(pt));
                        }

                        // Stop markers
                        const stopsToRender = (leg.stops && leg.stops.length > 0) ? leg.stops : [{
                                lat: leg.from_lat,
                                lon: leg.from_lon,
                                name: leg.from,
                                arrival: leg.departure_time,
                                is_intermediate: false
                            },
                            {
                                lat: leg.to_lat,
                                lon: leg.to_lon,
                                name: leg.to,
                                arrival: leg.arrival_time,
                                is_intermediate: false
                            }
                        ];
                        stopsToRender.forEach(stop => {
                            if (!stop.lat || !stop.lon) return;
                            const isMid = stop.is_intermediate === true;
                            L.circleMarker([parseFloat(stop.lat), parseFloat(stop.lon)], {
                                    radius: isMid ? 4 : 7,
                                    fillColor: isMid ? '#FFFFFF' : pathColor,
                                    color: pathColor,
                                    weight: isMid ? 2 : 3,
                                    opacity: 1,
                                    fillOpacity: isMid ? 0.85 : 1
                                }).addTo(searchResultsLayer)
                                .bindPopup(
                                    `<strong>${stop.name}</strong><br><span style="color:#666">${stop.arrival || ''}</span>`
                                );
                        });
                    }
                }

            } else if (route.stops && route.stops.length > 0) {
                const pathColor = route.route_color ? '#' + route.route_color : colors.transit;

                // Resolve shape: API shape → cached full shape clipped → stop coords
                let shapePts = (route.shape_pts && route.shape_pts.length > 2) ? route.shape_pts : null;
                if (!shapePts) {
                    const cached = allRoutesShapes[route.route_name];
                    if (cached && route.stops.length >= 2) {
                        const f = route.stops[0],
                            l = route.stops[route.stops.length - 1];
                        shapePts = clipCachedShape(cached, parseFloat(f.lat), parseFloat(f.lon), parseFloat(l.lat),
                            parseFloat(l.lon));
                    }
                }
                if (!shapePts) {
                    shapePts = route.stops.map(s => s.lat && s.lon ? [parseFloat(s.lat), parseFloat(s.lon)] : null)
                        .filter(Boolean);
                }

                if (shapePts && shapePts.length > 1) {
                    const directMapDistKm = haversineKm(shapePts);
                    L.polyline(shapePts, {
                            color: pathColor,
                            weight: 6,
                            opacity: 0.9
                        })
                        .addTo(searchResultsLayer)
                        .bindPopup(`<strong>🚌 ${route.route_name}</strong>${co2PopupLine(directMapDistKm)}`);
                    shapePts.forEach(pt => bounds.push(pt));
                }

                route.stops.forEach(stop => {
                    if (!stop || !stop.lat || !stop.lon) return;
                    const isMid = stop.is_intermediate === true;
                    L.circleMarker([parseFloat(stop.lat), parseFloat(stop.lon)], {
                            radius: isMid ? 4 : 7,
                            fillColor: isMid ? '#FFFFFF' : pathColor,
                            color: pathColor,
                            weight: isMid ? 2 : 3,
                            opacity: 1,
                            fillOpacity: isMid ? 0.85 : 1
                        }).addTo(searchResultsLayer)
                        .bindPopup(
                            `<strong>${stop.name}</strong><br><span style="color:#666">${stop.arrival}</span>`);
                });
            }

            if (bounds.length > 0) {
                map.fitBounds(bounds, {
                    padding: [50, 50],
                    animate: true,
                    duration: 0.5
                });
            }
        };
    </script>
</body>

</html>
