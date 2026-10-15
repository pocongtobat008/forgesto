<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * Ringkasan satu group untuk halaman detail (read-only).
 */
class GroupDetail extends Model
{
    protected string $table = '`groups`';

    public function stats(int $groupId): array
    {
        $row = $this->first(
            'SELECT
                (SELECT COUNT(*) FROM items i WHERE i.group_id = ?) AS items,
                (SELECT COUNT(*) FROM items i WHERE i.group_id = ? AND i.count_status IN (\'counted\',\'posted\')) AS counted,
                (SELECT COUNT(*) FROM items i WHERE i.group_id = ? AND (i.count_status = \'pending\' OR i.count_status IS NULL)) AS pending,
                (SELECT COALESCE(SUM(i.expected_qty),0) FROM items i WHERE i.group_id = ?) AS stock,
                (SELECT COUNT(*) FROM locations l WHERE l.group_id = ?) AS locations,
                (SELECT COUNT(*) FROM notifications n WHERE n.group_id = ? AND n.title = \'Permintaan Cek Ulang Hitung Fisik\') AS recheck',
            [$groupId, $groupId, $groupId, $groupId, $groupId, $groupId]
        );

        return array_map('intval', $row ?? []);
    }

    /** @return list<array> */
    public function membersByRole(int $groupId, string $role): array
    {
        return $this->query(
            'SELECT u.id, u.name, u.username, u.phone
               FROM user_groups ug JOIN users u ON u.id = ug.user_id
              WHERE ug.group_id = ? AND u.role = ? AND u.is_active = 1
              ORDER BY u.name',
            [$groupId, $role]
        );
    }

    /** @return list<array> */
    public function tablets(int $groupId): array
    {
        return $this->query(
            'SELECT d.name, d.code, d.serial_number, l.name AS location_name
               FROM devices d JOIN locations l ON l.id = d.location_id
              WHERE l.group_id = ? AND d.type = \'tablet\' AND d.is_active = 1
              ORDER BY d.code',
            [$groupId]
        );
    }

    /** @return list<array> */
    public function pendingItems(int $groupId, int $limit = 50): array
    {
        return $this->query(
            'SELECT i.code, i.name, l.name AS location_name
               FROM items i LEFT JOIN locations l ON l.id = i.location_id
              WHERE i.group_id = ? AND (i.count_status = \'pending\' OR i.count_status IS NULL)
              ORDER BY i.code LIMIT ' . (int) $limit,
            [$groupId]
        );
    }
}
