<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Auth;
use App\Core\Model;

class UserRule extends Model
{
    protected string $table = 'user_rules';

    protected array $fillable = [
        'user_id',
        'role',
        'menu_key',
        'menu_name',
        'can_view',
        'can_add',
        'can_edit',
        'can_delete',
        'can_post',
        'can_pic_post',
        'hidden_columns',
        'is_custom',
    ];

    /** Cache memoisation dalam satu siklus request */
    private static array $cache = [];

    /**
     * Definisi lengkap semua menu sistem beserta kolom tabel yang dapat dibatasi.
     */
    public const SYSTEM_MENUS = [
        'dashboard' => [
            'name'    => 'Dashboard',
            'group'   => 'operasional',
            'icon'    => 'dashboard',
            'route'   => 'dashboard',
            'columns' => [],
        ],
        'progress' => [
            'name'    => 'Progress',
            'group'   => 'operasional',
            'icon'    => 'chart',
            'route'   => 'progress',
            'columns' => [],
        ],
        'items' => [
            'name'    => 'Data STO',
            'group'   => 'operasional',
            'icon'    => 'package',
            'route'   => 'items',
            'columns' => [
                'code'         => 'Kode Item',
                'name'         => 'Nama Item',
                'group'        => 'Group STO',
                'location'     => 'Lokasi',
                'device'       => 'Device Scanner',
                'counted'      => 'Qty Hitung Fisik',
                'expected'     => 'Qty Sistem (Blind Count)',
                'difference'   => 'Selisih Qty',
                'price'        => 'Harga Satuan (Rp)',
                'diff_amount'  => 'Selisih Nilai (Rp)',
                'unit'         => 'Satuan',
                'category'     => 'Kategori',
                'count_status' => 'Status Hitung',
                'audit_status' => 'Status Audit',
            ],
        ],
        'items/memo' => [
            'name'    => 'Memo STO',
            'group'   => 'operasional',
            'icon'    => 'file-text',
            'route'   => 'items/memo',
            'columns' => [
                'no_box'      => 'Nomor Box',
                'group'       => 'Group STO',
                'counted_qty' => 'Qty Hasil Hitung',
                'unit_price'  => 'Harga Satuan',
                'total_qty'   => 'Total QTY Box',
            ],
        ],
        'audit' => [
            'name'    => 'Audit STO',
            'group'   => 'operasional',
            'icon'    => 'clipboard-check',
            'route'   => 'audit',
            'columns' => [
                'code'         => 'Kode Item',
                'name'         => 'Nama Item',
                'group'        => 'Group STO',
                'counted'      => 'Qty Hitung Observer',
                'audited'      => 'Qty Audit',
                'audit_status' => 'Status Audit',
                'sample'       => 'Status Sampling',
            ],
        ],
        'tahun-buku' => [
            'name'    => 'Tahun Buku',
            'group'   => 'operasional',
            'icon'    => 'archive',
            'route'   => 'tahun-buku',
            'columns' => [
                'audit_month' => 'Bulan Periode',
                'status'      => 'Status Periode',
                'progress'    => 'Progress Rekonsiliasi',
                'closed_at'   => 'Waktu Penutupan',
            ],
        ],
        'reports' => [
            'name'    => 'Report',
            'group'   => 'operasional',
            'icon'    => 'chart',
            'route'   => 'reports',
            'columns' => [
                'audit_month' => 'Tahun Buku',
                'status'      => 'Status Periode',
                'diff_qty'    => 'Selisih Qty',
                'diff_value'  => 'Nilai Selisih',
                'diff_items'  => 'Item Berselisih',
            ],
        ],
        'discrepancies' => [
            'name'    => 'Selisih',
            'group'   => 'operasional',
            'icon'    => 'trending-up',
            'route'   => 'discrepancies',
            'columns' => [
                'item'        => 'Item (Kode & Nama)',
                'group'       => 'Group STO',
                'expected'    => 'Stok Sistem',
                'counted'     => 'Hasil Hitung',
                'audited'     => 'Hasil Audit',
                'difference'  => 'Selisih Qty',
                'diff_amount' => 'Selisih Nilai (Rp)',
                'status'      => 'Status Penyelesaian',
                'reported'    => 'Waktu Dilaporkan',
            ],
        ],
        'reconciliation' => [
            'name'    => 'Rekonsiliasi',
            'group'   => 'operasional',
            'icon'    => 'scale',
            'route'   => 'reconciliation',
            'columns' => [
                'group_name'     => 'Nama Group',
                'system_qty'     => 'Total Qty Sistem',
                'physical_qty'   => 'Total Qty Fisik',
                'diff_qty'       => 'Selisih Qty',
                'system_value'   => 'Total Nilai Sistem (Rp)',
                'physical_value' => 'Total Nilai Fisik (Rp)',
                'diff_value'     => 'Total Selisih Nilai (Rp)',
                'status'         => 'Status Rekonsiliasi',
            ],
        ],
        'reconciliation/audit' => [
            'name'    => 'Audit Rekonsiliasi',
            'group'   => 'operasional',
            'icon'    => 'shield-check',
            'route'   => 'reconciliation/audit',
            'columns' => [
                'group_name'        => 'Nama Group',
                'sample_pct'        => 'Persentase Sample',
                'audit_status'      => 'Status Audit Rekon',
                'discrepancy_count' => 'Jumlah Selisih',
            ],
        ],
        'notifications' => [
            'name'    => 'Notifikasi',
            'group'   => 'operasional',
            'icon'    => 'bell',
            'route'   => 'notifications',
            'columns' => [],
        ],
        'master-data' => [
            'name'    => 'Data Master',
            'group'   => 'master',
            'icon'    => 'layers',
            'route'   => 'master-data',
            'columns' => [
                'code'     => 'Kode Barang',
                'name'     => 'Nama Barang',
                'category' => 'Kategori',
                'unit'     => 'Satuan',
                'price'    => 'Harga Satuan (Rp)',
            ],
        ],
        'groups' => [
            'name'    => 'Group STO',
            'group'   => 'master',
            'icon'    => 'layers',
            'route'   => 'groups',
            'columns' => [
                'code'         => 'Kode Group',
                'name'         => 'Nama Group',
                'warehouse'    => 'Gudang / Situs',
                'pic_observer' => 'PIC Observer',
                'auditor'      => 'Auditor Group',
                'status'       => 'Status Aktif',
            ],
        ],
        'users' => [
            'name'    => 'User & Role',
            'group'   => 'master',
            'icon'    => 'users',
            'route'   => 'users',
            'columns' => [
                'name'     => 'Nama Pengguna',
                'username' => 'Username',
                'email'    => 'Email',
                'role'     => 'Role Akses',
                'group'    => 'Group Delegasi',
                'status'   => 'Status Akun',
            ],
        ],
        'user-rules' => [
            'name'    => 'Rules Akses User',
            'group'   => 'master',
            'icon'    => 'shield',
            'route'   => 'user-rules',
            'columns' => [],
        ],
        'warehouses' => [
            'name'    => 'Gudang / Situs',
            'group'   => 'master',
            'icon'    => 'building',
            'route'   => 'warehouses',
            'columns' => [
                'name'    => 'Nama Gudang',
                'code'    => 'Kode Gudang',
                'address' => 'Alamat / Lokasi Fisik',
                'status'  => 'Status Aktif',
            ],
        ],
        'locations' => [
            'name'    => 'Lokasi Rak/Baris',
            'group'   => 'master',
            'icon'    => 'pin',
            'route'   => 'locations',
            'columns' => [
                'name'  => 'Nama Lokasi',
                'code'  => 'Kode Lokasi',
                'group' => 'Group Terkait',
            ],
        ],
        'devices' => [
            'name'    => 'Device Scanner',
            'group'   => 'master',
            'icon'    => 'tablet',
            'route'   => 'devices',
            'columns' => [
                'name'  => 'Nama Device',
                'model' => 'Model / Identitas',
                'group' => 'Group Terkait',
            ],
        ],
        'import' => [
            'name'    => 'Import Data',
            'group'   => 'master',
            'icon'    => 'upload',
            'route'   => 'import',
            'columns' => [],
        ],
        'exports' => [
            'name'    => 'Export Data',
            'group'   => 'master',
            'icon'    => 'download',
            'route'   => 'exports',
            'columns' => [],
        ],
        'activity' => [
            'name'    => 'Log Aktivitas',
            'group'   => 'lainnya',
            'icon'    => 'activity',
            'route'   => 'activity',
            'columns' => [
                'user'        => 'Pengguna',
                'action'      => 'Aksi',
                'entity'      => 'Modul / Entitas',
                'description' => 'Keterangan',
                'time'        => 'Waktu Aktivitas',
            ],
        ],
        'profile' => [
            'name'    => 'Profil & Akses',
            'group'   => 'lainnya',
            'icon'    => 'user-circle',
            'route'   => 'profile',
            'columns' => [],
        ],
    ];

