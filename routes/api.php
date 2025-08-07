<?php

use App\Http\Controllers\TenantController;
use App\Http\Controllers\TenantUserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Models\Permission;

/*
 * |--------------------------------------------------------------------------
 * | API Routes
 * |--------------------------------------------------------------------------
 * |
 * | Here is where you can register API routes for your application. These
 * | routes are loaded by the RouteServiceProvider within a group which
 * | is assigned the "api" middleware group. Enjoy building your API!
 * |
 */

Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('/user', function (Request $request) {
        $user = $request->user();
        $user->load(['roles', 'tenants']);

        $organisations = $user->tenants->map(function ($tenant) {
            return [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'logo_url' => $tenant->getLogoUrl(),
            ];
        });

        $permissions = collect();

        foreach ($user->tenants as $tenant) {
            $roleIds = $user->roles()
                ->wherePivot('tenant_id', $tenant->id)
                ->pluck('roles.id');

            $tenantPermissions = Permission::whereHas('roles', function ($q) use ($roleIds) {
                $q->whereIn('roles.id', $roleIds);
            })->get()->map(function ($permission) use ($tenant) {
                return [
                    'id' => $permission->id,
                    'name' => $permission->name,
                    'category' => $permission->category ?? null,
                    'description' => $permission->description ?? null,
                    'tenant_id' => $tenant->id,
                ];
            });

            $permissions = $permissions->merge($tenantPermissions);
        }

        $permissions = $permissions->unique(function ($perm) {
            return $perm['id'] . '-' . $perm['tenant_id'];
        })->values();

        $userData = $user->toArray();
        unset($userData['tenants']);

        return response()->json(array_merge(
            $userData,
            [
                'permissions' => $permissions,
                'organisations' => $organisations,
                'tenant_id' => session('tenant_id'),
            ]
        ));
    });

    Route::post('/tenants/{tenant}/join', [TenantUserController::class, 'joinTenantAsMember']);
    Route::post('/tenants/{tenant}/leave', [TenantUserController::class, 'leaveTenant']);
    Route::get('/tenants/{tenant}/settings', [TenantUserController::class, 'getTenantSettings']);
    Route::apiResource('tenants', TenantController::class);

    Route::post('/tenants/switch', [TenantUserController::class, 'switch']);
    // Tenant management routes (admin only)
    //    Route::middleware(['role:super_admin'])->group(function () {
    //        Route::apiResource('tenants', TenantController::class)->except(['index']);
    //
    //        // Tenant-user management
    //        Route::get('tenants/{tenant}/users', [TenantUserController::class, 'index']);
    //        Route::post('tenants/{tenant}/users', [TenantUserController::class, 'assignUser']);
    //        Route::delete('tenants/{tenant}/users/{user}', [TenantUserController::class, 'removeUser']);
    //        Route::put('tenants/{tenant}/users/{user}/roles', [TenantUserController::class, 'updateRoles']);
    //    });
});

// Tenant-specific routes
Route::middleware(['auth:sanctum', 'multitenancy'])->prefix('tenant')->group(function () {
    // These routes will only be accessible when a tenant is active
    Route::get('/dashboard', function () {
        return response()->json([
            'tenant' => tenant()->only(['id', 'name', 'slug']),
            'message' => 'Welcome to the tenant dashboard',
        ]);
    });
});
