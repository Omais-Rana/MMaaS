<?php

namespace App\Filament\SubAdmin\Resources\ScheduleImports\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class StopsRelationManager extends RelationManager
{
    protected static string $relationship = 'stops';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('stop_code')
                    ->label(__('schedules.stop_code'))
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                TextColumn::make('stop_name')
                    ->label(__('schedules.stop_name'))
                    ->searchable()
                    ->sortable()
                    ->wrap()
                    ->weight('medium'),
                TextColumn::make('stop_lat')
                    ->label(__('schedules.stop_lat'))
                    ->numeric(decimalPlaces: 6)
                    ->copyable()
                    ->copyMessage(__('filament-actions::copy.single.messages.copied'))
                    ->alignEnd(),
                TextColumn::make('stop_lon')
                    ->label(__('schedules.stop_lon'))
                    ->numeric(decimalPlaces: 6)
                    ->copyable()
                    ->copyMessage(__('filament-actions::copy.single.messages.copied'))
                    ->alignEnd(),
                TextColumn::make('wheelchair_boarding')
                    ->label(__('schedules.stop_wheelchair'))
                    ->badge()
                    ->formatStateUsing(fn (?int $state): string => match ($state) {
                        1 => __('schedules.wheelchair_yes'),
                        0 => __('schedules.wheelchair_no'),
                        default => __('schedules.wheelchair_unknown'),
                    })
                    ->color(fn (?int $state): string => match ($state) {
                        1 => 'success',
                        0 => 'danger',
                        default => 'gray',
                    }),
            ])
            ->defaultSort('stop_name')
            ->striped()
            ->paginated([10, 25, 50]);
    }
}
