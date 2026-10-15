<?php

/**
 * StockForge - Database configuration (MySQL / MariaDB via PDO).
 *
 * Values can be overridden with environment variables so the same codebase
 * can run on local, staging and production without edits.
 */

return [
    'driver'    => 'mysql',
    'host'      => getenv('DB_HOST') ?: '127.0.0.1',
    'port'      => getenv('DB_PORT') ?: '3306',
    'database'  => getenv('DB_DATABASE') ?: 'stockforge',
    'username'  => getenv('DB_USERNAME') ?: 'root',
    'password'  => getenv('DB_PASSWORD') !== false ? getenv('DB_PASSWORD') : '',
    'charset'   => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
    'options'   => [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        // Fail fast when MySQL is down instead of hanging on the default
        // socket timeout (otherwise the page just spins forever).
        PDO::ATTR_TIMEOUT            => 5,
    ],
];
