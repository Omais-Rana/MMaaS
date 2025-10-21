<?php

namespace App\Filament\SubAdmin\Resources;

use App\Filament\SubAdmin\Resources\PermissionResource\Pages;
use BackedEnum;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Tables;
use Filament\Tables\Table;
use Spatie\Permission\Models\Permission;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Illuminate\Support\Facades\Auth;

class PermissionResource extends Resource
{
    protected static ?string $model = Permission::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-key';

    protected static ?string $navigationLabel = 'Permissions';

    protected static string|\UnitEnum|null $navigationGroup = 'Access Control';

    // Hide from navigation - permissions are managed through roles
    protected static bool $shouldRegisterNavigation = false;

    // Permission checks
    public static function canViewAny(): bool
    {
        return Auth::user()->can('view_permissions');
    }

    public static function canCreate(): bool
    {
        return Auth::user()->can('create_permissions');
    }

    public static function canEdit($record): bool
    {
        return Auth::user()->can('edit_permissions');
    }

    public static function canDelete($record): bool
    {
        return Auth::user()->can('delete_permissions');
    }

    public static function canDeleteAny(): bool
    {
        return Auth::user()->can('delete_permissions');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Permission Information')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255)
                            ->helperText('Enter a unique permission name (e.g., view_reports, edit_content)'),
                    ])
                    ->columns(1),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('roles.name')
                    ->label('Assigned to Roles')
                    ->badge()
                    ->searchable(),

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
            'index' => Pages\ListPermissions::route('/'),
            'create' => Pages\CreatePermission::route('/create'),
            'edit' => Pages\EditPermission::route('/{record}/edit'),
        ];
    }
}
