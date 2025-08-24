<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class UserController extends Controller
{
    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function getUserInfo(Request $request): JsonResponse
    {
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
    }
}
