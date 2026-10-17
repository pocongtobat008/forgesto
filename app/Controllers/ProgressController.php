<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Models\BookPeriod;
use App\Models\Group;
use App\Models\Item;

/**
 * Menu "Progress" — progres penghitungan & audit per group.
 *
 * Halaman index menampilkan ringkasan periode + kartu per group (ringkas,
 * supaya tetap enak dibaca walau group berjumlah puluhan). Detail mendalam
 * tiap group diambil lewat endpoint JSON /progress/group/{id} dan ditampilkan
 * pada drawer yang meluncur dari kanan.
 */
class ProgressController extends Controller
{
    public function index(): string
    {
        $this->guard();

        $rows   = $this->groupRows();
        $period = BookPeriod::current();

        $totals = [
            'groups'   => count($rows),
            'items'    => 0,
            'counted'  => 0,
            'audited'  => 0,
            'done'     => 0,
            'running'  => 0,
            'empty'    => 0,
        ];

        foreach ($rows as $r) {
            $totals['items']   += (int) $r['total'];
            $totals['counted'] += (int) $r['counted'];
            $totals['audited'] += (int) $r['audited'];
            if ((int) $r['total'] === 0) {
                $totals['empty']++;
            } elseif ((int) $r['count_pct'] >= 100) {
                $totals['done']++;
            } elseif ((int) $r['counted'] > 0) {
                $totals['running']++;
            }
        }

        $totals['count_pct'] = percent($totals['counted'], $totals['items']);
        $totals['audit_pct'] = percent($totals['audited'], $totals['items']);

        return $this->view('progress/index', [
            'title'  => __('Progress'),
            'rows'   => $rows,
            'totals' => $totals,
            'period' => $period,
        ]);
    }

