<?php

namespace App\Filament\SubAdmin\Resources\PermissionResource\Pages;

use App\Filament\SubAdmin\Resources\PermissionResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePermission extends CreateRecord
{
    protected static string $resource = PermissionResource::class;

    protected static bool $canCreateAnother = false;
}
