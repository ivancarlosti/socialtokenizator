<?php

namespace App\Http\Middleware;

use App\Auth\AuthMethodResolver;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        if (AuthMethodResolver::isNone()) {
            abort(403, 'Uploads are disabled (AUTH_METHOD=none).');
        }

        if (! AuthMethodResolver::isAdmin()) {
            $url = AuthMethodResolver::loginUrl();
            return $url ? redirect($url) : abort(403);
        }

        return $next($request);
    }
}
