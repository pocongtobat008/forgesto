<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Request;
use App\Models\ActivityLog;
use App\Models\AuditSubmission;
use App\Models\GroupObserver;
use App\Models\Item;
use App\Models\ItemHistory;
use App\Models\PostingBatch;
use PDO;

/**
 * Endpoint Posting + Recount untuk Data STO.
 *
 * Aturan:
 *  - Observer hanya bisa posting untuk group tugasnya (picGroups).
 *  - Poweradmin & Admin STO bebas lingkup group.
 *  - Mendukung:
 *    1. mode 'selected': hanya item yang dicentang di tabel (wajib lolos guard group untuk observer).
 *    2. mode 'tab_all': seluruh item di tab tertentu ('pending', 'counted', atau 'all').
 *    3. mode 'group' (default): seluruh item siap posting di group yang ditentukan.
 */
class ItemPostingController extends Controller
{
    private PDO $pdo;
    private Item $items;

    public function __construct()
    {
        $this->pdo = Database::connection();
        $this->items = new Item();
    }

    /**
     * Kirim satu atau beberapa batch group ke auditor.
     */
    public function posting(): void
    {
        if (! Request::isAjax()) {
            $this->sendJson(400, ['success' => false, 'error' => 'Invalid request']);
        }
        if (! can('post_data') || (function_exists('can_action') && ! can_action('items', 'post'))) {
            $this->sendJson(403, ['success' => false, 'error' => 'Anda tidak punya izin posting.']);
        }

        $payload = $this->payload();
        $isAdmin = in_array(Auth::role(), ['poweradmin', 'adminsto'], true);
        $canPicPost = $isAdmin || (function_exists('can_action') && can_action('items', 'pic_post'));
        $userId = (int) Auth::id();
        $userName = (string) (Auth::user()['name'] ?? '');
        $period = (string) ($payload['period'] ?? 'default');
        $mode = (string) ($payload['mode'] ?? 'group');

        $pic = new GroupObserver();
        $batchModel = new PostingBatch();
        $submission = new AuditSubmission();

        $postedGroups = [];
        $blocked = [];
        $totalItems = 0;
        $totalCounted = 0;

        // =====================================================================
        // MODE 1: SELECTED ITEMS (Hanya item yang dipilih / dicentang di tabel)
        // =====================================================================
        if ($mode === 'selected') {
            $itemIds = array_values(array_filter(
                array_map('intval', (array) ($payload['item_ids'] ?? [])),
                static fn ($v) => $v > 0
            ));
            if ($itemIds === []) {
                $this->sendJson(400, ['success' => false, 'error' => 'Tidak ada item yang dipilih untuk diposting.']);
            }

            $placeholders = implode(',', array_fill(0, count($itemIds), '?'));
            $selectedRows = $this->items->query(
                "SELECT id, code, group_id, count_status, counted_qty, expected_qty FROM items WHERE id IN ({$placeholders})",
                $itemIds
            );

            if ($selectedRows === []) {
                $this->sendJson(404, ['success' => false, 'error' => 'Item yang dipilih tidak ditemukan.']);
            }

            // Validasi Keamanan: Observer HANYA boleh posting item di group penugasan mereka!
            foreach ($selectedRows as $row) {
                $gid = (int) $row['group_id'];
                if (! Auth::canSeeGroup($gid)) {
                    $this->sendJson(403, ['success' => false, 'error' => 'Akses ditolak: item berada di luar izin group Anda.']);
                }
                if (! $isAdmin && ! ($canPicPost && $pic->isPic($userId, $gid)) && ! can('manage_data')) {
                    $this->sendJson(403, [
                        'success' => false,
                        'error'   => "Keamanan: Sebagai Observer, Anda hanya dapat memposting item dari group tugas Anda. Item {$row['code']} bukan bagian dari group tugas Anda."
                    ]);
                }
            }

            // Kelompokkan per group_id
            $itemsByGroup = [];
            foreach ($selectedRows as $row) {
                $itemsByGroup[(int) $row['group_id']][] = $row;
            }

            try {
                $this->pdo->beginTransaction();

                foreach ($itemsByGroup as $groupId => $groupItems) {
                    $itemCount = count($groupItems);
                    $groupTotalCounted = 0;
                    $groupItemIds = [];

                    foreach ($groupItems as $r) {
                        $groupTotalCounted += (int) ($r['counted_qty'] ?? 0);
                        $groupItemIds[] = (int) $r['id'];
                    }

                    $batchId = $batchModel->insert([
                        'group_id'      => $groupId,
                        'period'        => $period,
                        'posted_by'     => $userId,
                        'posted_at'     => date('Y-m-d H:i:s'),
                        'item_count'    => $itemCount,
                        'total_counted' => $groupTotalCounted,
                        'status'        => PostingBatch::STATUS_POSTED,
                        'note'          => 'Posting pilihan ' . $itemCount . ' item',
                    ]);

                    $subPlaceholders = implode(',', array_fill(0, count($groupItemIds), '?'));
                    $stmt = $this->pdo->prepare(
                        "UPDATE items
                         SET count_status = 'submitted',
                             audit_status = 'pending',
                             needs_recount = 0,
                             is_locked = 1,
                             counted_qty = COALESCE(counted_qty, 0),
                             posted_by = ?,
                             posted_at = NOW(),
                             batch_id = ?
                         WHERE id IN ({$subPlaceholders})"
                    );
                    $stmt->execute(array_merge([$userId, $batchId], $groupItemIds));
                    $moved = $stmt->rowCount();

                    if ($moved > 0) {
                        ItemHistory::logBulk(
                            $groupItemIds,
                            ItemHistory::ACTION_POSTED,
                            Item::COUNT_COUNTED,
                            Item::COUNT_SUBMITTED,
                            $userId,
                            ['batch_id' => $batchId, 'period' => $period ?? null, 'via' => 'posting'],
                            'Posting batch #' . $batchId
                        );
                    }

                    $totalItems += $moved;
                    $totalCounted += $groupTotalCounted;

                    // Kirim ke auditor group
                    $auditors = $this->items->query(
                        "SELECT u.id FROM users u
                         JOIN user_groups ug ON ug.user_id = u.id
                         WHERE ug.group_id = ? AND u.role = 'audit' AND u.is_active = 1
                         ORDER BY u.id LIMIT 1",
                        [$groupId]
                    );
                    $auditorId = $auditors !== [] ? (int) $auditors[0]['id'] : null;
                    $picInfo = $pic->picFor($groupId);
                    $groupObserverId = $picInfo !== null ? (int) $picInfo['id'] : $userId;

                    $submission->insert([
                        'batch_id'     => $batchId,
                        'group_id'     => $groupId,
                        'observer_id'  => $groupObserverId,
                        'auditor_id'   => $auditorId,
                        'item_count'   => $moved,
                        'sample_count' => 0,
                        'recount_count'=> 0,
                        'status'       => AuditSubmission::STATUS_INBOX,
                        'note'         => 'Batch pilihan (' . $moved . ' item) dikirim dari ' . ($isAdmin ? 'Admin ' : 'observer ') . $userName,
                    ]);

                    $postedGroups[] = ['group_id' => $groupId, 'batch_id' => $batchId, 'items' => $moved];
                    (new ActivityLog())->record('posting', 'group', $groupId, "Posting batch #{$batchId} ({$moved} item pilihan) dikirim ke audit", $groupId);
                }

                $this->pdo->commit();
            } catch (\Throwable $e) {
                if ($this->pdo->inTransaction()) {
                    $this->pdo->rollBack();
                }
                $this->sendJson(500, ['success' => false, 'error' => 'Gagal memposting: ' . $e->getMessage()]);
            }

            $this->sendJson(200, [
                'success'       => true,
                'posted'        => $postedGroups,
                'total_items'   => $totalItems,
                'total_counted' => $totalCounted,
                'message'       => "Berhasil memposting {$totalItems} item yang dipilih ke audit.",
            ]);
            return;
        }

        // =====================================================================
        // MODE 2: TAB ALL (Semua item di tab Belum Dihitung / Sudah Dihitung)
        // =====================================================================
        if ($mode === 'tab_all') {
            $tabStatus = (string) ($payload['tab_status'] ?? 'pending'); // 'pending', 'counted', or 'all'
            $targetGroupId = (int) ($payload['group_id'] ?? 0);

            $groupIds = [];
            if ($targetGroupId > 0) {
                if (! Auth::canSeeGroup($targetGroupId) || (! $isAdmin && ! ($canPicPost && $pic->isPic($userId, $targetGroupId)) && ! can('manage_data'))) {
                    $this->sendJson(403, ['success' => false, 'error' => 'Keamanan: Anda tidak memiliki akses posting untuk group ini.']);
                }
                $groupIds = [$targetGroupId];
            } elseif ($isAdmin) {
                // Admin: seluruh group yang ada item sesuai tabStatus
                $statusSql = $tabStatus === 'counted' ? "count_status = 'counted'" : ($tabStatus === 'pending' ? "count_status = 'pending'" : "count_status IN ('pending', 'counted')");
                $allG = $this->items->query("SELECT DISTINCT group_id FROM items WHERE group_id IS NOT NULL AND {$statusSql}");
                $groupIds = array_values(array_filter(array_map(fn($r) => (int) $r['group_id'], $allG), fn($v) => $v > 0));
            } else {
                // Observer: HANYA group tugas observer
                $myGroups = $pic->groupIdsForPic($userId);
                $groupIds = array_values(array_filter(array_map('intval', $myGroups), fn($v) => $v > 0));
            }

            if ($groupIds === []) {
                $this->sendJson(200, [
                    'success'     => true,
                    'posted'      => [],
                    'total_items' => 0,
                    'message'     => 'Tidak ada item yang ditemukan pada tab ini.',
                ]);
            }

            try {
                $this->pdo->beginTransaction();

                foreach ($groupIds as $groupId) {
                    if (! Auth::canSeeGroup($groupId)) {
                        continue;
                    }
                    if (! $isAdmin && ! ($canPicPost && $pic->isPic($userId, $groupId)) && ! can('manage_data')) {
                        continue;
                    }

                    $statusCondition = $tabStatus === 'counted'
                        ? "(count_status = 'counted' OR (count_status = 'posted' AND batch_id IS NULL))"
                        : ($tabStatus === 'pending'
                            ? "count_status = 'pending'"
                            : "count_status IN ('pending', 'counted')");

                    $itemsToPost = $this->items->query(
                        "SELECT id, counted_qty FROM items WHERE group_id = ? AND {$statusCondition}",
                        [$groupId]
                    );

                    if ($itemsToPost === []) {
                        continue;
                    }

                    $itemCount = count($itemsToPost);
                    $groupTotalCounted = 0;
                    $groupItemIds = [];
                    foreach ($itemsToPost as $r) {
                        $groupTotalCounted += (int) ($r['counted_qty'] ?? 0);
                        $groupItemIds[] = (int) $r['id'];
                    }

                    $batchId = $batchModel->insert([
                        'group_id'      => $groupId,
                        'period'        => $period,
                        'posted_by'     => $userId,
                        'posted_at'     => date('Y-m-d H:i:s'),
                        'item_count'    => $itemCount,
                        'total_counted' => $groupTotalCounted,
                        'status'        => PostingBatch::STATUS_POSTED,
                        'note'          => 'Posting seluruh item tab ' . $tabStatus,
                    ]);

                    $subPlaceholders = implode(',', array_fill(0, count($groupItemIds), '?'));
                    $stmt = $this->pdo->prepare(
                        "UPDATE items
                         SET count_status = 'submitted',
                             audit_status = 'pending',
                             needs_recount = 0,
                             is_locked = 1,
                             counted_qty = COALESCE(counted_qty, 0),
                             posted_by = ?,
                             posted_at = NOW(),
                             batch_id = ?
                         WHERE id IN ({$subPlaceholders})"
                    );
                    $stmt->execute(array_merge([$userId, $batchId], $groupItemIds));
                    $moved = $stmt->rowCount();

                    if ($moved > 0) {
                        ItemHistory::logBulk(
                            $groupItemIds,
                            ItemHistory::ACTION_POSTED,
                            Item::COUNT_COUNTED,
                            Item::COUNT_SUBMITTED,
                            $userId,
                            ['batch_id' => $batchId, 'period' => $period ?? null, 'via' => 'posting'],
                            'Posting batch #' . $batchId
                        );
                    }

                    $totalItems += $moved;
                    $totalCounted += $groupTotalCounted;

                    $auditors = $this->items->query(
                        "SELECT u.id FROM users u
                         JOIN user_groups ug ON ug.user_id = u.id
                         WHERE ug.group_id = ? AND u.role = 'audit' AND u.is_active = 1
                         ORDER BY u.id LIMIT 1",
                        [$groupId]
                    );
                    $auditorId = $auditors !== [] ? (int) $auditors[0]['id'] : null;
                    $picInfo = $pic->picFor($groupId);
                    $groupObserverId = $picInfo !== null ? (int) $picInfo['id'] : $userId;

                    $submission->insert([
                        'batch_id'     => $batchId,
                        'group_id'     => $groupId,
                        'observer_id'  => $groupObserverId,
                        'auditor_id'   => $auditorId,
                        'item_count'   => $moved,
                        'sample_count' => 0,
                        'recount_count'=> 0,
                        'status'       => AuditSubmission::STATUS_INBOX,
                        'note'         => 'Batch tab ' . $tabStatus . ' (' . $moved . ' item) dikirim dari ' . ($isAdmin ? 'Admin ' : 'observer ') . $userName,
                    ]);

                    $postedGroups[] = ['group_id' => $groupId, 'batch_id' => $batchId, 'items' => $moved];
                    (new ActivityLog())->record('posting', 'group', $groupId, "Posting batch #{$batchId} ({$moved} item tab {$tabStatus}) dikirim ke audit", $groupId);
                }

                $this->pdo->commit();
            } catch (\Throwable $e) {
                if ($this->pdo->inTransaction()) {
                    $this->pdo->rollBack();
                }
                $this->sendJson(500, ['success' => false, 'error' => 'Gagal memposting: ' . $e->getMessage()]);
            }

            $this->sendJson(200, [
                'success'       => true,
                'posted'        => $postedGroups,
                'total_items'   => $totalItems,
                'total_counted' => $totalCounted,
                'message'       => "Berhasil memposting {$totalItems} item ke audit.",
            ]);
            return;
        }

        // =====================================================================
        // MODE 3: DEFAULT GROUP POSTING (Existing workflow)
        // =====================================================================
        $groupIds = array_values(array_filter(
            array_map('intval', (array) ($payload['group_ids'] ?? [])),
            static fn ($v) => $v > 0
        ));
        if ($groupIds === []) {
            $single = (int) ($payload['group_id'] ?? 0);
            if ($single > 0) {
                $groupIds = [$single];
            }
        }

        // Jika groupIds belum ditentukan:
        if ($groupIds === []) {
            if ($isAdmin) {
                // Poweradmin & Admin STO tidak terlock oleh group:
                $groupsWithCounted = $this->items->query(
                    "SELECT DISTINCT group_id FROM items
                     WHERE group_id IS NOT NULL
                       AND (count_status = ? OR (count_status = 'posted' AND batch_id IS NULL))",
                    [Item::COUNT_COUNTED]
                );
                $groupIds = array_values(array_filter(
                    array_map(static fn ($r) => (int) $r['group_id'], $groupsWithCounted),
                    static fn ($v) => $v > 0
                ));
                if ($groupIds === []) {
                    $this->sendJson(200, [
                        'success'       => true,
                        'posted'        => [],
                        'blocked'       => [],
                        'total_items'   => 0,
                        'total_counted' => 0,
                        'message'       => 'Tidak ada item yang siap diposting di group manapun.',
                    ]);
                    return;
                }
            } else {
                $this->sendJson(400, ['success' => false, 'error' => 'Group tidak ditentukan.']);
                return;
            }
        }

        try {
            $this->pdo->beginTransaction();

            foreach ($groupIds as $groupId) {
                if (! Auth::canSeeGroup($groupId)) {
                    $blocked[] = ['group_id' => $groupId, 'reason' => 'Di luar akses group Anda.'];
                    continue;
                }
                if (! $isAdmin && ! ($canPicPost && $pic->isPic($userId, $groupId)) && ! can('manage_data')) {
                    $blocked[] = ['group_id' => $groupId, 'reason' => 'Anda bukan observer penanggung jawab group ini atau tidak memiliki izin PIC posting.'];
                    continue;
                }

                $readiness = $this->items->postingReadiness($groupId, $period);
                // Observer terlock oleh group dan kelengkapan hitung; poweradmin & adminsto tidak terlock.
                if (! $isAdmin && $readiness['pending'] > 0) {
                    $blocked[] = [
                        'group_id' => $groupId,
                        'reason'   => 'Masih ada ' . $readiness['pending'] . ' item belum dihitung.',
                        'pending'  => $readiness['pending'],
                    ];
                    continue;
                }
                if ($readiness['counted'] === 0) {
                    $blocked[] = ['group_id' => $groupId, 'reason' => 'Tidak ada item yang siap diposting pada group ini.'];
                    continue;
                }

                $totalCountedValue = (int) ($this->items->scalar(
                    "SELECT COALESCE(SUM(counted_qty),0) FROM items
                     WHERE group_id = ?
                       AND (count_status = ? OR (count_status = 'posted' AND batch_id IS NULL))",
                    [$groupId, Item::COUNT_COUNTED]
                ) ?? 0);

                $batchId = $batchModel->insert([
                    'group_id'      => $groupId,
                    'period'        => $period,
                    'posted_by'     => $userId,
                    'posted_at'     => date('Y-m-d H:i:s'),
                    'item_count'    => $readiness['counted'],
                    'total_counted' => $totalCountedValue,
                    'status'        => PostingBatch::STATUS_POSTED,
                ]);

                // Pindahkan seluruh item 'counted' + item 'posted' legacy
                // (belum punya batch_id) ke batch ini.
                $legacyIds = array_map(
                    static fn ($r) => (int) $r['id'],
                    $this->items->query(
                        "SELECT id FROM items
                         WHERE group_id = ?
                           AND (count_status = 'counted'
                                OR (count_status = 'posted' AND batch_id IS NULL))",
                        [$groupId]
                    )
                );

                $stmt = $this->pdo->prepare(
                    "UPDATE items
                     SET count_status = 'submitted', audit_status = 'pending',
                         needs_recount = 0, is_locked = 1,
                         posted_by = ?, posted_at = NOW(), batch_id = ?
                     WHERE group_id = ?
                       AND (count_status = 'counted'
                            OR (count_status = 'posted' AND batch_id IS NULL))"
                );
                $stmt->execute([$userId, $batchId, $groupId]);
                $moved = $stmt->rowCount();

                if ($moved > 0) {
                    ItemHistory::logBulk(
                        $legacyIds,
                        ItemHistory::ACTION_POSTED,
                        Item::COUNT_COUNTED,
                        Item::COUNT_SUBMITTED,
                        $userId,
                        ['batch_id' => $batchId, 'period' => $period ?? null, 'via' => 'posting_group'],
                        'Posting massal group #' . $groupId
                    );
                }

                $totalItems += $moved;
                $totalCounted += $totalCountedValue;

                // Kirim ke auditor group.
                $auditors = $this->items->query(
                    "SELECT u.id FROM users u
                     JOIN user_groups ug ON ug.user_id = u.id
                     WHERE ug.group_id = ? AND u.role = 'audit' AND u.is_active = 1
                     ORDER BY u.id LIMIT 1",
                    [$groupId]
                );
                $auditorId = $auditors !== [] ? (int) $auditors[0]['id'] : null;

                $picInfo = $pic->picFor($groupId);
                $groupObserverId = $picInfo !== null ? (int) $picInfo['id'] : $userId;

                $submission->insert([
                    'batch_id'     => $batchId,
                    'group_id'     => $groupId,
                    'observer_id'  => $groupObserverId,
                    'auditor_id'   => $auditorId,
                    'item_count'   => $moved,
                    'sample_count' => 0,
                    'recount_count'=> 0,
                    'status'       => AuditSubmission::STATUS_INBOX,
                    'note'         => 'Batch dikirim dari ' . ($isAdmin ? 'Admin ' : 'observer ') . $userName,
                ]);

                $postedGroups[] = ['group_id' => $groupId, 'batch_id' => $batchId, 'items' => $moved];

                (new ActivityLog())->record('posting', 'group', $groupId, "Posting batch #{$batchId} ({$moved} item) dikirim ke audit", $groupId);
            }

            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            $this->sendJson(500, ['success' => false, 'error' => 'Gagal memposting: ' . $e->getMessage()]);
            return;
        }

        $this->sendJson(200, [
            'success'       => true,
            'posted'        => $postedGroups,
            'blocked'       => $blocked,
            'total_items'   => $totalItems,
            'total_counted' => $totalCounted,
            'message'       => $postedGroups !== []
                ? "Berhasil memposting {$totalItems} item ke audit."
                : ($blocked !== [] ? ($blocked[0]['reason'] ?? 'Posting diblokir.') : 'Tidak ada item yang bisa diposting.'),
        ]);
    }

