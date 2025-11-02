<?php

namespace App\Filament\SubAdmin\Resources\ScheduleImports\RelationManagers;

use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class TripsRelationManager extends RelationManager
{
    protected static string $relationship = 'trips';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('trip_id')
                    ->label(__('schedules.trip_id'))
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true)
                    ->columnSpanFull(),

                TextInput::make('trip_headsign')
                    ->label(__('schedules.trip_headsign'))
                    ->required()
                    ->maxLength(255),

                TextInput::make('trip_short_name')
                    ->label('Trip Short Name')
                    ->maxLength(255),

                TextInput::make('service_id')
                    ->label(__('schedules.trip_service'))
                    ->required()
                    ->maxLength(255),

                Select::make('direction_id')
                    ->label(__('schedules.trip_direction'))
                    ->options([
                        0 => __('schedules.direction_outbound'),
                        1 => __('schedules.direction_inbound'),
                    ])
                    ->native(false),

                Select::make('wheelchair_accessible')
                    ->label(__('schedules.trip_wheelchair'))
                    ->options([
                        0 => 'No information',
                        1 => __('schedules.wheelchair_yes'),
                        2 => __('schedules.wheelchair_no'),
                    ])
                    ->default(0)
                    ->native(false),
            ]);
    }

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
                    ->size('lg')
                    ->width('100px'),
                TextColumn::make('trip_headsign')
                    ->label(__('schedules.trip_destination'))
                    ->searchable()
                    ->sortable()
                    ->wrap()
                    ->placeholder('N/A')
                    ->limit(50),
                TextColumn::make('service_id')
                    ->label(__('schedules.trip_service'))
                    ->searchable()
                    ->sortable()
                    ->wrap()
                    ->width('200px'),
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
                    })
                    ->width('120px')
                    ->alignCenter(),
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
                    })
                    ->width('150px')
                    ->alignCenter(),
            ])
            ->defaultSort('route.route_short_name')
            ->striped()
            ->paginated([10, 25, 50])
            ->headerActions([
                // Create action removed - use "Add new record" button in header
            ])
            ->recordActions([
                EditAction::make()
                    ->icon('heroicon-s-pencil'),
                DeleteAction::make()
                    ->icon('heroicon-s-trash'),
            ])
            ->bulkActions([
                DeleteBulkAction::make(),
            ]);
    }
}
