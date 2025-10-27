<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        // Use authenticated user's saved locale preference
        if (Auth::check() && Auth::user()->locale) {
            App::setLocale(Auth::user()->locale);
        } else {
            App::setLocale(config('app.locale'));
        }

        return $next($request);
    }
}