    /**
     * Admin STO / poweradmin meminta cari ulang untuk item tertentu.
     * Menghidupkan kembali tombol posting pada item tersebut.
     */
    public function recount(): void
    {
        if (! Request::isAjax()) {
            $this->sendJson(400, ['success' => false, 'error' => 'Invalid request']);
        }
        if (! in_array(Auth::role(), ['adminsto', 'poweradmin'], true)) {
            $this->sendJson(403, ['success' => false, 'error' => 'Hanya Admin STO / Power Admin yang dapat meminta cari ulang.']);
        }

        $payload = $this->payload();
        $itemIds = array_values(array_filter(
            array_map('intval', (array) ($payload['item_ids'] ?? [])),
            static fn ($v) => $v > 0
        ));
        if ($itemIds === []) {
            $single = (int) ($payload['item_id'] ?? 0);
            if ($single > 0) {
                $itemIds = [$single];
            }
        }
        if ($itemIds === []) {
            $this->sendJson(400, ['success' => false, 'error' => 'Item tidak ditentukan.']);
        }

        $placeholders = implode(',', array_fill(0, count($itemIds), '?'));

        try {
            $this->pdo->beginTransaction();

            $batchIds = array_map(
                static fn ($r) => (int) $r['batch_id'],
                $this->items->query(
                    "SELECT DISTINCT batch_id FROM items
                     WHERE id IN ({$placeholders}) AND batch_id IS NOT NULL",
                    $itemIds
                )
            );

            $recountRows = $this->items->query(
                "SELECT id, count_status FROM items
                 WHERE id IN ({$placeholders})
                   AND count_status IN ('posted','submitted','audited','reconciled')",
                $itemIds
            );

            $stmt = $this->pdo->prepare(
                "UPDATE items
                 SET count_status = 'counted', audit_status = 'recount', is_locked = 0
                 WHERE id IN ({$placeholders})
                   AND count_status IN ('posted','submitted','audited','reconciled')"
            );
            $stmt->execute($itemIds);
            $affected = $stmt->rowCount();

            if ($affected > 0) {
                $log = [];
                foreach ($recountRows as $r) {
                    $log[] = [
                        'item_id'  => (int) $r['id'],
                        'action'   => ItemHistory::ACTION_RECOUNT,
                        'from'     => (string) $r['count_status'],
                        'to'       => Item::COUNT_COUNTED,
                        'metadata' => ['audit_status' => 'recount'],
                        'notes'    => 'Recount dari tab terposting',
                    ];
                }
                ItemHistory::logMany($log, (int) Auth::id());
            }

            foreach ($batchIds as $bid) {
                $this->items->updateBatchStatus($bid, PostingBatch::STATUS_RECOUNT);
            }

            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            $this->sendJson(500, ['success' => false, 'error' => 'Gagal recount: ' . $e->getMessage()]);
        }

        foreach ($itemIds as $id) {
            (new ActivityLog())->record('recount', 'item', $id, 'Admin STO meminta cari ulang', null);
        }

        $this->sendJson(200, [
            'success' => true,
            'count'   => $affected,
            'message' => "{$affected} item diminta dicari ulang. Observer dapat menghitung & posting lagi.",
        ]);
    }

    // ------------------------------------------------------------------
    // Helper
    // ------------------------------------------------------------------

    /** @return array<string, mixed> */
    protected function payload(): array
    {
        $raw = file_get_contents('php://input');
        $data = json_decode((string) $raw, true);

        return is_array($data) ? $data : [];
    }

    protected function sendJson(int $status, array $data): never
    {
        if (! headers_sent()) {
            http_response_code($status);
            header('Content-Type: application/json; charset=utf-8');
        }
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }
}
