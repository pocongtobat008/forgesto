<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Models\ActivityLog;
use App\Models\Device;
use App\Models\Location;
use App\Models\User;

class DeviceController extends Controller
{
    public function index(): string
    {
        (new Device())->releaseStale(); // sesi yatim: tidak login -> free

        $this->authorize('view_all_groups');

        $filters = [
            'group_id'    => Request::has('group_id') && Request::string('group_id') !== '' ? Request::int('group_id') : null,
            'location_id' => Request::has('location_id') && Request::string('location_id') !== '' ? Request::int('location_id') : null,
            'search'      => Request::string('search'),
            'col'         => \App\Models\Device::normaliseColFilters($_GET['col'] ?? null),
            'adv'         => \App\Support\AdvancedSearch::normalise($_GET['adv'] ?? null, Device::COL_MAP),
        ];
        $page = max(1, Request::int('page', 1));

        return $this->view('devices/index', [
            'title'     => __('Device'),
            'devices'   => (new Device())->listDetailed($filters, $page, list_per_page((int) config('app.per_page', 15))),
            'filters'   => $filters,
            'advCols' => ['code' => 'Kode', 'name' => 'Nama Device', 'type' => 'Tipe', 'serial' => 'Serial', 'location' => 'Lokasi', 'status' => 'Status'],
            'advNumeric' => [],
            'advMax' => \App\Support\AdvancedSearch::MAX_CONDITIONS,
            'locations' => (new Location())->forActiveUser(),
        ]);
    }

    /** Nilai distinct untuk popup filter kolom (JSON). */
    public function distinct(array $params = []): never
    {
        $this->authorize('view_all_groups');
        $filters = ['col' => Device::normaliseColFilters($_GET['col'] ?? null)];
        $key = (string) ($_GET['key'] ?? '');
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode(['values' => (new Device())->distinctValues($key, $filters)], JSON_UNESCAPED_UNICODE);
        exit;
    }

    public function create(): string
    {
        $this->authorize('manage_master');

        return $this->view('devices/form', [
            'title'     => __('Tambah Device'),
            'device'    => null,
            'locations' => (new Location())->forActiveUser(),
            'types'     => ['tablet', 'handheld', 'scanner', 'printer', 'pc', 'other'],
            'users'     => $this->userOptions(),
        ]);
    }

    public function store(): string
    {
        $this->authorize('manage_master');
        $this->verifyCsrf();

        $this->validate([
            'location_id' => 'required|integer|exists:locations,id',
            'code'        => 'required|max:50|unique:devices,code',
            'name'        => 'required|max:150',
        ], [
            'location_id' => 'Lokasi', 'code' => 'Kode device', 'name' => 'Nama device',
        ]);

        $location = (new Location())->find(Request::int('location_id'));
        if ($location === null) {
            $this->notFound('Lokasi tidak ditemukan.');
        }
        $this->ensureGroupAccess($location['group_id']);

        $device = new Device();
        $id = $device->insert([
            'location_id'   => Request::int('location_id'),
            'code'          => strtoupper(Request::string('code')),
            'name'          => Request::string('name'),
            'type'          => Request::string('type') ?: null,
            'serial_number' => Request::string('serial_number') ?: null,
            'user_id'       => Request::int('user_id') > 0 ? Request::int('user_id') : null,
            'is_active'     => Request::has('is_active') ? 1 : 0,
        ]);

        (new ActivityLog())->record('create', 'device', $id, 'Menambah device ' . Request::string('name'), (int) $location['group_id']);

        return $this->redirectWith('/devices', 'success', 'Device berhasil ditambahkan.');
    }

    public function edit(array $params): string
    {
        $this->authorize('manage_master');

        $device = (new Device())->find((int) $params['id']);
        if ($device === null) {
            $this->notFound('Device tidak ditemukan.');
        }

        return $this->view('devices/form', [
            'title'     => __('Edit Device'),
            'device'    => $device,
            'locations' => (new Location())->forActiveUser(),
            'types'     => ['tablet', 'handheld', 'scanner', 'printer', 'pc', 'other'],
            'users'     => $this->userOptions(),
        ]);
    }

    public function update(array $params): string
    {
        $this->authorize('manage_master');
        $this->verifyCsrf();

        $id = (int) $params['id'];
        $device = new Device();
        if ($device->find($id) === null) {
            $this->notFound('Device tidak ditemukan.');
        }

        $this->validate([
            'location_id' => 'required|integer|exists:locations,id',
            'code'        => 'required|max:50|unique:devices,code,' . $id,
            'name'        => 'required|max:150',
        ], [
            'location_id' => 'Lokasi', 'code' => 'Kode device', 'name' => 'Nama device',
        ]);

        $device->updateById($id, [
            'location_id'   => Request::int('location_id'),
            'code'          => strtoupper(Request::string('code')),
            'name'          => Request::string('name'),
            'type'          => Request::string('type') ?: null,
            'serial_number' => Request::string('serial_number') ?: null,
            'user_id'       => Request::int('user_id') > 0 ? Request::int('user_id') : null,
            'is_active'     => Request::has('is_active') ? 1 : 0,
        ]);

        (new ActivityLog())->record('update', 'device', $id, 'Mengubah device ' . Request::string('name'));

        return $this->redirectWith('/devices', 'success', 'Device berhasil diperbarui.');
    }

    /** Opsi "didaftarkan untuk" — user aktif, urut nama. */
    private function userOptions(): array
    {
        return (new User())->query(
            'SELECT id, name, username, role FROM users WHERE is_active = 1 ORDER BY name ASC'
        );
    }

    public function destroy(array $params): string
    {
        $this->authorize('manage_master');
        $this->verifyCsrf();

        $id = (int) $params['id'];
        $device = new Device();
        $row = $device->find($id);
        if ($row === null) {
            $this->notFound('Device tidak ditemukan.');
        }

        $device->deleteById($id);
        (new ActivityLog())->record('delete', 'device', $id, 'Menghapus device ' . $row['name']);

        return $this->redirectWith('/devices', 'success', 'Device berhasil dihapus.');
    }
}
/* I18N5-CTRL */
