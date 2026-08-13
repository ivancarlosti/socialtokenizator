<?php

namespace App\Support;

use Symfony\Component\HttpFoundation\IpUtils;

class IpWhitelist
{
    /**
     * Parse a multiline allowlist into a clean array of entries.
     * One entry per line; whitespace and empty lines are ignored; duplicates removed.
     *
     * @return string[]
     */
    public static function normalize(?string $raw): array
    {
        $entries = [];

        foreach (preg_split('/\r\n|\r|\n/', (string) $raw) ?: [] as $line) {
            $entry = trim($line);

            if ($entry === '') {
                continue;
            }

            $entries[] = $entry;
        }

        return array_values(array_unique($entries));
    }

    /**
     * Validate a single entry as a plain IPv4/IPv6 address or an IPv4/IPv6 CIDR range.
     */
    public static function isValidEntry(string $entry): bool
    {
        if (filter_var($entry, FILTER_VALIDATE_IP)) {
            return true;
        }

        if (! str_contains($entry, '/')) {
            return false;
        }

        [$ip, $mask] = explode('/', $entry, 2);

        if (! filter_var($ip, FILTER_VALIDATE_IP)) {
            return false;
        }

        if (! ctype_digit($mask)) {
            return false;
        }

        $maxBits = str_contains($ip, ':') ? 128 : 32;

        return (int) $mask >= 0 && (int) $mask <= $maxBits;
    }

    /**
     * Determine whether an IP is allowed by the given raw allowlist.
     * An empty allowlist allows any IP.
     */
    public static function isAllowed(string $ip, ?string $raw): bool
    {
        $entries = self::normalize($raw);

        if ($entries === []) {
            return true;
        }

        foreach ($entries as $entry) {
            if (IpUtils::checkIp($ip, $entry)) {
                return true;
            }
        }

        return false;
    }
}
