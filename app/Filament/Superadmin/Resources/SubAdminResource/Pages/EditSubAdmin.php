<?php

namespace App\Filament\Superadmin\Resources\SubAdminResource\Pages;

use App\Filament\Superadmin\Resources\SubAdminResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditSubAdmin extends EditRecord
{
    protected static string $resource = SubAdminResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Preserve owner superadmin linkage.
        $data['tenant_id'] = $this->record->tenant_id;

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
