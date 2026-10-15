<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Auth;
use App\Core\Model;

class Reconciliation extends Model
{
    protected string $table = 'reconciliation';

    protected array $fillable = [
        'item_id', 'group_id', 'period', 'stock_qty', 'counted_qty',
        'difference', 'unit_price', 'stock_amount', 'count_amount',
        'diff_amount', 'direction', 'status', 'verified_by', 'verified_at',
        'verify_note', 'submitted_by', 'submitted_at', 'created_by',
        'recheck_requested',
    ];

    public const DIRECTIONS = ['plus', 'minus', 'match'];
    public const STATUSES   = ['draft', 'pending_verify', 'verified', 'rejected'];

    /** Ambang nilai (Rp) untuk filter cepat "kurang / lebih dari 10jt". */
    public const BIG_THRESHOLD = 10000000.0;

    /** Opsi pengurutan daftar rekonsiliasi (default: selisih nilai terbesar). */
    private const SORTS = [
        'diff_desc' => 'ORDER BY ABS(r.diff_amount) DESC, r.id ASC',
        'diff_asc'  => 'ORDER BY ABS(r.diff_amount) ASC, r.id ASC',
        'qty_desc'  => 'ORDER BY ABS(r.difference) DESC, r.id ASC',
        'qty_asc'   => 'ORDER BY ABS(r.difference) ASC, r.id ASC',
        'code'      => 'ORDER BY i.code ASC',
    ];

    public const SORT_LABELS = [
        'diff_desc' => 'Selisih nilai terbesar',
        'diff_asc'  => 'Selisih nilai terkecil',
        'qty_desc'  => 'Selisih qty terbesar',
        'qty_asc'   => 'Selisih qty terkecil',
        'code'      => 'Kode item (A-Z)',
    ];

    /**
     * Peta TAHAPAN WORKFLOW stock take per item (join items).
     *
     * Dipakai halaman Rekonsiliasi untuk menjelaskan posisi tiap item di
     * alur: Belum Dihitung → Sudah Dihitung → Diaudit → Selesai,
     * plus penanda Kunci (locked untuk observer/petugas) agar jelas
     * "kenapa tombol saya tidak bisa ditekan".
     *
     * @return array<int, array{stage:string,stage_label:string,count_status:?string,audit_status:?string,is_locked:int,counted_by_name:?string}>
     */
    public function stageMap(): array
    {
        [$scope, $bindings] = Auth::groupScope('r.group_id');
        $where = " WHERE i.count_status IN ('audited','reconciled') AND i.audit_status IN ('audited','resolved')";
        if ($scope !== '') {
            $where .= ' AND ' . $scope;
        }

        $sql = "SELECT r.item_id,
                       i.count_status, i.audit_status, i.is_locked,
                       cu.name AS counted_by_name,
                       au.name AS audited_by_name
                FROM reconciliation r
                JOIN items i ON i.id = r.item_id
                LEFT JOIN users cu ON cu.id = i.counted_by
                LEFT JOIN users au ON au.id = i.posted_by" . $where;

        $rows = $this->query($sql, $bindings);

        $out = [];
        foreach ($rows as $row) {
            $auditStatus = (string) ($row['audit_status'] ?? 'pending');
            $countStatus = (string) ($row['count_status'] ?? 'pending');
            $locked      = (int) ($row['is_locked'] ?? 0);

            if ($auditStatus === 'audited' || $auditStatus === 'resolved') {
                $stage = 'done';
                $label = $auditStatus === 'resolved' ? 'Selesai (selisih tuntas)' : 'Selesai diaudit';
            } elseif ($auditStatus === 'discrepancy') {
                $stage = 'discrepancy';
                $label = 'Ada selisih: perlu tindak lanjut';
            } elseif ($countStatus === 'counted' || $countStatus === 'posted') {
                $stage = 'counted';
                $label = 'Sudah dihitung: menunggu audit';
            } else {
                $stage = 'pending';
                $label = 'Belum dihitung';
            }

            $out[(int) $row['item_id']] = [
                'stage'           => $stage,
                'stage_label'     => $label,
                'count_status'    => $countStatus,
                'audit_status'    => $auditStatus,
                'is_locked'       => $locked,
                'counted_by_name' => $row['counted_by_name'] ?? null,
                'audited_by_name' => $row['audited_by_name'] ?? null,
            ];
        }

        return $out;
    }

