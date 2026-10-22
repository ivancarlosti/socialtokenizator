<?php

// pdo_mysql is optional at runtime, so driver-specific constants must only be
// touched when the driver is actually loaded (a bare CLI may not have it).
$mysqlOptions = [];

if (extension_loaded('pdo_mysql')) {
    // PDO::MYSQL_ATTR_SSL_CA is deprecated as of PHP 8.5. The replacement,
    // Pdo\Mysql::ATTR_SSL_CA, only exists on PHP >= 8.4, so pick whichever
    // constant is available (the project still supports PHP 8.3).
    $sslCa = defined('Pdo\Mysql::ATTR_SSL_CA')
        ? Pdo\Mysql::ATTR_SSL_CA
        : PDO::MYSQL_ATTR_SSL_CA;

    $mysqlOptions = array_filter([$sslCa => env('MYSQL_ATTR_SSL_CA')]);
}

return [
    'default' => env('DB_CONNECTION', 'mysql'),

    'connections' => [
        'mysql' => [
            'driver' => 'mysql',
            'url' => env('DB_URL'),
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '3306'),
            'database' => env('DB_DATABASE', 'forge'),
            'username' => env('DB_USERNAME', 'forge'),
            'password' => env('DB_PASSWORD', ''),
            'unix_socket' => env('DB_SOCKET', ''),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => 'InnoDB',
            'options' => $mysqlOptions,
        ],
    ],

    'migrations' => [
        'table' => 'migrations',
        'update_date_on_publish' => true,
    ],

    'redis' => [],
];
