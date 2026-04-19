<?php

namespace App\Filament\Customer\Pages\Auth;

use DiogoGPinto\AuthUIEnhancer\Pages\Auth\Concerns\HasCustomLayout;
use Filament\Auth\Pages\Register as BaseRegister;
use Illuminate\Contracts\Support\Htmlable;

class Register extends BaseRegister
{
    use HasCustomLayout;

    public function getHeading(): string|Htmlable
    {
        return 'Create your account';
    }

    public function getTitle(): string|Htmlable
    {
        return 'Sign Up';
    }

    protected function getRedirectUrl(): string
    {
        return '/portal';
    }

    protected function mutateFormDataBeforeRegister(array $data): array
    {
        // Automatically set role to customer
        $data['role'] = 'customer';

        // Set is_active to true by default
        $data['is_active'] = true;

        return $data;
    }
}
