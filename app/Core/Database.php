<?php

declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOException;
use RuntimeException;

/**
 * Lazy PDO connection factory. A single connection is reused per request.
 */
class Database
{
    private static ?PDO $connection = null;

    public static function connection(): PDO
    {
        if (self::$connection instanceof PDO) {
            return self::$connection;
        }

        /** @var array<string, mixed> $config */
        $config = config('database', []);

        $dsn = sprintf(
            '%s:host=%s;port=%s;dbname=%s;charset=%s',
            $config['driver'] ?? 'mysql',
            $config['host'] ?? '127.0.0.1',
            $config['port'] ?? '3306',
            $config['database'] ?? 'stockforge',
            $config['charset'] ?? 'utf8mb4'
        );

        try {
            self::$connection = new PDO(
                $dsn,
                (string) ($config['username'] ?? 'root'),
                (string) ($config['password'] ?? ''),
                (array) ($config['options'] ?? [])
            );
        } catch (PDOException $e) {
            throw new RuntimeException(
                'Tidak dapat terhubung ke database. Periksa konfigurasi di config/database.php. '
                . $e->getMessage(),
                (int) $e->getCode(),
                $e
            );
        }

        return self::$connection;
    }

    public static function transaction(callable $callback): mixed
    {
        $pdo = self::connection();
        $pdo->beginTransaction();

        try {
            $result = $callback($pdo);
            $pdo->commit();

            return $result;
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $e;
        }
    }
}
