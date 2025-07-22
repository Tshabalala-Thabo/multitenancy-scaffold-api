<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Models\User;
use App\Models\Address;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

class TenantController extends Controller
{
    /**
     * Display a listing of the tenants.
     */
    public function index()
    {
        return response()->json([
            'tenants' => Tenant::with('address')->get()->map(function ($tenant) {
                return array_merge($tenant->toArray(), [
                    'logo_url' => $tenant->getLogoUrl()
                ]);
            }),
        ]);
    }

    /**
     * Store a newly created tenant in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'unique:tenants,slug'],
            'domain' => ['nullable', 'string', 'max:255', 'unique:tenants,domain'],
            'logo' => ['nullable', 'image', 'max:2048'],
            // Address validation
            'address' => ['required', 'array'],
            'address.street_address' => ['required', 'string', 'max:255'],
            'address.suburb' => ['required', 'string', 'max:255'],
            'address.city' => ['required', 'string', 'max:255'],
            'address.province' => ['required', 'string', 'max:255'],
            'address.postal_code' => ['required', 'string', 'max:10'],
            // Administrator details
            'admin_name' => ['required', 'string', 'max:255'],
            'admin_email' => ['required', 'email', 'unique:users,email'],
            'admin_password' => ['required', 'string', 'min:8'],
        ]);

        try {
            DB::beginTransaction();

            // Create the tenant
            $tenant = Tenant::create([
                'name' => $validated['name'],
                'slug' => Str::slug($validated['slug']),
                'domain' => $validated['domain'],

            ]);

            // Handle logo upload if provided
            if ($request->hasFile('logo')) {
                $tenant->storeLogo($request->file('logo'));
            }

            // Create address for tenant
            $tenant->address()->create($validated['address']);

            // Create administrator user
            $admin = User::create([
                'name' => $validated['admin_name'],
                'email' => $validated['admin_email'],
                'password' => Hash::make($validated['admin_password']),
            ]);

            // Attach user to tenant
            $tenant->users()->attach($admin->id);

            // Assign administrator role
            $adminRole = Role::firstOrCreate(['name' => 'Administrator', 'team_id' => $tenant->id]);
            $admin->assignRole($adminRole);

            // Update address if provided
            if (isset($validated['address'])) {
                $tenant->address()->update($validated['address']);
            }

            DB::commit();

            return response()->json([
                'message' => 'Tenant created successfully',
                'tenant' => array_merge($tenant->toArray(), [
                    'logo_url' => $tenant->getLogoUrl()
                ]),
                'administrator' => $admin,
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to create tenant',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified tenant.
     */
    public function show(Tenant $tenant)
    {
        return response()->json([
            'tenant' => $tenant,
        ]);
    }

    /**
     * Update the specified tenant in storage.
     */
    public function update(Request $request, Tenant $tenant)
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'slug' => ['sometimes', 'string', 'max:255', Rule::unique('tenants', 'slug')->ignore($tenant->id)],
            'domain' => ['nullable', 'string', 'max:255', Rule::unique('tenants', 'domain')->ignore($tenant->id)],
            'logo' => ['nullable', 'image', 'max:2048'],
            // Address validation
            'address' => ['sometimes', 'array'],
            'address.street_address' => ['sometimes', 'string', 'max:255'],
            'address.suburb' => ['sometimes', 'string', 'max:255'],
            'address.city' => ['sometimes', 'string', 'max:255'],
            'address.province' => ['sometimes', 'string', 'max:255'],
            'address.postal_code' => ['sometimes', 'string', 'max:10'],
        ]);

        try {
            DB::beginTransaction();

            // Handle logo upload if provided
            if ($request->hasFile('logo')) {
                $tenant->storeLogo($request->file('logo'));
            }

            // Update the tenant
            $tenant->update([
                'name' => $validated['name'] ?? $tenant->name,
                'slug' => isset($validated['slug']) ? Str::slug($validated['slug']) : $tenant->slug,
                'domain' => $validated['domain'] ?? $tenant->domain,

            ]);

            DB::commit();

            return response()->json([
                'message' => 'Tenant updated successfully',
                'tenant' => array_merge($tenant->toArray(), [
                    'logo_url' => $tenant->getLogoUrl()
                ]),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to update tenant',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove the specified tenant from storage.
     */
    public function destroy(Tenant $tenant)
    {
        // Check if tenant has users before deleting
        if ($tenant->users()->count() > 0) {
            return response()->json([
                'message' => 'Cannot delete tenant with associated users',
            ], 422);
        }

        $tenant->delete();

        return response()->json([
            'message' => 'Tenant deleted successfully',
        ]);
    }
}