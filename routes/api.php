<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UserController;
use App\Http\Controllers\TenantController;
use App\Http\Controllers\TenantUserController;
use App\Http\Controllers\TenantUserBanController;

Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('/user', [UserController::class, 'getUserInfo']);

    Route::apiResource('tenants', TenantController::class);

    Route::post('/tenants/switch', [TenantUserController::class, 'switch']);
    Route::post('/tenants/{tenant}/join', [TenantUserController::class, 'joinTenantAsMember']);
    Route::post('/tenants/{tenant}/leave', [TenantUserController::class, 'leaveTenant']);
    Route::get('/tenants/{tenant}/settings', [TenantUserController::class, 'getTenantSettings']);
    Route::put('/tenants/{tenant}/basic-info', [TenantUserController::class, 'updateBasicInfo']);
    Route::put('/tenants/{tenant}/access-control', [TenantUserController::class, 'updateAccessControl']);
    Route::put('/tenants/{tenant}/permissions', [TenantUserController::class, 'updatePermissions']);
    Route::post('/tenants/{tenant}/logo', [TenantUserController::class, 'uploadLogo']);
    Route::get('/tenants/{tenant}/users', [TenantUserController::class, 'getTenantUsers']);

    Route::get('/tenants/{tenant}/bans', [TenantUserBanController::class, 'index']);
    Route::post('/tenants/{tenant}/users/{user}/ban', [TenantUserBanController::class, 'banUserFromTenant']);
    Route::post('/tenants/{tenant}/users/{user}/unban', [TenantUserBanController::class, 'unbanUserFromTenant']);
});

