<?php

namespace App\Filament\Superadmin\Pages\Auth;

use Filament\Auth\Pages\Login as BaseLogin;
use Illuminate\Contracts\Support\Htmlable;

class Login extends BaseLogin
{
    public function getHeading(): string | Htmlable
    {
        return 'Sign in to your account';
    }

    public function getTitle(): string | Htmlable
    {
        return 'Login';
    }
}