    /**
     * Mengambil matriks rules efektif untuk sebuah ROLE.
     * Aturan ini otomatis berlaku untuk SEMUA pengguna yang memiliki role tersebut.
     */
    public static function forRole(string $role): array
    {
        $cacheKey = 'role:' . $role;
        if (isset(self::$cache[$cacheKey])) {
            return self::$cache[$cacheKey];
        }

        $instance = new self();

        // Ambil rules role dari database (user_id = 0)
        $roleRows = $instance->query(
            'SELECT * FROM user_rules WHERE user_id = 0 AND role = ? ORDER BY id ASC',
            [$role]
        );
        $roleMap = [];
        foreach ($roleRows as $r) {
            $roleMap[$r['menu_key']] = $r;
        }

        // Bangun hasil akhir untuk semua system menus + custom menus
        $result = [];

        // Gabungkan semua system menus
        foreach (self::SYSTEM_MENUS as $key => $meta) {
            if (isset($roleMap[$key])) {
                $row = $roleMap[$key];
            } else {
                $row = self::getDefaultRuleForRole($role, $key, $meta['name']);
            }

            $hiddenCols = [];
            if (! empty($row['hidden_columns'])) {
                $decoded = json_decode((string) $row['hidden_columns'], true);
                if (is_array($decoded)) {
                    $hiddenCols = $decoded;
                } else {
                    $hiddenCols = array_filter(array_map('trim', explode(',', (string) $row['hidden_columns'])));
                }
            }

            $result[$key] = [
                'menu_key'       => $key,
                'menu_name'      => ! empty($row['menu_name']) ? $row['menu_name'] : $meta['name'],
                'group'          => $meta['group'],
                'icon'           => $meta['icon'],
                'route'          => $meta['route'],
                'available_cols' => $meta['columns'],
                'can_view'       => (int) ($row['can_view'] ?? 1) === 1,
                'can_add'        => (int) ($row['can_add'] ?? 0) === 1,
                'can_edit'       => (int) ($row['can_edit'] ?? 0) === 1,
                'can_delete'     => (int) ($row['can_delete'] ?? 0) === 1,
                'can_post'       => (int) ($row['can_post'] ?? 0) === 1,
                'can_pic_post'   => (int) ($row['can_pic_post'] ?? 0) === 1,
                'hidden_columns' => $hiddenCols,
                'is_custom'      => 0,
                'is_override'    => false,
            ];
        }

        // Tambahkan custom menus untuk role ini
        foreach ($roleMap as $key => $row) {
            if (! isset(self::SYSTEM_MENUS[$key])) {
                $hiddenCols = [];
                if (! empty($row['hidden_columns'])) {
                    $decoded = json_decode((string) $row['hidden_columns'], true);
                    $hiddenCols = is_array($decoded) ? $decoded : array_filter(array_map('trim', explode(',', (string) $row['hidden_columns'])));
                }

                $result[$key] = [
                    'menu_key'       => $key,
                    'menu_name'      => ! empty($row['menu_name']) ? $row['menu_name'] : $key,
                    'group'          => 'custom',
                    'icon'           => 'sliders',
                    'route'          => $key,
                    'available_cols' => [],
                    'can_view'       => (int) $row['can_view'] === 1,
                    'can_add'        => (int) $row['can_add'] === 1,
                    'can_edit'       => (int) $row['can_edit'] === 1,
                    'can_delete'     => (int) $row['can_delete'] === 1,
                    'can_post'       => (int) $row['can_post'] === 1,
                    'can_pic_post'   => (int) $row['can_pic_post'] === 1,
                    'hidden_columns' => $hiddenCols,
                    'is_custom'      => 1,
                    'is_override'    => false,
                ];
            }
        }

        self::$cache[$cacheKey] = $result;
        return $result;
    }