    /**
     * Ringkasan jumlah item per TAHAPAN + jumlah terkunci, untuk
     * "jelaskan progress" di dashboard rekonsiliasi.
     *
     * @return array{pending:int,counted:int,discrepancy:int,done:int,total:int,locked:int}
     */
    public function stageSummary(): array
    {
        $s = ['pending' => 0, 'counted' => 0, 'discrepancy' => 0, 'done' => 0, 'total' => 0, 'locked' => 0];

        foreach ($this->stageMap() as $info) {
            $s[$info['stage']] = ($s[$info['stage']] ?? 0) + 1;
            $s['total']++;
            if ($info['is_locked'] === 1) {
                $s['locked']++;
            }
        }

        return $s;
    }

    /**
     * Hitung jumlah item dengan selisih nilai di atas ambang Rp10jt,
     * dipisah defisit (minus) & surplus (plus) — untuk chip filter cepat.
     *
     * @return array{minus_n:int,plus_n:int}
     */
    public function thresholdCounts(float $threshold = self::BIG_THRESHOLD): array
    {
        $sql = "SELECT SUM(CASE WHEN r.diff_amount <= -? THEN 1 ELSE 0 END) AS minus_n,
                       SUM(CASE WHEN r.diff_amount >=  ? THEN 1 ELSE 0 END) AS plus_n
                FROM reconciliation r
                JOIN items i ON i.id = r.item_id
                WHERE i.count_status IN ('audited','reconciled') AND i.audit_status IN ('audited','resolved')";
        $bindings = [$threshold, $threshold];

        [$scope, $scopeBindings] = Auth::groupScope('r.group_id');
        if ($scope !== '') {
            $sql       .= ' AND ' . $scope;
            $bindings   = array_merge($bindings, $scopeBindings);
        }

        $row = $this->first($sql, $bindings) ?? [];

        return [
            'minus_n' => (int) ($row['minus_n'] ?? 0),
            'plus_n'  => (int) ($row['plus_n'] ?? 0),
        ];
    }

    /**
     * @param array{group_id?:int|null,direction?:string,status?:string,search?:string,highlight?:string,min_diff?:string,sort?:string} $filters
     */
    /** Kolom filter tabel Rekonsiliasi (whitelist). Ringkasan TIDAK ikut filter ini. */
    public const COL_MAP = [
        'code'        => 'i.code',
        'kode'        => 'i.code',
        'name'        => 'i.name',
        'nama'        => 'i.name',
        'nama'     => 'i.name',
        'group'    => 'g.name',
        'location' => 'l.name',
        'status'   => 'r.status',
        'kategori' => 'i.category',
        'satuan'   => 'i.unit',
        'diajukan' => 'su.name',
        'verif'    => 'vu.name',
        'harga'    => 'r.unit_price',
        'stok'     => 'r.stock_qty',
        'hitung'   => 'r.counted_qty',
        'observer' => "(SELECT GROUP_CONCAT(uo.name SEPARATOR ' | ') FROM user_groups ugo JOIN users uo ON uo.id = ugo.user_id WHERE ugo.group_id = r.group_id AND uo.role = 'observer' AND uo.is_active = 1)",
        'auditor'  => "(SELECT GROUP_CONCAT(ua.name SEPARATOR ' | ') FROM user_groups uga JOIN users ua ON ua.id = uga.user_id WHERE uga.group_id = r.group_id AND ua.role = 'audit' AND ua.is_active = 1)",
        'tgl_ajuan' => 'DATE(r.submitted_at)',
        'tgl_verif' => 'DATE(r.verified_at)',
        'selqty'      => 'r.difference',
        'difference'  => 'r.difference',
        'selnil'      => 'r.diff_amount',
        'diff_amount' => 'r.diff_amount',
        'selnil'    => 'r.diff_amount',
        'catatan'   => 'r.verify_note',
    ];
    /** RNG_V1: kolom rentang. num = angka, date = tanggal (YYYY-MM-DD). */
    public const RANGE_COLS = ['selqty' => 'num', 'selnil' => 'num', 'tgl_ajuan' => 'date', 'tgl_verif' => 'date'];
    /** RNG_V1: kolom teks bebas (LIKE). */
    public const TEXT_COLS = ['catatan'];

