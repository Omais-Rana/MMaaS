<?php

namespace App\Filament\SubAdmin\Resources\ScheduleImports\RelationManagers;

use App\Models\Stop;
use App\Models\Trip;
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

class StopTimesRelationManager extends RelationManager
{
    protected static string $relationship = 'stopTimes';

    protected static ?string $title = 'Stop Times';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('trip_id')
                    ->label('Trip')
                    ->relationship('trip', 'trip_headsign', function ($query) {
                        // Get trips for the same schedule import
                        $scheduleImportId = $this->getOwnerRecord()->id;

                        return $query->where('schedule_import_id', $scheduleImportId)
                            ->orderBy('trip_headsign');
                    })
                    ->searchable()
                    ->preload()
                    ->required()
                    ->getOptionLabelFromRecordUsing(fn(Trip $record) => "{$record->trip_headsign} ({$record->trip_id})")
                    ->columnSpanFull(),

                Select::make('stop_id')
                    ->label('Stop')
                    ->relationship('stop', 'stop_name', function ($query) {
                        // Get stops for the same schedule import
                        $scheduleImportId = $this->getOwnerRecord()->id;

                        return $query->where('schedule_import_id', $scheduleImportId)
                            ->orderBy('stop_name');
                    })
                    ->searchable()
                    ->preload()
                    ->required()
                    ->getOptionLabelFromRecordUsing(fn(Stop $record) => "{$record->stop_name} ({$record->stop_id})")
                    ->columnSpanFull(),

                TextInput::make('arrival_time')
                    ->label('Arrival Time')
                    ->required()
                    ->placeholder('HH:MM:SS')
                    ->helperText('Format: HH:MM:SS (e.g., 08:30:00)')
                    ->maxLength(8)
                    ->regex('/^\d{2}:\d{2}:\d{2}$/'),

                TextInput::make('departure_time')
                    ->label('Departure Time')
                    ->required()
                    ->placeholder('HH:MM:SS')
                    ->helperText('Format: HH:MM:SS (e.g., 08:30:00)')
                    ->maxLength(8)
                    ->regex('/^\d{2}:\d{2}:\d{2}$/'),

                TextInput::make('stop_sequence')
                    ->label('Stop Sequence')
                    ->required()
                    ->numeric()
                    ->minValue(0)
                    ->helperText('Order of stops in the trip (0, 1, 2, ...)'),

                TextInput::make('stop_headsign')
                    ->label('Stop Headsign')
                    ->maxLength(255)
                    ->helperText('Optional: Text that appears on signage'),

                Select::make('pickup_type')
                    ->label('Pickup Type')
                    ->options([
                        0 => 'Regular pickup',
                        1 => 'No pickup available',
                        2 => 'Must phone agency',
                        3 => 'Must coordinate with driver',
                    ])
                    ->default(0)
                    ->native(false),

                Select::make('drop_off_type')
                    ->label('Drop Off Type')
                    ->options([
                        0 => 'Regular drop off',
                        1 => 'No drop off available',
                        2 => 'Must phone agency',
                        3 => 'Must coordinate with driver',
                    ])
                    ->default(0)
                    ->native(false),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function ($query) {
                // Qualify the tenant_id column to avoid ambiguity
                // since both stop_times and trips tables have tenant_id
                $query->withoutGlobalScope('tenant')
                    ->where('stop_times.tenant_id', Auth::user()->isSubAdmin()
                        ? Auth::id()
                        : Auth::user()->tenant_id);
            })
            ->columns([
                TextColumn::make('trip.trip_headsign')
                    ->label('Trip')
                    ->searchable()
                    ->sortable()
                    ->wrap()
                    ->limit(30)
                    ->weight('bold'),

                TextColumn::make('stop_sequence')
                    ->label('Seq')
                    ->sortable()
                    ->alignCenter()
                    ->width('60px'),

                TextColumn::make('stop.stop_name')
                    ->label('Stop Name')
                    ->searchable()
                    ->sortable()
                    ->wrap()
                    ->limit(30),

                TextColumn::make('stop.stop_id')
                    ->label('Stop ID')
                    ->searchable()
                    ->sortable()
                    ->width('100px'),

                TextColumn::make('arrival_time')
                    ->label('Arrival')
                    ->sortable()
                    ->alignCenter()
                    ->width('100px'),

                TextColumn::make('departure_time')
                    ->label('Departure')
                    ->sortable()
                    ->alignCenter()
                    ->width('100px'),

                TextColumn::make('stop_headsign')
                    ->label('Headsign')
                    ->wrap()
                    ->limit(30)
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('pickup_type')
                    ->label('Pickup')
                    ->badge()
                    ->formatStateUsing(fn(?int $state): string => match ($state) {
                        0 => 'Regular',
                        1 => 'No pickup',
                        2 => 'Phone',
                        3 => 'Coordinate',
                        default => 'Regular',
                    })
                    ->color(fn(?int $state): string => match ($state) {
                        0 => 'success',
                        1 => 'danger',
                        2 => 'warning',
                        3 => 'info',
                        default => 'gray',
                    })
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('drop_off_type')
                    ->label('Drop Off')
                    ->badge()
                    ->formatStateUsing(fn(?int $state): string => match ($state) {
                        0 => 'Regular',
                        1 => 'No drop off',
                        2 => 'Phone',
                        3 => 'Coordinate',
                        default => 'Regular',
                    })
                    ->color(fn(?int $state): string => match ($state) {
                        0 => 'success',
                        1 => 'danger',
                        2 => 'warning',
                        3 => 'info',
                        default => 'gray',
                    })
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('stop_sequence')
            ->striped()
            ->paginated([10, 25, 50, 100])
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
