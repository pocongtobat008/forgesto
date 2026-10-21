<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Auth;
use App\Core\Model;

class Group extends Model
{
    // `groups` is a reserved word in MySQL 8, so it is always quoted.
    protected string $table = '`groups`';

    protected array $fillable = ['sto_id', 'warehouse_id', 'code', 'name', 'description', 'pic_employee_id', 'pic_name', 'is_active'];

    /**
     * Groups enriched with their warehouse, STO code, item counts and the
     * observer/auditor officers delegated to each group.
     *
     * The GROUP_CONCAT subqueries keep the officer list in a single column so
     * the table can render compact chips instead of several rows per group.
     *
     * @return list<array>
     */
    /** Kolom yang bisa difilter (key => kolom baris hasil listDetailed). Whitelist. */
    public const COL_MAP = [
        'code'      => 'code',
        'name'      => 'name',
        'warehouse' => 'warehouse_name',
        'officers'  => 'officers',
        'sto'       => 'sto_code',
        'racks'     => 'location_count',
        'items'     => 'item_count',
        'pic'       => 'pic_name',
        'status'    => 'is_active',
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

    /** Terapkan filter kolom pada baris hasil (data kecil, paginasi di PHP). @return list<array> */
    public static function applyColFilters(array $rows, array $col): array
    {
        foreach ($col as $key => $vals) {
            if ($vals === []) continue;
            if ($key === 'officers') {
                $allowed = array_map('strval', $vals);
                $rows = array_values(array_filter($rows, static function (array $r) use ($allowed): bool {
                    $names = [];
                    if (!empty($r['observer_names'])) {
                        foreach (explode(' | ', (string) $r['observer_names']) as $n) {
                            $n = trim($n); if ($n !== '') $names[] = $n;
                        }
                    }
                    if (!empty($r['auditor_names'])) {
                        foreach (explode(' | ', (string) $r['auditor_names']) as $n) {
                            $n = trim($n); if ($n !== '') $names[] = $n;
                        }
                    }
                    if ($names === []) {
                        return in_array('— belum ditunjuk —', $allowed, true);
                    }
                    foreach ($allowed as $a) {
                        if (in_array($a, $names, true)) return true;
                    }
                    return false;
                }));
                continue;
            }
            $field = self::COL_MAP[$key] ?? null;
            if ($field === null) continue;
            $allowed = array_map('strval', $vals);
            $rows = array_values(array_filter($rows, static function (array $r) use ($field, $allowed): bool {
                return in_array((string) ($r[$field] ?? ''), $allowed, true);
            }));
        }
        return $rows;
    }

    /**
     * Terapkan kondisi "Pencarian lanjutan" (adv) pada baris hasil.
     * Satu fitur pencarian menggantikan filter-filter tersebar.
     *
     * @param list<array> $rows
     * @param list<array{col:string,op:string,val:string}> $conds
     * @return list<array>
     */
    public static function applyAdv(array $rows, array $conds): array
    {
        if ($conds === []) {
            return $rows;
        }

        return array_values(array_filter($rows, static function (array $r) use ($conds): bool {
            foreach ($conds as $c) {
                if ($c['col'] === 'officers') {
                    $val = trim((string) (($r['observer_names'] ?? '') . ' ' . ($r['auditor_names'] ?? '')));
                    $q   = (string) $c['val'];
                    $ok  = match ($c['op']) {
                        'contains' => mb_stripos($val, $q) !== false,
                        'starts'   => $q !== '' && mb_stripos($val, $q) === 0,
                        'equals'   => mb_stripos($val, $q) !== false,
                        'empty'    => $val === '',
                        default    => true,
                    };
                    if (!$ok) return false;
                    continue;
                }
                $field = self::COL_MAP[$c['col']] ?? null;
                if ($field === null) continue;
                $val = (string) ($r[$field] ?? '');
                $q   = (string) $c['val'];

                $ok = match ($c['op']) {
                    'contains' => mb_stripos($val, $q) !== false,
                    'starts'   => $q !== '' && mb_stripos($val, $q) === 0,
                    'equals'   => mb_strtolower($val) === mb_strtolower($q),
                    'empty'    => $val === '',
                    'gt'       => is_numeric($val) && is_numeric($q) && (float) $val > (float) $q,
                    'lt'       => is_numeric($val) && is_numeric($q) && (float) $val < (float) $q,
                    default    => true,
                };
                if (! $ok) {
                    return false;   // AND
                }
            }
            return true;
        }));
    }

    /** Nilai distinct per kolom untuk popup filter. @return list<string> */
    public function distinctValues(string $key, array $filters): array
    {
        if ($key === 'officers') {
            $f = $filters; unset($f['col'][$key]);
            $rows = self::applyColFilters($this->listDetailed(), $f['col'] ?? []);
            $out = [];
            $hasUnassigned = false;
            foreach ($rows as $r) {
                $found = false;
                if (!empty($r['observer_names'])) {
                    foreach (explode(' | ', (string) $r['observer_names']) as $n) {
                        $n = trim($n); if ($n !== '') { $out[$n] = true; $found = true; }
                    }
                }
                if (!empty($r['auditor_names'])) {
                    foreach (explode(' | ', (string) $r['auditor_names']) as $n) {
                        $n = trim($n); if ($n !== '') { $out[$n] = true; $found = true; }
                    }
                }
                if (!$found) $hasUnassigned = true;
            }
            $vals = array_keys($out);
            sort($vals, SORT_NATURAL | SORT_FLAG_CASE);
            if ($hasUnassigned) $vals[] = '— belum ditunjuk —';
            return array_map('strval', array_slice($vals, 0, 500));
        }

        $field = self::COL_MAP[$key] ?? null;
        if ($field === null) return [];
        $f = $filters; unset($f['col'][$key]);
        $rows = self::applyColFilters($this->listDetailed(), $f['col'] ?? []);
        $out = [];
        foreach ($rows as $r) {
            $v = $r[$field] ?? null;
            if ($v === null || $v === '') continue;
            $out[(string) $v] = true;
        }
        $vals = array_keys($out);
        sort($vals, SORT_NATURAL | SORT_FLAG_CASE);
        return array_map('strval', array_slice($vals, 0, 500));
    }

    public function listDetailed(): array
    {
        [$scope, $bindings] = Auth::groupScope('g.id');

        $sql = <<<SQL
            SELECT g.*,
                   w.code AS warehouse_code, w.name AS warehouse_name, w.city AS warehouse_city,
                   s.code AS sto_code, s.name AS sto_name, s.address AS sto_address,
                   (SELECT COUNT(*) FROM items i WHERE i.group_id = g.id) AS item_count,
                   (SELECT COUNT(*) FROM locations l WHERE l.group_id = g.id) AS location_count,
                   (SELECT COUNT(*) FROM user_groups ug WHERE ug.group_id = g.id) AS member_count,
                   (SELECT GROUP_CONCAT(u.name ORDER BY u.name SEPARATOR ' | ')
                      FROM user_groups ug JOIN users u ON u.id = ug.user_id
                     WHERE ug.group_id = g.id AND u.role = 'observer' AND u.is_active = 1) AS observer_names,
                   (SELECT GROUP_CONCAT(u.name ORDER BY u.name SEPARATOR ' | ')
                      FROM user_groups ug JOIN users u ON u.id = ug.user_id
                     WHERE ug.group_id = g.id AND u.role = 'audit' AND u.is_active = 1) AS auditor_names
              FROM `groups` g
              LEFT JOIN sto s ON s.id = g.sto_id
              LEFT JOIN warehouses w ON w.id = g.warehouse_id
            SQL;

        if ($scope !== '') {
            $sql .= ' WHERE ' . $scope;
        }

        $sql .= ' ORDER BY w.name, g.name';

        return $this->query($sql, $bindings);
    }

    /**
     * Groups visible to the active user (for filter dropdowns).
     *
     * @return list<array>
     */
    public function forActiveUser(): array
    {
        [$scope, $bindings] = Auth::groupScope('id');

        $sql = 'SELECT id, code, name FROM `groups`';
        if ($scope !== '') {
            $sql .= ' WHERE ' . $scope;
        }
        $sql .= ' ORDER BY name';

        return $this->query($sql, $bindings);
    }

    public function withSto(int $id): ?array
    {
        return $this->first(
            'SELECT g.*, s.code AS sto_code, s.name AS sto_name,
                    w.code AS warehouse_code, w.name AS warehouse_name, w.city AS warehouse_city
             FROM `groups` g
             LEFT JOIN sto s ON s.id = g.sto_id
             LEFT JOIN warehouses w ON w.id = g.warehouse_id
             WHERE g.id = ? LIMIT 1',
            [$id]
        );
    }
}