<?php

namespace App\Filament\Superadmin\Resources\SubAdminResource\Pages;

use App\Filament\Superadmin\Resources\SubAdminResource;
use Filament\Resources\Pages\CreateRecord;

class CreateSubAdmin extends CreateRecord
{
    protected static string $resource = SubAdminResource::class;

    protected function afterCreate(): void
    {
        // Automatically assign Administrator role to new sub-admins
        $this->record->assignRole('Administrator');
    }
}
