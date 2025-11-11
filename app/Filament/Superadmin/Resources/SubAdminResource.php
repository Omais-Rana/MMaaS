<?php

namespace App\Filament\Superadmin\Resources;

use App\Filament\Superadmin\Resources\SubAdminResource\Pages;
use App\Models\User;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Hash;

class SubAdminResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-wrench-screwdriver';

    protected static ?string $navigationLabel = 'Operators';

    protected static ?string $modelLabel = 'Operator';

    protected static ?string $pluralModelLabel = 'Operators';

    protected static ?string $recordTitleAttribute = 'name';

    public static function getGlobalSearchResultTitle($record): string
    {
        return $record->name;
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'email'];
    }

    public static function getGlobalSearchResultDetails($record): array
    {
        return [
            'Email' => $record->email,
            'Status' => $record->is_active ? 'Active' : 'Inactive',
        ];
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Operator Information')
                    ->maxWidth('md')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Service Name')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\TextInput::make('email')
                            ->email()
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255),

                        Forms\Components\Select::make('departments')
                            ->label('Department(s)')
                            ->multiple()
                            ->relationship('departments', 'name')
                            ->searchable()
                            ->preload()
                            ->live()
                            ->helperText('Select one or more departments this operator serves')
                            ->required()
                            ->createOptionForm([
                                Forms\Components\TextInput::make('name')
                                    ->label('Department Name')
                                    ->required()
                                    ->unique()
                                    ->maxLength(255),
                            ])
                            ->createOptionUsing(function (array $data) {
                                return \App\Models\Department::create($data)->id;
                            }),

                        Forms\Components\Select::make('locations')
                            ->label('Location(s)')
                            ->multiple()
                            ->required()
                            ->options(function ($get) {
                                $departments = $get('departments');
                                if (! $departments) {
                                    return [];
                                }

                                $options = [];

                                // Add "All" option for each selected department
                                foreach (\App\Models\Department::whereIn('id', $departments)->get() as $dept) {
                                    $options["dept_{$dept->id}"] = "All of {$dept->name}";
                                }

                                // Add individual cities from selected departments
                                $regions = \App\Models\Region::whereIn('department_id', $departments)->get();
                                foreach ($regions as $region) {
                                    $label = $region->postal_code
                                        ? "{$region->name} - {$region->postal_code}"
                                        : $region->name;
                                    $options["region_{$region->id}"] = $label;
                                }

                                return $options;
                            })
                            ->searchable()
                            ->preload()
                            ->helperText('Select "All of [Department]" to serve entire department, or select specific cities')
                            ->afterStateHydrated(function ($component, $state, $record) {
                                if (! $record) {
                                    return;
                                }

                                $values = [];

                                // Add department selections (if serving entire department)
                                foreach ($record->departments as $dept) {
                                    // Check if user serves entire department (has no specific cities from this dept)
                                    $hasSpecificCities = $record->regions()->where('department_id', $dept->id)->exists();
                                    if (! $hasSpecificCities) {
                                        $values[] = "dept_{$dept->id}";
                                    }
                                }

                                // Add specific region selections
                                foreach ($record->regions as $region) {
                                    $values[] = "region_{$region->id}";
                                }

                                $component->state($values);
                            }),
                        // Note: Removed ->dehydrated(false) to allow form submission of location data

                        Forms\Components\TextInput::make('password')
                            ->password()
                            ->dehydrateStateUsing(fn($state) => Hash::make($state))
                            ->dehydrated(fn($state) => filled($state))
                            ->required(fn(string $context): bool => $context === 'create')
                            ->maxLength(255)
                            ->helperText(fn(string $context): ?string => $context === 'edit' ? 'Leave blank to keep current password' : null),

                        Forms\Components\Hidden::make('role')
                            ->default('subadmin'),

                        Forms\Components\Toggle::make('is_active')
                            ->label('Active')
                            ->default(true)
                            ->required(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Service')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('email')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('departments.name')
                    ->label('Departments')
                    ->badge()
                    ->separator(',')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('service_area')
                    ->label('Service Area')
                    ->getStateUsing(function ($record) {
                        $areas = [];

                        // Load relationships
                        $record->load(['departments', 'regions']);

                        // Check each department
                        foreach ($record->departments as $dept) {
                            $specificCities = $record->regions->where('department_id', $dept->id);

                            if ($specificCities->isEmpty()) {
                                // Serving entire department
                                $areas[] = "All of {$dept->name}";
                            } else {
                                // Serving specific cities
                                foreach ($specificCities as $city) {
                                    $areas[] = $city->postal_code
                                        ? "{$city->name} - {$city->postal_code}"
                                        : $city->name;
                                }
                            }
                        }

                        return ! empty($areas) ? implode(', ', $areas) : '—';
                    })
                    ->wrap()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('employees_count')
                    ->counts('employees')
                    ->label('Employees')
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active Status')
                    ->placeholder('All')
                    ->trueLabel('Active')
                    ->falseLabel('Inactive'),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('role', 'subadmin')
            ->with(['departments', 'regions']);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSubAdmins::route('/'),
            'create' => Pages\CreateSubAdmin::route('/create'),
            'edit' => Pages\EditSubAdmin::route('/{record}/edit'),
        ];
    }
}
