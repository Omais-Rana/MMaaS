<?php

namespace App\Filament\SubAdmin\Pages\Auth;

use App\Models\User;
use Filament\Auth\Pages\Login as BaseLogin;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Validation\ValidationException;

class Login extends BaseLogin
{
    public function getHeading(): string|Htmlable
    {
        return 'Sign in to your account';
    }

    public function getTitle(): string|Htmlable
    {
        return 'Login';
    }

    protected function throwFailureValidationException(): never
    {
        // Check if a user exists with this email
        $email = $this->data['email'] ?? null;

        if ($email) {
            $user = User::where('email', $email)->first();

            // If user exists but is inactive, show specific error
            if ($user && ! $user->is_active) {
                throw ValidationException::withMessages([
                    'data.email' => 'Your account has been deactivated. Please contact support for assistance.',
                ]);
            }
        }

        // Default credentials error
        parent::throwFailureValidationException();
    }
}
