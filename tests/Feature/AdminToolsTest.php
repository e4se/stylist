<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Horizon\Horizon;
use Laravel\Telescope\Telescope;
use Tests\TestCase;

class AdminToolsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_tool_capabilities_are_hidden_from_regular_users(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('auth.can.viewHorizon', false)
                ->where('auth.can.viewTelescope', false),
            );
    }

    public function test_admin_tool_capabilities_are_shared_with_admins(): void
    {
        $this->seed(RoleSeeder::class);

        $admin = User::factory()->create();
        $admin->assignRole(Role::Admin);

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('auth.can.viewHorizon', true)
                ->where('auth.can.viewTelescope', true),
            );
    }

    public function test_regular_users_cannot_access_admin_tools(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $this->assertFalse(Horizon::check($this->requestForUser($user)));
        $this->assertFalse(Telescope::check($this->requestForUser($user)));
    }

    public function test_admins_can_access_admin_tools(): void
    {
        $this->seed(RoleSeeder::class);

        $admin = User::factory()->create();
        $admin->assignRole(Role::Admin);

        $this->actingAs($admin);

        $this->assertTrue(Horizon::check($this->requestForUser($admin)));
        $this->assertTrue(Telescope::check($this->requestForUser($admin)));
    }

    public function test_local_environment_can_access_admin_tools_without_admin_role(): void
    {
        $this->app->detectEnvironment(fn (): string => 'local');

        $user = User::factory()->create();

        $this->assertTrue(Horizon::check($this->requestForUser($user)));
        $this->assertTrue(Telescope::check($this->requestForUser($user)));
    }

    protected function requestForUser(User $user): Request
    {
        $request = Request::create('/', 'GET');
        $request->setUserResolver(fn (): User => $user);

        return $request;
    }
}