    /**
     * Detail satu group (JSON) untuk drawer.
     */
    public function group(array $params): string
    {
        $this->guard();

        $id = (int) ($params['id'] ?? 0);
        if ($id <= 0) {
            return $this->json(['ok' => false, 'message' => __('Group tidak valid.')], 422);
        }

        $group = (new Group())->withSto($id);
        if ($group === null) {
            return $this->json(['ok' => false, 'message' => __('Group tidak ditemukan.')], 404);
        }

        $item = new Item();

        // Batasi sesuai scope group milik user.
        [$scope, $scopeBind] = Auth::groupScope('g.id');
        if ($scope !== '') {
            $allowed = $item->first(
                "SELECT g.id FROM `groups` g WHERE g.id = ? AND {$scope} LIMIT 1",
                array_merge([$id], $scopeBind)
            );
            if ($allowed === null) {
                return $this->json(['ok' => false, 'message' => __('Anda tidak memiliki akses ke group ini.')], 403);
            }
        }

        $agg = $item->first(
            "SELECT
                COUNT(*)                                                          AS total,
                COALESCE(SUM(i.count_status IN ('counted','posted','submitted')), 0) AS counted,
                COALESCE(SUM(i.count_status = 'pending'), 0)                      AS pending,
                COALESCE(SUM(i.count_status IN ('posted','submitted')), 0)        AS posted,
                COALESCE(SUM(i.audit_status = 'audited'), 0)                      AS audited,
                COALESCE(SUM(i.audit_status = 'pending'), 0)                      AS not_audited,
                COALESCE(SUM(i.audit_status = 'discrepancy'), 0)                  AS discrepancy,
                COALESCE(SUM(i.audit_status = 'resolved'), 0)                     AS resolved,
                COALESCE(SUM(i.audit_status = 'recount' OR COALESCE(i.needs_recount, 0) = 1), 0) AS recount,
                COALESCE(SUM(CASE WHEN i.counted_qty IS NOT NULL THEN (i.counted_qty - i.expected_qty) ELSE 0 END), 0) AS diff_qty,
                COALESCE(SUM(CASE WHEN i.counted_qty IS NOT NULL THEN (i.counted_qty - i.expected_qty) * COALESCE(i.unit_price, 0) ELSE 0 END), 0) AS diff_amount,
                MIN(i.counted_at)                                                 AS first_count,
                MAX(i.counted_at)                                                 AS last_count
             FROM items i
             WHERE i.group_id = ?",
            [$id]
        ) ?? [];

        $total    = (int) ($agg['total'] ?? 0);
        $counted  = (int) ($agg['counted'] ?? 0);
        $audited  = (int) ($agg['audited'] ?? 0);

        $locations = $item->query(
            "SELECT l.id, l.code, l.name,
                    COUNT(i.id)                                            AS total,
                    COALESCE(SUM(i.count_status IN ('counted','posted','submitted')), 0) AS counted
             FROM locations l
             LEFT JOIN items i ON i.location_id = l.id
             WHERE l.group_id = ?
             GROUP BY l.id, l.code, l.name
             ORDER BY l.name",
            [$id]
        );

        $locations = array_map(static function (array $l): array {
            $t = (int) $l['total'];
            $c = (int) $l['counted'];
            $l['total']   = $t;
            $l['counted'] = $c;
            $l['pct']     = percent($c, $t);
            return $l;
        }, $locations);

        $showMoney = can_see_stock_qty();

        $top = $item->query(
            "SELECT i.code, i.name, i.expected_qty, i.counted_qty, COALESCE(i.unit_price, 0) AS unit_price,
                    (i.counted_qty - i.expected_qty) AS diff_qty
             FROM items i
             WHERE i.group_id = ?
               AND i.counted_qty IS NOT NULL
               AND i.counted_qty <> i.expected_qty
             ORDER BY ABS(i.counted_qty - i.expected_qty) DESC, i.name ASC
             LIMIT 6",
            [$id]
        );

        $top = array_map(static function (array $t) use ($showMoney): array {
            $diff = (int) $t['diff_qty'];
            $out  = [
                'code'     => (string) $t['code'],
                'name'     => (string) $t['name'],
                'diff'     => $diff,
                'counted'  => (int) $t['counted_qty'],
                'expected' => $showMoney ? (int) $t['expected_qty'] : null,
                'amount'   => $showMoney ? $diff * (float) $t['unit_price'] : null,
            ];
            return $out;
        }, $top);

        $groupName = (string) ($group['name'] ?? '');

        // Petugas yang ditugaskan pada group ini (observer & auditor).
        $officers = $item->first(
            "SELECT
                (SELECT GROUP_CONCAT(u.name ORDER BY u.name SEPARATOR ' | ')
                   FROM user_groups ug JOIN users u ON u.id = ug.user_id
                  WHERE ug.group_id = g.id AND u.role = 'observer' AND u.is_active = 1) AS observers,
                (SELECT GROUP_CONCAT(u.name ORDER BY u.name SEPARATOR ' | ')
                   FROM user_groups ug JOIN users u ON u.id = ug.user_id
                  WHERE ug.group_id = g.id AND u.role = 'audit' AND u.is_active = 1) AS auditors
             FROM `groups` g
             WHERE g.id = ?",
            [$id]
        ) ?? [];

        return $this->json([
            'ok'    => true,
            'group' => [
                'id'          => $id,
                'code'        => (string) ($group['code'] ?? ''),
                'name'        => $groupName,
                'description' => (string) ($group['description'] ?? ''),
                'warehouse'   => trim((string) ($group['warehouse_name'] ?? '')),
                'sto'         => trim((string) ($group['sto_name'] ?? '')),
                'observers'   => trim((string) ($officers['observers'] ?? '')),
                'auditors'    => trim((string) ($officers['auditors'] ?? '')),
            ],
            'stats' => [
                'total'      => $total,
                'counted'    => $counted,
                'pending'    => (int) ($agg['pending'] ?? 0),
                'posted'     => (int) ($agg['posted'] ?? 0),
                'audited'    => $audited,
                'notAudited' => (int) ($agg['not_audited'] ?? 0),
                'discrepancy'=> (int) ($agg['discrepancy'] ?? 0),
                'resolved'   => (int) ($agg['resolved'] ?? 0),
                'recount'    => (int) ($agg['recount'] ?? 0),
                'countPct'   => percent($counted, $total),
                'auditPct'   => percent($audited, $total),
            ],
            'diff' => [
                'visible' => $showMoney,
                'qty'     => $showMoney ? (int) ($agg['diff_qty'] ?? 0) : null,
                'amount'  => $showMoney ? (float) ($agg['diff_amount'] ?? 0) : null,
            ],
            'time' => [
                'first' => ! empty($agg['first_count']) ? (string) $agg['first_count'] : null,
                'last'  => ! empty($agg['last_count']) ? (string) $agg['last_count'] : null,
            ],
            'locations' => $locations,
            'top'       => $showMoney ? $top : [],
            'links'     => [
                'items' => url('items?col[group][]=' . rawurlencode($groupName)),
                'audit' => url('audit?col[group][]=' . rawurlencode($groupName)),
            ],
            'labels' => [
                'total'      => __('Total item'),
                'counted'    => __('Sudah dihitung'),
                'pending'    => __('Belum dihitung'),
                'posted'     => __('Terposting'),
                'audited'    => __('Sudah diaudit'),
                'notAudited' => __('Belum diaudit'),
                'discrepancy'=> __('Selisih'),
                'resolved'   => __('Selesai'),
                'recount'    => __('Cek ulang'),
                'hitung'     => __('Progres hitung'),
                'audit'      => __('Progres audit'),
                'loc'        => __('Sebaran lokasi'),
                'top'        => __('Item selisih terbesar'),
                'noLoc'      => __('Belum ada lokasi terdaftar untuk group ini.'),
                'noTop'      => __('Belum ada item dengan selisih qty.'),
                'first'      => __('Hitung pertama'),
                'last'       => __('Hitung terakhir'),
                'empty'      => __('Belum ada item pada group ini.'),
                'diffQty'    => __('Selisih qty'),
                'adminOnly'  => __('Detail selisih hanya untuk admin.'),
                'diffAmt'    => __('Selisih nilai'),
                'hidden'     => __('Nilai dirahasiakan (blind count)'),
                'items'      => __('Buka Data STO'),
                'auditBtn'   => __('Buka Audit'),
                'sys'        => __('Sistem'),
                'count'      => __('Hitung'),
                'obs'        => __('Observer'),
                'aud'        => __('Audit'),
                'none'       => __('belum ditugaskan'),
            ],
        ]);
    }

