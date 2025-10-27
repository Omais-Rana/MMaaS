<?php

namespace App\Livewire;

use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class LanguageSwitcherAction extends Component
{
    public function switchLanguage()
    {
        $currentLocale = Auth::user()->locale ?? 'en';
        $newLocale = $currentLocale === 'en' ? 'fr' : 'en';

        // Update user's locale preference
        Auth::user()->update(['locale' => $newLocale]);

        // Refresh the page to apply new locale
        return redirect(request()->header('Referer'));
    }

    public function render()
    {
        return view('livewire.language-switcher-action');
    }
}
