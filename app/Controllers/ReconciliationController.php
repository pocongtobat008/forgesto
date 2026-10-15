<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Request;
use App\Models\ActivityLog;
use App\Models\CountHistory;
use App\Models\Group;
use App\Models\Item;
use App\Models\Notification;
use App\Models\Reconciliation;
use App\Models\ExportLog;

class ReconciliationController extends Controller
{
    use BookPeriodActions;
    /**
     * SAFE_RETURN_HELPER — tujuan redirect yang aman (hanya path internal).
     * Dipakai agar aksi yang dipicu dari tabel bisa kembali ke daftar.
     */
    private function safeReturn(string $fallback): string
    {
        $to = trim((string) Request::string('return_to', ''));

        // Wajib path internal (diawali '/'), tolak: absolut/protocol-relative,
        // backslash trick, newline (header injection), dan whitespace.
        if ($to === '' || $to[0] !== '/' || str_starts_with($to, '//')) {
            return $fallback;
        }
        if (str_contains($to, '\\') || preg_match('#[\r\n\t ]#', $to)) {
            return $fallback;
        }
        if (! preg_match('#^/[^\s]*$#', $to)) {
            return $fallback;
        }

        return $to;
    }

    /** Nilai distinct untuk popup filter kolom tabel Rekonsiliasi (JSON). */
    public function distinct(array $params = []): never
    {
        $this->requireReconAccess();
        // Ability 'recon_view' tidak terdaftar; halaman index juga tanpa ability khusus.
        // Akses user dibatasi lewat Auth::check() + groupScope di listDetailed.
        if (! Auth::check()) { $this->abort(403, 'Tidak ada user aktif.'); }
        if (in_array((string) ($_GET['key'] ?? ''), Reconciliation::ADMIN_ONLY_COLS, true) && ! in_array(Auth::role(), ['poweradmin', 'adminsto'], true)) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['values' => []]);
            exit;
        }
        $col = Reconciliation::normaliseColFilters($_GET['col'] ?? null);
        $key = (string) ($_GET['key'] ?? '');
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode(['values' => (new Reconciliation())->distinctColValues($key, $col)], JSON_UNESCAPED_UNICODE);
        exit;
    }

    public function index(): string
    {
        $this->requireReconAccess();
        $filters = [
            'group_id'  => Request::has('group_id') && Request::string('group_id') !== '' ? Request::int('group_id') : null,
            'direction' => in_array(Request::string('direction'), Reconciliation::DIRECTIONS, true) ? Request::string('direction') : null,
            'status'    => in_array(Request::string('status'), Reconciliation::STATUSES, true) ? Request::string('status') : null,
            'rng'       => Reconciliation::normaliseRangeFilters($_GET['rng'] ?? null, $_GET['txt'] ?? null)['rng'],
            'txt'       => Reconciliation::normaliseRangeFilters($_GET['rng'] ?? null, $_GET['txt'] ?? null)['txt'],
            'search'    => Request::string('search'),
            'highlight' => in_array(Request::string('highlight'), ['plus', 'minus'], true) ? Request::string('highlight') : null,
            'min_diff'  => Request::string('min_diff') === '10jt' ? '10jt' : null,
            'stage'     => in_array(Request::string('stage'), ['pending', 'counted', 'discrepancy', 'done'], true) ? Request::string('stage') : null,
            'recheck'   => Request::string('recheck') === '1' ? '1' : null,
            'col'       => Reconciliation::normaliseColFilters($_GET['col'] ?? null),
            'adv'       => \App\Support\AdvancedSearch::normalise($_GET['adv'] ?? null, Reconciliation::COL_MAP, ['harga', 'price', 'stok', 'stock', 'hitung', 'counted', 'selqty', 'difference', 'selnil', 'diff_amount']),
            'sort'      => array_key_exists(Request::string('sort', 'diff_desc'), Reconciliation::SORT_LABELS) ? Request::string('sort', 'diff_desc') : 'diff_desc',
        ];
        $page = max(1, Request::int('page', 1));
        $perPage = self::resolvePerPage();

        $reconModel = new Reconciliation();

        $currentPeriod  = \App\Models\BookPeriod::current();
        $isReadOnly     = $currentPeriod !== null && (($currentPeriod['status'] ?? 'open') === 'closed' || ! empty($currentPeriod['is_archive_view']));
        $canSeeStockQty = in_array(Auth::role(), ['poweradmin', 'adminsto'], true);
        $groups         = (new Group())->forActiveUser();

        if ($isReadOnly && $currentPeriod !== null) {
            $periodId = (int) $currentPeriod['id'];
            $db = \App\Core\Database::connection();
            $stmt = $db->prepare(
                "SELECT s.id, s.item_id, s.item_code, s.item_name,
                        s.item_code AS code, s.item_name AS name,
                        g.name AS group_name, g.code AS group_code, g.id AS group_id,
                        s.unit_price, s.expected_qty, s.expected_qty AS stock_qty,
                        s.counted_qty, s.audited_qty, s.difference,
                        (s.difference * s.unit_price) AS diff_amount,
                        CASE WHEN s.difference > 0 THEN 'plus' ELSE (CASE WHEN s.difference < 0 THEN 'minus' ELSE 'match' END) END AS direction,
                        s.count_status, s.audit_status,
                        'verified' AS status,
                        '-' AS submitted_by_name,
                        '-' AS verified_by_name,
                        '-' AS verify_note,
                        COALESCE(l.name, '-') AS location_name,
                        COALESCE(i.category, 'Umum') AS category,
                        COALESCE(i.unit, 'pcs') AS unit
                 FROM period_snapshots s
                 JOIN `groups` g ON g.id = s.group_id
                 LEFT JOIN items i ON i.id = s.item_id
                 LEFT JOIN locations l ON l.id = i.location_id
                 WHERE s.period_id = ? AND s.difference IS NOT NULL AND s.difference <> 0
                 ORDER BY ABS(s.difference * s.unit_price) DESC"
            );
            $stmt->execute([$periodId]);
            $allDiffRows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            $totStmt = $db->prepare(
                "SELECT 
                    COUNT(*) AS total_items,
                    SUM(CASE WHEN s.difference < 0 THEN 1 ELSE 0 END) AS minus_items,
                    SUM(CASE WHEN s.difference > 0 THEN 1 ELSE 0 END) AS plus_items,
                    SUM(CASE WHEN s.difference = 0 THEN 1 ELSE 0 END) AS match_items,
                    COUNT(*) AS verified_items,
                    0 AS pending_items,
                    SUM(s.expected_qty) AS total_stock_qty,
                    SUM(s.counted_qty) AS total_counted_qty,
                    SUM(s.difference) AS total_diff_qty,
                    SUM(CASE WHEN s.difference < 0 THEN (s.difference * s.unit_price) ELSE 0 END) AS total_minus_amount,
                    SUM(CASE WHEN s.difference > 0 THEN (s.difference * s.unit_price) ELSE 0 END) AS total_plus_amount,
                    SUM(s.difference * s.unit_price) AS net_diff_amount,
                    SUM(s.expected_qty * s.unit_price) AS total_stock_amount,
                    SUM(s.counted_qty * s.unit_price) AS total_count_amount
                 FROM period_snapshots s
                 WHERE s.period_id = ?"
            );
            $totStmt->execute([$periodId]);
            $totalsRow = $totStmt->fetch(\PDO::FETCH_ASSOC) ?: [];

            $totals = [
                'total_items'        => (int) ($totalsRow['total_items'] ?? 0),
                'minus_items'        => (int) ($totalsRow['minus_items'] ?? 0),
                'plus_items'         => (int) ($totalsRow['plus_items'] ?? 0),
                'match_items'        => (int) ($totalsRow['match_items'] ?? 0),
                'verified_items'     => (int) ($totalsRow['verified_items'] ?? 0),
                'pending_items'      => 0,
                'total_stock_qty'    => (int) ($totalsRow['total_stock_qty'] ?? 0),
                'total_counted_qty'  => (int) ($totalsRow['total_counted_qty'] ?? 0),
                'total_diff_qty'     => (int) ($totalsRow['total_diff_qty'] ?? 0),
                'total_minus_amount' => (float) ($totalsRow['total_minus_amount'] ?? 0),
                'total_plus_amount'  => (float) ($totalsRow['total_plus_amount'] ?? 0),
                'net_diff_amount'    => (float) ($totalsRow['net_diff_amount'] ?? 0),
                'total_stock_amount' => (float) ($totalsRow['total_stock_amount'] ?? 0),
                'total_count_amount' => (float) ($totalsRow['total_count_amount'] ?? 0),
                'minus_items_big'    => 0,
                'plus_items_big'     => 0,
            ];

            $rows = [
                'items'     => $allDiffRows,
                'total'     => count($allDiffRows),
                'page'      => 1,
                'per_page'  => count($allDiffRows) > 0 ? count($allDiffRows) : 50,
                'last_page' => 1,
                'from'      => 1,
                'to'        => count($allDiffRows),
            ];
            $summaryGroups = [];
            $stageSummary  = ['pending' => 0, 'counted' => 0, 'discrepancy' => 0, 'done' => count($allDiffRows)];
            $stageMap      = [];
        } else {
            $summaryGroups  = $reconModel->summaryPerGroup();
            $totals         = $reconModel->summaryTotals();
            $rows           = $reconModel->listDetailed($filters, $page, $perPage);
            $thresholdCounts = $reconModel->thresholdCounts();
            $totals['minus_items_big'] = $thresholdCounts['minus_n'];
            $totals['plus_items_big']  = $thresholdCounts['plus_n'];
            $stageSummary = $reconModel->stageSummary();
            $stageMap     = $reconModel->stageMap();
        }

        return $this->view('reconciliation/index', [
            'title'          => __('Rekonsiliasi & Nilai Stok'),
            'rows'           => $rows,
            'filters'        => $filters,
            'groups'         => $groups,
            'summaryGroups'  => $summaryGroups,
            'totals'         => $totals,
            'stageSummary'   => $stageSummary,
            'stageMap'       => $stageMap,
            'canSeeStockQty' => $canSeeStockQty,
            'directions'     => Reconciliation::DIRECTIONS,
            'statuses'       => Reconciliation::STATUSES,
            'sortLabels'     => Reconciliation::SORT_LABELS,
            'bigThreshold'   => Reconciliation::BIG_THRESHOLD,
            'perPage'        => $perPage,
            'perPageOptions' => self::PER_PAGE_OPTIONS,
            'isReadOnly'     => $isReadOnly,
            'currentPeriod'  => $currentPeriod,
        ]);
    }

    /** Pilihan preset paginasi; nilai lain (custom) dibatasi 10..500. */
    public const PER_PAGE_OPTIONS = [20, 50, 100, 200];
    public const PER_PAGE_DEFAULT = 50;
    public const PER_PAGE_MAX = 500;

    private static function resolvePerPage(): int
    {
        if (Request::string('all', '') === '1') { return 2000; }
        $n = (int) Request::int('per_page', self::PER_PAGE_DEFAULT);
        if ($n < 10) { return self::PER_PAGE_DEFAULT; }
        return min($n, self::PER_PAGE_MAX);
    }

    public function show(array $params): string
    {
        if (! \App\Core\Auth::check()) {
            $this->abort(403, 'Tidak ada user aktif.');
        }
        if (! in_array(\App\Core\Auth::role(), ['poweradmin', 'adminsto', 'audit', 'observer'], true)) {
            $this->abort(403, 'Rekonsiliasi hanya dapat diakses Power Admin, Admin STO, dan Auditor.');
        }
        $id = (int) $params['id'];
        $reconModel = new Reconciliation();
        $row = $reconModel->findDetailed($id);

        if ($row === null) {
            $this->notFound('Data rekonsiliasi tidak ditemukan.');
        }

        $this->ensureGroupAccess((int) $row['group_id']);

        $canSeeStockQty = in_array(Auth::role(), ['poweradmin', 'adminsto'], true);
        $canVerify      = in_array(Auth::role(), ['poweradmin', 'adminsto'], true);

        $historyModel = new CountHistory();
        $timeline     = $historyModel->timelineForRecon($id);

        $itemModel = new Item();
        $item      = $itemModel->findDetailed((int) $row['item_id']);

        return $this->view('reconciliation/show', [
            'title'          => __('Detail Rekonsiliasi: ') . ($row['item_code'] ?? 'Item'),
            'recon'          => $row,
            'item'           => $item,
            'timeline'       => $timeline,
            'canSeeStockQty' => $canSeeStockQty,
            'canVerify'      => $canVerify,
        ]);
    }

    /**
     * Submit update/koreksi hasil hitung atau harga satuan.
     * Menggunakan verifikasi input user + pencatatan audit trail (count_history).
     */
    public function update(array $params): string
    {
        $this->requireReconAccess();
        $this->verifyCsrf();
        $this->guardOpenPeriod("/reconciliation");

        $id = (int) $params['id'];
        $reconModel = new Reconciliation();
        $row = $reconModel->find($id);

        if ($row === null) {
            $this->notFound('Data rekonsiliasi tidak ditemukan.');
        }

        $this->ensureGroupAccess((int) $row['group_id']);

        $this->validate([
            'counted_qty' => 'required|integer|min:0',
            'reason'      => 'required|min:3|max:255',
        ], [
            'counted_qty' => 'Hasil Hitung',
            'reason'      => 'Alasan Perubahan',
        ]);

        $oldCounted  = (int) $row['counted_qty'];
        $newCounted  = Request::int('counted_qty');
        $reason      = trim(Request::string('reason'));
        $unitPrice   = Request::has('unit_price') ? max(0.0, (float) Request::string('unit_price')) : (float) $row['unit_price'];
        $oldPrice    = (float) $row['unit_price'];

        $historyModel = new CountHistory();

        // Cek apakah ada perubahan
        $hasQtyChange   = ($oldCounted !== $newCounted);
        $hasPriceChange = (abs($oldPrice - $unitPrice) > 0.01);

        if (! $hasQtyChange && ! $hasPriceChange) {
            return $this->redirectWith($this->safeReturn('/reconciliation/' . $id), 'info', 'Tidak ada data angka yang berubah.');
        }

        $userRole = Auth::role();
        $isAdmin  = in_array($userRole, ['poweradmin', 'adminsto'], true);

        // Update reconciliation row
        $stockQty    = (int) $row['stock_qty'];
        $diff        = $newCounted - $stockQty;
        $dir         = $diff > 0 ? 'plus' : ($diff < 0 ? 'minus' : 'match');
        $stockAmount = round($stockQty * $unitPrice, 2);
        $countAmount = round($newCounted * $unitPrice, 2);
        $diffAmount  = round($diff * $unitPrice, 2);

        $newStatus = $isAdmin ? 'verified' : 'pending_verify';

        $reconModel->updateById($id, [
            'counted_qty'   => $newCounted,
            'unit_price'    => $unitPrice,
            'difference'    => $diff,
            'stock_amount'  => $stockAmount,
            'count_amount'  => $countAmount,
            'diff_amount'   => $diffAmount,
            'direction'     => $dir,
            'status'        => $newStatus,
            'submitted_by'  => Auth::id(),
            'submitted_at'  => date('Y-m-d H:i:s'),
            'verified_by'   => $isAdmin ? Auth::id() : $row['verified_by'],
            'verified_at'   => $isAdmin ? date('Y-m-d H:i:s') : $row['verified_at'],
            'verify_note'   => $isAdmin ? 'Diverifikasi langsung oleh ' . Auth::role() : $row['verify_note'],
        ]);

        // Juga update di tabel items agar tersinkronisasi
        $itemModel = new Item();
        $itemModel->updateById((int) $row['item_id'], [
            'counted_qty' => $newCounted,
            'unit_price'  => $unitPrice,
            'counted_by'  => Auth::id(),
            'counted_at'  => date('Y-m-d H:i:s'),
        ]);

        // Catat ke audit trail count_history
        if ($hasQtyChange) {
            $historyModel->record(
                (int) $row['item_id'],
                $id,
                'counted_qty',
                $oldCounted,
                $newCounted,
                $reason,
                $isAdmin ? 'direct_update' : 'cross_check'
            );
        }

        if ($hasPriceChange) {
            $historyModel->record(
                (int) $row['item_id'],
                $id,
                'unit_price',
                $oldPrice,
                $unitPrice,
                $reason . ' (Harga Satuan disesuaikan)',
                'price_update'
            );
        }

        (new ActivityLog())->record(
            'update',
            'reconciliation',
            $id,
            "Koreksi hitung: {$oldCounted} -> {$newCounted} (Alasan: {$reason})",
            (int) $row['group_id']
        );

        if (! $isAdmin) {
            (new Notification())->toRole(
                'adminsto',
                'Koreksi Rekonsiliasi Perlu Verifikasi',
                "Hasil hitung item ID {$row['item_id']} diubah ke {$newCounted}. Alasan: {$reason}",
                'warning',
                '/reconciliation/' . $id,
                (int) $row['group_id']
            );
        }

        $msg = $isAdmin
            ? 'Perubahan angka berhasil disimpan dan langsung diverifikasi.'
            : 'Perubahan angka berhasil disimpan dan menunggu verifikasi Admin STO / Power Admin.';

        return $this->redirectWith($this->safeReturn('/reconciliation/' . $id), 'success', $msg);
    }

    /**
     * Verifikasi atau tolak perubahan oleh Admin STO / Power Admin.
     */
    public function verify(array $params): string
    {
        $this->verifyCsrf();
        $this->guardOpenPeriod("/reconciliation");

        if (! in_array(Auth::role(), ['poweradmin', 'adminsto'], true)) {
            $this->abort(403, 'Hanya Power Admin dan Admin STO yang dapat memverifikasi rekonsiliasi.');
        }

        $id = (int) $params['id'];
        $reconModel = new Reconciliation();
        $row = $reconModel->find($id);

        if ($row === null) {
            $this->notFound('Data rekonsiliasi tidak ditemukan.');
        }

        $this->ensureGroupAccess((int) $row['group_id']);

        $action = Request::string('action', 'approve');
        $note   = trim(Request::string('verify_note', ''));

        $historyModel = new CountHistory();

        if ($action === 'approve') {
            $reconModel->updateById($id, [
                'status'      => 'verified',
                'verified_by' => Auth::id(),
                'verified_at' => date('Y-m-d H:i:s'),
                'verify_note' => $note ?: 'Diverifikasi dan disetujui.',
            ]);

            $historyModel->record(
                (int) $row['item_id'],
                $id,
                'status',
                $row['status'],
                'verified',
                $note ?: 'Disetujui oleh ' . Auth::role(),
                'verification'
            );

            // Item dinyatakan SELESAI: hitung -> posting -> audit -> rekonsiliasi
            // terverifikasi Admin STO. Ini mengubah status item jadi 'Selesai'
            // dan menutup alur (terkunci dari hitung ulang biasa).
            (new Item())->markReconciled((int) $row['item_id']);

            // Tutup selisih terkait jika ada di tabel discrepancies
            (new \App\Models\Discrepancy())->resolveForReconciledItem((int) $row['item_id'], (int) Auth::id(), $note);

            (new ActivityLog())->record(
                'verify',
                'reconciliation',
                $id,
                'Menyetujui rekonsiliasi item ID ' . $row['item_id'],
                (int) $row['group_id']
            );

            return $this->redirectWith('/reconciliation/' . $id, 'success', 'Rekonsiliasi berhasil disetujui dan diverifikasi.');
        }

        // Action reject
        $reconModel->updateById($id, [
            'status'      => 'rejected',
            'verified_by' => Auth::id(),
            'verified_at' => date('Y-m-d H:i:s'),
            'verify_note' => $note ?: 'Ditolak saat verifikasi.',
        ]);

        $historyModel->record(
            (int) $row['item_id'],
            $id,
            'status',
            $row['status'],
            'rejected',
            $note ?: 'Ditolak oleh ' . Auth::role(),
            'rejection'
        );

        (new ActivityLog())->record(
            'reject',
            'reconciliation',
            $id,
            'Menolak rekonsiliasi item ID ' . $row['item_id'] . ': ' . $note,
            (int) $row['group_id']
        );

        return $this->redirectWith('/reconciliation/' . $id, 'warning', 'Rekonsiliasi ditolak.');
    }

    /**
     * Kirim item ke petugas untuk dicek ulang.
     *
     * Menandai rekonsiliasi kembali ke status 'draft' (perlu hitung ulang),
     * mencatat audit trail, dan mengirim notifikasi ke role observer/audit
     * serta adminsto.
     */
    public function recheck(array $params): string
    {
        $this->requireReconAccess();
        $this->verifyCsrf();
        $this->guardOpenPeriod("/reconciliation");

        $id = (int) $params['id'];
        $reconModel = new Reconciliation();
        $row = $reconModel->find($id);

        if ($row === null) {
            $this->notFound('Data rekonsiliasi tidak ditemukan.');
        }

        $this->ensureGroupAccess((int) $row['group_id']);

        $note = trim(Request::string('recheck_note', ''));
        $oldStatus = (string) $row['status'];

        $reconModel->updateById($id, [
            'status'            => 'draft',
            'recheck_requested' => 1,
            'verify_note'       => $note !== '' ? $note : 'Dikirim ke petugas untuk dicek ulang.',
            'verified_by'       => null,
            'verified_at'       => null,
        ]);

        // Item keluar dari alur posting/audit -> kembali 'counted' + audit
        // 'recount', kunci dibuka agar observer bisa hitung & posting ulang.
        (new Item())->markRecount((int) $row['item_id']);

        // Audit trail
        (new CountHistory())->record(
            (int) $row['item_id'],
            $id,
            'status',
            $oldStatus,
            'draft',
            $note !== '' ? $note : 'Diminta cek ulang oleh ' . Auth::role(),
            'recheck_request'
        );

        (new ActivityLog())->record(
            'recheck',
            'reconciliation',
            $id,
            'Meminta cek ulang hitung fisik item ID ' . $row['item_id'] . ($note !== '' ? ': ' . $note : ''),
            (int) $row['group_id']
        );

        // Notifikasi ke petugas (observer) + admin STO
        $itemCode = (string) ($row['item_code'] ?? '');
        if ($itemCode === '') {
            $itemRow  = (new Item())->find((int) $row['item_id']);
            $itemCode = (string) ($itemRow['code'] ?? ('#' . $row['item_id']));
        }
        $itemName = (string) ($row['item_name'] ?? '');

        $title = 'Permintaan Cek Ulang Hitung Fisik';
        $message = 'Item ' . $itemCode . ($itemName !== '' ? ' (' . $itemName . ')' : '')
            . ' diminta dihitung ulang oleh ' . Auth::role()
            . ($note !== '' ? '. Catatan: ' . $note : '.');

        $notification = new Notification();
        $groupId = (int) $row['group_id'];
        try {
            // Petugas (observer) diarahkan ke Data STO tab Cek Ulang
            $notification->toRole('observer', $title, $message, 'warning', '/items?count_status[]=recount', $groupId);
            // Auditor diarahkan ke Audit tab Cek Ulang
            $notification->toRole('audit', $title, $message, 'warning', '/audit?tab=recheck', $groupId);
            // Admin STO diarahkan ke Rekonsiliasi
            $notification->toRole('adminsto', $title, $message, 'warning', '/reconciliation/' . $id, $groupId);
        } catch (\Throwable $e) {
            // notifikasi opsional
        }

        return $this->redirectWith($this->safeReturn('/reconciliation/' . $id), 'success', 'Item berhasil dikirim ke petugas untuk dicek ulang.');
    }

    /**
     * Riwayat audit koreksi rekonsiliasi (count_history) — halaman konsisten
     * dengan UI/UX Rekonsiliasi. Filter: group, aksi, field, cari.
     */
    /** Export rekonsiliasi ke xlsx (format SAP). Memakai filter yang sama dengan halaman. */
    public function exportSap(): void
    {
        $this->requireReconAccess();
        $this->authorize('export_data');
        $this->verifyCsrf();

        $groupId = Request::int('group_id') ?: null;
        if ($groupId !== null) {
            $this->ensureGroupAccess($groupId);
        }

        // Lingkup export: all | group | diff (selisih saja) | filter (ikut filter layar).
        $scope = Request::string('scope', 'filter');
        if (! in_array($scope, ['all', 'group', 'diff', 'filter'], true)) { $scope = 'filter'; }
        if ($scope === 'group' && $groupId === null) { $scope = 'all'; }

        $filters = [
            'group_id'  => $scope === 'all' ? null : $groupId,
            'direction' => $scope === 'filter' && in_array(Request::string('direction'), Reconciliation::DIRECTIONS, true) ? Request::string('direction') : null,
            'status'    => $scope === 'filter' && in_array(Request::string('status'), Reconciliation::STATUSES, true) ? Request::string('status') : null,
            'search'    => $scope === 'filter' ? Request::string('search') : '',
            'highlight' => null,
            'min_diff'  => $scope === 'filter' && Request::string('min_diff') === '10jt' ? '10jt' : null,
            'stage'     => $scope === 'filter' && in_array(Request::string('stage'), ['pending', 'counted', 'discrepancy', 'done'], true) ? Request::string('stage') : null,
            'sort'      => in_array(Request::string('sort'), array_keys(Reconciliation::SORT_LABELS), true) ? Request::string('sort') : 'diff_desc',
        ];
        $model = new Reconciliation();
        // Semua baris sesuai lingkup (tanpa paginasi).
        $result = $model->listDetailed($filters, 1, 100000);
        $rows = $result['items'] ?? [];
        if ($scope === 'diff') {
            $rows = array_values(array_filter($rows, static fn ($r) => in_array($r['direction'] ?? '', ['plus', 'minus'], true)));
        }

        $logModel = new ExportLog();
        $logId = $logModel->insert([
            'user_id'    => Auth::id(),
            'group_id'   => $groupId,
            'type'       => 'reconciliation_sap',
            'filename'   => 'rekonsiliasi_' . date('Ymd_His') . '.xlsx',
            'total_rows' => count($rows),
            'progress'   => 10,
            'status'     => 'processing',
        ]);

        $fileName = 'rekonsiliasi_' . date('Ymd_His') . '_' . $logId . '.xlsx';
        $outPath  = ExportLog::storageDir() . '/' . $fileName;

        // Susun baris untuk SAP (nilai angka mentah, bukan HTML/format tampilan).
        // Kirim seluruh field baris (raw). Susunan kolom & label ada di scripts/export_recon_xlsx.py.
        $payloadRows = [];
        foreach ($rows as $r) {
            $payloadRows[] = $r;
        }
        // Kolom: urutan & pilihan dari layar (key = data-col). Kosong = semua kolom default.
        $colsRaw = Request::string('cols', '');
        $cols = array_values(array_filter(array_map('trim', explode(',', $colsRaw)), static fn ($k) => preg_match('/^[a-z_]{2,20}$/', $k) === 1));

        $script = dirname(__DIR__, 2) . '/scripts/export_recon_xlsx.py';
        $json = json_encode([
            'out'  => $outPath,
            'rows' => $payloadRows,
            'cols' => $cols,
            'scope' => $scope,
            'meta' => ['Dibuat oleh' => (string) (Auth::user()['name'] ?? ''), 'Waktu export' => date('Y-m-d H:i:s'), 'Lingkup' => $scope],
        ], JSON_UNESCAPED_UNICODE);

        $proc = proc_open(['python3', $script], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        $stdout = '';
        if (is_resource($proc)) {
            fwrite($pipes[0], (string) $json);
            fclose($pipes[0]);
            $stdout = stream_get_contents($pipes[1]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            proc_close($proc);
        }

        $res = json_decode(trim($stdout), true);
        if (! is_array($res) || empty($res['ok'])) {
            $logModel->updateById($logId, [
                'status'      => 'failed',
                'progress'    => 100,
                'message'     => 'Export gagal: ' . (is_array($res) ? (string) ($res['error'] ?? 'tidak diketahui') : 'proses tidak mengembalikan hasil'),
                'finished_at' => date('Y-m-d H:i:s'),
            ]);
            flash('error', 'Export gagal. Lihat riwayat export untuk detail.');
            $this->redirectWith('/exports', 'error', 'Export gagal.');
        }

        $logModel->updateById($logId, [
            'stored_path'  => $fileName,
            'success_rows' => (int) $res['rows'],
            'progress'     => 100,
            'status'       => 'success',
            'finished_at'  => date('Y-m-d H:i:s'),
        ]);

        $this->redirectWith('/exports', 'success', 'Export berhasil: ' . (int) $res['rows'] . ' baris. Unduh dari tabel di bawah.');
    }

    public function auditTrail(): string
    {
        if (! in_array(Auth::role(), ['poweradmin', 'adminsto'], true)) {
            $this->abort(403, 'Hanya Power Admin dan Admin STO yang dapat melihat audit rekonsiliasi.');
        }

        $actionLabels = [
            'direct_update'  => 'Koreksi (Admin)',
            'cross_check'    => 'Koreksi (Petugas)',
            'price_update'   => 'Ubah Harga',
            'verification'   => 'Verifikasi',
            'rejection'      => 'Penolakan',
            'recheck_request'=> 'Cek Ulang',
        ];
        $fieldLabels = [
            'counted_qty' => 'Hasil Hitung',
            'unit_price'  => 'Harga Satuan',
            'status'      => 'Status',
        ];

        $filters = [
            'group_id' => Request::has('group_id') && Request::string('group_id') !== '' ? Request::int('group_id') : null,
            'action'   => array_key_exists(Request::string('action'), $actionLabels) ? Request::string('action') : null,
            'field'    => array_key_exists(Request::string('field'), $fieldLabels) ? Request::string('field') : null,
            'search'   => Request::string('search'),
        ];
        $page = max(1, Request::int('page', 1));

        $scope = 'r.group_id';   // count_history join reconciliation
        $clauses  = [];
        $bindings = [];

        [$gs, $gsb] = Auth::groupScope($scope);
        if ($gs !== '') { $clauses[] = $gs; $bindings = array_merge($bindings, $gsb); }

        if (! empty($filters['group_id'])) { $clauses[] = 'r.group_id = ?'; $bindings[] = (int) $filters['group_id']; }
        if (! empty($filters['action']))   { $clauses[] = 'h.action = ?';   $bindings[] = $filters['action']; }
        if (! empty($filters['field']))    { $clauses[] = 'h.field_name = ?'; $bindings[] = $filters['field']; }
        if (! empty($filters['search'])) {
            $s = '%' . trim((string) $filters['search']) . '%';
            $clauses[] = '(i.code LIKE ? OR i.name LIKE ? OR h.reason LIKE ?)';
            array_push($bindings, $s, $s, $s);
        }

        $where = $clauses === [] ? '' : ' WHERE ' . implode(' AND ', $clauses);

        $base = 'FROM count_history h
                 JOIN reconciliation r ON r.id = h.recon_id
                 JOIN items i ON i.id = h.item_id
                 LEFT JOIN `groups` g ON g.id = r.group_id
                 LEFT JOIN users u ON u.id = h.changed_by' . $where;

        $model = new CountHistory();
        $rows = $model->paginate(
            'SELECT h.id, h.field_name, h.old_value, h.new_value, h.delta, h.reason, h.action, h.changed_at,
                    i.code AS item_code, i.name AS item_name,
                    g.name AS group_name,
                    u.name AS user_name, u.role AS user_role ' . $base
            . ' ORDER BY h.changed_at DESC, h.id DESC',
            $bindings,
            $page,
            (int) config('app.per_page', 20)
        );

        $totalsRow = $model->first(
            'SELECT COUNT(h.id) AS total,
                    COUNT(DISTINCT h.changed_by) AS actors,
                    SUM(CASE WHEN h.field_name = \'counted_qty\' THEN 1 ELSE 0 END) AS qty_changes,
                    SUM(CASE WHEN h.changed_at >= DATE_SUB(NOW(), INTERVAL 1 DAY) THEN 1 ELSE 0 END) AS today '
            . $base,
            $bindings
        ) ?? [];

        return $this->view('reconciliation/audit', [
            'title'         => __('Audit Riwayat Koreksi Rekonsiliasi'),
            'rows'          => $rows,
            'filters'       => $filters,
            'groups'        => (new Group())->forActiveUser(),
            'totals'        => $totalsRow,
            'actionLabels'  => $actionLabels,
            'fieldLabels'   => $fieldLabels,
        ]);
    }

    /**
     * Kirim BANYAK item sekaligus ke petugas untuk dicek ulang (aksi bulk).
     * Reuse logika recheck per-item; cap 300 item per aksi.
     */
    public function bulkRecheck(): string
    {
        $this->requireReconAccess();
        $this->verifyCsrf();
        $this->guardOpenPeriod("/reconciliation");

        $returnTo = $this->safeReturn('/reconciliation');

        // Mode 1: daftar id eksplisit dari checkbox.
        $ids = Request::input('ids', []);
        if (is_string($ids)) {
            $ids = array_filter(array_map('intval', explode(',', $ids)));
        }
        if (! is_array($ids)) {
            $ids = [];
        }
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn ($v) => $v > 0)));

        // Mode 2: berdasarkan filter aktif (bulk "semua yang cocok").
        if ($ids === [] && Request::string('bulk_scope') === 'filter') {
            $filters = [
                'group_id'  => Request::has('group_id') && Request::string('group_id') !== '' ? Request::int('group_id') : null,
                'direction' => in_array(Request::string('direction'), Reconciliation::DIRECTIONS, true) ? Request::string('direction') : null,
                'status'    => in_array(Request::string('status'), Reconciliation::STATUSES, true) ? Request::string('status') : null,
                'search'    => Request::string('search'),
                'highlight' => in_array(Request::string('highlight'), ['plus', 'minus'], true) ? Request::string('highlight') : null,
                'min_diff'  => Request::string('min_diff') === '10jt' ? '10jt' : null,
            ];
            $reconModel = new Reconciliation();
            $ids = array_map(static fn ($r) => (int) $r['id'], $reconModel->idsForBulk($filters));
        }

        if ($ids === []) {
            return $this->redirectWith($returnTo, 'warning', 'Tidak ada item yang dipilih untuk dikirim ke petugas.');
        }

        $reconModel   = new Reconciliation();
        $notification = new Notification();
        $processed    = 0;
        $skipped      = 0;

        foreach (array_slice($ids, 0, 300) as $id) {
            $row = $reconModel->find($id);
            if ($row === null) {
                $skipped++;
                continue;
            }

            if (! Auth::canSeeGroup((int) $row['group_id'])) {
                $skipped++;
                continue;
            }

            $oldStatus = (string) $row['status'];

            $reconModel->updateById($id, [
                'status'            => 'draft',
                'recheck_requested' => 1,
                'verify_note'       => 'Dikirim massal ke petugas untuk dicek ulang.',
                'verified_by'       => null,
                'verified_at'       => null,
            ]);

            (new CountHistory())->record(
                (int) $row['item_id'],
                $id,
                'status',
                $oldStatus,
                'draft',
                'Diminta cek ulang (bulk) oleh ' . Auth::role(),
                'recheck_request'
            );

            // Tandai item di items table agar needs_recount = 1 dan masuk ke Data STO tab Cek Ulang & Audit
            (new Item())->markRecount((int) $row['item_id']);

            $processed++;
        }

        if ($processed === 0) {
            return $this->redirectWith($returnTo, 'warning', 'Tidak ada item yang berhasil dikirim (mungkin akses group terbatas).');
        }

        (new ActivityLog())->record(
            'recheck_bulk',
            'reconciliation',
            0,
            "Bulk cek ulang: {$processed} item dikirim ke petugas" . ($skipped > 0 ? ", {$skipped} dilewati." : '.'),
            null
        );

        $title   = 'Permintaan Cek Ulang (Bulk)';
        $codes = [];
        foreach (array_slice($ids, 0, 5) as $cid) {
            $crow = $reconModel->find($cid);
            if ($crow !== null) {
                $icode = \App\Core\Database::connection()->prepare('SELECT code FROM items WHERE id = ?');
                $icode->execute([(int) $crow['item_id']]);
                $codes[] = (string) ($icode->fetchColumn() ?: $cid);
            }
        }
        $more = $processed > count($codes) ? ' dan ' . ($processed - count($codes)) . ' lainnya' : '';
        $message = $processed . ' item diminta dihitung ulang oleh ' . Auth::role() . ': '
            . ($codes !== [] ? implode(', ', $codes) . $more : '-') . '. Buka daftar cek ulang untuk memproses.';
        try {
            // Petugas (observer) diarahkan ke Data STO tab Cek Ulang
            $notification->toRole('observer', $title, $message, 'warning', '/items?count_status[]=recount', null);
            // Auditor diarahkan ke Audit tab Cek Ulang
            $notification->toRole('audit', $title, $message, 'warning', '/audit?tab=recheck', null);
            // Admin STO diarahkan ke Rekonsiliasi
            $notification->toRole('adminsto', $title, $message, 'warning', '/reconciliation?recheck=1', null);
        } catch (\Throwable $e) {
            // notifikasi opsional
        }

        return $this->redirectWith($returnTo, 'success', $processed . ' item berhasil dikirim ke petugas untuk dicek ulang.');
    }

    /**
     * Sinkronisasi data rekonsiliasi dari items.
     */
    public function sync(): string
    {
        $this->verifyCsrf();

        if (! in_array(Auth::role(), ['poweradmin', 'adminsto'], true)) {
            $this->abort(403, 'Hanya Power Admin dan Admin STO yang dapat melakukan sinkronisasi.');
        }

        $reconModel = new Reconciliation();
        $created = $reconModel->syncFromItems();

        (new ActivityLog())->record(
            'sync',
            'reconciliation',
            0,
            "Sinkronisasi data rekonsiliasi: {$created} item baru ditambahkan.",
            null
        );

        return $this->redirectWith('/reconciliation', 'success', "Sinkronisasi selesai. {$created} item baru ditambahkan ke rekonsiliasi.");
    }

    /** Rekonsiliasi hanya untuk user yang memiliki rule can_view / poweradmin & adminsto. */
    private function requireReconAccess(): void
    {
        if (! Auth::check()) { $this->abort(403, 'Tidak ada user aktif.'); }
        if (function_exists('can_view_menu') && ! can_view_menu('reconciliation')) {
            $this->abort(403, 'Anda tidak memiliki hak akses ke menu Rekonsiliasi.');
        }
        if (! in_array(Auth::role(), ['poweradmin', 'adminsto'], true) && ! (function_exists('can_view_menu') && can_view_menu('reconciliation'))) {
            $this->abort(403, 'Rekonsiliasi hanya dapat diakses Power Admin dan Admin STO.');
        }
    }
}
/* I18N5-CTRL */
