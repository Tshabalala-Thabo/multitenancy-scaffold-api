<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Create basic permissions
        $permissions = [
            'view dashboard',
            'manage users',
            'create announcement',
            'view announcements',
        ];

        // foreach ($permissions as $perm) {
        //     Permission::firstOrCreate([
        //         'name' => $perm,
        //         'guard_name' => 'web',
        //     ]);
        // }

        // 2. Create Super Admin Role
        $superAdminRole = Role::firstOrCreate([
            'name' => 'super_admin',
            'guard_name' => 'web',
        ]);

        // Give super admin all permissions
        // $superAdminRole->syncPermissions(Permission::all());

        // 3. Create Super Admin User (not tied to a tenant)
        $superAdminUser = User::firstOrCreate(
            ['email' => 'super@admin.com'],
            [
                'name' => 'Super Admin',
                'last_name' => 'User',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );

        if ($superAdminRole && !$superAdminUser->hasRole($superAdminRole->name)) {
            $superAdminUser->assignRole($superAdminRole->name);
        }

        // $superAdminUser->assignRole($superAdminRole);

        // 4. Loop through all tenants and seed tenant-scoped roles & assign permissions
        // Tenant::all()->each(function ($tenant) use ($permissions) {
        //     tenancy()->initialize($tenant);

        //     // Create tenant-specific roles
        //     $adminRole = Role::firstOrCreate([
        //         'name' => 'admin',
        //         'guard_name' => 'web',
        //         'tenant_id' => $tenant->id,
        //     ]);

        //     $memberRole = Role::firstOrCreate([
        //         'name' => 'member',
        //         'guard_name' => 'web',
        //         'tenant_id' => $tenant->id,
        //     ]);

        //     // Assign relevant permissions to roles
        //     $adminRole->syncPermissions([
        //         'view dashboard',
        //         'manage users',
        //         'create announcement',
        //         'view announcements',
        //     ]);

        //     $memberRole->syncPermissions([
        //         'view dashboard',
        //         'view announcements',
        //     ]);

        //     tenancy()->end();
        // });
    }
}
