<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Log;

class TenantUserController extends Controller
{

    /**
     * @param Request $request
     * @param Tenant $tenant
     * @return Response
     */
    public function joinTenantAsMember(Request $request, Tenant $tenant): Response
    {
        $user = $request->user();

        // Start database transaction
        DB::beginTransaction();

        try {
            Log::info('Attempting to join tenant as member', [
                'user_id' => $user->id,
                'tenant_id' => $tenant->id
            ]);

            // Check if user is already a member
            if ($tenant->users()->where('user_id', $user->id)->exists()) {
                return $this->jsonUnprocessable('You are already a member of this organization');
            }

            // Get the member role for this tenant
            $memberRole = Role::where('name', 'member')
                ->where('tenant_id', $tenant->id)
                ->first();

            if (!$memberRole) {
                Log::error('Member role not found for tenant', [
                    'tenant_id' => $tenant->id
                ]);
                return $this->jsonServerError('Member role not found for this organization', 500);
            }

            // Attach user to tenant
            $tenant->users()->attach($user->id);
            Log::info('User attached to tenant', [
                'user_id' => $user->id,
                'tenant_id' => $tenant->id
            ]);

            // Attach the role with tenant context
            $user->roles()->attach($memberRole->id, [
                'tenant_id' => $tenant->id,
            ]);
            Log::info('Role assigned to user', [
                'user_id' => $user->id,
                'role_id' => $memberRole->id,
                'tenant_id' => $tenant->id
            ]);

            // Commit the transaction if all operations succeed
            DB::commit();

            return $this->jsonCreated('You have successfully joined the organization as a member');

        } catch (\Exception $e) {
            // Rollback the transaction on error
            DB::rollBack();
            
            Log::error('Failed to join organization', [
                'user_id' => $user->id ?? null,
                'tenant_id' => $tenant->id ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            if (app()->environment('local')) {
                return $this->jsonServerError($e->getMessage());
            }
            
            return $this->jsonServerError('Failed to join organization. Please try again later.');
        }
    }

    /**
     * @param Request $request
     * @param Tenant $tenant
     * @return Response
     */
    public function leaveTenant(Request $request, Tenant $tenant): Response
    {
        $user = $request->user();

        try {
            if (!$tenant->users()->where('user_id', $user->id)->exists()) {
                return $this->jsonUnprocessable('You are not a member of this organization');
            }

            $tenant->users()->detach($user->id);

            $user->roles()->wherePivot('tenant_id', $tenant->id)->detach();

            return $this->jsonSuccess('You have successfully left the organization');
        } catch (\Exception $e) {
            if (app()->environment('local')) {
                return $this->jsonServerError($e->getMessage());
            }
            return $this->jsonServerError('Failed to leave organization.');
        }
    }

    /**
     * Display a listing of users for a specific tenant.
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
