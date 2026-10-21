<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Request;
use App\Models\ActivityLog;
use App\Models\Group;
use App\Models\Sto;
use App\Models\Warehouse;

class GroupController extends Controller
{
    public function index(): string
    {
        $this->authorize('view_all_groups');

        $col = \App\Models\Group::normaliseColFilters($_GET['col'] ?? null);
        $adv = \App\Support\AdvancedSearch::normalise($_GET['adv'] ?? null, \App\Models\Group::COL_MAP);
        $advLabels = ['code' => 'Kode', 'name' => 'Nama', 'warehouse' => 'Gudang', 'officers' => 'Petugas', 'sto' => 'STO', 'racks' => 'Rak', 'items' => 'Item', 'status' => 'Status'];
        $advSqlMap = ['code' => 'code', 'name' => 'name', 'warehouse' => 'warehouse_name', 'status' => 'is_active'];

        return $this->view('groups/index', [
            'title'   => __('Group'),
            'groups'  => $this->paginateRows(
                \App\Models\Group::applyAdv(
                    \App\Models\Group::applyColFilters((new Group())->listDetailed(), $col),
                    $adv
                ),
                max(1, \App\Core\Request::int('page', 1)),
                list_per_page(20)
            ),
            'filters' => [
                'search'           => (string) Request::string('search', ''),
                'warehouse_id'     => Request::int('warehouse_id') ?: null,
                'warehouseOptions' => (new Warehouse())->forActiveUser(Auth::groupIds(), Auth::allGroups()),
                'col'              => $col,
                'adv'              => $adv,
            ],
            'advCols'  => $advLabels,
            'advMax'   => \App\Support\AdvancedSearch::MAX_CONDITIONS,
        ]);
    }

    /** Nilai distinct untuk popup filter kolom (JSON). */
    public function distinct(array $params = []): never
    {
        $this->authorize('view_all_groups');
        $filters = ['col' => \App\Models\Group::normaliseColFilters($_GET['col'] ?? null)];
        $key = (string) ($_GET['key'] ?? '');
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode(['values' => (new Group())->distinctValues($key, $filters)], JSON_UNESCAPED_UNICODE);
        exit;
    }

    public function create(): string
    {
        $this->authorize('manage_groups');

        return $this->view('groups/form', [
            'title'      => __('Tambah Group'),
            'group'      => null,
            'stos'       => (new Sto())->options(),
            'warehouses' => (new Warehouse())->options(),
        ]);
    }

    public function store(): string
    {
        $this->authorize('manage_groups');
        $this->verifyCsrf();

        $this->validate([
            'code'            => 'required|max:50|unique:groups,code',
            'name'            => 'required|max:150',
            'sto_id'          => 'nullable|integer|exists:sto,id',
            'warehouse_id'    => 'required|integer|exists:warehouses,id',
            'pic_employee_id' => 'nullable|max:80',
            'pic_name'        => 'nullable|max:150',
        ], [
            'code' => 'Kode group', 'name' => 'Nama group', 'sto_id' => 'STO', 'warehouse_id' => 'Gudang',
            'pic_employee_id' => 'ID PIC', 'pic_name' => 'Nama PIC',
        ]);

        $group = new Group();
        $id = $group->insert([
            'code'           => strtoupper(Request::string('code')),
            'name'           => Request::string('name'),
            'description'    => Request::string('description') ?: null,
            'sto_id'         => Request::int('sto_id') ?: null,
            'warehouse_id'   => Request::int('warehouse_id') ?: null,
            'pic_employee_id' => $this->picEmployeeId(),
            'pic_name'       => $this->picName(),
            'is_active'      => Request::has('is_active') ? 1 : 0,
        ]);

        (new ActivityLog())->record('create', 'group', $id, 'Menambah group ' . Request::string('name'), $id);

        return $this->redirectWith('/groups', 'success', 'Group berhasil ditambahkan.');
    }

    public function show(array $params): string
    {
        $id = (int) $params['id'];
        $group = (new Group())->withSto($id);
        if ($group === null) {
            $this->notFound('Group tidak ditemukan.');
        }
        $this->ensureGroupAccess($id);

        $detail = new \App\Models\GroupDetail();

        return $this->view('groups/show', [
            'title'         => __('Detail Group'),
            'group'         => $group,
            'stats'         => $detail->stats($id) + ['pending' => 0],
            'observers'     => $detail->membersByRole($id, 'observer'),
            'auditors'      => $detail->membersByRole($id, 'audit'),
            'tablets'       => $detail->tablets($id),
            'pendingItems'  => $detail->pendingItems($id),
            'canSeeStockQty' => can_see_stock_qty(),
        ]);
    }

