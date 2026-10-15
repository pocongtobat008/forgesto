<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * Kiriman audit: auditor menerima batch dari observer, melakukan pengecekan
 * (minimal 10% per group), lalu melapor ke Admin STO.
 *
 * Alur status: inbox -> in_progress -> reported -> confirmed
 */
class AuditSubmission extends Model
{
    protected string $table = 'audit_submissions';

    /** @var list<string> */
    protected array $fillable = [
        'batch_id', 'group_id', 'observer_id', 'auditor_id',
        'item_count', 'sample_count', 'recount_count', 'status',
        'reported_at', 'confirmed_at', 'confirmed_by', 'note',
    ];

    public const STATUS_INBOX       = 'inbox';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_REPORTED    = 'reported';
    public const STATUS_CONFIRMED   = 'confirmed';

    /** Inbox auditor: batch yang sudah dikirim tapi belum dilaporkan. */
    public function inboxForAuditor(int $auditorId): array
    {
        return $this->query(
            "SELECT s.*, g.code AS group_code, g.name AS group_name,
                    u.name AS observer_name, u.username AS observer_username,
                    b.item_count AS batch_items, b.posted_at,
                    (SELECT COUNT(*) FROM items i WHERE i.batch_id = s.batch_id AND i.audit_status = 'audited') AS audited_items,
                    (SELECT COUNT(*) FROM audits a WHERE a.group_id = s.group_id) AS audit_rows
             FROM audit_submissions s
             LEFT JOIN `groups` g ON g.id = s.group_id
             LEFT JOIN users u ON u.id = s.observer_id
             LEFT JOIN posting_batches b ON b.id = s.batch_id
             WHERE s.auditor_id = ? AND s.status IN ('inbox','in_progress')
             ORDER BY s.created_at DESC",
            [$auditorId]
        );
    }

    /** Semua kiriman yang sudah dilaporkan auditor ke admin (menunggu konfirmasi). */
    public function reportedList(): array
    {
        return $this->query(
            "SELECT s.*, g.code AS group_code, g.name AS group_name,
                    ua.name AS auditor_name, uo.name AS observer_name
             FROM audit_submissions s
             LEFT JOIN `groups` g ON g.id = s.group_id
             LEFT JOIN users ua ON ua.id = s.auditor_id
             LEFT JOIN users uo ON uo.id = s.observer_id
             WHERE s.status = 'reported'
             ORDER BY s.reported_at DESC"
        );
    }
}
