<?php

namespace App\Filament\SubAdmin\Resources;

use App\Filament\SubAdmin\Resources\EmployeeResource\Pages;
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
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class EmployeeResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $navigationLabel = 'Employees';

    protected static ?string $modelLabel = 'Employee';

    protected static ?string $pluralModelLabel = 'Employees';

    protected static ?int $navigationSort = 2;

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
            'Roles' => $record->roles->pluck('name')->join(', ') ?: 'No roles',
            'Status' => $record->is_active ? 'Active' : 'Inactive',
        ];
    }

    // Permission checks
    public static function canViewAny(): bool
    {
        return Auth::user()->can('view_employees');
    }

    public static function canCreate(): bool
    {
        return Auth::user()->can('create_employees');
    }

    public static function canEdit($record): bool
    {
        // Prevent editing sub-admins (only allow editing employees)
        if ($record->role === 'subadmin') {
            return false;
        }

        return Auth::user()->can('edit_employees');
    }

    public static function canDelete($record): bool
    {
        // Prevent deleting sub-admins (only allow deleting employees)
        if ($record->role === 'subadmin') {
            return false;
        }

        return Auth::user()->can('delete_employees');
    }

    public static function canDeleteAny(): bool
    {
        return Auth::user()->can('delete_employees');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Employee Information')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\TextInput::make('email')
                            ->email()
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255),

                        Forms\Components\TextInput::make('password')
                            ->password()
                            ->dehydrateStateUsing(fn ($state) => Hash::make($state))
                            ->dehydrated(fn ($state) => filled($state))
                            ->required(fn (string $context): bool => $context === 'create')
                            ->maxLength(255)
                            ->helperText('Leave blank to keep current password'),

                        Forms\Components\Hidden::make('role')
                            ->default('employee'),

                        Forms\Components\Hidden::make('tenant_id')
                            ->default(function () {
                                // Sub-admins set their own ID as tenant
                                // Employees inherit their tenant_id
                                return Auth::user()->role === 'subadmin'
                                    ? Auth::id()
                                    : Auth::user()->tenant_id;
                            }),

                        Forms\Components\Toggle::make('is_active')
                            ->label('Active')
                            ->default(true)
                            ->required(),

                        Forms\Components\Select::make('roles')
                            ->relationship(
                                'roles',
                                'name',
                                function ($query) {
                                    $user = Auth::user();

                                    if ($user->role === 'subadmin') {
                                        // Sub-admins see Administrator and their own tenant's roles
                                        return $query->where(function ($q) use ($user) {
                                            $q->whereNull('tenant_id') // Administrator
                                                ->orWhere('tenant_id', $user->id);
                                        });
                                    } else {
                                        // Employees see only their tenant's roles (not Administrator)
                                        return $query->where('tenant_id', $user->tenant_id)
                                            ->where('name', '!=', 'Administrator');
                                    }
                                }
                            )
                            ->multiple()
                            ->preload()
                            ->searchable()
                            ->helperText('Assign roles to this employee'),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('email')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->sortable(),

                Tables\Columns\TextColumn::make('roles.name')
                    ->label('Roles')
                    ->badge()
                    ->searchable(),

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
        // Sub-admins see all employees under them
        // Employees only see other employees (not sub-admins)
        $query = parent::getEloquentQuery();

        if (Auth::user()->role === 'subadmin') {
            // Sub-admins see all their employees
            return $query->where('role', 'employee')
                ->where('tenant_id', Auth::id());
        } else {
            // Employees see only other employees (same tenant)
            return $query->where('role', 'employee')
                ->where('tenant_id', Auth::user()->tenant_id);
        }
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
            'index' => Pages\ListEmployees::route('/'),
            'create' => Pages\CreateEmployee::route('/create'),
            'edit' => Pages\EditEmployee::route('/{record}/edit'),
        ];
    }
}
