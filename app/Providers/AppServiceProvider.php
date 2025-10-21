<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Register unified login response
        $this->app->singleton(
            \Filament\Auth\Http\Responses\Contracts\LoginResponse::class,
            \App\Http\Responses\LoginResponse::class
        );

        // Register unified logout response
        $this->app->singleton(
            \Filament\Auth\Http\Responses\Contracts\LogoutResponse::class,
            \App\Http\Responses\LogoutResponse::class
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
