<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Models\BookPeriod;
use App\Services\PeriodResetService;
use App\Services\PeriodDeleteService;

/**
 * Menu Tahun Buku: pengaturan periode stok opname, status close buku,
 * dan laporan selisih per group (dari snapshot periode yang sudah ditutup).
 * Aksi tulis (tutup, buka baru, periode pertama) tetap lewat endpoint /audit/period/*.
 */
class BookYearController extends Controller
{
    /** POST /tahun-buku/{id}/buka : Buka tahun buku untuk dilihat di menu yang sudah ada (read-only jika closed). */
    public function openPeriod(array $params): never
    {
        $this->authorize('manage_data');
        $this->verifyCsrf();

        $id = (int) ($params['id'] ?? 0);
        $m  = new BookPeriod();
        $p  = $m->find($id);
        if ($p === null) {
            $this->redirectWith('/tahun-buku', 'error', 'Periode tidak ditemukan.');
        }

        BookPeriod::setViewPeriod($id);
        $isClosed = ($p['status'] ?? 'open') === 'closed';
        $msg = $isClosed
            ? "Membuka Tahun Buku {$p['audit_month']} (Mode Read-Only). Gunakan menu Data STO, Audit, dan Rekonsiliasi untuk meninjau data."
            : "Membuka Tahun Buku {$p['audit_month']} (Periode Berjalan).";

        $this->redirectWith('/items', 'success', $msg);
    }

    /** GET|POST /tahun-buku/kembali : Kembali ke tahun buku aktif berjalan. */
    public function resetView(): never
    {
        BookPeriod::clearViewPeriod();
        $this->redirectWith('/items', 'info', 'Kembali ke Tahun Buku Berjalan.');
    }

    /** GET /tahun-buku */
    public function index(): string
    {
        $this->authorize('manage_data');

        $m = new BookPeriod();
        $active = $m->active();
        $periods = $m->query(
            'SELECT id, audit_month, audit_start, audit_end, note, status, is_active, closed_at, reopened_at
               FROM audit_periods ORDER BY id DESC LIMIT 24'
        );
        $lastClosed = $m->first(
            "SELECT * FROM audit_periods WHERE status = 'closed' ORDER BY closed_at DESC, id DESC LIMIT 1"
        );

        return $this->view('book/index', [
            'title'      => __('Tahun Buku'),
            'active'     => $active,
            'periods'    => $periods,
            'lastClosed' => $lastClosed,
            'lastRows'   => $lastClosed !== null ? $this->groupDiff((int) $lastClosed['id']) : [],
            'canAdmin'   => Auth::can('manage_data'),
            'deletable'  => array_values(array_filter($periods, static fn (array $p): bool => PeriodDeleteService::isDeletable($p))),
            'resetPreview' => ($active !== null && ($active['status'] ?? 'open') !== 'closed' && Auth::can('manage_data'))
                ? (new PeriodResetService())->preview($active) : null,
        ]);
    }

    /** GET /tahun-buku/{id} : selisih per group untuk satu periode (read-only). */
    public function detail(array $params): string
    {
        $this->authorize('manage_data');

        $id = (int) ($params['id'] ?? 0);
        $period = (new BookPeriod())->find($id);
        if ($period === null) {
            $this->notFound('Periode tidak ditemukan.');
        }

        return $this->view('book/detail', [
            'title'  => __('Tahun Buku ') . (string) $period['audit_month'],
            'period' => $period,
            'rows'   => $this->groupDiff($id),
        ]);
    }

    /** POST /tahun-buku/reset : reset hitungan periode berjalan (dua langkah + cadangan). */
    public function resetPeriod(): never
    {
        $this->authorize('manage_data');
        $this->verifyCsrf();

        $active = (new BookPeriod())->active();
        if ($active === null) {
            $this->redirectWith('/tahun-buku', 'error', 'Belum ada periode aktif.');
        }

        try {
            $r = (new PeriodResetService())->execute(
                $active,
                (string) \App\Core\Request::string('confirm_code'),
                (int) Auth::id()
            );
        } catch (\RuntimeException $e) {
            $this->redirectWith('/tahun-buku', 'error', $e->getMessage());
        }

        $this->redirectWith('/tahun-buku', 'success',
            "Reset {$active['audit_month']} selesai: {$r['items']} item dikembalikan. Cadangan: {$r['backup']}");
    }

