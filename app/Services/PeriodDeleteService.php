<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Models\ActivityLog;
use RuntimeException;

/**
 * Hapus periode tutup buku (khusus data uji/simulasi). Tiga langkah verifikasi:
 *   1. preview()  : baca saja, tampilkan isi yang akan hilang.
 *   2. backup()   : tulis cadangan JSON, verifikasi ukuran. Gagal = batal.
 *   3. execute()  : ketik ulang kode periode persis, transaksi + cek jumlah baris.
 *
 * Aturan keamanan: periode aktif tidak bisa dihapus. Hanya periode bertanda SIMULASI
 * atau TST/UJI di catatan yang boleh dihapus, agar riwayat asli tidak terhapus tak sengaja.
 */
final class PeriodDeleteService
{
    public static function isDeletable(array $p): bool
    {
        if ((int) ($p['is_active'] ?? 0) === 1) {
            return false;
        }
        $note = (string) ($p['note'] ?? '');
        return str_starts_with($note, 'SIMULASI') || str_starts_with($note, 'TST') || str_starts_with($note, 'UJI');
    }

    /** Langkah 1: pratinjau. Hanya baca. */
    public function preview(int $periodId): array
    {
        $pdo = Database::connection();
        try {
            $stmt = $pdo->prepare('SELECT * FROM audit_periods WHERE id = ?');
            $stmt->execute([$periodId]);
            $p = $stmt->fetch();
        } catch (\PDOException $e) {
            error_log('[PeriodDelete] preview DB error: ' . $e->getMessage());
            throw new RuntimeException('Gagal membaca periode. Cek log server.');
        }
        if ($p === false) {
            throw new RuntimeException('Periode tidak ditemukan.');
        }
        if (! self::isDeletable($p)) {
            throw new RuntimeException('Periode ini tidak boleh dihapus (aktif, atau bukan data uji/simulasi).');
        }

        $s = $pdo->prepare(
            'SELECT COUNT(*) n, COUNT(DISTINCT group_id) AS grp_count,
                    COALESCE(SUM(difference * unit_price), 0) diff_value
               FROM period_snapshots WHERE period_id = ?'
        );
        $s->execute([$periodId]);
        $agg = $s->fetch() ?: ['n' => 0, 'grp_count' => 0, 'diff_value' => 0];

        return [
            'period' => $p,
            'snapshots' => (int) $agg['n'],
            'groups' => (int) $agg['grp_count'],
            'diff_value' => (float) $agg['diff_value'],
        ];
    }

    /** Langkah 2: cadangan JSON. Mengembalikan path file. Melempar bila gagal. */
    public function backup(int $periodId): string
    {
        $pdo = Database::connection();
        $p = $pdo->prepare('SELECT * FROM audit_periods WHERE id = ?');
        $p->execute([$periodId]);
        $period = $p->fetch();
        $snap = $pdo->prepare('SELECT * FROM period_snapshots WHERE period_id = ?');
        $snap->execute([$periodId]);
        $rows = $snap->fetchAll();

        $dir = dirname(__DIR__, 2) . '/storage/backups';
        if (! is_dir($dir) && ! mkdir($dir, 0775, true) && ! is_dir($dir)) {
            throw new RuntimeException('Gagal membuat folder cadangan.');
        }
        $file = $dir . '/hapus_periode_' . $periodId . '_' . date('Ymd_His') . '.json';
        $json = json_encode(['period' => $period, 'snapshots' => $rows], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        if (file_put_contents($file, $json, LOCK_EX) === false || filesize($file) !== strlen($json)) {
            @unlink($file);
            throw new RuntimeException('Cadangan gagal ditulis. Hapus dibatalkan.');
        }
        return $file;
    }

    /** Langkah 3: hapus dalam transaksi. Kode harus cocok persis. */
    public function execute(int $periodId, string $typedCode, int $userId): array
    {
        $pv = $this->preview($periodId);
        $code = (string) $pv['period']['audit_month'];
        if (trim($typedCode) !== $code) {
            throw new RuntimeException("Konfirmasi tidak cocok. Ketik persis kode periode: {$code}.");
        }
        $file = $this->backup($periodId);

        $deleted = Database::transaction(function (\PDO $pdo) use ($periodId, $pv) {
            $snapDel = $pdo->prepare('DELETE FROM period_snapshots WHERE period_id = ?');
            $snapDel->execute([$periodId]);
            if ($snapDel->rowCount() !== $pv['snapshots']) {
                throw new RuntimeException('Jumlah snapshot terhapus tidak cocok. Dibatalkan.');
            }
            $perDel = $pdo->prepare('DELETE FROM audit_periods WHERE id = ? AND is_active = 0');
            $perDel->execute([$periodId]);
            if ($perDel->rowCount() !== 1) {
                throw new RuntimeException('Periode gagal dihapus. Dibatalkan.');
            }
            return ['snapshots' => $pv['snapshots']];
        });

        (new ActivityLog())->record(
            'book.delete_period', 'audit_periods', $periodId,
            "Hapus periode {$code}: {$deleted['snapshots']} snapshot. Cadangan: " . basename($file),
            null, $userId
        );

        return ['period' => $code, 'snapshots' => $deleted['snapshots'], 'backup' => basename($file)];
    }
}
