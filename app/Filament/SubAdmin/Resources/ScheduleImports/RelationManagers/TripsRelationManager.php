<?php

namespace App\Filament\SubAdmin\Resources\ScheduleImports\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class TripsRelationManager extends RelationManager
{
    protected static string $relationship = 'trips';

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function ($query) {
                // Remove the global scope and re-apply it with qualified table name
                // to avoid ambiguity when joining with routes table
                $query->withoutGlobalScope('tenant')
                    ->where('trips.tenant_id', Auth::user()->isSubAdmin()
                        ? Auth::id()
                        : Auth::user()->tenant_id);
            })
            ->columns([
                TextColumn::make('route.route_short_name')
                    ->label(__('schedules.trip_line'))
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->size('lg'),
                TextColumn::make('trip_headsign')
                    ->label(__('schedules.trip_destination'))
                    ->searchable()
                    ->sortable()
                    ->wrap()
                    ->limit(50),
                TextColumn::make('service_id')
                    ->label(__('schedules.trip_service'))
                    ->searchable()
                    ->sortable()
                    ->wrap(),
                TextColumn::make('direction_id')
                    ->label(__('schedules.trip_direction'))
                    ->badge()
                    ->formatStateUsing(fn (?int $state): string => match ($state) {
                        0 => __('schedules.direction_outbound'),
                        1 => __('schedules.direction_inbound'),
                        default => __('schedules.direction_na'),
                    })
                    ->color(fn (?int $state): string => match ($state) {
                        0 => 'info',
                        1 => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('wheelchair_accessible')
                    ->label(__('schedules.trip_wheelchair'))
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
            ->defaultSort('route.route_short_name')
            ->striped()
            ->paginated([10, 25, 50]);
    }
}
