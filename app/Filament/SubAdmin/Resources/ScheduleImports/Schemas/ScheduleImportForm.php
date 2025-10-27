<?php

namespace App\Filament\SubAdmin\Resources\ScheduleImports\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Schemas\Schema;

class ScheduleImportForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                FileUpload::make('file')
                    ->label(__('schedules.file_label'))
                    ->helperText(__('schedules.file_help'))
                    ->acceptedFileTypes(['application/zip', 'application/x-rar-compressed', 'application/x-zip-compressed'])
                    ->maxSize(102400) // 100MB
                    ->required()
                    ->disk('local')
                    ->directory('schedule-imports')
                    ->visibility('private')
                    ->storeFileNamesIn('filename')
                    ->columnSpanFull(),
            ]);
    }
}
