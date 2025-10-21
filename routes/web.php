<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;

Route::get('/', function () {
    return view('welcome');
});

// Unified login - redirect to user login (which accepts all users)
Route::get('/login', function () {
    // If already authenticated, redirect to appropriate panel
    if (Auth::check()) {
        /** @var \App\Models\User $user */
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

// Redirect panel-specific logins to unified login
Route::get('/panel/login', fn() => redirect('/login'));
Route::get('/portal/login', fn() => redirect('/login'));
