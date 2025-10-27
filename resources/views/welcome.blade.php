<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', 'TravelHub') }} - Discover Your Next Adventure</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700" rel="stylesheet" />

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="bg-white dark:bg-gray-900 text-gray-900 dark:text-white">
    {{-- Header with Authentication --}}
    <header
        class="fixed top-0 left-0 right-0 z-50 bg-white/95 dark:bg-gray-900/95 backdrop-blur-sm border-b border-gray-200 dark:border-gray-800">
        <div class="max-w-7xl mx-auto px-6 lg:px-8">
            <div class="flex items-center justify-between h-16">
                <div class="flex items-center gap-3">
                    <svg class="h-8 w-8 text-red-600 dark:text-red-500" fill="currentColor" viewBox="0 0 24 24">
                        <path
                            d="M12 2L2 7v10c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V7l-10-5zm0 18c-4.41 0-8-3.59-8-8V7.89l8-3.2 8 3.2V12c0 4.41-3.59 8-8 8z" />
                    </svg>
                    <span class="text-xl font-bold">{{ config('app.name', 'TravelHub') }}</span>
                </div>

                @if (Route::has('login'))
                    <nav class="flex items-center gap-4">
                        @auth
                            <a href="{{ url('/dashboard') }}"
                                class="inline-flex items-center px-6 py-2.5 bg-red-600 hover:bg-red-700 dark:bg-red-500 dark:hover:bg-red-600 text-white rounded-lg text-sm font-semibold transition-colors">
                                Dashboard
                            </a>
                        @else
                            <a href="{{ route('login') }}"
                                class="inline-flex items-center px-6 py-2.5 text-gray-700 dark:text-gray-300 hover:text-red-600 dark:hover:text-red-500 text-sm font-medium transition-colors">
                                Log in
                            </a>

                            @if (Route::has('register'))
                                <a href="{{ route('register') }}"
                                    class="inline-flex items-center px-6 py-2.5 bg-red-600 hover:bg-red-700 dark:bg-red-500 dark:hover:bg-red-600 text-white rounded-lg text-sm font-semibold transition-colors">
                                    Sign Up
                                </a>
                            @endif
                        @endauth
                    </nav>
                @endif
            </div>
        </div>
    </header>

    {{-- Hero Section with Search --}}
    <section
        class="pt-32 pb-20 px-6 lg:px-8 bg-gradient-to-br from-red-50 via-orange-50 to-amber-50 dark:from-gray-900 dark:via-red-950 dark:to-gray-900">
        <div class="max-w-7xl mx-auto">
            <div class="text-center max-w-4xl mx-auto mb-12">
                <h1 class="text-5xl lg:text-6xl font-bold mb-6 text-gray-900 dark:text-white">
                    Discover Your Next <span class="text-red-600 dark:text-red-500">Adventure</span>
                </h1>
                <p class="text-xl text-gray-600 dark:text-gray-400 mb-8">
                    Search destinations and get instant suggestions for flights, trains, buses, and more.
                    All your travel options in one place.
                </p>
            </div>

            {{-- Search Box --}}
            <div class="max-w-4xl mx-auto">
                <div
                    class="bg-white dark:bg-gray-800 rounded-2xl shadow-2xl p-8 border border-gray-200 dark:border-gray-700">
                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-4">
                        <div>
                            <label class="block text-sm font-medium mb-2 text-gray-700 dark:text-gray-300">From</label>
                            <input type="text" placeholder="Enter departure city"
                                class="w-full px-4 py-3 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-gray-900 dark:text-white placeholder-gray-500 dark:placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-red-500 dark:focus:ring-red-400">
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-2 text-gray-700 dark:text-gray-300">To</label>
                            <input type="text" placeholder="Enter destination"
                                class="w-full px-4 py-3 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-gray-900 dark:text-white placeholder-gray-500 dark:placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-red-500 dark:focus:ring-red-400">
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-2 text-gray-700 dark:text-gray-300">Date</label>
                            <input type="date"
                                class="w-full px-4 py-3 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-red-500 dark:focus:ring-red-400">
                        </div>
                    </div>
                    <button
                        class="w-full px-8 py-4 bg-red-600 hover:bg-red-700 dark:bg-red-500 dark:hover:bg-red-600 text-white rounded-lg text-lg font-semibold transition-colors shadow-lg hover:shadow-xl">
                        Search All Travel Options
                    </button>
                </div>
            </div>
        </div>
    </section>

    {{-- Multimodal Travel Options --}}
    <section class="py-20 px-6 lg:px-8 bg-white dark:bg-gray-900">
        <div class="max-w-7xl mx-auto">
            <div class="text-center mb-16">
                <h2 class="text-4xl font-bold mb-4 text-gray-900 dark:text-white">
                    All Travel Options, One Platform
                </h2>
                <p class="text-lg text-gray-600 dark:text-gray-400">
                    Compare and book from multiple transportation modes instantly
                </p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                {{-- Flight Option --}}
                <div
                    class="p-8 bg-gradient-to-br from-red-50 to-orange-50 dark:from-gray-800 dark:to-red-900/20 rounded-2xl border border-gray-200 dark:border-gray-700 hover:shadow-xl transition-shadow">
                    <div class="w-14 h-14 bg-red-600 dark:bg-red-500 rounded-xl flex items-center justify-center mb-6">
                        <svg class="w-8 h-8 text-white" fill="currentColor" viewBox="0 0 24 24">
                            <path
                                d="M21 16v-2l-8-5V3.5c0-.83-.67-1.5-1.5-1.5S10 2.67 10 3.5V9l-8 5v2l8-2.5V19l-2 1.5V22l3.5-1 3.5 1v-1.5L13 19v-5.5l8 2.5z" />
                        </svg>
                    </div>
                    <h3 class="text-2xl font-bold mb-3 text-gray-900 dark:text-white">Flights</h3>
                    <p class="text-gray-600 dark:text-gray-400">
                        Compare prices from major airlines and find the best deals on domestic and international
                        flights.
                    </p>
                </div>

                {{-- Train Option --}}
                <div
                    class="p-8 bg-gradient-to-br from-blue-50 to-cyan-50 dark:from-gray-800 dark:to-blue-900/20 rounded-2xl border border-gray-200 dark:border-gray-700 hover:shadow-xl transition-shadow">
                    <div
                        class="w-14 h-14 bg-blue-600 dark:bg-blue-500 rounded-xl flex items-center justify-center mb-6">
                        <svg class="w-8 h-8 text-white" fill="currentColor" viewBox="0 0 24 24">
                            <path
                                d="M4 15.5C4 17.43 5.57 19 7.5 19L6 20.5v.5h12v-.5L16.5 19c1.93 0 3.5-1.57 3.5-3.5V5c0-3.5-3.58-4-8-4s-8 .5-8 4v10.5zm8 1.5c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zm6-7H6V5h12v5z" />
                        </svg>
                    </div>
                    <h3 class="text-2xl font-bold mb-3 text-gray-900 dark:text-white">Trains</h3>
                    <p class="text-gray-600 dark:text-gray-400">
                        Book train tickets with real-time availability and choose from various classes and routes.
                    </p>
                </div>

                {{-- Bus Option --}}
                <div
                    class="p-8 bg-gradient-to-br from-green-50 to-emerald-50 dark:from-gray-800 dark:to-green-900/20 rounded-2xl border border-gray-200 dark:border-gray-700 hover:shadow-xl transition-shadow">
                    <div
                        class="w-14 h-14 bg-green-600 dark:bg-green-500 rounded-xl flex items-center justify-center mb-6">
                        <svg class="w-8 h-8 text-white" fill="currentColor" viewBox="0 0 24 24">
                            <path
                                d="M4 16c0 .88.39 1.67 1 2.22V20c0 .55.45 1 1 1h1c.55 0 1-.45 1-1v-1h8v1c0 .55.45 1 1 1h1c.55 0 1-.45 1-1v-1.78c.61-.55 1-1.34 1-2.22V6c0-3.5-3.58-4-8-4s-8 .5-8 4v10zm3.5 1c-.83 0-1.5-.67-1.5-1.5S6.67 14 7.5 14s1.5.67 1.5 1.5S8.33 17 7.5 17zm9 0c-.83 0-1.5-.67-1.5-1.5s.67-1.5 1.5-1.5 1.5.67 1.5 1.5-.67 1.5-1.5 1.5zm1.5-6H6V6h12v5z" />
                        </svg>
                    </div>
                    <h3 class="text-2xl font-bold mb-3 text-gray-900 dark:text-white">Buses</h3>
                    <p class="text-gray-600 dark:text-gray-400">
                        Affordable bus options with comfortable seating and flexible schedules for budget travelers.
                    </p>
                </div>

                {{-- Car Rental Option --}}
                <div
                    class="p-8 bg-gradient-to-br from-purple-50 to-pink-50 dark:from-gray-800 dark:to-purple-900/20 rounded-2xl border border-gray-200 dark:border-gray-700 hover:shadow-xl transition-shadow">
                    <div
                        class="w-14 h-14 bg-purple-600 dark:bg-purple-500 rounded-xl flex items-center justify-center mb-6">
                        <svg class="w-8 h-8 text-white" fill="currentColor" viewBox="0 0 24 24">
                            <path
                                d="M18.92 6.01C18.72 5.42 18.16 5 17.5 5h-11c-.66 0-1.21.42-1.42 1.01L3 12v8c0 .55.45 1 1 1h1c.55 0 1-.45 1-1v-1h12v1c0 .55.45 1 1 1h1c.55 0 1-.45 1-1v-8l-2.08-5.99zM6.5 16c-.83 0-1.5-.67-1.5-1.5S5.67 13 6.5 13s1.5.67 1.5 1.5S7.33 16 6.5 16zm11 0c-.83 0-1.5-.67-1.5-1.5s.67-1.5 1.5-1.5 1.5.67 1.5 1.5-.67 1.5-1.5 1.5zM5 11l1.5-4.5h11L19 11H5z" />
                        </svg>
                    </div>
                    <h3 class="text-2xl font-bold mb-3 text-gray-900 dark:text-white">Car Rentals</h3>
                    <p class="text-gray-600 dark:text-gray-400">
                        Rent vehicles from trusted providers and enjoy the freedom to explore at your own pace.
                    </p>
                </div>
            </div>
        </div>
    </section>

    {{-- Subscription Plans --}}
    <section class="py-20 px-6 lg:px-8 bg-gradient-to-br from-gray-50 to-gray-100 dark:from-gray-800 dark:to-gray-900">
        <div class="max-w-7xl mx-auto">
            <div class="text-center mb-16">
                <h2 class="text-4xl font-bold mb-4 text-gray-900 dark:text-white">
                    Travel More, Save More
                </h2>
                <p class="text-lg text-gray-600 dark:text-gray-400">
                    Choose a subscription plan that fits your travel needs
                </p>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 max-w-6xl mx-auto">
                {{-- Free Plan --}}
                <div
                    class="p-8 bg-white dark:bg-gray-800 rounded-2xl border-2 border-gray-300 dark:border-gray-700 hover:border-red-500 dark:hover:border-red-500 transition-colors">
                    <h3 class="text-2xl font-bold mb-2 text-gray-900 dark:text-white">Explorer</h3>
                    <div class="mb-6">
                        <span class="text-4xl font-bold text-gray-900 dark:text-white">Free</span>
                    </div>
                    <ul class="space-y-4 mb-8">
                        <li class="flex items-start gap-3">
                            <svg class="w-6 h-6 text-red-600 dark:text-red-500 shrink-0 mt-0.5" fill="currentColor"
                                viewBox="0 0 24 24">
                                <path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z" />
                            </svg>
                            <span class="text-gray-600 dark:text-gray-400">Basic destination search</span>
                        </li>
                        <li class="flex items-start gap-3">
                            <svg class="w-6 h-6 text-red-600 dark:text-red-500 shrink-0 mt-0.5" fill="currentColor"
                                viewBox="0 0 24 24">
                                <path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z" />
                            </svg>
                            <span class="text-gray-600 dark:text-gray-400">Compare up to 10 options</span>
                        </li>
                        <li class="flex items-start gap-3">
                            <svg class="w-6 h-6 text-red-600 dark:text-red-500 shrink-0 mt-0.5" fill="currentColor"
                                viewBox="0 0 24 24">
                                <path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z" />
                            </svg>
                            <span class="text-gray-600 dark:text-gray-400">Email support</span>
                        </li>
                    </ul>
                    <button
                        class="w-full px-6 py-3 border-2 border-red-600 dark:border-red-500 text-red-600 dark:text-red-500 hover:bg-red-600 dark:hover:bg-red-500 hover:text-white rounded-lg font-semibold transition-all">
                        Get Started
                    </button>
                </div>

                {{-- Premium Plan --}}
                <div
                    class="p-8 bg-gradient-to-br from-red-600 to-orange-600 dark:from-red-500 dark:to-orange-500 rounded-2xl text-white shadow-2xl transform lg:scale-105">
                    <div class="inline-block px-3 py-1 bg-white/20 rounded-full text-sm font-medium mb-4">
                        Most Popular
                    </div>
                    <h3 class="text-2xl font-bold mb-2">Adventurer</h3>
                    <div class="mb-6">
                        <span class="text-4xl font-bold">$9.99</span>
                        <span class="text-white/80">/month</span>
                    </div>
                    <ul class="space-y-4 mb-8">
                        <li class="flex items-start gap-3">
                            <svg class="w-6 h-6 shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z" />
                            </svg>
                            <span>Unlimited destination searches</span>
                        </li>
                        <li class="flex items-start gap-3">
                            <svg class="w-6 h-6 shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z" />
                            </svg>
                            <span>Compare unlimited options</span>
                        </li>
                        <li class="flex items-start gap-3">
                            <svg class="w-6 h-6 shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z" />
                            </svg>
                            <span>Price alerts & notifications</span>
                        </li>
                        <li class="flex items-start gap-3">
                            <svg class="w-6 h-6 shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z" />
                            </svg>
                            <span>Priority customer support</span>
                        </li>
                        <li class="flex items-start gap-3">
                            <svg class="w-6 h-6 shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z" />
                            </svg>
                            <span>Exclusive discounts up to 15%</span>
                        </li>
                    </ul>
                    <button
                        class="w-full px-6 py-3 bg-white text-red-600 dark:text-red-500 hover:bg-gray-100 rounded-lg font-semibold transition-colors shadow-lg">
                        Subscribe Now
                    </button>
                </div>

                {{-- Enterprise Plan --}}
                <div
                    class="p-8 bg-white dark:bg-gray-800 rounded-2xl border-2 border-gray-300 dark:border-gray-700 hover:border-red-500 dark:hover:border-red-500 transition-colors">
                    <h3 class="text-2xl font-bold mb-2 text-gray-900 dark:text-white">Globetrotter</h3>
                    <div class="mb-6">
                        <span class="text-4xl font-bold text-gray-900 dark:text-white">$24.99</span>
                        <span class="text-gray-600 dark:text-gray-400">/month</span>
                    </div>
                    <ul class="space-y-4 mb-8">
                        <li class="flex items-start gap-3">
                            <svg class="w-6 h-6 text-red-600 dark:text-red-500 shrink-0 mt-0.5" fill="currentColor"
                                viewBox="0 0 24 24">
                                <path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z" />
                            </svg>
                            <span class="text-gray-600 dark:text-gray-400">Everything in Adventurer</span>
                        </li>
                        <li class="flex items-start gap-3">
                            <svg class="w-6 h-6 text-red-600 dark:text-red-500 shrink-0 mt-0.5" fill="currentColor"
                                viewBox="0 0 24 24">
                                <path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z" />
                            </svg>
                            <span class="text-gray-600 dark:text-gray-400">AI-powered recommendations</span>
                        </li>
                        <li class="flex items-start gap-3">
                            <svg class="w-6 h-6 text-red-600 dark:text-red-500 shrink-0 mt-0.5" fill="currentColor"
                                viewBox="0 0 24 24">
                                <path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z" />
                            </svg>
                            <span class="text-gray-600 dark:text-gray-400">Exclusive discounts up to 25%</span>
                        </li>
                        <li class="flex items-start gap-3">
                            <svg class="w-6 h-6 text-red-600 dark:text-red-500 shrink-0 mt-0.5" fill="currentColor"
                                viewBox="0 0 24 24">
                                <path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z" />
                            </svg>
                            <span class="text-gray-600 dark:text-gray-400">24/7 concierge support</span>
                        </li>
                        <li class="flex items-start gap-3">
                            <svg class="w-6 h-6 text-red-600 dark:text-red-500 shrink-0 mt-0.5" fill="currentColor"
                                viewBox="0 0 24 24">
                                <path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z" />
                            </svg>
                            <span class="text-gray-600 dark:text-gray-400">Travel insurance included</span>
                        </li>
                    </ul>
                    <button
                        class="w-full px-6 py-3 border-2 border-red-600 dark:border-red-500 text-red-600 dark:text-red-500 hover:bg-red-600 dark:hover:bg-red-500 hover:text-white rounded-lg font-semibold transition-all">
                        Get Premium
                    </button>
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
                            d="M12 2L2 7v10c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V7l-10-5zm0 18c-4.41 0-8-3.59-8-8V7.89l8-3.2 8 3.2V12c0 4.41-3.59 8-8 8z" />
                    </svg>
                    <span class="text-xl font-bold">{{ config('app.name', 'TravelHub') }}</span>
                </div>
                <p class="text-gray-400 text-sm">
                    Your complete multimodal travel companion
                </p>
            </div>
            <div class="border-t border-gray-800 pt-8">
                <p class="text-gray-500 text-sm">
                    &copy; {{ date('Y') }} {{ config('app.name', 'TravelHub') }}. All rights reserved.
                </p>
            </div>
        </div>
    </footer>
</body>

</html>