    /**
     * Backward-compatibility wrapper: mengambil rules untuk user (berdasarkan rolenya).
     * Sesuai ketentuan, perizinan berlaku PER ROLE, bukan per orang.
     */
    public static function forUser(int $userId, string $role): array
    {
        return self::forRole($role);
    }

    /**
     * Cek apakah role/user berhak melihat menu tertentu (untuk Sidebar & Controller Gate).
     */
    public static function canView(string $menuKey, mixed $arg2 = null, mixed $arg3 = null): bool
    {
        $role = null;
        if (is_string($arg2) && $arg2 !== '') {
            $role = $arg2;
        } elseif (is_string($arg3) && $arg3 !== '') {
            $role = $arg3;
        }
        $role ??= Auth::role() ?? 'observer';

        // Power Admin selalu berhak atas user-rules, users, & dashboard (safety guard agar tidak terkunci)
        if ($role === 'poweradmin' && in_array($menuKey, ['user-rules', 'users', 'dashboard'], true)) {
            return true;
        }

        $rules = self::forRole($role);
        return ! empty($rules[$menuKey]['can_view']);
    }

    /**
     * Cek apakah role/user berhak melakukan aksi tertentu pada menu.
     * $action: 'view', 'add', 'edit', 'delete', 'post', 'pic_post'
     */
    public static function canAction(string $menuKey, string $action, mixed $arg3 = null, mixed $arg4 = null): bool
    {
        $role = null;
        if (is_string($arg3) && $arg3 !== '') {
            $role = $arg3;
        } elseif (is_string($arg4) && $arg4 !== '') {
            $role = $arg4;
        }
        $role ??= Auth::role() ?? 'observer';

        // Power Admin selalu punya full action pada user-rules & users
        if ($role === 'poweradmin' && in_array($menuKey, ['user-rules', 'users'], true)) {
            return true;
        }

        $rules = self::forRole($role);
        $field = match ($action) {
            'view'     => 'can_view',
            'add'      => 'can_add',
            'edit'     => 'can_edit',
            'delete'   => 'can_delete',
            'post'     => 'can_post',
            'pic_post' => 'can_pic_post',
            default    => null,
        };

        if ($field === null) {
            return false;
        }

        return ! empty($rules[$menuKey][$field]);
    }

