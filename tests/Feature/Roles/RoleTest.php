<?php

namespace Tests\Feature\Roles;

use App\Enums\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class RoleTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_role_is_seeded(): void
    {
        $this->seed(RoleSeeder::class);

        $this->assertDatabaseHas('roles', [
            'name' => Role::Admin->value,
            'guard_name' => 'web',
        ]);
    }

    public function test_admin_role_grants_all_gate_checks(): void
    {
        $this->seed(RoleSeeder::class);

        $admin = User::factory()->create();
        $user = User::factory()->create();

        $admin->assignRole(Role::Admin);

        $this->assertTrue($admin->hasRole(Role::Admin));
        $this->assertTrue(Gate::forUser($admin)->allows('manage-admin-area'));
        $this->assertFalse(Gate::forUser($user)->allows('manage-admin-area'));
    }
}
