<?php

namespace App\Filament\SubAdmin\Resources\GbfsSources\RelationManagers;

use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class StationsRelationManager extends RelationManager
{
    protected static string $relationship = 'stations';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('station_id')
                    ->searchable()
                    ->sortable()
                    ->label('Station ID'),
                TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->label('Station Name'),
                TextColumn::make('capacity')
                    ->sortable()
                    ->numeric()
                    ->label('Capacity'),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                // Read Only
            ])
            ->recordActions([
                // Read Only
            ])
            ->toolbarActions([
                // Read Only
            ]);
    }
}
