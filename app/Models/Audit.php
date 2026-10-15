<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Auth;
use App\Core\Model;

class Audit extends Model
{
    protected string $table = 'audits';

    protected array $fillable = [
        'item_id', 'group_id', 'auditor_id', 'expected_qty', 'counted_qty',
        'audited_qty', 'result', 'note', 'is_sample', 'audited_at',
    ];

    /**
     * @param array{group_id?:int|null,result?:string} $filters
     */
    /** Kolom filter Riwayat Audit (whitelist). */
    public const COL_MAP = [
        'item'     => 'i.code',
        'group'    => 'g.name',
        'result'   => 'a.result',
        'auditor'  => 'u.name',
        'expected' => 'a.expected_qty',
        'counted'  => 'a.counted_qty',
        'audited'  => 'a.audited_qty',
        'time'     => 'DATE(a.audited_at)',
    ];

    /** @return array<string, list<string>> */
    public static function normaliseColFilters(mixed $raw): array
    {
        $out = [];
        if (! is_array($raw)) return $out;
        foreach ($raw as $key => $vals) {
            if (! isset(self::COL_MAP[$key]) || ! is_array($vals)) continue;
            if ($key === 'expected' && ! can_see_stock_qty()) continue; // #6 blind
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

    /** Nilai distinct untuk popup, memakai filter kolom lain. @return list<string> */
    public function distinctColValues(string $key, array $col): array
    {
        $expr = self::COL_MAP[$key] ?? null;
        if ($expr === null) return [];
        if ($key === 'expected' && ! can_see_stock_qty()) return []; // #6 blind
        unset($col[$key]);
        [$scope, $sb] = Auth::groupScope('a.group_id');
        $clauses = $scope !== '' ? [$scope] : [];
        $bind = $scope !== '' ? $sb : [];
        [$cc, $cb] = self::colClauseSql($col, self::COL_MAP);
        $clauses = array_merge($clauses, $cc);
        $bind = array_merge($bind, $cb);
        $where = $clauses === [] ? '' : ' WHERE ' . implode(' AND ', $clauses);
        $rows = $this->query("SELECT DISTINCT {$expr} AS v FROM audits a JOIN items i ON i.id = a.item_id JOIN `groups` g ON g.id = a.group_id LEFT JOIN users u ON u.id = a.auditor_id{$where} ORDER BY v LIMIT 500", $bind);
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

        [$scope, $scopeBindings] = Auth::groupScope('a.group_id');
        if ($scope !== '') {
            $clauses[] = $scope;
            $bindings = array_merge($bindings, $scopeBindings);
        }

        if (! empty($filters['group_id'])) {
            $clauses[] = 'a.group_id = ?';
            $bindings[] = $filters['group_id'];
        }

        if (! empty($filters['result'])) {
            $clauses[] = 'a.result = ?';
            $bindings[] = $filters['result'];
        }

        [$cc, $cb] = self::colClauseSql((array) ($filters['col'] ?? []), self::COL_MAP);
        $clauses = array_merge($clauses, $cc);
        $bindings = array_merge($bindings, $cb);

        $where = $clauses === [] ? '' : ' WHERE ' . implode(' AND ', $clauses);

        $sql = 'SELECT a.*, i.code AS item_code, i.name AS item_name,
                       g.code AS group_code, g.name AS group_name,
                       u.name AS auditor_name
                FROM audits a
                JOIN items i ON i.id = a.item_id
                JOIN `groups` g ON g.id = a.group_id
                LEFT JOIN users u ON u.id = a.auditor_id' . $where;

        $res = $this->paginate($sql . ' ORDER BY a.audited_at DESC', $bindings, $page, $perPage);
        if (! can_see_stock_qty()) {
            foreach ($res['items'] as &$row) {
                $row['expected_qty'] = null;
            }
            unset($row);
        }

        return $res;
    }

    /**
     * Audit progress summary for the dashboard.
     *
     * @return array{audited:int,discrepancy:int,coverage:float,total_audits:int}
     */
    public function coverage(): array
    {
        [$scope, $bindings] = Auth::groupScope('group_id');

        $sql = "SELECT
                    COALESCE(SUM(result = 'match'), 0)       AS matched,
                    COALESCE(SUM(result = 'discrepancy'), 0) AS discrepancy,
                    COUNT(*)                                 AS total_audits
                FROM audits";
        if ($scope !== '') {
            $sql .= ' WHERE ' . $scope;
        }

        $row = $this->first($sql, $bindings) ?? [];

        return [
            'audited'      => (int) ($row['matched'] ?? 0),
            'discrepancy'  => (int) ($row['discrepancy'] ?? 0),
            'total_audits' => (int) ($row['total_audits'] ?? 0),
            'coverage'     => 0.0,
        ];
    }

    public function recentForItem(int $itemId): array
    {
        $rows = $this->query(
            'SELECT a.*, u.name AS auditor_name
             FROM audits a LEFT JOIN users u ON u.id = a.auditor_id
             WHERE a.item_id = ? ORDER BY a.audited_at DESC',
            [$itemId]
        );
        if (! can_see_stock_qty()) {
            foreach ($rows as &$row) {
                $row['expected_qty'] = null;
            }
            unset($row);
        }
        return $rows;
    }
}
