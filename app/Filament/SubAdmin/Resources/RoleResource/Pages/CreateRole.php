<?php

namespace App\Filament\SubAdmin\Resources\RoleResource\Pages;

use App\Filament\SubAdmin\Resources\RoleResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateRole extends CreateRecord
{
    protected static string $resource = RoleResource::class;

    protected static bool $canCreateAnother = false;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Ensure tenant_id is set correctly
        if (Auth::user()->role === 'subadmin') {
            $data['tenant_id'] = Auth::id();
        } else {
            // Employees creating roles use their own tenant_id
            $data['tenant_id'] = Auth::user()->tenant_id;
        }

        return $data;
    }
}
