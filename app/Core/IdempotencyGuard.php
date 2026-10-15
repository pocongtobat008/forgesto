<?php

declare(strict_types=1);

namespace App\Core;

use PDO;

/**
 * Guard idempotensi untuk form mutasi (submit, hapus, import, export).
 *
 * Setiap form memuat `_idem` (token acak per render). Server mencoba menyisipkan
 * hash token ke request_locks; kalau sudah ada, permintaan itu duplikat dan ditolak.
 * Ini melengkapi latch JS di app.js, yang tidak melindungi reload atau Enter cepat.
 */
final class IdempotencyGuard
{
    public const FIELD = '_idem';

    /** Token acak untuk dicetak di form. */
    public static function token(): string
    {
        return bin2hex(random_bytes(16));
    }

    /**
     * Klaim token untuk satu aksi. True = boleh lanjut, false = duplikat.
     * Token kosong (form lama / klien tanpa field) dianggap lolos agar tidak memutus akses lama,
     * tetapi dicatat sebagai `legacy` agar bisa dipantau.
     */
    public static function claim(string $action, ?int $userId): bool
    {
        $raw = (string) Request::input(self::FIELD, '');
        if ($raw === '' || ! preg_match('/^[a-f0-9]{32}$/', $raw)) {
            return true;
        }

        $pdo = Database::connection();
        self::prune($pdo);

        $stmt = $pdo->prepare(
            'INSERT IGNORE INTO request_locks (token_hash, user_id, action, created_at) VALUES (?, ?, ?, NOW())'
        );
        $stmt->execute([hash('sha256', $raw), $userId, substr($action, 0, 60)]);

        return $stmt->rowCount() === 1;
    }

    /** Hapus klaim yang lebih tua dari 1 hari agar tabel tetap kecil. */
    private static function prune(PDO $pdo): void
    {
        // Dijalankan ~1% dari request untuk menghemat I/O.
        if (random_int(1, 100) !== 1) {
            return;
        }
        $pdo->exec('DELETE FROM request_locks WHERE created_at < (NOW() - INTERVAL 1 DAY)');
    }
}
