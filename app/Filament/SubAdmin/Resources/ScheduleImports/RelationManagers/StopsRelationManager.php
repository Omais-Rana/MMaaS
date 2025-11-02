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

class StopsRelationManager extends RelationManager
{
    protected static string $relationship = 'stops';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('stop_id')
                    ->label(__('schedules.stop_id'))
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true),

                TextInput::make('stop_code')
                    ->label(__('schedules.stop_code'))
                    ->maxLength(255),

                TextInput::make('stop_name')
                    ->label(__('schedules.stop_name'))
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull(),

                TextInput::make('stop_desc')
                    ->label('Description')
                    ->maxLength(500)
                    ->columnSpanFull(),

                TextInput::make('stop_lat')
                    ->label(__('schedules.stop_lat'))
                    ->required()
                    ->numeric()
                    ->step(0.000001)
                    ->minValue(-90)
                    ->maxValue(90),

                TextInput::make('stop_lon')
                    ->label(__('schedules.stop_lon'))
                    ->required()
                    ->numeric()
                    ->step(0.000001)
                    ->minValue(-180)
                    ->maxValue(180),

                Select::make('wheelchair_boarding')
                    ->label(__('schedules.stop_wheelchair'))
                    ->options([
                        0 => __('schedules.wheelchair_no'),
                        1 => __('schedules.wheelchair_yes'),
                        2 => __('schedules.wheelchair_unknown'),
                    ])
                    ->default(2)
                    ->native(false),

                TextInput::make('stop_url')
                    ->label('Stop URL')
                    ->url()
                    ->maxLength(255)
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('stop_code')
                    ->label(__('schedules.stop_code'))
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->placeholder('N/A')
                    ->width('120px'),
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
                    ->width('140px')
                    ->alignCenter(),
                TextColumn::make('stop_lon')
                    ->label(__('schedules.stop_lon'))
                    ->numeric(decimalPlaces: 6)
                    ->copyable()
                    ->copyMessage(__('filament-actions::copy.single.messages.copied'))
                    ->width('140px')
                    ->alignCenter(),
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
                    })
                    ->width('150px')
                    ->alignCenter(),
            ])
            ->defaultSort('stop_name')
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
