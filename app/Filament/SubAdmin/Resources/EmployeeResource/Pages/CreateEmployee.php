<?php

namespace App\Filament\SubAdmin\Resources\EmployeeResource\Pages;

use App\Filament\SubAdmin\Resources\EmployeeResource;
use Filament\Resources\Pages\CreateRecord;

class CreateEmployee extends CreateRecord
{
    protected static string $resource = EmployeeResource::class;
}
