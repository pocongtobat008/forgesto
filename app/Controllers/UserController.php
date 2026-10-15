<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Models\ActivityLog;
use App\Models\Group;
use App\Models\User;

class UserController extends Controller
{
    public function index(): string
    {
        $this->authorize('manage_users');

        $filters = [
            'role'      => Request::string('role'),
            'search'    => Request::string('search'),
            'is_active' => Request::has('is_active') ? Request::string('is_active') : '',
            'col'       => User::normaliseColFilters($_GET['col'] ?? null),
            'adv'       => \App\Support\AdvancedSearch::normalise($_GET['adv'] ?? null, User::COL_MAP),
        ];

        $page = max(1, Request::int('page', 1));

        return $this->view('users/index', [
            'title'   => __('User & Role'),
            'users'   => (new User())->listDetailed($filters, $page, list_per_page((int) config('app.per_page', 15))),
            'filters' => $filters,
            'advCols' => ['name' => 'Nama', 'username' => 'Username', 'email' => 'Email', 'role' => 'Role', 'group' => 'Group', 'status' => 'Status'],
            'advNumeric' => [],
            'advMax' => \App\Support\AdvancedSearch::MAX_CONDITIONS,
            'roles'   => (array) config('app.roles', []),
        ]);
    }

    /** Nilai distinct untuk popup filter kolom (JSON). */
    public function distinct(array $params = []): never
    {
        $filters = ['col' => User::normaliseColFilters($_GET['col'] ?? null)];
        $key = (string) ($_GET['key'] ?? '');
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode(['values' => (new User())->distinctValues($key, $filters)], JSON_UNESCAPED_UNICODE);
        exit;
    }

    public function create(): string
    {
        $this->authorize('manage_users');

        return $this->view('users/form', [
            'title'  => __('Tambah User'),
            'user'   => null,
            'groups' => (new Group())->forActiveUser(),
            'roles'  => (array) config('app.roles', []),
            'roleDescriptions' => (array) config('app.role_descriptions', []),
            'selectedGroups' => [],
        ]);
    }

    public function store(): string
    {
        $this->authorize('manage_users');
        $this->verifyCsrf();

        $this->validate([
            'name'     => 'required|max:150',
            'username' => 'required|max:80|unique:users,username',
            'email'    => 'nullable|email|max:150',
            'role'     => 'required|in:poweradmin,adminsto,audit,observer',
        ], [
            'name' => 'Nama', 'username' => 'Username', 'email' => 'Email', 'role' => 'Role',
        ]);

        $user = new User();
        $id = $user->insert([
            'name'     => Request::string('name'),
            'username' => strtolower(Request::string('username')),
            'email'    => Request::string('email') ?: null,
            'phone'    => Request::string('phone') ?: null,
            'role'     => Request::string('role'),
            'password' => Request::string('password') !== '' ? password_hash(Request::string('password'), PASSWORD_DEFAULT) : null,
            'is_active' => Request::has('is_active') ? 1 : 0,
        ]);

        $user->syncGroups($id, $this->groupIds());

        (new ActivityLog())->record('create', 'user', $id, 'Menambah user ' . Request::string('name'));

        return $this->redirectWith('/users', 'success', 'User berhasil ditambahkan.');
    }

    public function edit(array $params): string
    {
        $this->authorize('manage_users');

        $user = (new User())->withGroup((int) $params['id']);
        if ($user === null) {
            $this->notFound('User tidak ditemukan.');
        }

        return $this->view('users/form', [
            'title'  => __('Edit User'),
            'user'   => $user,
            'groups' => (new Group())->forActiveUser(),
            'roles'  => (array) config('app.roles', []),
            'roleDescriptions' => (array) config('app.role_descriptions', []),
            'selectedGroups' => $user['group_ids'] ?? [],
        ]);
    }

    public function update(array $params): string
    {
        $this->authorize('manage_users');
        $this->verifyCsrf();

        $id = (int) $params['id'];
        $user = new User();
        if ($user->find($id) === null) {
            $this->notFound('User tidak ditemukan.');
        }

        $this->validate([
            'name'     => 'required|max:150',
            'username' => 'required|max:80|unique:users,username,' . $id,
            'email'    => 'nullable|email|max:150',
            'role'     => 'required|in:poweradmin,adminsto,audit,observer',
        ], [
            'name' => 'Nama', 'username' => 'Username', 'email' => 'Email', 'role' => 'Role',
        ]);

        $data = [
            'name'     => Request::string('name'),
            'username' => strtolower(Request::string('username')),
            'email'    => Request::string('email') ?: null,
            'phone'    => Request::string('phone') ?: null,
            'role'     => Request::string('role'),
            'is_active' => Request::has('is_active') ? 1 : 0,
        ];

        if (Request::string('password') !== '') {
            $data['password'] = password_hash(Request::string('password'), PASSWORD_DEFAULT);
        }

        $user->updateById($id, $data);
        $user->syncGroups($id, $this->groupIds());

        (new ActivityLog())->record('update', 'user', $id, 'Mengubah user ' . Request::string('name'));

        return $this->redirectWith('/users', 'success', 'User berhasil diperbarui.');
    }

    public function destroy(array $params): string
    {
        $this->authorize('manage_users');
        $this->verifyCsrf();

        $id = (int) $params['id'];

        if ($id === (int) auth()['id']) {
            return $this->redirectWith('/users', 'error', 'Anda tidak dapat menghapus akun yang sedang aktif.');
        }

        $user = new User();
        $row = $user->find($id);
        if ($row === null) {
            $this->notFound('User tidak ditemukan.');
        }

        $user->deleteById($id);
        (new ActivityLog())->record('delete', 'user', $id, 'Menghapus user ' . $row['name']);

        return $this->redirectWith('/users', 'success', 'User berhasil dihapus.');
    }

    /** @return list<int> */
    private function groupIds(): array
    {
        $ids = $_POST['group_ids'] ?? [];

        return is_array($ids) ? array_values(array_map('intval', $ids)) : [];
    }
}
/* I18N5-CTRL */
