<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Auth;
use App\Core\Model;

class ExportLog extends Model
{
    protected string $table = 'export_logs';

    protected array $fillable = [
        'user_id', 'group_id', 'type', 'filename', 'stored_path', 'total_rows',
        'success_rows', 'failed_rows', 'progress', 'status', 'message', 'finished_at',
    ];

    public static function storageDir(): string
    {
        return dirname(__DIR__, 2) . '/storage/exports';
    }

    public function originalPath(array $log): ?string
    {
        $name = (string) ($log['stored_path'] ?? '');
        if ($name === '' || str_contains($name, '/') || str_contains($name, '..')) {
            return null;
        }
        $full = self::storageDir() . '/' . $name;

        return is_file($full) ? $full : null;
    }

    /** Kolom filter Riwayat Export (whitelist). Kunci => kolom baris hasil recent(). */
    public const COL_MAP = [
        'file'   => 'filename',
        'group'  => 'group_code',
        'lines'  => 'total_rows',
        'status' => 'status',
        'user'   => 'user_name',
        'time'   => 'created_date',
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

    /** Terapkan filter kolom pada baris (data kecil, paginasi di PHP). */
    public static function applyColFilters(array $rows, array $col): array
    {
        foreach ($col as $key => $vals) {
            $field = self::COL_MAP[$key] ?? null;
            if ($field === null || $vals === []) continue;
            $allowed = array_map('strval', $vals);
            $rows = array_values(array_filter($rows, static function (array $r) use ($field, $allowed): bool {
                $v = (string) ($r[$field] ?? '');
                if ($field === 'created_date') $v = substr((string) ($r['created_at'] ?? ''), 0, 10);
                if ($field === 'group_code' && $v === '') $v = 'Semua';
                return in_array($v, $allowed, true);
            }));
        }
        return $rows;
    }

    /** Terapkan kondisi Pencarian lanjutan (adv) pada baris (data kecil). @return list<array> */
    public static function applyAdv(array $rows, array $conds): array
    {
        if ($conds === []) return $rows;
        return array_values(array_filter($rows, static function (array $r) use ($conds): bool {
            foreach ($conds as $c) {
                $field = self::COL_MAP[$c['col']] ?? null;
                if ($field === null) continue;
                $val = (string) ($r[$field] ?? '');
                if ($field === 'group_code' && $val === '') $val = 'Semua';
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

    /** Nilai distinct per kolom untuk popup. @return list<string> */
    public function distinctValues(string $key, array $col): array
    {
        $field = self::COL_MAP[$key] ?? null;
        if ($field === null) return [];
        unset($col[$key]);
        $rows = self::applyColFilters($this->recent(500), $col);
        $out = [];
        foreach ($rows as $r) {
            $v = (string) ($r[$field] ?? '');
            if ($field === 'created_date') $v = substr((string) ($r['created_at'] ?? ''), 0, 10);
            if ($field === 'group_code' && $v === '') $v = 'Semua';
            if ($v === '') continue;
            $out[$v] = true;
        }
        $vals = array_keys($out);
        sort($vals, SORT_NATURAL | SORT_FLAG_CASE);
        return array_map('strval', array_slice($vals, 0, 500));
    }

    public function recent(int $limit = 10): array
    {
        [$scope, $bindings] = Auth::groupScope('e.group_id');
        // Log tanpa grup (group_id NULL, mis. export lintas-grup) hanya untuk yang boleh lihat semua grup.
        if (Auth::allGroups() && $scope !== '') {
            $scope = '(' . $scope . ' OR e.group_id IS NULL)';
        } elseif (Auth::allGroups()) {
            $scope = '';
        }

        $sql = 'SELECT e.*, DATE(e.created_at) AS created_date, u.name AS user_name, g.code AS group_code
                FROM export_logs e
                LEFT JOIN users u ON u.id = e.user_id
                LEFT JOIN `groups` g ON g.id = e.group_id';

        if ($scope !== '') {
            $sql .= ' WHERE ' . $scope;
        }

        $sql .= ' ORDER BY e.created_at DESC LIMIT ' . max(1, $limit);

        return $this->query($sql, $bindings);
    }
}
