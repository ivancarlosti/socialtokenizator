<?php

namespace App\Http\Middleware;

use App\Models\Setting;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApiTokenAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        if (! $token) {
            return response()->json([
                'error' => 'Missing API token. Provide it as: Authorization: Bearer <token>',
            ], 401);
        }

        $storedToken = Setting::get('api_token');

        if (! $storedToken || ! hash_equals($storedToken, $token)) {
            return response()->json([
                'error' => 'Invalid API token.',
            ], 401);
        }

        return $next($request);
    }
}
