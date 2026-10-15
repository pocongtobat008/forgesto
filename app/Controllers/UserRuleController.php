<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Request;
use App\Models\ActivityLog;
use App\Models\User;
use App\Models\UserRule;

class UserRuleController extends Controller
{
    /**
     * Tampilan utama matriks rules akses per ROLE.
     * Aturan dikonfigurasi per peran (bukan per orang) dan otomatis
     * berlaku untuk SEMUA pengguna yang memiliki peran tersebut.
     * Maintenance eksklusif oleh Power Admin.
     */
    public function index(): string
    {
        $this->ensurePowerAdmin();

        $validRoles = ['observer', 'audit', 'adminsto', 'poweradmin'];
        $selectedRole = Request::string('role', 'observer');
        if (! in_array($selectedRole, $validRoles, true)) {
            $selectedRole = 'observer';
        }

        // Ambil rules untuk role yang dipilih
        $rules = UserRule::forRole($selectedRole);

        // Ambil statistik jumlah pengguna aktif di setiap peran
        $userCounts = (new User())->query(
            'SELECT role, COUNT(*) as total_users FROM users WHERE is_active = 1 GROUP BY role'
        );
        $countsMap = [
            'observer'   => 0,
            'audit'      => 0,
            'adminsto'   => 0,
            'poweradmin' => 0,
        ];
        foreach ($userCounts as $uc) {
            $rKey = (string) $uc['role'];
            if (isset($countsMap[$rKey])) {
                $countsMap[$rKey] = (int) $uc['total_users'];
            }
        }

        return $this->view('user_rules/index', [
            'title'        => __('Rules Hak Akses per Peran (Role)'),
            'selectedRole' => $selectedRole,
            'rules'        => $rules,
            'systemMenus'  => UserRule::SYSTEM_MENUS,
            'countsMap'    => $countsMap,
            'roles'        => (array) config('app.roles', []),
        ]);
    }

    /**
     * Menyimpan matriks rules untuk ROLE yang dipilih.
     * Otomatis diterapkan untuk seluruh akun dengan peran tersebut.
     */
    public function save(): string
    {
        $this->ensurePowerAdmin();
        $this->verifyCsrf();

        $role = Request::string('role', 'observer');
        $validRoles = ['observer', 'audit', 'adminsto', 'poweradmin'];
        if (! in_array($role, $validRoles, true)) {
            $role = 'observer';
        }

        $rawRules = $_POST['rules'] ?? [];
        if (! is_array($rawRules)) {
            $rawRules = [];
        }

        $menusData = [];
        foreach ($rawRules as $menuKey => $vals) {
            if (! is_array($vals)) {
                continue;
            }

            $hiddenCols = $vals['hidden_columns'] ?? [];
            if (! is_array($hiddenCols)) {
                $hiddenCols = [];
            }

            $menusData[$menuKey] = [
                'menu_name'      => trim((string) ($vals['menu_name'] ?? '')),
                'can_view'       => ! empty($vals['can_view']) ? 1 : 0,
                'can_add'        => ! empty($vals['can_add']) ? 1 : 0,
                'can_edit'       => ! empty($vals['can_edit']) ? 1 : 0,
                'can_delete'     => ! empty($vals['can_delete']) ? 1 : 0,
                'can_post'       => ! empty($vals['can_post']) ? 1 : 0,
                'can_pic_post'   => ! empty($vals['can_pic_post']) ? 1 : 0,
                'hidden_columns' => $hiddenCols,
                'is_custom'      => ! empty($vals['is_custom']) ? 1 : 0,
            ];
        }

        $ruleModel = new UserRule();
        $ruleModel->saveRulesForRole($role, $menusData);

        (new ActivityLog())->record(
            'update',
            'user_rule',
            0,
            'Memperbarui rules hak akses untuk role ' . role_label($role) . ' (berlaku untuk semua pengguna role ini)'
        );

        return $this->redirectWith(
            '/user-rules?role=' . $role,
            'success',
            'Rules hak akses untuk peran ' . e(role_label($role)) . ' berhasil disimpan dan langsung berlaku untuk SEMUA pengguna dengan peran ini.'
        );
    }

