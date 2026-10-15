<?php

/**
 * StockForge - Root entry point for shared-hosting / XAMPP htdocs setups.
 *
 * Allows the app to be opened as:
 *   http://localhost/sistock
 * instead of:
 *   http://localhost/sistock/public
 *
 * The real front controller stays in public/index.php; this file only
 * forwards the request. On a VPS/dedicated server you should instead point
 * the virtual host DocumentRoot at the public/ directory and delete this file.
 */

declare(strict_types=1);

require __DIR__ . '/public/index.php';
