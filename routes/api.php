<?php

use App\Http\Controllers\TenantController;
use App\Http\Controllers\TenantUserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

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

// Protected routes
Route::middleware(['auth:sanctum'])->group(function () {
    // User routes
    Route::get('/user', function (Request $request) {
        $user = $request->user();
        $user->load([
            'roles',
            'permissions'
        ]);
        return response()->json($user);
    });
    Route::post('/tenants/{tenant}/join', [TenantUserController::class, 'joinTenantAsMember']);
    Route::post('/tenants/{tenant}/leave', [TenantUserController::class, 'leaveTenant']);
    Route::apiResource('tenants', TenantController::class);

    Route::post('/tenants/switch', [\App\Http\Controllers\SwitchTenantController::class, 'switch']);
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
