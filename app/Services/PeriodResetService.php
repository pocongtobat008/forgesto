<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Models\ActivityLog;
use App\Models\Notification;
use RuntimeException;

/**
 * Reset hitungan periode berjalan (tahun buku).
 *
 * Yang DIHAPUS: data hitung & audit periode berjalan
 *   (items.counted_* / status, count_history, audits, discrepancies, reconciliation).
 * Yang TIDAK disentuh: master item, group, lokasi, user, snapshot periode tertutup.
 *
 * Pengaman: periode harus open, konfirmasi kode periode persis, cadangan JSON ke storage/backups,
 * transaksi atomik dengan verifikasi jumlah, dan jejak di activity_logs + notifikasi.
 */
final class PeriodResetService
{
    /** Ambang "signifikan" (default; ubah sesuai kebijakan). */
    public const SIGNIFICANT_VALUE_RP = 10_000_000;
    public const SIGNIFICANT_ITEM_PCT = 20.0;

    private const TABLES_TO_CLEAR = ['reconciliation', 'discrepancies', 'audits', 'count_history'];

    /**
     * Pratinjau: hanya membaca. Tidak mengubah apa pun.
     *
     * @param array{audit_month:string,status:string} $period
     * @return array<string, mixed>
     */
    public function preview(array $period): array
    {
        $pdo = Database::connection();

        $counts = [];
        foreach (self::TABLES_TO_CLEAR as $t) {
            $counts[$t] = (int) $pdo->query("SELECT COUNT(*) FROM {$t}")->fetchColumn();
        }

        $itemsTouched = (int) $pdo->query(
            "SELECT COUNT(*) FROM items
              WHERE counted_qty IS NOT NULL
                 OR count_status <> 'pending'
                 OR audit_status <> 'pending'"
        )->fetchColumn();

        $countedValue = (float) $pdo->query(
            'SELECT COALESCE(SUM(counted_qty * unit_price), 0) FROM items WHERE counted_qty IS NOT NULL'
        )->fetchColumn();

        $totalItems = (int) $pdo->query('SELECT COUNT(*) FROM items')->fetchColumn();

        // Perbandingan dengan close buku terakhir.
        $last = $pdo->query(
            "SELECT id, audit_month FROM audit_periods
              WHERE status = 'closed' ORDER BY closed_at DESC, id DESC LIMIT 1"
        )->fetch();

        $cmp = [
            'has_last_closed' => $last !== false,
            'last_closed_month' => $last['audit_month'] ?? null,
            'moved_group' => 0,
            'qty_changed' => 0,
            'value_delta' => 0.0,
            'value_delta_items' => 0,
        ];

        if ($last !== false) {
            $stmt = $pdo->prepare(
                'SELECT
                    SUM(i.group_id <> s.group_id) AS moved_group,
                    SUM(i.expected_qty <> s.expected_qty) AS qty_changed,
                    COALESCE(SUM((COALESCE(i.counted_qty,0) - COALESCE(s.counted_qty,0)) * i.unit_price), 0) AS value_delta,
                    SUM(COALESCE(i.counted_qty,0) <> COALESCE(s.counted_qty,0)) AS value_delta_items
                   FROM items i
                   JOIN period_snapshots s ON s.item_id = i.id AND s.period_id = ?'
            );
            $stmt->execute([(int) $last['id']]);
            $row = $stmt->fetch() ?: [];
            $cmp['moved_group'] = (int) ($row['moved_group'] ?? 0);
            $cmp['qty_changed'] = (int) ($row['qty_changed'] ?? 0);
            $cmp['value_delta'] = (float) ($row['value_delta'] ?? 0);
            $cmp['value_delta_items'] = (int) ($row['value_delta_items'] ?? 0);
        }

        $pct = $totalItems > 0 ? round($cmp['value_delta_items'] / $totalItems * 100, 1) : 0.0;
        $reasons = [];
        if ($cmp['moved_group'] > 0) {
            $reasons[] = "{$cmp['moved_group']} item pindah group";
        }
        if (abs($cmp['value_delta']) >= self::SIGNIFICANT_VALUE_RP) {
            $reasons[] = 'selisih nilai ≥ Rp ' . number_format(self::SIGNIFICANT_VALUE_RP, 0, ',', '.');
        }
        if ($pct >= self::SIGNIFICANT_ITEM_PCT) {
            $reasons[] = "{$pct}% item berubah (≥ " . self::SIGNIFICANT_ITEM_PCT . '%)';
        }

        return [
            'period' => (string) $period['audit_month'],
            'counts' => $counts,
            'items_touched' => $itemsTouched,
            'total_items' => $totalItems,
            'counted_value' => $countedValue,
            'compare' => $cmp,
            'significant' => $reasons !== [],
            'reasons' => $reasons,
            'nothing_to_reset' => $itemsTouched === 0 && array_sum($counts) === 0,
        ];
    }

