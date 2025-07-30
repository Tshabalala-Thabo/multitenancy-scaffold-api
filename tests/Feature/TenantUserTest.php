<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TenantUserTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_join_a_tenant()
    {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->create();

        $response = $this->actingAs($user)->postJson("/api/tenants/{$tenant->id}/join");

        $response->assertStatus(200);
        $this->assertDatabaseHas('tenant_user', [
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
        ]);

        $this->assertTrue($user->hasRole('member'));
    }

    public function test_user_can_leave_a_tenant()
    {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->create();
        $user->tenants()->attach($tenant);

        $response = $this->actingAs($user)->postJson("/api/tenants/{$tenant->id}/leave");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('tenant_user', [
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
        ]);
    }
}
