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
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Builder;

class RoleResource extends Resource
{
    protected static ?string $model = Role::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-shield-check';

    protected static ?string $navigationLabel = 'Roles & Permissions';

    protected static string|\UnitEnum|null $navigationGroup = 'Access Control';

    // Permission checks
    public static function canViewAny(): bool
    {
        return Auth::user()->can('view_roles');
    }

    public static function canCreate(): bool
    {
        return Auth::user()->can('create_roles');
    }

    public static function canEdit($record): bool
    {
        // Employees cannot edit the Administrator role
        if (Auth::user()->role === 'employee' && $record->name === 'Administrator') {
            return false;
        }
        return Auth::user()->can('edit_roles');
    }

    public static function canDelete($record): bool
    {
        // Employees cannot delete the Administrator role
        if (Auth::user()->role === 'employee' && $record->name === 'Administrator') {
            return false;
        }
        return Auth::user()->can('delete_roles');
    }

    public static function canDeleteAny(): bool
    {
        return Auth::user()->can('delete_roles');
    }

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

                        Forms\Components\Hidden::make('tenant_id')
                            ->default(function () {
                                // Sub-admins set their own ID as tenant
                                // Employees inherit their tenant_id
                                return Auth::user()->role === 'subadmin'
                                    ? Auth::id()
                                    : Auth::user()->tenant_id;
                            }),
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
                    ->label('Role Name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('permissions_count')
                    ->counts('permissions')
                    ->label('Permissions')
                    ->badge()
                    ->color('success')
                    ->sortable(),

                Tables\Columns\TextColumn::make('permissions.name')
                    ->label('Permission Details')
                    ->badge()
                    ->separator(',')
                    ->limit(3)
                    ->searchable()
                    ->toggleable()
                    ->wrap(),

                Tables\Columns\TextColumn::make('users_count')
                    ->counts('users')
                    ->label('Assigned Users')
                    ->badge()
                    ->color('info')
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

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        $user = Auth::user();

        // Scope roles by tenant
        if ($user->role === 'subadmin') {
            // Sub-admins see Administrator role (null tenant_id) and their own roles
            $query->where(function ($q) use ($user) {
                $q->whereNull('tenant_id') // Administrator role
                    ->orWhere('tenant_id', $user->id); // Their own roles
            });
        } elseif ($user->role === 'employee') {
            // Employees see only roles from their tenant (not Administrator)
            $query->where('tenant_id', $user->tenant_id)
                ->where('name', '!=', 'Administrator');
        }

        return $query;
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
