<?php

namespace Tests\Feature\Roles;

use App\Enums\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
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

    public function test_database_seeder_creates_alexander_as_admin(): void
    {
        $this->seed(DatabaseSeeder::class);

        $user = User::query()
            ->where('email', 'e4se96@gmail.com')
            ->firstOrFail();

        $this->assertSame('Alexander', $user->name);
        $this->assertTrue(Hash::check('12798841', $user->password));
        $this->assertTrue($user->hasRole(Role::Admin));
    }

    public function test_admin_role_does_not_grant_unspecified_gate_checks(): void
    {
        $this->seed(RoleSeeder::class);

        $admin = User::factory()->create();
        $user = User::factory()->create();

        $admin->assignRole(Role::Admin);

        $this->assertTrue($admin->hasRole(Role::Admin));
        $this->assertFalse(Gate::forUser($admin)->allows('manage-admin-area'));
        $this->assertFalse(Gate::forUser($user)->allows('manage-admin-area'));
    }
}
