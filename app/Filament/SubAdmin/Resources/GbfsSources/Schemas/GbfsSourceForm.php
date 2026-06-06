<?php

namespace App\Filament\SubAdmin\Resources\GbfsSources\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class GbfsSourceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                TextInput::make('url')
                    ->label('GBFS JSON URL')
                    ->url()
                    ->required()
                    ->maxLength(255),
                Select::make('type')
                    ->options([
                        'cycle' => 'Cycle (Bikes)',
                        'car' => 'Car (Cars/Vans)',
                    ])
                    ->required(),
                Toggle::make('is_active')
                    ->default(true),
            ]);
    }
}