    /**
     * Reset aturan role kembali ke built-in default sistem.
     */
    public function reset(): string
    {
        $this->ensurePowerAdmin();
        $this->verifyCsrf();

        $role = Request::string('role', 'observer');
        $validRoles = ['observer', 'audit', 'adminsto', 'poweradmin'];
        if (! in_array($role, $validRoles, true)) {
            $role = 'observer';
        }

        (new UserRule())->run('DELETE FROM user_rules WHERE user_id = 0 AND role = ?', [$role]);

        (new ActivityLog())->record(
            'reset',
            'user_rule',
            0,
            'Mereset rules role ' . role_label($role) . ' ke default bawaan sistem'
        );

        return $this->redirectWith(
            '/user-rules?role=' . $role,
            'success',
            'Rules untuk peran ' . e(role_label($role)) . ' berhasil direset mengikuti bawaan sistem.'
        );
    }

    /**
     * Tambah Menu Khusus / Kustom untuk ROLE tertentu.
     */
    public function addCustom(): string
    {
        $this->ensurePowerAdmin();
        $this->verifyCsrf();

        $role = Request::string('role', 'observer');
        $rawKey = Request::string('custom_key');
        $rawName = Request::string('custom_name');

        $cleanKey = strtolower(trim((string) preg_replace('/[^a-zA-Z0-9_\-\/]/', '', $rawKey)));
        $cleanName = trim($rawName);

        if ($cleanKey === '' || $cleanName === '') {
            return $this->redirectWith('/user-rules?role=' . $role, 'error', 'Key menu dan Nama menu tidak boleh kosong.');
        }

        $canView = Request::has('can_view') ? 1 : 0;
        $canAdd = Request::has('can_add') ? 1 : 0;
        $canEdit = Request::has('can_edit') ? 1 : 0;
        $canDelete = Request::has('can_delete') ? 1 : 0;
        $canPost = Request::has('can_post') ? 1 : 0;
        $canPicPost = Request::has('can_pic_post') ? 1 : 0;

        $ruleModel = new UserRule();
        $ruleModel->run(
            'INSERT INTO user_rules 
                (user_id, role, menu_key, menu_name, can_view, can_add, can_edit, can_delete, can_post, can_pic_post, hidden_columns, is_custom)
             VALUES (0, ?, ?, ?, ?, ?, ?, ?, ?, ?, "[]", 1)
             ON DUPLICATE KEY UPDATE
                menu_name = VALUES(menu_name),
                can_view = VALUES(can_view),
                can_add = VALUES(can_add),
                can_edit = VALUES(can_edit),
                can_delete = VALUES(can_delete),
                can_post = VALUES(can_post),
                can_pic_post = VALUES(can_pic_post),
                is_custom = 1,
                updated_at = NOW()',
            [
                $role,
                $cleanKey,
                $cleanName,
                $canView,
                $canAdd,
                $canEdit,
                $canDelete,
                $canPost,
                $canPicPost,
            ]
        );

        (new ActivityLog())->record(
            'create',
            'user_rule',
            0,
            'Menambahkan menu khusus [' . $cleanName . '] untuk role ' . role_label($role)
        );

        return $this->redirectWith('/user-rules?role=' . $role, 'success', 'Menu khusus "' . e($cleanName) . '" berhasil ditambahkan untuk peran ' . e(role_label($role)) . '.');
    }

    /**
     * Hapus Menu Khusus / Kustom untuk ROLE tertentu.
     */
    public function deleteCustom(): string
    {
        $this->ensurePowerAdmin();
        $this->verifyCsrf();

        $role = Request::string('role', 'observer');
        $menuKey = Request::string('menu_key');

        (new UserRule())->deleteCustomRule($role, $menuKey);

        (new ActivityLog())->record(
            'delete',
            'user_rule',
            0,
            'Menghapus menu khusus [' . $menuKey . '] untuk role ' . role_label($role)
        );

        return $this->redirectWith('/user-rules?role=' . $role, 'success', 'Menu khusus berhasil dihapus untuk peran ' . e(role_label($role)) . '.');
    }

