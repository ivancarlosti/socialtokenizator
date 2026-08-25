<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RedirectIndexPhp
{
    /**
     * Redirect the bare /index.php front-controller URL to /.
     *
     * /index.php/... is handled by nginx (see build/nginx/default.conf), so
     * this middleware intentionally only handles the root index.php case.
     * Query strings are preserved.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $baseUrl = $request->getBaseUrl();

        if (! str_ends_with($baseUrl, 'index.php')) {
            return $next($request);
        }

        $pathInfo = $request->getPathInfo();

        if ($pathInfo !== '' && $pathInfo !== '/') {
            return $next($request);
        }

        $target = '/';

        $query = $request->getQueryString();
        if ($query !== null && $query !== '') {
            $target .= '?' . $query;
        }

        return redirect($target, 301);
    }
}