    /**
     * Cek apakah kolom tertentu pada tabel menu ini disembunyikan untuk role aktif.
     */
    public static function isColumnHidden(string $menuKey, string $columnKey, mixed $arg3 = null, mixed $arg4 = null): bool
    {
        $role = null;
        if (is_string($arg3) && $arg3 !== '') {
            $role = $arg3;
        } elseif (is_string($arg4) && $arg4 !== '') {
            $role = $arg4;
        }
        $role ??= Auth::role() ?? 'observer';

        if ($role === 'poweradmin') {
            return false;
        }

        $rules = self::forRole($role);
        $hidden = $rules[$menuKey]['hidden_columns'] ?? [];

        return in_array($columnKey, $hidden, true);
    }

    /**
     * Daftar semua kolom tersembunyi pada menu untuk role aktif.
     */
    public static function getHiddenColumns(string $menuKey, mixed $arg2 = null, mixed $arg3 = null): array
    {
        $role = null;
        if (is_string($arg2) && $arg2 !== '') {
            $role = $arg2;
        } elseif (is_string($arg3) && $arg3 !== '') {
            $role = $arg3;
        }
        $role ??= Auth::role() ?? 'observer';

        if ($role === 'poweradmin') {
            return [];
        }

        $rules = self::forRole($role);
        return $rules[$menuKey]['hidden_columns'] ?? [];
    }

    /**
     * Menjembatani Auth::can($ability) dengan sistem user rules.
     * Mengembalikan true/false jika ada mapping rule langsung, atau null untuk fallback ke config.
     */
    public static function checkAbility(int $userId, string $role, string $ability): ?bool
    {
        // Power Admin selalu diizinkan untuk manage_users & manage_roles
        if ($role === 'poweradmin' && in_array($ability, ['manage_users', 'manage_roles', 'view_all_groups'], true)) {
            return true;
        }

        return match ($ability) {
            'manage_users'       => self::canAction('users', 'add', $role) || self::canAction('users', 'edit', $role),
            'manage_data'        => self::canAction('items', 'add', $role) || self::canAction('items', 'edit', $role),
            'post_data'          => self::canAction('items', 'post', $role),
            'audit_data'         => self::canAction('audit', 'edit', $role) || self::canAction('audit', 'view', $role),
            'manage_discrepancy' => self::canAction('discrepancies', 'edit', $role),
            'import_data'        => self::canAction('import', 'add', $role),
            'export_data'        => self::canAction('exports', 'view', $role),
            'view_activity'      => self::canAction('activity', 'view', $role),
            'manage_warehouses'  => self::canAction('warehouses', 'edit', $role),
            'manage_groups'      => self::canAction('groups', 'edit', $role),
            default              => null,
        };
    }

