<?php

namespace App\Http\Middleware;

use App\Models\User;
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

        $user = User::query()->where('api_token', $token)->first();

        if (! $user) {
            return response()->json([
                'error' => 'Invalid API token.',
            ], 401);
        }

        $request->setUserResolver(fn () => $user);

        return $next($request);
    }
}
