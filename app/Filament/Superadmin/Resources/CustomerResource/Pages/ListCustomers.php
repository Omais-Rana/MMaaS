<?php

namespace App\Filament\Superadmin\Resources\CustomerResource\Pages;

use App\Filament\Superadmin\Resources\CustomerResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCustomers extends ListRecords
{
    protected static string $resource = CustomerResource::class;

    protected static ?string $title = 'Manage Customers';

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Add Customer')
                ->createAnother(false),
        ];
    }
}