    /** Kunci yang hanya boleh untuk poweradmin/adminsto (angka stok sistem). */
    public const ADMIN_ONLY_COLS = ['stok', 'hitung'];

    /**
     * RNG_V1: validasi rentang & teks. rng[key][min|max], txt[key].
     * Nilai tidak valid dibuang; tidak pernah masuk SQL mentah.
     * @return array{rng: array<string, array<string,string>>, txt: array<string,string>}
     */
    public static function normaliseRangeFilters(mixed $rng, mixed $txt): array
    {
        $outR = []; $outT = [];
        if (is_array($rng)) {
            foreach ($rng as $key => $pair) {
                if (! isset(self::RANGE_COLS[$key]) || ! is_array($pair)) continue;
                $type = self::RANGE_COLS[$key];
                $clean = [];
                foreach (['min', 'max'] as $side) {
                    if (! isset($pair[$side])) continue;
                    $v = trim((string) $pair[$side]);
                    if ($type === 'num' && preg_match('/^-?\d{1,12}(\.\d{1,2})?$/', $v)) {
                        $clean[$side] = $v;
                    } elseif ($type === 'date' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $v)) {
                        [$y, $m, $d] = array_map('intval', explode('-', $v));
                        if (checkdate($m, $d, $y)) $clean[$side] = $v;
                    }
                }
                if ($clean !== []) $outR[$key] = $clean;
            }
        }
        if (is_array($txt)) {
            foreach ($txt as $key => $v) {
                if (! in_array($key, self::TEXT_COLS, true)) continue;
                $v = trim((string) $v);
                if ($v !== '' && mb_strlen($v) <= 120) $outT[$key] = $v;
            }
        }
        return ['rng' => $outR, 'txt' => $outT];
    }

    /** @return array{0:list<string>,1:list<mixed>} */
    public static function rangeClauseSql(array $rng, array $txt): array
    {
        $clauses = []; $bind = [];
        foreach ($rng as $key => $pair) {
            $expr = self::COL_MAP[$key] ?? null;
            if ($expr === null) continue;
            if (isset($pair['min'])) { $clauses[] = $expr . ' >= ?'; $bind[] = $pair['min']; }
            if (isset($pair['max'])) { $clauses[] = $expr . ' <= ?'; $bind[] = $pair['max']; }
        }
        foreach ($txt as $key => $v) {
            $expr = self::COL_MAP[$key] ?? null;
            if ($expr === null) continue;
            $clauses[] = $expr . ' LIKE ?';
            $bind[] = '%' . addcslashes($v, '%_\\') . '%';
        }
        return [$clauses, $bind];
    }

    /** @return array<string, list<string>> */
    public static function normaliseColFilters(mixed $raw): array
    {
        $out = [];
        if (! is_array($raw)) return $out;
        foreach ($raw as $key => $vals) {
            if (! isset(self::COL_MAP[$key]) || ! is_array($vals)) continue;
            $clean = [];
            foreach ($vals as $v) {
                $v = trim((string) $v);
                if ($v !== '' && mb_strlen($v) <= 120) $clean[] = $v;
            }
            $clean = array_values(array_unique($clean));
            if ($clean !== []) $out[$key] = $clean;
        }
        return $out;
    }

    /** @return array{0:list<string>,1:list<mixed>} */
    public static function colClauseSql(array $col, array $map): array
    {
        $clauses = []; $bindings = [];
        foreach ($col as $key => $vals) {
            $expr = $map[$key] ?? null;
            if ($expr === null || ! is_array($vals) || $vals === []) continue;
            $ph = implode(',', array_fill(0, count($vals), '?'));
            $clauses[] = "{$expr} IN ({$ph})";
            foreach ($vals as $v) $bindings[] = $v;
        }
        return [$clauses, $bindings];
    }

    /** Nilai distinct untuk popup, memakai scope + filter kolom lain. @return list<string> */
    public function distinctColValues(string $key, array $col): array
    {
        $expr = self::COL_MAP[$key] ?? null;
        if ($expr === null) return [];
        if (in_array($key, self::ADMIN_ONLY_COLS, true) && ! in_array(Auth::role(), ['poweradmin', 'adminsto'], true)) return [];
        unset($col[$key]);
        [$scope, $sb] = Auth::groupScope('r.group_id');
        $clauses = [
            "i.count_status IN ('audited','reconciled')",
            "i.audit_status IN ('audited','resolved')",
        ];
        $bind = [];
        if ($scope !== '') {
            $clauses[] = $scope;
            $bind = array_merge($bind, $sb);
        }
        [$cc, $cb] = self::colClauseSql($col, self::COL_MAP);
        $clauses = array_merge($clauses, $cc);
        $bind = array_merge($bind, $cb);
        $where = ' WHERE ' . implode(' AND ', $clauses);
        $rows = $this->query("SELECT DISTINCT {$expr} AS v FROM reconciliation r JOIN items i ON i.id = r.item_id JOIN `groups` g ON g.id = r.group_id LEFT JOIN locations l ON l.id = i.location_id LEFT JOIN users su ON su.id = r.submitted_by LEFT JOIN users vu ON vu.id = r.verified_by{$where} ORDER BY v LIMIT 500", $bind);
        $out = [];
        foreach ($rows as $r) {
            if ($r['v'] === null || $r['v'] === '') continue;
            $out[] = (string) $r['v'];
        }
        return $out;
    }

    public function listDetailed(array $filters, int $page = 1, int $perPage = 15): array
    {
        $clauses  = [
            "((i.count_status IN ('audited','reconciled') AND i.audit_status IN ('audited','resolved')) OR r.recheck_requested = 1)",
        ];
        $bindings = [];

        [$scope, $scopeBindings] = Auth::groupScope('r.group_id');
        if ($scope !== '') {
            $clauses[] = $scope;
            $bindings  = array_merge($bindings, $scopeBindings);
        }

        if (! empty($filters['group_id'])) {
            $clauses[]  = 'r.group_id = ?';
            $bindings[] = (int) $filters['group_id'];
        }

        if (! empty($filters['recheck'])) {
            $clauses[] = 'r.recheck_requested = 1';
        }

        if (! empty($filters['direction']) && in_array($filters['direction'], self::DIRECTIONS, true)) {
            $clauses[]  = 'r.direction = ?';
            $bindings[] = $filters['direction'];
        }

        if (! empty($filters['status']) && in_array($filters['status'], self::STATUSES, true)) {
            $clauses[]  = 'r.status = ?';
            $bindings[] = $filters['status'];
        }

        // Filter TAHAPAN stock take (dipakai kartu "Alur & Kemajuan").
        // Nilainya diturunkan dari items.count_status & items.audit_status.
        $stage = $filters['stage'] ?? null;
        if (in_array($stage, ['pending', 'counted', 'discrepancy', 'done'], true)) {
            if ($stage === 'pending') {
                $clauses[] = "i.count_status = 'pending' AND i.audit_status <> 'discrepancy'";
            } elseif ($stage === 'counted') {
                $clauses[] = "i.count_status IN ('counted','posted') AND i.audit_status NOT IN ('audited','resolved','discrepancy')";
            } elseif ($stage === 'discrepancy') {
                $clauses[] = "i.audit_status = 'discrepancy'";
            } else { // done
                $clauses[] = "i.audit_status IN ('audited','resolved')";
            }
        }

        // Filter cepat "defisit / surplus di atas Rp10jt" (dipakai bulk kirim petugas).
        $minDiff = (($filters['min_diff'] ?? null) === '10jt') ? self::BIG_THRESHOLD : 0.0;
        $highlight = $filters['highlight'] ?? null;
        if (in_array($highlight, ['plus', 'minus'], true)) {
            if ($minDiff > 0.0) {
                $clauses[]  = 'r.diff_amount ' . ($highlight === 'minus' ? '<= -?' : '>= ?');
                $bindings[] = $minDiff;
            } else {
                $clauses[]  = 'r.direction = ?';
                $bindings[] = $highlight;
            }
        }

        if (! empty($filters['search'])) {
            $search     = '%' . trim((string) $filters['search']) . '%';
            $clauses[]  = '(i.code LIKE ? OR i.name LIKE ? OR g.name LIKE ? OR l.name LIKE ?)';
            $bindings[] = $search;
            $bindings[] = $search;
            $bindings[] = $search;
            $bindings[] = $search;
        }

        [$cc, $cb] = self::colClauseSql((array) ($filters['col'] ?? []), self::COL_MAP);
        $clauses = array_merge($clauses, $cc);
        $bindings = array_merge($bindings, $cb);
        [$rc, $rb] = self::rangeClauseSql((array) ($filters['rng'] ?? []), (array) ($filters['txt'] ?? []));
        $clauses = array_merge($clauses, $rc);
        $bindings = array_merge($bindings, $rb);

        $__advConds = (array) ($filters['adv'] ?? []);
        $__ab = [];
        $__ac = \App\Support\AdvancedSearch::clauses($__advConds, self::COL_MAP, ['harga', 'price', 'stok', 'stock', 'hitung', 'counted', 'selqty', 'difference', 'selnil', 'diff_amount'], $__ab);
        $clauses = array_merge($clauses, $__ac);
        $bindings = array_merge($bindings, $__ab);

        $where = $clauses === [] ? '' : ' WHERE ' . implode(' AND ', $clauses);

        $sql = 'SELECT r.*,
                       i.code AS item_code, i.name AS item_name, i.unit, i.category,
                       g.code AS group_code, g.name AS group_name,
                       l.name AS location_name,
                       vu.name AS verified_by_name,
                       su.name AS submitted_by_name,
                       (SELECT GROUP_CONCAT(CONCAT(u.name, \' (\', u.username, \')\') ORDER BY u.name SEPARATOR \' | \')
                          FROM user_groups ug JOIN users u ON u.id = ug.user_id
                         WHERE ug.group_id = g.id AND u.role = \'observer\' AND u.is_active = 1) AS group_observers,
                       (SELECT GROUP_CONCAT(CONCAT(u.name, \' (\', u.username, \')\') ORDER BY u.name SEPARATOR \' | \')
                          FROM user_groups ug JOIN users u ON u.id = ug.user_id
                         WHERE ug.group_id = g.id AND u.role = \'audit\' AND u.is_active = 1) AS group_auditors
                FROM reconciliation r
                JOIN items i ON i.id = r.item_id
                JOIN `groups` g ON g.id = r.group_id
                LEFT JOIN locations l ON l.id = i.location_id
                LEFT JOIN users vu ON vu.id = r.verified_by
                LEFT JOIN users su ON su.id = r.submitted_by' . $where;

        $sortKey = $filters['sort'] ?? 'diff_desc';
        $orderBy = self::SORTS[$sortKey] ?? self::SORTS['diff_desc'];

        return $this->paginate(
            $sql . ' ' . $orderBy,
            $bindings,
            $page,
            $perPage
        );
    }

    /**
     * Summary per group: total items, plus count, minus count, match count,
     * stock qty, counted qty, difference qty, difference amount, condition status.
     *
     * @return array<int, array>
     */
    public function summaryPerGroup(): array
    {
        [$scope, $bindings] = Auth::groupScope('r.group_id');
        $where = $scope !== '' ? ' WHERE ' . $scope : '';

        $sql = "SELECT g.id AS group_id, g.code AS group_code, g.name AS group_name,
                       COUNT(r.id) AS total_items,
                       SUM(CASE WHEN r.direction = 'plus' THEN 1 ELSE 0 END) AS plus_count,
                       SUM(CASE WHEN r.direction = 'minus' THEN 1 ELSE 0 END) AS minus_count,
                       SUM(CASE WHEN r.direction = 'match' THEN 1 ELSE 0 END) AS match_count,
                       SUM(r.stock_qty) AS total_stock,
                       SUM(r.counted_qty) AS total_counted,
                       SUM(r.difference) AS total_diff_qty,
                       SUM(r.diff_amount) AS total_diff_amount,
                       SUM(r.stock_amount) AS total_stock_amount,
                       SUM(r.count_amount) AS total_count_amount
                FROM `groups` g
                LEFT JOIN (
                    reconciliation r 
                    JOIN items i ON i.id = r.item_id AND i.count_status IN ('audited','reconciled') AND i.audit_status IN ('audited','resolved')
                ) ON r.group_id = g.id" . $where . "
                GROUP BY g.id, g.code, g.name
                ORDER BY ABS(total_diff_amount) DESC, g.name ASC";

        return $this->query($sql, $bindings);
    }

    /**
     * Overall totals KPI.
     *
     * @return array
     */
    public function summaryTotals(): array
    {
        [$scope, $bindings] = Auth::groupScope('r.group_id');
        $where = " WHERE i.count_status IN ('audited','reconciled') AND i.audit_status IN ('audited','resolved')";
        if ($scope !== '') {
            $where .= ' AND ' . $scope;
        }

        $sql = "SELECT COUNT(r.id) AS total_items,
                       SUM(CASE WHEN r.direction = 'plus' THEN 1 ELSE 0 END) AS plus_items,
                       SUM(CASE WHEN r.direction = 'minus' THEN 1 ELSE 0 END) AS minus_items,
                       SUM(CASE WHEN r.direction = 'match' THEN 1 ELSE 0 END) AS match_items,
                       SUM(CASE WHEN r.status = 'verified' THEN 1 ELSE 0 END) AS verified_items,
                       SUM(CASE WHEN r.status = 'pending_verify' THEN 1 ELSE 0 END) AS pending_items,
                       SUM(r.stock_qty) AS total_stock_qty,
                       SUM(r.counted_qty) AS total_counted_qty,
                       SUM(r.difference) AS total_diff_qty,
                       SUM(CASE WHEN r.direction = 'plus' THEN r.diff_amount ELSE 0 END) AS total_plus_amount,
                       SUM(CASE WHEN r.direction = 'minus' THEN r.diff_amount ELSE 0 END) AS total_minus_amount,
                       SUM(r.diff_amount) AS net_diff_amount,
                       SUM(r.stock_amount) AS total_stock_amount,
                       SUM(r.count_amount) AS total_count_amount
                FROM reconciliation r
                JOIN items i ON i.id = r.item_id" . $where;

        $row = $this->first($sql, $bindings);

        return $row ?? [
            'total_items'        => 0,
            'plus_items'         => 0,
            'minus_items'        => 0,
            'match_items'        => 0,
            'verified_items'     => 0,
            'pending_items'      => 0,
            'total_stock_qty'    => 0,
            'total_counted_qty'  => 0,
            'total_diff_qty'     => 0,
            'total_plus_amount'  => 0,
            'total_minus_amount' => 0,
            'net_diff_amount'    => 0,
            'total_stock_amount' => 0,
            'total_count_amount' => 0,
        ];
    }

    /**
     * Find single detailed record.
     */
    public function findDetailed(int $id): ?array
    {
        return $this->first(
            'SELECT r.*,
                   i.code AS item_code, i.name AS item_name, i.unit, i.category,
                   i.unit_price AS item_price,
                   g.code AS group_code, g.name AS group_name,
                   l.name AS location_name,
                   vu.name AS verified_by_name, vu.role AS verified_by_role,
                   su.name AS submitted_by_name, su.role AS submitted_by_role,
                   (SELECT GROUP_CONCAT(CONCAT(u.name, \' (\', u.username, \')\') ORDER BY u.name SEPARATOR \' | \')
                      FROM user_groups ug JOIN users u ON u.id = ug.user_id
                     WHERE ug.group_id = g.id AND u.role = \'observer\' AND u.is_active = 1) AS group_observers,
                   (SELECT GROUP_CONCAT(CONCAT(u.name, \' (\', u.username, \')\') ORDER BY u.name SEPARATOR \' | \')
                      FROM user_groups ug JOIN users u ON u.id = ug.user_id
                     WHERE ug.group_id = g.id AND u.role = \'audit\' AND u.is_active = 1) AS group_auditors
            FROM reconciliation r
            JOIN items i ON i.id = r.item_id
            JOIN `groups` g ON g.id = r.group_id
            LEFT JOIN locations l ON l.id = i.location_id
            LEFT JOIN users vu ON vu.id = r.verified_by
            LEFT JOIN users su ON su.id = r.submitted_by
            WHERE r.id = ? LIMIT 1',
            [$id]
        );
    }

    /**
     * Recalculate difference, amounts, and direction for a row.
     */
    public function recalculateRow(int $id): bool
    {
        $row = $this->find($id);
        if ($row === null) {
            return false;
        }

        $stockQty   = (int) $row['stock_qty'];
        $countedQty = (int) $row['counted_qty'];
        $unitPrice  = (float) $row['unit_price'];

        $diff        = $countedQty - $stockQty;
        $direction   = $diff > 0 ? 'plus' : ($diff < 0 ? 'minus' : 'match');
        $stockAmount = round($stockQty * $unitPrice, 2);
        $countAmount = round($countedQty * $unitPrice, 2);
        $diffAmount  = round($diff * $unitPrice, 2);

        $this->updateById($id, [
            'difference'   => $diff,
            'direction'    => $direction,
            'stock_amount' => $stockAmount,
            'count_amount' => $countAmount,
            'diff_amount'  => $diffAmount,
        ]);

        return true;
    }

    /**
     * Tandai satu baris untuk dicek ulang petugas (dipakai aksi bulk).
     *
     * @return array{id:int,item_id:int,group_id:int}|null
     */
    public function requestRecheck(int $id, string $note = ''): ?array
    {
        $row = $this->find($id);
        if ($row === null) {
            return null;
        }

        $this->updateById($id, [
            'recheck_requested' => 1,
            'verify_note'       => $note !== '' ? $note : (string) ($row['verify_note'] ?? 'Dikirim ke petugas untuk dicek ulang.'),
        ]);

        return [
            'id'       => $id,
            'item_id'  => (int) $row['item_id'],
            'group_id' => (int) $row['group_id'],
        ];
    }

    /**
     * Ambil (cap $cap) baris berdasarkan filter ringkas untuk aksi bulk.
     * Filter: highlight=plus|minus, min_diff=10jt, group_id, direction, status, search.
     *
     * @param array<string,mixed> $filters
     * @return array<int,array<string,mixed>>
     */
    public function idsForBulk(array $filters, int $cap = 300): array
    {
        $sql = 'SELECT r.id, r.item_id, r.group_id, r.diff_amount
                FROM reconciliation r
                JOIN items i ON i.id = r.item_id';
        $clauses  = [];
        $bindings = [];

        [$scope, $scopeBindings] = Auth::groupScope('r.group_id');
        if ($scope !== '') {
            $clauses[] = $scope;
            $bindings  = array_merge($bindings, $scopeBindings);
        }

        if (! empty($filters['group_id'])) {
            $clauses[]  = 'r.group_id = ?';
            $bindings[] = (int) $filters['group_id'];
        }
        if (! empty($filters['direction']) && in_array($filters['direction'], self::DIRECTIONS, true)) {
            $clauses[]  = 'r.direction = ?';
            $bindings[] = $filters['direction'];
        }
        if (! empty($filters['status']) && in_array($filters['status'], self::STATUSES, true)) {
            $clauses[]  = 'r.status = ?';
            $bindings[] = $filters['status'];
        }

        $minDiff   = (($filters['min_diff'] ?? null) === '10jt') ? self::BIG_THRESHOLD : 0.0;
        $highlight = $filters['highlight'] ?? null;
        if (in_array($highlight, ['plus', 'minus'], true)) {
            if ($minDiff > 0.0) {
                $clauses[]  = 'r.diff_amount ' . ($highlight === 'minus' ? '<= -?' : '>= ?');
                $bindings[] = $minDiff;
            } else {
                $clauses[]  = 'r.direction = ?';
                $bindings[] = $highlight;
            }
        }

        if (! empty($filters['search'])) {
            $search     = '%' . trim((string) $filters['search']) . '%';
            $clauses[]  = '(i.code LIKE ? OR i.name LIKE ?)';
            $bindings[] = $search;
            $bindings[] = $search;
        }

        if ($clauses !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $clauses);
        }

        $sql .= ' ORDER BY ABS(r.diff_amount) DESC, r.id ASC LIMIT ' . max(1, $cap);

        return $this->query($sql, $bindings);
    }

    /**
     * Sync from items table (creates missing rows or updates current counted_qty/expected_qty).
     */
    public function syncFromItems(): int
    {
        // Hanya item yang SUDAH dilaporkan auditor ke admin (count_status
        // 'audited' atau 'reconciled') yang boleh masuk rekonsiliasi.
        // Item 'posted'/'submitted' masih di tangan auditor -> belum masuk.
        $items = $this->query(
            "SELECT i.id, i.group_id, i.expected_qty, i.counted_qty, i.unit_price
             FROM items i
             WHERE i.count_status IN ('audited','reconciled')
               AND i.audit_status IN ('audited','resolved')"
        );

        $count = 0;
        foreach ($items as $item) {
            $existing = $this->first(
                'SELECT id, stock_qty, counted_qty, unit_price FROM reconciliation WHERE item_id = ? LIMIT 1',
                [(int) $item['id']]
            );

            $stockQty    = (int) $item['expected_qty'];
            $countedQty  = $item['counted_qty'] === null ? 0 : (int) $item['counted_qty'];
            $unitPrice   = (float) ($item['unit_price'] ?? 0);
            $diff        = $countedQty - $stockQty;
            $dir         = $diff > 0 ? 'plus' : ($diff < 0 ? 'minus' : 'match');
            $stockAmount = round($stockQty * $unitPrice, 2);
            $countAmount = round($countedQty * $unitPrice, 2);
            $diffAmount  = round($diff * $unitPrice, 2);

            if ($existing === null) {
                $this->insert([
                    'item_id'      => (int) $item['id'],
                    'group_id'     => (int) $item['group_id'],
                    'period'       => date('Y-m'),
                    'stock_qty'    => $stockQty,
                    'counted_qty'  => $countedQty,
                    'difference'   => $diff,
                    'unit_price'   => $unitPrice,
                    'stock_amount' => $stockAmount,
                    'count_amount' => $countAmount,
                    'diff_amount'  => $diffAmount,
                    'direction'    => $dir,
                    'status'       => 'draft',
                ]);
                $count++;
            }
        }

        return $count;
    }
}
