<div>
    @php
        $currentLocale = auth()->user()->locale ?? 'en';
    @endphp

    <x-filament::link wire:click="switchLanguage" tag="button" type="button"
        class="flex items-center gap-1 cursor-pointer">
        @if ($currentLocale === 'en')
            <span>🇫🇷 Français</span>
        @else
            <span>🇬🇧 English</span>
        @endif
    </x-filament::link>
</div>
