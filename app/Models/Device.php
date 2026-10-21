<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Auth;
use App\Core\Model;

class Device extends Model
{
    protected string $table = 'devices';

    protected array $fillable = ['location_id', 'code', 'name', 'type', 'serial_number', 'is_active',
        'user_id', 'status', 'in_use_by', 'in_use_at'];

    /**
     * @param array{group_id?:int|null,location_id?:int|null,search?:string} $filters
     */
    /** Kolom yang bisa difilter (key => ekspresi SQL). Whitelist. */
    public const COL_MAP = [
        'code'     => 'd.code',
        'name'     => 'd.name',
        'type'     => 'd.type',
        'serial'   => 'd.serial_number',
        'location' => 'l.name',
        'status'   => 'd.status',
        'active'   => 'd.is_active',
        'holder'   => 'h.name',
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
        $rows = $this->query("SELECT DISTINCT {$expr} AS v FROM devices d JOIN locations l ON l.id = d.location_id JOIN `groups` g ON g.id = l.group_id{$where} ORDER BY v LIMIT 500", $bind);
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

        if (! empty($filters['location_id'])) {
            $clauses[] = 'd.location_id = ?';
            $bindings[] = $filters['location_id'];
        }

        if (! empty($filters['search'])) {
            $clauses[] = '(d.name LIKE ? OR d.code LIKE ? OR d.serial_number LIKE ?)';
            $like = '%' . $filters['search'] . '%';
            $bindings[] = $like;
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

        $sql = 'SELECT d.*, l.name AS location_name, l.code AS location_code,
                       g.id AS group_id, g.code AS group_code, g.name AS group_name,
                       u.name AS assigned_name, u.username AS assigned_username,
                       h.name AS holder_name, h.username AS holder_username
                FROM devices d
                JOIN locations l ON l.id = d.location_id
                JOIN `groups` g ON g.id = l.group_id
                LEFT JOIN users u ON u.id = d.user_id
                LEFT JOIN users h ON h.id = d.in_use_by' . $where;

        return $this->paginate($sql . ' ORDER BY g.name, l.name, d.name', $bindings, $page, $perPage);
    }

    /** Device yang didaftarkan (di-assign) untuk satu user. */
    public function findByUser(int $userId): ?array
    {
        $row = $this->query(
            'SELECT d.*, l.name AS location_name, l.code AS location_code
             FROM devices d JOIN locations l ON l.id = d.location_id
             WHERE d.user_id = ? ORDER BY d.is_active DESC, d.id ASC LIMIT 1',
            [$userId]
        );

        return $row[0] ?? null;
    }

    /**
     * Tandai tablet milik user sebagai sedang dipakai (free -> in_use).
     * Device lain yang masih tercatat dipegang user ini dilepas lebih dulu.
     */
    public function bindToUser(int $userId): ?array
    {
        $device = $this->findByUser($userId);
        if ($device === null) {
            return null;
        }

        $this->run(
            "UPDATE devices SET status = 'free', in_use_by = NULL, in_use_at = NULL
             WHERE in_use_by = ? AND id <> ?",
            [$userId, (int) $device['id']]
        );
        $this->run(
            "UPDATE devices SET status = 'in_use', in_use_by = ?, in_use_at = NOW() WHERE id = ?",
            [$userId, (int) $device['id']]
        );

        $device['status']    = 'in_use';
        $device['in_use_by'] = $userId;

        return $device;
    }

    /** Lepas semua tablet yang dipegang user (dipakai saat logout). */
    public function releaseByUser(int $userId): int
    {
        return $this->run(
            "UPDATE devices SET status = 'free', in_use_by = NULL, in_use_at = NULL WHERE in_use_by = ?",
            [$userId]
        )->rowCount();
    }

    /**
     * Lepas tablet yang "nyangkut" in_use tanpa sesi aktif.
     * Dipanggil saat halaman login / daftar tablet dibuka, supaya aturan
     * "login = dipakai, tidak login = free" tetap benar walau browser ditutup
     * tanpa logout (sesi yatim).
     */
    public function releaseStale(int $hours = 12): int
    {
        return $this->run(
            "UPDATE devices
                SET status = 'free', in_use_by = NULL, in_use_at = NULL
              WHERE status = 'in_use'
                AND (in_use_at IS NULL OR in_use_at < (NOW() - INTERVAL ? HOUR))",
            [$hours]
        )->rowCount();
    }

    /** Ringkasan status pemakaian untuk dashboard menu tablet. @return array{free:int,in_use:int} */
    public function usageSummary(): array
    {
        $rows = $this->query("SELECT status, COUNT(*) AS c FROM devices GROUP BY status");
        $out = ['free' => 0, 'in_use' => 0];
        foreach ($rows as $r) {
            $k = (string) $r['status'];
            if (isset($out[$k])) {
                $out[$k] = (int) $r['c'];
            }
        }

        return $out;
    }

    public function inLocation(int $locationId): array
    {
        return $this->all(['location_id' => $locationId], 'name ASC');
    }

    public function countScoped(): int
    {
        [$scope, $bindings] = Auth::groupScope('l.group_id');

        $sql = 'SELECT COUNT(*) FROM devices d JOIN locations l ON l.id = d.location_id';
        if ($scope !== '') {
            $sql .= ' WHERE ' . $scope;
        }

        return (int) $this->scalar($sql, $bindings);
    }
}
