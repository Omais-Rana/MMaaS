<?php

namespace App\Filament\Customer\Pages\Auth;

use App\Models\User;
use DiogoGPinto\AuthUIEnhancer\Pages\Auth\Concerns\HasCustomLayout;
use Filament\Actions\Action;
use Filament\Auth\Pages\Login as BaseLogin;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Validation\ValidationException;

class Login extends BaseLogin
{
    use HasCustomLayout;

    public function getHeading(): string|Htmlable
    {
        return 'Sign in to your account';
    }

    public function getTitle(): string|Htmlable
    {
        return 'Login';
    }

    public function registerAction(): Action
    {
        return Action::make('register')
            ->link()
            ->label(__('filament-panels::auth/pages/login.actions.register.label'))
            ->url('/user/register');
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
