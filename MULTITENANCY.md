# Laravel Multitenancy Implementation Guide

This document provides an overview of how multitenancy is implemented in this application using Spatie's Laravel Multitenancy package.

## Overview

This application uses a multi-tenant architecture where:

- Each tenant has its own subdomain (e.g., `tenant1.example.com`)
- Users can belong to multiple tenants
- Permissions and roles are tenant-specific
- Data is isolated between tenants

## Key Components

### Models

1. **Tenant Model** (`App\Models\Tenant`)
   - Extends Spatie's base Tenant model
   - Has a many-to-many relationship with users
   - Includes a `slug` field for subdomain identification

2. **User Model** (`App\Models\User`)
   - Uses the `BelongsToTenant` trait for tenant association
   - Has a many-to-many relationship with tenants
   - Uses the `HasRoles` trait for role-based permissions

### Tenant Resolution

The application uses a custom `DomainTenantFinder` class to determine the current tenant based on the subdomain of the incoming request.

### Middleware

1. **NeedsTenant Middleware** (`multitenancy`)
   - Ensures a tenant is active for specific routes
   - Automatically applied to tenant-specific routes

2. **EnsureTenantExists Middleware** (`tenant.exists`)
   - Checks if a valid tenant exists for the current request
   - Returns a 404 error if no tenant is found

## API Routes

### Public Routes

- `GET /api/tenants` - List all tenants

### Protected Routes (requires authentication)

- `GET /api/user` - Get current user information

### Admin Routes (requires 'manage tenants' permission)

- `POST /api/tenants` - Create a new tenant
- `GET /api/tenants/{tenant}` - Get tenant details
- `PUT /api/tenants/{tenant}` - Update tenant details
- `DELETE /api/tenants/{tenant}` - Delete a tenant

### Tenant-User Management (requires 'manage tenants' permission)

- `GET /api/tenants/{tenant}/users` - List users for a tenant
- `POST /api/tenants/{tenant}/users` - Assign a user to a tenant
- `DELETE /api/tenants/{tenant}/users/{user}` - Remove a user from a tenant
- `PUT /api/tenants/{tenant}/users/{user}/roles` - Update user roles for a tenant

### Tenant-Specific Routes (requires authentication and active tenant)

- `GET /api/tenant/dashboard` - Access tenant dashboard

## Usage Examples

### Creating a Tenant

```php
$tenant = Tenant::create([
    'name' => 'Acme Corporation',
    'slug' => 'acme',
]);
```

### Assigning a User to a Tenant

```php
$tenant->users()->attach($userId);
```

### Assigning Tenant-Specific Roles

```php
$user->assignRole([
    'admin',
    'team_id' => $tenant->id
]);
```

### Making a Tenant Current

```php
$tenant->makeCurrent();
```

### Accessing Current Tenant

```php
$currentTenant = tenant();
```

## Configuration

The multitenancy configuration is located in `config/multitenancy.php`. Key settings include:

- `tenant_finder` - The class used to determine the current tenant
- `switch_tenant_tasks` - Tasks executed when switching between tenants
- `tenant_model` - The model class used for tenants

## Best Practices

1. **Always check tenant context**: Use the `tenant()` helper to ensure you're in the correct tenant context.

2. **Use middleware**: Apply the `multitenancy` middleware to routes that should only be accessible within a tenant context.

3. **Tenant-aware relationships**: When defining relationships that should be tenant-specific, use the appropriate scoping.

4. **Testing**: When testing tenant-specific functionality, make sure to set the current tenant using `$tenant->makeCurrent()`.

5. **Tenant isolation**: Be careful with global scopes and events that might leak data between tenants.