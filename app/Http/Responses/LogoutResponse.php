<?php

namespace App\Http\Responses;

use Filament\Auth\Http\Responses\Contracts\LogoutResponse as LogoutResponseContract;

class LogoutResponse implements LogoutResponseContract
{
    public function toResponse($request)
    {
        // Redirect to unified login page after logout
        return redirect()->to('/login');
    }
}
