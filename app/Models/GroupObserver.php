<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * Observer penanggung jawab (PIC) per group.
 *
 * Satu group boleh punya banyak observer (user_groups), tetapi hanya PIC
 * yang berhak melakukan posting batch untuk group tersebut.
 */
class GroupObserver extends Model
{
    protected string $table = 'group_observers';

    /** @var list<string> */
    protected array $fillable = ['group_id', 'user_id', 'is_pic'];

    /** Daftar group_id yang di-PIC-kan oleh user ini. */
    public function groupIdsForPic(int $userId): array
    {
        $rows = $this->query(
            'SELECT group_id FROM group_observers WHERE user_id = ? AND is_pic = 1',
            [$userId]
        );

        return array_map(static fn ($r) => (int) $r['group_id'], $rows);
    }

    /** Apakah user ini PIC dari group tersebut? */
    public function isPic(int $userId, int $groupId): bool
    {
        return (int) ($this->scalar(
            'SELECT COUNT(*) FROM group_observers WHERE user_id = ? AND group_id = ? AND is_pic = 1',
            [$userId, $groupId]
        ) ?? 0) > 0;
    }

    /** PIC aktif untuk sebuah group (username + nama). */
    public function picFor(int $groupId): ?array
    {
        return $this->first(
            'SELECT u.id, u.username, u.name, u.role
             FROM group_observers go
             JOIN users u ON u.id = go.user_id
             WHERE go.group_id = ? AND go.is_pic = 1 AND u.is_active = 1
             ORDER BY u.name
             LIMIT 1',
            [$groupId]
        );
    }

    /**
     * Peta group_id => PIC untuk sekumpulan group.
     *
     * @param  list<int> $groupIds
     * @return array<int, array{id:int,username:string,name:string}>
     */
    public function picMap(array $groupIds): array
    {
        if ($groupIds === []) {
            return [];
        }
        $in = implode(',', array_fill(0, count($groupIds), '?'));
        $rows = $this->query(
            "SELECT go.group_id, u.id, u.username, u.name
             FROM group_observers go
             JOIN users u ON u.id = go.user_id
             WHERE go.is_pic = 1 AND u.is_active = 1 AND go.group_id IN ($in)
             ORDER BY u.name",
            array_map('intval', $groupIds)
        );

        $map = [];
        foreach ($rows as $r) {
            $map[(int) $r['group_id']] = [
                'id'       => (int) $r['id'],
                'username' => (string) $r['username'],
                'name'     => (string) $r['name'],
            ];
        }

        return $map;
    }

    /** Set PIC tunggal untuk group (ganti PIC lama). */
    public function setPic(int $groupId, int $userId): void
    {
        $this->run('UPDATE group_observers SET is_pic = 0 WHERE group_id = ?', [$groupId]);
        $existing = (int) ($this->scalar(
            'SELECT COUNT(*) FROM group_observers WHERE group_id = ? AND user_id = ?',
            [$groupId, $userId]
        ) ?? 0);
        if ($existing > 0) {
            $this->run(
                'UPDATE group_observers SET is_pic = 1 WHERE group_id = ? AND user_id = ?',
                [$groupId, $userId]
            );
        } else {
            $this->run(
                'INSERT INTO group_observers (group_id, user_id, is_pic) VALUES (?, ?, 1)',
                [$groupId, $userId]
            );
        }
    }
}
