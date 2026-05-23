<?php

namespace Tests\Feature\Settings;

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\User;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ProfileUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_is_displayed(): void
    {
        $user = User::factory()->create(['locale' => 'ru']);

        $response = $this
            ->actingAs($user)
            ->get(route('profile.edit'));

        $response
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('settings/profile')
                ->where('auth.user.locale', 'ru'),
            );
    }

    public function test_profile_information_can_be_updated(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch(route('profile.update'), [
                'name' => 'Test User',
                'email' => 'test@example.com',
                'locale' => 'ru',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('profile.edit'))
            ->assertInertiaFlash('toast.message', 'Профиль обновлен.');

        $user->refresh();

        $this->assertSame('Test User', $user->name);
        $this->assertSame('test@example.com', $user->email);
        $this->assertSame('ru', $user->locale);
        $this->assertNull($user->email_verified_at);
    }

    public function test_invalid_locale_is_rejected(): void
    {
        $user = User::factory()->create(['locale' => 'en']);

        $response = $this
            ->actingAs($user)
            ->from(route('profile.edit'))
            ->patch(route('profile.update'), [
                'name' => 'Test User',
                'email' => $user->email,
                'locale' => 'fr',
            ]);

        $response
            ->assertSessionHasErrors('locale')
            ->assertRedirect(route('profile.edit'));

        $this->assertSame('en', $user->refresh()->locale);
    }

    public function test_backend_uses_the_authenticated_users_locale(): void
    {
        $user = User::factory()->create(['locale' => 'ru']);

        $this
            ->actingAs($user)
            ->get(route('profile.edit'))
            ->assertOk();

        $this->assertSame('ru', app()->getLocale());
    }

    public function test_user_preferred_locale_uses_the_stored_locale(): void
    {
        $user = User::factory()->create(['locale' => 'ru']);

        $this->assertInstanceOf(HasLocalePreference::class, $user);
        $this->assertSame('ru', $user->preferredLocale());
    }

    public function test_inertia_version_includes_the_authenticated_users_locale(): void
    {
        $user = User::factory()->create(['locale' => 'en']);
        $middleware = app(HandleInertiaRequests::class);
        $request = Request::create(route('profile.edit'));
        $request->setUserResolver(fn (): User => $user);

        $this->assertStringEndsWith(':en', $middleware->version($request));

        $user->forceFill(['locale' => 'ru'])->save();

        $this->assertStringEndsWith(':ru', $middleware->version($request));
    }

    public function test_locale_change_forces_a_full_reload_on_stale_inertia_visits(): void
    {
        $user = User::factory()->create(['locale' => 'ru']);
        $middleware = app(HandleInertiaRequests::class);
        $request = Request::create(route('dashboard'));
        $request->setUserResolver(fn (): User => $user);
        $oldVersion = $middleware->version($request);

        $user->forceFill(['locale' => 'en'])->save();

        $this
            ->actingAs($user)
            ->withHeaders([
                'X-Inertia' => 'true',
                'X-Inertia-Version' => $oldVersion,
                'X-Requested-With' => 'XMLHttpRequest',
            ])
            ->get(route('dashboard'))
            ->assertConflict()
            ->assertHeader('X-Inertia-Location', route('dashboard'));
    }

    public function test_email_verification_status_is_unchanged_when_the_email_address_is_unchanged(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch(route('profile.update'), [
                'name' => 'Test User',
                'email' => $user->email,
                'locale' => $user->locale,
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('profile.edit'));

        $this->assertNotNull($user->refresh()->email_verified_at);
    }

    public function test_user_can_delete_their_account(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->delete(route('profile.destroy'), [
                'password' => 'password',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('home'));

        $this->assertGuest();
        $this->assertNull($user->fresh());
    }

    public function test_correct_password_must_be_provided_to_delete_account(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from(route('profile.edit'))
            ->delete(route('profile.destroy'), [
                'password' => 'wrong-password',
            ]);

        $response
            ->assertSessionHasErrors('password')
            ->assertRedirect(route('profile.edit'));

        $this->assertNotNull($user->fresh());
    }
}
