<?php

namespace App\Filament\Superadmin\Resources\SubAdminResource\Pages;

use App\Filament\Superadmin\Resources\SubAdminResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditSubAdmin extends EditRecord
{
    protected static string $resource = SubAdminResource::class;

    protected array $locations = [];

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Store locations temporarily
        $this->locations = $data['locations'] ?? [];

        // Remove locations field as it's handled by the relationship
        unset($data['locations']);

        return $data;
    }

    protected function afterSave(): void
    {
        // Sync regions (specific cities)
        $regionIds = [];
        foreach ($this->locations as $location) {
            if (str_starts_with($location, 'region_')) {
                $regionIds[] = (int) str_replace('region_', '', $location);
            }
        }

        $this->record->regions()->sync($regionIds);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
