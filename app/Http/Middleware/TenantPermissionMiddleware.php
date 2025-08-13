<?php

namespace App\Http\Middleware;

use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Exceptions\UnauthorizedException;
use Closure;

class TenantPermissionMiddleware
{
    public function handle($request, Closure $next, $permission, $guard = null): mixed
    {
        $user = Auth::guard($guard)->user();

        if (!$user) {
            throw UnauthorizedException::notLoggedIn();
        }

        $tenantId = $user->current_tenant_id;

        if (!$tenantId) {
            throw UnauthorizedException::forPermissions([$permission]);
        }

        $hasRolePermission = $user
            ->roles()
            ->where('roles.tenant_id', $tenantId)
            ->whereHas('permissions', function ($q) use ($permission) {
                $q->where('name', $permission);
            })
            ->exists();

        if (!$hasRolePermission) {
            throw UnauthorizedException::forPermissions([$permission]);
        }

        return $next($request);
    }
}
