<?php

namespace App\Providers\Filament;

use App\Filament\Customer\Pages\Auth\Register;
use App\Filament\Superadmin\Pages\Auth\Login;
use App\Filament\Superadmin\Widgets\SuperadminStatsOverview;
use App\Http\Middleware\RedirectNonSuperadmin;
use App\Http\Middleware\SetLocale;
use Filafly\Themes\Brisk\BriskTheme;
use Filament\Actions\Action;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class SuperadminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('user')
            ->brandName('Dashboard')
            ->login(Login::class)
            ->registration(Register::class)
            ->breadcrumbs(false)
            ->globalSearch(true)
            ->globalSearchKeyBindings(['command+k', 'ctrl+k'])
            ->databaseNotifications()
            ->databaseNotificationsPolling('10s')
            ->breadcrumbs(false)
            ->colors([
                'primary' => Color::Red,
            ])
            ->userMenuItems([
                Action::make('switch-language')
                    ->label(fn(): string => (Auth::user()?->locale ?? 'en') === 'en' ? 'Switch to French' : 'Passer en anglais')
                    ->icon('heroicon-o-language')
                    ->url(fn(): string => route('locale.toggle')),
            ])
            ->renderHook(
                PanelsRenderHook::SIDEBAR_NAV_START,
                fn(): string => view('filament.components.return-to-map-button')->render()
            )
            ->discoverResources(in: app_path('Filament/Superadmin/Resources'), for: 'App\Filament\Superadmin\Resources')
            ->discoverPages(in: app_path('Filament/Superadmin/Pages'), for: 'App\Filament\Superadmin\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Superadmin/Widgets'), for: 'App\Filament\Superadmin\Widgets')
            ->widgets([
                SuperadminStatsOverview::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
                SetLocale::class,
            ])
            ->authMiddleware([
                Authenticate::class,
                RedirectNonSuperadmin::class,
            ])
            ->resourceCreatePageRedirect('index')
            ->resourceEditPageRedirect('index')
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->plugin(BriskTheme::make());
    }
}
