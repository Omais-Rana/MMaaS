<?php

namespace App\Filament\Superadmin\Resources\Regions;

use App\Filament\Superadmin\Resources\Regions\Pages\ManageRegions;
use App\Models\Region;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class RegionResource extends Resource
{
    protected static ?string $model = Region::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMapPin;

    protected static ?string $navigationLabel = 'Locations';

    protected static ?string $modelLabel = 'Location';

    protected static ?string $pluralModelLabel = 'Locations';

    protected static ?int $navigationSort = 4;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                \Filament\Forms\Components\Select::make('department_id')
                    ->label('Department / Province')
                    ->relationship('department', 'name')
                    ->searchable()
                    ->preload()
                    ->required()
                    ->createOptionForm([
                        TextInput::make('name')
                            ->label('Department Name')
                            ->required()
                            ->unique()
                            ->maxLength(255),
                    ])
                    ->createOptionUsing(function (array $data) {
                        return \App\Models\Department::create($data)->id;
                    }),

                TextInput::make('name')
                    ->label('City')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(255),

                TextInput::make('postal_code')
                    ->label('Postal Code')
                    ->required()
                    ->maxLength(20),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('City')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('postal_code')
                    ->label('Postal Code')
                    ->searchable()
                    ->sortable()
                    ->placeholder('—'),

                TextColumn::make('department.name')
                    ->label('Department')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('users_count')
                    ->label('Operators')
                    ->counts('users')
                    ->sortable(),

                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('name');
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageRegions::route('/'),
        ];
    }
}
