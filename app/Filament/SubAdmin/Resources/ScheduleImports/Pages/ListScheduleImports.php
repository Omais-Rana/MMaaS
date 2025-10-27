<?php

namespace App\Filament\SubAdmin\Resources\ScheduleImports\Pages;

use App\Filament\SubAdmin\Resources\ScheduleImports\ScheduleImportResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListScheduleImports extends ListRecords
{
    protected static string $resource = ScheduleImportResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
