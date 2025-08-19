<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UserController;
use App\Http\Controllers\TenantController;
use App\Http\Controllers\TenantUserController;

Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('/user', [UserController::class, 'getUserInfo']);

    Route::apiResource('tenants', TenantController::class);

    Route::post('/tenants/switch', [TenantUserController::class, 'switch']);
    Route::post('/tenants/{tenant}/join', [TenantUserController::class, 'joinTenantAsMember']);
    Route::post('/tenants/{tenant}/leave', [TenantUserController::class, 'leaveTenant']);
    Route::get('/tenants/{tenant}/settings', [TenantUserController::class, 'getTenantSettings']);
    Route::put('/tenants/{tenant}/basic-info', [TenantUserController::class, 'updateBasicInfo']);
    Route::put('/tenants/{tenant}/access-control', [TenantUserController::class, 'updateAccessControl']);
    Route::patch('/tenants/{tenant}/permissions', [TenantUserController::class, 'updatePermissions']);
    Route::get('/tenants/{tenant}/users', [TenantUserController::class, 'getTenantUsers']);
    Route::post('/tenants/{tenant}/logo', [TenantUserController::class, 'uploadLogo']);
});



