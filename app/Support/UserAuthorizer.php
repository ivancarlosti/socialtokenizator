<?php

namespace App\Support;

use App\Models\Image;
use App\Models\User;

class UserAuthorizer
{
    /**
     * Normalize an email address for storage and comparison.
     */
    public static function normalizeEmail(string $email): string
    {
        return strtolower(trim($email));
    }

    /**
     * Find a user by their normalized email address.
     */
    public static function findByEmail(string $email): ?User
    {
        $email = self::normalizeEmail($email);
        if ($email === '') {
            return null;
        }

        return User::query()->where('email', $email)->first();
    }

    /**
     * Authorize a successfully-authenticated email for an admin session.
     *
     * - If the user already exists, they are allowed in.
     * - If the users table is empty, this is the "first login": the user is seeded
     *   and all posts without an author are assigned to them (backward compatibility).
     * - Otherwise the email is not registered yet and access is refused.
     *
     * Returns null when login should be refused.
     */
    public static function authorizeLogin(string $email): ?User
    {
        $email = self::normalizeEmail($email);
        if ($email === '') {
            return null;
        }

        $user = self::findByEmail($email);
        if ($user) {
            return $user;
        }

        if (User::query()->count() === 0) {
            $user = User::query()->create(['email' => $email]);

            Image::query()
                ->whereNull('author_id')
                ->update(['author_id' => $user->id]);

            return $user;
        }

        return null;
    }

    /**
     * Verify an email/password pair against the comma-separated ACCOUNT_* lists.
     */
    public static function accountCredentialsMatch(string $email, string $password): bool
    {
        $email = self::normalizeEmail($email);
        $logins = (array) config('auth_method.account.login', []);
        $passwords = (array) config('auth_method.account.password', []);

        foreach ($logins as $index => $login) {
            if (self::normalizeEmail((string) $login) !== $email) {
                continue;
            }

            $expected = (string) ($passwords[$index] ?? '');
            if ($expected === '') {
                return false;
            }

            $info = password_get_info($expected);
            return ($info['algo'] ?? null)
                ? password_verify($password, $expected)
                : hash_equals($expected, $password);
        }

        return false;
    }

    /**
     * Check whether a Keycloak email is allowed by KEYCLOAK_EMAIL_ACCOUNT.
     * Entries may be full email addresses or bare domains.
     */
    public static function isKeycloakEmailAllowed(string $email): bool
    {
        $email = self::normalizeEmail($email);
        if ($email === '') {
            return false;
        }

        $allowed = (array) config('auth_method.keycloak.allowed_email', []);

        foreach ($allowed as $entry) {
            $entry = strtolower(trim((string) $entry));
            if ($entry === '') {
                continue;
            }

            // Full email: exact match.
            if (str_contains($entry, '@')) {
                if ($entry === $email) {
                    return true;
                }
                continue;
            }

            // Bare domain: match any address under that domain.
            $domain = ltrim($entry, '.');
            if ($domain !== '' && str_ends_with($email, '@' . $domain)) {
                return true;
            }
        }

        return false;
    }
}
