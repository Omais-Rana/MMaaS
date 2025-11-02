<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', 'GTFS Manager') }} - Public Transit Schedule Management</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700" rel="stylesheet" />

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="bg-white dark:bg-gray-900 text-gray-900 dark:text-white">
    {{-- Header with Authentication --}}
    <header class="fixed top-0 left-0 right-0 z-50 bg-white/98 backdrop-blur-md border-b border-gray-100 shadow-sm">
        <div class="max-w-7xl mx-auto px-6 lg:px-8">
            <div class="flex items-center justify-between h-16">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 bg-red-600 rounded-lg flex items-center justify-center">
                        <svg class="h-6 w-6 text-white" fill="currentColor" viewBox="0 0 24 24">
                            <path
                                d="M4 16c0 .88.39 1.67 1 2.22V20c0 .55.45 1 1 1h1c.55 0 1-.45 1-1v-1h8v1c0 .55.45 1 1 1h1c.55 0 1-.45 1-1v-1.78c.61-.55 1-1.34 1-2.22V6c0-3.5-3.58-4-8-4s-8 .5-8 4v10zm3.5 1c-.83 0-1.5-.67-1.5-1.5S6.67 14 7.5 14s1.5.67 1.5 1.5S8.33 17 7.5 17zm9 0c-.83 0-1.5-.67-1.5-1.5s.67-1.5 1.5-1.5 1.5.67 1.5 1.5-.67 1.5-1.5 1.5zm1.5-6H6V6h12v5z" />
                        </svg>
                    </div>
                    <span class="text-xl font-bold text-gray-900">{{ config('app.name', 'GTFS Manager') }}</span>
                </div>

                @if (Route::has('login'))
                    <nav class="flex items-center gap-4">
                        @auth
                            <a href="{{ url('/dashboard') }}"
                                class="inline-flex items-center px-6 py-2.5 bg-red-600 hover:bg-red-700 text-white rounded-lg text-sm font-semibold transition-colors shadow-sm">
                                Dashboard
                            </a>
                        @else
                            <a href="{{ route('login') }}"
                                class="inline-flex items-center px-6 py-2.5 text-gray-700 hover:text-red-600 text-sm font-medium transition-colors">
                                Log in
                            </a>

                            @if (Route::has('register'))
                                <a href="{{ route('register') }}"
                                    class="inline-flex items-center px-6 py-2.5 bg-red-600 hover:bg-red-700 text-white rounded-lg text-sm font-semibold transition-colors shadow-sm">
                                    Sign Up
                                </a>
                            @endif
                        @endauth
                    </nav>
                @endif
            </div>
        </div>
    </header>

    {{-- Hero Section --}}
    <section class="pt-32 pb-20 px-6 lg:px-8 bg-gradient-to-br from-gray-50 via-white to-gray-50">
        <div class="max-w-7xl mx-auto">
            <div class="grid lg:grid-cols-2 gap-12 items-center">
                {{-- Left Content --}}
                <div>
                    <div
                        class="inline-flex items-center gap-2 px-4 py-2 bg-red-50 border border-red-200 rounded-full mb-6">
                        <svg class="w-4 h-4 text-red-600" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M12 2L2 7v10c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V7l-10-5z" />
                        </svg>
                        <span class="text-sm font-semibold text-red-600">Professional GTFS Management</span>
                    </div>

                    <h1 class="text-5xl lg:text-6xl font-bold mb-6 text-gray-900 leading-tight">
                        Manage Transit <span class="text-red-600">Schedules</span> with Confidence
                    </h1>

                    <p class="text-xl text-gray-600 mb-8 leading-relaxed">
                        Import, manage, and distribute GTFS schedule data for buses, trains, and public transit.
                        Streamline operations with our comprehensive platform built for transit operators.
                    </p>

                    <div class="flex flex-col sm:flex-row gap-4">
                        @if (Route::has('register'))
                            <a href="{{ route('register') }}"
                                class="inline-flex items-center justify-center px-8 py-4 bg-red-600 hover:bg-red-700 text-white rounded-lg text-lg font-semibold transition-all shadow-lg hover:shadow-xl">
                                Get Started Free
                                <svg class="w-5 h-5 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M13 7l5 5m0 0l-5 5m5-5H6" />
                                </svg>
                            </a>
                        @endif
                        <a href="#features"
                            class="inline-flex items-center justify-center px-8 py-4 bg-white hover:bg-gray-50 text-gray-900 border-2 border-gray-300 rounded-lg text-lg font-semibold transition-all">
                            Learn More
                        </a>
                    </div>

                    <div class="flex items-center gap-6 mt-8 pt-8 border-t border-gray-200">
                        <div>
                            <div class="text-3xl font-bold text-gray-900">100+</div>
                            <div class="text-sm text-gray-600">Transit Operators</div>
                        </div>
                        <div class="h-12 w-px bg-gray-300"></div>
                        <div>
                            <div class="text-3xl font-bold text-gray-900">50K+</div>
                            <div class="text-sm text-gray-600">Routes Managed</div>
                        </div>
                        <div class="h-12 w-px bg-gray-300"></div>
                        <div>
                            <div class="text-3xl font-bold text-gray-900">99.9%</div>
                            <div class="text-sm text-gray-600">Uptime</div>
                        </div>
                    </div>
                </div>

                {{-- Right Visual --}}
                <div class="hidden lg:block">
                    <div class="relative">
                        <div
                            class="absolute -inset-4 bg-gradient-to-r from-red-100 to-red-50 rounded-3xl transform rotate-3">
                        </div>
                        <div class="relative bg-white rounded-2xl shadow-2xl p-8 border border-gray-200">
                            <div class="space-y-4">
                                <div class="flex items-center gap-3 p-4 bg-red-50 rounded-lg border border-red-200">
                                    <div
                                        class="w-10 h-10 bg-red-600 rounded-lg flex items-center justify-center flex-shrink-0">
                                        <svg class="w-6 h-6 text-white" fill="currentColor" viewBox="0 0 24 24">
                                            <path
                                                d="M9 16.17L5.53 12.7c-.39-.39-1.02-.39-1.41 0-.39.39-.39 1.02 0 1.41l4.18 4.18c.39.39 1.02.39 1.41 0L20.29 7.71c.39-.39.39-1.02 0-1.41-.39-.39-1.02-.39-1.41 0L9 16.17z" />
                                        </svg>
                                    </div>
                                    <div class="flex-1">
                                        <div class="font-semibold text-gray-900">GTFS Import Complete</div>
                                        <div class="text-sm text-gray-600">1,247 routes • 3,891 stops</div>
                                    </div>
                                </div>

                                <div class="flex items-center gap-3 p-4 bg-gray-50 rounded-lg border border-gray-200">
                                    <div
                                        class="w-10 h-10 bg-gray-200 rounded-lg flex items-center justify-center flex-shrink-0">
                                        <svg class="w-6 h-6 text-gray-600" fill="currentColor" viewBox="0 0 24 24">
                                            <path
                                                d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z" />
                                        </svg>
                                    </div>
                                    <div class="flex-1">
                                        <div class="font-semibold text-gray-900">Route Validation</div>
                                        <div class="text-sm text-gray-600">All routes verified</div>
                                    </div>
                                </div>

                                <div class="flex items-center gap-3 p-4 bg-gray-50 rounded-lg border border-gray-200">
                                    <div
                                        class="w-10 h-10 bg-gray-200 rounded-lg flex items-center justify-center flex-shrink-0 animate-pulse">
                                        <svg class="w-6 h-6 text-gray-600" fill="currentColor" viewBox="0 0 24 24">
                                            <path
                                                d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5s-3 1.34-3 3 1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5z" />
                                        </svg>
                                    </div>
                                    <div class="flex-1">
                                        <div class="font-semibold text-gray-900">Team Sync</div>
                                        <div class="text-sm text-gray-600">Syncing changes...</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- Platform Features --}}
    <section class="py-20 px-6 lg:px-8 bg-white">
        <div class="max-w-7xl mx-auto">
            <div class="text-center mb-16">
                <h2 class="text-4xl font-bold mb-4 text-gray-900">
                    Everything You Need to Manage Transit Schedules
                </h2>
                <p class="text-lg text-gray-600">
                    Comprehensive GTFS schedule management for public transit operators
                </p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                {{-- GTFS Import --}}
                <div
                    class="p-8 bg-white rounded-2xl border-2 border-red-100 hover:border-red-300 hover:shadow-xl transition-all">
                    <div class="w-14 h-14 bg-red-600 rounded-xl flex items-center justify-center mb-6">
                        <svg class="w-8 h-8 text-white" fill="currentColor" viewBox="0 0 24 24">
                            <path
                                d="M9 16.17L5.53 12.7c-.39-.39-1.02-.39-1.41 0-.39.39-.39 1.02 0 1.41l4.18 4.18c.39.39 1.02.39 1.41 0L20.29 7.71c.39-.39.39-1.02 0-1.41-.39-.39-1.02-.39-1.41 0L9 16.17z" />
                        </svg>
                    </div>
                    <h3 class="text-2xl font-bold mb-3 text-gray-900">GTFS Import</h3>
                    <p class="text-gray-600">
                        Import GTFS schedule files quickly and validate data automatically with comprehensive error
                        checking.
                    </p>
                </div>

                {{-- Route Management --}}
                <div
                    class="p-8 bg-white rounded-2xl border-2 border-red-100 hover:border-red-300 hover:shadow-xl transition-all">
                    <div class="w-14 h-14 bg-red-600 rounded-xl flex items-center justify-center mb-6">
                        <svg class="w-8 h-8 text-white" fill="currentColor" viewBox="0 0 24 24">
                            <path
                                d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z" />
                        </svg>
                    </div>
                    <h3 class="text-2xl font-bold mb-3 text-gray-900">Route Management</h3>
                    <p class="text-gray-600">
                        Manage bus routes, train lines, and stops with intuitive tools designed for transit operators.
                    </p>
                </div>

                {{-- Schedule Editor --}}
                <div
                    class="p-8 bg-white rounded-2xl border-2 border-red-100 hover:border-red-300 hover:shadow-xl transition-all">
                    <div class="w-14 h-14 bg-red-600 rounded-xl flex items-center justify-center mb-6">
                        <svg class="w-8 h-8 text-white" fill="currentColor" viewBox="0 0 24 24">
                            <path
                                d="M11.99 2C6.47 2 2 6.48 2 12s4.47 10 9.99 10C17.52 22 22 17.52 22 12S17.52 2 11.99 2zM12 20c-4.42 0-8-3.58-8-8s3.58-8 8-8 8 3.58 8 8-3.58 8-8 8zm.5-13H11v6l5.25 3.15.75-1.23-4.5-2.67z" />
                        </svg>
                    </div>
                    <h3 class="text-2xl font-bold mb-3 text-gray-900">Schedule Editor</h3>
                    <p class="text-gray-600">
                        Edit timetables, manage service calendars, and update trip schedules with ease.
                    </p>
                </div>

                {{-- Team Collaboration --}}
                <div
                    class="p-8 bg-white rounded-2xl border-2 border-red-100 hover:border-red-300 hover:shadow-xl transition-all">
                    <div class="w-14 h-14 bg-red-600 rounded-xl flex items-center justify-center mb-6">
                        <svg class="w-8 h-8 text-white" fill="currentColor" viewBox="0 0 24 24">
                            <path
                                d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5s-3 1.34-3 3 1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5z" />
                        </svg>
                    </div>
                    <h3 class="text-2xl font-bold mb-3 text-gray-900">Team Collaboration</h3>
                    <p class="text-gray-600">
                        Manage employee access, assign roles, and collaborate on schedule updates across your team.
                    </p>
                </div>

                {{-- Real-time Updates --}}
                <div
                    class="p-8 bg-white rounded-2xl border-2 border-red-100 hover:border-red-300 hover:shadow-xl transition-all">
                    <div class="w-14 h-14 bg-red-600 rounded-xl flex items-center justify-center mb-6">
                        <svg class="w-8 h-8 text-white" fill="currentColor" viewBox="0 0 24 24">
                            <path
                                d="M17.65 6.35C16.2 4.9 14.21 4 12 4c-4.42 0-7.99 3.58-7.99 8s3.57 8 7.99 8c3.73 0 6.84-2.55 7.73-6h-2.08c-.82 2.33-3.04 4-5.65 4-3.31 0-6-2.69-6-6s2.69-6 6-6c1.66 0 3.14.69 4.22 1.78L13 11h7V4l-2.35 2.35z" />
                        </svg>
                    </div>
                    <h3 class="text-2xl font-bold mb-3 text-gray-900">Activity Tracking</h3>
                    <p class="text-gray-600">
                        Monitor all schedule changes and updates with comprehensive activity logs and audit trails.
                    </p>
                </div>

                {{-- Data Distribution --}}
                <div
                    class="p-8 bg-white rounded-2xl border-2 border-red-100 hover:border-red-300 hover:shadow-xl transition-all">
                    <div class="w-14 h-14 bg-red-600 rounded-xl flex items-center justify-center mb-6">
                        <svg class="w-8 h-8 text-white" fill="currentColor" viewBox="0 0 24 24">
                            <path
                                d="M19.35 10.04C18.67 6.59 15.64 4 12 4 9.11 4 6.6 5.64 5.35 8.04 2.34 8.36 0 10.91 0 14c0 3.31 2.69 6 6 6h13c2.76 0 5-2.24 5-5 0-2.64-2.05-4.78-4.65-4.96zM14 13v4h-4v-4H7l5-5 5 5h-3z" />
                        </svg>
                    </div>
                    <h3 class="text-2xl font-bold mb-3 text-gray-900">Data Export</h3>
                    <p class="text-gray-600">
                        Export GTFS data for distribution to trip planners, apps, and other transit information systems.
                    </p>
                </div>
            </div>
        </div>
    </section>

    {{-- Subscription Plan --}}
    <section class="py-20 px-6 lg:px-8 bg-gray-50">
        <div class="max-w-7xl mx-auto">
            <div class="text-center mb-16">
                <h2 class="text-4xl font-bold mb-4 text-gray-900">
                    Simple, Transparent Pricing
                </h2>
                <p class="text-lg text-gray-600">
                    One subscription, unlimited access to all features
                </p>
            </div>

            <div class="max-w-lg mx-auto">
                {{-- Premium Plan --}}
                <div class="p-10 bg-white rounded-3xl border-2 border-red-200 shadow-2xl">
                    <div class="text-center mb-8">
                        <div
                            class="inline-block px-4 py-2 bg-red-100 text-red-700 rounded-full text-sm font-semibold mb-4">
                            Most Popular
                        </div>
                        <h3 class="text-3xl font-bold mb-4 text-gray-900">Premium Plan</h3>
                        <div class="mb-2">
                            <span class="text-5xl font-bold text-gray-900">$29.99</span>
                            <span class="text-xl text-gray-500">/month</span>
                        </div>
                        <p class="text-gray-600">Everything you need to manage transit schedules</p>
                    </div>

                    <ul class="space-y-4 mb-8">
                        <li class="flex items-start gap-3">
                            <svg class="w-6 h-6 shrink-0 mt-0.5 text-red-600" fill="currentColor"
                                viewBox="0 0 24 24">
                                <path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z" />
                            </svg>
                            <span class="text-lg text-gray-700">Unlimited GTFS schedule imports</span>
                        </li>
                        <li class="flex items-start gap-3">
                            <svg class="w-6 h-6 shrink-0 mt-0.5 text-red-600" fill="currentColor"
                                viewBox="0 0 24 24">
                                <path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z" />
                            </svg>
                            <span class="text-lg text-gray-700">Unlimited routes, stops, and trips</span>
                        </li>
                        <li class="flex items-start gap-3">
                            <svg class="w-6 h-6 shrink-0 mt-0.5 text-red-600" fill="currentColor"
                                viewBox="0 0 24 24">
                                <path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z" />
                            </svg>
                            <span class="text-lg text-gray-700">Team collaboration & employee management</span>
                        </li>
                        <li class="flex items-start gap-3">
                            <svg class="w-6 h-6 shrink-0 mt-0.5 text-red-600" fill="currentColor"
                                viewBox="0 0 24 24">
                                <path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z" />
                            </svg>
                            <span class="text-lg text-gray-700">Real-time activity tracking & audit logs</span>
                        </li>
                        <li class="flex items-start gap-3">
                            <svg class="w-6 h-6 shrink-0 mt-0.5 text-red-600" fill="currentColor"
                                viewBox="0 0 24 24">
                                <path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z" />
                            </svg>
                            <span class="text-lg text-gray-700">Data validation & error checking</span>
                        </li>
                        <li class="flex items-start gap-3">
                            <svg class="w-6 h-6 shrink-0 mt-0.5 text-red-600" fill="currentColor"
                                viewBox="0 0 24 24">
                                <path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z" />
                            </svg>
                            <span class="text-lg text-gray-700">GTFS export & data distribution</span>
                        </li>
                        <li class="flex items-start gap-3">
                            <svg class="w-6 h-6 shrink-0 mt-0.5 text-red-600" fill="currentColor"
                                viewBox="0 0 24 24">
                                <path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z" />
                            </svg>
                            <span class="text-lg text-gray-700">Priority support & assistance</span>
                        </li>
                    </ul>

                    @if (Route::has('register'))
                        <a href="{{ route('register') }}"
                            class="block text-center w-full px-8 py-4 bg-red-600 hover:bg-red-700 text-white rounded-xl font-bold text-lg transition-colors shadow-lg">
                            Start Free Trial
                        </a>
                    @else
                        <button
                            class="w-full px-8 py-4 bg-red-600 hover:bg-red-700 text-white rounded-xl font-bold text-lg transition-colors shadow-lg">
                            Contact Sales
                        </button>
                    @endif

                    <p class="text-center text-gray-500 text-sm mt-4">14-day free trial • No credit card required</p>
                </div>
            </div>
        </div>
    </section>

    {{-- Footer --}}
    <footer class="py-12 px-6 lg:px-8 bg-gray-900 dark:bg-black text-white">
        <div class="max-w-7xl mx-auto text-center">
            <div class="mb-6">
                <div class="flex items-center justify-center gap-3 mb-4">
                    <svg class="h-8 w-8 text-red-600 dark:text-red-500" fill="currentColor" viewBox="0 0 24 24">
                        <path
                            d="M4 16c0 .88.39 1.67 1 2.22V20c0 .55.45 1 1 1h1c.55 0 1-.45 1-1v-1h8v1c0 .55.45 1 1 1h1c.55 0 1-.45 1-1v-1.78c.61-.55 1-1.34 1-2.22V6c0-3.5-3.58-4-8-4s-8 .5-8 4v10zm3.5 1c-.83 0-1.5-.67-1.5-1.5S6.67 14 7.5 14s1.5.67 1.5 1.5S8.33 17 7.5 17zm9 0c-.83 0-1.5-.67-1.5-1.5s.67-1.5 1.5-1.5 1.5.67 1.5 1.5-.67 1.5-1.5 1.5zm1.5-6H6V6h12v5z" />
                    </svg>
                    <span class="text-xl font-bold">{{ config('app.name', 'GTFS Manager') }}</span>
                </div>
                <p class="text-gray-400 text-sm">
                    Professional GTFS schedule management for transit operators
                </p>
            </div>
            <div class="border-t border-gray-800 pt-8">
                <p class="text-gray-500 text-sm">
                    &copy; {{ date('Y') }} {{ config('app.name', 'GTFS Manager') }}. All rights reserved.
                </p>
            </div>
        </div>
    </footer>
</body>

</html>
