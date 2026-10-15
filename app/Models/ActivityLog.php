<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Auth;
use App\Core\Model;

class ActivityLog extends Model
{
    protected string $table = 'activity_logs';

    protected array $fillable = [
        'user_id', 'group_id', 'action', 'entity', 'entity_id', 'description', 'ip_address',
    ];

    public function record(
        string $action,
        ?string $entity = null,
        ?int $entityId = null,
        ?string $description = null,
        ?int $groupId = null,
        ?int $userId = null
    ): int {
        return $this->insert([
            'user_id'     => $userId ?? Auth::id(),
            'group_id'    => $groupId,
            'action'      => $action,
            'entity'      => $entity,
            'entity_id'   => $entityId,
            'description' => $description,
            'ip_address'  => $_SERVER['REMOTE_ADDR'] ?? null,
        ]);
    }

    /**
     * @param array{group_id?:int|null,action?:string,search?:string} $filters
     */
    /** Kolom filter Aktivitas (whitelist). */
    public const COL_MAP = [
        'time'   => 'DATE(a.created_at)',
        'action' => 'a.action',
        'group'  => 'g.name',
        'user'   => 'u.name',
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

    public function listDetailed(array $filters, int $page = 1, int $perPage = 20): array
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

        if (! empty($filters['action'])) {
            $clauses[] = 'a.action = ?';
            $bindings[] = $filters['action'];
        }

        if (! empty($filters['search'])) {
            $clauses[] = '(a.description LIKE ? OR u.name LIKE ?)';
            $like = '%' . $filters['search'] . '%';
            $bindings[] = $like;
            $bindings[] = $like;
        }

        $textMap = ['description' => 'a.description', 'ip' => 'a.ip_address'];
        foreach ((array) ($filters['text'] ?? []) as $tk => $tv) {
            $tv = trim((string) $tv);
            if (! isset($textMap[$tk]) || $tv === '' || mb_strlen($tv) > 120) continue;
            $clauses[] = $textMap[$tk] . ' LIKE ?';
            $bindings[] = '%' . $tv . '%';
        }
        $day = (string) ($filters['date'] ?? '');
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $day)) {
            $clauses[] = 'DATE(a.created_at) = ?';
            $bindings[] = $day;
        }

        $__advConds = (array) ($filters['adv'] ?? []);
        $__ab = [];
        $__ac = \App\Support\AdvancedSearch::clauses($__advConds, self::COL_MAP, [], $__ab);
        $clauses = array_merge($clauses, $__ac);
        $bindings = array_merge($bindings, $__ab);

        [$cc, $cb] = self::colClauseSql((array) ($filters['col'] ?? []), self::COL_MAP);
        $clauses = array_merge($clauses, $cc);
        $bindings = array_merge($bindings, $cb);

        $where = $clauses === [] ? '' : ' WHERE ' . implode(' AND ', $clauses);

        $sql = 'SELECT a.*, u.name AS user_name, u.role AS user_role, g.code AS group_code
                FROM activity_logs a
                LEFT JOIN users u ON u.id = a.user_id
                LEFT JOIN `groups` g ON g.id = a.group_id' . $where;

        return $this->paginate($sql . ' ORDER BY a.created_at DESC', $bindings, $page, $perPage);
    }

    public function recent(int $limit = 8): array
    {
        [$scope, $bindings] = Auth::groupScope('a.group_id');

        $sql = 'SELECT a.*, u.name AS user_name
                FROM activity_logs a
                LEFT JOIN users u ON u.id = a.user_id';

        if ($scope !== '') {
            $sql .= ' WHERE ' . $scope;
        }

        $sql .= ' ORDER BY a.created_at DESC LIMIT ' . max(1, $limit);

        return $this->query($sql, $bindings);
    }
}
