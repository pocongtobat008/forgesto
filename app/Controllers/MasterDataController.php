<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Request;
use App\Models\Audit;
use App\Models\Device;
use App\Models\Discrepancy;
use App\Models\Group;
use App\Models\Item;
use App\Models\Location;

class MasterDataController extends Controller
{
    use BookPeriodActions;

    /**
     * Halaman Utama Master Data:
     * Menampilkan semua data barang inventaris (termasuk belum dihitung,
     * sudah dihitung, terposting, dan diaudit) tanpa menunggu submit audit.
     */
    public function index(): string
    {
        $this->ensureAccess();

        $itemModel = new Item();
        $filters = $itemModel->normaliseFilters($_GET);
        $page = max(1, Request::int('page', 1));
        $perPage = (int) config('app.per_page', 15);
        $showAll = Request::string('all', '') === '1';
        if ($showAll) {
            $page = 1;
            $perPage = 2000;
        }

        $itemsPage = $itemModel->listDetailed($filters, $page, $perPage);
        if ($page > (int) $itemsPage['last_page'] && (int) $itemsPage['last_page'] > 0) {
            $page = (int) $itemsPage['last_page'];
            $itemsPage = $itemModel->listDetailed($filters, $page, $perPage);
        }

        // Summary KPI seluruh data
        $summary = $itemModel->summary($filters);

        // Nilai finansial stok sistem (hanya dihitung untuk role yang berhak)
        $canSeeStockQty = can_see_stock_qty();
        $stockValues = $itemModel->stockValues($filters);
        $totalSystemValue = $stockValues['total_sys_val'];
        $totalCountedValue = $stockValues['total_counted_val'];

        return $this->view('master_data/index', [
            'title'             => __('Master Data'),
            'subtitle'          => __('Seluruh data inventaris barang (semua tahapan perhitungan & audit)'),
            'showAll'           => $showAll,
            'items'             => $itemsPage,
            'filters'           => $filters,
            'groups'            => (new Group())->forActiveUser(),
            'locations'         => (new Location())->forActiveUser(),
            'summary'           => $summary,
            'totalSystemValue'  => $totalSystemValue,
            'totalCountedValue' => $totalCountedValue,
            'canSeeStockQty'    => $canSeeStockQty,
            'countStatuses'     => Item::COUNT_STATUSES,
            'auditStatuses'     => Item::AUDIT_STATUSES,
        ]);
    }

    /**
     * JSON: nilai unik untuk dropdown filter Excel satu kolom.
     */
    public function distinct(array $params = []): never
    {
        $this->ensureAccess();

        $itemModel = new Item();
        $filters = $itemModel->normaliseFilters($_GET);
        $key = (string) ($_GET['key'] ?? '');

        // #6 Blind guard: sembunyikan qty sistem dari observer & audit
        if ($key === 'expected' && ! can_see_stock_qty()) {
            header('Content-Type: application/json; charset=utf-8');
            header('Cache-Control: no-store');
            echo json_encode(['values' => []], JSON_UNESCAPED_UNICODE);
            exit;
        }

        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode(['values' => $itemModel->distinctValues($key, $filters)], JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * Export xlsx: semua baris yang lolos filter aktif (tanpa pagination).
     */
    public function exportFiltered(array $params = []): never
    {
        if (! can('export_data')) {
            http_response_code(403);
            echo 'Forbidden';
            exit;
        }

        $itemModel = new Item();
        $filters = $itemModel->normaliseFilters($_GET);
        $rows = $itemModel->exportRows($filters);

        $dir = dirname(__DIR__, 2) . '/storage/exports';
        if (! is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $fileName = 'master_data_' . date('Ymd_His') . '.xlsx';
        $outPath = $dir . '/' . $fileName;

        $script = dirname(__DIR__, 2) . '/scripts/export_items_xlsx.py';
        $json = json_encode(['out' => $outPath, 'rows' => $rows, 'meta' => [
            'Dibuat oleh'  => (string) (Auth::user()['name'] ?? ''),
            'Waktu export' => date('Y-m-d H:i:s'),
            'Jumlah baris' => count($rows),
            'Tipe'         => 'Master Data',
        ]], JSON_UNESCAPED_UNICODE);

        $proc = proc_open(['python3', $script], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        $stdout = '';
        if (is_resource($proc)) {
            fwrite($pipes[0], (string) $json);
            fclose($pipes[0]);
            $stdout = stream_get_contents($pipes[1]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            proc_close($proc);
        }
        $res = json_decode(trim($stdout), true);
        if (! is_array($res) || empty($res['ok']) || ! is_file($outPath)) {
            http_response_code(500);
            echo 'Export gagal.';
            exit;
        }
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $fileName . '"');
        header('Content-Length: ' . filesize($outPath));
        readfile($outPath);
        @unlink($outPath);
        exit;
    }

    /**
     * Detail item pada Master Data.
     */
    public function show(array $params): string
    {
        $this->ensureAccess();

        $id = (int) $params['id'];
        $item = (new Item())->findDetailed($id);
        if ($item === null) {
            $this->notFound('Data tidak ditemukan.');
        }
        $this->ensureGroupAccess($item['group_id']);

        return $this->view('items/show', [
            'title'       => __('Detail ') . $item['code'],
            'item'        => $item,
            'audits'      => (new Audit())->recentForItem($id),
            'discrepancy' => (new Discrepancy())->findByItem($id),
        ]);
    }

    private function ensureAccess(): void
    {
        if (! Auth::check()) {
            $this->abort(403, 'Tidak ada user aktif.');
        }
    }
}
