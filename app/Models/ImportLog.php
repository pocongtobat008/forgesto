<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Auth;
use App\Core\Model;

class ImportLog extends Model
{
    protected string $table = 'import_logs';

    protected array $fillable = [
        'user_id', 'group_id', 'type', 'filename', 'total_rows',
        'success_rows', 'failed_rows', 'status', 'message',
        'progress', 'stored_path', 'finished_at', 'group_name',
    ];

    /** Direktori penyimpanan file import (di luar public/). */
    public static function storageDir(): string
    {
        return dirname(__DIR__, 2) . '/storage/imports';
    }

    /**
     * Salin file upload ke storage. Nama file dibuat dari id + sanitasi, tidak memakai nama asli
     * sebagai path (mencegah path traversal). Mengembalikan nama relatif atau null bila gagal.
     */
    public static function storeOriginal(int $logId, string $originalName, string $tmpPath): ?string
    {
        $dir = self::storageDir();
        if (! is_dir($dir) && ! @mkdir($dir, 0775, true)) {
            return null;
        }
        $ext  = strtolower(preg_replace('/[^a-z0-9]/i', '', pathinfo($originalName, PATHINFO_EXTENSION)) ?: 'bin');
        $name = $logId . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
        if (! @copy($tmpPath, $dir . '/' . $name)) {
            return null;
        }

        return $name;
    }

    /** Path absolut bila file tersimpan dan berada di dalam storage/imports. Null bila tidak. */
    public function originalPath(array $log): ?string
    {
        $name = (string) ($log['stored_path'] ?? '');
        if ($name === '' || str_contains($name, '/') || str_contains($name, '..')) {
            return null;
        }
        $full = self::storageDir() . '/' . $name;

        return is_file($full) ? $full : null;
    }

    public function recent(int $limit = 10): array
    {
        [$scope, $bindings] = Auth::groupScope('i.group_id');
        // Log tanpa grup (group_id NULL, mis. export lintas-grup) hanya untuk yang boleh lihat semua grup.
        if (Auth::allGroups() && $scope !== '') {
            $scope = '(' . $scope . ' OR i.group_id IS NULL)';
        } elseif (Auth::allGroups()) {
            $scope = '';
        }

        $sql = 'SELECT i.*, u.name AS user_name, g.code AS group_code
                FROM import_logs i
                LEFT JOIN users u ON u.id = i.user_id
                LEFT JOIN `groups` g ON g.id = i.group_id';

        if ($scope !== '') {
            $sql .= ' WHERE ' . $scope;
        }

        $sql .= ' ORDER BY i.created_at DESC LIMIT ' . max(1, $limit);

        return $this->query($sql, $bindings);
    }
}
