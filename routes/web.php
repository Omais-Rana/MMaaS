<?php

use App\Http\Controllers\Api\RouteSearchController;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

$redirectAuthenticatedUserByRole = function (User $user) {
    return match ($user->role) {
        'superadmin' => redirect('/user'),
        'subadmin', 'employee' => redirect('/panel'),
        'customer' => redirect('/portal'),
        default => redirect('/'),
    };
};

// Unified login - redirect to user login (which accepts all users)
Route::get('/login', function () use ($redirectAuthenticatedUserByRole) {
    // If already authenticated, redirect to appropriate panel
    if (Auth::check()) {
        /** @var User $user */
        $user = Auth::user();

        return $redirectAuthenticatedUserByRole($user);
    }

    // Redirect to user panel login
    return redirect('/user/login');
})->name('login');

// Unified registration - redirect to user registration
Route::get('/register', fn () => redirect('/user/register'))->name('register');

// Dashboard route - redirect to appropriate panel based on role
Route::get('/dashboard', function () use ($redirectAuthenticatedUserByRole) {
    if (Auth::check()) {
        /** @var User $user */
        $user = Auth::user();

        return $redirectAuthenticatedUserByRole($user);
    }

    return redirect('/login');
})->name('dashboard');

Route::middleware('auth')->get('/locale/toggle', function () {
    /** @var User $user */
    $user = Auth::user();

    $user->update([
        'locale' => ($user->locale ?? 'en') === 'en' ? 'fr' : 'en',
    ]);

    return redirect()->back();
})->name('locale.toggle');

// Public API routes for route search
Route::prefix('api')->group(function () {
    Route::get('/stops/search', [RouteSearchController::class, 'searchStops']);
    Route::post('/routes/search', [RouteSearchController::class, 'searchRoutes']);
    Route::get('/territories', [RouteSearchController::class, 'getTerritories']);
    Route::get('/territories/routes/all-shapes', [RouteSearchController::class, 'getRoutesWithShapesByTerritory']);
    Route::get('/routes/all-shapes', [RouteSearchController::class, 'getAllRoutesWithShapes']);
    Route::get('/routes/{routeName}/stops', [RouteSearchController::class, 'getRouteStops']);
});
