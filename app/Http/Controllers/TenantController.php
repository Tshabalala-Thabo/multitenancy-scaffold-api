<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Tenant;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use Illuminate\Http\JsonResponse;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class TenantController extends Controller
{
    /**
     * @return JsonResponse|Response
     */
    public function index(): Response|JsonResponse
    {
        try {
            $tenants = Tenant::with(['address', 'users'])->get()->map(function ($tenant) {
                $tenantArray = $tenant->toArray();
                $tenantArray['logo_url'] = $tenant->getLogoUrl();

                $tenantArray['users'] = $tenant->users->map(function ($user) use ($tenant) {
                    $userArray = $user->toArray();

                    // Manually fetch roles for this tenant
                    $userArray['roles'] = $user->roles()
                        ->wherePivot('tenant_id', $tenant->id)
                        ->pluck('name');

                    return $userArray;
                });

                return $tenantArray;
            });

            return $this->json($tenants->toArray());

        } catch (\Exception $e) {
            if (app()->environment('local')) {
                return response()->json([
                    'message' => 'Failed to retrieve tenants.',
                    'error' => $e->getMessage(),
                ], 500);
            }

            return $this->jsonServerError('Failed to retrieve tenants.');
        }
    }

    /**
     * @param Request $request
     * @return Response|JsonResponse
     */
    public function store(Request $request): Response|JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'unique:tenants,slug'],
            'domain' => ['nullable', 'string', 'max:255', 'unique:tenants,domain'],
            'logo' => ['nullable', 'image', 'max:2048'],
            'address' => ['required', 'array'],
            'address.street_address' => ['required', 'string', 'max:255'],
            'address.suburb' => ['required', 'string', 'max:255'],
            'address.city' => ['required', 'string', 'max:255'],
            'address.province' => ['required', 'string', 'max:255'],
            'address.postal_code' => ['required', 'string', 'max:10'],
            'administrators' => ['required', 'array', 'min:1'],
            'administrators.*.name' => ['required', 'string', 'max:255'],
            'administrators.*.email' => ['required', 'email', 'unique:users,email'],
            'administrators.*.password' => ['required', 'string', 'min:8'],
        ]);

        try {
            DB::beginTransaction();

            $tenant = Tenant::create([
                'name' => $validated['name'],
                'slug' => Str::slug($validated['slug']),
                'domain' => $validated['domain'],
            ]);

            if ($request->hasFile('logo')) {
                $tenant->storeLogo($request->file('logo'));
            }

            $tenant->address()->create($validated['address']);

            Log::info('tenant id: ' . $tenant->id);
            $adminRole = Role::firstOrCreate([
                'name' => 'administrator',
                'tenant_id' => $tenant->id,
                'guard_name' => 'web',
            ]);

            foreach ($validated['administrators'] as $adminData) {
                $admin = User::create([
                    'name' => $adminData['name'],
                    'email' => $adminData['email'],
                    'password' => Hash::make($adminData['password']),
                ]);

                $tenant->users()->attach($admin->id);

                // Attach role with tenant_id to pivot table
                $admin->roles()->attach($adminRole->id, ['tenant_id' => $tenant->id]);
            }


            if (isset($validated['address'])) {
                $tenant->address()->update($validated['address']);
            }

            DB::commit();

            $tenant->load(['address', 'users']);

            $tenantArray = $tenant->toArray();
            $tenantArray['logo_url'] = $tenant->getLogoUrl();

            $tenantArray['users'] = $tenant->users->map(function ($user) use ($tenant) {
                $userArray = $user->toArray();

                $userArray['roles'] = $user->roles()
                    ->wherePivot('tenant_id', $tenant->id)
                    ->pluck('name');

                return $userArray;
            });

            return response()->json($tenantArray);
        } catch (\Exception $e) {
            DB::rollBack();

            if (app()->environment('local')) {
                return response()->json([
                    'message' => 'Failed to create tenant.',
                    'error' => $e->getMessage(),
                ], 500);
            }

            return $this->jsonServerError('Failed to create tenant.');
        }
    }

    /**
     * @param Tenant $tenant
     * @return JsonResponse|Response
     */
    public function show(Tenant $tenant): Response|JsonResponse
    {
        try {
            $tenant = Tenant::with('address')->find($tenant->id);
            return $this->json($tenant->toArray());
        } catch (\Exception $ex) {
            if (app()->environment('local')) {
                return response()->json([
                    'message' => 'Failed to create tenant.',
                    'error' => $ex->getMessage(),
                ], 500);
            }
            return $this->jsonServerError('Failed to retrieve tenant.');
        }
    }

    /**
     * @param Request $request
     * @param Tenant $tenant
     * @return JsonResponse|Response
     */
    public function update(Request $request, Tenant $tenant): Response|JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'slug' => ['sometimes', 'string', 'max:255', Rule::unique('tenants', 'slug')->ignore($tenant->id)],
            'domain' => ['nullable', 'string', 'max:255', Rule::unique('tenants', 'domain')->ignore($tenant->id)],
            'logo' => ['nullable', 'image', 'max:2048'],
            'remove_logo' => ['nullable', 'boolean'],
            'address' => ['sometimes', 'array'],
            'address.street_address' => ['sometimes', 'string', 'max:255'],
            'address.suburb' => ['sometimes', 'string', 'max:255'],
            'address.city' => ['sometimes', 'string', 'max:255'],
            'address.province' => ['sometimes', 'string', 'max:255'],
            'address.postal_code' => ['sometimes', 'string', 'max:10'],
            'administrators' => ['sometimes', 'array'],
            'administrators.*.name' => ['required_with:administrators', 'string', 'max:255'],
            'administrators.*.email' => ['required_with:administrators', 'email'],
            'administrators.*.is_new' => ['nullable', 'boolean'],
        ]);

        try {
            DB::beginTransaction();

            Log::debug('Updating logo:', [
                'hasFile' => $request->hasFile('logo'),
                'file' => $request->file('logo'),
            ]);

            // Handle logo upload
            if ($request->hasFile('logo')) {
                log::info('Uploading tenant logo for ID: ' . $tenant->id);
                $tenant->storeLogo($request->file('logo'));
            }

            // Handle logo removal
            if ($request->boolean('remove_logo')) {
                Log::info('Removing tenant logo for ID: ' . $tenant->id);
                $tenant->deleteLogo();
                $tenant->logo_path = null;
                $tenant->save();
            }

            // Update tenant main fields
            $tenant->update([
                'name' => $validated['name'] ?? $tenant->name,
                'slug' => isset($validated['slug']) ? Str::slug($validated['slug']) : $tenant->slug,
                'domain' => $validated['domain'] ?? $tenant->domain,
            ]);

            // Update address if present
            if (isset($validated['address'])) {
                $tenant->address()->update($validated['address']);
            }

            // Update administrators if provided
            if (isset($validated['administrators'])) {
                $adminRole = Role::firstOrCreate([
                    'name' => 'administrator',
                    'tenant_id' => $tenant->id,
                    'guard_name' => 'web',
                ]);

                foreach ($validated['administrators'] as $adminData) {
                    if (!empty($adminData['is_new'] === true)) {
                        // Create new administrator
                        $newAdmin = User::create([
                            'name' => $adminData['name'],
                            'email' => $adminData['email'],
                            'password' => Hash::make($adminData['password'] ?? Str::random(10)),
                        ]);

                        // Attach to tenant and role
                        $tenant->users()->attach($newAdmin->id);
                        $newAdmin->roles()->attach($adminRole->id, ['tenant_id' => $tenant->id]);

                        continue;
                    }

                    // Update existing administrator
                    $existingAdmin = $tenant->users()
                        ->whereHas('roles', function ($q) use ($adminRole) {
                            $q->where('roles.id', $adminRole->id);
                        })
                        ->where('email', $adminData['email'])
                        ->first();

                    if ($existingAdmin) {
                        $existingAdmin->update([
                            'name' => $adminData['name'],
                            'email' => $adminData['email'],
                        ]);
                    }
                }
            }


            DB::commit();

            $tenant->load(['address', 'users']);

            $tenantArray = $tenant->toArray();
            $tenantArray['logo_url'] = $tenant->getLogoUrl();

            $tenantArray['users'] = $tenant->users->map(function ($user) use ($tenant) {
                $userArray = $user->toArray();
                $userArray['roles'] = $user->roles()
                    ->wherePivot('tenant_id', $tenant->id)
                    ->pluck('name');
                return $userArray;
            });

            return $this->json($tenantArray);
        } catch (\Exception $e) {
            DB::rollBack();

            if (app()->environment('local')) {
                return response()->json([
                    'message' => 'Failed to update tenant.',
                    'error' => $e->getMessage(),
                ], 500);
            }

            return $this->jsonServerError('Failed to update tenant.');
        }
    }

    /**
     * @param Tenant $tenant
     * @return JsonResponse|Response
     */
    public function destroy(Tenant $tenant): Response|JsonResponse
    {
        try {
            if ($tenant->users()->count() > 0) {
                return response()->json([
                    'message' => 'Cannot delete tenant with associated users',
                ], 422);
            }

            $tenant->delete();

            return $this->jsonSuccess('Tenant deleted successfully');
        } catch (\Exception $e) {

            if (app()->environment('local')) {
                return response()->json([
                    'message' => 'Failed to create tenant.',
                    'error' => $e->getMessage(),
                ], 500);
            }

            return $this->jsonServerError('Failed to delete tenant.');
        }
    }
}
