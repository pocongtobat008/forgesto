<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Request;
use App\Models\ActivityLog;
use App\Models\Audit;
use App\Models\Device;
use App\Models\Discrepancy;
use App\Models\Group;
use App\Models\Item;
use App\Models\Location;
use App\Models\Notification;

class ItemController extends Controller
{
    use BookPeriodActions;
    use ItemMemoActions;
    use ItemMemoPdfActions;

    public function index(): string
    {
        $currentPeriod = \App\Models\BookPeriod::current();
        $isReadOnly = $currentPeriod !== null && (($currentPeriod['status'] ?? 'open') === 'closed' || ! empty($currentPeriod['is_archive_view']));

        $itemModel = new Item();
        $filters = $itemModel->normaliseFilters($_GET);

        // Default tab adalah 'pending' saat periode berjalan aktif.
        // Saat membuka arsip (read-only), default ke 'all' agar snapshot langsung tampil.
        if (empty($_GET['count_status']) && Request::string('tab') !== 'all') {
            if ($isReadOnly) {
                $_GET['tab'] = 'all';
            } else {
                $filters['count_status'] = ['pending'];
            }
        }

        $page = max(1, Request::int('page', 1));
        $perPage = (int) config('app.per_page', 15);
        $showAll = Request::string('all', '') === '1';
        if ($showAll) {
            $page = 1;
            $perPage = 2000;
        }

        $tabFilters = $filters;
        $tabFilters['count_status'] = [];

        if ($isReadOnly && $currentPeriod !== null) {
            $periodId = (int) $currentPeriod['id'];
            $itemsPage = $itemModel->listDetailedSnapshot($periodId, $filters, $page, $perPage);
            if ($page > (int) $itemsPage['last_page']) {
                $page = (int) $itemsPage['last_page'];
                $itemsPage = $itemModel->listDetailedSnapshot($periodId, $filters, $page, $perPage);
            }
            $tabCounts = $itemModel->summarySnapshot($periodId, $tabFilters);
        } else {
            $itemsPage = $itemModel->listDetailed($filters, $page, $perPage);
            if ($page > (int) $itemsPage['last_page']) {
                $page = (int) $itemsPage['last_page'];
                $itemsPage = $itemModel->listDetailed($filters, $page, $perPage);
            }
            $tabCounts = $itemModel->summary($tabFilters);
        }

        // --- Konteks Posting: PIC group + gate penghitungan ---
        // Group aktif = satu-satunya group yang difilter; kalau kosong, pakai
        // group pertama yang boleh diposting oleh user.
        $activeGroup = count((array) ($filters['group_id'] ?? [])) === 1
            ? (int) ((array) $filters['group_id'])[0]
            : null;

        $picGroups = (new \App\Models\GroupObserver())->groupIdsForPic((int) Auth::id());
        if (Auth::allGroups()) {
            // poweradmin/adminsto melihat semua group (bisa posting untuk semua).
            $picGroups = array_map(static fn ($g) => (int) $g['id'], (new Group())->forActiveUser());
        }
        $picGroups = array_values(array_unique(array_map('intval', $picGroups)));

        $groupPending = 0;
        $readiness = ['pending' => 0, 'counted' => 0, 'posted' => 0, 'total' => 0, 'ready' => false];
        if ($activeGroup !== null) {
            $readiness = $itemModel->postingReadiness($activeGroup, 'default');
            $groupPending = (int) $readiness['pending'];
        }

        return $this->view('items/index', [
            'title'         => __('Data STO'),
            'showAll'       => $showAll,
            'items'         => $itemsPage,
            'filters'       => $filters,
            'groups'        => (new Group())->forActiveUser(),
            'locations'     => (new Location())->forActiveUser(),
            'summary'       => $isReadOnly ? $tabCounts : $itemModel->summary($filters),
            'tabCounts'     => $tabCounts,
            'countStatuses' => Item::COUNT_STATUSES,
            'auditStatuses' => Item::AUDIT_STATUSES,
            'activeGroup'   => $activeGroup,
            'picGroups'     => $picGroups,
            'readiness'     => $readiness,
            'groupPending'  => $groupPending,
            'isReadOnly'    => $isReadOnly,
            'currentPeriod' => $currentPeriod,
        ]);
    }

