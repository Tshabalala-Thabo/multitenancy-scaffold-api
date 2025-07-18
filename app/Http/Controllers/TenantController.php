<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class TenantController extends Controller
{
    /**
     * Display a listing of the tenants.
     */
    public function index()
    {
        return response()->json([
            'tenants' => Tenant::all(),
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
        ]);

        // Create the tenant
        $tenant = Tenant::create([
            'name' => $validated['name'],
            'slug' => Str::slug($validated['slug']),
        ]);

        return response()->json([
            'message' => 'Tenant created successfully',
            'tenant' => $tenant,
        ], 201);
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
        ]);

        // Update the tenant
        $tenant->update([
            'name' => $validated['name'] ?? $tenant->name,
            'slug' => isset($validated['slug']) ? Str::slug($validated['slug']) : $tenant->slug,
        ]);

        return response()->json([
            'message' => 'Tenant updated successfully',
            'tenant' => $tenant,
        ]);
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