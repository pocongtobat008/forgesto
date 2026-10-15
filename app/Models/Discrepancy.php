<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Auth;
use App\Core\Model;

class Discrepancy extends Model
{
    protected string $table = 'discrepancies';

    protected array $fillable = [
        'item_id', 'group_id', 'expected_qty', 'counted_qty', 'audited_qty',
        'difference', 'status', 'note', 'resolution_note', 'created_by',
        'resolved_by', 'resolved_at',
    ];

    public const STATUSES = ['open', 'searching', 'resolved'];

    /**
     * @param array{group_id?:int|null,status?:string} $filters
     */
    /** Kolom filter Selisih (whitelist). */
    public const COL_MAP = [
        'item'   => 'i.code',
        'group'  => 'g.name',
        'status' => 'd.status',
        'expected'   => 'd.expected_qty',
        'counted'    => 'd.counted_qty',
        'audited'    => 'd.audited_qty',
        'difference' => 'd.difference',
        'reported'   => 'cu.name',
    ];

    /** Kolom filter (whitelist). @return array{0:list<string>,1:list<mixed>} */
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

    /** @return array<string, list<string>> */
    public static function normaliseColFilters(mixed $raw, array $map): array
    {
        $out = [];
        if (! is_array($raw)) return $out;
        foreach ($raw as $key => $vals) {
            if (! isset($map[$key]) || ! is_array($vals)) continue;
            if (in_array($key, ['expected', 'difference'], true) && ! can_see_stock_qty()) continue; // #6 blind
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

    /** Nilai distinct untuk popup, memakai filter kolom lain. @return list<string> */
    public function distinctColValues(string $key, array $col, array $map, string $from, string $scope = '', array $scopeBind = []): array
    {
        $expr = $map[$key] ?? null;
        if ($expr === null) return [];
        if (in_array($key, ['expected', 'difference'], true) && ! can_see_stock_qty()) return []; // #6 blind
        unset($col[$key]);
        $clauses = $scope !== '' ? [$scope] : [];
        $bind = $scope !== '' ? $scopeBind : [];
        [$cc, $cb] = self::colClauseSql($col, $map);
        $clauses = array_merge($clauses, $cc);
        $bind = array_merge($bind, $cb);
        $where = $clauses === [] ? '' : ' WHERE ' . implode(' AND ', $clauses);
        $rows = $this->query("SELECT DISTINCT {$expr} AS v {$from}{$where} ORDER BY v LIMIT 500", $bind);
        $out = [];
        foreach ($rows as $r) {
            if ($r['v'] === null || $r['v'] === '') continue;
            $out[] = (string) $r['v'];
        }
        return $out;
    }

    public function listDetailed(array $filters, int $page = 1, int $perPage = 15): array
    {
        $clauses = [];
        $bindings = [];

        [$scope, $scopeBindings] = Auth::groupScope('d.group_id');
        if ($scope !== '') {
            $clauses[] = $scope;
            $bindings = array_merge($bindings, $scopeBindings);
        }

        if (! empty($filters['group_id'])) {
            $clauses[] = 'd.group_id = ?';
            $bindings[] = $filters['group_id'];
        }

        if (! empty($filters['status'])) {
            $clauses[] = 'd.status = ?';
            $bindings[] = $filters['status'];
        }

        if (! empty($filters['search'])) {
            $term = '%' . trim((string) $filters['search']) . '%';
            $clauses[] = '(i.code LIKE ? OR i.name LIKE ? OR g.name LIKE ? OR d.note LIKE ?)';
            $bindings = array_merge($bindings, [$term, $term, $term, $term]);
        }


        $__advConds = (array) ($filters['adv'] ?? []);
        $__ab = [];
        $__ac = \App\Support\AdvancedSearch::clauses($__advConds, self::COL_MAP, ['expected', 'counted', 'audited', 'difference'], $__ab);
        $clauses = array_merge($clauses, $__ac);
        $bindings = array_merge($bindings, $__ab);

        [$cc, $cb] = self::colClauseSql((array) ($filters['col'] ?? []), self::COL_MAP);
        $clauses = array_merge($clauses, $cc);
        $bindings = array_merge($bindings, $cb);

        $where = $clauses === [] ? '' : ' WHERE ' . implode(' AND ', $clauses);

        $sql = 'SELECT d.*, i.code AS item_code, i.name AS item_name,
                       g.code AS group_code, g.name AS group_name,
                       cu.name AS created_by_name, ru.name AS resolved_by_name
                FROM discrepancies d
                JOIN items i ON i.id = d.item_id
                JOIN `groups` g ON g.id = d.group_id
                LEFT JOIN users cu ON cu.id = d.created_by
                LEFT JOIN users ru ON ru.id = d.resolved_by' . $where;

        $res = $this->paginate(
            $sql . " ORDER BY FIELD(d.status, 'open', 'searching', 'resolved'), ABS(d.difference) DESC, d.created_at DESC",
            $bindings,
            $page,
            $perPage
        );

        if (! can_see_stock_qty()) {
            foreach ($res['items'] as &$row) {
                $row['expected_qty'] = null;
                $row['difference'] = null;
            }
            unset($row);
        }

        return $res;
    }

    public function openCount(): int
    {
        [$scope, $bindings] = Auth::groupScope('group_id');

        $sql = "SELECT COUNT(*) FROM discrepancies WHERE status <> 'resolved'";
        if ($scope !== '') {
            $sql .= ' AND ' . $scope;
        }

        return (int) $this->scalar($sql, $bindings);
    }

    public function findByItem(int $itemId): ?array
    {
        $row = $this->first(
            'SELECT d.*, i.code AS item_code, i.name AS item_name,
                   g.code AS group_code, g.name AS group_name,
                   cu.name AS created_by_name, ru.name AS resolved_by_name
            FROM discrepancies d
            JOIN items i ON i.id = d.item_id
            JOIN `groups` g ON g.id = d.group_id
            LEFT JOIN users cu ON cu.id = d.created_by
            LEFT JOIN users ru ON ru.id = d.resolved_by
            WHERE d.item_id = ? ORDER BY d.id DESC LIMIT 1',
            [$itemId]
        );

        if ($row !== null && ! can_see_stock_qty()) {
            $row['expected_qty'] = null;
            $row['difference'] = null;
        }

        return $row;
    }

    public function resolveForReconciledItem(int $itemId, int $resolvedBy, string $note = ''): void
    {
        $this->run(
            "UPDATE discrepancies 
             SET status = 'resolved', 
                 resolution_note = ?, 
                 resolved_by = ?, 
                 resolved_at = NOW() 
             WHERE item_id = ? AND status <> 'resolved'",
            [$note ?: 'Diselesaikan otomatis melalui rekonsiliasi.', $resolvedBy, $itemId]
        );
    }
}
