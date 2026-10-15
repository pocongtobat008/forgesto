<?php

/**
 * StockForge - Front controller.
 *
 * Point your web server document root at this `public/` directory.
 * For local development you can simply run:
 *   php -S localhost:8000 -t public
 * and open http://localhost:8000
 */

declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

(new App\Core\Application())->run();
