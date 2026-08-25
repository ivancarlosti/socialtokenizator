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
     * - query strings and subdirectory installs are preserved.
     *
     * Detection is based on Symfony's computed base URL rather than the raw
     * REQUEST_URI, which avoids matching internal front-controller rewrites
     * and therefore avoids redirect loops.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $baseUrl = $request->getBaseUrl();

        if (! str_ends_with($baseUrl, 'index.php')) {
            return $next($request);
        }

        $root = rtrim(substr($baseUrl, 0, -strlen('index.php')), '/');
        $pathInfo = $request->getPathInfo();

        $target = $root;

        if ($pathInfo !== '' && $pathInfo !== '/') {
            $target .= '/' . ltrim($pathInfo, '/');
        }

        if ($target === '') {
            $target = '/';
        }

        $query = $request->getQueryString();
        if ($query !== null && $query !== '') {
            $target .= '?' . $query;
        }

        return redirect($target, 301);
    }
}
