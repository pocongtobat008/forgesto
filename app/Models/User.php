<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

class User extends Model
{
    protected string $table = 'users';

    protected array $fillable = [
        'name', 'username', 'email', 'phone', 'password', 'role', 'is_active',
    ];

    public function findByUsername(string $username): ?array
    {
        return $this->findBy('username', $username);
    }

    /** @return list<int> */
    public function groupIds(int $userId): array
    {
        $rows = $this->query('SELECT group_id FROM user_groups WHERE user_id = ?', [$userId]);

        return array_map(static fn (array $row): int => (int) $row['group_id'], $rows);
    }

    public function withGroup(int $id): ?array
    {
        $user = $this->find($id);
        if ($user === null) {
            return null;
        }

        $user['group_ids'] = $this->groupIds($id);
        $user['groups'] = $this->query(
            'SELECT g.id, g.code, g.name
             FROM `groups` g
             JOIN user_groups ug ON ug.group_id = g.id
             WHERE ug.user_id = ?
             ORDER BY g.name',
            [$id]
        );

        return $user;
    }

    /**
     * @param array{role?:string,search?:string,is_active?:string} $filters
     * @return array{items:list<array>,total:int,page:int,per_page:int,last_page:int,from:int,to:int}
     */
    /** Kolom yang bisa difilter (key => ekspresi SQL). Whitelist, bukan input mentah. */
    public const COL_MAP = [
        'name'     => 'u.name',
        'username' => 'u.username',
        'email'    => 'u.email',
        'role'     => 'u.role',
        'group'    => 'group',
        'status'   => 'u.is_active',
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

    /** Nilai distinct per kolom untuk popup filter (ikut menerapkan filter lain kecuali kolom ini). @return list<string> */
    public function distinctValues(string $key, array $filters): array
    {
        if ($key === 'group') {
            $groups = $this->query("SELECT DISTINCT code FROM `groups` ORDER BY code");
            $out = ['Semua group'];
            foreach ($groups as $g) {
                if (!empty($g['code'])) $out[] = (string) $g['code'];
            }
            $out[] = '— belum ada —';
            return $out;
        }

        $expr = self::COL_MAP[$key] ?? null;
        if ($expr === null) return [];
        $f = $filters; unset($f['col'][$key]);
        [$where, $bind] = $this->colWhere($f);
        $sql = "SELECT DISTINCT {$expr} AS v FROM users u{$where} ORDER BY v LIMIT 500";
        $rows = $this->query($sql, $bind);
        $out = [];
        foreach ($rows as $r) {
            if ($r['v'] === null || $r['v'] === '') continue;
            $out[] = (string) $r['v'];
        }
        return $out;
    }

    /** @return array{0:string,1:list<mixed>} */
    private function colWhere(array $filters): array
    {
        $clauses = []; $bindings = [];
        foreach ((array) ($filters['col'] ?? []) as $key => $vals) {
            if (! is_array($vals) || $vals === []) continue;
            if ($key === 'group') {
                $subClauses = [];
                foreach ($vals as $v) {
                    if ($v === 'Semua group') {
                        $subClauses[] = "u.role NOT IN ('observer', 'audit')";
                    } elseif ($v === '— belum ada —') {
                        $subClauses[] = "(u.role IN ('observer', 'audit') AND NOT EXISTS (SELECT 1 FROM user_groups ug WHERE ug.user_id = u.id))";
                    } else {
                        $subClauses[] = "EXISTS (SELECT 1 FROM user_groups ug JOIN `groups` g ON g.id = ug.group_id WHERE ug.user_id = u.id AND (g.code = ? OR g.name = ?))";
                        $bindings[] = $v;
                        $bindings[] = $v;
                    }
                }
                if ($subClauses !== []) {
                    $clauses[] = '(' . implode(' OR ', $subClauses) . ')';
                }
                continue;
            }
            $expr = self::COL_MAP[$key] ?? null;
            if ($expr === null) continue;
            $ph = implode(',', array_fill(0, count($vals), '?'));
            $clauses[] = "{$expr} IN ({$ph})";
            foreach ($vals as $v) $bindings[] = $v;
        }
        return [$clauses === [] ? '' : ' WHERE ' . implode(' AND ', $clauses), $bindings];
    }

    public function listDetailed(array $filters, int $page = 1, int $perPage = 15): array
    {
        $clauses = [];
        $bindings = [];

        if (! empty($filters['role'])) {
            $clauses[] = 'u.role = ?';
            $bindings[] = $filters['role'];
        }

        if (isset($filters['is_active']) && $filters['is_active'] !== '') {
            $clauses[] = 'u.is_active = ?';
            $bindings[] = (int) $filters['is_active'];
        }

        if (! empty($filters['search'])) {
            $clauses[] = '(u.name LIKE ? OR u.username LIKE ? OR u.email LIKE ?)';
            $like = '%' . $filters['search'] . '%';
            $bindings[] = $like;
            $bindings[] = $like;
            $bindings[] = $like;
        }

        [$cWhere, $cBind] = $this->colWhere($filters);
        if ($cWhere !== '') {
            $clauses[] = substr($cWhere, 7);
            $bindings = array_merge($bindings, $cBind);
        }

        $__advConds = (array) ($filters['adv'] ?? []);
        $__ab = [];
        $__ac = \App\Support\AdvancedSearch::clauses($__advConds, self::COL_MAP, [], $__ab);
        $clauses = array_merge($clauses, $__ac);
        $bindings = array_merge($bindings, $__ab);

        $where = $clauses === [] ? '' : ' WHERE ' . implode(' AND ', $clauses);

        $sql = 'SELECT u.*,
                       (SELECT GROUP_CONCAT(g.code ORDER BY g.code SEPARATOR \', \')
                          FROM user_groups ug JOIN `groups` g ON g.id = ug.group_id
                         WHERE ug.user_id = u.id) AS group_codes
                FROM users u' . $where;

        return $this->paginate($sql . ' ORDER BY u.role, u.name', $bindings, $page, $perPage);
    }

    /**
     * Replace the group delegations of a user.
     *
     * @param list<int|string> $groupIds
     */
    public function syncGroups(int $userId, array $groupIds): void
    {
        $this->run('DELETE FROM user_groups WHERE user_id = ?', [$userId]);

        $unique = array_values(array_unique(array_filter(array_map('intval', $groupIds))));
        foreach ($unique as $groupId) {
            $this->run(
                'INSERT IGNORE INTO user_groups (user_id, group_id) VALUES (?, ?)',
                [$userId, $groupId]
            );
        }
    }

    public function countByRole(string $role): int
    {
        return $this->countWhere('role = ? AND is_active = 1', [$role]);
    }

    /**
     * Users that belong to any of the given groups (e.g. to notify observers).
     *
     * @param list<int> $groupIds
     * @return list<array>
     */
    public function inGroups(array $groupIds, string $role): array
    {
        if ($groupIds === []) {
            return [];
        }

        $placeholders = implode(', ', array_fill(0, count($groupIds), '?'));
        $bindings = $groupIds;
        $bindings[] = $role;

        return $this->query(
            "SELECT u.id, u.name, u.username, ug.group_id
             FROM users u
             JOIN user_groups ug ON ug.user_id = u.id
             WHERE ug.group_id IN ({$placeholders}) AND u.role = ? AND u.is_active = 1",
            $bindings
        );
    }
}
