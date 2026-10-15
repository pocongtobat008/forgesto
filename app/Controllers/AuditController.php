<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Request;
use App\Models\ActivityLog;
use App\Models\Audit;
use App\Models\AuditSubmission;
use App\Models\Discrepancy;
use App\Models\Item;
use App\Models\Notification;
use App\Models\PostingBatch;

class AuditController extends Controller
{
    use AuditPeriodActions;
    use BookPeriodActions;

    public function index(): string
    {
        $this->authorize('audit_data');

        $itemModel = new Item();
        $filters = $itemModel->normaliseFilters($_GET);
        $page = max(1, Request::int('page', 1));
        $perPage = list_per_page((int) config('app.per_page', 15));
        $minPercent = (int) config('app.audit.min_percent', 10);

        $queue = $itemModel->auditQueue($filters, $page, $perPage);
        $progress = $itemModel->groupProgress($filters);

        // Tiga tab Audit (permintaan plan.md):
        //  1) rekomendasi sampel minimal X% yang belum diaudit,
        //  2) semua item yang sudah diposting observer,
        //  3) item yang diminta Admin STO untuk dicek ulang (recheck).
        $currentPeriod = \App\Models\BookPeriod::current();
        $isReadOnly = $currentPeriod !== null && (($currentPeriod['status'] ?? 'open') === 'closed' || ! empty($currentPeriod['is_archive_view']));

        $tab = in_array(Request::string('tab', 'sample'), ['sample', 'posted', 'recheck'], true)
            ? Request::string('tab', 'sample')
            : 'sample';

        if ($isReadOnly && $currentPeriod !== null) {
            $periodId = (int) $currentPeriod['id'];
            $progress = $itemModel->groupProgressSnapshot($periodId, $filters);
            $db = \App\Core\Database::connection();
            $stmtPost = $db->prepare("SELECT s.*, s.item_code AS code, s.item_name AS name, g.name AS group_name,
                                             s.expected_qty, s.counted_qty, s.audited_qty, s.difference, s.count_status, s.audit_status
                                      FROM period_snapshots s
                                      JOIN `groups` g ON g.id = s.group_id
                                      WHERE s.period_id = ? AND s.count_status IN ('posted', 'submitted', 'audited', 'reconciled')
                                      ORDER BY g.name ASC, s.item_code ASC LIMIT 1000");
            $stmtPost->execute([$periodId]);
            $postRows = $stmtPost->fetchAll(\PDO::FETCH_ASSOC);

            $stmtRec = $db->prepare("SELECT s.*, s.item_code AS code, s.item_name AS name, g.name AS group_name,
                                            s.expected_qty, s.counted_qty, s.audited_qty, s.difference, s.count_status, s.audit_status
                                     FROM period_snapshots s
                                     JOIN `groups` g ON g.id = s.group_id
                                     WHERE s.period_id = ? AND s.count_status IN ('posted', 'submitted')
                                     ORDER BY g.name ASC, s.item_code ASC LIMIT 1000");
            $stmtRec->execute([$periodId]);
            $recRows = $stmtRec->fetchAll(\PDO::FETCH_ASSOC);

            $stmtRech = $db->prepare("SELECT s.*, s.item_code AS code, s.item_name AS name, g.name AS group_name,
                                             s.expected_qty, s.counted_qty, s.audited_qty, s.difference, s.count_status, s.audit_status
                                      FROM period_snapshots s
                                      JOIN `groups` g ON g.id = s.group_id
                                      WHERE s.period_id = ? AND (s.audit_status = 'recount' OR s.count_status = 'recount')
                                      ORDER BY g.name ASC, s.item_code ASC LIMIT 1000");
            $stmtRech->execute([$periodId]);
            $rechRows = $stmtRech->fetchAll(\PDO::FETCH_ASSOC);

            $postedAll = ['items' => $postRows, 'total' => count($postRows), 'page' => 1, 'per_page' => 1000, 'last_page' => 1];
            $recommendation = $recRows;
            $recheck = ['items' => $rechRows, 'total' => count($rechRows), 'page' => 1, 'per_page' => 1000, 'last_page' => 1];
            $queue = ['items' => [], 'total' => 0, 'page' => 1, 'per_page' => 15, 'last_page' => 1];
        } else {
            $recommendation = $itemModel->auditRecommendation($minPercent);
            $postedAll = $itemModel->postedAll($filters, $page, $perPage);
            $recheck = $itemModel->recheckItems($filters, $page, $perPage);
        }

        // Recommended sample size per group (at least minPercent%).
        $progress = array_map(static function (array $row) use ($minPercent): array {
            $row['needed'] = (int) $row['total'] > 0
                ? max(1, (int) ceil(((int) $row['total']) * $minPercent / 100))
                : 0;

            return $row;
        }, $progress);

        return $this->view('audit/index', [
            'title'         => __('Audit'),
            'queue'         => $queue,
            'progress'      => $progress,
            'filters'       => $filters,
            'groups'        => (new \App\Models\Group())->forActiveUser(),
            'minPercent'    => $minPercent,
            'history'       => $isReadOnly ? ['items' => [], 'total' => 0] : (new Audit())->listDetailed($filters, 1, 8),
            'period'  => $currentPeriod ?? (new \App\Models\AuditPeriod())->active(),
            'periods' => (new \App\Models\AuditPeriod())->query('SELECT id,audit_month,status FROM audit_periods ORDER BY id DESC LIMIT 12'),
            'tab'           => $tab,
            'recommendation'=> $recommendation,
            'postedAll'     => $postedAll,
            'recheck'       => $recheck,
            'inbox'         => $isReadOnly ? [] : (new AuditSubmission())->inboxForAuditor((int) Auth::id()),
            'isReadOnly'    => $isReadOnly,
            'currentPeriod' => $currentPeriod,
        ]);
    }

    /**
     * Auditor melaporkan hasil audit sebuah batch ke Admin STO.
     * Item dalam batch naik ke count_status = 'audited' (menunggu konfirmasi).
     */
    public function report(array $params): string
    {
        $this->authorize('audit_data');
        $this->verifyCsrf();
        $this->guardOpenPeriod('/audit');

        $submissionId = (int) ($params['id'] ?? 0);
        $subs = new AuditSubmission();
        $submission = $subs->first('SELECT * FROM audit_submissions WHERE id = ?', [$submissionId]);
        if ($submission === null) {
            $this->notFound('Kiriman audit tidak ditemukan.');
        }
        $this->ensureGroupAccess((int) $submission['group_id']);

        if (! in_array((string) $submission['status'], [AuditSubmission::STATUS_INBOX, AuditSubmission::STATUS_IN_PROGRESS], true)) {
            return $this->redirectWith('/audit', 'info', 'Batch ini sudah dilaporkan sebelumnya.');
        }

        $batchId = (int) $submission['batch_id'];
        $itemModel = new Item();
        $groupStats = $itemModel->statsForGroup((int) $submission['group_id']);
        $minPercent = (int) config('app.audit.min_percent', 10);
        $needed = (int) $groupStats['total'] > 0
            ? max(1, (int) ceil((int) $groupStats['total'] * $minPercent / 100))
            : 0;
        $auditedCount = (new Audit())->countWhere('group_id = ?', [(int) $submission['group_id']]);

        if ($auditedCount < $needed) {
            return $this->redirectWith(
                '/audit',
                'warning',
                "Belum memenuhi minimal {$minPercent}% ({$auditedCount}/{$needed} item). Selesaikan sampel dulu sebelum melapor."
            );
        }

        $audited = $itemModel->markBatchAudited($batchId);
        $itemModel->updateBatchStatus($batchId, PostingBatch::STATUS_AUDITED);

        $subs->updateById($submissionId, [
            'status'       => AuditSubmission::STATUS_REPORTED,
            'auditor_id'   => Auth::id(),
            'sample_count' => $auditedCount,
            'reported_at'  => date('Y-m-d H:i:s'),
        ]);

        // Auto-sync ke rekonsiliasi saat auditor melapor ke admin
        (new \App\Models\Reconciliation())->syncFromItems();

        (new Notification())->toRole(
            'adminsto',
            'Audit selesai — perlu konfirmasi',
            'Batch group ' . ($submission['group_id']) . " selesai diaudit ({$audited} item). Menunggu konfirmasi rekonsiliasi.",
            'info',
            '/reconciliation',
            (int) $submission['group_id']
        );
        (new ActivityLog())->record('audit_report', 'batch', $batchId, "Auditor melaporkan batch #{$batchId} ({$audited} item)", (int) $submission['group_id']);

        return $this->redirectWith('/audit', 'success', "Laporan terkirim. {$audited} item masuk rekonsiliasi untuk dikonfirmasi Admin STO.");
    }

    public function check(array $params): string
    {
        $this->authorize('audit_data');

        $item = (new Item())->findDetailed((int) $params['id']);
        if ($item === null) {
            $this->notFound('Data tidak ditemukan.');
        }
        $this->ensureGroupAccess($item['group_id']);

        return $this->view('audit/check', [
            'title'     => __('Pengecekan ') . $item['code'],
            'item'      => $item,
            'minPercent'=> (int) config('app.audit.min_percent', 10),
        ]);
    }

    public function submit(array $params): string
    {
        $this->authorize('audit_data');
        $this->verifyCsrf();
        $this->guardOpenPeriod("/audit");

        $id = (int) $params['id'];
        $itemModel = new Item();
        $item = $itemModel->findDetailed($id);
        if ($item === null) {
            $this->notFound('Data tidak ditemukan.');
        }
        $this->ensureGroupAccess($item['group_id']);

        if (! in_array((string) $item['count_status'], [Item::COUNT_SUBMITTED, Item::COUNT_POSTED], true)) {
            return $this->redirectWith('/audit', 'error', 'Data belum diposting oleh observer, belum bisa diaudit.');
        }

        $auditedQty = Request::int('audited_qty');
        $note = Request::string('note') ?: null;
        $expected = (int) $item['expected_qty'];
        $counted = (int) ($item['counted_qty'] ?? 0);
        $isMatch = $auditedQty === $counted;

        // Decide sample membership before recording the new audit.
        $groupStats = $itemModel->statsForGroup((int) $item['group_id']);
        $needed = $groupStats['total'] > 0
            ? max(1, (int) ceil($groupStats['total'] * (int) config('app.audit.min_percent', 10) / 100))
            : 0;
        $alreadyAudited = (new Audit())->countWhere('group_id = ?', [(int) $item['group_id']]);

        (new Audit())->insert([
            'item_id'     => $id,
            'group_id'    => (int) $item['group_id'],
            'auditor_id'  => Auth::id(),
            'expected_qty'=> $expected,
            'counted_qty' => $counted,
            'audited_qty' => $auditedQty,
            'result'      => $isMatch ? 'match' : 'discrepancy',
            'note'        => $note,
            'is_sample'   => $alreadyAudited < $needed ? 1 : 0,
            'audited_at'  => date('Y-m-d H:i:s'),
        ]);

        if ($isMatch) {
            $itemModel->updateById($id, ['audit_status' => Item::AUDIT_AUDITED]);
            (new Notification())->toRole(
                'adminsto',
                'Audit selesai',
                $item['code'] . ' (' . $item['name'] . ') dinyatakan sesuai.',
                'success',
                '/items/' . $id,
                (int) $item['group_id']
            );
            (new ActivityLog())->record('audit', 'item', $id, 'Audit sesuai ' . $item['code'], (int) $item['group_id']);

            return $this->redirectWith('/audit', 'success', 'Audit ' . $item['code'] . ' selesai: sesuai.');
        }

        // Discrepancy: auto-open the record so the observer can search for it.
        $itemModel->updateById($id, [
            'audit_status' => Item::AUDIT_DISCREPANCY,
            'is_locked'    => 0,
        ]);

        $discrepancy = new Discrepancy();
        $difference = $auditedQty - $expected;
        $discrepancy->insert([
            'item_id'      => $id,
            'group_id'     => (int) $item['group_id'],
            'expected_qty' => $expected,
            'counted_qty'  => $counted,
            'audited_qty'  => $auditedQty,
            'difference'   => $difference,
            'status'       => 'open',
            'note'         => $note ?? 'Selisih ditemukan saat audit.',
            'created_by'   => Auth::id(),
        ]);

        (new Notification())->toRole(
            'observer',
            'Selisih perlu dicari',
            $item['code'] . ' terdapat selisih hasil audit. Mohon dicari dan diperbarui.',
            'warning',
            '/discrepancies',
            (int) $item['group_id']
        );
        (new Notification())->toRole(
            'adminsto',
            'Selisih baru',
            $item['code'] . ' (' . $item['name'] . ') memiliki selisih ' . $difference . ' unit.',
            'danger',
            '/discrepancies',
            (int) $item['group_id']
        );
        (new ActivityLog())->record('audit', 'item', $id, 'Audit selisih ' . $item['code'] . ' (' . $difference . ')', (int) $item['group_id']);

        return $this->redirectWith('/audit', 'warning', 'Selisih ditemukan pada ' . $item['code'] . '. Observer telah dinotifikasi untuk mencari.');
    }

    /** Nilai distinct untuk popup filter kolom Riwayat Audit (JSON). */
    public function historyDistinct(array $params = []): never
    {
        $this->authorize('audit_data');
        $col = \App\Models\Audit::normaliseColFilters($_GET['col'] ?? null);
        $key = (string) ($_GET['key'] ?? '');
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode(['values' => (new \App\Models\Audit())->distinctColValues($key, $col)], JSON_UNESCAPED_UNICODE);
        exit;
    }

    public function history(): string
    {
        $this->authorize('audit_data');

        $filters = [
            'group_id' => Request::has('group_id') && Request::string('group_id') !== '' ? Request::int('group_id') : null,
            'result'   => in_array(Request::string('result'), ['match', 'discrepancy'], true) ? Request::string('result') : null,
            'col'      => \App\Models\Audit::normaliseColFilters($_GET['col'] ?? null),
        ];
        $page = max(1, Request::int('page', 1));

        return $this->view('audit/history', [
            'title'   => __('Riwayat Audit'),
            'audits'  => (new Audit())->listDetailed($filters, $page, list_per_page((int) config('app.per_page', 15))),
            'filters' => $filters,
            'groups'  => (new \App\Models\Group())->forActiveUser(),
        ]);
    }
}
/* I18N5-CTRL */
