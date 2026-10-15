<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Request;
use App\Models\ActivityLog;
use App\Models\Discrepancy;
use App\Models\Group;
use App\Models\Item;
use App\Models\Notification;

class DiscrepancyController extends Controller
{
    use BookPeriodActions;
    /** Nilai distinct untuk popup filter kolom (JSON). */
    public function distinct(array $params = []): never
    {
        $col = \App\Models\Discrepancy::normaliseColFilters($_GET['col'] ?? null, \App\Models\Discrepancy::COL_MAP);
        if (! \can_see_stock_qty()) { unset($col['expected'], $col['difference']); }
        $key = (string) ($_GET['key'] ?? '');
        if (in_array($key, ['expected', 'difference'], true) && ! \can_see_stock_qty()) { $key = ''; }
        [$scope, $sb] = Auth::groupScope('d.group_id');
        $vals = (new \App\Models\Discrepancy())->distinctColValues($key, $col, \App\Models\Discrepancy::COL_MAP, 'FROM discrepancies d JOIN items i ON i.id = d.item_id JOIN `groups` g ON g.id = d.group_id LEFT JOIN users cu ON cu.id = d.created_by', $scope, $sb);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode(['values' => array_map('strval', $vals)], JSON_UNESCAPED_UNICODE);
        exit;
    }

    public function index(): string
    {
        $filters = [
            'search'   => Request::has('search') && trim(Request::string('search')) !== '' ? trim(Request::string('search')) : null,
            'group_id' => Request::has('group_id') && Request::string('group_id') !== '' ? Request::int('group_id') : null,
            'status'   => in_array(Request::string('status'), Discrepancy::STATUSES, true) ? Request::string('status') : null,
        ];
        $filters['col'] = Discrepancy::normaliseColFilters($_GET['col'] ?? null, Discrepancy::COL_MAP);
        $advNumeric = ['expected', 'counted', 'audited', 'difference'];
        $advCols = ['item' => 'Kode Item', 'group' => 'Group', 'status' => 'Status', 'expected' => 'Sistem', 'counted' => 'Hitung', 'audited' => 'Audit', 'difference' => 'Selisih', 'reported' => 'Dilaporkan'];
        if (! \can_see_stock_qty()) {
            unset($filters['col']['expected'], $filters['col']['difference']);
            $advNumeric = ['counted', 'audited'];
            unset($advCols['expected'], $advCols['difference']);
        }
        $filters['adv'] = \App\Support\AdvancedSearch::normalise($_GET['adv'] ?? null, Discrepancy::COL_MAP, $advNumeric);
        $page = max(1, Request::int('page', 1));

        $currentPeriod = \App\Models\BookPeriod::current();
        $isReadOnly = $currentPeriod !== null && (($currentPeriod['status'] ?? 'open') === 'closed' || ! empty($currentPeriod['is_archive_view']));

        if ($isReadOnly && $currentPeriod !== null) {
            $periodId = (int) $currentPeriod['id'];
            $db = \App\Core\Database::connection();
            $stmt = $db->prepare(
                "SELECT s.id, s.item_id, s.item_code, s.item_name,
                        g.name AS group_name, g.code AS group_code,
                        s.expected_qty, s.counted_qty, s.audited_qty, s.difference,
                        'resolved' AS status, 'Periode ditutup (arsip)' AS note,
                        '-' AS created_by_name,
                        '-' AS resolved_by_name,
                        s.snapshot_at AS created_at
                 FROM period_snapshots s
                 JOIN `groups` g ON g.id = s.group_id
                 WHERE s.period_id = ? AND s.difference IS NOT NULL AND s.difference <> 0
                 ORDER BY g.name ASC, s.item_code ASC"
            );
            $stmt->execute([$periodId]);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            $discrepancies = [
                'items'     => $rows,
                'total'     => count($rows),
                'page'      => 1,
                'per_page'  => count($rows) > 0 ? count($rows) : 15,
                'last_page' => 1,
                'from'      => 1,
                'to'        => count($rows),
            ];
        } else {
            $discrepancies = (new Discrepancy())->listDetailed($filters, $page, list_per_page((int) config('app.per_page', 15)));
        }

        return $this->view('discrepancies/index', [
            'title'        => __('Selisih'),
            'discrepancies'=> $discrepancies,
            'filters'      => $filters,
            'advCols'      => $advCols,
            'advNumeric'   => $advNumeric,
            'advMax'       => \App\Support\AdvancedSearch::MAX_CONDITIONS,
            'groups'       => (new Group())->forActiveUser(),
            'statuses'     => Discrepancy::STATUSES,
            'isReadOnly'   => $isReadOnly,
            'currentPeriod'=> $currentPeriod,
        ]);
    }

