<?php

namespace App\Http\Middleware;

use App\Models\Setting;
use App\Support\IpWhitelist;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApiIpWhitelist
{
    public function handle(Request $request, Closure $next): Response
    {
        $ip = $request->ip();
        $allowlist = Setting::get('api_allowed_ips');

        if (! IpWhitelist::isAllowed($ip, $allowlist)) {
            return response()->json([
                'error' => 'IP address is not allowed to access the API.',
            ], 403);
        }

        return $next($request);
    }
}
