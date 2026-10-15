<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * Batch posting: satu baris = satu aksi "Posting" oleh PIC observer untuk
 * sebuah group pada satu periode. Batch inilah yang dikirim ke auditor.
 */
class PostingBatch extends Model
{
    protected string $table = 'posting_batches';

    /** @var list<string> */
    protected array $fillable = [
        'group_id', 'period', 'posted_by', 'posted_at',
        'item_count', 'total_counted', 'status', 'note',
    ];

    public const STATUS_POSTED     = 'posted';
    public const STATUS_RECOUNT    = 'recount';
    public const STATUS_AUDITED    = 'audited';
    public const STATUS_RECONCILED = 'reconciled';

    public function openForGroup(int $groupId, string $period): ?array
    {
        return $this->first(
            "SELECT * FROM posting_batches
             WHERE group_id = ? AND period = ? AND status = 'posted'
             ORDER BY id DESC LIMIT 1",
            [$groupId, $period]
        );
    }

    public function listDetailed(int $limit = 50): array
    {
        return $this->query(
            "SELECT b.*, g.code AS group_code, g.name AS group_name,
                    u.name AS posted_by_name,
                    (SELECT COUNT(*) FROM items i WHERE i.batch_id = b.id) AS live_items,
                    (SELECT COUNT(*) FROM items i WHERE i.batch_id = b.id AND i.count_status = 'counted') AS recount_items
             FROM posting_batches b
             LEFT JOIN `groups` g ON g.id = b.group_id
             LEFT JOIN users u ON u.id = b.posted_by
             ORDER BY b.posted_at DESC
             LIMIT {$limit}"
        );
    }
}