    /** GET /tahun-buku/hapus/{id} : langkah 1 (pratinjau) + langkah 3 (form ketik kode). */
    public function deleteConfirm(array $params): string
    {
        $this->authorize('manage_data');
        $svc = new PeriodDeleteService();
        try {
            $pv = $svc->preview((int) ($params['id'] ?? 0));
        } catch (\RuntimeException $e) {
            $this->redirectWith('/tahun-buku', 'error', $e->getMessage());
        }
        return $this->view('book/_delete_confirm', [
            'title'  => __('Hapus periode'),
            'p'      => $pv['period'],
            'pv'     => $pv,
        ]);
    }

    /** POST /tahun-buku/hapus/{id} : langkah 2 (cadangan) + langkah 3 (transaksi). */
    public function deleteExecute(array $params): never
    {
        $this->authorize('manage_data');
        $this->verifyCsrf();
        try {
            $r = (new PeriodDeleteService())->execute(
                (int) ($params['id'] ?? 0),
                (string) \App\Core\Request::string('confirm_code'),
                (int) Auth::id()
            );
        } catch (\Throwable $e) {
            // Kembali ke halaman konfirmasi agar pesan galat terlihat di tempat,
            // bukan dilempar ke daftar Tahun Buku tanpa konteks.
            error_log('[book.delete_period] ' . $e->getMessage());
            $this->redirectWith('/tahun-buku/hapus/' . (int) ($params['id'] ?? 0), 'error', $e->getMessage());
        }
        $this->redirectWith('/tahun-buku', 'success',
            "Periode {$r['period']} dihapus ({$r['snapshots']} snapshot). Cadangan: {$r['backup']}");
    }

    /** Ringkasan selisih per group dari period_snapshots. */

    /**
     * GET /tahun-buku/{id}/worksheet : lembar kerja periode lama (read-only).
     * Tiga tab: Data STO, Audit, Rekonsiliasi — semuanya dari period_snapshots.
     */
    public function worksheet(array $params): never
    {
        $id = (int) ($params['id'] ?? 0);
        BookPeriod::setViewPeriod($id);
        $this->redirectWith('/items', 'info', 'Membuka Tahun Buku dalam mode read-only.');
    }

