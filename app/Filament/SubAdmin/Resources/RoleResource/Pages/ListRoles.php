<?php

namespace App\Filament\SubAdmin\Resources\RoleResource\Pages;

use App\Filament\SubAdmin\Resources\RoleResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListRoles extends ListRecords
{
    protected static string $resource = RoleResource::class;
    protected static ?string $title = 'Roles and Permissions';

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Add Role')
                ->icon('heroicon-s-plus-circle')
                ->createAnother(false),
        ];
    }
}
