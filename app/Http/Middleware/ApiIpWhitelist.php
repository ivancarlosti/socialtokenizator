<?php

namespace App\Http\Middleware;

use App\Models\Setting;
use App\Support\IpWhitelist;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class ApiIpWhitelist
{
    public function handle(Request $request, Closure $next): Response
    {
        $ip = $this->resolveIp($request);
        $allowlist = Setting::get('api_allowed_ips');

        if (! IpWhitelist::isAllowed($ip, $allowlist)) {
            $context = [
                'resolved_ip'     => $ip,
                'remote_addr'     => $request->server('REMOTE_ADDR'),
                'x_forwarded_for' => $request->header('X-Forwarded-For'),
                'allowlist'       => IpWhitelist::normalize($allowlist),
            ];

            Log::warning('API IP allowlist denied request', $context);

            return response()->json([
                'error' => "IP address {$ip} is not allowed to access the API.",
            ], 403);
        }

        return $next($request);
    }

    /**
     * Resolve the client IP for allowlist matching.
     *
     * Laravel's TrustProxies middleware already resolves X-Forwarded-For. This
     * fallback covers upstream proxies that only send X-Real-IP and still leave
     * the request IP as a loopback address.
     */
    private function resolveIp(Request $request): string
    {
        $ip = (string) $request->ip();

        if ($ip === '127.0.0.1' || $ip === '::1') {
            foreach (['X-Forwarded-For', 'X-Real-IP'] as $header) {
                $value = $request->header($header);

                if (! is_string($value) || trim($value) === '') {
                    continue;
                }

                foreach (explode(',', $value) as $candidate) {
                    $candidate = trim($candidate);

                    if (filter_var($candidate, FILTER_VALIDATE_IP)) {
                        return $candidate;
                    }
                }
            }
        }

        return $ip;
    }
}
