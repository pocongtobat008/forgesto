<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Auth;
use App\Core\Model;

/**
 * ItemHistory — jejak perubahan status per item (audit trail STO).
 *
 * Tabel: item_histories (FK item_id -> items.id ON DELETE CASCADE).
 *
 * Aturan pakai:
 *  - Semua penulisan WAJIB lewat logChange()/logBulk()/logMany().
 *  - Penulisan TIDAK PERNAH melempar exception ke pemanggil: kegagalan log
 *    dicatat ke error_log dan dikembalikan sebagai false/0. Alur bisnis
 *    (posting/audit) tidak boleh ikut gagal hanya karena jejak gagal ditulis.
 */
class ItemHistory extends Model
{
    protected string $table = 'item_histories';

    protected array $fillable = [
        'item_id',
        'action',
        'from_status',
        'to_status',
        'user_id',
        'notes',
        'metadata',
    ];

    // Action constants
    public const ACTION_COUNTED          = 'counted';
    public const ACTION_POSTED           = 'posted';
    public const ACTION_SUBMITTED        = 'submitted';
    public const ACTION_AUDITED          = 'audited';
    public const ACTION_DISCREPANCY      = 'discrepancy';
    public const ACTION_RECOUNT          = 'recount';
    public const ACTION_RECOUNT_REQUEST  = 'recount_request';
    public const ACTION_RECONCILED       = 'reconciled';
    public const ACTION_RESET            = 'reset';
    public const ACTION_UNCOUNTED        = 'uncounted';
    public const ACTION_BACKFILL         = 'backfill';

    /**
     * Catat satu transisi status item. Tidak pernah melempar exception.
     */
    public static function logChange(
        int $itemId,
        string $action,
        ?string $fromStatus = null,
        ?string $toStatus = null,
        ?int $userId = null,
        ?array $metadata = null,
        ?string $notes = null
    ): bool {
        if ($itemId <= 0) {
            return false;
        }

        try {
            $uid = $userId ?? Auth::id();

            (new static())->insert([
                'item_id'     => $itemId,
                'action'      => $action,
                'from_status' => $fromStatus,
                'to_status'   => $toStatus,
                'user_id'     => $uid ?: null,
                'notes'       => $notes,
                'metadata'    => $metadata !== null && $metadata !== [] ? json_encode($metadata, JSON_UNESCAPED_UNICODE) : null,
            ]);

            return true;
        } catch (\Throwable $e) {
            error_log('[ItemHistory] gagal mencatat ' . $action . ' item#' . $itemId . ': ' . $e->getMessage());

            return false;
        }
    }

    /**
     * Catat transisi seragam untuk banyak item sekaligus (bulk posting/recount).
     *
     * @param list<int> $itemIds
     */
    public static function logBulk(
        array $itemIds,
        string $action,
        ?string $fromStatus = null,
        ?string $toStatus = null,
        ?int $userId = null,
        ?array $metadata = null,
        ?string $notes = null
    ): int {
        $rows = [];
        foreach ($itemIds as $id) {
            $rows[] = [
                'item_id' => (int) $id,
                'action'  => $action,
                'from'    => $fromStatus,
                'to'      => $toStatus,
                'metadata'=> $metadata,
                'notes'   => $notes,
            ];
        }

        return self::logMany($rows, $userId);
    }

    /**
     * Catat banyak transisi dengan from/to berbeda per item.
     *
     * @param list<array{item_id:int,action:string,from?:?string,to?:?string,metadata?:?array,notes?:?string}> $rows
     */
    public static function logMany(array $rows, ?int $userId = null): int
    {
        $ok = 0;
        foreach ($rows as $row) {
            if (self::logChange(
                (int) ($row['item_id'] ?? 0),
                (string) ($row['action'] ?? 'update'),
                $row['from'] ?? null,
                $row['to'] ?? null,
                $userId,
                $row['metadata'] ?? null,
                $row['notes'] ?? null
            )) {
                $ok++;
            }
        }

        return $ok;
    }

    /**
     * Status hitung item saat ini (dibaca sebelum update agar from_status akurat).
     */
    public function statusOf(int $itemId): ?string
    {
        try {
            $val = $this->scalar('SELECT count_status FROM items WHERE id = ?', [$itemId]);

            return $val === null || $val === false ? null : (string) $val;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Riwayat satu item (terbaru dulu).
     */
    public function getItemHistory(int $itemId, int $limit = 50): array
    {
        $limit = max(1, min(500, $limit));

        return $this->query(
            "SELECT h.*, u.name AS user_name, i.code AS item_code, i.name AS item_name
             FROM item_histories h
             LEFT JOIN users u ON h.user_id = u.id
             LEFT JOIN items i ON h.item_id = i.id
             WHERE h.item_id = ?
             ORDER BY h.created_at DESC, h.id DESC
             LIMIT {$limit}",
            [$itemId]
        );
    }

    /**
     * Perubahan terbaru lintas item (monitoring).
     */
    public function getRecentChanges(int $limit = 100): array
    {
        $limit = max(1, min(1000, $limit));

        return $this->query(
            "SELECT h.*, u.name AS user_name, i.code AS item_code, i.name AS item_name,
                    g.name AS group_name
             FROM item_histories h
             LEFT JOIN users u ON h.user_id = u.id
             LEFT JOIN items i ON h.item_id = i.id
             LEFT JOIN `groups` g ON i.group_id = g.id
             ORDER BY h.created_at DESC, h.id DESC
             LIMIT {$limit}"
        );
    }

    /**
     * Statistik per jenis aksi.
     */
    public function getStatsByAction(?string $dateFrom = null, ?string $dateTo = null): array
    {
        $where = '1=1';
        $params = [];

        if ($dateFrom) {
            $where .= ' AND created_at >= ?';
            $params[] = $dateFrom;
        }

        if ($dateTo) {
            $where .= ' AND created_at <= ?';
            $params[] = $dateTo;
        }

        return $this->query(
            "SELECT action, COUNT(*) AS total,
                    COUNT(DISTINCT item_id) AS unique_items,
                    COUNT(DISTINCT user_id) AS unique_users
             FROM item_histories
             WHERE {$where}
             GROUP BY action
             ORDER BY total DESC",
            $params
        );
    }
}
