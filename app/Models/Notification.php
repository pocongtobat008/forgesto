<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Auth;
use App\Core\Model;

class Notification extends Model
{
    protected string $table = 'notifications';

    protected array $fillable = [
        'user_id', 'role', 'group_id', 'title', 'message', 'type', 'link', 'is_read',
    ];

    /**
     * SQL fragment selecting notifications addressed to the active user:
     *   - directly to the user, OR
     *   - broadcast to the user's role, OR
     *   - broadcast to everyone.
     * Group-specific broadcasts are limited to the user's groups.
     *
     * @return array{0:string,1:list<mixed>}
     */
    private function audienceClause(): array
    {
        $userId = Auth::id();
        $role = Auth::role();

        $clauses = [];
        $bindings = [];

        $clauses[] = 'n.user_id = ?';
        $bindings[] = $userId;

        if ($role !== null) {
            $clauses[] = '(n.user_id IS NULL AND n.role = ?)';
            $bindings[] = $role;
        }

        $clauses[] = '(n.user_id IS NULL AND n.role IS NULL)';

        $audience = '(' . implode(' OR ', $clauses) . ')';

        $groupIds = Auth::groupIds();
        if (Auth::allGroups()) {
            return [$audience, $bindings];
        }

        if ($groupIds === []) {
            return ['(' . $audience . ' AND n.group_id IS NULL)', $bindings];
        }

        $placeholders = implode(', ', array_fill(0, count($groupIds), '?'));
        $bindings = array_merge($bindings, $groupIds);

        return ['(' . $audience . " AND (n.group_id IS NULL OR n.group_id IN ({$placeholders})))", $bindings];
    }

    public function forActiveUser(int $limit = 20, bool $onlyUnread = false): array
    {
        [$audience, $bindings] = $this->audienceClause();

        $sql = 'SELECT n.*, g.code AS group_code
                FROM notifications n
                LEFT JOIN `groups` g ON g.id = n.group_id
                WHERE ' . $audience;

        if ($onlyUnread) {
            $sql .= ' AND n.is_read = 0';
        }

        $sql .= ' ORDER BY n.created_at DESC LIMIT ' . max(1, $limit);

        return $this->query($sql, $bindings);
    }

    public function unreadCount(): int
    {
        [$audience, $bindings] = $this->audienceClause();

        return (int) $this->scalar(
            'SELECT COUNT(*) FROM notifications n WHERE ' . $audience . ' AND n.is_read = 0',
            $bindings
        );
    }

    public function markRead(int $id): void
    {
        $this->updateById($id, ['is_read' => 1]);
    }

    public function markAllRead(): void
    {
        [$audience, $bindings] = $this->audienceClause();
        $this->run('UPDATE notifications n SET n.is_read = 1 WHERE ' . $audience, $bindings);
    }

    /**
     * Push a notification to a single user.
     */
    public function toUser(int $userId, string $title, string $message, string $type = 'info', ?string $link = null): int
    {
        return $this->insert([
            'user_id' => $userId,
            'title'   => $title,
            'message' => $message,
            'type'    => $type,
            'link'    => $link,
        ]);
    }

    /**
     * Push a notification to every user of a role (optionally within a group).
     */
    public function toRole(string $role, string $title, string $message, string $type = 'info', ?string $link = null, ?int $groupId = null): int
    {
        return $this->insert([
            'role'     => $role,
            'group_id' => $groupId,
            'title'    => $title,
            'message'  => $message,
            'type'     => $type,
            'link'     => $link,
        ]);
    }

    public function broadcast(string $title, string $message, string $type = 'info', ?string $link = null): int
    {
        return $this->insert([
            'title'   => $title,
            'message' => $message,
            'type'    => $type,
            'link'    => $link,
        ]);
    }
}
