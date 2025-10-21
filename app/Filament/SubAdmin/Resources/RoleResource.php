<?php

namespace App\Filament\SubAdmin\Resources;

use App\Filament\SubAdmin\Resources\RoleResource\Pages;
use BackedEnum;
use Filament\Forms;
use Filament\Schemas\Components\Section;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;

class RoleResource extends Resource
{
    protected static ?string $model = Role::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-shield-check';

    protected static ?string $navigationLabel = 'Roles';

    protected static string|\UnitEnum|null $navigationGroup = 'Access Control';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Role Information')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255)
                            ->helperText('Enter a unique role name (e.g., Manager, Editor)')
                            ->columnSpanFull(),
                    ]),

                Section::make('Permissions')
                    ->description('Select permissions for this role, organized by module')
                    ->schema([
                        Forms\Components\CheckboxList::make('permissions')
                            ->relationship('permissions', 'name')
                            ->searchable()
                            ->bulkToggleable()
                            ->columns(2)
                            ->gridDirection('row')
                            ->options(function () {
                                // Group permissions by module
                                $permissions = Permission::all()->groupBy(function ($permission) {
                                    // Extract module from permission name (e.g., 'view_employees' -> 'Employees')
                                    $parts = explode('_', $permission->name);
                                    if (count($parts) > 1) {
                                        $module = ucfirst($parts[1]);
                                        // Handle special cases
                                        if (in_array($parts[1], ['roles', 'permissions'])) {
                                            return 'Access Control';
                                        }
                                        return $module;
                                    }
                                    return 'Other';
                                });

                                // Build grouped options with module prefix
                                $options = [];
                                foreach ($permissions as $module => $perms) {
                                    foreach ($perms as $permission) {
                                        $options[$permission->id] = $module . ': ' . str_replace('_', ' ', ucwords($permission->name, '_'));
                                    }
                                }
                                return $options;
                            })
                            ->helperText('Check the permissions you want to assign to this role'),
                    ])
                    ->collapsible(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('permissions.name')
                    ->label('Permissions')
                    ->badge()
                    ->searchable(),

                Tables\Columns\TextColumn::make('users_count')
                    ->counts('users')
                    ->label('Users')
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
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

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRoles::route('/'),
            'create' => Pages\CreateRole::route('/create'),
            'edit' => Pages\EditRole::route('/{record}/edit'),
        ];
    }
}