    /**
     * Simpan seluruh matriks aturan untuk sebuah ROLE (user_id = 0).
     * Berlaku untuk SEMUA pengguna yang memiliki role tersebut.
     */
    public function saveRulesForRole(string $role, array $menusData): void
    {
        self::$cache = [];

        foreach ($menusData as $menuKey => $item) {
            $menuName = (string) ($item['menu_name'] ?? (self::SYSTEM_MENUS[$menuKey]['name'] ?? $menuKey));
            $canView = ! empty($item['can_view']) ? 1 : 0;
            $canAdd = ! empty($item['can_add']) ? 1 : 0;
            $canEdit = ! empty($item['can_edit']) ? 1 : 0;
            $canDelete = ! empty($item['can_delete']) ? 1 : 0;
            $canPost = ! empty($item['can_post']) ? 1 : 0;
            $canPicPost = ! empty($item['can_pic_post']) ? 1 : 0;
            $isCustom = ! empty($item['is_custom']) ? 1 : 0;

            $hiddenCols = $item['hidden_columns'] ?? [];
            if (is_array($hiddenCols)) {
                $hiddenColsStr = json_encode(array_values(array_unique(array_filter($hiddenCols))));
            } else {
                $hiddenColsStr = (string) $hiddenCols;
            }

            $this->run(
                'INSERT INTO user_rules 
                    (user_id, role, menu_key, menu_name, can_view, can_add, can_edit, can_delete, can_post, can_pic_post, hidden_columns, is_custom)
                 VALUES (0, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE
                    menu_name = VALUES(menu_name),
                    can_view = VALUES(can_view),
                    can_add = VALUES(can_add),
                    can_edit = VALUES(can_edit),
                    can_delete = VALUES(can_delete),
                    can_post = VALUES(can_post),
                    can_pic_post = VALUES(can_pic_post),
                    hidden_columns = VALUES(hidden_columns),
                    is_custom = VALUES(is_custom),
                    updated_at = NOW()',
                [
                    $role,
                    $menuKey,
                    $menuName,
                    $canView,
                    $canAdd,
                    $canEdit,
                    $canDelete,
                    $canPost,
                    $canPicPost,
                    $hiddenColsStr,
                    $isCustom,
                ]
            );
        }
    }

    /**
     * Backward-compatibility: simpan rules yang diarahkan ke role.
     */
    public function saveRulesForUser(int $userId, string $role, array $menusData, bool $applyToRole = false): void
    {
        $this->saveRulesForRole($role, $menusData);
    }

    /**
     * Simpan rule satu menu untuk sebuah role.
     */
    public function saveSingleMenuRule(string $role, string $menuKey, array $data): void
    {
        self::$cache = [];

        $menuName = (string) ($data['menu_name'] ?? (self::SYSTEM_MENUS[$menuKey]['name'] ?? $menuKey));
        $canView = ! empty($data['can_view']) ? 1 : 0;
        $canAdd = ! empty($data['can_add']) ? 1 : 0;
        $canEdit = ! empty($data['can_edit']) ? 1 : 0;
        $canDelete = ! empty($data['can_delete']) ? 1 : 0;
        $canPost = ! empty($data['can_post']) ? 1 : 0;
        $canPicPost = ! empty($data['can_pic_post']) ? 1 : 0;
        $isCustom = ! empty($data['is_custom']) ? 1 : (isset(self::SYSTEM_MENUS[$menuKey]) ? 0 : 1);

        $hiddenCols = $data['hidden_columns'] ?? [];
        if (is_array($hiddenCols)) {
            $hiddenColsStr = json_encode(array_values(array_unique(array_filter($hiddenCols))));
        } else {
            $hiddenColsStr = (string) $hiddenCols;
        }

        $this->run(
            'INSERT INTO user_rules 
                (user_id, role, menu_key, menu_name, can_view, can_add, can_edit, can_delete, can_post, can_pic_post, hidden_columns, is_custom)
             VALUES (0, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                menu_name = VALUES(menu_name),
                can_view = VALUES(can_view),
                can_add = VALUES(can_add),
                can_edit = VALUES(can_edit),
                can_delete = VALUES(can_delete),
                can_post = VALUES(can_post),
                can_pic_post = VALUES(can_pic_post),
                hidden_columns = VALUES(hidden_columns),
                is_custom = VALUES(is_custom),
                updated_at = NOW()',
            [
                $role,
                $menuKey,
                $menuName,
                $canView,
                $canAdd,
                $canEdit,
                $canDelete,
                $canPost,
                $canPicPost,
                $hiddenColsStr,
                $isCustom,
            ]
        );
    }

    /**
     * Hapus rule spesifik user agar kembali mengikuti template role.
     */
    public function resetUserToRoleDefaults(int $userId): void
    {
        self::$cache = [];
        $this->run('DELETE FROM user_rules WHERE user_id = ?', [$userId]);
    }

    /**
     * Hapus satu custom menu untuk role tertentu.
     */
    public function deleteCustomRule(string $role, string $menuKey): void
    {
        self::$cache = [];
        $this->run('DELETE FROM user_rules WHERE user_id = 0 AND role = ? AND menu_key = ?', [$role, $menuKey]);
    }

    /**
     * Default fallback logic per role jika belum disimpan di DB.
     */
    public static function getDefaultRuleForRole(string $role, string $menuKey, string $menuName): array
    {
        $canView = 1;
        $canAdd = 0;
        $canEdit = 0;
        $canDelete = 0;
        $canPost = 0;
        $canPicPost = 0;
        $hiddenCols = '';

        if ($role === 'poweradmin') {
            return [
                'menu_name'      => $menuName,
                'can_view'       => 1,
                'can_add'        => 1,
                'can_edit'       => 1,
                'can_delete'     => 1,
                'can_post'       => 1,
                'can_pic_post'   => 1,
                'hidden_columns' => '[]',
            ];
        }

        if ($role === 'adminsto') {
            $adminMenus = [
                'dashboard', 'progress', 'items', 'items/memo', 'audit', 'tahun-buku', 'reports', 'discrepancies',
                'reconciliation', 'reconciliation/audit', 'notifications', 'master-data',
                'groups', 'locations', 'devices', 'import', 'exports', 'activity', 'profile',
            ];
            $canView = in_array($menuKey, $adminMenus, true) ? 1 : 0;
            $canAdd = in_array($menuKey, ['items', 'items/memo', 'tahun-buku', 'groups', 'locations', 'devices', 'import'], true) ? 1 : 0;
            $canEdit = in_array($menuKey, ['items', 'items/memo', 'tahun-buku', 'discrepancies', 'reconciliation', 'groups', 'locations', 'devices', 'profile'], true) ? 1 : 0;
            $canDelete = in_array($menuKey, ['items', 'locations', 'devices'], true) ? 1 : 0;
            $canPost = in_array($menuKey, ['items', 'items/memo', 'reconciliation'], true) ? 1 : 0;
            $canPicPost = in_array($menuKey, ['items', 'items/memo'], true) ? 1 : 0;
        } elseif ($role === 'audit') {
            $auditMenus = ['dashboard', 'progress', 'items', 'items/memo', 'audit', 'discrepancies', 'notifications', 'master-data', 'profile'];
            $canView = in_array($menuKey, $auditMenus, true) ? 1 : 0;
            $canAdd = in_array($menuKey, ['audit'], true) ? 1 : 0;
            $canEdit = in_array($menuKey, ['audit', 'discrepancies', 'profile'], true) ? 1 : 0;
            if ($menuKey === 'items' || $menuKey === 'discrepancies') {
                $hiddenCols = '["expected"]';
            }
        } elseif ($role === 'observer') {
            $observerMenus = ['dashboard', 'progress', 'items', 'items/memo', 'discrepancies', 'notifications', 'master-data', 'profile'];
            $canView = in_array($menuKey, $observerMenus, true) ? 1 : 0;
            $canEdit = in_array($menuKey, ['profile'], true) ? 1 : 0;
            $canPost = in_array($menuKey, ['items', 'items/memo'], true) ? 1 : 0;
            $canPicPost = in_array($menuKey, ['items', 'items/memo'], true) ? 1 : 0;
            if ($menuKey === 'items') {
                $hiddenCols = '["expected","price","diff_amount"]';
            } elseif ($menuKey === 'discrepancies') {
                $hiddenCols = '["expected","difference","diff_amount"]';
            }
        }

        return [
            'menu_name'      => $menuName,
            'can_view'       => $canView,
            'can_add'        => $canAdd,
            'can_edit'       => $canEdit,
            'can_delete'     => $canDelete,
            'can_post'       => $canPost,
            'can_pic_post'   => $canPicPost,
            'hidden_columns' => $hiddenCols ?: '[]',
        ];
    }
}
