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
                <div class="w-10 h-10 bg-red-600 rounded-lg flex items-center justify-center shadow-lg shadow-red-200">
                    <svg class="h-6 w-6 text-white" fill="currentColor" viewBox="0 0 24 24">
                        <path
                            d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z" />
                    </svg>
                </div>
                <h1 class="text-2xl font-bold bg-clip-text text-transparent bg-gradient-to-r from-red-600 to-red-900">
                    Transit Finder</h1>
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
                            value="{{ date('Y-m-d') }}">
                    </div>
                    <div>
                        <label
                            class="block text-xs font-semibold text-gray-600 uppercase tracking-wider mb-1">Time</label>
                        <input type="time" id="time" required
                            class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-lg focus:bg-white focus:border-red-500 focus:ring-2 focus:ring-red-200 outline-none cursor-pointer text-sm"
                            value="{{ date('H:i') }}">
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
            <div id="initial-state" class="text-center py-12 text-gray-400">
                <svg class="w-16 h-16 mx-auto mb-4 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                        d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7">
                    </path>
                </svg>
                <p>Enter your origin and destination to find the best routes.</p>
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

        <!-- Legend Overlay -->
        <div
            class="absolute bottom-6 right-6 z-[1000] bg-white/95 backdrop-blur rounded-lg p-4 shadow-xl text-sm border border-gray-100">
            <div class="font-semibold text-gray-800 mb-2">Map Legend</div>
            <div class="flex flex-col gap-2">
                <div class="flex items-center gap-3">
                    <div style="width: 24px; height: 3px; background: #DC2626;"></div>
                    <span class="text-gray-600">Transit</span>
                </div>
                <div class="flex items-center gap-3">
                    <div style="width: 24px; height: 3px; background: #3B82F6; border-top: 2px dashed #3B82F6;"></div>
                    <span class="text-gray-600">Walking</span>
                </div>
            </div>
        </div>
    </div>

    <script>
        // --- AUTOCOMPLETE LOGIC ---
        async function setupAutocomplete(inputId, dropdownId) {
            const input = document.getElementById(inputId),
                dropdown = document.getElementById(dropdownId);
            input.addEventListener('input', async function() {
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
                            s.stop_name + '">' +
                            '<div class="font-medium text-sm text-gray-800">' + s.stop_name +
                            '</div>' + cityTag +
                            '</div>';
                    }).join('');
                    dropdown.classList.remove('hidden');
                    dropdown.querySelectorAll('.autocomplete-item').forEach(item => {
                        item.addEventListener('click', function() {
                            input.value = this.dataset.name;
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

        setupAutocomplete('origin', 'origin-autocomplete');
        setupAutocomplete('destination', 'destination-autocomplete');

        document.getElementById('swap-locations').addEventListener('click', function() {
            const origin = document.getElementById('origin');
            const destination = document.getElementById('destination');
            const temp = origin.value;
            origin.value = destination.value;
            destination.value = temp;
        });

        // --- MAP LOGIC ---
        let map = null;
        let allRoutesLayer = L.layerGroup();
        let searchResultsLayer = L.layerGroup();
        let currentRouteData = null;

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
            }
        }

        async function loadAllShapes() {
            try {
                const res = await fetch('/api/routes/all-shapes');
                const data = await res.json();

                if (data && data.routes && Array.isArray(data.routes)) {
                    allRoutesLayer.clearLayers();
                    data.routes.forEach(r => {
                        if (r.shape_pts && r.shape_pts.length > 1) {
                            let color = r.route_color ? '#' + r.route_color : '#9CA3AF';
                            let polyline = L.polyline(r.shape_pts, {
                                color: color,
                                weight: 2,
                                opacity: 0.6
                            });

                            let routeTitle = r.route_name || r.route_short_name || 'Route';
                            let agName = r.agency_name ?
                                `<br><span style="font-size:11px;color:#666;">${r.agency_name}</span>` : '';

                            polyline.bindTooltip(`<b>${routeTitle}</b>${agName}`, {
                                sticky: true,
                                className: 'font-sans'
                            });

                            polyline.on('mouseover', function() {
                                this.setStyle({
                                    weight: 5,
                                    opacity: 1
                                });
                                this.bringToFront();
                            });
                            polyline.on('mouseout', function() {
                                this.setStyle({
                                    weight: 2,
                                    opacity: 0.6
                                });
                            });

                            allRoutesLayer.addLayer(polyline);
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
                }
            } catch (e) {
                console.error('Failed to load all shapes:', e);
            }
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
                        time: document.getElementById('time').value
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

        window.showRouteOnMap = function(routeIndex) {
            if (!currentRouteData || !currentRouteData[routeIndex]) return;

            const route = currentRouteData[routeIndex];
            searchResultsLayer.clearLayers();

            document.querySelectorAll('.route-card').forEach((card, idx) => {
                if (idx === routeIndex) {
                    card.classList.add('ring-2', 'ring-red-500', 'border-transparent');
                } else {
                    card.classList.remove('ring-2', 'ring-red-500', 'border-transparent');
                }
            });

            const bounds = [];
            const colors = {
                walk: '#3B82F6',
                transit: '#DC2626'
            };

            if (route.legs && route.legs.length > 0) {
                for (const leg of route.legs) {
                    if (leg.type === 'walk') {
                        if (leg.from_lat && leg.from_lon && leg.to_lat && leg.to_lon) {
                            const walkLine = L.polyline(
                                [
                                    [leg.from_lat, leg.from_lon],
                                    [leg.to_lat, leg.to_lon]
                                ], {
                                    color: colors.walk,
                                    weight: 4,
                                    opacity: 0.7,
                                    dashArray: '8, 8'
                                }
                            ).addTo(searchResultsLayer);
                            walkLine.bindPopup(
                                `<strong>🚶 Walk ${leg.duration} min</strong><br>${leg.from} → ${leg.to}`);
                            bounds.push([leg.from_lat, leg.from_lon], [leg.to_lat, leg.to_lon]);
                        }
                    } else if (leg.type === 'transit') {
                        let pathColor = leg.route_color ? '#' + leg.route_color : colors.transit;
                        if (leg.shape_pts && leg.shape_pts.length > 1) {
                            const transitLine = L.polyline(leg.shape_pts, {
                                color: pathColor,
                                weight: 6,
                                opacity: 0.9
                            }).addTo(searchResultsLayer);

                            transitLine.bindPopup(`<strong>🚌 ${leg.route_name}</strong><br>${leg.from} → ${leg.to}`);
                            bounds.push(...leg.shape_pts);

                            if (leg.stops && leg.stops.length > 1) {
                                [0, leg.stops.length - 1].forEach(stopIdx => {
                                    const stop = leg.stops[stopIdx];
                                    if (stop.lat && stop.lon) {
                                        L.circleMarker([stop.lat, stop.lon], {
                                            radius: 6,
                                            fillColor: '#FFF',
                                            color: pathColor,
                                            weight: 3,
                                            opacity: 1,
                                            fillOpacity: 1
                                        }).addTo(searchResultsLayer).bindPopup(
                                            `<strong>${stop.name}</strong>`);
                                    }
                                });
                            }
                        } else if (leg.stops && leg.stops.length > 1) {
                            const coords = leg.stops.map(s => s.lat && s.lon ? [s.lat, s.lon] : null).filter(c => c !==
                                null);
                            if (coords.length > 1) {
                                const transitLine = L.polyline(coords, {
                                    color: pathColor,
                                    weight: 6,
                                    opacity: 0.9
                                }).addTo(searchResultsLayer);
                                bounds.push(...coords);

                                leg.stops.forEach((stop, stopIdx) => {
                                    if (stop.lat && stop.lon) {
                                        const isFirst = stopIdx === 0;
                                        const isLast = stopIdx === leg.stops.length - 1;
                                        L.circleMarker([stop.lat, stop.lon], {
                                            radius: isFirst || isLast ? 6 : 4,
                                            fillColor: isFirst || isLast ? '#FFF' : pathColor,
                                            color: pathColor,
                                            weight: isFirst || isLast ? 3 : 1,
                                            opacity: 1,
                                            fillOpacity: 1
                                        }).addTo(searchResultsLayer).bindPopup(
                                            `<strong>${stop.name}</strong>`);
                                    }
                                });
                            }
                        } else if (leg.from_lat && leg.from_lon && leg.to_lat && leg.to_lon) {
                            const transitLine = L.polyline(
                                [
                                    [leg.from_lat, leg.from_lon],
                                    [leg.to_lat, leg.to_lon]
                                ], {
                                    color: pathColor,
                                    weight: 6,
                                    opacity: 0.9
                                }
                            ).addTo(searchResultsLayer);
                            bounds.push([leg.from_lat, leg.from_lon], [leg.to_lat, leg.to_lon]);

                            [
                                [leg.from_lat, leg.from_lon],
                                [leg.to_lat, leg.to_lon]
                            ].forEach(coord => {
                                L.circleMarker(coord, {
                                    radius: 6,
                                    fillColor: '#FFF',
                                    color: pathColor,
                                    weight: 3,
                                    opacity: 1,
                                    fillOpacity: 1
                                }).addTo(searchResultsLayer);
                            });
                        }
                    }
                }
            } else if (route.stops && route.stops.length > 0) {
                let pathColor = route.route_color ? '#' + route.route_color : colors.transit;

                if (route.shape_pts && route.shape_pts.length > 1) {
                    L.polyline(route.shape_pts, {
                        color: pathColor,
                        weight: 6,
                        opacity: 0.9
                    }).addTo(searchResultsLayer).bindPopup(`<strong>🚌 ${route.route_name}</strong>`);
                    bounds.push(...route.shape_pts);

                    [0, route.stops.length - 1].forEach(stopIdx => {
                        const stop = route.stops[stopIdx];
                        if (stop && stop.lat && stop.lon) {
                            L.circleMarker([stop.lat, stop.lon], {
                                radius: 6,
                                fillColor: '#FFF',
                                color: pathColor,
                                weight: 3,
                                opacity: 1,
                                fillOpacity: 1
                            }).addTo(searchResultsLayer).bindPopup(`<strong>${stop.name}</strong>`);
                        }
                    });
                } else {
                    const coords = route.stops.map(stop => stop.lat && stop.lon ? [stop.lat, stop.lon] : null).filter(
                        c => c !== null);
                    if (coords.length > 1) {
                        L.polyline(coords, {
                            color: pathColor,
                            weight: 6,
                            opacity: 0.9
                        }).addTo(searchResultsLayer).bindPopup(`<strong>🚌 ${route.route_name}</strong>`);
                        bounds.push(...coords);

                        route.stops.forEach((stop, idx) => {
                            if (stop.lat && stop.lon) {
                                const isFirst = idx === 0;
                                const isLast = idx === route.stops.length - 1;
                                L.circleMarker([stop.lat, stop.lon], {
                                    radius: isFirst || isLast ? 6 : 4,
                                    fillColor: isFirst || isLast ? '#FFF' : pathColor,
                                    color: pathColor,
                                    weight: isFirst || isLast ? 3 : 1,
                                    opacity: 1,
                                    fillOpacity: 1
                                }).addTo(searchResultsLayer).bindPopup(`<strong>${stop.name}</strong>`);
                            }
                        });
                    }
                }
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
