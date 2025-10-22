<?php

namespace App\Filament\SubAdmin\Resources\EmployeeResource\Pages;

use App\Filament\SubAdmin\Resources\EmployeeResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListEmployees extends ListRecords
{
    protected static string $resource = EmployeeResource::class;
    protected static ?string $title = 'Manage Employees';

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Add Employee')
                ->createAnother(false),
        ];
    }
}
