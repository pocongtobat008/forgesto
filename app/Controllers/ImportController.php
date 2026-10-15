<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Request;
use App\Models\Group;
use App\Models\ImportLog;
use App\Services\ItemImportService;

class ImportController extends Controller
{
    public function index(): string
    {
        $this->authorize('import_data');

        return $this->view('import/index', [
            'title'   => __('Import Data'),
            'groups'  => (new Group())->forActiveUser(),
            'logs'    => (new ImportLog())->recent(10),
            'result'  => null,
            'columns' => [
                'group_code'     => 'Kode group (mis. GRP-A). Kosongkan bila group sudah dipilih.',
                'code'           => 'Kode unik barang/data. Wajib.',
                'name'           => 'Nama barang/data. Wajib.',
                'category'       => 'Kategori (opsional).',
                'unit'           => 'Satuan (opsional).',
                'expected_qty'   => 'Jumlah menurut sistem.',
                'counted_qty'    => 'Hasil hitung fisik (opsional, bila diisi langsung tercatat).',
                'location_code'  => 'Kode lokasi (opsional).',
                'device_code'    => 'Kode device (opsional).',
                'condition_note' => 'Catatan kondisi (opsional).',
            ],
        ]);
    }

    public function store(): string
    {
        $this->authorize('import_data');
        $this->verifyCsrf();

        $file = Request::file('file');
        if ($file === null) {
            return $this->redirectWith('/import', 'error', 'Pilih file .xlsx atau .csv terlebih dahulu.');
        }

        if ((int) $file['error'] !== UPLOAD_ERR_OK) {
            return $this->redirectWith('/import', 'error', 'Upload gagal (kode ' . (int) $file['error'] . ').');
        }

        $extension = strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION));
        $allowed = (array) config('app.upload.allowed', ['xlsx', 'csv']);
        if (! in_array($extension, $allowed, true)) {
            return $this->redirectWith('/import', 'error', 'Format file harus salah satu dari: ' . implode(', ', $allowed) . '.');
        }

        $maxSize = (int) config('app.upload.max_size', 8388608);
        if ((int) $file['size'] > $maxSize) {
            return $this->redirectWith('/import', 'error', 'Ukuran file maksimal ' . round($maxSize / 1048576, 1) . ' MB.');
        }

        $groupId = Request::int('group_id') ?: null;
        if ($groupId !== null) {
            $this->ensureGroupAccess($groupId);
        }

        try {
            // Satu file xlsx bisa memuat sheet lokasi & device (diproses dulu), lalu item.
            $masterReport = null;
            if ($extension === 'xlsx') {
                $masterReport = (new \App\Services\MasterImportService())->importLocationsAndDevices(
                    (string) $file['tmp_name'],
                    (int) ($groupId ?? 0),
                    true
                );
            }
            $result = (new ItemImportService())->import($file, $groupId, (int) Auth::id());
            $result['master'] = $masterReport;
        } catch (\Throwable $e) {
            return $this->redirectWith('/import', 'error', 'Import gagal: ' . $e->getMessage());
        }

        $type = $result['failed'] > 0 ? ($result['success'] > 0 ? 'warning' : 'error') : 'success';
        flash($type, "Import selesai: {$result['success']} berhasil ({$result['created']} baru, {$result['updated']} diperbarui), {$result['failed']} gagal dari {$result['total']} baris.");
        if (!empty($masterReport)) {
            $l = $masterReport['lokasi'];
            $d = $masterReport['device'];
            $mtype = ($l['failed'] + $d['failed']) > 0 ? 'warning' : 'success';
            flash($mtype, "Master: lokasi {$l['created']} baru/{$l['updated']} diperbarui/{$l['failed']} gagal; device {$d['created']} baru/{$d['updated']} diperbarui/{$d['failed']} gagal."
                . (($l['errors'] || $d['errors']) ? ' Contoh: ' . implode(' | ', array_slice(array_merge($l['errors'], $d['errors']), 0, 3)) : ''));
        }

        return $this->view('import/index', [
            'title'   => __('Import Data'),
            'groups'  => (new Group())->forActiveUser(),
            'logs'    => (new ImportLog())->recent(10),
            'result'  => $result,
            'columns' => [],
        ]);
    }

    /** Import master 1-file (xlsx). Default dry-run; tulis hanya bila mode=commit. */
    public function master(): string
    {
        $this->authorize('import_data');
        $this->verifyCsrf();

        $file = Request::file('file');
        if ($file === null || (int) $file['error'] !== UPLOAD_ERR_OK) {
            return $this->redirectWith('/import', 'error', 'Pilih file master .xlsx terlebih dahulu.');
        }
        if (strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION)) !== 'xlsx') {
            return $this->redirectWith('/import', 'error', 'File master harus .xlsx.');
        }
        if ((int) $file['size'] > (int) config('app.upload.max_size', 8388608)) {
            return $this->redirectWith('/import', 'error', 'Ukuran file melebihi batas upload.');
        }

        $dryRun = Request::string('mode', 'dry') !== 'commit';

        try {
            $out = (new \App\Services\MasterWorkbookImportService())->run(
                (string) $file['tmp_name'],
                $dryRun,
                (int) Auth::id()
            );
        } catch (\Throwable $e) {
            return $this->redirectWith('/import', 'error', 'Import master gagal (tidak ada data yang disimpan): ' . $e->getMessage());
        }

        $errs = 0;
        $ok = 0;
        foreach ($out['report'] as $r) {
            $errs += $r['failed'];
            $ok += $r['created'] + $r['updated'];
        }
        $label = $dryRun ? 'DRY-RUN (belum disimpan)' : 'TERSIMPAN';
        $type = $errs === 0 ? 'success' : ($ok > 0 ? 'warning' : 'error');
        flash($type, "Master {$label}: {$ok} baris valid, {$errs} gagal.");

        return $this->view('import/master', [
            'title'  => __('Hasil Import Master'),
            'out'    => $out,
            'dryRun' => $dryRun,
        ]);
    }

    /** Unduh template master 1 file. */
    public function templateMaster(): void
    {
        $this->authorize('import_data');
        $path = dirname(__DIR__, 2) . '/public/templates/stockforge-master-v3.xlsx';
        if (! is_file($path)) {
            $this->abort(404, 'Template master belum tersedia.');
        }
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="stockforge-master-v3.xlsx"');
        header('Content-Length: ' . filesize($path));
        readfile($path);
        exit;
    }

    /** Import khusus sheet karyawan (master data + akun login). */
    public function employees(): string
    {
        $this->authorize('import_data');
        $this->verifyCsrf();

        $file = Request::file('file');
        if ($file === null || (int) $file['error'] !== UPLOAD_ERR_OK) {
            return $this->redirectWith('/import', 'error', 'Pilih file karyawan .xlsx terlebih dahulu.');
        }
        if (strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION)) !== 'xlsx') {
            return $this->redirectWith('/import', 'error', 'File karyawan harus .xlsx.');
        }
        if ((int) $file['size'] > (int) config('app.upload.max_size', 8388608)) {
            return $this->redirectWith('/import', 'error', 'Ukuran file melebihi batas upload.');
        }

        try {
            $out = (new \App\Services\EmployeeImportService())->import($file, (int) Auth::id());
        } catch (\Throwable $e) {
            return $this->redirectWith('/import', 'error', 'Import karyawan gagal: ' . $e->getMessage());
        }

        $failed = (int) ($out['failed'] ?? 0);
        $okCount = (int) ($out['created'] ?? 0) + (int) ($out['updated'] ?? 0);
        $type = $failed === 0 ? 'success' : ($okCount > 0 ? 'warning' : 'error');
        flash($type, "Karyawan TERSIMPAN: {$out['created']} baru, {$out['updated']} diperbarui, {$failed} gagal.");

        return $this->view('import/employees', [
            'title' => __('Hasil Import Karyawan'),
            'out'   => $out,
        ]);
    }

    /** Unduh template karyawan. */
    public function templateEmployees(): void
    {
        $this->authorize('import_data');
        $path = dirname(__DIR__, 2) . '/public/templates/stockforge-employees-template.xlsx';
        if (! is_file($path)) {
            $this->abort(404, 'Template karyawan belum tersedia.');
        }
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="stockforge-employees-template.xlsx"');
        header('Content-Length: ' . filesize($path));
        readfile($path);
        exit;
    }

    /** Unduh file asli import. Hak akses: import_data + akses grup log tersebut. */
    public function download(array $params): void
    {
        $this->authorize('import_data');

        $logModel = new ImportLog();
        $log = $logModel->find((int) ($params['id'] ?? 0));
        if ($log === null) {
            $this->abort(404, 'Riwayat import tidak ditemukan.');
        }
        if (! empty($log['group_id'])) {
            $this->ensureGroupAccess((int) $log['group_id']);
        }

        $path = $logModel->originalPath($log);
        if ($path === null) {
            $this->abort(404, 'File asli tidak tersedia (mungkin import lama sebelum fitur ini).');
        }

        $name = preg_replace('/[^A-Za-z0-9._-]/', '_', (string) ($log['filename'] ?: 'import.xlsx'));
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $name . '"');
        header('Content-Length: ' . filesize($path));
        header('X-Content-Type-Options: nosniff');
        readfile($path);
        exit;
    }

    public function templateXlsx(): void
    {
        $this->authorize('import_data');
        $path = dirname(__DIR__, 2) . '/public/templates/stockforge-master-v3.xlsx';
        if (! is_file($path)) {
            $this->abort(404, 'Template belum tersedia.');
        }
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="stockforge-master-v3.xlsx"');
        header('Content-Length: ' . filesize($path));
        readfile($path);
        exit;
    }

    public function template(): string
    {
        $this->authorize('import_data');

        if (! headers_sent()) {
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="stockforge-template-import.csv"');
        }

        return ItemImportService::template();
    }

    /** Progress SSE untuk import master (real-time). */
    public function progressMaster(): void
    {
        $this->authorize('import_data');
        
        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        header('Connection: keep-alive');
        header('X-Accel-Buffering: no');
        
        $sessionId = session_id();
        $progressFile = sys_get_temp_dir() . "/stockforge_import_progress_{$sessionId}.json";
        
        $sent = 0;
        $timeout = 300;
        $start = time();
        
        while (time() - $start < $timeout) {
            if (file_exists($progressFile)) {
                $data = json_decode(file_get_contents($progressFile), true);
                if ($data && $data['seq'] > $sent) {
                    echo "data: " . json_encode($data) . "\n\n";
                    flush();
                    $sent = $data['seq'];
                    
                    if ($data['done'] ?? false) {
                        unlink($progressFile);
                        break;
                    }
                }
            }
            usleep(200000);
        }
        exit;
    }

    /** Hapus SEMUA data master milik user. 3x verifikasi. */
    public function bulkDeleteMaster(): void
    {
        $this->authorize('import_data');
        $this->verifyCsrf();
        
        $confirm1 = Request::string('confirm1', '');
        $confirm2 = Request::string('confirm2', '');
        $confirm3 = Request::string('confirm3', '');
        
        if ($confirm1 !== 'HAPUS' || $confirm2 !== 'SEMUA' || $confirm3 !== 'DATA') {
            $this->json(['success' => false, 'message' => 'Verifikasi gagal. Ketik: HAPUS, SEMUA, DATA'], 400);
            return;
        }
        
        $userId = (int) Auth::id();
        $pdo = \App\Core\Database::connection();
        
        try {
            $pdo->beginTransaction();
            
            $stmt = $pdo->prepare('
                SELECT g.id FROM `groups` g
                INNER JOIN user_groups ug ON ug.group_id = g.id
                WHERE ug.user_id = ?
            ');
            $stmt->execute([$userId]);
            $groupIds = $stmt->fetchAll(\PDO::FETCH_COLUMN);
            
            if (empty($groupIds)) {
                $pdo->rollBack();
                $this->json(['success' => false, 'message' => 'Tidak ada group yang bisa dihapus.'], 400);
                return;
            }
            
            $placeholders = implode(',', array_fill(0, count($groupIds), '?'));
            
            $counts = [];
            $stmtItems = $pdo->prepare("DELETE FROM items WHERE group_id IN ($placeholders)");
            $stmtItems->execute($groupIds);
            $counts['items'] = $stmtItems->rowCount();
            
            $stmtLoc = $pdo->prepare("DELETE FROM locations WHERE group_id IN ($placeholders)");
            $stmtLoc->execute($groupIds);
            $counts['locations'] = $stmtLoc->rowCount();
            
            $stmtDev = $pdo->prepare("DELETE FROM devices WHERE group_id IN ($placeholders)");
            $stmtDev->execute($groupIds);
            $counts['devices'] = $stmtDev->rowCount();
            
            $stmtDisc = $pdo->prepare("DELETE FROM discrepancies WHERE group_id IN ($placeholders)");
            $stmtDisc->execute($groupIds);
            $counts['discrepancies'] = $stmtDisc->rowCount();
            
            $stmtAudit = $pdo->prepare("DELETE FROM audits WHERE group_id IN ($placeholders)");
            $stmtAudit->execute($groupIds);
            $counts['audits'] = $stmtAudit->rowCount();
            
            $stmtUG = $pdo->prepare("DELETE FROM user_groups WHERE group_id IN ($placeholders)");
            $stmtUG->execute($groupIds);
            $counts['user_groups'] = $stmtUG->rowCount();
            
            $stmtGrp = $pdo->prepare("DELETE FROM `groups` WHERE id IN ($placeholders)");
            $stmtGrp->execute($groupIds);
            $counts['groups'] = $stmtGrp->rowCount();
            
            $pdo->commit();
            
            \App\Models\ActivityLog::create([
                'user_id' => $userId,
                'action' => 'bulk_delete_master',
                'description' => 'Hapus semua data master: ' . json_encode($counts),
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            ]);
            
            $this->json([
                'success' => true,
                'message' => 'Semua data master berhasil dihapus.',
                'deleted' => $counts,
            ]);
            
        } catch (\Throwable $e) {
            $pdo->rollBack();
            $this->json(['success' => false, 'message' => 'Gagal: ' . $e->getMessage()], 500);
        }
    }
}
/* I18N5-CTRL */
