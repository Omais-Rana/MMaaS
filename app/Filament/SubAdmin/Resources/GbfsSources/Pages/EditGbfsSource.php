<?php

namespace App\Filament\SubAdmin\Resources\GbfsSources\Pages;

use App\Filament\SubAdmin\Resources\GbfsSources\GbfsSourceResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditGbfsSource extends EditRecord
{
    protected static string $resource = GbfsSourceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
