<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Auth;
use App\Core\Model;

/**
 * The countable/auditable data records ("data STO").
 */
class Item extends Model
{
    public const COUNT_PENDING = 'pending';
    public const COUNT_COUNTED = 'counted';
    public const COUNT_POSTED  = 'posted';
    /** Dikirim observer ke auditor; menunggu pengecekan. */
    public const COUNT_SUBMITTED  = 'submitted';
    /** Sudah dilaporkan auditor ke Admin STO (menunggu konfirmasi). */
    public const COUNT_AUDITED    = 'audited';
    /** Selesai: rekonsiliasi sudah dikonfirmasi Admin STO. */
    public const COUNT_RECONCILED = 'reconciled';
    public const COUNT_RECOUNT    = 'recount';

    public const AUDIT_PENDING     = 'pending';
    public const AUDIT_AUDITED     = 'audited';
    public const AUDIT_DISCREPANCY = 'discrepancy';
    public const AUDIT_RESOLVED    = 'resolved';
    /** Admin STO meminta auditor mengecek ulang item ini. */
    public const AUDIT_RECOUNT     = 'recount';

    /** Label tampilan untuk count_status (dipakai view & badge). */
    public const COUNT_LABELS = [
        'pending'    => 'Belum dihitung',
        'counted'    => 'Sudah dihitung',
        'posted'     => 'Terposting',
        'submitted'  => 'Di audit',
        'audited'    => 'Diaudit',
        'reconciled' => 'Selesai',
        'recount'    => 'Cek ulang',
    ];

    protected string $table = 'items';

    protected array $fillable = [
        'group_id', 'location_id', 'device_id', 'code', 'name', 'category', 'unit',
        'id_barang', 'serial', 'business_unit', 'sub_inventory', 'unit_price', 'amount_note',
        'expected_qty', 'counted_qty', 'count_status', 'audit_status', 'is_locked', 'needs_recount',
        'condition_note', 'counted_by', 'counted_at', 'posted_by', 'posted_at',
        'batch_id', 'submitted_at', 'created_by',
    ];

    public const COUNT_STATUSES = ['pending', 'counted', 'posted', 'submitted', 'audited', 'reconciled', 'recount'];
    public const AUDIT_STATUSES = ['pending', 'audited', 'discrepancy', 'resolved', 'recount'];

    /** Status count_status yang dianggap final (masuk rekonsiliasi). */
    public const FINAL_STATUSES = ['reconciled'];

    /** Status yang berarti item tidak bisa diubah lagi oleh observer. */
    public const LOCKED_STATUSES = ['posted', 'submitted', 'audited', 'reconciled'];

    public static function countLabel(?string $status): string
    {
        return self::COUNT_LABELS[(string) $status] ?? ucfirst((string) $status);
    }

    /**
     * Normalise filter input coming from the query string.
     *
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function normaliseFilters(array $input): array
    {
        return [
            'group_id'     => self::normaliseIds($input['group_id'] ?? null),
            'location_id'  => self::normaliseIds($input['location_id'] ?? null),
            'count_status' => self::normaliseEnums($input['count_status'] ?? null, self::COUNT_STATUSES),
            'audit_status' => self::normaliseEnums($input['audit_status'] ?? null, self::AUDIT_STATUSES),
            'locked'       => in_array(($input['locked'] ?? ''), ['0', '1'], true) ? (string) $input['locked'] : null,
            'posted'       => in_array(($input['posted'] ?? ''), ['0', '1'], true) ? (string) $input['posted'] : null,
            'search'       => trim((string) ($input['search'] ?? '')),
            'col'          => self::normaliseColFilters($input['col'] ?? null),
            'adv'          => self::normaliseAdvanced($input['adv'] ?? null),
            'sort'         => self::normaliseSort($input['sort'] ?? null, $input['dir'] ?? null),
        ];
    }

    /** Kolom yang bisa difilter (key => ekspresi SQL). */
    public const COL_MAP = [
        'code'     => 'i.code',
        'name'     => 'i.name',
        'group'    => 'g.name',
        'location' => 'l.name',
        'device'   => 'd.name',
        'expected' => 'i.expected_qty',
        'counted'  => 'i.counted_qty',
        'count'    => 'i.count_status',
        'audit'    => 'i.audit_status',
        'unit'     => 'i.unit',
        'category' => 'i.category',
        'price'    => 'i.unit_price',
        'diff'     => '(COALESCE(i.counted_qty, 0) - i.expected_qty)',
        'locked'   => 'i.is_locked',
        'status'   => 'r.status',
        'amount_note'    => 'i.amount_note',
        'condition_note' => 'i.condition_note',
        'device_serial'  => 'd.serial_number',
        'device_type'    => 'd.type',
        'location_code'  => 'l.code',
        'group_code'     => 'g.code',
    ];
    public const NUMERIC_COLS = ['expected', 'counted', 'price', 'diff'];

    /** Kolom yang boleh dipakai untuk sort (sama dengan whitelist filter). */
    public static function normaliseSort(?string $key, ?string $dir): ?array
    {
        if ($key === null || ! isset(self::COL_MAP[$key])) return null;
        if ($key === 'expected' && ! can_see_stock_qty()) return null; // #6 blind
        return ['key' => $key, 'dir' => strtolower((string) $dir) === 'desc' ? 'DESC' : 'ASC'];
    }
    /** Ekspresi ORDER BY aman dari whitelist. */
    private static function orderClause(?array $sort): string
    {
        $base = 'g.name ASC, i.code ASC';
        if ($sort === null) return $base;
        $expr = self::COL_MAP[$sort['key']];
        $isNum = in_array($sort['key'], self::NUMERIC_COLS, true);
        $e = $isNum ? "COALESCE({$expr}, 0)" : "COALESCE({$expr}, '')";
        return "{$e} {$sort['dir']}, i.code ASC";
    }
    public const ADV_OPS = ['contains', 'equals', 'starts', 'gt', 'lt', 'empty', 'in'];

