<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;

class LanguageSwitcher extends Widget
{
    protected string $view = 'filament.widgets.language-switcher';

    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = -100;
}