    /**
     * JSON: cari satu item berdasarkan kode untuk scan barcode.
     *
     * Endpoint ini membuat scan tetap bekerja walau item berada di tab atau
     * halaman lain — pencarian dilakukan di database, bukan di DOM yang aktif.
     */
    public function lookup(): never
    {
        if (! Auth::check()) {
            header('Content-Type: application/json; charset=utf-8');
            http_response_code(401);
            echo json_encode(['found' => false, 'message' => 'Sesi berakhir. Muat ulang halaman.']);
            exit;
        }

        $code = trim((string) ($_GET['code'] ?? ''));
        if ($code === '') {
            header('Content-Type: application/json; charset=utf-8');
            http_response_code(400);
            echo json_encode(['found' => false, 'message' => 'Kode kosong.']);
            exit;
        }

        $itemModel = new Item();
        $item = $itemModel->findByCode($code);

        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');

        if ($item === null) {
            echo json_encode([
                'found'   => false,
                'message' => 'Barang dengan kode "' . $code . '" tidak ditemukan' . (Auth::allGroups() ? '.' : ' pada gudang/group Anda.'),
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $isLocked = (int) $item['is_locked'] === 1;
        $canCount = Auth::can('count_data') && (! $isLocked || Auth::can('unlock_data'));

        echo json_encode([
            'found' => true,
            'item'  => [
                'id'            => (int) $item['id'],
                'code'          => $item['code'],
                'name'          => $item['name'],
                'group'         => $item['group_name'] ?: '-',
                'group_code'    => $item['group_code'] ?? '',
                'location'      => $item['location_name'] ?: '-',
                'device'        => $item['device_name'] ?: '-',
                'unit'          => $item['unit'] ?: 'UNIT',
                'category'      => $item['category'] ?: '-',
                'sub_inventory' => $item['sub_inventory'] ?? '',
                'expected_qty'  => (can_see_stock_qty() && $item['expected_qty'] !== null) ? (int) $item['expected_qty'] : null,
                'can_see_qty'   => can_see_stock_qty(),
                'counted_qty'   => $item['counted_qty'] === null ? null : (int) $item['counted_qty'],
                'count_status'  => $item['count_status'],
                'audit_status'  => $item['audit_status'],
                'is_locked'     => $isLocked,
                'needs_recount' => (int) ($item['needs_recount'] ?? 0) === 1,
                'condition_note'=> $item['condition_note'] ?? '',
            ],
            'can_count' => $canCount,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    /** JSON: nilai unik untuk dropdown filter Excel satu kolom. */
    public function distinct(array $params = []): never
    {
        $itemModel = new Item();
        $filters = $itemModel->normaliseFilters($_GET);
        $key = (string) ($_GET['key'] ?? '');
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode(['values' => $itemModel->distinctValues($key, $filters)], JSON_UNESCAPED_UNICODE);
        exit;
    }

    /** Export xlsx: hanya baris yang lolos filter aktif (tanpa pagination). */
    public function exportFiltered(array $params = []): never
    {
        if (! can('export_data')) {
            http_response_code(403);
            echo 'Forbidden';
            exit;
        }

        $itemModel = new Item();
        $filters = $itemModel->normaliseFilters($_GET);
        $rows = $itemModel->exportRows($filters);

        $dir = dirname(__DIR__, 2) . "/storage/exports";
        if (! is_dir($dir)) @mkdir($dir, 0775, true);
        $fileName = 'data_sto_filter_' . date('Ymd_His') . '.xlsx';
        $outPath = $dir . '/' . $fileName;

        $script = dirname(__DIR__, 2) . '/scripts/export_items_xlsx.py';
        $json = json_encode(['out' => $outPath, 'rows' => $rows, 'meta' => [
            'Dibuat oleh' => (string) (Auth::user()['name'] ?? ''),
            'Waktu export' => date('Y-m-d H:i:s'),
            'Jumlah baris' => count($rows),
        ]], JSON_UNESCAPED_UNICODE);

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
        if (! is_array($res) || empty($res['ok']) || ! is_file($outPath)) {
            http_response_code(500);
            echo 'Export gagal.';
            exit;
        }
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $fileName . '"');
        header('Content-Length: ' . filesize($outPath));
        readfile($outPath);
        @unlink($outPath);
        exit;
    }
    public function create(): string
    {
        if (function_exists('can_action') && ! can_action('items', 'add')) {
            $this->abort(403, 'User Anda tidak memiliki izin untuk menambah data.');
        }
        $this->authorize('manage_data');

        return $this->view('items/form', [
            'title'     => __('Tambah Data'),
            'item'      => null,
            'groups'    => (new Group())->forActiveUser(),
            'locations' => (new Location())->forActiveUser(),
            'devices'   => [],
        ]);
    }

    public function store(): string
    {
        if (function_exists('can_action') && ! can_action('items', 'add')) {
            $this->abort(403, 'User Anda tidak memiliki izin untuk menambah data.');
        }
        $this->authorize('manage_data');
        $this->verifyCsrf();
        $this->guardOpenPeriod("/items");

        $this->validate([
            'group_id'     => 'required|integer|exists:groups,id',
            'code'         => 'required|max:80',
            'name'         => 'required|max:200',
            'expected_qty' => 'required|integer|min_value:0',
        ], [
            'group_id' => 'Group', 'code' => 'Kode', 'name' => 'Nama', 'expected_qty' => 'Qty sistem',
        ]);

        $groupId = Request::int('group_id');
        $this->ensureGroupAccess($groupId);

        $item = new Item();
        $code = Request::string('code');
        if ($item->first('SELECT id FROM items WHERE group_id = ? AND code = ?', [$groupId, $code]) !== null) {
            return $this->back('/items/create', Request::all(), ['code' => 'Kode sudah dipakai pada group tersebut.']);
        }

        $id = $item->insert([
            'group_id'     => $groupId,
            'location_id'  => Request::int('location_id') ?: null,
            'device_id'    => Request::int('device_id') ?: null,
            'code'         => $code,
            'name'         => Request::string('name'),
            'category'     => Request::string('category') ?: null,
            'unit'         => Request::string('unit') ?: null,
            'expected_qty' => Request::int('expected_qty'),
            'count_status' => Item::COUNT_PENDING,
            'audit_status' => Item::AUDIT_PENDING,
            'created_by'   => Auth::id(),
        ]);

        (new ActivityLog())->record('create', 'item', $id, 'Menambah data ' . $code, $groupId);

        return $this->redirectWith('/items/' . $id, 'success', 'Data berhasil ditambahkan.');
    }

    public function show(array $params): string
    {
        $id = (int) $params['id'];
        $item = (new Item())->findDetailed($id);
        if ($item === null) {
            $this->notFound('Data tidak ditemukan.');
        }
        $this->ensureGroupAccess($item['group_id']);

        return $this->view('items/show', [
            'title'        => __('Detail ') . $item['code'],
            'item'         => $item,
            'audits'       => (new Audit())->recentForItem($id),
            'discrepancy'  => (new Discrepancy())->findByItem($id),
        ]);
    }

    public function edit(array $params): string
    {
        $this->authorize('manage_data');

        $id = (int) $params['id'];
        $item = (new Item())->findDetailed($id);
        if ($item === null) {
            $this->notFound('Data tidak ditemukan.');
        }
        $this->ensureGroupAccess($item['group_id']);

        return $this->view('items/form', [
            'title'     => __('Edit Data'),
            'item'      => $item,
            'groups'    => (new Group())->forActiveUser(),
            'locations' => (new Location())->forActiveUser(),
            'devices'   => (new Device())->listDetailed(['group_id' => $item['group_id']], 1, 100)['items'],
        ]);
    }

    public function update(array $params): string
    {
        $this->guardOpenPeriod("/items");
        $this->authorize('manage_data');
        $this->verifyCsrf();

        $id = (int) $params['id'];
        $item = new Item();
        $row = $item->find($id);
        if ($row === null) {
            $this->notFound('Data tidak ditemukan.');
        }
        $this->ensureGroupAccess($row['group_id']);

        if ((int) $row['is_locked'] === 1 && ! Auth::can('unlock_data')) {
            return $this->redirectWith('/items/' . $id, 'error', 'Data terkunci. Ajukan unlock ke Admin STO untuk mengubah.');
        }

        $this->validate([
            'group_id'     => 'required|integer|exists:groups,id',
            'code'         => 'required|max:80',
            'name'         => 'required|max:200',
            'expected_qty' => 'required|integer|min_value:0',
        ], [
            'group_id' => 'Group', 'code' => 'Kode', 'name' => 'Nama', 'expected_qty' => 'Qty sistem',
        ]);

        $groupId = Request::int('group_id');
        $this->ensureGroupAccess($groupId);
        $code = Request::string('code');

        $duplicate = $item->first(
            'SELECT id FROM items WHERE group_id = ? AND code = ? AND id <> ?',
            [$groupId, $code, $id]
        );
        if ($duplicate !== null) {
            return $this->back('/items/' . $id . '/edit', Request::all(), ['code' => 'Kode sudah dipakai pada group tersebut.']);
        }

        $item->updateById($id, [
            'group_id'     => $groupId,
            'location_id'  => Request::int('location_id') ?: null,
            'device_id'    => Request::int('device_id') ?: null,
            'code'         => $code,
            'name'         => Request::string('name'),
            'category'     => Request::string('category') ?: null,
            'unit'         => Request::string('unit') ?: null,
            'expected_qty' => Request::int('expected_qty'),
        ]);

        (new ActivityLog())->record('update', 'item', $id, 'Mengubah data ' . $code, $groupId);

        return $this->redirectWith('/items/' . $id, 'success', 'Data berhasil diperbarui.');
    }

    /**
     * Observer enters the counted quantity.
     */
    public function count(array $params): string
    {
        $this->guardOpenPeriod("/items");
        $this->authorize('count_data');
        $this->verifyCsrf();

        $id = (int) $params['id'];
        $itemModel = new Item();
        $item = $itemModel->find($id);
        if ($item === null) {
            if (Request::isAjax()) {
                return $this->json(['success' => false, 'message' => 'Data tidak ditemukan.'], 404);
            }
            $this->notFound('Data tidak ditemukan.');
        }
        $this->ensureGroupAccess($item['group_id']);

        if ((int) $item['is_locked'] === 1 && ! Auth::can('unlock_data')) {
            if (Request::isAjax()) {
                return $this->json(['success' => false, 'message' => 'Data terkunci (sudah diposting). Ajukan unlock untuk mengubah.'], 403);
            }
            return $this->redirectWith('/items/' . $id, 'error', 'Data terkunci (sudah diposting). Ajukan unlock untuk mengubah.');
        }

        $qty = Request::int('counted_qty');
        $note = Request::string('condition_note') ?: null;
        $itemModel->markCounted($id, $qty, (int) Auth::id(), $note);

        (new ActivityLog())->record('count', 'item', $id, 'Menghitung ' . $item['code'] . ' = ' . $qty, (int) $item['group_id']);

        if (Request::isAjax()) {
            return $this->json([
                'success' => true,
                'message' => 'Hasil hitung ' . $item['code'] . ' berhasil disimpan (' . $qty . ').',
                'item' => [
                    'id' => $id,
                    'code' => $item['code'],
                    'name' => $item['name'],
                    'counted_qty' => $qty,
                    'count_status' => Item::COUNT_COUNTED,
                    'condition_note' => $note,
                ],
                'redirect' => '/items?count_status[]=counted&highlight=' . $id,
            ]);
        }

        $referer = (string) ($_SERVER['HTTP_REFERER'] ?? '');
        $target = ($referer !== '' && str_contains($referer, '/items')) ? ('/items?count_status[]=counted&highlight=' . $id) : ('/items/' . $id);
        return $this->redirectWith($target, 'success', 'Hasil hitung tersimpan. Masuk ke tab Sudah Dihitung.');
    }

    /**
     * Post counted data to the audit queue and lock it.
     */
    public function post(array $params): string
    {
        $this->guardOpenPeriod("/items");
        $this->authorize('post_data');
        $this->verifyCsrf();

        $id = (int) $params['id'];
        $itemModel = new Item();
        $item = $itemModel->find($id);
        if ($item === null) {
            $this->notFound('Data tidak ditemukan.');
        }
        $this->ensureGroupAccess($item['group_id']);

        if ($item['count_status'] === Item::COUNT_PENDING) {
            return $this->redirectWith('/items/' . $id, 'error', 'Data belum dihitung, tidak bisa diposting.');
        }

        $itemModel->markPosted($id, (int) Auth::id());

        (new Notification())->toRole(
            'audit',
            'Data siap diaudit',
            $item['code'] . ' (' . $item['name'] . ') telah diposting dan menunggu audit.',
            'info',
            '/audit',
            (int) $item['group_id']
        );
        (new ActivityLog())->record('post', 'item', $id, 'Posting data ' . $item['code'] . ' ke audit', (int) $item['group_id']);

        return $this->redirectWith('/items/' . $id, 'success', 'Data berhasil diposting ke audit. Data kini terkunci.');
    }

    /**
     * Admin STO opens a locked record for correction.
     */
    public function unlock(array $params): string
    {
        $this->guardOpenPeriod("/items");
        $this->authorize('unlock_data');
        $this->verifyCsrf();

        $id = (int) $params['id'];
        $itemModel = new Item();
        $item = $itemModel->find($id);
        if ($item === null) {
            $this->notFound('Data tidak ditemukan.');
        }
        $this->ensureGroupAccess($item['group_id']);

        $itemModel->unlock($id);

        (new Notification())->toRole(
            'observer',
            'Data dibuka (unlock)',
            $item['code'] . ' dibuka oleh ' . (auth()['name'] ?? 'Admin') . ', silakan perbarui bila perlu.',
            'warning',
            '/items/' . $id,
            (int) $item['group_id']
        );
        (new ActivityLog())->record('unlock', 'item', $id, 'Unlock data ' . $item['code'], (int) $item['group_id']);

        return $this->redirectWith('/items/' . $id, 'success', 'Data berhasil dibuka untuk pembaruan.');
    }

    /**
     * Post every counted item of a group at once.
     */
    public function bulkPost(): string
    {
        $this->guardOpenPeriod("/items");
        $this->authorize('post_data');
        $this->verifyCsrf();

        $groupId = Request::int('group_id');
        if ($groupId === 0) {
            return $this->redirectWith('/items', 'error', 'Pilih group terlebih dahulu.');
        }
        $this->ensureGroupAccess($groupId);

        $itemModel = new Item();
        $rows = $itemModel->query(
            "SELECT id, code FROM items
             WHERE group_id = ? AND count_status = 'counted'",
            [$groupId]
        );

        foreach ($rows as $row) {
            $itemModel->markPosted((int) $row['id'], (int) Auth::id());
        }

        if ($rows !== []) {
            $group = (new Group())->find($groupId);
            (new Notification())->toRole(
                'audit',
                'Batch data siap diaudit',
                count($rows) . ' item dari ' . ($group['name'] ?? 'group') . ' diposting dan menunggu audit.',
                'info',
                '/audit',
                $groupId
            );
            (new ActivityLog())->record('post', 'item', null, 'Posting massal ' . count($rows) . ' item', $groupId);
        }

        return $this->redirectWith('/items?group_id=' . $groupId, 'success', count($rows) . ' data berhasil diposting ke audit.');
    }

    public function destroy(array $params): string
    {
        if (function_exists('can_action') && ! can_action('items', 'delete')) {
            $this->abort(403, 'User Anda tidak memiliki izin untuk menghapus data.');
        }
        $this->guardOpenPeriod("/items");
        $this->authorize('manage_data');
        $this->verifyCsrf();

        $id = (int) $params['id'];
        $itemModel = new Item();
        $row = $itemModel->find($id);
        if ($row === null) {
            $this->notFound('Data tidak ditemukan.');
        }
        $this->ensureGroupAccess($row['group_id']);

        if ((int) $row['is_locked'] === 1) {
            return $this->redirectWith('/items/' . $id, 'error', 'Data terkunci. Unlock terlebih dahulu sebelum menghapus.');
        }

        $itemModel->deleteById($id);
        (new ActivityLog())->record('delete', 'item', $id, 'Menghapus data ' . $row['code'], (int) $row['group_id']);

        return $this->redirectWith('/items', 'success', 'Data berhasil dihapus.');
    }
}
/* I18N5-CTRL */
