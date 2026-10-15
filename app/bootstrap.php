<?php

/**
 * StockForge - Application bootstrap.
 *
 * Registers a lightweight PSR-4 style autoloader for the App\ namespace,
 * loads configuration, helpers and starts the session.
 */

declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '1');

/*
|--------------------------------------------------------------------------
| Database read timeout
|--------------------------------------------------------------------------
| mysqlnd ships with a network read timeout of 86400 seconds (24 hours), so a
| MySQL server that accepts the TCP connection but never completes the
| handshake makes the page "load" forever with no output. Lower it so such a
| stall surfaces as a normal error page instead.
| Inert (harmless) when pdo_mysql is built against libmysqlclient.
*/
ini_set('mysqlnd.net_read_timeout', '15');

define('BASE_PATH', dirname(__DIR__));
define('APP_PATH', BASE_PATH . '/app');
define('PUBLIC_PATH', BASE_PATH . '/public');
define('STORAGE_PATH', BASE_PATH . '/storage');

/*
|--------------------------------------------------------------------------
| Autoloader (App\ => app/)
|--------------------------------------------------------------------------
*/
spl_autoload_register(static function (string $class): void {
    $prefix = 'App\\';

    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return;
    }

    $relative = substr($class, strlen($prefix));
    $file = APP_PATH . '/' . str_replace('\\', '/', $relative) . '.php';

    if (is_file($file)) {
        require_once $file;
    }
});

/*
|--------------------------------------------------------------------------
| Configuration store
|--------------------------------------------------------------------------
*/
$GLOBALS['__config'] = [
    'app'      => require BASE_PATH . '/config/app.php',
    'database' => require BASE_PATH . '/config/database.php',
];

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/
require_once APP_PATH . '/Helpers/functions.php';
require_once APP_PATH . '/Helpers/icons.php';

date_default_timezone_set(config('app.timezone', 'UTC'));

/*
|--------------------------------------------------------------------------
| Session
|--------------------------------------------------------------------------
*/
App\Core\Session::start((string) config('app.session_name', 'stockforge_session'));
