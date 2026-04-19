<?php

use App\Http\Controllers\Api\RouteSearchController;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Unified login - redirect to user login (which accepts all users)
Route::get('/login', function () {
    // If already authenticated, redirect to appropriate panel
    if (Auth::check()) {
        /** @var User $user */
        $user = Auth::user();

        return match ($user->role) {
            'superadmin' => redirect('/user'),
            'subadmin', 'employee' => redirect('/panel'),
            'customer' => redirect('/portal'),
            default => redirect('/'),
        };
    }

    // Redirect to user panel login
    return redirect('/user/login');
})->name('login');

// Unified registration - redirect to user registration
Route::get('/register', fn() => redirect('/user/register'))->name('register');

// Dashboard route - redirect to appropriate panel based on role
Route::get('/dashboard', function () {
    if (Auth::check()) {
        /** @var User $user */
        $user = Auth::user();

        return match ($user->role) {
            'superadmin' => redirect('/user'),
            'subadmin', 'employee' => redirect('/panel'),
            'customer' => redirect('/portal'),
            default => redirect('/'),
        };
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

// Redirect panel-specific logins to unified login
Route::get('/panel/login', fn() => redirect('/login'));
Route::get('/portal/login', fn() => redirect('/login'));

// Public API routes for route search
Route::prefix('api')->group(function () {
    Route::get('/stops/search', [RouteSearchController::class, 'searchStops']);
    Route::post('/routes/search', [RouteSearchController::class, 'searchRoutes']);
    Route::get('/territories', [RouteSearchController::class, 'getTerritories']);
    Route::get('/territories/routes/all-shapes', [RouteSearchController::class, 'getRoutesWithShapesByTerritory']);
    Route::get('/routes/all-shapes', [RouteSearchController::class, 'getAllRoutesWithShapes']);
    Route::get('/routes/{routeName}/stops', [RouteSearchController::class, 'getRouteStops']);
});