    public function show(array $params): string
    {
        $row = (new Discrepancy())->first(
            'SELECT d.*, i.code AS item_code, i.name AS item_name, i.counted_qty,
                    g.code AS group_code, g.name AS group_name,
                    cu.name AS created_by_name, ru.name AS resolved_by_name
             FROM discrepancies d
             JOIN items i ON i.id = d.item_id
             JOIN `groups` g ON g.id = d.group_id
             LEFT JOIN users cu ON cu.id = d.created_by
             LEFT JOIN users ru ON ru.id = d.resolved_by
             WHERE d.id = ? LIMIT 1',
            [(int) $params['id']]
        );

        if ($row === null) {
            $this->notFound('Selisih tidak ditemukan.');
        }
        $this->ensureGroupAccess($row['group_id']);

        return $this->view('discrepancies/show', [
            'title'       => __('Detail Selisih'),
            'discrepancy' => $row,
            'item'        => (new Item())->findDetailed((int) $row['item_id']),
        ]);
    }

    /**
     * Admin STO flags a difference manually (upload selisih per group).
     */
    public function store(): string
    {
        $this->authorize('manage_discrepancy');
        $this->guardOpenPeriod("/discrepancies");
        $this->verifyCsrf();

        $this->validate([
            'item_id' => 'required|integer|exists:items,id',
            'note'    => 'required|max:255',
        ], ['item_id' => 'Item', 'note' => 'Catatan']);

        $itemModel = new Item();
        $item = $itemModel->find(Request::int('item_id'));
        if ($item === null) {
            $this->notFound('Data tidak ditemukan.');
        }
        $this->ensureGroupAccess($item['group_id']);

        $expected = (int) $item['expected_qty'];
        $counted = (int) ($item['counted_qty'] ?? 0);

        (new Discrepancy())->insert([
            'item_id'      => (int) $item['id'],
            'group_id'     => (int) $item['group_id'],
            'expected_qty' => $expected,
            'counted_qty'  => $counted,
            'difference'   => $counted - $expected,
            'status'       => 'open',
            'note'         => Request::string('note'),
            'created_by'   => Auth::id(),
        ]);

        $itemModel->updateById((int) $item['id'], [
            'audit_status' => Item::AUDIT_DISCREPANCY,
            'is_locked'    => 0,
        ]);

        (new Notification())->toRole(
            'observer',
            'Selisih baru untuk dicari',
            $item['code'] . ': ' . Request::string('note'),
            'warning',
            '/discrepancies',
            (int) $item['group_id']
        );
        (new ActivityLog())->record('create', 'discrepancy', (int) $item['id'], 'Menandai selisih ' . $item['code'], (int) $item['group_id']);

        return $this->redirectWith('/discrepancies', 'success', 'Selisih berhasil ditandai dan observer dinotifikasi.');
    }

    /**
     * Observer updates the counted quantity while searching.
     */
    public function search(array $params): string
    {
        $this->authorize('search_discrepancy');
        $this->guardOpenPeriod("/discrepancies");
        $this->verifyCsrf();

        $id = (int) $params['id'];
        $discrepancy = new Discrepancy();
        $row = $discrepancy->find($id);
        if ($row === null) {
            $this->notFound('Selisih tidak ditemukan.');
        }
        $this->ensureGroupAccess($row['group_id']);

        if ($row['status'] === 'resolved') {
            return $this->redirectWith('/discrepancies/' . $id, 'error', 'Selisih sudah selesai dan tidak bisa diubah.');
        }

        $countedQty = Request::int('counted_qty');
        $note = Request::string('note') ?: null;

        $itemModel = new Item();
        $itemModel->updateById((int) $row['item_id'], [
            'counted_qty'    => $countedQty,
            'condition_note' => $note,
            'counted_by'     => Auth::id(),
            'counted_at'     => date('Y-m-d H:i:s'),
        ]);

        $discrepancy->updateById($id, [
            'counted_qty' => $countedQty,
            'difference'  => $countedQty - (int) $row['expected_qty'],
            'status'      => 'searching',
            'note'        => $note ?? $row['note'],
        ]);

        (new Notification())->toRole(
            'adminsto',
            'Selisih sedang dicari',
            'Observer memperbarui hasil pencarian selisih.',
            'info',
            '/discrepancies/' . $id,
            (int) $row['group_id']
        );
        (new ActivityLog())->record('search', 'discrepancy', $id, 'Mencari selisih, hasil hitung = ' . $countedQty, (int) $row['group_id']);

        return $this->redirectWith('/discrepancies/' . $id, 'success', 'Hasil pencarian tersimpan.');
    }

