<?php

namespace App\Filament\SubAdmin\Resources\ScheduleImports\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\ColorColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class RoutesRelationManager extends RelationManager
{
    protected static string $relationship = 'routes';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('route_short_name')
                    ->label(__('schedules.route_line'))
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->size('lg')
                    ->width('100px'),
                ColorColumn::make('route_color')
                    ->label(__('schedules.route_color'))
                    ->getStateUsing(fn($record): ?string => $record->route_color ? '#' . $record->route_color : null)
                    ->copyable()
                    ->copyMessage(__('filament-actions::copy.single.messages.copied'))
                    ->width('80px')
                    ->alignCenter(),
                TextColumn::make('route_long_name')
                    ->label(__('schedules.route_destination'))
                    ->searchable()
                    ->sortable()
                    ->wrap()
                    ->limit(60),
                TextColumn::make('route_type')
                    ->label(__('schedules.route_type'))
                    ->badge()
                    ->formatStateUsing(fn(string $state): string => match ((int) $state) {
                        0 => __('schedules.type_tram'),
                        1 => __('schedules.type_subway'),
                        2 => __('schedules.type_rail'),
                        3 => __('schedules.type_bus'),
                        4 => __('schedules.type_ferry'),
                        5 => __('schedules.type_cable_tram'),
                        6 => __('schedules.type_aerial_lift'),
                        7 => __('schedules.type_funicular'),
                        default => 'Unknown',
                    })
                    ->color(fn(string $state): string => match ((int) $state) {
                        0, 1 => 'info',
                        2 => 'warning',
                        3 => 'success',
                        4, 5 => 'primary',
                        6, 7, 11, 12 => 'gray',
                        default => 'danger',
                    })
                    ->width('120px')
                    ->alignCenter(),
                TextColumn::make('trips_count')
                    ->counts('trips')
                    ->label(__('schedules.route_trips_count'))
                    ->sortable()
                    ->width('100px')
                    ->alignCenter(),
            ])
            ->defaultSort('route_short_name')
            ->striped()
            ->paginated([10, 25, 50]);
    }
}
