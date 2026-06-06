<?php

namespace App\Filament\SubAdmin\Resources\GbfsSources;

use App\Filament\SubAdmin\Resources\GbfsSources\Pages\CreateGbfsSource;
use App\Filament\SubAdmin\Resources\GbfsSources\Pages\EditGbfsSource;
use App\Filament\SubAdmin\Resources\GbfsSources\Pages\ListGbfsSources;
use App\Filament\SubAdmin\Resources\GbfsSources\Schemas\GbfsSourceForm;
use App\Filament\SubAdmin\Resources\GbfsSources\Tables\GbfsSourcesTable;
use App\Models\GbfsSource;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class GbfsSourceResource extends Resource
{
    protected static ?string $model = GbfsSource::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return GbfsSourceForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return GbfsSourcesTable::configure($table);
    }
    public static function getRelations(): array
    {
        return [
            RelationManagers\StationsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListGbfsSources::route('/'),
            'create' => CreateGbfsSource::route('/create'),
            'edit' => EditGbfsSource::route('/{record}/edit'),
        ];
    }
}