    /**
     * Eksekusi reset. Melempar RuntimeException bila salah satu pengaman gagal.
     *
     * @param array{id:int|string,audit_month:string,status:string} $period
     * @return array<string, int|string>
     */
    public function execute(array $period, string $typedCode, int $userId): array
    {
        if (($period['status'] ?? 'open') === 'closed') {
            throw new RuntimeException('Periode sudah ditutup. Data terkunci dan tidak bisa direset.');
        }
        if (trim($typedCode) !== (string) $period['audit_month']) {
            throw new RuntimeException("Konfirmasi tidak cocok. Ketik persis kode periode: {$period['audit_month']}.");
        }

        $before = $this->preview($period);
        if ($before['nothing_to_reset']) {
            throw new RuntimeException('Tidak ada data hitung/audit yang perlu direset.');
        }

        $backupFile = $this->writeBackup($period, $before);

        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            $deleted = [];
            foreach (self::TABLES_TO_CLEAR as $t) {
                $deleted[$t] = $pdo->exec("DELETE FROM {$t}");
                if ($deleted[$t] !== $before['counts'][$t]) {
                    throw new RuntimeException("Jumlah hapus {$t} tidak cocok: {$deleted[$t]} vs {$before['counts'][$t]}.");
                }
            }

            $resetItems = $pdo->exec(
                "UPDATE items
                    SET counted_qty = NULL, counted_by = NULL, counted_at = NULL,
                        count_status = 'pending', audit_status = 'pending',
                        posted_by = NULL, posted_at = NULL
                  WHERE counted_qty IS NOT NULL
                     OR count_status <> 'pending'
                     OR audit_status <> 'pending'"
            );
            if ($resetItems !== $before['items_touched']) {
                throw new RuntimeException("Jumlah item direset tidak cocok: {$resetItems} vs {$before['items_touched']}.");
            }

            // Verifikasi pasca-reset: harus benar-benar nol.
            $left = (int) $pdo->query(
                "SELECT (SELECT COUNT(*) FROM items WHERE counted_qty IS NOT NULL OR count_status<>'pending' OR audit_status<>'pending')
                      + (SELECT COUNT(*) FROM count_history) + (SELECT COUNT(*) FROM audits)
                      + (SELECT COUNT(*) FROM discrepancies) + (SELECT COUNT(*) FROM reconciliation)"
            )->fetchColumn();
            if ($left !== 0) {
                throw new RuntimeException("Verifikasi pasca-reset gagal: masih ada {$left} baris.");
            }

            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }

        $desc = sprintf(
            'Reset hitungan periode %s: items=%d, audits=%d, count_history=%d, discrepancies=%d, reconciliation=%d. Cadangan: %s',
            $period['audit_month'],
            $resetItems,
            $deleted['audits'],
            $deleted['count_history'],
            $deleted['discrepancies'],
            $deleted['reconciliation'],
            basename($backupFile)
        );
        (new ActivityLog())->record('book.reset', 'audit_periods', (int) $period['id'], $desc, null, $userId);
        (new Notification())->broadcast(
            'Hitungan periode direset',
            "Periode {$period['audit_month']} direset. {$resetItems} item dikembalikan ke belum dihitung. Cadangan tersimpan.",
            'warning',
            '/tahun-buku'
        );

        return [
            'items' => $resetItems,
            'audits' => $deleted['audits'],
            'count_history' => $deleted['count_history'],
            'discrepancies' => $deleted['discrepancies'],
            'reconciliation' => $deleted['reconciliation'],
            'backup' => basename($backupFile),
        ];
    }

    /** Tulis cadangan JSON sebelum hapus. Gagal tulis = batal reset. */
    private function writeBackup(array $period, array $before): string
    {
        $pdo = Database::connection();
        $dir = dirname(__DIR__, 2) . '/storage/backups';
        if (! is_dir($dir) && ! mkdir($dir, 0775, true) && ! is_dir($dir)) {
            throw new RuntimeException('Gagal membuat folder cadangan.');
        }

        $data = [
            'period' => $period,
            'preview' => $before,
            'items' => $pdo->query(
                "SELECT id, group_id, location_id, code, expected_qty, counted_qty, count_status, audit_status,
                        counted_by, counted_at, posted_by, posted_at
                   FROM items WHERE counted_qty IS NOT NULL OR count_status<>'pending' OR audit_status<>'pending'"
            )->fetchAll(),
        ];
        foreach (self::TABLES_TO_CLEAR as $t) {
            $data[$t] = $pdo->query("SELECT * FROM {$t}")->fetchAll();
        }

        $file = $dir . '/reset_' . $period['audit_month'] . '_' . date('Ymd_His') . '.json';
        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
        if (file_put_contents($file, $json, LOCK_EX) === false) {
            throw new RuntimeException('Gagal menulis cadangan. Reset dibatalkan.');
        }
        $written = (string) file_get_contents($file);
        if (strlen($written) !== strlen($json)) {
            @unlink($file);
            throw new RuntimeException('Verifikasi cadangan gagal. Reset dibatalkan.');
        }

        return $file;
    }
}
