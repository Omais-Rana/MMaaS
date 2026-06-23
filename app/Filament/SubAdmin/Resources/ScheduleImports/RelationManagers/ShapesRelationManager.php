<?php

namespace App\Filament\SubAdmin\Resources\ScheduleImports\RelationManagers;

use Filament\Actions\DeleteBulkAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ShapesRelationManager extends RelationManager
{
    protected static string $relationship = 'shapes';

    public function isReadOnly(): bool
    {
        return true;
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('Shape Points')
            ->description('Individual lat/lon points that define each route shape polygon.')
            ->columns([
                TextColumn::make('shape_id')
                    ->label('Shape ID')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->weight('bold'),
                TextColumn::make('shape_pt_sequence')
                    ->label('Sequence')
                    ->numeric()
                    ->sortable()
                    ->alignCenter()
                    ->width('90px'),
                TextColumn::make('shape_pt_lat')
                    ->label('Latitude')
                    ->numeric(decimalPlaces: 6)
                    ->sortable()
                    ->alignEnd()
                    ->fontFamily('mono'),
                TextColumn::make('shape_pt_lon')
                    ->label('Longitude')
                    ->numeric(decimalPlaces: 6)
                    ->sortable()
                    ->alignEnd()
                    ->fontFamily('mono'),
                TextColumn::make('shape_dist_traveled')
                    ->label('Dist. Traveled')
                    ->numeric(decimalPlaces: 2)
                    ->sortable()
                    ->alignEnd()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('shape_id')
                    ->label('Shape ID')
                    ->options(
                        fn (RelationManager $livewire): array => $livewire->getOwnerRecord()
                            ->shapes()
                            ->distinct()
                            ->orderBy('shape_id')
                            ->pluck('shape_id', 'shape_id')
                            ->toArray()
                    )
                    ->searchable()
                    ->native(false),
            ])
            ->defaultSort('shape_id')
            ->striped()
            ->paginated([25, 50, 100])
            ->bulkActions([
                DeleteBulkAction::make(),
            ]);
    }
}