    /**
     * API: Ambil rule satu menu untuk pop-up kontekstual.
     * Endpoint: GET /api/menu-rule?menu_key=xxx&role=xxx
     */
    public function apiGetMenuRule(): string
    {
        $currentUserRole = (string) (Auth::role() ?? 'observer');
        $isPowerAdmin = ($currentUserRole === 'poweradmin');

        $menuKey = Request::string('menu_key');
        if ($menuKey === '') {
            return $this->json(['success' => false, 'error' => 'Parameter menu_key wajib diisi.'], 400);
        }

        $systemMenus = UserRule::SYSTEM_MENUS;
        $menuInfo = $systemMenus[$menuKey] ?? [
            'name'    => ucfirst($menuKey),
            'columns' => [],
        ];

        // Jika bukan poweradmin, hanya boleh melihat perizinan rolenya sendiri
        if (! $isPowerAdmin) {
            $rules = UserRule::forRole($currentUserRole);
            $myRule = $rules[$menuKey] ?? [
                'can_view'       => 1,
                'can_add'        => 0,
                'can_edit'       => 0,
                'can_delete'     => 0,
                'can_post'       => 0,
                'can_pic_post'   => 0,
                'hidden_columns' => [],
            ];

            return $this->json([
                'success'        => true,
                'is_poweradmin'  => false,
                'menu_key'       => $menuKey,
                'menu_name'      => $menuInfo['name'],
                'available_cols' => $menuInfo['columns'],
                'target_role'    => $currentUserRole,
                'role_label'     => role_label($currentUserRole),
                'rule'           => $myRule,
            ]);
        }

        // Power Admin: Bisa memilih role target
        $targetRole = Request::string('role', 'observer');
        if (! in_array($targetRole, ['observer', 'audit', 'adminsto', 'poweradmin'], true)) {
            $targetRole = 'observer';
        }

        $rules = UserRule::forRole($targetRole);
        $ruleData = $rules[$menuKey] ?? [
            'can_view'       => 1,
            'can_add'        => 0,
            'can_edit'       => 0,
            'can_delete'     => 0,
            'can_post'       => 0,
            'can_pic_post'   => 0,
            'hidden_columns' => [],
        ];

        return $this->json([
            'success'        => true,
            'is_poweradmin'  => true,
            'menu_key'       => $menuKey,
            'menu_name'      => $menuInfo['name'],
            'available_cols' => $menuInfo['columns'],
            'target_role'    => $targetRole,
            'role_label'     => role_label($targetRole),
            'rule'           => $ruleData,
        ]);
    }

    /**
     * API: Simpan rule satu menu dari pop-up kontekstual.
     * Endpoint: POST /api/menu-rule/save
     */
    public function apiSaveMenuRule(): string
    {
        $this->ensurePowerAdmin();
        $this->verifyCsrf();

        $menuKey = Request::string('menu_key');
        if ($menuKey === '') {
            return $this->json(['success' => false, 'error' => 'menu_key wajib diisi.'], 400);
        }

        $targetRole = Request::string('role', 'observer');
        if (! in_array($targetRole, ['observer', 'audit', 'adminsto', 'poweradmin'], true)) {
            $targetRole = 'observer';
        }

        $hiddenCols = $_POST['hidden_columns'] ?? [];
        if (! is_array($hiddenCols)) {
            $hiddenCols = [];
        }

        $ruleData = [
            'menu_name'      => Request::string('menu_name', UserRule::SYSTEM_MENUS[$menuKey]['name'] ?? $menuKey),
            'can_view'       => Request::has('can_view') ? 1 : 0,
            'can_add'        => Request::has('can_add') ? 1 : 0,
            'can_edit'       => Request::has('can_edit') ? 1 : 0,
            'can_delete'     => Request::has('can_delete') ? 1 : 0,
            'can_post'       => Request::has('can_post') ? 1 : 0,
            'can_pic_post'   => Request::has('can_pic_post') ? 1 : 0,
            'hidden_columns' => $hiddenCols,
        ];

        (new UserRule())->saveSingleMenuRule($targetRole, $menuKey, $ruleData);

        (new ActivityLog())->record(
            'update',
            'menu_rule',
            0,
            'Memperbarui rule menu [' . ($ruleData['menu_name'] ?: $menuKey) . '] untuk role ' . role_label($targetRole) . ' (berlaku untuk semua pengguna role ini)'
        );

        return $this->json([
            'success' => true,
            'message' => 'Rule menu "' . ($ruleData['menu_name'] ?: $menuKey) . '" berhasil disimpan untuk peran ' . role_label($targetRole) . '!',
        ]);
    }

    /**
     * Memastikan hanya user dengan role 'poweradmin' yang berhak mengakses.
     */
    private function ensurePowerAdmin(): void
    {
        $role = (string) (Auth::role() ?? '');
        if ($role !== 'poweradmin') {
            $this->forbidden('Akses ditolak. Fitur ini hanya dapat dikelola oleh Power Admin.');
        }
    }
}
