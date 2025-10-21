# Updated Multi-Tenancy Structure

## Corrected Architecture

### User Roles

1. **Superadmin** - System administrator

    - Creates and manages sub-admins
    - Full system access
    - No tenant relationship

2. **Sub-Admin** - Business owner/tenant

    - Creates and manages employees
    - Can create roles and permissions for employees
    - Each sub-admin is a separate tenant

3. **Employee** - Staff member working for a sub-admin

    - Belongs to a specific sub-admin (via `tenant_id`)
    - Can be assigned roles and permissions by their sub-admin
    - Access scoped to their tenant

4. **Customer** - Independent end-user
    - NOT tied to any sub-admin
    - Separate entity with own registration
    - No `tenant_id` relationship

## Relationships

```
Superadmin (no tenant)
    └── Creates Sub-Admins

Sub-Admin (tenant)
    └── Creates Employees (tenant_id = sub-admin.id)

Customer (independent, no tenant)
```

## Database Structure

### Users Table

-   `role`: enum('superadmin', 'subadmin', 'employee', 'customer')
-   `tenant_id`: Foreign key to users table (NULL for superadmin, subadmin, and customer; SET for employee)
-   `is_active`: Boolean

## Panel Structure

### 1. Superadmin Panel (`/superadmin`)

**Resources:**

-   SubAdminResource - Manage sub-admins

**Access:** Superadmin only

### 2. Sub-Admin Panel (`/subadmin`)

**Resources:**

-   EmployeeResource - Manage employees (tenant-scoped)
-   CustomerResource - View/manage all customers (NOT tenant-scoped)
-   RoleResource - Create roles for employees
-   PermissionResource - Create permissions for employees

**Access:** Sub-admin only
**Scoping:** Employees automatically scoped to logged-in sub-admin

### 3. Customer Panel (`/customer`)

**Features:**

-   Self-registration enabled
-   Password reset
-   Email verification
-   No tenant relationship

**Access:** Customer only

## Key Changes Made

1. ✅ Added `employee` role to database enum
2. ✅ Created `EmployeeResource` for sub-admins to manage their staff
3. ✅ Updated `CustomerResource` to remove tenant relationship
4. ✅ Updated User model with `isEmployee()` and `employees()` relationship
5. ✅ Changed SubAdminResource to show "Employees" count instead of "Customers"
6. ✅ Middleware already handles employee scoping via `tenant_id`

## Usage Flow

### Superadmin Creates Sub-Admin

1. Login to `/superadmin`
2. Create sub-admin account
3. Sub-admin receives credentials

### Sub-Admin Creates Employees

1. Login to `/subadmin`
2. Navigate to Employees
3. Create employee accounts
4. Assign roles/permissions
5. Employees are automatically linked via `tenant_id`

### Sub-Admin Views Customers

1. Login to `/subadmin`
2. Navigate to Customers
3. View all customers (not filtered by tenant)
4. Customers are independent entities

### Customer Registration

1. Visit `/customer/register`
2. Self-register account
3. Login and access customer portal
4. No relationship to any sub-admin

## Files Structure

```
app/
├── Filament/
│   ├── Superadmin/
│   │   └── Resources/
│   │       └── SubAdminResource.php (manages sub-admins)
│   ├── SubAdmin/
│   │   └── Resources/
│   │       ├── EmployeeResource.php (manages employees - TENANT SCOPED)
│   │       ├── CustomerResource.php (views customers - NOT SCOPED)
│   │       ├── RoleResource.php
│   │       └── PermissionResource.php
│   └── Customer/
│       └── (customer-facing resources)
├── Http/
│   └── Middleware/
│       └── ApplyTenantScopes.php (scopes employees to sub-admin)
└── Models/
    └── User.php (4 roles: superadmin, subadmin, employee, customer)
```

## Testing Checklist

-   [ ] Create sub-admin from superadmin panel
-   [ ] Login as sub-admin
-   [ ] Create employees (should be scoped to sub-admin)
-   [ ] Verify employees show in "Employees" list
-   [ ] Check that employee count appears in superadmin panel
-   [ ] Verify customer registration works independently
-   [ ] Confirm customers appear in sub-admin panel but are NOT scoped
-   [ ] Test role/permission assignment to employees
