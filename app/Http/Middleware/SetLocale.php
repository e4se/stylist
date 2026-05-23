<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $supportedLocales = config('app.supported_locales', ['en']);

        if (! is_array($supportedLocales)) {
            $supportedLocales = ['en'];
        }

        $locale = $request->user()?->locale;

        if (! is_string($locale) || ! in_array($locale, $supportedLocales, true)) {
            $configuredLocale = config('app.locale', 'en');
            $locale = is_string($configuredLocale) ? $configuredLocale : 'en';
        }

        App::setLocale($locale);

        return $next($request);
    }
}