    public function beritaAcara(array $params): string
    {
        $id = (int) $params['id'];
        $group = (new Group())->withSto($id);
        if ($group === null) {
            $this->notFound('Group tidak ditemukan.');
        }
        $this->ensureGroupAccess($id);

        $detail = new \App\Models\GroupDetail();

        return $this->view('groups/berita_acara', [
            'title'          => __('Berita Acara STO'),
            'group'          => $group,
            'stats'          => $detail->stats($id),
            'observers'      => $detail->membersByRole($id, 'observer'),
            'auditors'       => $detail->membersByRole($id, 'audit'),
            'tablets'        => $detail->tablets($id),
            'pendingItems'   => $detail->pendingItems($id, 500),
            'canSeeStockQty' => can_see_stock_qty(),
            'periode'        => $this->periodeHitung($id),
        ]);
    }

    private function periodeHitung(int $groupId): string
    {
        $row = (new \App\Models\GroupDetail())->first(
            'SELECT MIN(counted_at) AS first_at, MAX(counted_at) AS last_at FROM items WHERE group_id = ? AND counted_at IS NOT NULL',
            [$groupId]
        );
        if (empty($row['first_at'])) {
            return 'Belum ada perhitungan';
        }
        $fmt = static fn (string $t): string => date('d M Y', strtotime($t));
        $a = $fmt($row['first_at']);
        $b = $fmt($row['last_at']);
        return $a === $b ? $a : $a . ' s/d ' . $b;
    }

    public function edit(array $params): string
    {
        $this->authorize('manage_groups');

        $group = (new Group())->find((int) $params['id']);
        if ($group === null) {
            $this->notFound('Group tidak ditemukan.');
        }

        return $this->view('groups/form', [
            'title'      => __('Edit Group'),
            'group'      => $group,
            'stos'       => (new Sto())->options(),
            'warehouses' => (new Warehouse())->options(),
        ]);
    }

    public function update(array $params): string
    {
        $this->authorize('manage_groups');
        $this->verifyCsrf();

        $id = (int) $params['id'];
        $group = new Group();
        if ($group->find($id) === null) {
            $this->notFound('Group tidak ditemukan.');
        }

        $this->validate([
            'code'            => 'required|max:50|unique:groups,code,' . $id,
            'name'            => 'required|max:150',
            'sto_id'          => 'nullable|integer|exists:sto,id',
            'warehouse_id'    => 'required|integer|exists:warehouses,id',
            'pic_employee_id' => 'nullable|max:80',
            'pic_name'        => 'nullable|max:150',
        ], [
            'code' => 'Kode group', 'name' => 'Nama group', 'sto_id' => 'STO', 'warehouse_id' => 'Gudang',
            'pic_employee_id' => 'ID PIC', 'pic_name' => 'Nama PIC',
        ]);

        $group->updateById($id, [
            'code'           => strtoupper(Request::string('code')),
            'name'           => Request::string('name'),
            'description'    => Request::string('description') ?: null,
            'sto_id'         => Request::int('sto_id') ?: null,
            'warehouse_id'   => Request::int('warehouse_id') ?: null,
            'pic_employee_id' => $this->picEmployeeId(),
            'pic_name'       => $this->picName(),
            'is_active'      => Request::has('is_active') ? 1 : 0,
        ]);

        (new ActivityLog())->record('update', 'group', $id, 'Mengubah group ' . Request::string('name'), $id);

        return $this->redirectWith('/groups', 'success', 'Group berhasil diperbarui.');
    }

    public function destroy(array $params): string
    {
        $this->authorize('manage_groups');
        $this->verifyCsrf();

        $id = (int) $params['id'];
        $group = new Group();
        $row = $group->find($id);
        if ($row === null) {
            $this->notFound('Group tidak ditemukan.');
        }

        $group->deleteById($id);
        (new ActivityLog())->record('delete', 'group', $id, 'Menghapus group ' . $row['name']);

        return $this->redirectWith('/groups', 'success', 'Group berhasil dihapus.');
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

    /** ID karyawan PIC group (kosong -> null). */
    private function picEmployeeId(): ?string
    {
        $id = strtoupper(trim((string) Request::string('pic_employee_id')));

        return $id === '' ? null : $id;
    }

    /**
     * Nama PIC: pakai input form; bila kosong tetapi ID diisi, ambil dari
     * master karyawan agar tampilan konsisten.
     */
    private function picName(): ?string
    {
        $name = trim((string) Request::string('pic_name'));
        if ($name !== '') {
            return $name;
        }

        $id = $this->picEmployeeId();
        if ($id === null) {
            return null;
        }

        $stmt = \App\Core\Database::connection()->prepare('SELECT name FROM employees WHERE UPPER(employee_id) = ? LIMIT 1');
        $stmt->execute([$id]);
        $found = $stmt->fetchColumn();

        return $found === false ? null : (string) $found;
    }
}
/* I18N5-CTRL */
