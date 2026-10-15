<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Request;
use App\Models\ExportLog;
use App\Models\Reconciliation;

class ExportController extends Controller
{
    public function index(): string
    {
        $this->authorize('export_data');

        $col = \App\Models\ExportLog::normaliseColFilters($_GET['col'] ?? null);
        $adv = \App\Support\AdvancedSearch::normalise($_GET['adv'] ?? null, \App\Models\ExportLog::COL_MAP);

        return $this->view('exports/index', [
            'title' => __('Riwayat Export'),
            'logs'  => $this->paginateRows(\App\Models\ExportLog::applyAdv(\App\Models\ExportLog::applyColFilters((new ExportLog())->recent(500), $col), $adv), max(1, \App\Core\Request::int('page', 1)), list_per_page(20)),
            'filters' => ['col' => $col, 'adv' => $adv, 'search' => (string) Request::string('search', '')],
            'advCols' => ['file' => 'File', 'group' => 'Group', 'lines' => 'Baris', 'status' => 'Status', 'user' => 'Oleh', 'time' => 'Waktu'],
            'advNumeric' => [],
            'advMax' => \App\Support\AdvancedSearch::MAX_CONDITIONS,
        ]);
    }

    /** Nilai distinct untuk popup filter kolom (JSON). */
    public function distinct(array $params = []): never
    {
        $this->authorize('export_data');
        $col = \App\Models\ExportLog::normaliseColFilters($_GET['col'] ?? null);
        $key = (string) ($_GET['key'] ?? '');
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode(['values' => (new ExportLog())->distinctValues($key, $col)], JSON_UNESCAPED_UNICODE);
        exit;
    }

    /** Unduh file export. Hak: export_data + akses grup log tersebut. */
    public function download(array $params): void
    {
        $this->authorize('export_data');

        $model = new ExportLog();
        $log = $model->find((int) ($params['id'] ?? 0));
        if ($log === null) {
            $this->abort(404, 'Riwayat export tidak ditemukan.');
        }
        if (! empty($log['group_id'])) {
            $this->ensureGroupAccess((int) $log['group_id']);
        }

        $path = $model->originalPath($log);
        if ($path === null) {
            $this->abort(404, 'File export tidak tersedia.');
        }

        $name = preg_replace('/[^A-Za-z0-9._-]/', '_', (string) ($log['filename'] ?: 'export.xlsx'));
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $name . '"');
        header('Content-Length: ' . filesize($path));
        header('X-Content-Type-Options: nosniff');
        readfile($path);
        exit;
    }

    /**
     * Potong array hasil query menjadi satu halaman (format sama dengan Model::paginate).
     *
     * @param list<array> $rows
     * @return array<string, mixed>
     */
    protected function paginateRows(array $rows, int $page, int $perPage): array
    {
        $total    = count($rows);
        $lastPage = max(1, (int) ceil($total / $perPage));
        $page     = min(max(1, $page), $lastPage);
        $offset   = ($page - 1) * $perPage;

        return [
            'items'     => array_slice($rows, $offset, $perPage),
            'total'     => $total,
            'page'      => $page,
            'per_page'  => $perPage,
            'last_page' => $lastPage,
            'from'      => $total === 0 ? 0 : $offset + 1,
            'to'        => min($offset + $perPage, $total),
        ];
    }
}
/* I18N5-CTRL */
