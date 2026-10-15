<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Auth;
use App\Core\Model;

class Location extends Model
{
    protected string $table = 'locations';

    protected array $fillable = ['group_id', 'code', 'name', 'address', 'is_active'];

    /**
     * @param array{group_id?:int|null,search?:string} $filters
     * @return array{items:list<array>,total:int,page:int,per_page:int,last_page:int,from:int,to:int}
     */
    /** Kolom yang bisa difilter (key => ekspresi SQL). Whitelist. */
    public const COL_MAP = [
        'code'   => 'l.code',
        'name'   => 'l.name',
        'group'  => 'g.name',
        'device' => '(SELECT COUNT(*) FROM devices d WHERE d.location_id = l.id)',
        'item'   => '(SELECT COUNT(*) FROM items i WHERE i.location_id = l.id)',
        'status' => 'l.is_active',
    ];

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
    private function colClauses(array $filters): array
    {
        $clauses = []; $bindings = [];
        foreach ((array) ($filters['col'] ?? []) as $key => $vals) {
            $expr = self::COL_MAP[$key] ?? null;
            if ($expr === null || ! is_array($vals) || $vals === []) continue;
            $ph = implode(',', array_fill(0, count($vals), '?'));
            $clauses[] = "{$expr} IN ({$ph})";
            foreach ($vals as $v) $bindings[] = $v;
        }
        return [$clauses, $bindings];
    }

    /** Nilai distinct per kolom untuk popup (ikut scope grup, kecuali kolom ini). @return list<string> */
    public function distinctValues(string $key, array $filters): array
    {
        $expr = self::COL_MAP[$key] ?? null;
        if ($expr === null) return [];
        $f = $filters; unset($f['col'][$key]);
        [$scope, $sb] = Auth::groupScope('l.group_id');
        $clauses = $scope !== '' ? [$scope] : [];
        $bind = $scope !== '' ? $sb : [];
        [$cc, $cb] = $this->colClauses($f);
        $clauses = array_merge($clauses, $cc);
        $bind = array_merge($bind, $cb);
        $where = $clauses === [] ? '' : ' WHERE ' . implode(' AND ', $clauses);
        $rows = $this->query("SELECT DISTINCT {$expr} AS v FROM locations l JOIN `groups` g ON g.id = l.group_id{$where} ORDER BY v LIMIT 500", $bind);
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

        [$scope, $scopeBindings] = Auth::groupScope('l.group_id');
        if ($scope !== '') {
            $clauses[] = $scope;
            $bindings = array_merge($bindings, $scopeBindings);
        }

        if (! empty($filters['group_id'])) {
            $clauses[] = 'l.group_id = ?';
            $bindings[] = $filters['group_id'];
        }

        if (! empty($filters['search'])) {
            $clauses[] = '(l.name LIKE ? OR l.code LIKE ?)';
            $like = '%' . $filters['search'] . '%';
            $bindings[] = $like;
            $bindings[] = $like;
        }

        $__advConds = (array) ($filters['adv'] ?? []);
        $__ab = [];
        $__ac = \App\Support\AdvancedSearch::clauses($__advConds, self::COL_MAP, [], $__ab);
        $clauses = array_merge($clauses, $__ac);
        $bindings = array_merge($bindings, $__ab);

        [$cc, $cb] = $this->colClauses($filters);
        $clauses = array_merge($clauses, $cc);
        $bindings = array_merge($bindings, $cb);

        $where = $clauses === [] ? '' : ' WHERE ' . implode(' AND ', $clauses);

        $sql = 'SELECT l.id, l.group_id, l.code, l.name, l.address, l.is_active, l.created_at, l.updated_at, g.code AS group_code, g.name AS group_name,
                       (SELECT COUNT(*) FROM devices d WHERE d.location_id = l.id) AS device_count,
                       (SELECT COUNT(*) FROM items i WHERE i.location_id = l.id) AS item_count
                FROM locations l
                JOIN `groups` g ON g.id = l.group_id' . $where;

        return $this->paginate($sql . ' ORDER BY g.name, l.name', $bindings, $page, $perPage);
    }

    /** @return list<array> */
    public function forActiveUser(): array
    {
        [$scope, $bindings] = Auth::groupScope('l.group_id');

        $sql = 'SELECT l.id, l.code, l.name, l.group_id, g.name AS group_name
                FROM locations l JOIN `groups` g ON g.id = l.group_id';

        if ($scope !== '') {
            $sql .= ' WHERE ' . $scope;
        }

        $sql .= ' ORDER BY g.name, l.name';

        return $this->query($sql, $bindings);
    }

    public function inGroup(int $groupId): array
    {
        return $this->all(['group_id' => $groupId], 'name ASC');
    }

    public function countScoped(): int
    {
        [$scope, $bindings] = Auth::groupScope('group_id');

        return (int) $this->scalar(
            'SELECT COUNT(*) FROM locations' . ($scope !== '' ? ' WHERE ' . $scope : ''),
            $bindings
        );
    }
}
