<?php

return [
    'keycloak' => [
        'client_id' => env('KEYCLOAK_CLIENT_ID'),
        'client_secret' => env('KEYCLOAK_CLIENT_SECRET'),
        'redirect' => env('KEYCLOAK_REDIRECT_URI'),
        'base_url' => rtrim((string) env('KEYCLOAK_BASE_URL'), '/'),
        'realms' => env('KEYCLOAK_REALM'),
    ],

    'ai' => [
        'api_key' => env('AI_API_KEY'),
        'api_url' => env('AI_API_URL'),
        'model'   => env('AI_MODEL'),
    ],
];
