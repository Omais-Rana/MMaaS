<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RedirectIfAuthenticated
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, string ...$guards): Response
    {
        $guards = empty($guards) ? [null] : $guards;

        foreach ($guards as $guard) {
            if (Auth::guard($guard)->check()) {
                $user = Auth::user();

                // Redirect authenticated users to their appropriate panel
                return match ($user->role) {
                    'superadmin' => redirect('/superadmin'),
                    'subadmin', 'employee' => redirect('/subadmin'),
                    'customer' => redirect('/customer'),
                    default => redirect('/'),
                };
            }
        }

        return $next($request);
    }
}
