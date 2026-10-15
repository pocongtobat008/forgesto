<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Models\ActivityLog;
use App\Models\Group;
use App\Models\Location;

class LocationController extends Controller
{
    public function index(): string
    {
        $this->authorize('view_all_groups');

        $filters = [
            'group_id' => Request::has('group_id') && Request::string('group_id') !== '' ? Request::int('group_id') : null,
            'search'   => Request::string('search'),
            'col'      => \App\Models\Location::normaliseColFilters($_GET['col'] ?? null),
            'adv'      => \App\Support\AdvancedSearch::normalise($_GET['adv'] ?? null, Location::COL_MAP),
        ];
        $page = max(1, Request::int('page', 1));

        return $this->view('locations/index', [
            'title'     => __('Lokasi'),
            'locations' => (new Location())->listDetailed($filters, $page, list_per_page((int) config('app.per_page', 15))),
            'filters'   => $filters,
            'advCols' => ['code' => 'Kode', 'name' => 'Nama Lokasi', 'group' => 'Group', 'device' => 'Device', 'item' => 'Item', 'status' => 'Status'],
            'advNumeric' => [],
            'advMax' => \App\Support\AdvancedSearch::MAX_CONDITIONS,
            'groups'    => (new Group())->forActiveUser(),
        ]);
    }

    /** Nilai distinct untuk popup filter kolom (JSON). */
    public function distinct(array $params = []): never
    {
        $this->authorize('view_all_groups');
        $filters = ['col' => Location::normaliseColFilters($_GET['col'] ?? null)];
        $key = (string) ($_GET['key'] ?? '');
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode(['values' => (new Location())->distinctValues($key, $filters)], JSON_UNESCAPED_UNICODE);
        exit;
    }

    public function create(): string
    {
        $this->authorize('manage_master');

        return $this->view('locations/form', [
            'title'    => __('Tambah Lokasi'),
            'location' => null,
            'groups'   => (new Group())->forActiveUser(),
        ]);
    }

    public function store(): string
    {
        $this->authorize('manage_master');
        $this->verifyCsrf();

        $this->validate([
            'group_id' => 'required|integer|exists:groups,id',
            'code'     => 'required|max:50',
            'name'     => 'required|max:150',
        ], [
            'group_id' => 'Group', 'code' => 'Kode lokasi', 'name' => 'Nama lokasi',
        ]);

        $groupId = Request::int('group_id');
        $code = strtoupper(Request::string('code'));

        $location = new Location();
        if ($location->first('SELECT id FROM locations WHERE group_id = ? AND code = ?', [$groupId, $code]) !== null) {
            return $this->back('/locations/create', Request::all(), ['code' => 'Kode lokasi sudah dipakai pada group tersebut.']);
        }

        $id = $location->insert([
            'group_id'  => $groupId,
            'code'      => $code,
            'name'      => Request::string('name'),
            'address'   => Request::string('address') ?: null,
            'is_active' => Request::has('is_active') ? 1 : 0,
        ]);

        (new ActivityLog())->record('create', 'location', $id, 'Menambah lokasi ' . Request::string('name'), $groupId);

        return $this->redirectWith('/locations', 'success', 'Lokasi berhasil ditambahkan.');
    }

    public function edit(array $params): string
    {
        $this->authorize('manage_master');

        $location = (new Location())->find((int) $params['id']);
        if ($location === null) {
            $this->notFound('Lokasi tidak ditemukan.');
        }
        $this->ensureGroupAccess($location['group_id']);

        return $this->view('locations/form', [
            'title'    => __('Edit Lokasi'),
            'location' => $location,
            'groups'   => (new Group())->forActiveUser(),
        ]);
    }

    public function update(array $params): string
    {
        $this->authorize('manage_master');
        $this->verifyCsrf();

        $id = (int) $params['id'];
        $location = new Location();
        $row = $location->find($id);
        if ($row === null) {
            $this->notFound('Lokasi tidak ditemukan.');
        }
        $this->ensureGroupAccess($row['group_id']);

        $this->validate([
            'group_id' => 'required|integer|exists:groups,id',
            'code'     => 'required|max:50',
            'name'     => 'required|max:150',
        ], [
            'group_id' => 'Group', 'code' => 'Kode lokasi', 'name' => 'Nama lokasi',
        ]);

        $groupId = Request::int('group_id');
        $code = strtoupper(Request::string('code'));

        $duplicate = $location->first(
            'SELECT id FROM locations WHERE group_id = ? AND code = ? AND id <> ?',
            [$groupId, $code, $id]
        );
        if ($duplicate !== null) {
            return $this->back('/locations/edit/' . $id, Request::all(), ['code' => 'Kode lokasi sudah dipakai pada group tersebut.']);
        }

        $location->updateById($id, [
            'group_id'  => $groupId,
            'code'      => $code,
            'name'      => Request::string('name'),
            'address'   => Request::string('address') ?: null,
            'is_active' => Request::has('is_active') ? 1 : 0,
        ]);

        (new ActivityLog())->record('update', 'location', $id, 'Mengubah lokasi ' . Request::string('name'), $groupId);

        return $this->redirectWith('/locations', 'success', 'Lokasi berhasil diperbarui.');
    }

    public function destroy(array $params): string
    {
        $this->authorize('manage_master');
        $this->verifyCsrf();

        $id = (int) $params['id'];
        $location = new Location();
        $row = $location->find($id);
        if ($row === null) {
            $this->notFound('Lokasi tidak ditemukan.');
        }
        $this->ensureGroupAccess($row['group_id']);

        $location->deleteById($id);
        (new ActivityLog())->record('delete', 'location', $id, 'Menghapus lokasi ' . $row['name'], (int) $row['group_id']);

        return $this->redirectWith('/locations', 'success', 'Lokasi berhasil dihapus.');
    }
}
/* I18N5-CTRL */
