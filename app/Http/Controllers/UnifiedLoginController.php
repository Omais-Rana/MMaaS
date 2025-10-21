<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class UnifiedLoginController extends Controller
{
    public function showLoginForm()
    {
        return view('auth.unified-login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            $request->session()->regenerate();

            $user = Auth::user();

            // Redirect based on role
            return match ($user->role) {
                'superadmin' => redirect()->intended('/superadmin'),
                'subadmin', 'employee' => redirect()->intended('/subadmin'),
                'customer' => redirect()->intended('/customer'),
                default => redirect('/'),
            };
        }

        return back()->withErrors([
            'email' => 'The provided credentials do not match our records.',
        ])->onlyInput('email');
    }
}
