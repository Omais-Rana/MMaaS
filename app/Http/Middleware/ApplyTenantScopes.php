<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class ApplyTenantScopes
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check()) {
            $user = Auth::user();

            // If the authenticated user is a sub-admin, scope all queries
            if ($user->isSubAdmin()) {
                // Apply global scope to User model for sub-admins
                \App\Models\User::addGlobalScope('tenant', function (Builder $builder) use ($user) {
                    $builder->where(function ($query) use ($user) {
                        $query->where('tenant_id', $user->id)
                            ->orWhere('id', $user->id);
                    });
                });
            }
        }

        return $next($request);
    }
}
