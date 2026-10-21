<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * Informasi penting yang dipublikasikan admin dan otomatis menjadi notifikasi.
 */
class InfoMessage extends Model
{
    protected string $table = 'info_messages';

    protected array $fillable = ['title', 'body', 'audience_role', 'type', 'is_active', 'created_by'];

    /** @return list<array<string,mixed>> */
    public function listAll(): array
    {
        return $this->query(
            'SELECT i.*, u.name AS creator_name, u.username AS creator_username
             FROM info_messages i
             LEFT JOIN users u ON u.id = i.created_by
             ORDER BY i.is_active DESC, i.created_at DESC, i.id DESC'
        );
    }

    /**
     * Informasi aktif yang relevan untuk sebuah role (null = semua audiens).
     *
     * @return list<array<string,mixed>>
     */
    public function activeForUser(?string $role, int $limit = 3): array
    {
        $sql = 'SELECT title, body, type, created_at FROM info_messages WHERE is_active = 1';
        $bindings = [];
        if ($role !== null && $role !== '') {
            $sql .= ' AND (audience_role IS NULL OR audience_role = ?)';
            $bindings[] = $role;
        }
        $sql .= ' ORDER BY created_at DESC, id DESC LIMIT ' . max(1, $limit);

        return $this->query($sql, $bindings);
    }

    /** @return array{total:int,active:int,notified:int} */
    public function summary(): array
    {
        $row = $this->first(
            "SELECT COUNT(*) AS total, SUM(is_active = 1) AS active FROM info_messages"
        );

        $notified = (int) $this->scalar(
            "SELECT COUNT(*) FROM notifications WHERE link = 'informasi'"
        );

        return [
            'total'    => (int) ($row['total'] ?? 0),
            'active'   => (int) ($row['active'] ?? 0),
            'notified' => $notified,
        ];
    }
}
