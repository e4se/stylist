<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class BreadcrumbsTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_shares_route_bound_breadcrumbs(): void
    {
        $user = User::factory()->create();

        $this
            ->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('breadcrumbs.0.title', 'Dashboard')
                ->where('breadcrumbs.0.url', route('dashboard'))
                ->where('breadcrumbs.0.current', true),
            );
    }

    public function test_wardrobe_shares_route_bound_breadcrumbs(): void
    {
        $user = User::factory()->create();

        $this
            ->actingAs($user)
            ->get(route('wardrobe.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('breadcrumbs.0.title', 'Wardrobe')
                ->where('breadcrumbs.0.url', route('wardrobe.index'))
                ->where('breadcrumbs.0.current', true),
            );
    }

    public function test_wardrobe_breadcrumb_title_is_translated_on_the_server(): void
    {
        $user = User::factory()->create(['locale' => 'ru']);

        $this
            ->actingAs($user)
            ->get(route('wardrobe.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('breadcrumbs.0.title', 'Гардероб')
                ->where('breadcrumbs.0.url', route('wardrobe.index'))
                ->where('breadcrumbs.0.current', true),
            );
    }

    public function test_settings_pages_share_nested_route_bound_breadcrumbs(): void
    {
        $user = User::factory()->create();

        $this
            ->actingAs($user)
            ->get(route('profile.edit'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('breadcrumbs.0.title', 'Settings')
                ->where('breadcrumbs.0.url', route('settings.index'))
                ->where('breadcrumbs.1.title', 'Profile settings')
                ->where('breadcrumbs.1.url', route('profile.edit'))
                ->where('breadcrumbs.1.current', true),
            );
    }

    public function test_security_settings_page_shares_nested_route_bound_breadcrumbs(): void
    {
        $user = User::factory()->create();

        $this
            ->actingAs($user)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->get(route('security.edit'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('breadcrumbs.0.title', 'Settings')
                ->where('breadcrumbs.1.title', 'Security settings')
                ->where('breadcrumbs.1.url', route('security.edit'))
                ->where('breadcrumbs.1.current', true),
            );
    }

    public function test_appearance_settings_page_shares_nested_route_bound_breadcrumbs(): void
    {
        $user = User::factory()->create();

        $this
            ->actingAs($user)
            ->get(route('appearance.edit'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('breadcrumbs.0.title', 'Settings')
                ->where('breadcrumbs.1.title', 'Appearance settings')
                ->where('breadcrumbs.1.url', route('appearance.edit'))
                ->where('breadcrumbs.1.current', true),
            );
    }

    public function test_breadcrumb_titles_are_translated_on_the_server(): void
    {
        $user = User::factory()->create(['locale' => 'ru']);

        $this
            ->actingAs($user)
            ->get(route('profile.edit'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('breadcrumbs.0.title', 'Настройки')
                ->where('breadcrumbs.1.title', 'Настройки профиля'),
            );
    }
}
