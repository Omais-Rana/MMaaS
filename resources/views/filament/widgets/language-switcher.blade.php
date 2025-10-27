<div class="fi-wi-language-switcher">
    <div class="flex justify-end items-center gap-4 p-4">
        <span class="text-sm font-medium text-gray-700 dark:text-gray-300">
            {{ __('Language') }}:
        </span>
        <div class="inline-flex gap-2">
            <a href="{{ url()->current() }}?locale=en" wire:navigate="false"
                class="inline-flex items-center px-3 py-1.5 rounded-lg text-sm font-medium transition-colors {{ app()->getLocale() == 'en' ? 'bg-primary-100 text-primary-700 dark:bg-primary-900 dark:text-primary-300' : 'text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-800' }}">
                🇬🇧 English
            </a>
            <a href="{{ url()->current() }}?locale=fr" wire:navigate="false"
                class="inline-flex items-center px-3 py-1.5 rounded-lg text-sm font-medium transition-colors {{ app()->getLocale() == 'fr' ? 'bg-primary-100 text-primary-700 dark:bg-primary-900 dark:text-primary-300' : 'text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-800' }}">
                🇫🇷 Français
            </a>
        </div>
    </div>
</div>
