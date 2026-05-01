<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Active authentication method
    |--------------------------------------------------------------------------
    |
    | Allowed values: "none", "account", "keycloak".
    |
    */
    'method' => strtolower((string) env('AUTH_METHOD', 'none')),

    'account' => [
        'login' => env('ACCOUNT_LOGIN'),
        'password' => env('ACCOUNT_PASSWORD'),
        'recaptcha_site_key' => env('RECAPTCHA_CLIENTID'),
        'recaptcha_secret' => env('RECAPTCHA_CLIENTSECRET'),
    ],

    'keycloak' => [
        'base_url' => rtrim((string) env('KEYCLOAK_BASE_URL'), '/'),
        'realm' => env('KEYCLOAK_REALM'),
        'client_id' => env('KEYCLOAK_CLIENT_ID'),
        'client_secret' => env('KEYCLOAK_CLIENT_SECRET'),
        'redirect_uri' => env('KEYCLOAK_REDIRECT_URI'),
        'allowed_email' => env('KEYCLOAK_EMAIL_ACCOUNT'),
    ],
];
