<?php

namespace App\Http\Responses;

use App\Models\User;
use Filament\Auth\Http\Responses\Contracts\LoginResponse as LoginResponseContract;
use Illuminate\Support\Facades\Auth;

class LoginResponse implements LoginResponseContract
{
    public function toResponse($request)
    {
        /** @var User $user */
        $user = Auth::user();

        // Redirect based on user role
        $redirectUrl = match ($user->role) {
            'superadmin' => '/user',
            'subadmin', 'employee' => '/panel',
            'customer' => '/portal',
            default => '/',
        };

        // Use redirect()->to() which works better with Livewire
        return redirect()->to($redirectUrl);
    }
}
