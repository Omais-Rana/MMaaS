<?php

namespace App\Filament\Superadmin\Resources\SubAdminResource\Pages;

use App\Filament\Superadmin\Resources\SubAdminResource;
use App\Models\User;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateSubAdmin extends CreateRecord
{
    protected static string $resource = SubAdminResource::class;

    protected static bool $canCreateAnother = false;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        /** @var User|null $user */
        $user = Auth::user();

        if ($user?->role === 'superadmin') {
            $data['tenant_id'] = $user->id;
        }

        return $data;
    }

    protected function afterCreate(): void
    {
        // Automatically assign Administrator role to new sub-admins
        $this->record->assignRole('Administrator');
    }
}
