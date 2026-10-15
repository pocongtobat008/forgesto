<?php

namespace App\Models;

use App\Core\Model;

/**
 * ItemHistory Model
 * Audit trail for item status changes
 */
class ItemHistory extends Model
{
    protected static $table = 'item_histories';
    
    protected static $fillable = [
        'item_id',
        'action',
        'from_status',
        'to_status',
        'user_id',
        'notes',
        'metadata'
    ];
    
    // Action constants
    public const ACTION_COUNTED = 'counted';
    public const ACTION_POSTED = 'posted';
    public const ACTION_RECOUNT = 'recount';
    public const ACTION_RESET = 'reset';
    public const ACTION_UNCOUNTED = 'uncounted';
    
    /**
     * Log item status change
     * 
     * @param int $itemId
     * @param string $action
     * @param string|null $fromStatus
     * @param string|null $toStatus
     * @param int|null $userId
     * @param array|null $metadata
     * @param string|null $notes
     * @return bool
     */
    public static function logChange(
        int $itemId,
        string $action,
        ?string $fromStatus = null,
        ?string $toStatus = null,
        ?int $userId = null,
        ?array $metadata = null,
        ?string $notes = null
    ): bool {
        return static::create([
            'item_id' => $itemId,
            'action' => $action,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'user_id' => $userId ?? (auth()->id() ?? null),
            'notes' => $notes,
            'metadata' => $metadata ? json_encode($metadata) : null
        ]);
    }
    
    /**
     * Get history for specific item
     * 
     * @param int $itemId
     * @param int $limit
     * @return array
     */
    public static function getItemHistory(int $itemId, int $limit = 50): array
    {
        $sql = "
            SELECT 
                h.*,
                u.name as user_name,
                i.code as item_code,
                i.name as item_name
            FROM item_histories h
            LEFT JOIN users u ON h.user_id = u.id
            LEFT JOIN items i ON h.item_id = i.id
            WHERE h.item_id = ?
            ORDER BY h.created_at DESC
            LIMIT ?
        ";
        
        return static::query($sql, [$itemId, $limit]);
    }
    
    /**
     * Get recent changes (for dashboard/monitoring)
     * 
     * @param int $limit
     * @return array
     */
    public static function getRecentChanges(int $limit = 100): array
    {
        $sql = "
            SELECT 
                h.*,
                u.name as user_name,
                i.code as item_code,
                i.name as item_name,
                g.name as group_name
            FROM item_histories h
            LEFT JOIN users u ON h.user_id = u.id
            LEFT JOIN items i ON h.item_id = i.id
            LEFT JOIN groups g ON i.group_id = g.id
            ORDER BY h.created_at DESC
            LIMIT ?
        ";
        
        return static::query($sql, [$limit]);
    }
    
    /**
     * Get statistics by action type
     * 
     * @param string|null $dateFrom
     * @param string|null $dateTo
     * @return array
     */
    public static function getStatsByAction(?string $dateFrom = null, ?string $dateTo = null): array
    {
        $where = '1=1';
        $params = [];
        
        if ($dateFrom) {
            $where .= ' AND created_at >= ?';
            $params[] = $dateFrom;
        }
        
        if ($dateTo) {
            $where .= ' AND created_at <= ?';
            $params[] = $dateTo;
        }
        
        $sql = "
            SELECT 
                action,
                COUNT(*) as total,
                COUNT(DISTINCT item_id) as unique_items,
                COUNT(DISTINCT user_id) as unique_users
            FROM item_histories
            WHERE {$where}
            GROUP BY action
            ORDER BY total DESC
        ";
        
        return static::query($sql, $params);
    }
}
