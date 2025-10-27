<?php

namespace App\Filament\SubAdmin\Resources\ScheduleImports\Pages;

use App\Filament\SubAdmin\Resources\ScheduleImports\ScheduleImportResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditScheduleImport extends EditRecord
{
    protected static string $resource = ScheduleImportResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
