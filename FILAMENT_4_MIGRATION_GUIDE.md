# IMPORTANT: Filament 4 Resource Generation Guide

## Current Status

✅ Database migrations have been run successfully
✅ Superadmin user has been seeded (email: superadmin@example.com, password: password)  
✅ All panel providers are configured correctly
✅ User model is set up with multi-tenancy support
✅ Middleware for tenant scoping is created

## Important: Manual Resource Creation Required

Due to breaking changes in Filament 4, the resources need to be generated using the Filament CLI to ensure proper compatibility.

### Resource files currently exist but need regeneration:

-   `app/Filament/Superadmin/Resources/SubAdminResource.php` - Has type errors
-   `app/Filament/SubAdmin/Resources/CustomerResource.php` - Has type errors
-   `app/Filament/SubAdmin/Resources/RoleResource.php` - Has type errors
-   `app/Filament/SubAdmin/Resources/PermissionResource.php` - Has type errors

## Step-by-Step Fix Instructions

### Step 1: Remove Existing Resources (Temporarily)

```powershell
Remove-Item -Path "app\Filament\Superadmin\Resources\SubAdminResource*" -Recurse -Force
Remove-Item -Path "app\Filament\SubAdmin\Resources\CustomerResource*" -Recurse -Force
Remove-Item -Path "app\Filament\SubAdmin\Resources\RoleResource*" -Recurse -Force
Remove-Item -Path "app\Filament\SubAdmin\Resources\PermissionResource*" -Recurse -Force
```

### Step 2: Generate Resources Using Filament CLI

#### 2a. Generate SubAdmin Resource (for Superadmin Panel)

```bash
php artisan make:filament-resource User --generate --panel=superadmin
```

When prompted:

-   Title attribute: `name`
-   Read-only view: `no`
-   Generate from database: `yes`
-   Soft deletes: `no`

Then customize the generated file:

1. Rename `UserResource.php` to `SubAdminResource.php`
2. Update class name to `SubAdminResource`
3. Add filter in `getEloquentQuery()`:

```php
public static function getEloquentQuery(): Builder
{
    return parent::getEloquentQuery()->where('role', 'subadmin');
}
```

4. Remove unnecessary fields and add hidden field for role='subadmin'
5. Update navigation label to "Sub-Admins"

#### 2b. Generate Customer Resource (for SubAdmin Panel)

```bash
php artisan make:filament-resource User --generate --panel=subadmin
```

Follow same prompts, then:

1. Rename to `CustomerResource.php`
2. Update class name to `CustomerResource`
3. Add tenant scoping:

```php
public static function getEloquentQuery(): Builder
{
    return parent::getEloquentQuery()
        ->where('role', 'customer')
        ->where('tenant_id', Auth::id());
}
```

4. Add hidden fields for `role='customer'` and `tenant_id=Auth::id()`
5. Add role selector

#### 2c. Generate Role Resource (for SubAdmin Panel)

```bash
php artisan make:filament-resource Role --generate --panel=subadmin
```

The model is `Spatie\Permission\Models\Role`

Update the generated resource:

```php
use Spatie\Permission\Models\Role;

protected static ?string $model = Role::class;
```

#### 2d. Generate Permission Resource (for SubAdmin Panel)

```bash
php artisan make:filament-resource Permission --generate --panel=subadmin
```

The model is `Spatie\Permission\Models\Permission`

Update the generated resource:

```php
use Spatie\Permission\Models\Permission;

protected static ?string $model = Permission::class;
```

## Alternative: Copy From Generated Test

If you want to manually fix the existing resources, here's the key changes needed:

### Before (Filament 3 style - WRONG):

```php
use Filament\Forms\Form;

protected static ?string $navigationIcon = 'heroicon-o-user-group';

public static function form(Form $form): Form
{
    return $form->schema([
        // components
    ]);
}
```

### After (Filament 4 style - CORRECT):

```php
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use BackedEnum;

protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlineUserGroup;

public static function form(Schema $schema): Schema
{
    return $schema->components([
        // components
    ]);
}
```

## Key Changes in Filament 4:

1. **Form → Schema**: `Form` class replaced with `Schema`
2. **schema() → components()**: Method name changed
3. **Icon Type**: Now uses `Heroicon` enum with `BackedEnum` type
4. **Navigation Icon**: Type is now `string|BackedEnum|null`

## Icon Mapping:

| Old (string)              | New (Heroicon enum)          |
| ------------------------- | ---------------------------- |
| 'heroicon-o-user-group'   | Heroicon::OutlineUserGroup   |
| 'heroicon-o-users'        | Heroicon::OutlineUsers       |
| 'heroicon-o-shield-check' | Heroicon::OutlineShieldCheck |
| 'heroicon-o-key'          | Heroicon::OutlineKey         |

## After Fixing Resources

Once resources are properly generated/fixed, you can access:

### Superadmin Panel

```
URL: http://localhost:8000/superadmin
Email: superadmin@example.com
Password: password
```

### SubAdmin Panel

```
URL: http://localhost:8000/subadmin
(Create sub-admin from superadmin panel first)
```

### Customer Panel

```
URL: http://localhost:8000/customer
(Register directly or create from subadmin panel)
```

## Testing the System

1. **Login as Superadmin**

    - Access /superadmin
    - Create a sub-admin account

2. **Login as Sub-Admin**

    - Access /subadmin with sub-admin credentials
    - Create customers
    - Create roles and permissions
    - Assign roles to customers

3. **Login as Customer**
    - Register at /customer/register
    - OR login with credentials created by sub-admin

## Troubleshooting

### Error: "Type of...must be BackedEnum|string|null"

-   You have old Filament 3 syntax
-   Regenerate resources using CLI or manually update as shown above

### Error: "Undefined type 'Filament\Forms\Form'"

-   Change `use Filament\Forms\Form` to `use Filament\Schemas\Schema`
-   Change method signature from `Form` to `Schema`

### Sub-admin can't see customers

-   Check `ApplyTenantScopes` middleware is applied
-   Verify `tenant_id` is set on customer records

## Next Steps

1. Delete existing resource files
2. Regenerate using Filament CLI
3. Customize as needed
4. Test all three panels
5. Add additional resources for your business logic

## Additional Resources

-   Filament 4 Documentation: https://filamentphp.com/docs
-   Laravel Permission Docs: https://spatie.be/docs/laravel-permission
-   Your SETUP_GUIDE.md for complete system documentation
