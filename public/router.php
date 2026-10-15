<?php

/**
 * Router script for the PHP built-in development server.
 *
 * Run with:
 *   php -S localhost:8000 -t public public/router.php
 *
 * Static files (CSS/JS/images) are served directly by the server; everything
 * else is handed to the front controller.
 */

declare(strict_types=1);

$path = (string) (parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/');
$file = __DIR__ . $path;

if ($path !== '/' && is_file($file)) {
    // Let the built-in server serve the requested asset.
    return false;
}

require __DIR__ . '/index.php';
