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

    /**
     * Aksi yang dianggap bagian dari ALUR PROSES STO (untuk panel Jejak Proses).
     * Noise non-proses (update user_rule/menu_rule, login, search) dikecualikan.
     */
    public const TRAIL_ACTIONS = [
        'count', 'posting', 'audit', 'audit_report',
        'verify', 'recheck', 'recheck_bulk', 'recount', 'sync', 'import',
    ];

    /**
     * Jejak proses per group & petugas — dipakai panel "Jejak Proses" yang sama
     * di menu Data STO, Audit, dan Rekonsiliasi.
     *
     * @param array{group_id?:int|null} $filters
     * @return list<array<string,mixed>>
     */
    public function processTrail(array $filters = [], int $limit = 40): array
    {
        $clauses = [];
        $bindings = [];

        [$scope, $scopeBindings] = Auth::groupScope('a.group_id');
        if ($scope !== '') {
            $clauses[] = $scope;
            $bindings = array_merge($bindings, $scopeBindings);
        }

        if (! empty($filters['group_id'])) {
            $gids = array_values(array_filter(array_map('intval', (array) $filters['group_id']), static fn (int $v): bool => $v > 0));
            if ($gids !== []) {
                $clauses[] = 'a.group_id IN (' . implode(',', array_fill(0, count($gids), '?')) . ')';
                foreach ($gids as $g) {
                    $bindings[] = $g;
                }
            }
        }

        $ph = implode(',', array_fill(0, count(self::TRAIL_ACTIONS), '?'));
        $clauses[] = "a.action IN ({$ph})";
        foreach (self::TRAIL_ACTIONS as $a) {
            $bindings[] = $a;
        }

        $where = ' WHERE ' . implode(' AND ', $clauses);
        $limit = max(1, min(200, $limit));

        return $this->query(
            "SELECT a.id, a.action, a.entity, a.entity_id, a.description, a.created_at,
                    u.name AS user_name, u.role AS user_role,
                    g.id AS group_id, g.code AS group_code, g.name AS group_name
             FROM activity_logs a
             LEFT JOIN users u ON u.id = a.user_id
             LEFT JOIN `groups` g ON g.id = a.group_id
             {$where}
             ORDER BY a.created_at DESC, a.id DESC
             LIMIT {$limit}",
            $bindings
        );
    }

    /** Label tahap + kelas badge untuk sebuah action trail. */
    public static function trailStage(string $action): array
    {
        return match ($action) {
            'count'        => ['Hitung',        'badge-warning', 'check-circle'],
            'posting'      => ['Posting',       'badge-info',    'send'],
            'audit'        => ['Audit',         'badge-brand',   'clipboard-check'],
            'audit_report' => ['Lapor audit',   'badge-success', 'clipboard-list'],
            'verify'       => ['Verifikasi',    'badge-success', 'shield-check'],
            'recheck'      => ['Cek ulang',     'badge-warn',    'refresh'],
            'recheck_bulk' => ['Cek ulang',     'badge-warn',    'refresh'],
            'recount'      => ['Hitung ulang',  'badge-danger',  'refresh'],
            'sync'         => ['Sinkronisasi',  'badge-info',    'scale'],
            'import'       => ['Import',        'badge-info',    'upload'],
            default        => [$action,         '',              'activity'],
        };
    }
}