    /**
     * Admin STO resolves a discrepancy.
     */
    public function resolve(array $params): string
    {
        $this->authorize('manage_discrepancy');
        $this->guardOpenPeriod("/discrepancies");
        $this->verifyCsrf();

        $id = (int) $params['id'];
        $discrepancy = new Discrepancy();
        $row = $discrepancy->find($id);
        if ($row === null) {
            $this->notFound('Selisih tidak ditemukan.');
        }
        $this->ensureGroupAccess($row['group_id']);

        $discrepancyData = [
            'status'          => 'resolved',
            'resolution_note' => Request::string('resolution_note') ?: 'Diselesaikan oleh Admin STO.',
            'resolved_by'     => Auth::id(),
            'resolved_at'     => date('Y-m-d H:i:s'),
        ];

        $itemData = [
            'audit_status' => Item::AUDIT_RESOLVED,
            'is_locked'    => 1,
        ];

        // Shortcut revisi qty setelah dihitung ulang (permintaan user)
        $countedInput = Request::post('counted_qty');
        if ($countedInput !== null && $countedInput !== '') {
            $newCounted = (int) $countedInput;
            $itemData['counted_qty'] = $newCounted;
            $itemData['needs_recount'] = 0;
            $itemData['count_status'] = Item::COUNT_COUNTED;
            $itemData['counted_by'] = Auth::id();
            $itemData['counted_at'] = date('Y-m-d H:i:s');

            $discrepancyData['counted_qty'] = $newCounted;
            $discrepancyData['difference'] = $newCounted - (int) $row['expected_qty'];

            try {
                $db = \App\Core\Database::connection();
                $reconStmt = $db->prepare("SELECT id, stock_qty, unit_price FROM reconciliation WHERE item_id = ? LIMIT 1");
                $reconStmt->execute([(int) $row['item_id']]);
                $recon = $reconStmt->fetch(\PDO::FETCH_ASSOC);
                if ($recon) {
                    $stockQty = (int) $recon['stock_qty'];
                    $diff = $newCounted - $stockQty;
                    $unitPrice = (float) $recon['unit_price'];
                    $diffAmount = $diff * $unitPrice;
                    $countAmount = $newCounted * $unitPrice;
                    $direction = $diff > 0 ? 'plus' : ($diff < 0 ? 'minus' : 'match');

                    $upRecon = $db->prepare("UPDATE reconciliation SET counted_qty = ?, difference = ?, count_amount = ?, diff_amount = ?, direction = ?, recheck_requested = 0 WHERE id = ?");
                    $upRecon->execute([$newCounted, $diff, $countAmount, $diffAmount, $direction, (int) $recon['id']]);
                }
            } catch (\Throwable $e) {
                // Ignore
            }
        }

        $discrepancy->updateById($id, $discrepancyData);
        (new Item())->updateById((int) $row['item_id'], $itemData);

        (new Notification())->toRole(
            'observer',
            'Selisih diselesaikan',
            'Selisih telah diselesaikan oleh Admin STO.',
            'success',
            '/discrepancies/' . $id,
            (int) $row['group_id']
        );
        (new ActivityLog())->record('resolve', 'discrepancy', $id, 'Menyelesaikan selisih', (int) $row['group_id']);

        return $this->redirectWith('/discrepancies/' . $id, 'success', 'Selisih berhasil diselesaikan.');
    }
}
/* I18N5-CTRL */
