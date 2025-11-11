<?php

namespace App\Filament\Superadmin\Resources\Regions\Pages;

use App\Filament\Superadmin\Resources\Regions\RegionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageRegions extends ManageRecords
{
    protected static string $resource = RegionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Add Location')
                ->createAnother(false),
        ];
    }
}
