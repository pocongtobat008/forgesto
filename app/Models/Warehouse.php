<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * Warehouse (Gudang / Situs) — the parent entity of Groups.
 *
 * A warehouse is a physical site (e.g. Depok, Cikarang, Karawang, Pandaan).
 * Switching the active warehouse in the header scopes the entire application.
 */
class Warehouse extends Model
{
    protected string $table = 'warehouses';

    protected array $fillable = ['code', 'name', 'city', 'address', 'is_active'];

    public function findByCode(string $code): ?array
    {
        return $this->findBy('code', $code);
    }

    /**
     * Warehouses usable in dropdowns (active first, then alphabetical).
     *
     * @return list<array>
     */
    public function options(): array
    {
        return $this->query('SELECT * FROM warehouses ORDER BY is_active DESC, name ASC');
    }

    /**
     * Warehouses enriched with their group / location / item counts.
     *
     * @return list<array>
     */
    /** Kolom yang bisa difilter (key => kolom baris hasil listDetailed). Whitelist. */
    public const COL_MAP = [
        'code'    => 'code',
        'name'    => 'name',
        'city'    => 'city',
        'address' => 'address',
        'groups'  => 'group_count',
        'racks'   => 'location_count',
        'items'   => 'item_count',
        'status'  => 'is_active',
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
            $field = self::COL_MAP[$key] ?? null;
            if ($field === null || $vals === []) continue;
            $allowed = array_map('strval', $vals);
            $rows = array_values(array_filter($rows, static function (array $r) use ($field, $allowed): bool {
                $v = (string) ($r[$field] ?? '');
                return in_array($v, $allowed, true);
            }));
        }
        return $rows;
    }

    /** Terapkan kondisi Pencarian lanjutan (adv) pada baris hasil. @return list<array> */
    public static function applyAdv(array $rows, array $conds): array
    {
        if ($conds === []) return $rows;
        return array_values(array_filter($rows, static function (array $r) use ($conds): bool {
            foreach ($conds as $c) {
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
                if (! $ok) return false;
            }
            return true;
        }));
    }

    /** Nilai distinct per kolom untuk popup filter. @return list<string> */
    public function distinctValues(string $key, array $filters): array
    {
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
        return $this->query(
            'SELECT w.*,
                    (SELECT COUNT(*) FROM `groups` g WHERE g.warehouse_id = w.id) AS group_count,
                    (SELECT COUNT(*) FROM locations l
                       JOIN `groups` g2 ON g2.id = l.group_id
                      WHERE g2.warehouse_id = w.id) AS location_count,
                    (SELECT COUNT(*) FROM items i
                       JOIN `groups` g3 ON g3.id = i.group_id
                      WHERE g3.warehouse_id = w.id) AS item_count
               FROM warehouses w
              ORDER BY w.is_active DESC, w.name ASC'
        );
    }

    /**
     * Warehouses that actually contain groups visible to the active user.
     * Used to build the header switcher so a user only sees their own sites.
     *
     * @param list<int> $groupIds  groups the active user may access
     * @param bool      $allGroups whether the user may access every group
     * @return list<array>
     */
    public function forActiveUser(array $groupIds, bool $allGroups): array
    {
        if ($allGroups) {
            return $this->query('SELECT id, code, name, city FROM warehouses ORDER BY name');
        }

        if ($groupIds === []) {
            return [];
        }

        $placeholders = implode(', ', array_fill(0, count($groupIds), '?'));

        return $this->query(
            "SELECT DISTINCT w.id, w.code, w.name, w.city
               FROM warehouses w
               JOIN `groups` g ON g.warehouse_id = w.id
              WHERE g.id IN ({$placeholders})
              ORDER BY w.name",
            $groupIds
        );
    }

    /**
     * @return list<int> group ids that belong to the given warehouse
     */
    public function groupIds(int $warehouseId): array
    {
        $rows = $this->query('SELECT id FROM `groups` WHERE warehouse_id = ?', [$warehouseId]);

        return array_map(static fn (array $row): int => (int) $row['id'], $rows);
    }
}