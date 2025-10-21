# Multi-Tenancy Filament 4 Project

A Laravel application with Filament 4 implementing a multi-tenancy system with three distinct panels: Superadmin, Sub-Admin, and Customer.

## Architecture Overview

### Three Panel System

1. **Superadmin Panel** (`/superadmin`)

    - Manages sub-admins
    - Full system access
    - Can create and manage sub-admin accounts

2. **Sub-Admin Panel** (`/subadmin`)

    - Tenant-scoped access
    - Manages customers within their tenant
    - Can create roles and permissions
    - Can assign roles to customers

3. **Customer Panel** (`/customer`)
    - End-user portal
    - Self-registration enabled
    - Limited access based on assigned roles

## Database Structure

### Users Table

-   `id`: Primary key
-   `name`: User's name
-   `email`: User's email (unique)
-   `password`: Hashed password
-   `role`: Enum (superadmin, subadmin, customer)
-   `tenant_id`: Foreign key to users table (links customers to sub-admins)
-   `is_active`: Boolean for active status
-   `email_verified_at`: Email verification timestamp
-   `created_at`, `updated_at`: Timestamps

### Relationships

-   **Sub-Admin**: Can have many customers (one-to-many via `tenant_id`)
-   **Customer**: Belongs to one sub-admin (many-to-one via `tenant_id`)
-   **Superadmin**: No tenant relationships

## Setup Instructions

### 1. Install Dependencies

```bash
composer install
npm install
```

### 2. Environment Configuration

Copy `.env.example` to `.env` and configure your database:

```bash
cp .env.example .env
php artisan key:generate
```

### 3. Run Migrations

```bash
php artisan migrate
```

This will create:

-   Users table with multi-tenancy fields
-   Spatie Permission tables (roles, permissions, etc.)

### 4. Seed Superadmin

Create the initial superadmin account:

```bash
php artisan db:seed --class=SuperAdminSeeder
```

**Default Credentials:**

-   Email: `superadmin@example.com`
-   Password: `password`

**⚠️ Change these credentials immediately in production!**

### 5. Build Assets

```bash
npm run build
```

### 6. Start Development Server

```bash
php artisan serve
```

## Panel Access

### Superadmin Panel

-   URL: `http://localhost:8000/superadmin`
-   Login with superadmin credentials
-   Create sub-admin accounts

### Sub-Admin Panel

-   URL: `http://localhost:8000/subadmin`
-   Login with sub-admin credentials (created by superadmin)
-   Manage customers and roles within your tenant

### Customer Panel

-   URL: `http://localhost:8000/customer`
-   Register new account or login
-   Access features based on assigned roles

## Features

### Superadmin Features

-   ✅ Create and manage sub-admins
-   ✅ Activate/deactivate sub-admin accounts
-   ✅ View all sub-admins and their customer counts
-   ✅ Full system oversight

### Sub-Admin Features

-   ✅ Create and manage customers (tenant-scoped)
-   ✅ Create custom roles
-   ✅ Create custom permissions
-   ✅ Assign roles to customers
-   ✅ Activate/deactivate customer accounts
-   ✅ All data automatically scoped to their tenant

### Customer Features

-   ✅ Self-registration
-   ✅ Password reset
-   ✅ Email verification
-   ✅ Access based on assigned roles and permissions
-   ✅ Account management

## Tenant Scoping

The system automatically applies tenant scoping for sub-admins:

-   **Middleware**: `ApplyTenantScopes` automatically filters queries
-   Sub-admins can only see and manage their own customers
-   Customers are automatically assigned to the creating sub-admin
-   Global scopes ensure data isolation

## Role & Permission Management

### Creating Roles (Sub-Admin Panel)

1. Navigate to "Access Control" > "Roles"
2. Click "Create"
3. Enter role name (e.g., "Manager", "Viewer")
4. Assign permissions
5. Save

### Creating Permissions (Sub-Admin Panel)

1. Navigate to "Access Control" > "Permissions"
2. Click "Create"
3. Enter permission name (e.g., "view_reports", "edit_content")
4. Save

### Assigning Roles to Customers

1. Navigate to "Customers"
2. Edit a customer
3. Select roles from the "Roles" dropdown
4. Save

## Security Best Practices

1. **Change Default Credentials**: Update superadmin password immediately
2. **Environment Variables**: Never commit `.env` file
3. **Database Credentials**: Use strong passwords
4. **HTTPS**: Always use HTTPS in production
5. **Email Verification**: Enable for all panels in production

## File Structure

```
app/
├── Filament/
│   ├── Superadmin/
│   │   └── Resources/
│   │       └── SubAdminResource.php
│   ├── SubAdmin/
│   │   └── Resources/
│   │       ├── CustomerResource.php
│   │       ├── RoleResource.php
│   │       └── PermissionResource.php
│   └── Customer/
│       └── Resources/
│           └── (Your customer resources)
├── Http/
│   └── Middleware/
│       └── ApplyTenantScopes.php
├── Models/
│   └── User.php
└── Providers/
    └── Filament/
        ├── SuperadminPanelProvider.php
        ├── SubAdminPanelProvider.php
        └── CustomerPanelProvider.php
```

## User Model Methods

```php
// Check user role
$user->isSuperAdmin()  // Returns bool
$user->isSubAdmin()    // Returns bool
$user->isCustomer()    // Returns bool

// Access relationships
$user->tenant          // Get sub-admin (for customers)
$user->tenantUsers     // Get customers (for sub-admins)

// Scopes
User::subAdmins()->get()              // Get all sub-admins
User::customers()->get()              // Get all customers
User::forTenant($tenantId)->get()     // Get users for specific tenant
```

## Customization

### Adding Resources to Customer Panel

1. Create resource: `php artisan make:filament-resource YourModel --panel=customer`
2. Place in `app/Filament/Customer/Resources/`
3. Implement your business logic

### Custom Dashboard Widgets

-   Superadmin: `app/Filament/Superadmin/Widgets/`
-   Sub-Admin: `app/Filament/SubAdmin/Widgets/`
-   Customer: `app/Filament/Customer/Widgets/`

## Troubleshooting

### Cannot Access Panel

-   Verify `is_active` is `true` for the user
-   Check user `role` matches the panel (`superadmin`, `subadmin`, or `customer`)
-   Clear cache: `php artisan cache:clear`

### Sub-Admin Can't See Customers

-   Ensure `tenant_id` is set correctly on customer records
-   Check middleware is applied: `ApplyTenantScopes`
-   Review global scope in middleware

### Permission Not Working

-   Run: `php artisan cache:clear`
-   Ensure Spatie permissions are published: `php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider"`
-   Check role assignments in database

## Extending the System

### Adding Custom Guards

Edit `config/auth.php` to add custom guards for different user types if needed.

### Multi-Database Tenancy

For full database isolation, consider using packages like:

-   `stancl/tenancy`
-   `spatie/laravel-multitenancy`

## Support

For issues or questions:

1. Check Laravel documentation: https://laravel.com/docs
2. Check Filament documentation: https://filamentphp.com/docs
3. Check Spatie Permission documentation: https://spatie.be/docs/laravel-permission

## License

This project is open-sourced software licensed under the MIT license.
