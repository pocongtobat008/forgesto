<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Encapsulates the incoming HTTP request.
 */
class Request
{
    private static ?string $basePath = null;
    private static ?string $path = null;

    /**
     * The sub-directory the front controller is served from
     * (empty string when served from the web root).
     */
    public static function basePath(): string
    {
        if (self::$basePath !== null) {
            return self::$basePath;
        }

        $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
        $dir = rtrim(dirname($script), '/.');

        return self::$basePath = $dir;
    }

    /**
     * Current route path, normalised without a leading or trailing slash.
     */
    public static function path(): string
    {
        if (self::$path !== null) {
            return self::$path;
        }

        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
        $uri = (string) (parse_url($uri, PHP_URL_PATH) ?: '/');
        $uri = rawurldecode($uri);

        $base = self::basePath();
        if ($base !== '' && str_starts_with($uri, $base)) {
            $uri = substr($uri, strlen($base));
        }

        if (str_starts_with($uri, '/index.php')) {
            $uri = substr($uri, strlen('/index.php'));
        }

        // Fallback routing for servers without URL rewriting.
        if (isset($_GET['r']) && $_GET['r'] !== '') {
            $uri = '/' . ltrim((string) $_GET['r'], '/');
        }

        return self::$path = "/" . trim($uri, "/");
    }

    public static function method(): string
    {
        $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));

        // Method spoofing for HTML forms: <input name="_method" value="PUT">
        if ($method === 'POST' && isset($_POST['_method'])) {
            $spoofed = strtoupper((string) $_POST['_method']);
            if (in_array($spoofed, ['PUT', 'PATCH', 'DELETE'], true)) {
                return $spoofed;
            }
        }

        return $method;
    }

    public static function isPost(): bool
    {
        return self::method() === 'POST';
    }

    /**
     * A single request input value (POST first, then GET).
     */
    public static function input(string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, $_POST)) {
            return $_POST[$key];
        }

        return $_GET[$key] ?? $default;
    }

    public static function all(): array
    {
        return array_merge($_GET, $_POST);
    }

    public static function string(string $key, string $default = ''): string
    {
        $value = self::input($key, $default);

        return is_string($value) ? trim($value) : (string) $default;
    }

    public static function int(string $key, int $default = 0): int
    {
        $value = self::input($key, $default);

        return is_numeric($value) ? (int) $value : $default;
    }

    public static function float(string $key, float $default = 0): float
    {
        $value = self::input($key, $default);

        return is_numeric($value) ? (float) $value : $default;
    }

    public static function boolean(string $key): bool
    {
        $value = self::input($key);

        return in_array($value, ['1', 1, true, 'on', 'true', 'yes'], true);
    }

    public static function has(string $key): bool
    {
        return array_key_exists($key, $_POST) || array_key_exists($key, $_GET);
    }

    /**
     * @return array{name:string,type:string,tmp_name:string,error:int,size:int}
     */
    public static function file(string $key): ?array
    {
        if (! isset($_FILES[$key]) || ($_FILES[$key]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }

        /** @var array{name:string,type:string,tmp_name:string,error:int,size:int} $file */
        $file = $_FILES[$key];

        return $file;
    }

    public static function isAjax(): bool
    {
        return strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest';
    }
}
