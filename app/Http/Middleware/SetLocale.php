<?php

namespace App\Http\Middleware;

use App\Support\Locales;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = (string) $request->session()->get(Locales::SESSION_KEY, '');

        if (! Locales::isSupported($locale)) {
            $locale = Locales::default();
        }

        app()->setLocale($locale);

        return $next($request);
    }
}
