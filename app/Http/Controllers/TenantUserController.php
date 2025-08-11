<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

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

        try {
            DB::beginTransaction();

            if ($tenant->users()->where('user_id', $user->id)->exists()) {
                return $this->jsonUnprocessable('You are already a member of this organization');
            }

            $memberRole = Role::where('name', 'member')
                ->where('tenant_id', $tenant->id)
                ->first();

            if (!$memberRole) {
                Log::error('Member role not found for tenant', [
                    'tenant_id' => $tenant->id
                ]);
                return $this->jsonServerError('Member role not found for this organization', 500);
            }

            $tenant->users()->attach($user->id);
            Log::info('User attached to tenant', [
                'user_id' => $user->id,
                'tenant_id' => $tenant->id
            ]);

            $user->roles()->attach($memberRole->id, [
                'tenant_id' => $tenant->id,
            ]);
            Log::info('Role assigned to user', [
                'user_id' => $user->id,
                'role_id' => $memberRole->id,
                'tenant_id' => $tenant->id
            ]);

            $user->current_tenant_id = $tenant->id;
            $user->save();

            session(['tenant_id' => $tenant->id]);

            DB::commit();

            return $this->jsonCreated('You have successfully joined the organization as a member');
        } catch (\Exception $e) {
            // Rollback the transaction on error
            DB::rollBack();
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

            $isAdmin = $user
                ->roles()
                ->wherePivot('tenant_id', $tenant->id)
                ->where('name', 'administrator')
                ->exists();

            if ($isAdmin) {
                return $this->jsonUnprocessable('Administrators cannot leave the organization.');
            }

            DB::beginTransaction();

            $tenant->users()->detach($user->id);
            $user->roles()->wherePivot('tenant_id', $tenant->id)->detach();

            $nextTenant = $user->tenants()->orderBy('created_at')->first();

            if ($nextTenant) {
                $user->update(['current_tenant_id' => $nextTenant->id]);
                $request->session()->put('tenant_id', $nextTenant->id);
            } else {
                $user->update(['current_tenant_id' => null]);
                $request->session()->forget('tenant_id');
            }

            DB::commit();

            return $this->jsonSuccess('You have successfully left the organization');
        } catch (\Exception $e) {
            DB::rollBack();
            if (app()->environment('local')) {
                return $this->jsonServerError($e->getMessage());
            }
            return $this->jsonServerError('Failed to leave organization.');
        }
    }

    /**
     * @param Request $request
     * @return JsonResponse|Response
     */
    public function switch(Request $request): Response|JsonResponse
    {
        try {
            $request->validate([
                'tenant_id' => 'required|exists:tenants,id',
            ]);

            $user = Auth::user();
            $tenant = Tenant::find($request->tenant_id);

            if (!$tenant) {
                return $this->jsonServerError('Tenant not found.');
            }

            if ($user->tenants->contains('id', $tenant->id)) {
                DB::beginTransaction();

                $user->update(['current_tenant_id' => $tenant->id]);
                $tenant->makeCurrent();
                $request->session()->put('tenant_id', $tenant->id);

                DB::commit();
                return $this->jsonNoContent();
            }

            return $this->jsonServerError('You do not have access to this tenant.');
        } catch (\Exception $e) {
            DB::rollBack();

            if (app()->environment('local')) {
                return $this->jsonServerError($e->getMessage());
            }

            return $this->jsonServerError('Failed to switch tenant. Please try again later.');
        }
    }

    /**
     * @param Tenant $tenant
     * @return Response|JsonResponse
     */
    public function getTenantSettings(Tenant $tenant): Response|JsonResponse
    {
        try {
            $tenant->load([
                'address',
                'roles.permissions'
            ]);

            $allPermissions = Permission::all();

            $tenantData = $tenant->toArray();
            $tenantData['logo_url'] = $tenant->getLogoUrl();
            $tenantData['permissions'] = $allPermissions;

            return $this->json($tenantData);
        } catch (\Exception $ex) {
            if (app()->environment('local')) {
                return response()->json([
                    'message' => 'Failed to retrieve tenant.',
                    'error' => $ex->getMessage(),
                ], 500);
            }
            return $this->jsonServerError('Failed to retrieve tenant.');
        }
    }

    /**
     * Update the basic information of a tenant
     *
     * @param Request $request
     * @param Tenant $tenant
     * @return JsonResponse|Response
     */
    public function updateBasicInfo(Request $request, Tenant $tenant): Response|JsonResponse
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'domain' => 'required|string|max:255|unique:tenants,domain,' . $tenant->id,
                'address' => 'required|array',
                'address.street_address' => 'required|string|max:255',
                'address.suburb' => 'required|string|max:255',
                'address.city' => 'required|string|max:255',
                'address.province' => 'required|string|max:255',
                'address.postal_code' => 'required|string|max:20',
            ]);

            DB::beginTransaction();

            // Update tenant basic info
            $tenant->update([
                'name' => $validated['name'],
                'domain' => $validated['domain'],
            ]);

            // Update or create address
            if ($tenant->address) {
                $tenant->address()->update($validated['address']);
            } else {
                $tenant->address()->create($validated['address']);
            }

            DB::commit();

            return $this->jsonSuccess('Tenant information updated successfully');
        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();

            return $this->jsonUnprocessable($e->errors());
        } catch (\Exception $e) {
            DB::rollBack();

            if (app()->environment('local')) {
                return response()->json([
                    'message' => 'Failed to update tenant information',
                    'error' => $e->getMessage(),
                ], 500);
            }

            return $this->jsonServerError('Failed to update tenant information. Please try again later.');
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
