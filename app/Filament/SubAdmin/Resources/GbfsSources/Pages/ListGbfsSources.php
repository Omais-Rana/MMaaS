<?php

namespace App\Filament\SubAdmin\Resources\GbfsSources\Pages;

use App\Filament\SubAdmin\Resources\GbfsSources\GbfsSourceResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListGbfsSources extends ListRecords
{
    protected static string $resource = GbfsSourceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
