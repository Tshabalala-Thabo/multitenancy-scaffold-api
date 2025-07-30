<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

class TenantUserController extends Controller
{
    /**
     * @param Request $request
     * @param Tenant $tenant
     * @return Response
     */
    public function join(Request $request, Tenant $tenant): Response
    {
        $user = $request->user();

        if ($tenant->users()->where('user_id', $user->id)->exists()) {
            return $this->jsonUnprocessable('User is already a member of this tenant');
        }

        try {
            DB::transaction(function () use ($user, $tenant) {
                $tenant->users()->attach($user->id);

                $memberRole = Role::firstOrCreate([
                    'name' => 'member',
                    'tenant_id' => $tenant->id,
                ]);

                $user->assignRole($memberRole);
            });

            return $this->jsonSuccess('Successfully joined the tenant');
        } catch (\Exception $e) {
            return $this->jsonServerError('Failed to join the tenant. Please try again.');
        }
    }

    /**
     * @param Request $request
     * @param Tenant $tenant
     * @return Response
     */
    public function leave(Request $request, Tenant $tenant): Response
    {
        $user = $request->user();

        if (!$tenant->users()->where('user_id', $user->id)->exists()) {
            return $this->jsonUnprocessable('User is not a member of this tenant');
        }

        try {
            DB::transaction(function () use ($user, $tenant) {
                $tenant->users()->detach($user->id);

                $user->roles()
                    ->wherePivot('team_id', $tenant->id)
                    ->detach();
            });

            return $this->jsonSuccess('Successfully left the tenant');

        } catch (\Exception $e) {
            return $this->jsonServerError('Failed to leave the tenant. Please try again.');
        }
    }

    /**
     * @param Tenant $tenant
     * @return JsonResponse
     */
    public function index(Tenant $tenant)
    {
        return response()->json([
            'users' => $tenant->users()->with('roles')->get(),
        ]);
    }

    /**
     * Assign a user to a tenant.
     */
    public function assignUser(Request $request, Tenant $tenant)
    {
        $validated = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'roles' => ['sometimes', 'array'],
            'roles.*' => ['exists:roles,name'],
        ]);

        $user = User::findOrFail($validated['user_id']);

        // Check if user is already assigned to this tenant
        if ($tenant->users()->where('user_id', $user->id)->exists()) {
            return response()->json([
                'message' => 'User is already assigned to this tenant',
            ], 422);
        }

        // Assign user to tenant
        $tenant->users()->attach($user->id);

        // Assign roles if provided
        if (isset($validated['roles'])) {
            foreach ($validated['roles'] as $role) {
                $user->assignRole([$role, 'team_id' => $tenant->id]);
            }
        }

        return response()->json([
            'message' => 'User assigned to tenant successfully',
        ], 201);
    }

    /**
     * Remove a user from a tenant.
     */
    public function removeUser(Tenant $tenant, User $user)
    {
        // Check if user is assigned to this tenant
        if (!$tenant->users()->where('user_id', $user->id)->exists()) {
            return response()->json([
                'message' => 'User is not assigned to this tenant',
            ], 422);
        }

        // Remove user from tenant
        $tenant->users()->detach($user->id);

        // Remove tenant-specific roles
        $user->roles()->wherePivot('team_id', $tenant->id)->detach();

        return response()->json([
            'message' => 'User removed from tenant successfully',
        ]);
    }

    /**
     * Update user roles for a specific tenant.
     */
    public function updateRoles(Request $request, Tenant $tenant, User $user)
    {
        $validated = $request->validate([
            'roles' => ['required', 'array'],
            'roles.*' => ['exists:roles,name'],
        ]);

        // Check if user is assigned to this tenant
        if (!$tenant->users()->where('user_id', $user->id)->exists()) {
            return response()->json([
                'message' => 'User is not assigned to this tenant',
            ], 422);
        }

        // Begin transaction
        DB::beginTransaction();

        try {
            // Remove existing tenant-specific roles
            $user->roles()->wherePivot('team_id', $tenant->id)->detach();

            // Assign new roles
            foreach ($validated['roles'] as $role) {
                $user->assignRole([$role, 'team_id' => $tenant->id]);
            }

            DB::commit();

            return response()->json([
                'message' => 'User roles updated successfully',
                'roles' => $user->roles()->wherePivot('team_id', $tenant->id)->get(),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Failed to update user roles',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
