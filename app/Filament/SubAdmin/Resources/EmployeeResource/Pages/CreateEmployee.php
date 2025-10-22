<?php

namespace App\Filament\SubAdmin\Resources\EmployeeResource\Pages;

use App\Filament\SubAdmin\Resources\EmployeeResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateEmployee extends CreateRecord
{
    protected static string $resource = EmployeeResource::class;

    protected static bool $canCreateAnother = false;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Ensure tenant_id is set correctly based on who's creating
        if (Auth::user()->role === 'subadmin') {
            $data['tenant_id'] = Auth::id();
        } else {
            // Employees creating other employees use their own tenant_id
            $data['tenant_id'] = Auth::user()->tenant_id;
        }

        return $data;
    }
}