    /**
     * Ambil baris progres semua group (scope user).
     *
     * @return array<int, array<string, mixed>>
     */
    private function groupRows(): array
    {
        $item = new Item();

        [$scope, $bindings] = Auth::groupScope('g.id');
        $where = $scope !== '' ? ' WHERE ' . $scope : '';

        $rows = $item->query(
            "SELECT g.id, g.code, g.name,
                    (SELECT GROUP_CONCAT(u.name ORDER BY u.name SEPARATOR ' | ')
                       FROM user_groups ug JOIN users u ON u.id = ug.user_id
                      WHERE ug.group_id = g.id AND u.role = 'observer' AND u.is_active = 1) AS observers,
                    (SELECT GROUP_CONCAT(u.name ORDER BY u.name SEPARATOR ' | ')
                       FROM user_groups ug JOIN users u ON u.id = ug.user_id
                      WHERE ug.group_id = g.id AND u.role = 'audit' AND u.is_active = 1) AS auditors,
                    COUNT(i.id)                                                       AS total,
                    COALESCE(SUM(i.count_status IN ('counted','posted','submitted')), 0) AS counted,
                    COALESCE(SUM(i.count_status = 'pending'), 0)                      AS pending,
                    COALESCE(SUM(i.count_status IN ('posted','submitted')), 0)        AS posted,
                    COALESCE(SUM(i.audit_status = 'audited'), 0)                      AS audited,
                    COALESCE(SUM(i.audit_status = 'pending'), 0)                      AS not_audited,
                    COALESCE(SUM(i.audit_status = 'discrepancy'), 0)                  AS discrepancy,
                    COALESCE(SUM(i.audit_status = 'resolved'), 0)                     AS resolved,
                    COALESCE(SUM(i.audit_status = 'recount' OR COALESCE(i.needs_recount, 0) = 1), 0) AS recount,
                    COALESCE(SUM(CASE WHEN i.counted_qty IS NOT NULL THEN (i.counted_qty - i.expected_qty) ELSE 0 END), 0) AS diff_qty,
                    COALESCE(SUM(CASE WHEN i.counted_qty IS NOT NULL THEN (i.counted_qty - i.expected_qty) * COALESCE(i.unit_price, 0) ELSE 0 END), 0) AS diff_amount
             FROM `groups` g
             LEFT JOIN items i ON i.group_id = g.id
             {$where}
             GROUP BY g.id, g.code, g.name
             ORDER BY g.name",
            $bindings
        );

        return array_map(static function (array $r): array {
            $total   = (int) $r['total'];
            $counted = (int) $r['counted'];
            $audited = (int) $r['audited'];

            $r['total']        = $total;
            $r['counted']      = $counted;
            $r['pending']      = (int) $r['pending'];
            $r['posted']       = (int) $r['posted'];
            $r['audited']      = $audited;
            $r['not_audited']  = (int) $r['not_audited'];
            $r['discrepancy']  = (int) $r['discrepancy'];
            $r['resolved']     = (int) $r['resolved'];
            $r['recount']      = (int) $r['recount'];
            $r['diff_qty']     = (int) $r['diff_qty'];
            $r['diff_amount']  = (float) $r['diff_amount'];
            $r['count_pct']    = percent($counted, $total);
            $r['audit_pct']    = percent($audited, $total);
            $r['observers']    = trim((string) ($r['observers'] ?? ''));
            $r['auditors']     = trim((string) ($r['auditors'] ?? ''));

            if ($total === 0) {
                $r['state'] = 'empty';
            } elseif ($r['count_pct'] >= 100) {
                $r['state'] = 'done';
            } elseif ($counted > 0) {
                $r['state'] = 'running';
            } else {
                $r['state'] = 'idle';
            }

            return $r;
        }, $rows);
    }

    private function guard(): void
    {
        if (function_exists('can_view_menu') && ! can_view_menu('progress')) {
            $this->abort(403, __('Anda tidak memiliki akses ke menu Progress.'));
        }
    }
}
