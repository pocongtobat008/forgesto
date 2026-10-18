<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Request;
use App\Models\ActivityLog;
use App\Models\Group;
use App\Services\EmployeeIdService;
use App\Services\EmployeeImportService;

/**
 * Pembuatan ID karyawan massal untuk role Observer & Auditor.
 *
 * Hanya Power Admin (ability manage_users) yang boleh membuka halaman ini.
 */
class EmployeeIdController extends Controller
{
    /** Role yang boleh dibuat dari halaman ini. */
    private const ROLES = [
        'observer' => 'Observer',
        'audit'    => 'Auditor',
    ];

    public function index(): string
    {
        $this->authorize('manage_users');

        $role   = $this->role();
        $prefix = $this->prefix();
        $qty    = $this->qty();
        $start  = max(0, Request::int('start', 0));
        $base   = trim(Request::string('base')) ?: self::ROLES[$role];

        $codes = (new EmployeeIdService())->nextCodes($prefix, $qty, $start > 0 ? $start : null);

        return $this->view('users/employee-ids', [
            'title'    => __('ID Karyawan'),
            'roles'    => self::ROLES,
            'role'     => $role,
            'prefix'   => $prefix,
            'qty'      => $qty,
            'start'    => $start,
            'base'     => $base,
            'groups'   => (new Group())->forActiveUser(),
            'codes'    => $codes,
            'created'  => null,
            'errors'   => [],
            'defaultPassword' => EmployeeImportService::DEFAULT_PASSWORD,
        ]);
    }

    public function store(): string
    {
        $this->authorize('manage_users');
        $this->verifyCsrf();

        $role = Request::string('role');
        if (! isset(self::ROLES[$role])) {
            $this->back('employee-ids', [], ['role' => __('Pilih role Observer atau Auditor.')]);
        }

        $qty = Request::int('qty', 0);
        if ($qty < 1 || $qty > 50) {
            $this->back('employee-ids', [], ['qty' => __('Jumlah ID harus antara 1 dan 50.')]);
        }

        $base = trim(Request::string('base'));
        if ($base === '') {
            $this->back('employee-ids', [], ['base' => __('Nama dasar wajib diisi.')]);
        }

        $prefix = $this->prefix();
        $start  = max(0, Request::int('start', 0));

        $password = (string) (Request::string('password') ?: EmployeeImportService::DEFAULT_PASSWORD);
        if (mb_strlen($password) < 6) {
            $this->back('employee-ids', [], ['password' => __('Password minimal 6 karakter.')]);
        }

        $groupCode = strtoupper(trim(Request::string('group_code')));

        $codes = (new EmployeeIdService())->nextCodes($prefix, $qty, $start > 0 ? $start : null);

        $importer = new EmployeeImportService();
        $actorId  = (int) (Auth::id() ?? 0);

        $rows   = [];
        $errors = [];

        foreach ($codes as $i => $code) {
            $data = [
                'employee_id' => $code,
                'name'        => $base . ' ' . str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT),
                'role'        => $role,
                'group_code'  => $groupCode,
                'password'    => $password,
                'is_active'   => 1,
            ];

            try {
                $action = $groupCode !== ''
                    ? $importer->upsertWithGroup($data, $actorId)
                    : $importer->upsert($data, $actorId);

                $rows[] = $data + ['action' => $action];
            } catch (\Throwable $e) {
                $errors[] = $code . ' — ' . $e->getMessage();
            }
        }

        if ($rows !== []) {
            (new ActivityLog())->record(
                'create',
                'user',
                null,
                'Membuat ' . count($rows) . ' ID karyawan ' . self::ROLES[$role] . ' (' . $codes[0] . '…' . end($codes) . ')'
            );
        }

        return $this->view('users/employee-ids', [
            'title'    => __('ID Karyawan'),
            'roles'    => self::ROLES,
            'role'     => $role,
            'prefix'   => $prefix,
            'qty'      => $qty,
            'start'    => $start,
            'base'     => $base,
            'groups'   => (new Group())->forActiveUser(),
            'codes'    => (new EmployeeIdService())->nextCodes($prefix, $qty, null),
            'created'  => $rows,
            'errors'   => $errors,
            'groupCode' => $groupCode,
            'defaultPassword' => $password,
        ]);
    }

    private function role(): string
    {
        $role = strtolower(Request::string('role'));

        return isset(self::ROLES[$role]) ? $role : 'observer';
    }

    private function prefix(): string
    {
        $prefix = strtoupper(trim(Request::string('prefix')));

        return preg_match('/^[A-Z]{1,4}$/', $prefix) === 1 ? $prefix : 'WE';
    }

    private function qty(): int
    {
        $qty = Request::int('qty', 5);

        return max(1, min(50, $qty ?: 5));
    }
}
