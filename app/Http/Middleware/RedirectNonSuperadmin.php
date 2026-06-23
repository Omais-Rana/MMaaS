<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RedirectNonSuperadmin
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Skip for login/logout routes
        if (
            $request->routeIs('filament.admin.auth.login') ||
            $request->routeIs('filament.admin.auth.logout')
        ) {
            return $next($request);
        }

        // If user is authenticated but not a superadmin, redirect to their panel
        if (Auth::check()) {
            /** @var User $user */
            $user = Auth::user();

            if ($user->role !== 'superadmin') {
                $redirectUrl = match ($user->role) {
                    'subadmin', 'employee' => '/panel',
                    'customer' => '/portal',
                    default => '/',
                };

                return redirect($redirectUrl);
            }
        }

        return $next($request);
    }
}
