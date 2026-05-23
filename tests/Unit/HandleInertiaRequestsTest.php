<?php

namespace Tests\Unit;

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\User;
use Illuminate\Http\Request;
use Tests\TestCase;

class HandleInertiaRequestsTest extends TestCase
{
    public function test_version_uses_configured_locale_for_guests(): void
    {
        config()->set('app.locale', 'de');

        $request = Request::create('/');
        $request->setUserResolver(fn (): null => null);

        $version = (new HandleInertiaRequests)->version($request);

        $this->assertNotNull($version);
        $this->assertStringEndsWith(':de', $version);
    }

    public function test_version_uses_authenticated_user_locale(): void
    {
        config()->set('app.locale', 'de');

        $user = new User(['locale' => 'fr']);
        $request = Request::create('/');
        $request->setUserResolver(fn (): User => $user);

        $version = (new HandleInertiaRequests)->version($request);

        $this->assertNotNull($version);
        $this->assertStringEndsWith(':fr', $version);
    }
}
