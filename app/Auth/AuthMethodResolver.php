<?php

namespace App\Auth;

class AuthMethodResolver
{
    public const NONE = 'none';
    public const ACCOUNT = 'account';
    public const KEYCLOAK = 'keycloak';

    public static function current(): string
    {
        $method = strtolower((string) config('auth_method.method', self::NONE));
        return in_array($method, [self::NONE, self::ACCOUNT, self::KEYCLOAK], true)
            ? $method
            : self::NONE;
    }

    public static function isNone(): bool
    {
        return self::current() === self::NONE;
    }

    public static function isAccount(): bool
    {
        return self::current() === self::ACCOUNT;
    }

    public static function isKeycloak(): bool
    {
        return self::current() === self::KEYCLOAK;
    }

    public static function isAdmin(): bool
    {
        return (bool) session('admin', false);
    }

    public static function loginUrl(): ?string
    {
        return match (self::current()) {
            self::ACCOUNT => route('auth.login.show'),
            self::KEYCLOAK => route('auth.keycloak.redirect'),
            default => null,
        };
    }
}
