# Updated Platform Architecture

## 1) Current Multi-Tenant Model

### User Roles

1. **Superadmin**
    - Accesses the superadmin panel.
    - Creates and manages operator accounts (sub-admins).
    - Can manage global customers.

2. **Sub-admin (Operator)**
    - Business/operator tenant.
    - Creates and manages employees.
    - Manages roles and permissions for their tenant scope.

3. **Employee**
    - Belongs to a sub-admin tenant via `tenant_id`.
    - Access is tenant-scoped through middleware and resource query scoping.

4. **Customer**
    - Independent end-user role for portal usage.
    - Not tied to sub-admin tenancy by default.

## 2) Ownership and Isolation Rules

### Superadmin -> Sub-admin ownership

- Sub-admin records are owned by the creating superadmin via `users.tenant_id = superadmin.id`.
- Superadmin operator lists are scoped to only their owned sub-admins.
- This prevents one superadmin from seeing another superadmin's operators.

### Sub-admin -> Employee ownership

- Employees use `tenant_id = subadmin.id`.
- Sub-admin panel applies tenant scoping middleware to keep data isolated.

### Role uniqueness (tenant-scoped)

- Role names are unique per tenant scope, not globally.
- Different tenants can both have role names like `Manager`.

## 3) Current Panel Paths and Access

1. **Superadmin panel**: `/user`
    - Panel id: `admin`
    - Access: superadmin only (enforced by redirect middleware)

2. **Sub-admin/employee panel**: `/panel`
    - Panel id: `panel`
    - Access: subadmin + employee

3. **Customer portal**: `/portal`
    - Panel id: `portal`
    - Access: customer
    - Includes registration, password reset, email verification

## 4) Data Model Notes

### users table

- `role`: `superadmin | subadmin | employee | customer`
- `tenant_id`:
    - superadmin: nullable
    - subadmin: set to owner superadmin id
    - employee: set to owner subadmin id
    - customer: nullable
- `is_active`: account access switch

### Removed feature: Location/Region coverage model

- The location/region feature has been removed from active functionality.
- Related resource and tables were dropped from current usage.

## 5) Route Searching Functionality

Route search is a core public-facing platform feature and is independent of the removed region/location model.

### API endpoints

- `GET /api/stops/search` -> stop autocomplete and lookup.
- `POST /api/routes/search` -> main journey search.
- `GET /api/routes/all-shapes` -> map display of route geometries.
- `GET /api/routes/{routeName}/stops` -> stops for a route preview.
- `GET /api/stops/nearest` -> nearest stop from coordinates.

### Search behavior (high level)

1. Validate origin/destination/date/time and optional coordinates.
2. Resolve stop IDs using coordinate-aware matching first, then name fallback.
3. Determine active services using `calendars` and `calendar_dates`.
4. Try direct journeys first.
5. If no direct path, compute transfer journeys.
6. Return journey legs with timing, stop details, and optional shape points for map rendering.

### Multi-tenant search telemetry

- Search requests are logged into `route_searches`.
- Tenant attribution is derived from nearby stop ownership and/or the current user tenant context.

## 6) Resource Overview (Current)

### Superadmin resources

- `SubAdminResource` (Operators): scoped to owned operators only.
- `CustomerResource`: global customer management.

### Sub-admin resources

- `EmployeeResource`
- `RoleResource`
- `PermissionResource`
- `ScheduleImports`

## 7) Practical Flow

1. Superadmin logs into `/user` and creates an operator.
2. Operator is automatically linked to that superadmin.
3. Operator logs into `/panel`, creates employees, roles, and permissions.
4. Customers use `/portal` independently.
5. End users can search routes through public API-backed UI (stops + route search + map shapes).

## 8) Validation Checklist

- [ ] Superadmin A cannot see Superadmin B's operators.
- [ ] New operators are auto-linked to creating superadmin.
- [ ] Employees only see tenant-scoped data in `/panel`.
- [ ] Two tenants can create same role name (for example `Manager`).
- [ ] Route search returns direct and transfer journeys correctly.
- [ ] Route map overlays load shapes and route stops correctly.
