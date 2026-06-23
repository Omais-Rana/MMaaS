<?php

use App\Providers\AppServiceProvider;
use App\Providers\Filament\CustomerPanelProvider;
use App\Providers\Filament\SubAdminPanelProvider;
use App\Providers\Filament\SuperadminPanelProvider;

return [
    AppServiceProvider::class,
    SuperadminPanelProvider::class,
    SubAdminPanelProvider::class,
    CustomerPanelProvider::class,
];
