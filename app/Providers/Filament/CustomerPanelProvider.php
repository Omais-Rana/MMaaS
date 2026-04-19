<?php

namespace App\Providers\Filament;

use App\Filament\Customer\Pages\Auth\Login;
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
use Filament\Widgets\AccountWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class CustomerPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('portal')
            ->path('portal')
            ->login(Login::class)
            ->passwordReset()
            ->emailVerification()
            ->profile()
            ->brandName('Customer Portal')
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
            ->discoverResources(in: app_path('Filament/Customer/Resources'), for: 'App\Filament\Customer\Resources')
            ->discoverPages(in: app_path('Filament/Customer/Pages'), for: 'App\Filament\Customer\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Customer/Widgets'), for: 'App\Filament\Customer\Widgets')
            ->widgets([
                AccountWidget::class,
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
            ])
            ->resourceCreatePageRedirect('index')
            ->resourceEditPageRedirect('index')
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->plugin(BriskTheme::make())
            ->plugin(AuthUIEnhancerPlugin::make());
    }
}
