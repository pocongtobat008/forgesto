<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Auth;
use App\Core\Model;

class CountHistory extends Model
{
    protected string $table = 'count_history';

    protected array $fillable = [
        'item_id', 'recon_id', 'field_name', 'old_value', 'new_value',
        'delta', 'reason', 'action', 'changed_by', 'changed_at',
    ];

    /**
     * Record a historical event for item / reconciliation.
     */
    public function record(
        int $itemId,
        ?int $reconId,
        string $fieldName,
        string|int|float|null $oldVal,
        string|int|float|null $newVal,
        ?string $reason = null,
        string $action = 'update',
        ?int $userId = null
    ): int {
        $oldStr = $oldVal === null ? null : (string) $oldVal;
        $newStr = $newVal === null ? null : (string) $newVal;

        $delta = null;
        if (is_numeric($oldVal) && is_numeric($newVal)) {
            $delta = (int) $newVal - (int) $oldVal;
        }

        return $this->insert([
            'item_id'    => $itemId,
            'recon_id'   => $reconId,
            'field_name' => $fieldName,
            'old_value'  => $oldStr,
            'new_value'  => $newStr,
            'delta'      => $delta,
            'reason'     => $reason,
            'action'     => $action,
            'changed_by' => $userId ?? Auth::id(),
            'changed_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * History timeline for a reconciliation ID.
     */
    public function timelineForRecon(int $reconId): array
    {
        return $this->query(
            'SELECT ch.*, u.name AS user_name, u.role AS user_role, u.username
             FROM count_history ch
             LEFT JOIN users u ON u.id = ch.changed_by
             WHERE ch.recon_id = ?
             ORDER BY ch.changed_at DESC, ch.id DESC',
            [$reconId]
        );
    }

    /**
     * History timeline for an item ID.
     */
    public function timelineForItem(int $itemId): array
    {
        return $this->query(
            'SELECT ch.*, u.name AS user_name, u.role AS user_role, u.username
             FROM count_history ch
             LEFT JOIN users u ON u.id = ch.changed_by
             WHERE ch.item_id = ?
             ORDER BY ch.changed_at DESC, ch.id DESC',
            [$itemId]
        );
    }
}
