<?php

namespace App\Filament\SubAdmin\Resources\GbfsSources\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class GbfsSourcesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'cycle' => 'warning',
                        'car' => 'info',
                        default => 'gray',
                    })
                    ->icon(fn (string $state): \BackedEnum => match ($state) {
                        'car' => Heroicon::Truck,
                        default => Heroicon::Sun,
                    })
                    ->searchable(),
                TextColumn::make('url')
                    ->limit(30)
                    ->tooltip(function (TextColumn $column): ?string {
                        return $column->getState();
                    }),
                IconColumn::make('is_active')
                    ->boolean(),
                TextColumn::make('stations_count')
                    ->counts('stations')
                    ->label('Stations'),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
