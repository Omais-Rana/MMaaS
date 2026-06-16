<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Transit Finder</title>
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;600;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/geist@1.0.3/dist/fonts/geist-sans/style.css" rel="stylesheet">

    <!-- Leaflet CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin="" />

    <!-- Leaflet JS -->
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin=""></script>

    <!-- FontAwesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"
        crossorigin="anonymous" />

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        body {
            font-family: 'Geist', sans-serif;
        }

        .mono {
            font-family: 'JetBrains Mono', monospace;
        }

        .autocomplete-dropdown {
            position: absolute;
            z-index: 1000;
            background: #191C21;
            color: #FFFFFF;
            border: 1px solid #374151;
            border-radius: 8px;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.5);
            max-height: 300px;
            overflow-y: auto;
            width: 100%;
            bottom: 100%;
            margin-bottom: 0.5rem;
        }

        .autocomplete-item {
            padding: 0.75rem 1rem;
            cursor: pointer;
            border-bottom: 1px solid #374151;
        }
        
        .autocomplete-item:last-child {
            border-bottom: none;
        }

        .autocomplete-item:hover {
            background-color: #374151;
        }

        .route-card {
            transition: transform 0.2s, box-shadow 0.2s, border-color 0.2s, background-color 0.2s;
            cursor: pointer;
        }

        .route-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.2);
        }

        .route-card.is-selected {
            border-color: #EA580C !important;
            box-shadow: 0 0 0 1px #EA580C;
            background-color: #111827 !important;
        }

        .leaflet-popup-content-wrapper {
            border-radius: 8px;
            background: #191C21;
            color: #FFFFFF;
        }

        .leaflet-popup-tip {
            background: #191C21;
        }

        .leaflet-popup-content {
            margin: 0.75rem;
            font-family: 'Geist', sans-serif;
        }

        /* Loading overlay for the whole app */
        #loading-overlay {
            display: none;
            position: absolute;
            inset: 0;
            background: rgba(25, 28, 33, 0.8);
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
            background-color: #191C21;
            border-color: #374151;
        }

        .bus-card:hover {
            background-color: #111827;
        }

        .bus-card.bus-card-selected {
            border-color: #EA580C !important;
            background-color: #111827;
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

        /* Custom Scrollbar */
        .custom-scrollbar {
            scrollbar-gutter: stable;
        }
        .custom-scrollbar::-webkit-scrollbar {
            width: 6px;
        }
        .custom-scrollbar::-webkit-scrollbar-track {
            background: transparent;
        }
        .custom-scrollbar::-webkit-scrollbar-thumb {
            background-color: #374151;
            border-radius: 10px;
        }
        .custom-scrollbar::-webkit-scrollbar-thumb:hover {
            background-color: #4B5563;
        }
    </style>
</head>

<body class="bg-[#191C21] h-screen w-screen overflow-hidden text-[#111827] relative font-sans">

    <!-- Map Container -->
    <div id="map" class="absolute inset-0 z-0 bg-[#191C21]"></div>

    <!-- Loading Overlay -->
    <div id="loading-overlay" class="fixed inset-0 bg-[#191C21]/80 z-[2000] hidden items-center justify-center backdrop-blur-sm">
        <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-[#EA580C]"></div>
    </div>

    <!-- Unified Collapsible Left Sidebar -->
    <aside id="sidebar" class="fixed top-4 left-4 bottom-[160px] w-auto z-[1000] flex flex-col gap-4 pointer-events-none transition-transform duration-300">
        
        <!-- Header & Auth -->
        <div class="pointer-events-auto bg-[#191C21]/95 backdrop-blur-md shadow-2xl rounded-[12px] border border-[#374151] p-4 flex-shrink-0 flex items-center justify-between w-[350px]">
            <div class="flex items-center gap-3">
                <div class="w-8 h-8 bg-[#EA580C] rounded-[8px] flex items-center justify-center shrink-0 shadow-lg shadow-[#EA580C]/20">
                    <svg class="h-5 w-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                    </svg>
                </div>
                <h1 class="text-[18px] font-[600] tracking-tight text-white font-sans">Transit Finder</h1>
            </div>
            
            <div class="flex items-center gap-3">
                @auth
                    @php
                        $dashboardUrl = match (auth()->user()->role) {
                            'superadmin' => '/user',
                            'subadmin', 'employee' => '/panel',
                            default => '/portal',
                        };
                    @endphp
                    <a href="{{ $dashboardUrl }}" class="text-[#D1D5DB] hover:text-white transition-colors" title="Dashboard">
                        <i class="fa-solid fa-chart-line"></i>
                    </a>
                @else
                    <div class="flex items-center gap-2">
                        <a href="/user/login" class="text-[#D1D5DB] hover:text-white text-[13px] font-[500] transition-colors">Sign In</a>
                        <a href="/user/register" class="bg-[#EA580C] hover:bg-[#FDBA74] hover:text-[#191C21] text-white px-3 py-1.5 rounded-[6px] text-[12px] font-[600] transition-colors shadow-sm">Sign Up</a>
                    </div>
                @endauth
                
                <div class="w-[1px] h-4 bg-[#374151] hidden"></div>
                
                <!-- Toggle Sidebar Button (Legacy) -->
                <button id="toggle-left-sidebar" class="text-[#9CA3AF] hover:text-white transition-colors hidden" title="Toggle Sidebar">
                    <i class="fa-solid fa-chevron-left transition-transform" id="left-sidebar-chevron"></i>
                </button>
            </div>
        </div>

        <!-- Sidebar Content Panels -->
        <div id="sidebar-content" class="flex flex-row gap-4 flex-1 pointer-events-none transition-all duration-300 items-start min-h-0">
            
            <!-- Left Column -->
            <div class="flex flex-col w-[350px] gap-4 pointer-events-none max-h-full min-h-0">
                <!-- Territory Explorer -->
                <div id="territory-wrapper" class="pointer-events-auto bg-[#191C21]/95 backdrop-blur-md shadow-2xl rounded-[12px] border border-[#374151] p-5 flex flex-col shrink min-h-0 transition-all duration-300">
                    <div class="flex items-center justify-between cursor-pointer mb-2 shrink-0" id="toggle-territory">
                        <div>
                            <h2 class="text-[15px] font-[600] text-white">Service Explorer</h2>
                            <p class="text-[11px] text-[#9CA3AF] mt-0.5" id="territory-desc">Select a location to view options.</p>
                        </div>
                        <button type="button" class="text-[#9CA3AF] hover:text-white transition-colors">
                            <i class="fa-solid fa-chevron-up transition-transform" id="territory-chevron"></i>
                        </button>
                    </div>
                    
                    <div id="territory-content" class="transition-all duration-300 overflow-hidden flex flex-col shrink min-h-0">
                        <div class="space-y-4 pt-2 overflow-y-auto custom-scrollbar shrink min-h-0">
                            <div>
                                <label class="block text-[11px] font-[600] text-[#6B7280] uppercase tracking-wider mb-2 font-mono">Locations</label>
                                <div id="territory-tree" class="space-y-1 flex flex-col">
                                    <div class="text-center py-4 text-[#6B7280] text-[12px]">Loading locations&hellip;</div>
                                </div>
                            </div>
                            <div>
                                <div class="flex items-center justify-between mb-2">
                                    <label class="block text-[11px] font-[600] text-[#6B7280] uppercase tracking-wider font-mono">Services</label>
                                    <button type="button" id="clear-services" class="text-[11px] text-[#9CA3AF] hover:text-[#EA580C] hover:underline transition-colors">Clear</button>
                                </div>
                                <div id="service-list" class="space-y-1 flex flex-col">
                                    <div class="text-center py-4 text-[#6B7280] text-[12px]">Select a city to load.</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Column -->
            <div class="flex flex-col w-[350px] gap-4 pointer-events-none max-h-full min-h-0">
                <!-- Middle Pane (Service Lines) -->
                <div id="middle-pane" class="hidden pointer-events-auto bg-[#191C21]/95 backdrop-blur-md shadow-2xl rounded-[12px] border border-[#374151] p-5 flex flex-col shrink min-h-0 transition-all duration-300">
                    <div class="flex items-center justify-between cursor-pointer mb-2 shrink-0" id="toggle-middle-pane">
                        <div>
                            <h3 class="text-[14px] font-[600] text-white uppercase tracking-wider font-mono">Service Lines</h3>
                            <p class="text-[11px] text-[#6B7280] mt-0.5">Active routes for selected services.</p>
                        </div>
                        <button type="button" class="text-[#9CA3AF] hover:text-white transition-colors">
                            <i class="fa-solid fa-chevron-up transition-transform" id="middle-pane-chevron"></i>
                        </button>
                    </div>
                    <div id="middle-pane-content" class="transition-all duration-300 overflow-hidden flex flex-col shrink min-h-0">
                        <div id="route-cards-list" class="space-y-3 overflow-y-auto pr-1 pb-2 pt-2 relative custom-scrollbar shrink min-h-0"></div>
                    </div>
                </div>

                <!-- Search Results Pane -->
                <div id="search-results-pane" class="hidden pointer-events-auto bg-[#191C21]/95 backdrop-blur-md shadow-2xl rounded-[12px] border border-[#374151] p-5 flex flex-col shrink min-h-0 transition-all duration-300 relative">
                    <div class="flex justify-between items-center cursor-pointer mb-2 shrink-0" id="toggle-search-results">
                        <div>
                            <h3 class="text-[14px] font-[600] text-white uppercase tracking-wider font-mono">Search Results</h3>
                            <p class="text-[11px] text-[#6B7280] mt-1">Select a route for details.</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <button type="button" class="text-[#9CA3AF] hover:text-white transition-colors">
                                <i class="fa-solid fa-chevron-up transition-transform" id="search-results-chevron"></i>
                            </button>
                            <button type="button" id="close-search-results" class="p-1.5 hover:bg-[#374151] rounded-[6px] text-[#9CA3AF] hover:text-white transition-colors ml-2">
                                <i class="fa-solid fa-xmark"></i>
                            </button>
                        </div>
                    </div>
                    
                    <div id="search-results-content" class="transition-all duration-300 overflow-hidden flex flex-col shrink min-h-0">
                        <div id="no-results" class="hidden text-center py-8 bg-[#111827] rounded-[8px] shadow-sm border border-[#374151] p-6 mt-2 shrink-0">
                            <i class="fa-solid fa-route text-[32px] text-[#EA580C] mb-3"></i>
                            <h3 class="text-[14px] font-[600] mb-1 text-white">No Routes Found</h3>
                            <p class="text-[11px] text-[#9CA3AF]">Try adjusting your search.</p>
                        </div>

                        <div id="routes-list" class="space-y-3 overflow-y-auto pr-1 pb-2 pt-2 relative custom-scrollbar shrink min-h-0 hidden"></div>
                    </div>
                </div>
            </div>
        </div>
    </aside>

    <!-- Floating Bottom Bar (Search Form) -->
    <div class="fixed bottom-6 left-1/2 -translate-x-1/2 z-[1000] w-[95%] max-w-[900px] bg-[#191C21]/95 backdrop-blur-md shadow-2xl rounded-[16px] border border-[#374151] p-4 pointer-events-auto">
        <form id="route-search-form" class="flex flex-col md:flex-row items-end gap-3 w-full mb-0">
            
            <!-- Origin -->
            <div class="relative flex-1 w-full">
                <label class="block text-[10px] font-[600] text-[#9CA3AF] uppercase tracking-wider mb-1.5 font-mono">From</label>
                <input type="text" id="origin" required class="w-full px-3 py-2.5 bg-[#111827] border border-[#374151] rounded-[8px] focus:ring-0 focus:border-[#EA580C] outline-none transition-colors text-white placeholder-[#6B7280] text-[13px] shadow-inner" placeholder="Starting Point" autocomplete="off">
                <div id="origin-autocomplete" class="autocomplete-dropdown hidden"></div>
            </div>

            <!-- Swap -->
            <button type="button" id="swap-locations" class="w-9 h-9 shrink-0 bg-[#1F2937] border border-[#374151] hover:bg-[#374151] rounded-[8px] flex items-center justify-center transition-colors mb-[1px]">
                <i class="fa-solid fa-arrow-right-arrow-left text-[#9CA3AF] text-[12px]"></i>
            </button>

            <!-- Destination -->
            <div class="relative flex-1 w-full">
                <label class="block text-[10px] font-[600] text-[#9CA3AF] uppercase tracking-wider mb-1.5 font-mono">To</label>
                <input type="text" id="destination" required class="w-full px-3 py-2.5 bg-[#111827] border border-[#374151] rounded-[8px] focus:ring-0 focus:border-[#EA580C] outline-none transition-colors text-white placeholder-[#6B7280] text-[13px] shadow-inner" placeholder="Destination" autocomplete="off">
                <div id="destination-autocomplete" class="autocomplete-dropdown hidden"></div>
            </div>

            <!-- Date/Time combined -->
            <div class="flex gap-2 shrink-0">
                <div class="w-[130px]">
                    <label class="block text-[10px] font-[600] text-[#9CA3AF] uppercase tracking-wider mb-1.5 font-mono">Date</label>
                    <input type="date" id="date" required class="w-full px-3 py-2.5 bg-[#111827] border border-[#374151] rounded-[8px] focus:ring-0 focus:border-[#EA580C] outline-none text-white text-[13px] shadow-inner cursor-pointer [color-scheme:dark]" value="{{ date('Y-m-d') }}" onclick="try{this.showPicker()}catch(e){}" onfocus="try{this.showPicker()}catch(e){}">
                </div>
                <div class="w-[120px]">
                    <label class="block text-[10px] font-[600] text-[#9CA3AF] uppercase tracking-wider mb-1.5 font-mono">Time</label>
                    <input type="time" id="time" required class="w-full px-3 py-2.5 bg-[#111827] border border-[#374151] rounded-[8px] focus:ring-0 focus:border-[#EA580C] outline-none text-white text-[13px] shadow-inner cursor-pointer [color-scheme:dark]" value="{{ date('H:i') }}" onclick="try{this.showPicker()}catch(e){}" onfocus="try{this.showPicker()}catch(e){}">
                </div>
            </div>

            <!-- Actions -->
            <div class="flex gap-2 shrink-0 mb-[1px]">
                <button type="submit" class="px-5 h-[42px] bg-[#EA580C] hover:bg-[#FDBA74] hover:text-[#191C21] text-white rounded-[8px] font-[600] text-[13px] transition-colors flex items-center justify-center gap-2 shadow-lg shadow-[#EA580C]/20">
                    <i class="fa-solid fa-magnifying-glass"></i> Search
                </button>
                <button type="button" id="reset-search" class="w-[42px] h-[42px] bg-[#191C21] border border-[#374151] rounded-[8px] flex items-center justify-center text-[#9CA3AF] hover:text-[#EA580C] hover:bg-[#374151] transition-colors" title="Reset">
                    <i class="fa-solid fa-rotate-left text-[13px]"></i>
                </button>
            </div>
        </form>
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
                        const cityTag = s.city ? '<div class="text-[10px] text-[#9CA3AF] mt-0.5 font-mono">' +
                            s.city + '</div>' : '';
                        return '<div class="autocomplete-item p-3 hover:bg-[#111827] cursor-pointer border-b border-[#374151] last:border-0 transition-colors" data-name="' +
                            s.stop_name + '" data-lat="' + (s.stop_lat || '') + '" data-lon="' + (s
                                .stop_lon || '') + '">' +
                            '<div class="font-[500] text-[13px] text-white">' + s.stop_name +
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

        let selectedCoords = {
            origin: null,
            destination: null
        };

        window.setAsRouteEndpoint = function(type, name, lat, lon) {
            const input = document.getElementById(type);
            if (input) {
                input.value = name;
                selectedCoords[type] = {
                    lat,
                    lon
                };
                if (map) {
                    map.closePopup();
                    if (userPinMarker) {
                        map.removeLayer(userPinMarker);
                        userPinMarker = null;
                    }
                    if (endpointMarkers[type]) {
                        map.removeLayer(endpointMarkers[type]);
                    }

                    let iconColor = type === 'origin' ? '#3b82f6' : (type === 'destination' ? '#e60000' : '#3b82f6');
                    let iconText = type === 'origin' ? 'A' : (type === 'destination' ? 'B' : 'V');

                    const endpointIcon = L.divIcon({
                        html: `<div class="w-8 h-8 rounded-full border-2 border-white shadow-md flex items-center justify-center text-white font-[800] text-[14px]" style="background-color: ${iconColor};">${iconText}</div>`,
                        className: 'bg-transparent',
                        iconSize: [32, 32],
                        iconAnchor: [16, 16]
                    });

                    endpointMarkers[type] = L.marker([lat, lon], {
                        icon: endpointIcon
                    }).addTo(map);
                }
            }
        };

        document.getElementById('reset-search')?.addEventListener('click', function() {
            // Reset the form
            document.getElementById('route-search-form').reset();

            // Clear internal state
            selectedCoords.origin = null;
            selectedCoords.destination = null;

            // Clear map layers and markers
            if (map) {
                Object.keys(endpointMarkers).forEach(key => {
                    if (endpointMarkers[key]) {
                        map.removeLayer(endpointMarkers[key]);
                        delete endpointMarkers[key];
                    }
                });

                if (userPinMarker) {
                    map.removeLayer(userPinMarker);
                    userPinMarker = null;
                }

                if (searchResultsLayer) searchResultsLayer.clearLayers();
                if (defaultRouteStopsLayer) defaultRouteStopsLayer.clearLayers();
            }
        });

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

            if (map) {
                if (selectedCoords.origin) {
                    setAsRouteEndpoint('origin', origin.value, selectedCoords.origin.lat, selectedCoords.origin
                    .lon);
                } else if (endpointMarkers['origin']) {
                    map.removeLayer(endpointMarkers['origin']);
                    delete endpointMarkers['origin'];
                }

                if (selectedCoords.destination) {
                    setAsRouteEndpoint('destination', destination.value, selectedCoords.destination.lat,
                        selectedCoords.destination.lon);
                } else if (endpointMarkers['destination']) {
                    map.removeLayer(endpointMarkers['destination']);
                    delete endpointMarkers['destination'];
                }
            }
        });

        // --- MAP LOGIC ---
        let map = null;
        let allRoutesLayer = L.layerGroup();
        let gbfsStationsLayer = L.layerGroup();
        let searchResultsLayer = L.layerGroup();
        let defaultRouteStopsLayer = L.layerGroup();
        let territoryStopsLayer = L.layerGroup();
        let parkingLayer = L.layerGroup();
        let currentRouteData = null;
        let allRoutesShapes = {}; // Cache: route_short_name -> full shape_pts array
        let routeStopsCache = {}; // Cache: routeName -> stops array
        let selectedDefaultPolyline = null; // Currently pinned polyline in default view
        let allRoutesMetaData = {}; // Cache: routeName -> {color, longName}
        let allRoutePolylines = {}; // Cache: routeName -> [polyline, ...]
        let selectedBusCard = null; // Currently selected bus info card element
        let selectedBusRouteName = null; // Route name of selected bus card
        let walkingRouteCache = new Map(); // Cache: coord pair -> routed lat/lon path
        let territoriesMeta = {};
        let serviceHierarchy = {};
        let expandedCountries = new Set();
        let activeCityKey = null;
        let selectedTenantServices = new Set(); // Stores "tenantId:service"
        let tenantRoutesCache = {};
        let allGbfsStations = []; // Stores all fetched stations
        let gbfsMarkers = {}; // Cache: station_id -> L.marker
        let expandedTenants = new Set();
        let userPinMarker = null;
        let endpointMarkers = {};

        function getModeLabel(mode) {
            if (mode === 'bus') return 'Buses';
            if (mode === 'cycle') return 'Bikes';
            if (mode === 'car') return 'Cars';
            return mode;
        }

        function initMap() {
            if (!map) {
                map = L.map('map', {
                    zoomControl: false // Move zoom control
                }).setView([47.5, 6.8], 10);

                L.control.zoom({
                    position: 'topright'
                }).addTo(map);

                // High-contrast light map with clear administrative boundaries and labels.
                L.tileLayer('https://{s}.basemaps.cartocdn.com/rastertiles/voyager_nolabels/{z}/{x}/{y}{r}.png', {
                    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OSM</a> contributors &copy; <a href="https://carto.com/attributions">CARTO</a>',
                    subdomains: 'abcd',
                    maxZoom: 20
                }).addTo(map);

                map.createPane('labelsPane');
                map.getPane('labelsPane').style.zIndex = 450;
                map.getPane('labelsPane').style.pointerEvents = 'none';

                L.tileLayer('https://{s}.basemaps.cartocdn.com/rastertiles/voyager_only_labels/{z}/{x}/{y}{r}.png', {
                    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OSM</a> contributors &copy; <a href="https://carto.com/attributions">CARTO</a>',
                    subdomains: 'abcd',
                    maxZoom: 20,
                    pane: 'labelsPane',
                }).addTo(map);

                allRoutesLayer.addTo(map);
                gbfsStationsLayer.addTo(map);
                searchResultsLayer.addTo(map);
                defaultRouteStopsLayer.addTo(map);
                territoryStopsLayer.addTo(map);

                const legend = L.control({ position: 'bottomright' });
                legend.onAdd = function() {
                    const div = L.DomUtil.create('div', 'bg-[#191C21] p-3 rounded-[8px] shadow-md border border-[#374151] text-[13px] text-white font-mono uppercase tracking-wider');
                    div.innerHTML = `
                        <div class="font-[600] mb-3 text-[#EA580C]">Legend</div>
                        <div class="flex items-center gap-3 mb-2"><div class="w-6 h-6 flex items-center justify-center rounded-full bg-[#374151] text-white shadow-sm border border-[#6B7280]"><i class="fa-solid fa-bus text-[10px]"></i></div> Bus Station</div>
                        <div class="flex items-center gap-3 mb-2"><div class="w-6 h-6 flex items-center justify-center rounded-full bg-[#374151] text-[#f59e0b] shadow-sm border border-[#6B7280]"><i class="fa-solid fa-bicycle text-[10px]"></i></div> Cycle Station</div>
                        <div class="flex items-center gap-3 mb-2"><div class="w-6 h-6 flex items-center justify-center rounded-full bg-[#374151] text-[#a855f7] shadow-sm border border-[#6B7280]"><i class="fa-solid fa-car text-[10px]"></i></div> Car Station</div>
                        <label class="flex items-center gap-3 cursor-pointer select-none mt-4 pt-3 border-t border-[#374151] group">
                            <div class="relative flex items-center justify-center">
                                <input type="checkbox" id="toggle-parking" class="peer sr-only">
                                <div class="w-[18px] h-[18px] bg-[#111827] border border-[#4B5563] rounded-[4px] peer-checked:bg-[#EA580C] peer-checked:border-[#EA580C] transition-colors flex items-center justify-center shadow-sm">
                                    <svg class="w-3 h-3 text-white opacity-0 peer-checked:opacity-100 transition-opacity" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path></svg>
                                </div>
                            </div>
                            <div class="flex items-center gap-2 text-[#D1D5DB] group-hover:text-white transition-colors text-[12px]">
                                <div class="w-6 h-6 flex items-center justify-center rounded-full bg-[#374151] text-[#10b981] shadow-sm border border-[#6B7280]"><i class="fa-solid fa-square-parking text-[10px]"></i></div>
                                Show Parking
                            </div>
                        </label>
                    `;

                    L.DomEvent.disableClickPropagation(div);

                    setTimeout(() => {
                        const toggle = document.getElementById('toggle-parking');
                        if (toggle) {
                            toggle.addEventListener('change', function(e) {
                                if (e.target.checked) {
                                    parkingLayer.addTo(map);
                                } else {
                                    parkingLayer.remove();
                                }
                            });
                        }
                    }, 0);

                    return div;
                };
                legend.addTo(map);

                map.on('click', function() {
                    if (selectedDefaultPolyline) {
                        selectedDefaultPolyline.setStyle({
                            weight: 2,
                            opacity: 0.6
                        });
                        selectedDefaultPolyline = null;
                        defaultRouteStopsLayer.clearLayers();
                    }
                });
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
                const escapedName = stop.name.replace(/'/g, "\\'");
                const popupHtml = `
                    <div class="p-1">
                        <div class="font-bold text-sm mb-2 text-gray-800">${stop.name}</div>
                        <div class="flex gap-2 mt-2 pt-2 border-t border-[#e5e7eb]">
                            <button onclick="setAsRouteEndpoint('origin', '${escapedName}', ${stop.lat}, ${stop.lon})" class="flex-1 bg-[#191C21] border border-[#EA580C] hover:bg-[#EA580C] hover:text-white text-[#EA580C] font-[600] py-1.5 px-2 rounded-[6px] transition-colors text-center text-[11px] uppercase tracking-wider font-mono shadow-sm">Start</button>
                            <button onclick="setAsRouteEndpoint('destination', '${escapedName}', ${stop.lat}, ${stop.lon})" class="flex-1 bg-[#191C21] border border-[#EA580C] hover:bg-[#EA580C] hover:text-white text-[#EA580C] font-[600] py-1.5 px-2 rounded-[6px] transition-colors text-center text-[11px] uppercase tracking-wider font-mono shadow-sm">End</button>
                        </div>
                    </div>
                `;

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
                    .bindPopup(popupHtml, {
                        className: 'rounded-xl'
                    })
                    .addTo(defaultRouteStopsLayer);
            });
        }

        function renderBusInfoPanel() {
            const middlePane = document.getElementById('middle-pane');
            const container = document.getElementById('route-cards-list');
            if (!container || !middlePane) return;

            if (selectedTenantServices.size === 0) {
                middlePane.classList.add('hidden');
                return;
            } else {
                middlePane.classList.remove('hidden');
                const middlePaneContent = document.getElementById('middle-pane-content');
                if (middlePaneContent && middlePaneContent.classList.contains('h-0')) {
                    document.getElementById('toggle-middle-pane').click();
                }
            }

            container.innerHTML = '';

            const routeNames = Object.keys(allRoutesMetaData);

            const selectedGbfsTenantModes = new Set();
            for (const svcKey of selectedTenantServices) {
                const [tId, mode] = svcKey.split(':');
                if (mode !== 'bus') selectedGbfsTenantModes.add(svcKey);
            }
            const activeStations = allGbfsStations.filter(st => {
                const isSelected = selectedGbfsTenantModes.has(`${st.tenant_id}:${st.source_type}`);
                const available = st.status.num_bikes_available || 0;
                return isSelected && available > 0;
            });

            if (routeNames.length === 0 && activeStations.length === 0) {
                container.innerHTML =
                    '<div class="text-center py-10 bg-[#191C21] rounded-[8px] border border-dashed border-[#374151] mt-4"><div class="text-[13px] text-[#9CA3AF] font-[500]">No services found.</div><div class="text-[11px] text-[#6B7280] mt-1">Try selecting different services.</div></div>';

                const toggleHeader = document.getElementById('toggle-middle-pane');
                if (toggleHeader) {
                    toggleHeader.querySelector('h3').innerText = 'Services';
                    toggleHeader.querySelector('p').innerText = 'Explore routes and available vehicles.';
                }
                return;
            }

            const toggleHeader = document.getElementById('toggle-middle-pane');
            if (toggleHeader) {
                if (routeNames.length > 0 && activeStations.length === 0) {
                    toggleHeader.querySelector('h3').innerText = 'Service Lines';
                    toggleHeader.querySelector('p').innerText = 'Explore active routes for your selected services.';
                } else if (routeNames.length === 0 && activeStations.length > 0) {
                    toggleHeader.querySelector('h3').innerText = 'Available Vehicles';
                    toggleHeader.querySelector('p').innerText = 'Explore stations and available vehicles.';
                } else {
                    toggleHeader.querySelector('h3').innerText = 'Services';
                    toggleHeader.querySelector('p').innerText = 'Explore routes and available vehicles.';
                }
            }

            const groupedRoutes = {};
            routeNames.forEach(routeName => {
                const meta = allRoutesMetaData[routeName];
                const operatorName = meta.operatorName || 'Service';

                if (!groupedRoutes[operatorName]) {
                    groupedRoutes[operatorName] = [];
                }

                groupedRoutes[operatorName].push(routeName);
            });

            Object.keys(groupedRoutes).sort((a, b) => a.localeCompare(b)).forEach(operatorName => {
                const section = document.createElement('div');
                section.className = 'bg-[#111827] rounded-[8px] border border-[#374151] shadow-sm overflow-hidden mb-4';

                const routes = groupedRoutes[operatorName].sort((a, b) => {
                    const numA = parseInt(a, 10);
                    const numB = parseInt(b, 10);
                    if (!isNaN(numA) && !isNaN(numB)) return numA - numB;
                    if (!isNaN(numA)) return -1;
                    if (!isNaN(numB)) return 1;
                    return a.localeCompare(b);
                });

                section.innerHTML = `
                    <div class="sticky top-0 z-10 px-4 py-3 bg-[#191C21] border-b border-[#374151] flex justify-between items-center backdrop-blur-md bg-opacity-90">
                        <div class="text-[11px] font-[600] text-white uppercase tracking-wider font-mono">${operatorName.replace(/</g, '&lt;')}</div>
                        <div class="text-[10px] font-[600] bg-[#111827] border border-[#374151] px-2 py-0.5 rounded-full text-[#9CA3AF] shadow-inner font-mono">${routes.length} line${routes.length > 1 ? 's' : ''}</div>
                    </div>
                    <div class="p-2 space-y-2"></div>
                `;

                const routesContainer = section.querySelector('.space-y-2');
                routes.forEach(routeName => {
                    const meta = allRoutesMetaData[routeName];
                    const card = document.createElement('div');
                    card.className =
                        'bus-card bg-[#191C21] rounded-[8px] border border-[#374151] hover:border-[#6B7280] transition-colors shadow-sm';
                    card.dataset.route = routeName;

                    card.innerHTML = `
                        <div class="bus-card-header flex items-center gap-3 p-3 cursor-pointer">
                            <span class="inline-flex items-center justify-center min-w-[2.75rem] h-8 px-2 rounded-[6px] text-white text-[12px] font-[600] shrink-0 shadow-md font-mono" style="background:${meta.color}">${routeName}</span>
                            <div class="min-w-0 flex-1">
                                <div class="text-[13px] font-[500] text-white leading-tight line-clamp-2">${meta.longName}</div>
                            </div>
                            <svg class="bus-card-chevron w-4 h-4 text-[#6B7280] shrink-0 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                            </svg>
                        </div>
                        <div class="bus-card-stops hidden border-t border-[#374151] max-h-60 overflow-y-auto bg-[#111827] rounded-b-[8px]"></div>
                    `;

                    routesContainer.appendChild(card);
                });

                container.appendChild(section);
            });

            // Render Stations
            if (activeStations.length > 0) {
                const groupedStations = {};
                activeStations.forEach(st => {
                    const operatorName = st.operator_name || 'Operator';
                    if (!groupedStations[operatorName]) groupedStations[operatorName] = [];
                    groupedStations[operatorName].push(st);
                });

                Object.keys(groupedStations).sort((a, b) => a.localeCompare(b)).forEach(operatorName => {
                    const section = document.createElement('div');
                    section.className = 'bg-[#111827] rounded-[8px] border border-[#374151] shadow-sm overflow-hidden mb-4';

                    const stations = groupedStations[operatorName];
                    section.innerHTML = `
                        <div class="sticky top-0 z-10 px-4 py-3 bg-[#191C21] border-b border-[#374151] flex justify-between items-center backdrop-blur-md bg-opacity-90">
                            <div class="text-[11px] font-[600] text-white uppercase tracking-wider font-mono">${operatorName.replace(/</g, '&lt;')}</div>
                            <div class="text-[10px] font-[600] bg-[#111827] border border-[#374151] px-2 py-0.5 rounded-full text-[#9CA3AF] shadow-inner font-mono">${stations.length} station${stations.length > 1 ? 's' : ''}</div>
                        </div>
                        <div class="p-2 space-y-2"></div>
                    `;

                    const stationsContainer = section.querySelector('.space-y-2');
                    stations.forEach(st => {
                        const isCar = st.source_type === 'car';
                        const available = st.status.num_bikes_available || 0;
                        const hasAvail = available > 0;
                        const badgeColor = hasAvail ? '#28a745' : '#25282b';
                        const iconHtml = isCar ? '<i class="fa-solid fa-car text-[12px]"></i>' :
                            '<i class="fa-solid fa-bicycle text-[12px]"></i>';

                        let detailsHtml = `Capacity: ${st.capacity || 0}`;
                        if (!isCar) {
                            detailsHtml +=
                                ` &middot; Bikes: ${available} / Docks: ${st.status.num_docks_available || 0}`;
                        } else {
                            detailsHtml += ` &middot; Cars: ${available}`;
                            if (st.status.vehicle_types_available && st.status.vehicle_types_available
                                .length > 0) {
                                const names = st.status.vehicle_types_available.map(v => v
                                    .vehicle_type_name).filter(Boolean).join(', ');
                                if (names) detailsHtml += `<br>Vehicles: ${names}`;
                            }
                        }

                        const card = document.createElement('div');
                        card.className =
                            'bus-card bg-[#191C21] rounded-[8px] border border-[#374151] hover:border-[#6B7280] transition-colors shadow-sm cursor-pointer';
                        card.innerHTML = `
                            <div class="flex items-center gap-3 p-3">
                                <span class="inline-flex items-center justify-center w-8 h-8 rounded-full text-white text-[12px] shadow-md shrink-0" style="background:${badgeColor}">${iconHtml}</span>
                                <div class="min-w-0 flex-1">
                                    <div class="text-[13px] font-[600] text-white leading-tight line-clamp-1">${st.name}</div>
                                    <div class="text-[11px] text-[#9CA3AF] mt-1 font-mono">${detailsHtml}</div>
                                </div>
                            </div>
                        `;

                        card.addEventListener('click', () => {
                            const marker = gbfsMarkers[st.station_id];
                            if (marker) {
                                // Invalidate map size in case the sidebar just opened, ensuring correct center calculation
                                map.invalidateSize();
                                // Set view directly to the marker
                                map.setView(marker.getLatLng(), 16, {
                                    animate: true
                                });
                                // Open popup after a slight delay to allow map to pan and prevent autoPan from fighting us
                                setTimeout(() => {
                                    marker.openPopup();
                                }, 250);
                            }
                        });

                        stationsContainer.appendChild(card);
                    });
                    container.appendChild(section);
                });
            }

            container.querySelectorAll('.bus-card .bus-card-header').forEach(header => {
                header.addEventListener('click', async () => {
                    const card = header.closest('.bus-card');
                    if (!card || !card.dataset.route) return;
                    await selectBusCard(card.dataset.route, card);
                });
            });
        }

        function territoryKey(tenantId) {
            return `${tenantId}`;
        }

        function cityKey(country, city) {
            return `${country}::${city}`;
        }

        function buildServiceHierarchy(territories) {
            const hierarchy = {};

            territories.forEach(territory => {
                const country = (territory.country || 'France').toString();
                const city = (territory.city || 'Unspecified').toString();

                if (!hierarchy[country]) {
                    hierarchy[country] = {};
                }

                if (!hierarchy[country][city]) {
                    hierarchy[country][city] = [];
                }

                hierarchy[country][city].push({
                    tenant_id: Number(territory.tenant_id),
                    operator_name: territory.operator_name || 'Service',
                    stops_count: territory.stops_count || 0,
                    territory_name: territory.name || city,
                    services: territory.services || ['bus'],
                });
            });

            Object.keys(hierarchy).forEach(country => {
                Object.keys(hierarchy[country]).forEach(city => {
                    hierarchy[country][city].sort((a, b) => {
                        return (a.operator_name || '').localeCompare(b.operator_name || '');
                    });
                });
            });

            return hierarchy;
        }

        function getActiveCityServices() {
            if (!activeCityKey) {
                return [];
            }

            const [country, city] = activeCityKey.split('::');
            return serviceHierarchy[country] && serviceHierarchy[country][city] ? serviceHierarchy[country][city] : [];
        }

        function renderTerritoryTree() {
            const container = document.getElementById('territory-tree');
            if (!container) return;

            const countries = Object.keys(serviceHierarchy).sort((a, b) => a.localeCompare(b));
            if (!countries.length) {
                container.innerHTML = '<div class="text-center py-6 text-gray-400 text-sm">No locations available.</div>';
                return;
            }

            const cardsHtml = countries.map(country => {
                const cities = Object.keys(serviceHierarchy[country]).sort((a, b) => a.localeCompare(b));

                const isExpanded = expandedCountries.has(country);

                const cityButtonsHtml = cities.map(city => {
                    const cKey = cityKey(country, city);
                    const isActive = activeCityKey === cKey;
                    return `
                        <button type="button" class="city-select-btn w-full flex items-center justify-between gap-3 px-3 py-2 rounded-[6px] text-left transition-colors ${isActive ? 'bg-[#111827] text-[#EA580C] border border-[#EA580C]' : 'bg-transparent hover:bg-[#374151] text-[#9CA3AF] border border-transparent'}" data-country="${country.replace(/"/g, '&quot;')}" data-city="${city.replace(/"/g, '&quot;')}">
                            <span class="text-[13px] font-[600] ${isActive ? 'text-[#EA580C]' : 'text-[#9CA3AF]'}">${city}</span>
                        </button>
                    `;
                }).join('');

                return `
                    <div class="bg-[#191C21] border border-[#374151] rounded-[8px] overflow-hidden mb-3">
                        <button type="button" class="country-toggle w-full flex items-center justify-between gap-2 px-4 py-2.5 bg-[#191C21] hover:bg-[#111827] transition-colors" data-country="${country.replace(/"/g, '&quot;')}">
                            <span class="text-[14px] font-[600] text-white uppercase tracking-wider font-mono">${country}</span>
                            <svg class="country-chevron w-4 h-4 text-[#6B7280] transition-transform ${isExpanded ? 'rotate-180' : ''}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                            </svg>
                        </button>
                        <div class="country-cities ${isExpanded ? '' : 'hidden'} p-2 space-y-1 bg-[#111827] border-t border-[#374151] flex flex-col">
                            ${cityButtonsHtml}
                        </div>
                    </div>
                `;
            }).join('');

            container.innerHTML = cardsHtml;

            container.querySelectorAll('.country-toggle').forEach(button => {
                button.addEventListener('click', () => {
                    const country = button.dataset.country;
                    if (!country) return;

                    if (expandedCountries.has(country)) {
                        expandedCountries.delete(country);
                    } else {
                        expandedCountries.add(country);
                    }

                    renderTerritoryTree();
                });
            });

            container.querySelectorAll('.city-select-btn').forEach(button => {
                button.addEventListener('click', () => {
                    const country = button.dataset.country;
                    const city = button.dataset.city;
                    if (!country || !city) return;
                    activeCityKey = cityKey(country, city);
                    expandedCountries.add(country);
                    renderTerritoryTree();
                    renderServiceList();
                    
                    const services = getActiveCityServices();
                    if (services.length > 0 && map) {
                        let minLat = 90, maxLat = -90, minLon = 180, maxLon = -180;
                        let hasBounds = false;
                        services.forEach(svc => {
                            const meta = territoriesMeta[territoryKey(svc.tenant_id)];
                            if (meta && meta.bbox) {
                                hasBounds = true;
                                minLat = Math.min(minLat, meta.bbox.min_lat);
                                maxLat = Math.max(maxLat, meta.bbox.max_lat);
                                minLon = Math.min(minLon, meta.bbox.min_lon);
                                maxLon = Math.max(maxLon, meta.bbox.max_lon);
                            }
                        });

                        if (hasBounds) {
                            map.fitBounds([
                                [minLat, minLon],
                                [maxLat, maxLon]
                            ], { padding: [20, 20], maxZoom: 14 });
                        }

                        territoryStopsLayer.clearLayers();
                        
                        services.forEach(svc => {
                            fetch('/api/territories/' + svc.tenant_id + '/stops')
                                .then(res => res.json())
                                .then(data => {
                                    if (data.stops) {
                                        data.stops.forEach(stop => {
                                            const escapedName = stop.name.replace(/'/g, "\\'");
                                            const popupHtml = `
                                                <div class="p-2 w-[220px]">
                                                    <div class="font-[600] text-[14px] mb-1 text-white leading-tight">${stop.name}</div>
                                                    <div class="text-[11px] text-[#9CA3AF] mb-3 capitalize font-mono">Bus Station</div>
                                                    <div class="flex gap-2 pt-3 border-t border-[#374151]">
                                                        <button onclick="setAsRouteEndpoint('origin', '${escapedName}', ${stop.lat}, ${stop.lon})" class="flex-1 bg-[#191C21] border border-[#EA580C] hover:bg-[#EA580C] hover:text-white text-[#EA580C] font-[600] py-1.5 px-2 rounded-[6px] transition-colors text-center text-[11px] uppercase tracking-wider font-mono shadow-sm">Start</button>
                                                        <button onclick="setAsRouteEndpoint('destination', '${escapedName}', ${stop.lat}, ${stop.lon})" class="flex-1 bg-[#191C21] border border-[#EA580C] hover:bg-[#EA580C] hover:text-white text-[#EA580C] font-[600] py-1.5 px-2 rounded-[6px] transition-colors text-center text-[11px] uppercase tracking-wider font-mono shadow-sm">End</button>
                                                    </div>
                                                </div>
                                            `;
                                            const icon = L.divIcon({
                                                html: '<div style="background:#3b82f6;color:#fff;width:24px;height:24px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:10px;border:2px solid #fff;box-shadow:0 2px 4px rgba(0,0,0,0.3);"><i class="fa-solid fa-bus"></i></div>',
                                                className: '',
                                                iconSize: [24, 24],
                                                iconAnchor: [12, 12]
                                            });
                                            L.marker([stop.lat, stop.lon], { icon: icon
                                            }).bindTooltip(stop.name, {
                                                sticky: true,
                                                className: 'font-sans text-sm'
                                            }).bindPopup(popupHtml, {
                                                className: 'rounded-xl'
                                            }).addTo(territoryStopsLayer);
                                        });
                                    }
                                    if (data.gbfs_stations) {
                                        data.gbfs_stations.forEach(station => {
                                            const escapedName = station.name.replace(/'/g, "\\'");
                                            const popupHtml = `
                                                <div class="p-2 w-[220px]">
                                                    <div class="font-[600] text-[14px] mb-1 text-white leading-tight">${station.name}</div>
                                                    <div class="text-[11px] text-[#9CA3AF] mb-3 capitalize font-mono">${station.gbfs_type} Station</div>
                                                    <div class="flex gap-2 pt-3 border-t border-[#374151]">
                                                        <button onclick="setAsRouteEndpoint('origin', '${escapedName}', ${station.lat}, ${station.lon})" class="flex-1 bg-[#191C21] border border-[#EA580C] hover:bg-[#EA580C] hover:text-white text-[#EA580C] font-[600] py-1.5 px-2 rounded-[6px] transition-colors text-center text-[11px] uppercase tracking-wider font-mono shadow-sm">Start</button>
                                                        <button onclick="setAsRouteEndpoint('destination', '${escapedName}', ${station.lat}, ${station.lon})" class="flex-1 bg-[#191C21] border border-[#EA580C] hover:bg-[#EA580C] hover:text-white text-[#EA580C] font-[600] py-1.5 px-2 rounded-[6px] transition-colors text-center text-[11px] uppercase tracking-wider font-mono shadow-sm">End</button>
                                                    </div>
                                                </div>
                                            `;
                                            const markerColor = station.gbfs_type === 'car' ? '#a855f7' : '#f59e0b';
                                            const iconClass = station.gbfs_type === 'car' ? 'fa-car' : 'fa-bicycle';
                                            const icon = L.divIcon({
                                                html: `<div style="background:${markerColor};color:#fff;width:24px;height:24px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:10px;border:2px solid #fff;box-shadow:0 2px 4px rgba(0,0,0,0.3);"><i class="fa-solid ${iconClass}"></i></div>`,
                                                className: '',
                                                iconSize: [24, 24],
                                                iconAnchor: [12, 12]
                                            });
                                            L.marker([station.lat, station.lon], { icon: icon
                                            }).bindTooltip(station.name, {
                                                sticky: true,
                                                className: 'font-sans text-sm'
                                            }).bindPopup(popupHtml, {
                                                className: 'rounded-xl'
                                            }).addTo(territoryStopsLayer);
                                        });
                                    }
                                    // Parking spots are now loaded separately via /api/parking-spots
                                })
                                .catch(err => console.error('Failed to load stops for territory:', err));
                        });
                    }
                });
            });
        }

        function renderServiceList() {
            const container = document.getElementById('service-list');
            if (!container) return;

            const services = getActiveCityServices();
            if (!services.length) {
                container.innerHTML =
                    '<div class="text-center py-6 text-gray-400 text-sm">Select a city to load services.</div>';
                return;
            }

            container.innerHTML = services.map(service => {
                const modes = service.services || ['bus'];
                const hasMultiple = modes.length > 1;
                const isExpanded = expandedTenants.has(Number(service.tenant_id));

                if (!hasMultiple) {
                    const mode = modes[0];
                    const svcKey = `${service.tenant_id}:${mode}`;
                    const isSelected = selectedTenantServices.has(svcKey);

                    return `
                        <button type="button" class="service-toggle-single w-full text-left rounded-[8px] p-3 transition-colors border mb-2 ${isSelected ? 'border-[#EA580C] bg-[#111827]' : 'border-[#374151] bg-[#191C21] hover:bg-[#111827]'}" data-tenant-id="${service.tenant_id}" data-mode="${mode}">
                            <div class="flex items-center justify-between gap-3">
                                <div class="flex-1 min-w-0">
                                    <div class="text-[13px] font-[600] truncate text-white leading-tight">${service.operator_name}</div>
                                    <div class="text-[11px] font-[500] mt-1 ${isSelected ? 'text-[#EA580C]' : 'text-[#9CA3AF]'}">${service.stops_count} active stops/stations</div>
                                </div>
                                <div class="shrink-0 w-4 h-4 rounded-[4px] border flex items-center justify-center transition-colors shadow-sm ${isSelected ? 'border-[#EA580C] bg-[#EA580C]' : 'border-[#6B7280] bg-[#374151]'}">
                                    ${isSelected ? '<svg class="w-3 h-3 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path></svg>' : ''}
                                </div>
                            </div>
                        </button>
                    `;
                }

                const someSelected = modes.some(m => selectedTenantServices.has(`${service.tenant_id}:${m}`));

                const childRows = modes.map(mode => {
                    const svcKey = `${service.tenant_id}:${mode}`;
                    const isSelected = selectedTenantServices.has(svcKey);
                    const label = getModeLabel(mode);

                    return `
                        <button type="button" class="service-toggle-child w-full flex items-center justify-between gap-3 px-3 py-2 rounded-[6px] text-left transition-colors border ${isSelected ? 'bg-[#191C21] text-[#EA580C] border-[#EA580C]' : 'bg-transparent hover:bg-[#374151] text-[#9CA3AF] border-transparent'}" data-tenant-id="${service.tenant_id}" data-mode="${mode}">
                            <span class="text-[13px] font-[600] ${isSelected ? 'text-[#EA580C]' : 'text-[#9CA3AF]'}">${label}</span>
                            <div class="shrink-0 w-4 h-4 rounded-[4px] border flex items-center justify-center transition-colors shadow-sm ${isSelected ? 'border-[#EA580C] bg-[#EA580C]' : 'border-[#6B7280] bg-[#374151]'}">
                                ${isSelected ? '<svg class="w-3 h-3 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path></svg>' : ''}
                            </div>
                        </button>
                    `;
                }).join('');

                return `
                    <div class="border rounded-[8px] overflow-hidden mb-2 shadow-sm transition-colors ${someSelected ? 'border-[#EA580C] bg-[#111827]' : 'border-[#374151] bg-[#191C21]'}">
                        <button type="button" class="tenant-accordion-toggle w-full flex items-center justify-between gap-2 px-4 py-3 transition-colors ${someSelected ? 'bg-[#111827]' : 'bg-[#191C21] hover:bg-[#111827]'}" data-tenant-id="${service.tenant_id}">
                            <div class="flex-1 min-w-0 text-left">
                                <div class="text-[13px] font-[600] text-white">${service.operator_name}</div>
                                <div class="text-[11px] font-[500] mt-1 ${someSelected ? 'text-[#EA580C]' : 'text-[#9CA3AF]'}">Multiple services</div>
                            </div>
                            <svg class="w-4 h-4 text-[#6B7280] transition-transform ${isExpanded ? 'rotate-180' : ''}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                            </svg>
                        </button>
                        <div class="${isExpanded ? '' : 'hidden'} p-2 space-y-1 bg-[#111827] border-t ${someSelected ? 'border-[#EA580C]' : 'border-[#374151]'} flex flex-col">
                            ${childRows}
                        </div>
                    </div>
                `;
            }).join('');

            container.querySelectorAll('.tenant-accordion-toggle').forEach(button => {
                button.addEventListener('click', () => {
                    const tenantId = Number(button.dataset.tenantId);
                    if (expandedTenants.has(tenantId)) {
                        expandedTenants.delete(tenantId);
                    } else {
                        expandedTenants.add(tenantId);
                    }
                    renderServiceList();
                });
            });

            const handleToggle = async (button) => {
                const tenantId = Number(button.dataset.tenantId);
                const mode = button.dataset.mode;
                if (!tenantId || !mode) return;

                const svcKey = `${tenantId}:${mode}`;
                if (selectedTenantServices.has(svcKey)) {
                    selectedTenantServices.delete(svcKey);
                } else {
                    selectedTenantServices.add(svcKey);
                }

                renderServiceList();
                await loadShapesForSelectedServices();
            };

            container.querySelectorAll('.service-toggle-single').forEach(button => {
                button.addEventListener('click', () => handleToggle(button));
            });

            container.querySelectorAll('.service-toggle-child').forEach(button => {
                button.addEventListener('click', () => handleToggle(button));
            });
        }

        function renderTerritoryPanel() {
            const territories = Object.values(territoriesMeta);
            serviceHierarchy = buildServiceHierarchy(territories);

            if (activeCityKey) {
                const [country, city] = activeCityKey.split('::');
                if (!serviceHierarchy[country] || !serviceHierarchy[country][city]) {
                    activeCityKey = null;
                }
            }

            if (!activeCityKey) {
                const countries = Object.keys(serviceHierarchy).sort((a, b) => a.localeCompare(b));
                if (countries.length) {
                    const firstCountry = countries[0];
                    const firstCity = Object.keys(serviceHierarchy[firstCountry]).sort((a, b) => a.localeCompare(b))[0];
                    if (firstCity) {
                        activeCityKey = cityKey(firstCountry, firstCity);
                        expandedCountries.add(firstCountry);
                    }
                }
            }

            renderTerritoryTree();
            renderServiceList();
            renderBusInfoPanel();
        }

        async function selectBusCard(routeName, cardEl) {
            const isSameCard = selectedBusCard === cardEl;

            // Deselect previously selected card
            if (selectedBusCard) {
                selectedBusCard.classList.remove('bus-card-selected');
                const prevStops = selectedBusCard.querySelector('.bus-card-stops');
                if (prevStops) prevStops.classList.add('hidden');

                selectedBusCard = null;
                selectedBusRouteName = null;
                selectedDefaultPolyline = null;
                defaultRouteStopsLayer.clearLayers();
            }

            // If same card was clicked again, just deselect (toggle off)
            // Restore all routes to default visibility
            if (isSameCard) {
                Object.keys(allRoutePolylines).forEach(rn => {
                    allRoutePolylines[rn].forEach(p => p.setStyle({
                        weight: 2,
                        opacity: 0.6
                    }));
                });
                return;
            }

            // Select the new card
            selectedBusCard = cardEl;
            selectedBusRouteName = routeName;
            cardEl.classList.add('bus-card-selected');

            // Hide all other routes, highlight selected
            Object.keys(allRoutePolylines).forEach(rn => {
                if (rn === routeName) {
                    allRoutePolylines[rn].forEach(p => {
                        p.setStyle({
                            weight: 5,
                            opacity: 1
                        });
                        p.bringToFront();
                    });
                } else {
                    allRoutePolylines[rn].forEach(p => {
                        p.setStyle({
                            opacity: 0,
                            weight: 0
                        });
                    });
                }
            });
            if (allRoutePolylines[routeName]) {
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
                                <div class="w-2 h-2 rounded-full border-2 ${i === 0 || i === stops.length - 1 ? 'border-[#EA580C] bg-[#EA580C]' : 'border-[#6B7280] bg-[#374151]'}"></div>
                                ${i < stops.length - 1 ? '<div class="w-px h-3 bg-[#374151] mt-0.5"></div>' : ''}
                            </div>
                            <span class="text-[12px] text-[#9CA3AF] leading-tight pb-1">${s.name}</span>
                        </div>
                    `).join('');
                } else {
                    stopsEl.innerHTML =
                        '<div class="px-3 py-2 text-[12px] text-[#6B7280]">No stop information available.</div>';
                }
            }
        }

        function renderRoutesOnMap(routes, shouldFitBounds = true) {
            if (!map.hasLayer(allRoutesLayer)) {
                map.addLayer(allRoutesLayer);
            }

            allRoutesLayer.clearLayers();
            allRoutesShapes = {};
            allRoutesMetaData = {};
            allRoutePolylines = {};

            routes.forEach(r => {
                if (!r.shape_pts || r.shape_pts.length <= 1) {
                    return;
                }

                const routeName = r.route_short_name || r.route_name;
                if (!routeName) {
                    return;
                }

                if (!allRoutesShapes[routeName]) {
                    allRoutesShapes[routeName] = [];
                }
                allRoutesShapes[routeName].push(r.shape_pts);

                const color = r.route_color ? '#' + r.route_color : '#9CA3AF';
                if (!allRoutesMetaData[routeName]) {
                    allRoutesMetaData[routeName] = {
                        color: color,
                        longName: r.route_long_name || r.route_name || routeName,
                        operatorName: r.operator_name || r.agency_name || 'Service',
                    };
                    allRoutePolylines[routeName] = [];
                }

                const polyline = L.polyline(r.shape_pts, {
                    color: color,
                    weight: 2,
                    opacity: 0.6,
                    routeName: routeName,
                    routeColor: color,
                });

                const routeTitle = r.route_name || r.route_short_name || 'Route';
                const agencyName = r.operator_name || r.agency_name;
                const agencyLine = agencyName ?
                    `<br><span style="font-size:11px;color:#666;">${agencyName}</span>` : '';

                polyline.bindTooltip(
                    `<b>${routeTitle}</b>${agencyLine}<br><span style="font-size:10px;color:#16a34a">🌿 CNG · Natural Gas</span>`, {
                        sticky: true,
                        className: 'font-sans'
                    });

                polyline.on('click', async function(e) {
                    L.DomEvent.stopPropagation(e);
                    
                    if (selectedDefaultPolyline === this) {
                        this.setStyle({
                            weight: 2,
                            opacity: 0.6
                        });
                        selectedDefaultPolyline = null;
                        defaultRouteStopsLayer.clearLayers();
                        return;
                    }

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
                });

                allRoutesLayer.addLayer(polyline);
                allRoutePolylines[routeName].push(polyline);
            });

            if (shouldFitBounds && allRoutesLayer.getLayers().length > 0) {
                const layerGroupBounds = L.featureGroup(allRoutesLayer.getLayers()).getBounds();
                if (layerGroupBounds.isValid()) {
                    map.fitBounds(layerGroupBounds, {
                        padding: [50, 50]
                    });
                }
            }

            renderBusInfoPanel();
        }

        async function fetchRoutesForTenant(tenantId) {
            if (tenantRoutesCache[tenantId]) {
                return tenantRoutesCache[tenantId];
            }

            const res = await fetch(`/api/territories/routes/all-shapes?tenant_id=${encodeURIComponent(tenantId)}`);
            if (!res.ok) {
                throw new Error(`Territory routes request failed with status ${res.status}`);
            }

            const text = await res.text();
            const data = text ? JSON.parse(text) : {
                routes: []
            };

            const tenantKey = territoryKey(tenantId);
            const territory = territoriesMeta[tenantKey] || {};
            const operatorName = territory.operator_name || 'Service';

            const routes = Array.isArray(data.routes) ? data.routes.map(route => ({
                ...route,
                operator_name: operatorName,
                tenant_id: tenantId,
            })) : [];

            tenantRoutesCache[tenantId] = routes;
            return routes;
        }

        async function loadShapesForSelectedServices() {
            try {
                allRoutesLayer.clearLayers();
                gbfsStationsLayer.clearLayers();
                allRoutesShapes = {};
                allRoutesMetaData = {};
                allRoutePolylines = {};
                gbfsMarkers = {};
                selectedBusCard = null;
                selectedBusRouteName = null;
                selectedDefaultPolyline = null;
                defaultRouteStopsLayer.clearLayers();

                if (selectedTenantServices.size === 0) {
                    renderBusInfoPanel();
                    return;
                }

                // 1. Handle GTFS (bus)
                const busTenantIds = [];
                for (const svcKey of selectedTenantServices) {
                    const [tId, mode] = svcKey.split(':');
                    if (mode === 'bus') {
                        busTenantIds.push(Number(tId));
                    }
                }

                if (busTenantIds.length > 0) {
                    const routesByTenant = await Promise.all(busTenantIds.map(tId => fetchRoutesForTenant(tId)));
                    const mergedRoutes = routesByTenant.flat();
                    renderRoutesOnMap(mergedRoutes, true);
                } else {
                    renderBusInfoPanel();
                }

                // 2. Handle GBFS (cycle, car)
                const selectedGbfsTenantModes = new Set();
                for (const svcKey of selectedTenantServices) {
                    const [tId, mode] = svcKey.split(':');
                    if (mode !== 'bus') {
                        selectedGbfsTenantModes.add(svcKey);
                    }
                }

                if (selectedGbfsTenantModes.size > 0 && allGbfsStations.length > 0) {
                    const filteredStations = allGbfsStations.filter(st => {
                        return selectedGbfsTenantModes.has(`${st.tenant_id}:${st.source_type}`);
                    });

                    filteredStations.forEach(st => {
                        const isCar = st.source_type === 'car';
                        const isBike = st.source_type === 'cycle';
                        const available = st.status.num_bikes_available || 0;
                        const color = available > 0 ? '#28a745' : '#25282b'; // Green if >0, Charcoal if 0
                        const carIcon = `<i class="fa-solid fa-car text-white text-[12px]"></i>`;
                        const bikeIcon = `<i class="fa-solid fa-bicycle text-white text-[12px]"></i>`;
                        const iconHtml = `<div class="w-7 h-7 rounded-full border border-white flex items-center justify-center" style="background-color: ${color};">
                            ${isCar ? carIcon : bikeIcon}
                        </div>`;

                        const icon = L.divIcon({
                            html: iconHtml,
                            className: 'bg-transparent',
                            iconSize: [28, 28],
                            iconAnchor: [14, 14]
                        });

                        let popupHtml = `
                            <div class="p-2 w-[220px]">
                                <div class="font-[600] text-[14px] mb-1 text-white leading-tight">${st.name}</div>
                                <div class="text-[11px] text-[#9CA3AF] mb-2">${st.operator_name}</div>
                                <div class="text-[12px] font-[500] text-white bg-[#111827] border border-[#374151] p-3 rounded-[6px] mb-3">`;
                        if (isBike) {
                            popupHtml += `Capacity: ${st.capacity || 0}<br>`;
                            popupHtml += `Bikes: ${available} / Docks: ${st.status.num_docks_available || 0}`;
                        } else if (isCar) {
                            popupHtml += `Available Cars: ${available}`;
                            if (st.status.vehicle_types_available && st.status.vehicle_types_available.length >
                                0) {
                                const names = st.status.vehicle_types_available
                                    .map(v => v.vehicle_type_name)
                                    .filter(Boolean)
                                    .join(', ');
                                if (names) {
                                    popupHtml += `<br>Vehicles: ${names}`;
                                }
                            }
                        }
                        popupHtml += `</div>
                            <div class="flex gap-2 mt-3 pt-3 border-t border-[#e5e7eb]">
                                <button onclick="setAsRouteEndpoint('origin', '${st.name.replace(/'/g, "\\'")}', ${st.lat}, ${st.lon})" class="flex-1 bg-[#191C21] border border-[#EA580C] hover:bg-[#EA580C] hover:text-white text-[#EA580C] font-[600] py-1.5 px-2 rounded-[6px] transition-colors text-center text-[11px] uppercase tracking-wider font-mono shadow-sm">Start</button>
                                <button onclick="setAsRouteEndpoint('destination', '${st.name.replace(/'/g, "\\'")}', ${st.lat}, ${st.lon})" class="flex-1 bg-[#191C21] border border-[#EA580C] hover:bg-[#EA580C] hover:text-white text-[#EA580C] font-[600] py-1.5 px-2 rounded-[6px] transition-colors text-center text-[11px] uppercase tracking-wider font-mono shadow-sm">End</button>
                            </div>
                        </div>`;

                        const marker = L.marker([st.lat, st.lon], {
                                icon
                            })
                            .bindPopup(popupHtml, {
                                className: 'rounded-xl'
                            })
                            .addTo(gbfsStationsLayer);

                        gbfsMarkers[st.station_id] = marker;
                    });
                }

                // Call renderBusInfoPanel at the end to render BOTH routes and stations
                // Note: renderBusInfoPanel is already called above if there are no buses,
                // but we should call it unconditionally or conditionally at the very end.
                // Wait, previously if (busTenantIds.length > 0) called renderRoutesOnMap -> calls renderBusInfoPanel.
                // If busTenantIds.length == 0, it called renderBusInfoPanel directly.
                // So renderBusInfoPanel is always called! We don't need to add another call here.

            } catch (e) {
                console.error('Failed to load selected service routes:', e);
            }
        }

        async function loadAllShapesFallback() {
            try {
                const res = await fetch('/api/routes/all-shapes');
                if (!res.ok) {
                    throw new Error(`All routes request failed with status ${res.status}`);
                }

                const text = await res.text();
                const data = text ? JSON.parse(text) : {
                    routes: []
                };

                if (!data || !Array.isArray(data.routes)) {
                    return;
                }
                renderRoutesOnMap(data.routes, true);
            } catch (error) {
                console.error('Fallback all-routes load failed:', error);
            }
        }

        async function loadTerritories() {
            try {
                const res = await fetch('/api/territories');
                if (!res.ok) {
                    throw new Error(`Territories request failed with status ${res.status}`);
                }

                const text = await res.text();
                const data = text ? JSON.parse(text) : {
                    territories: []
                };

                try {
                    const gbfsRes = await fetch('/api/shared-vehicles/stations');
                    if (gbfsRes.ok) {
                        const gbfsData = await gbfsRes.json();
                        allGbfsStations = gbfsData.data || [];
                    }
                } catch (e) {
                    console.error('Failed to load GBFS stations', e);
                }

                territoriesMeta = {};
                tenantRoutesCache = {};
                selectedTenantServices = new Set();
                selectedBusCard = null;
                selectedBusRouteName = null;
                selectedDefaultPolyline = null;
                allRoutesLayer.clearLayers();
                gbfsStationsLayer.clearLayers();
                allRoutesShapes = {};
                allRoutesMetaData = {};
                allRoutePolylines = {};

                if (data && data.territories && Array.isArray(data.territories)) {
                    data.territories.forEach(territory => {
                        const key = territoryKey(territory.tenant_id);
                        territoriesMeta[key] = territory;
                    });

                    renderTerritoryPanel();

                    if (data.territories.length === 0) {
                        await loadAllShapesFallback();
                    }
                }
            } catch (e) {
                console.error('Failed to load territories:', e);
                await loadAllShapesFallback();
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
        const CO2_CAR_G_PER_KM = 120; // g CO2/km (ADEME France baseline)
        const CO2_BUS_G_PER_KM = 32; // g CO2/km – Evolity CNG/GNV (Iveco Crealis)
        const CO2_SAVED_G_PER_KM = CO2_CAR_G_PER_KM - CO2_BUS_G_PER_KM; // 88g/km

        function co2Badge(distKm) {
            if (!distKm || distKm <= 0) return '';
            const savedG = distKm * CO2_SAVED_G_PER_KM;
            const savedLabel = savedG >= 1000 ?
                (savedG / 1000).toFixed(2).replace(/\.?0+$/, '') + ' kg' :
                Math.round(savedG) + ' g';
            const busCo2G = distKm * CO2_BUS_G_PER_KM;
            const busCo2Label = busCo2G >= 1000 ?
                (busCo2G / 1000).toFixed(2).replace(/\.?0+$/, '') + ' kg' :
                Math.round(busCo2G) + ' g';
            return `<div class="inline-flex items-center gap-1.5 text-[#34D399] text-[11px] font-[600] font-mono tracking-tight bg-[#064E3B] px-2 py-0.5 rounded-[4px] border border-[#059669]" title="Trip emits ${busCo2Label} CO2 and saves ${savedLabel} vs car">
                <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M11 20A7 7 0 0 1 9.8 6.1C15.5 5 17 4.48 19 2c1 2 2 4.18 2 8 0 5.5-4.78 10-10 10Z" />
                    <path d="M2 21c0-3 1.85-5.36 5.08-6C9.5 14.52 12 13 13 12" />
                </svg>
                <span>Saves ${savedLabel} CO₂</span>
            </div>`;
        }

        function co2PopupLine(distKm) {
            if (!distKm || distKm <= 0) return '';
            const savedG = Math.round(distKm * CO2_SAVED_G_PER_KM);
            const savedLabel = savedG >= 1000 ?
                (savedG / 1000).toFixed(2).replace(/\.?0+$/, '') + ' kg' :
                savedG + ' g';
            return `<div style="margin-top:8px;padding:6px 8px;background:#064E3B;border-radius:6px;border:1px solid #059669;font-size:11px;color:#34D399;font-family:'JetBrains Mono', monospace;font-weight:600;letter-spacing:-0.5px">🌿 Evolity CNG &middot; ${(distKm).toFixed(1)} km &middot; Saves ${savedLabel} CO₂</div>`;
        }

        // Initialize Map & Load shapes
        let allParkingSpots = [];
        
        const parkingIcon = L.divIcon({
            html: '<div style="background:#10b981;color:#fff;width:20px;height:20px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:10px;border:2px solid #fff;box-shadow:0 2px 4px rgba(0,0,0,0.5);"><i class="fa-solid fa-square-parking"></i></div>',
            className: '',
            iconSize: [20, 20],
            iconAnchor: [10, 10]
        });

        async function loadParkingSpots() {
            try {
                const res = await fetch('/api/parking-spots');
                const data = await res.json();
                if (data.parking_spots) {
                    allParkingSpots = data.parking_spots;
                    renderParkingSpots();
                }
            } catch (err) {
                console.error('Failed to load parking spots:', err);
            }
        }

        function renderParkingSpots() {
            parkingLayer.clearLayers();
            if (!allParkingSpots.length) return;

            // Optional: If we have an active bounds from the current city, we could filter here.
            // For now, render them all but with a shared icon reference to save memory/CPU.
            allParkingSpots.forEach(spot => {
                const escapedName = spot.name.replace(/'/g, "\\'");
                const popupHtml = `
                    <div class="p-2 w-[220px]">
                        <div class="font-[600] text-[14px] mb-1 text-white leading-tight">${spot.name}</div>
                        <div class="text-[11px] text-[#9CA3AF] mb-3 capitalize font-mono">Parking Spot</div>
                        <div class="flex gap-2 pt-3 border-t border-[#374151]">
                            <button onclick="setAsRouteEndpoint('origin', '${escapedName}', ${spot.lat}, ${spot.lon})" class="flex-1 bg-[#191C21] border border-[#EA580C] hover:bg-[#EA580C] hover:text-white text-[#EA580C] font-[600] py-1.5 px-2 rounded-[6px] transition-colors text-center text-[11px] uppercase tracking-wider font-mono shadow-sm">Start</button>
                            <button onclick="setAsRouteEndpoint('destination', '${escapedName}', ${spot.lat}, ${spot.lon})" class="flex-1 bg-[#191C21] border border-[#EA580C] hover:bg-[#EA580C] hover:text-white text-[#EA580C] font-[600] py-1.5 px-2 rounded-[6px] transition-colors text-center text-[11px] uppercase tracking-wider font-mono shadow-sm">End</button>
                        </div>
                    </div>
                `;
                L.marker([spot.lat, spot.lon], { icon: parkingIcon })
                    .bindTooltip(spot.name, { sticky: true, className: 'font-sans text-[12px] bg-[#191C21] text-white border-[#374151]' })
                    .bindPopup(popupHtml, { className: 'rounded-[8px] bg-[#191C21] text-white border-[#374151]' })
                    .addTo(parkingLayer);
            });
        }


        document.addEventListener('DOMContentLoaded', () => {
            initMap();
            loadTerritories();
            loadParkingSpots();

            const clearServicesBtn = document.getElementById('clear-services');
            if (clearServicesBtn) {
                clearServicesBtn.addEventListener('click', async () => {
                    selectedTenantServices.clear();
                    renderServiceList();
                    await loadShapesForSelectedServices();
                });
            }
        });

        // Accordion Toggles
        function setupAccordion(toggleId, contentId, chevronId) {
            const toggle = document.getElementById(toggleId);
            if (!toggle) return;
            toggle.addEventListener('click', () => {
                const content = document.getElementById(contentId);
                const chevron = document.getElementById(chevronId);
                if (content.classList.contains('h-0')) {
                    // Expand
                    content.classList.remove('h-0', 'opacity-0');
                    chevron.style.transform = 'rotate(0deg)';
                } else {
                    // Collapse
                    content.classList.add('h-0', 'opacity-0');
                    chevron.style.transform = 'rotate(180deg)';
                }
                setTimeout(() => map.invalidateSize(), 300);
            });
        }

        setupAccordion('toggle-territory', 'territory-content', 'territory-chevron');
        setupAccordion('toggle-middle-pane', 'middle-pane-content', 'middle-pane-chevron');
        setupAccordion('toggle-search-results', 'search-results-content', 'search-results-chevron');

        // Toggle Search Results pane
        document.getElementById('close-search-results').addEventListener('click', () => {
            const searchPane = document.getElementById('search-results-pane');
            searchPane.classList.add('hidden');
            searchPane.classList.remove('flex');

            // Expand middle-pane accordion
            const middlePaneContent = document.getElementById('middle-pane-content');
            if (middlePaneContent && middlePaneContent.classList.contains('h-0')) {
                document.getElementById('toggle-middle-pane').click();
            }

            // clear map layers related to search
            searchResultsLayer.clearLayers();
            defaultRouteStopsLayer.clearLayers();

            // redraw previously selected services if any
            renderBusInfoPanel();
        });

        // Search form reset
        document.getElementById('reset-search').addEventListener('click', () => {
            document.getElementById('origin').value = '';
            document.getElementById('destination').value = '';

            // Hide the search results pane entirely
            const searchPane = document.getElementById('search-results-pane');
            searchPane.classList.add('hidden');
            searchPane.classList.remove('flex');

            document.getElementById('routes-list').classList.add('hidden');
            document.getElementById('no-results').classList.add('hidden');

            searchResultsLayer.clearLayers();
            defaultRouteStopsLayer.clearLayers();
            allRoutesLayer.clearLayers();
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
            selectedBusCard = null;
            selectedBusRouteName = null;

            // Clear all selected services and modes
            selectedTenantServices.clear();
            expandedTenants.clear();
            selectedGbfsTenantModes.clear();

            renderTerritoryTree();
            renderBusInfoPanel();
        });

        // --- RESULTS RENDERING & HIGHLIGHT LOGIC ---
        function renderTransferRoute(route, routeIndex) {
            let totalMidStops = 0;
            route.legs.forEach(leg => {
                if (leg.type === 'transit' && leg.stops) {
                    totalMidStops += leg.stops.filter(s => s.is_intermediate).length;
                }
            });

            const transferDistKm = route.legs.reduce((total, leg) => {
                if (leg.type !== 'transit') {
                    return total;
                }

                if (leg.shape_pts && leg.shape_pts.length > 1) {
                    return total + haversineKm(leg.shape_pts);
                }

                if (leg.stops && leg.stops.length > 1) {
                    const legPoints = leg.stops.map(stop => [parseFloat(stop.lat), parseFloat(stop.lon)]);
                    return total + haversineKm(legPoints);
                }

                return total;
            }, 0);

            const badgesHtml = route.legs.map(leg => {
                if (leg.type === 'walk') {
                    return `<span class="text-[#7e7e7e]"><i class="fa-solid fa-person-walking"></i></span>`;
                } else {
                    const style = leg.route_color ?
                        `background-color: #${leg.route_color}; color: ${leg.route_text_color ? '#' + leg.route_text_color : '#FFFFFF'};` :
                        'background-color: #6B7280; color: #FFFFFF;';
                    return `<span class="inline-block px-2 py-0.5 text-xs font-bold rounded" style="${style}">${leg.route_name || leg.route_short_name || leg.route_long_name || 'Route'}</span>`;
                }
            }).join('<span class="text-gray-300 mx-1">›</span>');

            const legsHtml = route.legs.map((leg, idx) => {
                if (leg.type === 'walk') {
                    return `
                        <div class="relative pl-6 py-2">
                            <div class="flex items-start gap-3">
                                ${idx < route.legs.length - 1 ? '<div class="absolute left-[11px] top-4 bottom-[-10px] w-0.5 border-l-2 border-dotted border-[#374151]"></div>' : ''}
                                <div class="absolute left-[-29px] top-1.5 w-[14px] h-[14px] rounded-full border-2 border-[#9CA3AF] bg-[#191C21] z-10 shadow-sm"></div>
                                <div class="font-mono text-[12px] font-[600] text-white w-12 pt-0.5 shrink-0">${leg.start_time || ''}</div>
                                <div>
                                    <div class="font-[600] text-white leading-snug text-[13px]">${leg.from || 'Origin'}</div>
                                    <div class="mt-1 flex items-center gap-2">
                                        <span class="text-[12px] font-[500] text-[#9CA3AF]"><i class="fa-solid fa-person-walking"></i> Walk ${leg.duration} min</span>
                                    </div>
                                </div>
                            </div>
                            ${idx === route.legs.length - 1 ? `
                            <div class="relative flex items-start gap-3 mt-4">
                                <div class="absolute left-[-29px] top-1.5 w-[14px] h-[14px] rounded-full border-2 border-[#9CA3AF] bg-[#191C21] z-10 shadow-sm"></div>
                                <div class="font-mono text-[12px] font-[600] text-white w-12 pt-0.5 shrink-0">${leg.end_time || ''}</div>
                                <div class="font-[600] text-white leading-snug text-[13px]">${leg.to || 'Destination'}</div>
                            </div>
                            ` : ''}
                        </div>
                    `;
                } else if (leg.type === 'bike' || leg.type === 'car') {
                    const iconClass = leg.type === 'bike' ? 'fa-bicycle' : 'fa-car';
                    const routeBadgeStyle = leg.route_color ?
                        `background-color: #${leg.route_color}; color: ${leg.route_text_color ? '#' + leg.route_text_color : '#FFFFFF'};` :
                        'background-color: #3b82f6; color: #FFFFFF;';
                    
                    return `
                        <div class="relative pl-6 py-3">
                            <div class="absolute left-[11px] top-4 bottom-4 w-0.5 bg-[#374151]"></div>
                            
                            <!-- Ride Start -->
                            <div class="relative flex items-start gap-3 mb-4">
                                <div class="absolute left-[-29px] top-1.5 w-[14px] h-[14px] rounded-full border-2 border-[#9CA3AF] bg-[#191C21] z-10 shadow-sm"></div>
                                <div class="font-mono text-[12px] font-[600] text-white w-12 pt-0.5 shrink-0">${leg.start_time}</div>
                                <div>
                                    <div class="font-[600] text-white leading-snug text-[13px]">${leg.from}</div>
                                    <div class="mt-1 flex items-center gap-2">
                                        <span class="inline-block px-1.5 py-0.5 text-[10px] font-bold rounded uppercase tracking-wide font-mono" style="${routeBadgeStyle}">${leg.route_name || leg.type}</span>
                                        <span class="text-[12px] font-[500] text-[#9CA3AF]"><i class="fa-solid ${iconClass}"></i> Ride ${leg.duration} min</span>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Ride End -->
                            <div class="relative flex items-start gap-3">
                                <div class="absolute left-[-29px] top-1.5 w-[14px] h-[14px] rounded-full border-2 border-[#9CA3AF] bg-[#191C21] z-10 shadow-sm"></div>
                                <div class="font-mono text-[12px] font-[600] text-white w-12 pt-0.5 shrink-0">${leg.end_time}</div>
                                <div class="font-[600] text-white leading-snug text-[13px]">${leg.to}</div>
                            </div>
                        </div>
                    `;
                } else if (leg.type === 'transit') {
                    const routeBadgeStyle = leg.route_color ?
                        `background-color: #${leg.route_color}; color: ${leg.route_text_color ? '#' + leg.route_text_color : '#FFFFFF'};` :
                        'background-color: #6B7280; color: #FFFFFF;';

                    const midStops = leg.stops ? leg.stops.filter(s => s.is_intermediate) : [];
                    const midStopsHtml = midStops.length > 0 ? `
                        <details class="mt-1.5 overflow-hidden">
                            <summary class="text-[12px] text-[#9CA3AF] cursor-pointer select-none hover:text-[#EA580C] flex items-center gap-1 font-[500]">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                                ${midStops.length} intermediate stops
                            </summary>
                            <div class="mt-2 ml-1 space-y-1.5 border-l-2 border-[#374151] pl-3 mb-2">
                                ${midStops.map(s => `<div class="flex justify-between text-[12px] text-[#9CA3AF]"><span>${s.name}</span><span class="font-mono text-[#6B7280]">${s.arrival}</span></div>`).join('')}
                            </div>
                        </details>
                    ` : '';

                    return `
                        <div class="flex items-start gap-3 py-2">
                            <div class="w-6 flex flex-col items-center mt-1">
                                <div class="w-3 h-3 rounded-full" style="${routeBadgeStyle}"></div>
                                ${idx < route.legs.length - 1 ? `<div class="w-0.5 h-full bg-[#374151] my-1 min-h-[20px]"></div>` : ''}
                            </div>
                            <div class="flex-1 pb-3">
                                <div class="flex items-center gap-2">
                                    <span class="inline-block px-2 py-0.5 text-[11px] font-[600] rounded-[4px] font-mono shadow-sm" style="${routeBadgeStyle}">
                                        ${leg.route_name || leg.route_short_name || leg.route_long_name || 'Route'}
                                    </span>
                                    <span class="text-[13px] font-[600] text-white leading-tight">${leg.from}</span>
                                </div>
                                <div class="text-[12px] text-[#9CA3AF] mt-1">
                                    <div class="flex items-center gap-2 font-mono">
                                        <span class="font-[600] text-white">${leg.departure_time}</span>
                                        &rarr;
                                        <span class="font-[600] text-white">${leg.arrival_time}</span> 
                                        <span class="font-sans text-[#9CA3AF]">at ${leg.to}</span>
                                    </div>
                                </div>
                                ${midStopsHtml}
                            </div>
                        </div>
                    `;
                }
                return '';
            }).join('');

            return `
                <div class="route-card bg-[#191C21] rounded-[8px] border border-[#374151] p-5 mb-3 transition-colors hover:border-[#6B7280]" onclick="showRouteOnMap(${routeIndex})">
                    <div class="flex justify-between items-center mb-3 cursor-pointer">
                        <div class="flex items-baseline gap-2">
                            <span class="text-[22px] font-[600] text-white tracking-tight font-sans">${route.leave_at || route.departure_time}</span>
                            <span class="text-[#6B7280]">→</span>
                            <span class="text-[22px] font-[600] text-white tracking-tight font-sans">${route.arrival_time}</span>
                        </div>
                        <div class="px-3 py-1.5 bg-[#111827] border border-[#374151] text-[#D1D5DB] font-[600] text-[12px] rounded-full shadow-inner font-mono">
                            ${route.duration}
                        </div>
                    </div>
                    
                    <details class="group mt-2 cursor-pointer" onclick="event.stopPropagation()">
                        <summary class="flex items-center justify-between text-[13px] list-none outline-none">
                            <div class="flex flex-col gap-2">
                                <div class="flex items-center gap-2 flex-wrap">
                                    ${badgesHtml}
                                </div>
                                <div class="text-[#9CA3AF] font-[500] text-[12px]">${totalMidStops} stops in total</div>
                                <div class="mt-1">
                                    ${co2Badge(transferDistKm)}
                                </div>
                            </div>
                            <div class="flex items-center gap-1 text-[#6B7280] hover:text-[#EA580C] transition-colors bg-[#111827] px-2 py-1 rounded-[6px] border border-[#374151]">
                                <span class="font-[600] text-[11px] hidden group-open:inline uppercase tracking-wider font-mono">Hide</span>
                                <span class="font-[600] text-[11px] group-open:hidden uppercase tracking-wider font-mono">Details</span>
                                <svg class="w-4 h-4 transition-transform group-open:rotate-180" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                            </div>
                        </summary>
                        <div class="mt-4 pt-4 border-t border-gray-100">
                            ${legsHtml}
                        </div>
                    </details>
                </div>
            `;
        }

        document.getElementById('route-search-form').addEventListener('submit', async function(e) {
            e.preventDefault();
            const origin = document.getElementById('origin');
            const destination = document.getElementById('destination');
            const searchPane = document.getElementById('search-results-pane');
            const routesList = document.getElementById('routes-list');
            const overlay = document.getElementById('loading-overlay');
            const noResults = document.getElementById('no-results');

            // Collapse middle-pane accordion if it's open (to make room for search results in the right column)
            const middlePaneContent = document.getElementById('middle-pane-content');
            if (!middlePaneContent.classList.contains('h-0')) {
                document.getElementById('toggle-middle-pane').click();
            }

            // Open the search results pane accordion
            searchPane.classList.remove('hidden');
            const searchContent = document.getElementById('search-results-content');
            if (searchContent.classList.contains('h-0')) {
                document.getElementById('toggle-search-results').click();
            }

            overlay.classList.add('active');
            noResults.classList.add('hidden');
            routesList.innerHTML = '';
            routesList.classList.add('hidden');

            if (map.hasLayer(allRoutesLayer)) {
                map.removeLayer(allRoutesLayer);
            }
            searchResultsLayer.clearLayers();
            defaultRouteStopsLayer.clearLayers();
            
            Object.keys(endpointMarkers).forEach(key => {
                if (endpointMarkers[key]) {
                    map.removeLayer(endpointMarkers[key]);
                    delete endpointMarkers[key];
                }
            });
            if (userPinMarker) {
                map.removeLayer(userPinMarker);
                userPinMarker = null;
            }
            
            selectedDefaultPolyline = null;
            if (selectedBusCard) {
                selectedBusCard.classList.remove('bus-card-selected');
                const selectedStops = selectedBusCard.querySelector('.bus-card-stops');
                if (selectedStops) {
                    selectedStops.classList.add('hidden');
                }
                selectedBusCard = null;
                selectedBusRouteName = null;
            }

            try {
                const stops = [];
                stops.push({
                    name: origin.value,
                    coords: selectedCoords.origin
                });



                stops.push({
                    name: destination.value,
                    coords: selectedCoords.destination
                });

                let currentDate = document.getElementById('date').value;
                let currentTime = document.getElementById('time').value;

                async function fetchRouteLeg(oName, dName, oCoords, dCoords, date, time) {
                    const response = await fetch('/api/routes/search', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')
                                .content
                        },
                        body: JSON.stringify({
                            origin_name: oName,
                            destination_name: dName,
                            date: date,
                            time: time,
                            origin_lat: oCoords ? oCoords.lat : null,
                            origin_lon: oCoords ? oCoords.lon : null,
                            dest_lat: dCoords ? dCoords.lat : null,
                            dest_lon: dCoords ? dCoords.lon : null
                        })
                    });
                    return await response.json();
                }

                function convertDirectRouteToLegs(route, originCoords, destCoords, originName, destName) {
                    const transitLeg = {
                        type: 'transit',
                        from: route.from || (route.stops && route.stops.length > 0 ? route.stops[0].name : 'Unknown'),
                        to: route.to || (route.stops && route.stops.length > 0 ? route.stops[route.stops.length - 1].name : 'Unknown'),
                        departure_time: route.leave_at || route.departure_time,
                        arrival_time: route.arrival_time,
                        duration: route.duration,
                        route_name: route.route_name || route.route_short_name || route.route_long_name || 'Route',
                        route_color: route.route_color,
                        route_text_color: route.route_text_color,
                        stops: route.stops,
                        shape_pts: route.shape_pts,
                        from_lat: route.stops && route.stops.length > 0 ? route.stops[0].lat : null,
                        from_lon: route.stops && route.stops.length > 0 ? route.stops[0].lon : null,
                        to_lat: route.stops && route.stops.length > 0 ? route.stops[route.stops.length - 1].lat : null,
                        to_lon: route.stops && route.stops.length > 0 ? route.stops[route.stops.length - 1].lon : null,
                    };

                    const legs = [];

                    if (originCoords && transitLeg.from_lat) {
                        const distToStart = haversineKm([[originCoords[0], originCoords[1]], [transitLeg.from_lat, transitLeg.from_lon]]);
                        if (distToStart > 0.05) {
                            legs.push({
                                type: 'walk',
                                from: originName || 'Origin',
                                to: transitLeg.from,
                                from_lat: originCoords[0],
                                from_lon: originCoords[1],
                                to_lat: transitLeg.from_lat,
                                to_lon: transitLeg.from_lon,
                                distance: distToStart.toFixed(2),
                                duration: Math.round(distToStart / 5.0 * 60)
                            });
                        }
                    }

                    legs.push(transitLeg);

                    if (destCoords && transitLeg.to_lat) {
                        const distToEnd = haversineKm([[transitLeg.to_lat, transitLeg.to_lon], [destCoords[0], destCoords[1]]]);
                        if (distToEnd > 0.05) {
                            legs.push({
                                type: 'walk',
                                from: transitLeg.to,
                                to: destName || 'Destination',
                                from_lat: transitLeg.to_lat,
                                from_lon: transitLeg.to_lon,
                                to_lat: destCoords[0],
                                to_lon: destCoords[1],
                                distance: distToEnd.toFixed(2),
                                duration: Math.round(distToEnd / 5.0 * 60)
                            });
                        }
                    }

                    return legs;
                }

                let finalRoutes = [];

                if (stops.length === 2) {
                    const data = await fetchRouteLeg(stops[0].name, stops[1].name, stops[0].coords, stops[1]
                        .coords, currentDate, currentTime);
                    if (data.routes && data.routes.length > 0) {
                        data.routes.forEach(route => {
                            if (route.connection_type !== 'transfer') {
                                route.legs = convertDirectRouteToLegs(route, stops[0].coords, stops[1].coords, stops[0].name, stops[1].name);
                                route.connection_type = 'transfer';
                            }
                            finalRoutes.push(route);
                        });
                    }
                } else {
                    const firstLegData = await fetchRouteLeg(stops[0].name, stops[1].name, stops[0].coords,
                        stops[1].coords, currentDate, currentTime);
                    if (!firstLegData.routes || firstLegData.routes.length === 0) throw new Error(
                        "No route found for the first leg.");

                    const firstLegOptions = firstLegData.routes.slice(0, 3); // max 3 options

                    for (const option of firstLegOptions) {
                        let currentOptionLegs = [];
                        let currentArrTime = option.arrival_time;
                        let depTime = option.leave_at || option.departure_time;

                        if (option.connection_type !== 'transfer') {
                            currentOptionLegs.push(...convertDirectRouteToLegs(option, stops[0].coords, stops[1].coords, stops[0].name, stops[1].name));
                        } else {
                            currentOptionLegs.push(...option.legs);
                        }

                        let validJourney = true;
                        for (let i = 1; i < stops.length - 1; i++) {
                            const fromStop = stops[i];
                            const toStop = stops[i + 1];

                            const [h, m] = currentArrTime.split(':').map(Number);
                            const nextTimeObj = new Date(0, 0, 0, h, m + 5);
                            const nextTimeStr =
                                `${nextTimeObj.getHours().toString().padStart(2, '0')}:${nextTimeObj.getMinutes().toString().padStart(2, '0')}`;

                            const legData = await fetchRouteLeg(fromStop.name, toStop.name, fromStop.coords,
                                toStop.coords, currentDate, nextTimeStr);
                            if (!legData.routes || legData.routes.length === 0) {
                                validJourney = false;
                                break;
                            }

                            const bestNextLeg = legData.routes[0];
                            currentArrTime = bestNextLeg.arrival_time;
                            if (bestNextLeg.connection_type !== 'transfer') {
                                currentOptionLegs.push(...convertDirectRouteToLegs(bestNextLeg, fromStop.coords, toStop.coords, fromStop.name, toStop.name));
                            } else {
                                currentOptionLegs.push(...bestNextLeg.legs);
                            }
                        }

                        if (validJourney) {
                            const start = new Date(`1970/01/01 ${depTime}`);
                            const end = new Date(`1970/01/01 ${currentArrTime}`);
                            let diffMins = Math.round((end - start) / 60000);
                            if (diffMins < 0) diffMins += 24 * 60;

                            finalRoutes.push({
                                connection_type: 'transfer',
                                departure_time: depTime,
                                leave_at: depTime,
                                arrival_time: currentArrTime,
                                duration: `${Math.floor(diffMins / 60)}h ${diffMins % 60}m`,
                                legs: currentOptionLegs
                            });
                        }
                    }
                }

                overlay.classList.remove('active');

                if (finalRoutes.length === 0) {
                    noResults.classList.remove('hidden');
                    return;
                }

                currentRouteData = finalRoutes;
                noResults.classList.add('hidden');
                routesList.classList.remove('hidden');

                routesList.innerHTML = finalRoutes.map((route, index) => {
                    return renderTransferRoute(route, index);
                }).join('');

                routesList.classList.remove('hidden');

                if (finalRoutes && finalRoutes.length > 0) {
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

        window.showRouteOnMap = async function(routeIndex) {
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
                walk: '#4285F4', // Google blue
                transit: '#DC2626'
            };

            function addBounds(lat, lon) {
                if (lat != null && lon != null) bounds.push([parseFloat(lat), parseFloat(lon)]);
            }

            function buildWalkCacheKey(fromLat, fromLon, toLat, toLon) {
                return [fromLat, fromLon, toLat, toLon]
                    .map(value => Number.parseFloat(value).toFixed(5))
                    .join(',');
            }

            async function fetchWalkCandidates(baseUrl, profilePath, fromLat, fromLon, toLat, toLon) {
                const controller = new AbortController();
                const timeoutId = setTimeout(() => controller.abort(), 7000);

                try {
                    const url =
                        `${baseUrl}/${profilePath}/${fromLon},${fromLat};${toLon},${toLat}?overview=full&geometries=geojson&steps=false&alternatives=true`;
                    const response = await fetch(url, {
                        signal: controller.signal
                    });
                    if (!response.ok) {
                        return [];
                    }

                    const data = await response.json();
                    if (!Array.isArray(data?.routes)) {
                        return [];
                    }

                    return data.routes
                        .map(route => {
                            const coordinates = route?.geometry?.coordinates;
                            if (!Array.isArray(coordinates) || coordinates.length < 2) {
                                return null;
                            }

                            return {
                                distanceKm: Number(route.distance || 0) / 1000,
                                latLngs: coordinates.map(([lon, lat]) => [parseFloat(lat), parseFloat(
                                    lon)]),
                            };
                        })
                        .filter(Boolean);
                } catch (error) {
                    return [];
                } finally {
                    clearTimeout(timeoutId);
                }
            }

            async function fetchWalkingRouteLatLngs(fromLat, fromLon, toLat, toLon, mode = 'foot') {
                const fromLatNum = parseFloat(fromLat);
                const fromLonNum = parseFloat(fromLon);
                const toLatNum = parseFloat(toLat);
                const toLonNum = parseFloat(toLon);

                const fallback = [
                    [fromLatNum, fromLonNum],
                    [toLatNum, toLonNum]
                ];

                const cacheKey = buildWalkCacheKey(fromLatNum, fromLonNum, toLatNum, toLonNum) + '_' + mode;
                const reverseCacheKey = buildWalkCacheKey(toLatNum, toLonNum, fromLatNum, fromLonNum) + '_' + mode;

                if (walkingRouteCache.has(cacheKey)) {
                    return walkingRouteCache.get(cacheKey);
                }

                let providers = [];
                if (mode === 'bike') {
                    providers = [
                        { baseUrl: 'https://routing.openstreetmap.de/routed-bike/route/v1', profilePath: 'driving', penalty: 0 },
                        { baseUrl: 'https://router.project-osrm.org/route/v1', profilePath: 'bike', penalty: 0.05 }
                    ];
                } else if (mode === 'car') {
                    providers = [
                        { baseUrl: 'https://routing.openstreetmap.de/routed-car/route/v1', profilePath: 'driving', penalty: 0 },
                        { baseUrl: 'https://router.project-osrm.org/route/v1', profilePath: 'driving', penalty: 0.05 }
                    ];
                } else {
                    providers = [
                        { baseUrl: 'https://routing.openstreetmap.de/routed-foot/route/v1', profilePath: 'driving', penalty: 0 },
                        { baseUrl: 'https://routing.openstreetmap.de/routed-bike/route/v1', profilePath: 'driving', penalty: 0.04 },
                        { baseUrl: 'https://router.project-osrm.org/route/v1', profilePath: 'foot', penalty: 0.06 },
                        { baseUrl: 'https://router.project-osrm.org/route/v1', profilePath: 'bike', penalty: 0.08 },
                        { baseUrl: 'https://router.project-osrm.org/route/v1', profilePath: 'driving', penalty: 0.12 }
                    ];
                }

                const settled = await Promise.allSettled(
                    providers.map(provider => fetchWalkCandidates(
                        provider.baseUrl,
                        provider.profilePath,
                        fromLatNum,
                        fromLonNum,
                        toLatNum,
                        toLonNum
                    ))
                );

                const candidates = [];
                settled.forEach((result, idx) => {
                    if (result.status !== 'fulfilled') {
                        return;
                    }

                    const penalty = providers[idx].penalty;
                    result.value.forEach(route => {
                        if (route.distanceKm > 0 && route.latLngs.length > 1) {
                            candidates.push({
                                score: route.distanceKm * (1 + penalty),
                                latLngs: route.latLngs,
                            });
                        }
                    });
                });

                if (candidates.length === 0) {
                    walkingRouteCache.set(cacheKey, fallback);
                    walkingRouteCache.set(reverseCacheKey, [...fallback].reverse());

                    return fallback;
                }

                candidates.sort((a, b) => a.score - b.score);
                const best = candidates[0].latLngs;

                walkingRouteCache.set(cacheKey, best);
                walkingRouteCache.set(reverseCacheKey, [...best].reverse());

                return best;
            }

            /**
             * Draw a routing line between two coordinates (walk, bike, or car).
             */
            async function drawWalkLeg(fromLat, fromLon, toLat, toLon, popupHtml, mode = 'foot', pathColor = null, iconHtml = null) {
                const latlngs = await fetchWalkingRouteLatLngs(fromLat, fromLon, toLat, toLon, mode);
                const colorToUse = pathColor || colors.walk;

                // Simplified robust dotted line logic
                L.polyline(latlngs, {
                    color: colorToUse,
                    weight: 6,
                    lineCap: 'square', // Better for dots
                    dashArray: mode === 'foot' ? '4, 8' : null, // Solid line for non-walk
                    opacity: 0.8
                }).addTo(searchResultsLayer).bindPopup(popupHtml);

                L.polyline(latlngs, {
                    color: colorToUse,
                    weight: 12,
                    opacity: 0.15, // Subtle halo
                    lineCap: 'round'
                }).addTo(searchResultsLayer).bindPopup(popupHtml);

                // Endpoint markers
                const endpointStyle = {
                    radius: 5,
                    fillColor: colorToUse,
                    color: '#FFFFFF',
                    weight: 2,
                    opacity: 1,
                    fillOpacity: 1
                };
                L.circleMarker(latlngs[0], endpointStyle)
                    .addTo(searchResultsLayer)
                    .bindPopup(popupHtml);
                L.circleMarker(latlngs[latlngs.length - 1], endpointStyle)
                    .addTo(searchResultsLayer)
                    .bindPopup(popupHtml);

                // Figure icon at midpoint
                const midPoint = latlngs[Math.floor(latlngs.length / 2)] || latlngs[0];
                const finalIconHtml = iconHtml || `<div style="background:${colorToUse};color:#fff;width:24px;height:24px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-[800] text-[12px];box-shadow:0 1px 4px rgba(0,0,0,.35);border:2px solid #fff"><i class="fa-solid fa-person-walking"></i></div>`;
                const walkIcon = L.divIcon({
                    className: '',
                    html: finalIconHtml,
                    iconSize: [24, 24],
                    iconAnchor: [12, 12]
                });
                L.marker(midPoint, {
                        icon: walkIcon,
                        interactive: false
                    })
                    .addTo(searchResultsLayer);

                latlngs.forEach(([lat, lon]) => addBounds(lat, lon));
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
                    return leg.stops.map(s => s.lat && s.lon ? [parseFloat(s.lat), parseFloat(s.lon)] : null)
                        .filter(
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
                            const walkDist = leg.distance ? ` · ${(+leg.distance).toFixed(2)} km` : '';
                            const flipWalk = parseFloat(leg.from_lon) > parseFloat(leg.to_lon) ? ' fa-flip-horizontal' : '';
                            await drawWalkLeg(
                                leg.from_lat, leg.from_lon,
                                leg.to_lat, leg.to_lon,
                                `<strong><i class="fa-solid fa-person-walking text-[#7e7e7e]${flipWalk}"></i> Walk ${leg.duration} min</strong>${walkDist}<br>${leg.from} → ${leg.to}`
                            );
                        }
                    } else if (leg.type === 'bike' || leg.type === 'car') {
                        if (leg.from_lat && leg.from_lon && leg.to_lat && leg.to_lon) {
                            const rideDist = leg.distance ? ` · ${(+leg.distance).toFixed(2)} km` : '';
                            const flipRide = parseFloat(leg.from_lon) > parseFloat(leg.to_lon) ? ' fa-flip-horizontal' : '';
                            const iconClass = leg.type === 'bike' ? 'fa-bicycle' : 'fa-car';
                            const pathColor = leg.route_color ? '#' + leg.route_color : colors.walk;
                            const customIconHtml = `<div style="background:${pathColor};color:#fff;width:24px;height:24px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-[800] text-[12px];box-shadow:0 1px 4px rgba(0,0,0,.35);border:2px solid #fff"><i class="fa-solid ${iconClass}${flipRide}"></i></div>`;
                            
                            await drawWalkLeg(
                                leg.from_lat, leg.from_lon,
                                leg.to_lat, leg.to_lon,
                                `<strong><i class="fa-solid ${iconClass} text-[#7e7e7e]${flipRide}"></i> ${leg.route_name || 'Ride'} ${leg.duration} min</strong>${rideDist}<br>${leg.from} → ${leg.to}`,
                                leg.type,
                                pathColor,
                                customIconHtml
                            );

                            const stationIcon = L.divIcon({
                                className: '',
                                html: `<div style="background:${pathColor};color:#fff;width:28px;height:28px;border-radius:6px;display:flex;align-items:center;justify-content:center;box-shadow:0 2px 5px rgba(0,0,0,.3);border:2px solid #fff"><i class="fa-solid ${iconClass}"></i></div>`,
                                iconSize: [28, 28],
                                iconAnchor: [14, 14]
                            });

                            L.marker([leg.from_lat, leg.from_lon], { icon: stationIcon, zIndexOffset: 1000 }).addTo(searchResultsLayer).bindPopup(`<strong><i class="fa-solid ${iconClass}"></i> ${leg.from}</strong><br>Station`);
                            L.marker([leg.to_lat, leg.to_lon], { icon: stationIcon, zIndexOffset: 1000 }).addTo(searchResultsLayer).bindPopup(`<strong><i class="fa-solid ${iconClass}"></i> ${leg.to}</strong><br>Station`);
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
                                .bindPopup(
                                    `<strong><i class="fa-solid fa-bus text-[#7e7e7e]"></i> ${leg.route_name || leg.route_short_name || leg.route_long_name || 'Route'}</strong><br>${leg.from} → ${leg.to}${co2PopupLine(legMapDistKm)}`
                                );
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
                            const isEndpoint = stop.is_intermediate === false;
                            const escapedName = stop.name.replace(/'/g, "\\'");
                            const popupHtml = `
                                <div class="p-2 w-[220px]">
                                    <div class="font-[600] text-[14px] mb-1 text-white leading-tight">${stop.name}</div>
                                    ${stop.arrival ? `<div class="text-[11px] text-[#9CA3AF] mb-3 font-mono">${stop.arrival}</div>` : ''}
                                    <div class="flex gap-2 pt-3 border-t border-[#374151]">
                                        <button onclick="setAsRouteEndpoint('origin', '${escapedName}', ${stop.lat}, ${stop.lon})" class="flex-1 bg-[#191C21] border border-[#EA580C] hover:bg-[#EA580C] hover:text-white text-[#EA580C] font-[600] py-1.5 px-2 rounded-[6px] transition-colors text-center text-[11px] uppercase tracking-wider font-mono shadow-sm">Start</button>
                                        <button onclick="setAsRouteEndpoint('destination', '${escapedName}', ${stop.lat}, ${stop.lon})" class="flex-1 bg-[#191C21] border border-[#EA580C] hover:bg-[#EA580C] hover:text-white text-[#EA580C] font-[600] py-1.5 px-2 rounded-[6px] transition-colors text-center text-[11px] uppercase tracking-wider font-mono shadow-sm">End</button>
                                    </div>
                                </div>
                            `;
                            L.circleMarker([parseFloat(stop.lat), parseFloat(stop.lon)], {
                                    radius: isEndpoint ? 5 : 3,
                                    fillColor: isEndpoint ? '#FFFFFF' : '#FFFFFF',
                                    color: isEndpoint ? '#000000' : pathColor,
                                    weight: isEndpoint ? 2 : 2,
                                    opacity: 1,
                                    fillOpacity: 1
                                }).addTo(searchResultsLayer)
                                .bindPopup(popupHtml, { className: 'rounded-xl' });
                        });
                    }
                }

                // Draw bold explicitly marked Origin and Destination endpoints
                const firstLeg = route.legs[0];
                const lastLeg = route.legs[route.legs.length - 1];

                const uiOrigLat = selectedCoords.origin ? parseFloat(selectedCoords.origin.lat) : null;
                const uiOrigLon = selectedCoords.origin ? parseFloat(selectedCoords.origin.lon) : null;
                const uiDestLat = selectedCoords.destination ? parseFloat(selectedCoords.destination.lat) : null;
                const uiDestLon = selectedCoords.destination ? parseFloat(selectedCoords.destination.lon) : null;

                const startLat = uiOrigLat || (firstLeg && firstLeg.from_lat ? parseFloat(firstLeg.from_lat) : null);
                const startLon = uiOrigLon || (firstLeg && firstLeg.from_lon ? parseFloat(firstLeg.from_lon) : null);
                const endLat = uiDestLat || (lastLeg && lastLeg.to_lat ? parseFloat(lastLeg.to_lat) : null);
                const endLon = uiDestLon || (lastLeg && lastLeg.to_lon ? parseFloat(lastLeg.to_lon) : null);

                if (startLat && startLon) {
                    const startIcon = L.divIcon({
                        html: '<div style="background:#3b82f6;color:#fff;width:24px;height:24px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:bold;border:2px solid #fff;box-shadow:0 2px 4px rgba(0,0,0,0.3);">A</div>',
                        className: '',
                        iconSize: [24, 24],
                        iconAnchor: [12, 12]
                    });
                    L.marker([startLat, startLon], {
                            icon: startIcon
                        })
                        .addTo(searchResultsLayer).bindPopup(`<strong>Origin</strong><br>${firstLeg ? firstLeg.from : 'Start'}`);
                }
                if (endLat && endLon) {
                    const endIcon = L.divIcon({
                        html: '<div style="background:#dc2626;color:#fff;width:24px;height:24px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:bold;border:2px solid #fff;box-shadow:0 2px 4px rgba(0,0,0,0.3);">B</div>',
                        className: '',
                        iconSize: [24, 24],
                        iconAnchor: [12, 12]
                    });
                    L.marker([endLat, endLon], {
                            icon: endIcon
                        })
                        .addTo(searchResultsLayer).bindPopup(`<strong>Destination</strong><br>${lastLeg ? lastLeg.to : 'End'}`);
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
                        .bindPopup(
                            `<strong><i class="fa-solid fa-bus text-[#7e7e7e]"></i> ${route.route_name || route.route_short_name || route.route_long_name || 'Route'}</strong>${co2PopupLine(directMapDistKm)}`
                            );
                    shapePts.forEach(pt => bounds.push(pt));
                }

                route.stops.forEach(stop => {
                    if (!stop || !stop.lat || !stop.lon) return;
                    const isMid = stop.is_intermediate === true;
                    const escapedName = stop.name.replace(/'/g, "\\'");
                    const popupHtml = `
                        <div class="p-2 w-[220px]">
                            <div class="font-[600] text-[14px] mb-1 text-white leading-tight">${stop.name}</div>
                            ${stop.arrival ? `<div class="text-[11px] text-[#9CA3AF] mb-3 font-mono">${stop.arrival}</div>` : ''}
                            <div class="flex gap-2 pt-3 border-t border-[#374151]">
                                <button onclick="setAsRouteEndpoint('origin', '${escapedName}', ${stop.lat}, ${stop.lon})" class="flex-1 bg-[#191C21] border border-[#EA580C] hover:bg-[#EA580C] hover:text-white text-[#EA580C] font-[600] py-1.5 px-2 rounded-[6px] transition-colors text-center text-[11px] uppercase tracking-wider font-mono shadow-sm">Start</button>
                                <button onclick="setAsRouteEndpoint('destination', '${escapedName}', ${stop.lat}, ${stop.lon})" class="flex-1 bg-[#191C21] border border-[#EA580C] hover:bg-[#EA580C] hover:text-white text-[#EA580C] font-[600] py-1.5 px-2 rounded-[6px] transition-colors text-center text-[11px] uppercase tracking-wider font-mono shadow-sm">End</button>
                            </div>
                        </div>
                    `;
                    L.circleMarker([parseFloat(stop.lat), parseFloat(stop.lon)], {
                            radius: isMid ? 4 : 7,
                            fillColor: isMid ? '#FFFFFF' : pathColor,
                            color: pathColor,
                            weight: isMid ? 2 : 3,
                            opacity: 1,
                            fillOpacity: isMid ? 0.85 : 1
                        }).addTo(searchResultsLayer)
                        .bindPopup(popupHtml, { className: 'rounded-xl' });
                });

                // Draw bold explicitly marked Origin and Destination endpoints for direct routes
                const firstStop = route.stops[0];
                const lastStop = route.stops[route.stops.length - 1];

                const uiOrigLat = selectedCoords.origin ? selectedCoords.origin.lat : null;
                const uiOrigLon = selectedCoords.origin ? selectedCoords.origin.lon : null;
                const uiDestLat = selectedCoords.destination ? selectedCoords.destination.lat : null;
                const uiDestLon = selectedCoords.destination ? selectedCoords.destination.lon : null;

                const actualOriginLat = uiOrigLat ? parseFloat(uiOrigLat) : parseFloat(firstStop.lat);
                const actualOriginLon = uiOrigLon ? parseFloat(uiOrigLon) : parseFloat(firstStop.lon);
                const actualDestLat = uiDestLat ? parseFloat(uiDestLat) : parseFloat(lastStop.lat);
                const actualDestLon = uiDestLon ? parseFloat(uiDestLon) : parseFloat(lastStop.lon);

                const uiOriginName = document.getElementById('origin').value.toLowerCase().trim();
                const uiDestName = document.getElementById('destination').value.toLowerCase().trim();
                const originMatch = firstStop.name.toLowerCase().includes(uiOriginName) || uiOriginName.includes(
                    firstStop.name.toLowerCase());
                const destMatch = lastStop.name.toLowerCase().includes(uiDestName) || uiDestName.includes(lastStop
                    .name
                    .toLowerCase());

                const distToOrigin = haversineKm([
                    [actualOriginLat, actualOriginLon],
                    [parseFloat(firstStop.lat), parseFloat(firstStop.lon)]
                ]);
                const distToDest = haversineKm([
                    [parseFloat(lastStop.lat), parseFloat(lastStop.lon)],
                    [actualDestLat, actualDestLon]
                ]);

                // For direct routes, only show walk overlays when the selected point is clearly not the same stop.
                if (!originMatch && distToOrigin > 0.35) {
                    const flipStart = parseFloat(firstStop.lon) < actualOriginLon ? ' fa-flip-horizontal' : '';
                    await drawWalkLeg(actualOriginLat, actualOriginLon, firstStop.lat, firstStop.lon,
                        `<strong><i class="fa-solid fa-person-walking text-[#7e7e7e]${flipStart}"></i> Walk to ${firstStop.name}</strong>`
                        );
                }

                if (!destMatch && distToDest > 0.35) {
                    const flipEnd = actualDestLon < parseFloat(lastStop.lon) ? ' fa-flip-horizontal' : '';
                    await drawWalkLeg(lastStop.lat, lastStop.lon, actualDestLat, actualDestLon,
                        `<strong><i class="fa-solid fa-person-walking text-[#7e7e7e]${flipEnd}"></i> Walk from ${lastStop.name}</strong>`
                        );
                }

                const markerALat = actualOriginLat;
                const markerALon = actualOriginLon;
                const markerBLat = actualDestLat;
                const markerBLon = actualDestLon;

                if (markerALat && markerALon) {
                    const startIcon = L.divIcon({
                        html: '<div style="background:#3b82f6;color:#fff;width:24px;height:24px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:bold;border:2px solid #fff;box-shadow:0 2px 4px rgba(0,0,0,0.3);">A</div>',
                        className: '',
                        iconSize: [24, 24],
                        iconAnchor: [12, 12]
                    });
                    L.marker([markerALat, markerALon], {
                            icon: startIcon
                        })
                        .addTo(searchResultsLayer).bindPopup(`<strong>Origin</strong>`);
                }
                if (markerBLat && markerBLon) {
                    const endIcon = L.divIcon({
                        html: '<div style="background:#dc2626;color:#fff;width:24px;height:24px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:bold;border:2px solid #fff;box-shadow:0 2px 4px rgba(0,0,0,0.3);">B</div>',
                        className: '',
                        iconSize: [24, 24],
                        iconAnchor: [12, 12]
                    });
                    L.marker([markerBLat, markerBLon], {
                            icon: endIcon
                        })
                        .addTo(searchResultsLayer).bindPopup(`<strong>Destination</strong>`);
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
