<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Request;
use App\Models\ActivityLog;
use App\Models\Warehouse;

class WarehouseController extends Controller
{
    public function index(): string
    {
        $this->authorize('manage_warehouses');

        $col = \App\Models\Warehouse::normaliseColFilters($_GET['col'] ?? null);

        return $this->view('warehouses/index', [
            'title'      => __('Gudang / Situs'),
            'warehouses' => $this->paginateRows(\App\Models\Warehouse::applyAdv(\App\Models\Warehouse::applyColFilters((new Warehouse())->listDetailed(), $col), \App\Support\AdvancedSearch::normalise($_GET['adv'] ?? null, \App\Models\Warehouse::COL_MAP)), max(1, \App\Core\Request::int('page', 1)), list_per_page(20)),
            'filters'    => [
                'search' => (string) Request::string('search', ''),
                'col'    => $col,
                'adv'    => \App\Support\AdvancedSearch::normalise($_GET['adv'] ?? null, \App\Models\Warehouse::COL_MAP),
            ],
            'advCols'    => ['code' => 'Kode', 'name' => 'Nama Gudang', 'city' => 'Kota', 'address' => 'Alamat', 'groups' => 'Group', 'racks' => 'Rak', 'items' => 'Item', 'status' => 'Status'],
            'advNumeric' => [],
            'advMax'     => \App\Support\AdvancedSearch::MAX_CONDITIONS,
        ]);
    }

    /** Nilai distinct untuk popup filter kolom (JSON). */
    public function distinct(array $params = []): never
    {
        $this->authorize('manage_warehouses');
        $filters = ['col' => \App\Models\Warehouse::normaliseColFilters($_GET['col'] ?? null)];
        $key = (string) ($_GET['key'] ?? '');
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode(['values' => (new Warehouse())->distinctValues($key, $filters)], JSON_UNESCAPED_UNICODE);
        exit;
    }

    public function create(): string
    {
        $this->authorize('manage_warehouses');

        return $this->view('warehouses/form', [
            'title'     => __('Tambah Gudang'),
            'warehouse' => null,
        ]);
    }

    public function store(): string
    {
        $this->authorize('manage_warehouses');
        $this->verifyCsrf();

        $this->validate([
            'code' => 'required|max:50|unique:warehouses,code',
            'name' => 'required|max:150',
        ], [
            'code' => 'Kode gudang', 'name' => 'Nama gudang',
        ]);

        $warehouse = new Warehouse();
        $id = $warehouse->insert([
            'code'      => strtoupper(Request::string('code')),
            'name'      => Request::string('name'),
            'city'      => Request::string('city') ?: null,
            'address'   => Request::string('address') ?: null,
            'is_active' => Request::has('is_active') ? 1 : 0,
        ]);

        (new ActivityLog())->record('create', 'warehouse', $id, 'Menambah gudang ' . Request::string('name'));

        return $this->redirectWith('/warehouses', 'success', 'Gudang berhasil ditambahkan.');
    }

    public function edit(array $params): string
    {
        $this->authorize('manage_warehouses');

        $warehouse = (new Warehouse())->find((int) $params['id']);
        if ($warehouse === null) {
            $this->notFound('Gudang tidak ditemukan.');
        }

        return $this->view('warehouses/form', [
            'title'     => __('Edit Gudang'),
            'warehouse' => $warehouse,
        ]);
    }

    public function update(array $params): string
    {
        $this->authorize('manage_warehouses');
        $this->verifyCsrf();

        $id = (int) $params['id'];
        $warehouse = new Warehouse();
        if ($warehouse->find($id) === null) {
            $this->notFound('Gudang tidak ditemukan.');
        }

        $this->validate([
            'code' => 'required|max:50|unique:warehouses,code,' . $id,
            'name' => 'required|max:150',
        ], [
            'code' => 'Kode gudang', 'name' => 'Nama gudang',
        ]);

        $warehouse->updateById($id, [
            'code'      => strtoupper(Request::string('code')),
            'name'      => Request::string('name'),
            'city'      => Request::string('city') ?: null,
            'address'   => Request::string('address') ?: null,
            'is_active' => Request::has('is_active') ? 1 : 0,
        ]);

        (new ActivityLog())->record('update', 'warehouse', $id, 'Mengubah gudang ' . Request::string('name'));

        return $this->redirectWith('/warehouses', 'success', 'Gudang berhasil diperbarui.');
    }

    public function destroy(array $params): string
    {
        $this->authorize('manage_warehouses');
        $this->verifyCsrf();

        $id = (int) $params['id'];
        $warehouse = new Warehouse();
        $row = $warehouse->find($id);
        if ($row === null) {
            $this->notFound('Gudang tidak ditemukan.');
        }

        $groups = (int) $warehouse->scalar('SELECT COUNT(*) FROM `groups` WHERE warehouse_id = ?', [$id]);
        if ($groups > 0) {
            return $this->redirectWith('/warehouses', 'error', 'Gudang masih memiliki ' . $groups . ' group. Pindahkan atau hapus group terlebih dahulu.');
        }

        $warehouse->deleteById($id);
        (new ActivityLog())->record('delete', 'warehouse', $id, 'Menghapus gudang ' . $row['name']);

        return $this->redirectWith('/warehouses', 'success', 'Gudang berhasil dihapus.');
    }

    /**
     * Switch the active warehouse. Available to any authenticated user; the
     * warehouse must be within their visible scope (or "all" for power users).
     */
    public function switch(array $params): string
    {
        $this->verifyCsrf();

        $returnTo = (string) Request::input('return_to', '');
        if ($returnTo === '' || ! str_starts_with($returnTo, '/') || str_starts_with($returnTo, '//')) {
            $returnTo = '/dashboard';
        }

        if (! Auth::check()) {
            return $this->redirectWith($returnTo, 'error', 'Tidak ada user aktif.');
        }

        $raw = (string) Request::input('warehouse_id', '');

        if ($raw === 'all') {
            if (! Auth::switchWarehouse(0, true)) {
                return $this->redirectWith($returnTo, 'error', 'Anda tidak berhak melihat semua gudang.');
            }

            return $this->redirectWith($returnTo, 'success', 'Menampilkan semua gudang.');
        }

        $warehouseId = (int) $raw;
        if ($warehouseId <= 0 || ! Auth::switchWarehouse($warehouseId)) {
            return $this->redirectWith($returnTo, 'error', 'Gudang tidak ditemukan atau bukan dalam akses Anda.');
        }

        $warehouse = (new Warehouse())->find($warehouseId);

        return $this->redirectWith($returnTo, 'success', 'Gudang aktif: ' . ($warehouse['name'] ?? '-') . '.');
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
