<div>
    @php
        $currentLocale = auth()->user()->locale ?? 'en';
    @endphp

    <x-filament::button wire:click="switchLanguage" color="gray" size="sm" class="language-switcher-btn">
        @if ($currentLocale === 'en')
            <x-filament::icon icon="heroicon-o-language" class="w-4 h-4" />
            <span class="ml-2">Passer au Français</span>
        @else
            <x-filament::icon icon="heroicon-o-language" class="w-4 h-4" />
            <span class="ml-2">Switch to English</span>
        @endif
    </x-filament::button>

</div>
