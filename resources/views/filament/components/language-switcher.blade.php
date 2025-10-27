@php
    $currentLocale = auth()->check() && auth()->user()->locale ? auth()->user()->locale : app()->getLocale();
    $nextLocale = $currentLocale === 'en' ? 'fr' : 'en';
@endphp

<x-filament::link :href="url()->current() . '?locale=' . $nextLocale" tag="a" class="flex items-center gap-1">
    @if ($currentLocale === 'en')
        <span>🇫🇷 Français</span>
    @else
        <span>🇬🇧 English</span>
    @endif
</x-filament::link>
