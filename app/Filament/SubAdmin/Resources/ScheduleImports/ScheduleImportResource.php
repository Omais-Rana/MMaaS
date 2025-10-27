<?php

namespace App\Filament\SubAdmin\Resources\ScheduleImports;

use App\Filament\SubAdmin\Resources\ScheduleImports\Pages\CreateScheduleImport;
use App\Filament\SubAdmin\Resources\ScheduleImports\Pages\ListScheduleImports;
use App\Filament\SubAdmin\Resources\ScheduleImports\Schemas\ScheduleImportForm;
use App\Filament\SubAdmin\Resources\ScheduleImports\Tables\ScheduleImportsTable;
use App\Models\ScheduleImport;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ScheduleImportResource extends Resource
{
    protected static ?string $model = ScheduleImport::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCloudArrowUp;

    public static function getNavigationLabel(): string
    {
        return __('schedules.navigation_label');
    }

    public static function getModelLabel(): string
    {
        return __('schedules.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('schedules.plural_label');
    }

    public static function form(Schema $schema): Schema
    {
        return ScheduleImportForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ScheduleImportsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\RoutesRelationManager::class,
            RelationManagers\StopsRelationManager::class,
            RelationManagers\TripsRelationManager::class,
            RelationManagers\CalendarsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListScheduleImports::route('/'),
            'create' => CreateScheduleImport::route('/create'),
            'view' => Pages\ViewScheduleImport::route('/{record}'),
        ];
    }
}
