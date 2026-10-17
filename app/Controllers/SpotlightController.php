<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;

/**
 * Endpoint JSON untuk Spotlight (pencarian cepat Ctrl+K / tombol cari di topbar).
 *
 * Mengembalikan group dan item yang cocok dengan kata kunci, dibatasi pada
 * gudang aktif (kecuali user memilih "Semua Gudang").
 */
class SpotlightController extends Controller
{
    public function index(array $params = []): string
    {
        if (! Auth::check()) {
            return $this->json(['groups' => [], 'items' => []], 401);
        }

        $q = trim((string) ($_GET['q'] ?? ''));

        if (mb_strlen($q) < 2) {
            return $this->json(['groups' => [], 'items' => []]);
        }

        $like = '%' . $q . '%';
        $pdo  = Database::connection();
        $whId = Auth::warehouseId();

        /* ---------- Group ---------- */
        $sqlG  = 'SELECT id, code, name FROM `groups` WHERE is_active = 1 AND (name LIKE ? OR code LIKE ?)';
        $argsG = [$like, $like];

        if ($whId !== null) {
            $sqlG .= ' AND warehouse_id = ?';
            $argsG[] = $whId;
        }

        $sqlG .= ' ORDER BY name LIMIT 6';

        $stmt = $pdo->prepare($sqlG);
        $stmt->execute($argsG);

        $groups = [];
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $groups[] = [
                'id'   => (int) $row['id'],
                'code' => (string) $row['code'],
                'name' => (string) $row['name'],
            ];
        }

        /* ---------- Item ---------- */
        $sqlI  = 'SELECT i.id, i.code, i.name, i.count_status, g.id AS group_id, g.code AS group_code, g.name AS group_name'
            . ' FROM items i INNER JOIN `groups` g ON g.id = i.group_id'
            . ' WHERE (i.name LIKE ? OR i.code LIKE ?)';
        $argsI = [$like, $like];

        if ($whId !== null) {
            $sqlI .= ' AND g.warehouse_id = ?';
            $argsI[] = $whId;
        }

        $sqlI .= ' ORDER BY i.name LIMIT 8';

        $stmt = $pdo->prepare($sqlI);
        $stmt->execute($argsI);

        $items = [];
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $items[] = [
                'id'     => (int) $row['id'],
                'code'   => (string) $row['code'],
                'name'   => (string) $row['name'],
                'group'  => (string) $row['group_name'],
                'gid'    => (int) $row['group_id'],
                'status' => (string) $row['count_status'],
            ];
        }

        return $this->json(['q' => $q, 'groups' => $groups, 'items' => $items]);
    }
}
