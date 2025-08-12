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
            $tenantPermissions = $user->roles
                ->where('pivot.tenant_id', $tenant->id)
                ->flatMap(fn($role) => $role->permissions)
                ->map(function ($permission) use ($tenant) {
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

    Route::apiResource('tenants', TenantController::class);

    Route::post('/tenants/switch', [TenantUserController::class, 'switch']);
    Route::post('/tenants/{tenant}/join', [TenantUserController::class, 'joinTenantAsMember']);
    Route::post('/tenants/{tenant}/leave', [TenantUserController::class, 'leaveTenant']);
    Route::get('/tenants/{tenant}/settings', [TenantUserController::class, 'getTenantSettings']);
    Route::put('/tenants/{tenant}/basic-info', [TenantUserController::class, 'updateBasicInfo']);
    Route::patch('/tenants/{tenant}/access-control', [TenantUserController::class, 'updateAccessControl']);
    Route::patch('/tenants/{tenant}/permissions', [TenantUserController::class, 'updatePermissions']);
    Route::post('/tenants/{tenant}/logo', [TenantUserController::class, 'uploadLogo']);


});