    /** @return array<string, list<string>> */
    public static function normaliseColFilters(mixed $raw): array
    {
        $out = [];
        if (! is_array($raw)) return $out;
        foreach ($raw as $key => $vals) {
            if (! isset(self::COL_MAP[$key]) || ! is_array($vals)) continue;
            // #6 Blind: qty sistem tidak boleh jadi filter bagi observer/audit
            // (kalau tidak, qty bisa dibaca lewat binary-search filter).
            if (in_array($key, ['expected', 'diff'], true) && ! can_see_stock_qty()) continue;
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

    /** @return list<array{col:string,op:string,val:string}> */
    public static function normaliseAdvanced(mixed $raw): array
    {
        $out = [];
        if (! is_array($raw)) return $out;
        foreach ($raw as $row) {
            if (! is_array($row)) continue;
            $col = (string) ($row['col'] ?? '');
            $op  = (string) ($row['op'] ?? '');
            $val = trim((string) ($row['val'] ?? ''));
            if (! isset(self::COL_MAP[$col]) || ! in_array($op, self::ADV_OPS, true)) continue;
            if (in_array($col, ['expected', 'diff'], true) && ! can_see_stock_qty()) continue; // #6 blind
            if ($op !== 'empty' && $val === '') continue;
            if ($op === 'in') {
                $vals = array_values(array_filter(array_map('trim', explode("\x1f", $val)), static fn (string $x): bool => $x !== ''));
                if ($vals === []) continue;
                $out[] = ['col' => $col, 'op' => 'in', 'val' => implode("\x1f", array_slice($vals, 0, 200))];
                if (count($out) >= 12) break;
                continue;
            }
            if (in_array($op, ['gt', 'lt'], true) && ! is_numeric($val)) continue;
            if (mb_strlen($val) > 120) continue;
            $out[] = ['col' => $col, 'op' => $op, 'val' => $val];
            if (count($out) >= 12) break;
        }
        return $out;
    }

    /** @return list<int>|null  Accepts scalar or array (multi-select). */
    public static function normaliseIds(mixed $raw): ?array
    {
        $vals = array_filter(array_map('intval', (array) ($raw ?? [])), static fn (int $v): bool => $v > 0);
        $vals = array_values(array_unique($vals));

        return $vals === [] ? null : $vals;
    }

    /** @return list<string>|null */
    public static function normaliseEnums(mixed $raw, array $allowed): ?array
    {
        $vals = array_values(array_unique(array_filter((array) ($raw ?? []), static fn ($v): bool => in_array($v, $allowed, true))));

        return $vals === [] ? null : $vals;
    }

    /** Build "col IN (?,?)" and push bindings. */
    private function inClause(string $col, array $vals, array &$bindings): string
    {
        foreach ($vals as $v) {
            $bindings[] = $v;
        }

        return $col . ' IN (' . implode(',', array_fill(0, count($vals), '?')) . ')';
    }
    /**
     * Build the WHERE clause respecting the active user's group scope.
     *
     * @param array<string, mixed> $filters
     * @return array{0:string,1:list<mixed>}
     */
    private function buildWhere(array $filters, string $alias = 'i'): array
    {
        $clauses = [];
        $bindings = [];

        [$scope, $scopeBindings] = Auth::groupScope("{$alias}.group_id");
        if ($scope !== '') {
            $clauses[] = $scope;
            $bindings = array_merge($bindings, $scopeBindings);
        }

        if (! empty($filters['group_id'])) {
            $clauses[] = $this->inClause("{$alias}.group_id", (array) $filters['group_id'], $bindings);
        }

        if (! empty($filters['location_id'])) {
            $clauses[] = $this->inClause("{$alias}.location_id", (array) $filters['location_id'], $bindings);
        }

        if (! empty($filters['count_status'])) {
            $statuses = (array) $filters['count_status'];
            // Special case: 'recount' is not a real count_status, it's a flag
            if (in_array('recount', $statuses, true)) {
                $clauses[] = "({$alias}.needs_recount = 1 OR {$alias}.audit_status = 'recount')";
                $statuses = array_diff($statuses, ['recount']);
            } else {
                $clauses[] = "{$alias}.needs_recount = 0 AND {$alias}.audit_status <> 'recount'";
            }
            if (!empty($statuses)) {
                $clauses[] = $this->inClause("{$alias}.count_status", $statuses, $bindings);
            }
        }

        if (! empty($filters['audit_status'])) {
            $clauses[] = $this->inClause("{$alias}.audit_status", (array) $filters['audit_status'], $bindings);
        }

        if (isset($filters['locked']) && $filters['locked'] !== null) {
            $clauses[] = "{$alias}.is_locked = ?";
            $bindings[] = (int) $filters['locked'];
        }

        if (isset($filters['posted']) && $filters['posted'] !== null) {
            $clauses[] = "{$alias}.count_status = 'posted'";
        }

        foreach ((array) ($filters['col'] ?? []) as $key => $vals) {
            $expr = self::COL_MAP[$key] ?? null;
            if ($expr === null) continue;
            $expr = str_replace('i.', "{$alias}.", $expr);
            $ph = implode(',', array_fill(0, count($vals), '?'));
            if (in_array($key, self::NUMERIC_COLS, true)) {
                $clauses[] = "{$expr} IN ({$ph})";
                foreach ($vals as $v) $bindings[] = is_numeric($v) ? (float) $v : -1;
            } else {
                $clauses[] = "{$expr} IN ({$ph})";
                foreach ($vals as $v) $bindings[] = $v;
            }
        }

        foreach ((array) ($filters['adv'] ?? []) as $cond) {
            $expr = self::COL_MAP[$cond['col']] ?? null;
            if ($expr === null) continue;
            $expr = str_replace('i.', "{$alias}.", $expr);
            $isNum = in_array($cond['col'], self::NUMERIC_COLS, true);
            switch ($cond['op']) {
                case 'contains': $clauses[] = "{$expr} LIKE ?"; $bindings[] = '%' . $cond['val'] . '%'; break;
                case 'starts':   $clauses[] = "{$expr} LIKE ?"; $bindings[] = $cond['val'] . '%'; break;
                case 'equals':
                    $clauses[] = "{$expr} = ?";
                    $bindings[] = $isNum && is_numeric($cond['val']) ? (float) $cond['val'] : $cond['val'];
                    break;
                case 'gt': $clauses[] = "{$expr} > ?"; $bindings[] = (float) $cond['val']; break;
                case 'lt': $clauses[] = "{$expr} < ?"; $bindings[] = (float) $cond['val']; break;
                case 'empty': $clauses[] = "({$expr} IS NULL OR {$expr} = '')"; break;
                case 'in':
                    $list = explode("\x1f", $cond['val']);
                    $clauses[] = "{$expr} IN (" . implode(',', array_fill(0, count($list), '?')) . ")";
                    foreach ($list as $lv) $bindings[] = $isNum && is_numeric($lv) ? (float) $lv : $lv;
                    break;
            }
        }

        if (! empty($filters['search'])) {
            $clauses[] = "({$alias}.name LIKE ? OR {$alias}.code LIKE ? OR {$alias}.category LIKE ?)";
            $like = '%' . $filters['search'] . '%';
            $bindings[] = $like;
            $bindings[] = $like;
            $bindings[] = $like;
        }

        return [$clauses === [] ? '1=1' : implode(' AND ', $clauses), $bindings];
    }

    /**
     * @param array<string, mixed> $filters
     * @return array{items:list<array>,total:int,page:int,per_page:int,last_page:int,from:int,to:int}
     */
    public function listDetailed(array $filters, int $page = 1, int $perPage = 15): array
    {
        [$where, $bindings] = $this->buildWhere($filters);

        $sql = "SELECT i.*,
                       g.code AS group_code, g.name AS group_name,
                       l.name AS location_name, d.name AS device_name,
                       cu.name AS counted_by_name, pu.name AS posted_by_name
                FROM items i
                JOIN `groups` g ON g.id = i.group_id
                LEFT JOIN locations l ON l.id = i.location_id
                LEFT JOIN devices d ON d.id = i.device_id
                LEFT JOIN users cu ON cu.id = i.counted_by
                LEFT JOIN users pu ON pu.id = i.posted_by
                WHERE {$where}
                ORDER BY " . self::orderClause($filters['sort'] ?? null);

                $res = $this->paginate($sql, $bindings, $page, $perPage);
                if (! can_see_stock_qty()) {
                foreach ($res['items'] as &$row) {
                    $row['expected_qty'] = null;
                }
                unset($row);
                }

                return $res;
                }

    /**
     * Nilai unik untuk satu kolom (dipakai dropdown filter Excel).
     * Mengikuti filter lain, kecuali filter kolom itu sendiri.
     * @return list<string>
     */
    public function distinctValues(string $key, array $filters): array
    {
        if (! isset(self::COL_MAP[$key])) return [];
        if (in_array($key, ['expected', 'diff'], true) && ! can_see_stock_qty()) return []; // #6 blind: jangan bocorkan qty sistem
        $f = $filters;
        unset($f['col'][$key]);
        [$where, $bindings] = $this->buildWhere($f);
        $expr = self::COL_MAP[$key];
        $rows = (new self())->query(
            "SELECT DISTINCT COALESCE(NULLIF(TRIM(CAST({$expr} AS CHAR)), ''), '(kosong)') AS v
             FROM items i
             JOIN `groups` g ON g.id = i.group_id
             LEFT JOIN locations l ON l.id = i.location_id
             LEFT JOIN devices d ON d.id = i.device_id
             LEFT JOIN reconciliation r ON r.item_id = i.id
             WHERE {$where}
             ORDER BY v LIMIT 500",
            $bindings
        );
        return array_values(array_map(static fn (array $r): string => (string) $r['v'], $rows));
    }

    /** Semua baris yang lolos filter (tanpa pagination) untuk export. */
    public function exportRows(array $filters): array
    {
        [$where, $bindings] = $this->buildWhere($filters);
        $rows = $this->query(
            "SELECT i.code, i.name, g.name AS group_name, l.name AS location_name, d.name AS device_name,
                    i.unit, i.expected_qty, i.counted_qty, i.count_status, i.audit_status
             FROM items i
             JOIN `groups` g ON g.id = i.group_id
             LEFT JOIN locations l ON l.id = i.location_id
             LEFT JOIN devices d ON d.id = i.device_id
             WHERE {$where}
             ORDER BY g.name, i.code LIMIT 20000",
            $bindings
        );
        // #6 blind: qty sistem tidak boleh ikut ter-export untuk observer/audit.
        if (! can_see_stock_qty()) {
            foreach ($rows as &$row) {
                $row['expected_qty'] = null;
            }
            unset($row);
        }
        return $rows;
    }

    /**
     * Cari satu item berdasarkan kode (dipakai scan barcode).
     *
     * Pencarian dilakukan di sisi server supaya hasil scan tetap ketemu
     * walaupun item berada di tab / halaman lain (tidak ikut di-render DOM).
     * Tetap menghormati group-scope + gudang aktif milik user.
     */
    public function findByCode(string $code): ?array
    {
        $code = trim($code);
        if ($code === '') {
            return null;
        }

        [$scope, $scopeBindings] = Auth::groupScope('i.group_id');
        $where = $scope === '' ? '1=1' : $scope;

        return $this->first(
            "SELECT i.id, i.group_id, i.code, i.name, i.category, i.unit,
                    i.expected_qty, i.counted_qty, i.count_status, i.audit_status,
                    i.is_locked, i.needs_recount, i.condition_note, i.sub_inventory,
                    g.code AS group_code, g.name AS group_name,
                    l.name AS location_name, d.name AS device_name
             FROM items i
             JOIN `groups` g ON g.id = i.group_id
             LEFT JOIN locations l ON l.id = i.location_id
             LEFT JOIN devices d ON d.id = i.device_id
             WHERE {$where} AND i.code = ?
             ORDER BY i.id
             LIMIT 1",
            array_merge($scopeBindings, [$code])
        );

        if ($row !== null && ! can_see_stock_qty()) {
            $row['expected_qty'] = null;
        }

        return $row;
    }

    public function findDetailed(int $id): ?array
    {
        $row = $this->first(
            'SELECT i.*, g.code AS group_code, g.name AS group_name,
                    l.name AS location_name, d.name AS device_name,
                    cu.name AS counted_by_name, pu.name AS posted_by_name
             FROM items i
             JOIN `groups` g ON g.id = i.group_id
             LEFT JOIN locations l ON l.id = i.location_id
             LEFT JOIN devices d ON d.id = i.device_id
             LEFT JOIN users cu ON cu.id = i.counted_by
             LEFT JOIN users pu ON pu.id = i.posted_by
             WHERE i.id = ? LIMIT 1',
            [$id]
        );

        if ($row !== null && ! can_see_stock_qty()) {
            $row['expected_qty'] = null;
        }

        return $row;
    }

    /**
     * Aggregate counters used by the dashboard cards.
     *
     * @param array<string, mixed> $filters
     * @return array<string, int>
     */
    public function summary(array $filters = []): array
    {
        [$where, $bindings] = $this->buildWhere($filters);

        $row = $this->first(
            "SELECT
                COUNT(*)                                                   AS total,
                COALESCE(SUM(i.count_status = 'counted' AND i.needs_recount = 0 AND i.audit_status <> 'recount'), 0) AS counted,
                COALESCE(SUM(i.count_status = 'pending' AND i.needs_recount = 0 AND i.audit_status <> 'recount'), 0) AS not_counted,
                COALESCE(SUM(i.count_status IN ('posted', 'submitted')), 0) AS posted,
                COALESCE(SUM(i.needs_recount = 1 OR i.audit_status = 'recount'), 0) AS recount,
                COALESCE(SUM(i.audit_status = 'audited'), 0)               AS audited,
                COALESCE(SUM(i.audit_status = 'pending'), 0)               AS not_audited,
                COALESCE(SUM(i.audit_status = 'discrepancy'), 0)           AS discrepancy,
                COALESCE(SUM(i.audit_status = 'resolved'), 0)              AS resolved,
                COALESCE(SUM(i.is_locked = 1), 0)                          AS locked
             FROM items i
             JOIN `groups` g ON g.id = i.group_id
             LEFT JOIN locations l ON l.id = i.location_id
             LEFT JOIN devices d ON d.id = i.device_id
             WHERE {$where}",
            $bindings
        ) ?? [];

        $keys = ['total', 'counted', 'not_counted', 'posted', 'recount', 'audited', 'not_audited', 'discrepancy', 'resolved', 'locked'];

        $summary = [];
        foreach ($keys as $key) {
            $summary[$key] = (int) ($row[$key] ?? 0);
        }

        return $summary;
    }

    /**
     * Hitung total nilai stok sistem & hasil hitung (hanya untuk yang berhak).
     * @param array<string, mixed> $filters
     * @return array{total_sys_val:float,total_counted_val:float}
     */
    public function stockValues(array $filters = []): array
    {
        if (! can_see_stock_qty()) {
            return ['total_sys_val' => 0.0, 'total_counted_val' => 0.0];
        }

        [$where, $bindings] = $this->buildWhere($filters);
        $row = $this->first(
            "SELECT COALESCE(SUM(i.expected_qty * i.unit_price), 0) AS total_sys_val,
                    COALESCE(SUM(COALESCE(i.counted_qty, 0) * i.unit_price), 0) AS total_counted_val
             FROM items i
             JOIN `groups` g ON g.id = i.group_id
             LEFT JOIN locations l ON l.id = i.location_id
             LEFT JOIN devices d ON d.id = i.device_id
             WHERE {$where}",
            $bindings
        );

        return [
            'total_sys_val'     => (float) ($row['total_sys_val'] ?? 0),
            'total_counted_val' => (float) ($row['total_counted_val'] ?? 0),
        ];
    }

    /**
     * Per-group counting & auditing progress.
     *
     * @param array<string, mixed> $filters
     * @return list<array<string, mixed>>
     */
    public function groupProgress(array $filters = []): array
    {
        $clauses = [];
        $bindings = [];

        [$scope, $scopeBindings] = Auth::groupScope('g.id');
        if ($scope !== '') {
            $clauses[] = $scope;
            $bindings = array_merge($bindings, $scopeBindings);
        }

        if (! empty($filters['group_id'])) {
            $clauses[] = 'g.id = ?';
            $bindings[] = $filters['group_id'];
        }

        $where = $clauses === [] ? '' : ' WHERE ' . implode(' AND ', $clauses);

        $rows = $this->query(
            "SELECT g.id, g.code, g.name,
                    COUNT(i.id)                                        AS total,
                    COALESCE(SUM(i.count_status = 'counted'), 0)       AS counted,
                    COALESCE(SUM(i.count_status = 'pending'), 0)       AS not_counted,
                    COALESCE(SUM(i.count_status IN ('posted', 'submitted')), 0) AS posted,
                    COALESCE(SUM(i.audit_status = 'audited'), 0)       AS audited,
                    COALESCE(SUM(i.audit_status = 'pending'), 0)       AS not_audited,
                    COALESCE(SUM(i.audit_status = 'discrepancy'), 0)   AS discrepancy,
                    COALESCE(SUM(CASE WHEN i.counted_qty IS NOT NULL THEN (i.counted_qty - i.expected_qty) * i.unit_price ELSE 0 END), 0) AS diff_amount,
                    COALESCE(SUM(CASE WHEN i.counted_qty IS NOT NULL THEN (i.counted_qty - i.expected_qty) ELSE 0 END), 0) AS diff_qty,
                    (COALESCE(SUM(CASE WHEN i.audit_status = 'recount' OR i.needs_recount = 1 THEN 1 ELSE 0 END), 0) + 
                     (SELECT COUNT(*) FROM posting_batches pb WHERE pb.group_id = g.id AND pb.status = 'recount')) AS recount_count
             FROM `groups` g
             LEFT JOIN items i ON i.group_id = g.id
             {$where}
             GROUP BY g.id, g.code, g.name
             ORDER BY g.name",
            $bindings
        );

        return array_map(static function (array $row): array {
            $total = (int) $row['total'];
            $row['total'] = $total;
            $row['counted'] = (int) $row['counted'];
            $row['not_counted'] = (int) $row['not_counted'];
            $row['posted'] = (int) $row['posted'];
            $row['audited'] = (int) $row['audited'];
            $row['not_audited'] = (int) $row['not_audited'];
            $row['discrepancy'] = (int) $row['discrepancy'];
            $row['diff_amount'] = (float) ($row['diff_amount'] ?? 0);
            $row['diff_qty'] = (int) ($row['diff_qty'] ?? 0);
            $row['recount_count'] = (int) ($row['recount_count'] ?? 0);
            $row['count_percent'] = percent($row['counted'], $total);
            $row['audit_percent'] = percent($row['audited'], $total);

            return $row;
        }, $rows);
    }

    /**
     * Items that are posted and still waiting for audit.
     *
     * @param array<string, mixed> $filters
     * @return array{items:list<array>,total:int,page:int,per_page:int,last_page:int,from:int,to:int}
     */
    public function auditQueue(array $filters, int $page = 1, int $perPage = 15): array
    {
        $filters['count_status'] = [self::COUNT_POSTED, 'submitted'];
        $filters['audit_status'] = self::AUDIT_PENDING;

        return $this->listDetailed($filters, $page, $perPage);
    }

    /**
     * Rekomendasi sampel audit minimal X% per group (hanya yang belum diaudit).
     *
     * @return list<array>
     */
    public function auditRecommendation(int $percent): array
    {
        [$scope, $scopeBindings] = Auth::groupScope('i.group_id');
        $scopeSql = $scope === '' ? '' : ' AND ' . $scope;

        $rows = $this->query(
            "SELECT g.id AS group_id, g.code AS group_code, g.name AS group_name,
                    COUNT(*) AS total_items,
                    (SELECT COUNT(*) FROM audits a WHERE a.group_id = g.id) AS audited_count
             FROM items i
             JOIN `groups` g ON g.id = i.group_id
             WHERE 1=1{$scopeSql}
             GROUP BY g.id, g.code, g.name",
            $scopeBindings
        );

        $out = [];
        foreach ($rows as $r) {
            $total = (int) $r['total_items'];
            if ($total === 0) {
                continue;
            }
            $needed = max(1, (int) ceil($total * $percent / 100));
            $remaining = max(0, $needed - (int) $r['audited_count']);
            if ($remaining === 0) {
                continue;
            }

            $items = $this->query(
                "SELECT i.id, i.code, i.name, i.unit, i.expected_qty, i.counted_qty,
                        i.is_locked, i.audit_status, g.name AS group_name, g.code AS group_code
                 FROM items i
                 JOIN `groups` g ON g.id = i.group_id
                 WHERE i.group_id = ? AND i.count_status = 'posted' AND i.audit_status = 'pending'
                 ORDER BY i.is_locked DESC, i.code
                 LIMIT {$remaining}",
                [(int) $r['group_id']]
            );

            foreach ($items as $it) {
                $it['needed'] = $needed;
                $it['audited_count'] = (int) $r['audited_count'];
                $it['group_total'] = $total;
                if (! can_see_stock_qty()) {
                    $it['expected_qty'] = null;
                }
                $out[] = $it;
            }
        }

        return $out;
    }

    /**
     * Semua item yang sudah diposting observer (apa pun status auditnya).
     *
     * @param array<string, mixed> $filters
     * @return array{items:list<array>,total:int,page:int,per_page:int,last_page:int,from:int,to:int}
     */
    public function postedAll(array $filters, int $page = 1, int $perPage = 15): array
    {
        $filters['count_status'] = [self::COUNT_POSTED, 'submitted'];
        $filters['audit_status'] = null;

        return $this->listDetailed($filters, $page, $perPage);
    }

    /**
     * Item yang diminta dicek ulang oleh Admin STO (reconciliation.recheck_requested=1).
     *
     * @param array<string, mixed> $filters
     * @return array{items:list<array>,total:int,page:int,per_page:int,last_page:int,from:int,to:int}
     */
    public function recheckItems(array $filters, int $page = 1, int $perPage = 15): array
    {
        [$scope, $bindings] = Auth::groupScope('i.group_id');
        $clauses = ['r.recheck_requested = 1'];
        if ($scope !== '') {
            $clauses[] = $scope;
        }
        if (! empty($filters['group_id'])) {
            $clauses[] = $this->inClause('i.group_id', (array) $filters['group_id'], $bindings);
        }
        if (! empty($filters['search'])) {
            $like = '%' . $filters['search'] . '%';
            $clauses[] = '(i.code LIKE ? OR i.name LIKE ? OR g.name LIKE ?)';
            array_push($bindings, $like, $like, $like);
        }
        $where = implode(' AND ', $clauses);

        $sql = "SELECT i.id, i.code, i.name, i.unit, i.expected_qty, i.counted_qty, i.count_status,
                       i.audit_status, i.is_locked,
                       g.name AS group_name, g.code AS group_code,
                       l.name AS location_name,
                       r.id AS recon_id, r.status AS recon_status, r.counted_qty AS recon_counted,
                       r.difference AS recon_difference, r.verify_note AS recon_note,
                       r.submitted_at AS recon_submitted_at
                FROM reconciliation r
                JOIN items i ON i.id = r.item_id
                JOIN `groups` g ON g.id = i.group_id
                LEFT JOIN locations l ON l.id = i.location_id
                WHERE {$where}
                ORDER BY g.name, i.code";

        $res = $this->paginate($sql, $bindings, $page, $perPage);
        if (! can_see_stock_qty()) {
            foreach ($res['items'] as &$row) {
                $row['expected_qty'] = null;
            }
            unset($row);
        }

        return $res;
    }

    /**
     * Pick a representative audit sample of at least X% per group.
     *
     * @return list<array>
     */
    public function auditSample(int $percent): array
    {
        $groups = $this->groupProgress();

        $sample = [];
        foreach ($groups as $group) {
            $total = (int) $group['total'];
            if ($total === 0) {
                continue;
            }

            $needed = max(1, (int) ceil($total * $percent / 100));

            $rows = $this->query(
                "SELECT i.*, g.code AS group_code, g.name AS group_name
                 FROM items i
                 JOIN `groups` g ON g.id = i.group_id
                 WHERE i.group_id = ? AND i.count_status = 'posted' AND i.audit_status = 'pending'
                 ORDER BY i.is_locked DESC, i.code
                 LIMIT {$needed}",
                [$group['id']]
            );

            $sample = array_merge($sample, $rows);
        }

        return $sample;
    }

    /**
     * Items that need the observer to search for a difference.
     *
     * @param array<string, mixed> $filters
     */
    public function discrepancyItems(array $filters = []): array
    {
        $filters['audit_status'] = self::AUDIT_DISCREPANCY;

        return $this->listDetailed($filters, 1, 200)['items'];
    }

    public function statsForGroup(int $groupId): array
    {
        return $this->summary(['group_id' => $groupId]);
    }

    public function markCounted(int $id, int $qty, int $userId, ?string $note): void
    {
        $this->updateById($id, [
            'counted_qty'    => $qty,
            'count_status'   => self::COUNT_COUNTED,
            'audit_status'   => self::AUDIT_PENDING,
            'condition_note' => $note,
            'counted_by'     => $userId,
            'counted_at'     => date('Y-m-d H:i:s'),
            'needs_recount'  => 0,
        ]);
    }

    public function markPosted(int $id, int $userId): void
    {
        $this->updateById($id, [
            'count_status' => self::COUNT_POSTED,
            'posted_by'    => $userId,
            'posted_at'    => date('Y-m-d H:i:s'),
            'is_locked'    => 1,
        ]);
    }

    /**
     * Admin STO minta hitung ulang: item keluar dari alur posting/audit,
     * kembali ke 'counted' + audit_status 'recount' dan dibuka kuncinya
     * supaya observer bisa menghitung & memposting ulang.
     */
    public function markRecount(int $id): void
    {
        $this->updateById($id, [
            'count_status'  => self::COUNT_COUNTED,
            'audit_status'  => self::AUDIT_RECOUNT,
            'needs_recount' => 1,
            'is_locked'     => 0,
        ]);
    }

    public function unlock(int $id): void
    {
        $this->updateById($id, ['is_locked' => 0]);
    }

    public function lock(int $id): void
    {
        $this->updateById($id, ['is_locked' => 1]);
    }

    // ------------------------------------------------------------------
    // Alur posting -> audit (observer -> auditor -> admin)
    // ------------------------------------------------------------------

    /**
     * Ringkasan kesiapan posting untuk satu group: berapa yang sudah dihitung
     * dan berapa yang masih pending. Posting hanya boleh bila pending = 0.
     *
     * @return array{pending:int,counted:int,posted:int,total:int,ready:bool}
     */
    public function postingReadiness(int $groupId, string $period = 'default'): array
    {
        // Item 'posted' lama (diposting per-item sebelum alur batch ada) yang
        // belum punya batch_id tetap dianggap SIAP diposting: saat posting
        // dijalankan, item legacy itu "diadopsi" ke dalam batch baru.
        $row = $this->first(
            "SELECT
                SUM(count_status = 'pending') AS pending,
                SUM(count_status = 'counted' OR (count_status = 'posted' AND batch_id IS NULL)) AS counted,
                SUM(count_status IN ('submitted','audited','reconciled') OR (count_status = 'posted' AND batch_id IS NOT NULL)) AS posted,
                COUNT(*) AS total
             FROM items WHERE group_id = ?",
            [$groupId]
        ) ?? [];

        $pending = (int) ($row['pending'] ?? 0);
        $counted = (int) ($row['counted'] ?? 0);

        return [
            'pending' => $pending,
            'counted' => $counted,
            'posted'  => (int) ($row['posted'] ?? 0),
            'total'   => (int) ($row['total'] ?? 0),
            'ready'   => $pending === 0 && $counted > 0,
        ];
    }

    /**
     * Item dalam sebuah batch berdasarkan status count-nya.
     *
     * @return array{items:list<array>,total:int,page:int,per_page:int,last_page:int,from:int,to:int}
     */
    public function batchItems(int $batchId, int $page = 1, int $perPage = 15, ?string $countStatus = null): array
    {
        $bindings = [$batchId];
        $where = 'i.batch_id = ?';
        if ($countStatus !== null) {
            $where .= ' AND i.count_status = ?';
            $bindings[] = $countStatus;
        }

        $sql = "SELECT i.id, i.code, i.name, i.unit, i.expected_qty, i.counted_qty,
                       i.count_status, i.audit_status, i.is_locked,
                       g.name AS group_name, g.code AS group_code,
                       l.name AS location_name
                FROM items i
                JOIN `groups` g ON g.id = i.group_id
                LEFT JOIN locations l ON l.id = i.location_id
                WHERE {$where}
                ORDER BY i.code";

        $res = $this->paginate($sql, $bindings, $page, $perPage);
        if (! can_see_stock_qty()) {
            foreach ($res['items'] as &$row) {
                $row['expected_qty'] = null;
            }
            unset($row);
        }

        return $res;
    }

    /**
     * Rekomendasi sampel audit 10% per group untuk batch tertentu.
     * Hanya item posted+audit_status pending yang belum punya baris di audits.
     *
     * @return list<array>
     */
    public function auditSampleForBatch(int $batchId, int $percent = 10): array
    {
        $groupRow = $this->first(
            'SELECT group_id FROM posting_batches WHERE id = ?',
            [$batchId]
        );
        if ($groupRow === null) {
            return [];
        }
        $groupId = (int) $groupRow['group_id'];

        $total = (int) ($this->scalar(
            'SELECT COUNT(*) FROM items WHERE batch_id = ?',
            [$batchId]
        ) ?? 0);
        if ($total === 0) {
            return [];
        }
        $needed = max(1, (int) ceil($total * $percent / 100));
        $audited = (int) ($this->scalar(
            'SELECT COUNT(*) FROM audits WHERE group_id = ?',
            [$groupId]
        ) ?? 0);
        $remaining = max(0, $needed - $audited);
        if ($remaining === 0) {
            return [];
        }

        $rows = $this->query(
            "SELECT i.id, i.code, i.name, i.unit, i.expected_qty, i.counted_qty,
                    i.audit_status, i.is_locked,
                    g.name AS group_name, g.code AS group_code
             FROM items i
             JOIN `groups` g ON g.id = i.group_id
             WHERE i.batch_id = ?
               AND i.count_status IN ('posted','submitted')
               AND i.audit_status = 'pending'
             ORDER BY i.is_locked DESC, i.code
             LIMIT {$remaining}",
            [$batchId]
        );

        if (! can_see_stock_qty()) {
            foreach ($rows as &$row) {
                $row['expected_qty'] = null;
            }
            unset($row);
        }

        return $rows;
    }

    /**
     * Item dalam batch yang ditandai perlu cek ulang oleh Admin STO
     * (audit_status = 'recount').
     *
     * @return list<array>
     */
    public function recountItemsForBatch(int $batchId): array
    {
        $rows = $this->query(
            "SELECT i.id, i.code, i.name, i.unit, i.expected_qty, i.counted_qty,
                    i.audit_status, i.count_status, i.is_locked,
                    g.name AS group_name, g.code AS group_code
             FROM items i
             JOIN `groups` g ON g.id = i.group_id
             WHERE i.batch_id = ? AND i.audit_status = 'recount'
             ORDER BY i.code",
            [$batchId]
        );

        if (! can_see_stock_qty()) {
            foreach ($rows as &$row) {
                $row['expected_qty'] = null;
            }
            unset($row);
        }

        return $rows;
    }

    /**
     * Tandai semua item dalam batch sudah diaudit (setelah auditor lapor).
     */
    public function markBatchAudited(int $batchId): int
    {
        $stmt = $this->run(
            "UPDATE items
             SET count_status = 'audited', audit_status = 'audited'
             WHERE batch_id = ? AND count_status IN ('posted','submitted')",
            [$batchId]
        );

        return $stmt->rowCount();
    }

    /** Ubah status sebuah batch. */
    public function updateBatchStatus(int $batchId, string $status): void
    {
        $this->run('UPDATE posting_batches SET status = ? WHERE id = ?', [$status, $batchId]);
    }

    /** Batalkan (recount) semua item dalam satu batch: kembali ke counted. */
    public function reopenBatch(int $batchId): int
    {
        $stmt = $this->run(
            "UPDATE items
             SET count_status = 'counted', audit_status = 'recount', is_locked = 0
             WHERE batch_id = ? AND count_status IN ('posted','submitted','audited','reconciled')",
            [$batchId]
        );

        return $stmt->rowCount();
    }

    /**
     * Tandai item selesai (rekonsiliasi dikonfirmasi admin).
     */
    public function markReconciled(int $itemId): void
    {
        $this->updateById($itemId, [
            'count_status' => self::COUNT_RECONCILED,
            'audit_status' => self::AUDIT_RESOLVED,
        ]);
    }

    /**
     * Item yang memenuhi syarat masuk rekonsiliasi: sudah dilaporkan auditor
     * ke admin (count_status in audited/reconciled).
     *
     * @return array{items:list<array>,total:int,page:int,per_page:int,last_page:int,from:int,to:int}
     */
    public function reconcilableItems(int $page = 1, int $perPage = 15): array
    {
        [$scope, $bindings] = Auth::groupScope('i.group_id');
        $where = "i.count_status IN ('audited','reconciled')";
        if ($scope !== '') {
            $where .= ' AND ' . $scope;
        }

        $sql = "SELECT i.id, i.code, i.name, i.unit, i.expected_qty, i.counted_qty,
                       i.unit_price, i.count_status,
                       g.name AS group_name, g.code AS group_code
                FROM items i
                JOIN `groups` g ON g.id = i.group_id
                WHERE {$where}
                ORDER BY g.name, i.code";

        $res = $this->paginate($sql, $bindings, $page, $perPage);
        if (! can_see_stock_qty()) {
            foreach ($res['items'] as &$row) {
                $row['expected_qty'] = null;
            }
            unset($row);
        }

        return $res;
    }

    /**
     * Tampilkan baris snapshot untuk periode tertutup (read-only), cocok dengan format listDetailed.
     */
    public function listDetailedSnapshot(int $periodId, array $filters, int $page = 1, int $perPage = 15): array
    {
        $clauses = ['s.period_id = ?'];
        $bindings = [$periodId];

        [$scope, $scopeBindings] = Auth::groupScope('s.group_id');
        if ($scope !== '') {
            $clauses[] = $scope;
            $bindings = array_merge($bindings, $scopeBindings);
        }

        if (! empty($filters['group_id'])) {
            $clauses[] = $this->inClause('s.group_id', (array) $filters['group_id'], $bindings);
        }

        if (! empty($filters['count_status'])) {
            $statuses = (array) $filters['count_status'];
            $stClauses = [];
            if (in_array('pending', $statuses, true)) {
                $stClauses[] = "(s.count_status = 'pending' OR s.count_status IS NULL)";
            }
            if (in_array('counted', $statuses, true)) {
                $stClauses[] = "s.count_status = 'counted'";
            }
            if (in_array('posted', $statuses, true) || in_array('submitted', $statuses, true)) {
                $stClauses[] = "s.count_status IN ('posted', 'submitted')";
            }
            if (in_array('audited', $statuses, true) || in_array('reconciled', $statuses, true)) {
                $stClauses[] = "s.count_status IN ('audited', 'reconciled')";
            }
            if (in_array('recount', $statuses, true)) {
                $stClauses[] = "(s.audit_status = 'recount' OR s.count_status = 'recount')";
            }
            $other = array_diff($statuses, ['pending', 'counted', 'posted', 'submitted', 'audited', 'reconciled', 'recount']);
            if (! empty($other)) {
                $stClauses[] = $this->inClause('s.count_status', $other, $bindings);
            }
            if (! empty($stClauses)) {
                $clauses[] = '(' . implode(' OR ', $stClauses) . ')';
            }
        }

        if (! empty($filters['audit_status'])) {
            $clauses[] = $this->inClause('s.audit_status', (array) $filters['audit_status'], $bindings);
        }

        if (! empty($filters['search'])) {
            $clauses[] = "(s.item_name LIKE ? OR s.item_code LIKE ? OR g.name LIKE ?)";
            $like = '%' . $filters['search'] . '%';
            $bindings[] = $like;
            $bindings[] = $like;
            $bindings[] = $like;
        }

        $where = implode(' AND ', $clauses);

        $sql = "SELECT s.id,
                       s.item_id,
                       s.item_code AS code,
                       s.item_name AS name,
                       s.group_id,
                       s.unit_price,
                       s.expected_qty,
                       s.counted_qty,
                       s.audited_qty,
                       s.difference,
                       COALESCE(s.count_status, 'pending') AS count_status,
                       COALESCE(s.audit_status, 'pending') AS audit_status,
                       1 AS is_locked,
                       COALESCE(i.category, '-') AS category,
                       COALESCE(i.unit, 'UNIT') AS unit,
                       g.code AS group_code,
                       g.name AS group_name,
                       COALESCE(l.name, '-') AS location_name,
                       COALESCE(d.name, '-') AS device_name,
                       NULL AS counted_by_name,
                       NULL AS posted_by_name
                FROM period_snapshots s
                JOIN `groups` g ON g.id = s.group_id
                LEFT JOIN items i ON i.id = s.item_id
                LEFT JOIN locations l ON l.id = i.location_id
                LEFT JOIN devices d ON d.id = i.device_id
                WHERE {$where}
                ORDER BY g.name ASC, s.item_code ASC";

        $res = $this->paginate($sql, $bindings, $page, $perPage);
        if (! can_see_stock_qty()) {
            foreach ($res['items'] as &$row) {
                $row['expected_qty'] = null;
            }
            unset($row);
        }

        return $res;
    }

    /**
     * Ringkasan hitungan untuk tab status pada periode snapshot.
     */
    public function summarySnapshot(int $periodId, array $filters = []): array
    {
        $clauses = ['s.period_id = ?'];
        $bindings = [$periodId];

        [$scope, $scopeBindings] = Auth::groupScope('s.group_id');
        if ($scope !== '') {
            $clauses[] = $scope;
            $bindings = array_merge($bindings, $scopeBindings);
        }

        if (! empty($filters['group_id'])) {
            $clauses[] = $this->inClause('s.group_id', (array) $filters['group_id'], $bindings);
        }

        if (! empty($filters['search'])) {
            $clauses[] = "(s.item_name LIKE ? OR s.item_code LIKE ? OR g.name LIKE ?)";
            $like = '%' . $filters['search'] . '%';
            $bindings[] = $like;
            $bindings[] = $like;
            $bindings[] = $like;
        }

        $where = implode(' AND ', $clauses);

        $sql = "SELECT
                    COUNT(*) AS total,
                    SUM(CASE WHEN s.count_status = 'pending' OR s.count_status IS NULL THEN 1 ELSE 0 END) AS pending,
                    SUM(CASE WHEN s.count_status = 'counted' THEN 1 ELSE 0 END) AS counted,
                    SUM(CASE WHEN s.count_status IN ('posted', 'submitted') THEN 1 ELSE 0 END) AS posted,
                    SUM(CASE WHEN s.count_status IN ('audited', 'reconciled') THEN 1 ELSE 0 END) AS audited,
                    SUM(CASE WHEN s.audit_status = 'recount' OR s.count_status = 'recount' THEN 1 ELSE 0 END) AS recount
                FROM period_snapshots s
                JOIN `groups` g ON g.id = s.group_id
                WHERE {$where}";

        $row = $this->first($sql, $bindings) ?? [];

        return [
            'total'       => (int) ($row['total'] ?? 0),
            'pending'     => (int) ($row['pending'] ?? 0),
            'counted'     => (int) ($row['counted'] ?? 0),
            'posted'      => (int) ($row['posted'] ?? 0),
            'audited'     => (int) ($row['audited'] ?? 0),
            'recount'     => (int) ($row['recount'] ?? 0),
            'not_counted' => (int) ($row['pending'] ?? 0),
            'locked'      => (int) ($row['total'] ?? 0),
        ];
    }

    /**
     * Progres per group untuk dashboard pada periode snapshot.
     */
    public function groupProgressSnapshot(int $periodId, array $filters = []): array
    {
        $clauses = ['s.period_id = ?'];
        $bindings = [$periodId];

        [$scope, $scopeBindings] = Auth::groupScope('g.id');
        if ($scope !== '') {
            $clauses[] = $scope;
            $bindings = array_merge($bindings, $scopeBindings);
        }

        $where = implode(' AND ', $clauses);

        $sql = "SELECT g.id, g.name, g.code,
                       COUNT(s.id) AS total,
                       COALESCE(SUM(s.count_status = 'counted'), 0) AS counted,
                       COALESCE(SUM(s.count_status = 'pending' OR s.count_status IS NULL), 0) AS not_counted,
                       COALESCE(SUM(s.count_status IN ('posted', 'submitted')), 0) AS posted,
                       COALESCE(SUM(s.count_status IN ('audited', 'reconciled')), 0) AS audited,
                       COALESCE(SUM(s.count_status NOT IN ('audited', 'reconciled') OR s.count_status IS NULL), 0) AS not_audited,
                       COALESCE(SUM(s.difference * s.unit_price), 0) AS diff_amount,
                       COALESCE(SUM(s.difference), 0) AS diff_qty,
                       COALESCE(SUM(CASE WHEN s.audit_status = 'recount' OR s.count_status = 'recount' THEN 1 ELSE 0 END), 0) AS recount_count
                FROM `groups` g
                LEFT JOIN period_snapshots s ON s.group_id = g.id AND {$where}
                GROUP BY g.id, g.name, g.code
                ORDER BY g.name ASC";

        $rows = $this->query($sql, $bindings);
        return array_map(static function (array $row): array {
            $total = (int) $row['total'];
            $row['total'] = $total;
            $row['counted'] = (int) $row['counted'];
            $row['not_counted'] = (int) $row['not_counted'];
            $row['posted'] = (int) $row['posted'];
            $row['audited'] = (int) $row['audited'];
            $row['not_audited'] = (int) $row['not_audited'];
            $row['diff_amount'] = (float) ($row['diff_amount'] ?? 0);
            $row['diff_qty'] = (int) ($row['diff_qty'] ?? 0);
            $row['recount_count'] = (int) ($row['recount_count'] ?? 0);
            $row['count_percent'] = percent((int) $row['counted'], $total);
            $row['audit_percent'] = percent((int) $row['audited'], $total);
            return $row;
        }, $rows);
    }
}
