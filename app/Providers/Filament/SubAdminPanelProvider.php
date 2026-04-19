<?php

namespace App\Providers\Filament;

use App\Filament\SubAdmin\Pages\Auth\Login;
use App\Filament\SubAdmin\Widgets\MostSearchedRoutesWidget;
use App\Filament\SubAdmin\Widgets\SubAdminRecentActivity;
use App\Filament\SubAdmin\Widgets\SubAdminStatsOverview;
use App\Http\Middleware\ApplyTenantScopes;
use App\Http\Middleware\SetLocale;
use DiogoGPinto\AuthUIEnhancer\AuthUIEnhancerPlugin;
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

class SubAdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('panel')
            ->path('panel')
            ->login(Login::class)
            ->brandName('Management Panel')
            ->globalSearch(true)
            ->globalSearchKeyBindings(['command+k', 'ctrl+k'])
            ->databaseNotifications()
            ->databaseNotificationsPolling('10s')
            ->breadcrumbs(false)
            ->colors([
                'primary' => Color::Amber,
            ])
            ->userMenuItems([
                Action::make('switch-language')
                    ->label(fn (): string => (Auth::user()?->locale ?? 'en') === 'en' ? 'Switch to French' : 'Passer en anglais')
                    ->icon('heroicon-o-language')
                    ->url(fn (): string => route('locale.toggle')),
            ])
            ->renderHook(
                PanelsRenderHook::SIDEBAR_NAV_START,
                fn (): string => view('filament.components.return-to-map-button')->render()
            )
            ->discoverResources(in: app_path('Filament/SubAdmin/Resources'), for: 'App\Filament\SubAdmin\Resources')
            ->discoverPages(in: app_path('Filament/SubAdmin/Pages'), for: 'App\Filament\SubAdmin\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/SubAdmin/Widgets'), for: 'App\Filament\SubAdmin\Widgets')
            ->widgets([
                SubAdminStatsOverview::class,
                MostSearchedRoutesWidget::class,
                SubAdminRecentActivity::class,
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
                ApplyTenantScopes::class,
            ])
            ->resourceCreatePageRedirect('index')
            ->resourceEditPageRedirect('index')
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->plugin(BriskTheme::make())
            ->plugin(AuthUIEnhancerPlugin::make());
    }
}
