<?php

namespace App\Filament\SubAdmin\Resources\ScheduleImports\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\ColorColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class RoutesRelationManager extends RelationManager
{
    protected static string $relationship = 'routes';

    protected function getRouteTypeDescription(): ?string
    {
        $owner = $this->getOwnerRecord();

        // Get all unique route types for this schedule import
        $routeTypes = $owner->routes()->distinct('route_type')->pluck('route_type');

        // If all routes are the same type, return the description
        if ($routeTypes->count() === 1) {
            $type = (int) $routeTypes->first();

            $typeName = match ($type) {
                0 => __('schedules.type_tram'),
                1 => __('schedules.type_subway'),
                2 => __('schedules.type_rail'),
                3 => __('schedules.type_bus'),
                4 => __('schedules.type_ferry'),
                5 => __('schedules.type_cable_tram'),
                6 => __('schedules.type_aerial_lift'),
                7 => __('schedules.type_funicular'),
                default => 'Unknown',
            };

            return __('schedules.route_type_description', ['type' => $typeName]);
        }

        return null;
    }

    protected function hasMultipleRouteTypes(): bool
    {
        $owner = $this->getOwnerRecord();

        return $owner->routes()->distinct('route_type')->count() > 1;
    }

    public function table(Table $table): Table
    {
        $hasMultipleTypes = $this->hasMultipleRouteTypes();
        $typeDescription = $this->getRouteTypeDescription();

        return $table
            ->heading($typeDescription)
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
                    ->getStateUsing(fn ($record): ?string => $record->route_color ? '#'.$record->route_color : null)
                    ->copyable()
                    ->copyMessage(__('filament-actions::copy.single.messages.copied'))
                    ->placeholder('N/A')
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
                    ->formatStateUsing(fn (string $state): string => match ((int) $state) {
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
                    ->color(fn (string $state): string => match ((int) $state) {
                        0, 1 => 'info',
                        2 => 'warning',
                        3 => 'success',
                        4, 5 => 'primary',
                        6, 7, 11, 12 => 'gray',
                        default => 'danger',
                    })
                    ->width('120px')
                    ->alignCenter()
                    ->visible($hasMultipleTypes),
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
