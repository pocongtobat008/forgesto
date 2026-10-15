<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * Periode close buku.
 *  - status open   : input hitung/audit/rekonsiliasi boleh.
 *  - status closed : snapshot ada di period_snapshots, input ditolak (guard di controller).
 * is_active=1 = periode yang sedang ditampilkan/dikerjakan.
 * Membuka periode lama hanya read-only: items (data berjalan) tidak disentuh.
 */
class BookPeriod extends Model
{
    protected string $table = 'audit_periods';

    protected array $fillable = [
        'audit_month', 'audit_start', 'audit_end', 'note', 'status', 'is_active', 'created_by',
    ];

    public function active(): ?array
    {
        return $this->first('SELECT * FROM audit_periods WHERE is_active = 1 ORDER BY id DESC LIMIT 1');
    }

    /**
     * Periode yang sedang dilihat (bisa berupa arsip via session, atau default aktif DB).
     */
    public static function current(): ?array
    {
        $instance = new self();
        if (session_status() === PHP_SESSION_ACTIVE && ! empty($_SESSION['view_period_id'])) {
            $viewId = (int) $_SESSION['view_period_id'];
            $p = $instance->find($viewId);
            if ($p !== null) {
                $p['is_archive_view'] = ($p['status'] ?? 'open') === 'closed';
                return $p;
            }
            unset($_SESSION['view_period_id']);
        }

        $p = $instance->active();
        if ($p !== null) {
            $p['is_archive_view'] = ($p['status'] ?? 'open') === 'closed';
        }
        return $p;
    }

    /**
     * Cek apakah tampilan saat ini dalam mode read-only (periode ditutup / arsip).
     */
    public static function isReadOnly(): bool
    {
        $p = self::current();
        return $p !== null && (($p['status'] ?? 'open') === 'closed' || ! empty($p['is_archive_view']));
    }

    /**
     * Set periode yang ingin dilihat di session.
     */
    public static function setViewPeriod(int $id): bool
    {
        $instance = new self();
        $p = $instance->find($id);
        if ($p === null) {
            return false;
        }
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION['view_period_id'] = $id;
        return true;
    }

    /**
     * Hapus sesi lihat periode arsip (kembali ke periode berjalan).
     */
    public static function clearViewPeriod(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        unset($_SESSION['view_period_id']);
    }

    /** Tutup periode: snapshot semua item lalu kunci. Mengembalikan jumlah item. */
    public function close(int $periodId, int $userId): int
    {
        $this->run('DELETE FROM period_snapshots WHERE period_id = ?', [$periodId]);
        $this->run(
            'INSERT INTO period_snapshots
                (period_id, item_id, group_id, item_code, item_name, unit_price, expected_qty,
                 counted_qty, audited_qty, difference, count_status, audit_status)
             SELECT ?, i.id, i.group_id, i.code, i.name, i.unit_price, i.expected_qty,
                    i.counted_qty,
                    (SELECT a.audited_qty FROM audits a WHERE a.item_id = i.id ORDER BY a.id DESC LIMIT 1),
                    CASE WHEN i.counted_qty IS NULL THEN NULL ELSE i.counted_qty - i.expected_qty END,
                    i.count_status, i.audit_status
               FROM items i',
            [$periodId]
        );
        $this->run(
            "UPDATE audit_periods SET status = 'closed', closed_at = NOW(), closed_by = ? WHERE id = ?",
            [$userId, $periodId]
        );

        return (int) $this->scalar('SELECT COUNT(*) FROM period_snapshots WHERE period_id = ?', [$periodId]);
    }

    /**
     * Buka periode baru (hanya dipanggil bila periode aktif sudah closed).
     * Hitung di items direset ke 0/belum dihitung. Data historis ada di snapshot periode lama.
     */
    public function openNew(string $month, string $start, string $end, int $userId): int
    {
        $this->run('UPDATE audit_periods SET is_active = 0 WHERE is_active = 1');
        $id = (int) $this->insert([
            'audit_month' => $month,
            'audit_start' => $start,
            'audit_end'   => $end,
            'note'        => null,
            'status'      => 'open',
            'is_active'   => 1,
            'created_by'  => $userId,
        ]);
        $this->run(
            "UPDATE items SET counted_qty = NULL, count_status = 'pending', audit_status = 'pending',
                    counted_by = NULL, counted_at = NULL, posted_by = NULL, posted_at = NULL, is_locked = 0"
        );

        return $id;
    }

    /** Tampilkan periode lama read-only. Items (data berjalan) tidak diubah. */
    public function openReadonly(int $periodId): void
    {
        $this->run('UPDATE audit_periods SET is_active = 0 WHERE is_active = 1');
        $this->run('UPDATE audit_periods SET is_active = 1, reopened_at = NOW() WHERE id = ?', [$periodId]);
    }

    /** Baris snapshot periode (untuk tampilan read-only / cetak). */
    public function snapshotRows(int $periodId): array
    {
        return $this->query(
            'SELECT * FROM period_snapshots WHERE period_id = ? ORDER BY group_id, item_code',
            [$periodId]
        );
    }
}
