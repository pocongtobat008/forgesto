<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Thin wrapper around the PHP session superglobal with dot-free keys and
 * flash/old-input support.
 */
class Session
{
    public static function start(string $name = 'PHPSESSID'): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        if (PHP_SAPI !== 'cli') {
            session_name($name);
            session_start();
        }
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public static function put(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public static function has(string $key): bool
    {
        return isset($_SESSION[$key]);
    }

    public static function forget(string ...$keys): void
    {
        foreach ($keys as $key) {
            unset($_SESSION[$key]);
        }
    }

    public static function flush(): void
    {
        $_SESSION = [];
    }

    /**
     * Preserve the submitted input + validation errors for the next request.
     */
    public static function flashInput(array $input, array $errors = []): void
    {
        unset($input['_token'], $input['password'], $input['password_confirmation']);
        self::put('_old', $input);
        self::put('_errors', $errors);
    }

    public static function clearOldInput(): void
    {
        self::forget('_old', '_errors');
    }
}
