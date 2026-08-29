<?php

/*
|--------------------------------------------------------------------------
| Active authentication method
|--------------------------------------------------------------------------
|
| Allowed values: "account", "keycloak".
|
| When AUTH_METHOD is unset or invalid, "account" is used as the default.
|
*/

$parseList = static function (?string $value): array {
    if ($value === null) {
        return [];
    }

    $value = trim($value);
    if ($value === '') {
        return [];
    }

    return array_values(array_filter(
        array_map('trim', explode(',', $value)),
        static fn (string $entry): bool => $entry !== ''
    ));
};

return [
    'method' => strtolower((string) env('AUTH_METHOD', 'account')),

    'account' => [
        // Comma-separated, index-aligned lists. Pair ACCOUNT_LOGIN[i] with ACCOUNT_PASSWORD[i].
        //   ACCOUNT_LOGIN='a@example.com,b@example.com'
        //   ACCOUNT_PASSWORD='hash1,hash2'
        'login' => $parseList(env('ACCOUNT_LOGIN')),
        'password' => $parseList(env('ACCOUNT_PASSWORD')),
        'recaptcha_site_key' => env('RECAPTCHA_CLIENTID'),
        'recaptcha_secret' => env('RECAPTCHA_CLIENTSECRET'),
    ],

    'keycloak' => [
        'base_url' => rtrim((string) env('KEYCLOAK_BASE_URL'), '/'),
        'realm' => env('KEYCLOAK_REALM'),
        'client_id' => env('KEYCLOAK_CLIENT_ID'),
        'client_secret' => env('KEYCLOAK_CLIENT_SECRET'),
        'redirect_uri' => env('KEYCLOAK_REDIRECT_URI'),
        // Comma-separated allowlist. Each entry is either a full email address or a
        // bare domain that matches any address under that domain.
        //   KEYCLOAK_EMAIL_ACCOUNT='youremail@example.com,domain2.com,othermail@otherprovider.com'
        'allowed_email' => $parseList(env('KEYCLOAK_EMAIL_ACCOUNT')),
    ],
];
