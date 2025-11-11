<?php

namespace App\Filament\Superadmin\Resources\SubAdminResource\Pages;

use App\Filament\Superadmin\Resources\SubAdminResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Log;

class CreateSubAdmin extends CreateRecord
{
    protected static string $resource = SubAdminResource::class;

    protected static bool $canCreateAnother = false;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Store locations in session temporarily
        if (isset($data['locations'])) {
            session(['pending_locations' => $data['locations']]);
        }

        // Remove locations field as it's handled by the relationship
        unset($data['locations']);

        return $data;
    }

    protected function afterCreate(): void
    {
        // Automatically assign Administrator role to new sub-admins
        $this->record->assignRole('Administrator');

        // Retrieve locations from session
        $locations = session('pending_locations', []);

        Log::info('CreateSubAdmin - Locations data:', ['locations' => $locations]);

        // Process locations and sync regions (specific cities)
        $regionIds = [];

        foreach ($locations as $location) {
            if (str_starts_with($location, 'region_')) {
                $regionId = (int) str_replace('region_', '', $location);
                $regionIds[] = $regionId;
                Log::info('CreateSubAdmin - Adding region ID:', ['region_id' => $regionId]);
            }
        }

        Log::info('CreateSubAdmin - Syncing regions:', ['region_ids' => $regionIds, 'user_id' => $this->record->id]);

        // Sync regions - even if empty array (to clear any existing)
        $this->record->regions()->sync($regionIds);

        Log::info('CreateSubAdmin - After sync, regions count:', ['count' => $this->record->regions()->count()]);

        // Clear session
        session()->forget('pending_locations');
    }
}
