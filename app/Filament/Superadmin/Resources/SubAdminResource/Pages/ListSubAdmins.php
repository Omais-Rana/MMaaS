<?php

namespace App\Filament\Superadmin\Resources\SubAdminResource\Pages;

use App\Filament\Superadmin\Resources\SubAdminResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListSubAdmins extends ListRecords
{
    protected static string $resource = SubAdminResource::class;

    protected static ?string $title = 'Manage Operators';

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Add Operator')
                ->createAnother(false),
        ];
    }
}
