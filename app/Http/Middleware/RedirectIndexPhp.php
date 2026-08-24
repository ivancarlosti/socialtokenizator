<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RedirectIndexPhp
{
    /**
     * Redirect /index.php and /index.php/... to their clean canonical URLs.
     *
     * - /index.php          -> /
     * - /index.php/p/{id}   -> /p/{id}
     * - query strings are preserved.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $uri = $request->getRequestUri();

        if (str_starts_with($uri, '/index.php')) {
            $target = substr($uri, strlen('/index.php'));

            if ($target === '') {
                $target = '/';
            } elseif ($target[0] !== '/') {
                // Handles /index.php?foo=bar -> /?foo=bar
                $target = '/' . $target;
            }

            return redirect($target, 301);
        }

        return $next($request);
    }
}
