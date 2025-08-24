<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Models\TenantUserBan;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Log;
use App\Services\TenantUserService;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Models\Permission;
use App\Http\Requests\UpdateAccessControlRequest;
use App\Http\Requests\UpdateOrganizationInfoRequest;


class TenantUserController extends Controller
{

    protected TenantUserService $tenantUserService;


    /**
     * @param TenantUserService $tenantUserService
     */
    public function __construct(TenantUserService $tenantUserService)
    {
        $this->tenantUserService = $tenantUserService;
        $this->middleware('tenant_permission:settings:manage')->only(['getTenantSettings', 'updateBasicInfo', 'updateAccessControl']);
    }

    /**
     * @param UpdateAccessControlRequest $request
     * @param Tenant $tenant
     * @return Response
     */
    public function updateAccessControl(UpdateAccessControlRequest $request, Tenant $tenant): Response
    {
        try {
            $validated = $request->validated();
            $this->tenantUserService->updateAccessControl($tenant, $validated);

            return $this->jsonSuccess('Access control settings updated successfully');
        } catch (\Exception $e) {

            if (app()->environment('local')) {
                return $this->jsonServerError($e->getMessage());
            }

            return $this->jsonServerError("Failed to update access control settings");
        }
    }

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

            $activeBan = TenantUserBan::where('tenant_id', $tenant->id)
                ->where('user_id', $user->id)
                ->whereNull('unbanned_at')
                ->first();

            if ($activeBan) {
                return $this->jsonForbidden(
                    "You are currently banned from this organization.",
                );
            }

            if ($tenant->users()->where('user_id', $user->id)->exists()) {
                return $this->jsonUnprocessable('You are already a member of this organization');
            }

            $memberRole = Role::where('name', 'member')
                ->where('tenant_id', $tenant->id)
                ->first();

            if (!$memberRole) {
                return $this->jsonServerError('Member role not found for this organization');
            }

            $tenant->users()->attach($user->id);

            $user->roles()->attach($memberRole->id, [
                'tenant_id' => $tenant->id,
            ]);
            $user->current_tenant_id = $tenant->id;
            $user->save();

            session(['tenant_id' => $tenant->id]);

            DB::commit();

            return $this->jsonCreated('You have successfully joined the organization as a member');
        } catch (\Exception $e) {
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
                return $this->jsonServerError('Organization not found.');
            }

            if ($user->tenants->contains('id', $tenant->id)) {
                DB::beginTransaction();

                $user->update(['current_tenant_id' => $tenant->id]);
                $tenant->makeCurrent();
                $request->session()->put('tenant_id', $tenant->id);

                DB::commit();
                return $this->jsonNoContent();
            }

            return $this->jsonServerError('You do not have access to this organization.');
        } catch (\Exception $e) {
            DB::rollBack();

            if (app()->environment('local')) {
                return $this->jsonServerError($e->getMessage());
            }

            return $this->jsonServerError('Failed to switch organization. Please try again later.');
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
     * Update the basic information of an organization
     *
     * @param UpdateOrganizationInfoRequest $request
     * @param Tenant $tenant
     * @return JsonResponse|Response
     */
    public function updateBasicInfo(UpdateOrganizationInfoRequest $request, Tenant $tenant): Response|JsonResponse
    {
        DB::beginTransaction();

        try {
            $validated = $request->validated();

            $tenantData = $this->tenantUserService->updateOrganizationInfo(
                tenant: $tenant,
                validated: $validated,
                logoFile: $request->file('logo'),
                removeLogo: $request->boolean('remove_logo')
            );

            DB::commit();

            return response()->json([
                'message' => 'Organisation information updated successfully',
                'data' => $tenantData
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            if (app()->environment('local')) {
                return response()->json([
                    'message' => 'Failed to update organisation information',
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ], 500);
            }

            return $this->jsonServerError('Failed to update organisation information. Please try again later.');
        }
    }


    /**
     * @param Tenant $tenant
     * @return Response
     */
    public function getTenantUsers(Tenant $tenant): Response
    {
        try {
            $tenant->load('users.roles');

            $users = $tenant->users->map(function ($user) use ($tenant) {
                $user->setRelation(
                    'roles',
                    $user->roles->where('pivot.tenant_id', $tenant->id)->values()
                );
                return $user;
            });

            return $this->json($users->toArray());
        } catch (\Exception $e) {
            if (app()->environment('local')) {
                return $this->jsonServerError($e->getMessage());
            }
            return $this->jsonServerError('Failed to retrieve users.');
        }
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

        // Check if user is already assigned to this organisation
        if ($tenant->users()->where('user_id', $user->id)->exists()) {
            return response()->json([
                'message' => 'User is already assigned to this organisation',
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
            'message' => 'User assigned to organisation successfully',
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
                'message' => 'User is not assigned to this organisation',
            ], 422);
        }

        // Remove user from tenant
        $tenant->users()->detach($user->id);

        // Remove tenant-specific roles
        $user->roles()->wherePivot('team_id', $tenant->id)->detach();

        return response()->json([
            'message' => 'User removed from organisation successfully',
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
