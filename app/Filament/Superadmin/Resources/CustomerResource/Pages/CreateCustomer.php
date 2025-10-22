<?php

namespace App\Filament\Superadmin\Resources\CustomerResource\Pages;

use App\Filament\Superadmin\Resources\CustomerResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCustomer extends CreateRecord
{
    protected static string $resource = CustomerResource::class;

    protected static bool $canCreateAnother = false;
}