    /** GET /tahun-buku/{id}/export : export Excel selisih snapshot periode yang sudah ditutup. */
    public function exportSnapshot(array $params): void
    {
        $this->authorize('export_data');

        $id = (int) ($params['id'] ?? 0);
        $period = (new BookPeriod())->find($id);
        if ($period === null) {
            $this->notFound('Periode tidak ditemukan.');
        }
        if (($period['status'] ?? '') !== 'closed') {
            $this->redirectWith('/tahun-buku/' . $id, 'warning', 'Export hanya tersedia untuk periode yang sudah ditutup.');
        }

        $snaps = (new BookPeriod())->query(
            "SELECT s.item_id, s.group_id, s.item_code, s.item_name, s.unit_price,
                    s.expected_qty, s.counted_qty, s.difference, s.count_status, s.audit_status,
                    g.code AS group_code, g.name AS group_name,
                    s.expected_qty * s.unit_price AS stock_amount,
                    COALESCE(s.counted_qty, 0) * s.unit_price AS count_amount,
                    COALESCE(s.difference, 0) * s.unit_price AS diff_amount
               FROM period_snapshots s
               LEFT JOIN `groups` g ON g.id = s.group_id
              WHERE s.period_id = ?
              ORDER BY s.group_id, s.item_code",
            [$id]
        );

        $rows = [];
        foreach ($snaps as $r) {
            $diff = (int) ($r['difference'] ?? 0);
            $rows[] = [
                'id'              => (int) $r['item_id'],
                'period'          => (string) $period['audit_month'],
                'direction'       => $diff > 0 ? 'plus' : ($diff < 0 ? 'minus' : 'match'),
                'group_code'      => (string) ($r['group_code'] ?? ''),
                'group_name'      => (string) ($r['group_name'] ?? ''),
                'location_name'   => '',
                'item_code'       => (string) $r['item_code'],
                'item_name'       => (string) $r['item_name'],
                'category'        => '',
                'unit'            => '',
                'stock_qty'       => (int) $r['expected_qty'],
                'counted_qty'     => $r['counted_qty'] === null ? '' : (int) $r['counted_qty'],
                'difference'      => $diff,
                'unit_price'      => (float) $r['unit_price'],
                'stock_amount'    => (float) $r['stock_amount'],
                'count_amount'    => (float) $r['count_amount'],
                'diff_amount'     => (float) $r['diff_amount'],
                'status'          => (string) $r['audit_status'],
                'recheck_requested' => 0,
                'submitted_by_name' => '',
                'submitted_at'    => '',
                'verified_by_name'=> '',
                'verified_at'     => '',
                'verify_note'     => '',
                'group_observers' => '',
                'group_auditors'  => '',
            ];
        }

        $logModel = new \App\Models\ExportLog();
        $logId = $logModel->insert([
            'user_id'    => Auth::id(),
            'group_id'   => null,
            'type'       => 'book_snapshot',
            'filename'   => 'tahun_buku_' . $period['audit_month'] . '_' . date('Ymd_His') . '.xlsx',
            'total_rows' => count($rows),
            'progress'   => 10,
            'status'     => 'processing',
        ]);
        $fileName = 'tahun_buku_' . $period['audit_month'] . '_' . date('Ymd_His') . '_' . $logId . '.xlsx';
        $outPath  = \App\Models\ExportLog::storageDir() . '/' . $fileName;

        $payload = json_encode([
            'out'  => $outPath,
            'rows' => $rows,
            'cols' => [],
            'meta' => [
                'Sumber'        => 'Tahun Buku (snapshot tutup buku)',
                'Periode'       => (string) $period['audit_month'],
                'Dibuat oleh'   => (string) (Auth::user()['name'] ?? ''),
                'Waktu export'  => date('Y-m-d H:i:s'),
            ],
        ], JSON_UNESCAPED_UNICODE);

        $script = dirname(__DIR__, 2) . '/scripts/export_recon_xlsx.py';
        $proc = proc_open(['python3', $script], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        $stdout = '';
        if (is_resource($proc)) {
            fwrite($pipes[0], (string) $payload);
            fclose($pipes[0]);
            $stdout = stream_get_contents($pipes[1]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            proc_close($proc);
        }
        $res = json_decode(trim($stdout), true);
        if (! is_array($res) || empty($res['ok'])) {
            $logModel->updateById($logId, ['status' => 'failed', 'progress' => 100, 'finished_at' => date('Y-m-d H:i:s'), 'message' => 'Export gagal']);
            $this->redirectWith('/tahun-buku/' . $id, 'error', 'Export gagal. Lihat riwayat export.');
        }

        $logModel->updateById($logId, [
            'stored_path'  => $fileName,
            'success_rows' => (int) $res['rows'],
            'progress'     => 100,
            'status'       => 'success',
            'finished_at'  => date('Y-m-d H:i:s'),
        ]);

        $this->redirectWith('/exports', 'success', 'Export tutup buku berhasil dibuat.');
    }

    private function groupDiff(int $periodId): array
    {
        return (new BookPeriod())->query(
            "SELECT s.group_id,
                    COALESCE(g.name, '(tanpa group)') AS group_name,
                    COALESCE(g.code, '') AS group_code,
                    COUNT(*) AS items,
                    COALESCE(SUM(s.expected_qty), 0) AS expected,
                    COALESCE(SUM(s.counted_qty), 0) AS counted,
                    SUM(s.counted_qty IS NULL) AS not_counted,
                    COALESCE(SUM(s.difference), 0) AS diff_qty,
                    COALESCE(SUM(COALESCE(s.difference, 0) * s.unit_price), 0) AS diff_value,
                    SUM(COALESCE(s.difference, 0) <> 0) AS diff_items
               FROM period_snapshots s
               LEFT JOIN `groups` g ON g.id = s.group_id
              WHERE s.period_id = ?
              GROUP BY s.group_id, g.name, g.code
              ORDER BY g.name",
            [$periodId]
        );
    }
}
/* I18N5-CTRL */
