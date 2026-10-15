<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\Database;
use RuntimeException;

/**
 * Import SATU file xlsx sebagai master lengkap:
 *   gudang -> group -> petugas -> lokasi -> tablet -> item
 * Setiap sheet opsional. Urutan dijamin supaya relasi (FK by code) selalu sudah ada.
 *
 * Mode dry-run: seluruh proses dijalankan di dalam transaksi lalu di-ROLLBACK,
 * sehingga laporan akurat tanpa menulis apa pun.
 */
class MasterWorkbookImportService
{
    /** Sheet dikenali dari nama (lowercase). */
    public const SHEETS = ['karyawan', 'master', 'gudang', 'group', 'petugas', 'lokasi', 'tablet', 'item'];

    /** Role petugas yang boleh diimpor dari sheet petugas. */
    private const PETUGAS_ROLES = ['observer', 'audit', 'adminsto'];

    /** @var array<string, array{created:int,updated:int,failed:int,errors:list<string>}> */
    private array $report = [];
    
    private int $progressSeq = 0;
    private ?string $progressFile = null;

    public function __construct(private ?ItemImportService $items = null)
    {
        $sessionId = session_id();
        if ($sessionId) {
            $this->progressFile = sys_get_temp_dir() . "/stockforge_import_progress_{$sessionId}.json";
        }
    }
    
    private function updateProgress(int $current, int $total, string $sheet, string $status = 'processing'): void
    {
        if ($this->progressFile === null) {
            return;
        }
        $this->progressSeq++;
        $data = [
            'seq' => $this->progressSeq,
            'current' => $current,
            'total' => $total,
            'sheet' => $sheet,
            'status' => $status,
            'percent' => $total > 0 ? round(($current / $total) * 100, 1) : 0,
            'done' => false,
            'timestamp' => time(),
        ];
        file_put_contents($this->progressFile, json_encode($data));
    }
    
    private function finalizeProgress(bool $success = true): void
    {
        if ($this->progressFile === null) {
            return;
        }
        $this->progressSeq++;
        $data = [
            'seq' => $this->progressSeq,
            'done' => true,
            'success' => $success,
            'timestamp' => time(),
        ];
        file_put_contents($this->progressFile, json_encode($data));
    }

    /**
     * @return array{dry_run:bool,sheets:list<string>,report:array<string,array{created:int,updated:int,failed:int,errors:list<string>}>}
     */
    public function run(string $path, bool $dryRun, int $userId, ?int $defaultGroupId = null): array
    {
        $sheets = (new MasterImportService())->readSheets($path);
        $present = array_values(array_intersect(self::SHEETS, array_keys($sheets)));
        $this->report = [];
        foreach ($present as $s) {
            $this->report[$s] = ['created' => 0, 'updated' => 0, 'failed' => 0, 'errors' => []];
        }

        if ($present === []) {
            throw new RuntimeException('Tidak ada sheet yang dikenali. Nama sheet harus salah satu dari: ' . implode(', ', self::SHEETS) . '.');
        }

        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            // Sheet 'karyawan' WAJIB diproses lebih dulu: jadi sumber lookup
            // employee_id -> name untuk sheet 'master' dan 'petugas'.
            if (isset($sheets['karyawan'])) {
                $this->report['karyawan'] = (new EmployeeImportService())->importRows($sheets['karyawan'], $userId);
            }

            if (isset($sheets['master'])) {
                $this->importMaster($sheets['master'], $userId, $defaultGroupId);
                $present = array_values(array_unique(array_merge(
                    isset($sheets['karyawan']) ? ['karyawan'] : [],
                    ['master']
                )));
                $sheets = [];
            }
            foreach (['gudang', 'group', 'petugas', 'lokasi', 'tablet'] as $s) {
                if (isset($sheets[$s])) {
                    foreach ($sheets[$s] as $i => $row) {
                        $this->{'row' . ucfirst($s)}($row, $i + 2, $userId);
                    }
                }
            }
            // Sheet item: pakai ItemImportService (alias kolom, lock, unit, dsb.) dengan baris dari sheet bernama.
            if (isset($sheets['item'])) {
                $this->report['item'] = $this->items()->importRows($sheets['item'], $defaultGroupId, $userId);
            }
            
            $this->finalizeProgress(true);
        } catch (\Throwable $e) {
            $pdo->rollBack();
            $this->finalizeProgress(false);
            throw $e;
        }

        if ($dryRun) {
            $pdo->rollBack();
        } else {
            $pdo->commit();
        }

        return ['dry_run' => $dryRun, 'sheets' => $present, 'report' => $this->report];
    }

    /**
     * Format 1-sheet "master": 1 baris = 1 item, dengan gudang, group, petugas,
     * lokasi, tablet, dan item dalam baris yang sama (denormalisasi penuh).
     * Setiap baris dipecah ke sub-array per entitas lalu memakai rowX yang sama,
     * sehingga aturan validasi dan upsert tetap satu sumber kebenaran.
     */
    private function importMaster(array $rows, int $userId, ?int $defaultGroupId): void
    {
        foreach (['periode', 'gudang', 'group', 'petugas', 'lokasi', 'tablet', 'item'] as $s) {
            $this->report[$s] = ['created' => 0, 'updated' => 0, 'failed' => 0, 'errors' => []];
        }
        $itemRows = [];
        $total = count($rows);
        $processedPeriods = []; // Track processed periods to avoid duplicates
        
        foreach ($rows as $i => $r) {
            $line = $i + 2;
            
            // Handle periode first (before any other entities)
            $periodMonth = trim((string) ($r['period_month'] ?? ''));
            $periodStart = trim((string) ($r['period_start'] ?? ''));
            $periodEnd = trim((string) ($r['period_end'] ?? ''));
            
            if ($periodMonth !== '' && !isset($processedPeriods[$periodMonth])) {
                $this->rowPeriode([
                    'audit_month' => $periodMonth,
                    'audit_start' => $periodStart,
                    'audit_end' => $periodEnd
                ], $line, $userId);
                $processedPeriods[$periodMonth] = true;
            }
            
            // Update progress setiap 50 baris atau di akhir
            if ($i % 50 === 0 || $i === $total - 1) {
                $this->updateProgress($i + 1, $total, 'master', 'processing');
            }
            
            // Alias mapping: support multiple column name formats (old + new user format)
            $aliases = [
                'group_kode' => $r['group_kode'] ?? $r['group_code'] ?? '',
                'group_nama' => $r['group_nama'] ?? $r['group_name'] ?? $r['name'] ?? $r['nama group'] ?? '',
                'gudang_kode' => $r['gudang_kode'] ?? $r['warehouse_code'] ?? $r['gudang'] ?? '',
                'gudang_nama' => $r['gudang_nama'] ?? $r['warehouse_name'] ?? '',
                'gudang_kota' => $r['gudang_kota'] ?? $r['warehouse_city'] ?? '',
                'gudang_alamat' => $r['gudang_alamat'] ?? $r['warehouse_address'] ?? '',
                'group_deskripsi' => $r['group_deskripsi'] ?? $r['group_description'] ?? '',
                'item_kode' => $r['item_kode'] ?? $r['item_code'] ?? $r['code'] ?? $r['no item'] ?? '',
                'item_id_barang' => $r['item_id_barang'] ?? $r['id barang'] ?? '',
                'item_nama' => $r['item_nama'] ?? $r['item_name'] ?? $r['nama barang'] ?? '',
                'item_serial' => $r['item_serial'] ?? $r['item_serial_number'] ?? $r['serial(jika ada)'] ?? $r['serial'] ?? '',
                'item_stok' => $r['item_stok'] ?? $r['item_expected_qty'] ?? $r['expected_qty'] ?? $r['system qty'] ?? '',
                'item_harga' => $r['item_harga'] ?? $r['item_unit_price'] ?? $r['unit_price'] ?? $r['harga'] ?? '',
                'item_kategori' => $r['item_kategori'] ?? $r['item_category'] ?? $r['category'] ?? '',
                'item_business_unit' => $r['item_business_unit'] ?? $r['business unit'] ?? '',
                'item_sub_inventory' => $r['item_sub_inventory'] ?? $r['sub inventory'] ?? $r['sub_inventory'] ?? '',
                'item_satuan' => $r['item_satuan'] ?? $r['item_unit'] ?? $r['unit'] ?? '',
                'lokasi_kode' => $r['lokasi_kode'] ?? $r['location_code'] ?? $r['item_location_code'] ?? $r['lokasi item'] ?? '',
                'lokasi_nama' => $r['lokasi_nama'] ?? $r['location_name'] ?? $r['group lokasi'] ?? '',
                'tablet_nama' => $r['tablet_nama'] ?? $r['device_name'] ?? $r['nama tablet'] ?? '',
                'tablet_kode' => $r['tablet_kode'] ?? $r['device_code'] ?? '',
                'tablet_tipe' => $r['tablet_tipe'] ?? $r['device_type'] ?? '',
                'tablet_serial' => $r['tablet_serial'] ?? $r['device_serial_number'] ?? '',
                'petugas_auditor_id' => $r['petugas_auditor_id'] ?? $r['user_employee_id'] ?? $r['user id auditor'] ?? $r['id auditor'] ?? '',
                'petugas_auditor_nama' => $r['petugas_auditor_nama'] ?? $r['nama auditor'] ?? '',
                'petugas_observer_id' => $r['petugas_observer_id'] ?? $r['observer_employee_id'] ?? $r['user id observer'] ?? $r['id observer'] ?? '',
                'petugas_observer_nama' => $r['petugas_observer_nama'] ?? $r['nama observer'] ?? '',
            ];
            $g = fn (string $k): string => trim((string) ($aliases[$k] ?? $r[$k] ?? ''));

            // Resolve group: try code first, fallback to name
            $groupCodeOrName = $g('group_kode') ?: $g('group_nama');
            $groupResolved = $groupCodeOrName !== '' ? $this->resolveGroupByCodeOrName($groupCodeOrName) : null;
            $groupCode = $groupResolved ? $groupResolved[1] : '';
            
            if ($g('gudang_kode') !== '') {
                $this->rowGudang(['code' => $g('gudang_kode'), 'name' => $g('gudang_nama'), 'city' => $g('gudang_kota'), 'address' => $g('gudang_alamat')], $line, $userId);
            }
            if ($groupCode !== '') {
                $this->rowGroup(['group_code' => $groupCode, 'name' => $g('group_nama') ?: $groupCodeOrName, 'gudang_code' => $g('gudang_kode'), 'description' => $g('group_deskripsi')], $line, $userId);
            }
            foreach (['observer' => $g('petugas_observer_id'), 'audit' => $g('petugas_auditor_id')] as $role => $employeeId) {
                if ($employeeId !== '') {
                    // Nama di-lookup dari master karyawan. Bila tidak ada, tolak baris
                    // supaya tidak lahir petugas "hantu" tanpa data karyawan.
                    $employeeName = $this->employeeName($employeeId);
                    if ($employeeName === null) {
                        $this->fail('petugas', $line, "ID Karyawan {$role} '{$employeeId}' tidak ada di master karyawan. Tambahkan sheet 'karyawan' atau import karyawan lebih dulu.");
                        continue;
                    }
                    $this->rowPetugas([
                        'username' => $employeeId,
                        'name' => $employeeName,
                        'role' => $role,
                        'email' => '',
                        'group_codes' => '',
                    ], $line, $userId);
                    // Tambah keanggotaan group tanpa menghapus group lain milik user ini.
                    $gid = $this->groupIdFor($groupCode);
                    $uid = $this->scalar('SELECT id FROM users WHERE username=? LIMIT 1', [$employeeId]);
                    if ($gid !== null && $uid !== null) {
                        Database::connection()->prepare('INSERT IGNORE INTO user_groups (user_id,group_id,created_at) VALUES (?,?,NOW())')
                            ->execute([(int) $uid, (int) $gid]);
                    }
                }
            }
            if ($g('lokasi_kode') !== '') {
                $this->rowLokasi([
                    'group_code' => $groupCode,
                    'location_code' => $g('lokasi_kode'),
                    'name' => $g('lokasi_nama') ?: $g('lokasi_kode'),
                    'address' => '',
                ], $line, $userId);
            }
            if ($g('tablet_kode') !== '') {
                $this->rowTablet([
                    'group_code' => $groupCode,
                    'location_code' => $g('lokasi_kode'),
                    'device_code' => $g('tablet_kode'),
                    'name' => $g('tablet_nama') ?: $g('tablet_kode'),
                    'type' => $g('tablet_tipe'),
                    'serial_number' => $g('tablet_serial'),
                ], $line, $userId);
            }
            if ($g('item_kode') !== '' || $g('item_nama') !== '') {
                $itemRows[] = [
                    'group_code' => $groupCode, 
                    'code' => $g('item_kode'), 
                    'id_barang' => $g('item_id_barang'),
                    'name' => $g('item_nama'),
                    'serial' => $g('item_serial'),
                    'category' => $g('item_kategori'), 
                    'business_unit' => $g('item_business_unit'),
                    'sub_inventory' => $g('item_sub_inventory'),
                    'unit' => $g('item_satuan'),
                    'unit_price' => $g('item_harga'), 
                    'expected_qty' => $g('item_stok'),
                    'location_code' => $g('lokasi_kode'), 
                    'device_code' => $g('tablet_kode'),
                ];
            }
        }
        if ($itemRows !== []) {
            $this->report['item'] = $this->items()->importRows($itemRows, $defaultGroupId, $userId);
        }
    }

    private function items(): ItemImportService
    {
        return $this->items ??= new ItemImportService();
    }

    /* ---------- periode ---------- */
    private function rowPeriode(array $row, int $line, int $userId): void
    {
        $month = trim($row['audit_month'] ?? '');
        $start = trim($row['audit_start'] ?? '');
        $end = trim($row['audit_end'] ?? '');
        
        if ($month === '' || $start === '' || $end === '') {
            $this->fail('periode', $line, 'audit_month, audit_start, dan audit_end wajib diisi.');
            return;
        }
        
        // Validate format YYYY-MM
        if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
            $this->fail('periode', $line, 'audit_month harus format YYYY-MM (misal: 2026-10).');
            return;
        }
        
        // Validate date format YYYY-MM-DD
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $start) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $end)) {
            $this->fail('periode', $line, 'audit_start dan audit_end harus format YYYY-MM-DD.');
            return;
        }
        
        $pdo = Database::connection();
        
        // Check if period already exists
        $stmt = $pdo->prepare('SELECT id FROM audit_periods WHERE audit_month = ?');
        $stmt->execute([$month]);
        $existing = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        if ($existing) {
            $this->report['periode']['updated']++;
            // Update existing period (only if still open)
            $stmt = $pdo->prepare(
                "UPDATE audit_periods 
                 SET audit_start = ?, audit_end = ?, updated_at = NOW() 
                 WHERE audit_month = ? AND status = 'open'"
            );
            $stmt->execute([$start, $end, $month]);
        } else {
            $this->report['periode']['created']++;
            // Create new period with status open and NOT active (user must activate manually)
            $stmt = $pdo->prepare(
                "INSERT INTO audit_periods 
                 (audit_month, audit_start, audit_end, status, is_active, created_by, created_at, updated_at) 
                 VALUES (?, ?, ?, 'open', 0, ?, NOW(), NOW())"
            );
            $stmt->execute([$month, $start, $end, $userId]);
        }
    }

    /* ---------- gudang ---------- */
    private function rowGudang(array $row, int $line, int $userId): void
    {
        $code = $this->up($row, 'code');
        $name = $this->v($row, 'name');
        if ($code === '' || $name === '') {
            { $this->fail('gudang', $line, 'code dan name wajib diisi.'); return; }
        }
        $pdo = Database::connection();
        $st = $pdo->prepare('SELECT id FROM warehouses WHERE UPPER(code) = ? LIMIT 1');
        $st->execute([$code]);
        $id = $st->fetchColumn();
        $city = $this->v($row, 'city');
        $addr = $this->v($row, 'address') ?: null;
        if ($id === false) {
            $pdo->prepare('INSERT INTO warehouses (code,name,city,address,is_active,created_at,updated_at) VALUES (?,?,?,?,1,NOW(),NOW())')
                ->execute([$code, $name, $city ?: null, $addr]);
            $this->ok('gudang', 'created');
        } else {
            $pdo->prepare('UPDATE warehouses SET name=?, city=COALESCE(NULLIF(?,""),city), address=COALESCE(NULLIF(?,""),address), updated_at=NOW() WHERE id=?')
                ->execute([$name, $city, (string) $addr, (int) $id]);
            $this->ok('gudang', 'updated');
        }
    }

    /* ---------- group ---------- */
    private function rowGroup(array $row, int $line, int $userId): void
    {
        $code = $this->up($row, 'group_code');
        $name = $this->v($row, 'name');
        if ($code === '' || $name === '') {
            { $this->fail('group', $line, 'group_code dan name wajib diisi.'); return; }
        }
        $wh = $this->up($row, 'gudang_code');
        $whId = null;
        if ($wh !== '') {
            $whId = $this->scalar('SELECT id FROM warehouses WHERE UPPER(code)=? LIMIT 1', [$wh]);
            if ($whId === null) {
                { $this->fail('group', $line, "gudang_code '{$wh}' tidak ditemukan (isi sheet gudang dulu)."); return; }
            }
        }
        $pdo = Database::connection();
        $id = $this->scalar('SELECT id FROM `groups` WHERE UPPER(code)=? LIMIT 1', [$code]);
        if ($id === null) {
            $pdo->prepare('INSERT INTO `groups` (warehouse_id,code,name,description,is_active,created_at,updated_at) VALUES (?,?,?,?,1,NOW(),NOW())')
                ->execute([$whId, $code, $name, $this->v($row, 'description') ?: null]);
            $this->ok('group', 'created');
        } else {
            $pdo->prepare('UPDATE `groups` SET name=?, warehouse_id=COALESCE(?,warehouse_id), description=COALESCE(NULLIF(?,""),description), updated_at=NOW() WHERE id=?')
                ->execute([$name, $whId, $this->v($row, 'description'), (int) $id]);
            $this->ok('group', 'updated');
        }
    }

    /* ---------- petugas (observer / auditor / admin group) ---------- */
    private function rowPetugas(array $row, int $line, int $userId): void
    {
        $username = $this->v($row, 'username');
        $name = $this->v($row, 'name');
        $role = strtolower($this->v($row, 'role'));
        if ($username === '' || $name === '' || $role === '') {
            { $this->fail('petugas', $line, 'username, name, dan role wajib diisi.'); return; }
        }
        if (! in_array($role, self::PETUGAS_ROLES, true)) {
            { $this->fail('petugas', $line, "role '{$role}' tidak valid (pakai: observer, audit, adminsto)."); return; }
        }
        $pdo = Database::connection();
        $uid = $this->scalar('SELECT id FROM users WHERE username=? LIMIT 1', [$username]);
        $email = $this->v($row, 'email') ?: null;
        $phone = $this->v($row, 'phone') ?: null;
        if ($uid === null) {
            // Akun baru: password diisi hash acak; user diset via reset/login dev switcher.
            $hash = password_hash(bin2hex(random_bytes(16)), PASSWORD_BCRYPT);
            $pdo->prepare('INSERT INTO users (name,username,email,phone,password,role,is_active,created_at,updated_at) VALUES (?,?,?,?,?,?,0,NOW(),NOW())')
                ->execute([$name, $username, $email, $phone, $hash, $role]);
            $uid = (int) $pdo->lastInsertId();
            $this->ok('petugas', 'created');
        } else {
            $pdo->prepare('UPDATE users SET name=?, email=COALESCE(?,email), phone=COALESCE(?,phone), role=?, updated_at=NOW() WHERE id=?')
                ->execute([$name, $email, $phone, $role, (int) $uid]);
            $this->ok('petugas', 'updated');
        }

        // group_codes: "GRP-A;GRP-B" -> user_groups (sinkronisasi penuh untuk user ini)
        $codesRaw = $this->up($row, 'group_codes');
        if ($codesRaw !== '') {
            $codes = array_filter(array_map('trim', explode(';', $codesRaw)));
            $pdo->prepare('DELETE FROM user_groups WHERE user_id=?')->execute([(int) $uid]);
            foreach ($codes as $c) {
                $gid = $this->scalar('SELECT id FROM `groups` WHERE UPPER(code)=? LIMIT 1', [strtoupper($c)]);
                if ($gid === null) {
                    $this->fail('petugas', $line, "group '{$c}' tidak ditemukan, dilewati.");
                    continue;
                }
                $pdo->prepare('INSERT IGNORE INTO user_groups (user_id,group_id,created_at) VALUES (?,?,NOW())')
                    ->execute([(int) $uid, (int) $gid]);
            }
        }
    }

    /* ---------- lokasi ---------- */
    private function rowLokasi(array $row, int $line, int $userId): void
    {
        $gcode = $this->up($row, 'group_code');
        $lcode = $this->up($row, 'location_code');
        $name = $this->v($row, 'name');
        if ($lcode === '' || $name === '') {
            { $this->fail('lokasi', $line, 'location_code dan name wajib diisi.'); return; }
        }
        $gid = $this->groupIdFor($gcode);
        if ($gid === null) {
            { $this->fail('lokasi', $line, "group '{$gcode}' tidak ditemukan atau bukan wewenang Anda."); return; }
        }
        $pdo = Database::connection();
        $id = $this->scalar('SELECT id FROM locations WHERE group_id=? AND UPPER(code)=? LIMIT 1', [$gid, $lcode]);
        $addr = $this->v($row, 'address') ?: null;
        if ($id === null) {
            $pdo->prepare('INSERT INTO locations (group_id,code,name,address,is_active,created_at,updated_at) VALUES (?,?,?,?,1,NOW(),NOW())')
                ->execute([$gid, $lcode, $name, $addr]);
            $this->ok('lokasi', 'created');
        } else {
            $pdo->prepare('UPDATE locations SET name=?, address=COALESCE(?,address), updated_at=NOW() WHERE id=?')
                ->execute([$name, $addr, (int) $id]);
            $this->ok('lokasi', 'updated');
        }
    }

    /* ---------- tablet / device ---------- */
    private function rowTablet(array $row, int $line, int $userId): void
    {
        $gcode = $this->up($row, 'group_code');
        $lcode = $this->up($row, 'location_code');
        $dcode = $this->up($row, 'device_code');
        $name = $this->v($row, 'name');
        if ($dcode === '' || $name === '') {
            { $this->fail('tablet', $line, 'device_code dan name wajib diisi.'); return; }
        }
        $gid = $this->groupIdFor($gcode);
        if ($gid === null) {
            { $this->fail('tablet', $line, "group '{$gcode}' tidak ditemukan atau bukan wewenang Anda."); return; }
        }
        $locId = $this->scalar('SELECT id FROM locations WHERE group_id=? AND UPPER(code)=? LIMIT 1', [$gid, $lcode]);
        if ($locId === null) {
            { $this->fail('tablet', $line, "location_code '{$lcode}' tidak ada di group {$gcode}."); return; }
        }
        $type = $this->v($row, 'type') ?: 'tablet';
        $serial = $this->v($row, 'serial_number') ?: null;
        $pdo = Database::connection();
        $id = $this->scalar('SELECT id FROM devices WHERE UPPER(code)=? LIMIT 1', [$dcode]);
        if ($id === null) {
            $pdo->prepare('INSERT INTO devices (location_id,code,name,type,serial_number,is_active,created_at,updated_at) VALUES (?,?,?,?,?,1,NOW(),NOW())')
                ->execute([(int) $locId, $dcode, $name, $type, $serial]);
            $this->ok('tablet', 'created');
        } else {
            $pdo->prepare('UPDATE devices SET location_id=?, name=?, type=?, serial_number=COALESCE(?,serial_number), updated_at=NOW() WHERE id=?')
                ->execute([(int) $locId, $name, $type, $serial, (int) $id]);
            $this->ok('tablet', 'updated');
        }
    }

    /* ---------- helpers ---------- */
    /**
     * Lookup nama karyawan dari master karyawan (tabel employees).
     * Mengembalikan nama bila ditemukan & aktif, selain itu null.
     */
    private function employeeName(string $employeeId): ?string
    {
        $employeeId = strtoupper(trim($employeeId));
        if ($employeeId === '') {
            return null;
        }

        $stmt = Database::connection()->prepare(
            'SELECT name FROM employees WHERE UPPER(employee_id) = ? LIMIT 1'
        );
        $stmt->execute([$employeeId]);
        $name = $stmt->fetchColumn();

        return $name === false ? null : (string) $name;
    }

    /** Group yang boleh diakses user; kode kosong ditolak (tidak ada default di mode master). */
    private function groupIdFor(string $code): ?int
    {
        if ($code === '') {
            return null;
        }
        $id = $this->scalar('SELECT id FROM `groups` WHERE UPPER(code)=? LIMIT 1', [$code]);

        return ($id !== null && Auth::canSeeGroup((int) $id)) ? (int) $id : null;
    }

    /** Resolve group by code OR name. Returns [id, code] or null. */
    private function resolveGroupByCodeOrName(string $codeOrName): ?array
    {
        if ($codeOrName === '') {
            return null;
        }
        $pdo = Database::connection();
        // Try by code first
        $st = $pdo->prepare('SELECT id, code FROM `groups` WHERE UPPER(code)=? LIMIT 1');
        $st->execute([strtoupper($codeOrName)]);
        $row = $st->fetch(\PDO::FETCH_ASSOC);
        if ($row && Auth::canSeeGroup((int) $row['id'])) {
            return [(int) $row['id'], (string) $row['code']];
        }
        // Fallback: try by name
        $st = $pdo->prepare('SELECT id, code FROM `groups` WHERE UPPER(name)=? LIMIT 1');
        $st->execute([strtoupper($codeOrName)]);
        $row = $st->fetch(\PDO::FETCH_ASSOC);
        if ($row && Auth::canSeeGroup((int) $row['id'])) {
            return [(int) $row['id'], (string) $row['code']];
        }
        return null;
    }

    private function scalar(string $sql, array $args): ?int
    {
        $st = Database::connection()->prepare($sql);
        $st->execute($args);
        $v = $st->fetchColumn();

        return $v === false ? null : (int) $v;
    }

    private function v(array $row, string $k): string
    {
        return trim((string) ($row[$k] ?? ''));
    }

    private function up(array $row, string $k): string
    {
        return strtoupper($this->v($row, $k));
    }

    private function ok(string $sheet, string $kind): void
    {
        $this->report[$sheet][$kind]++;
    }

    private function fail(string $sheet, int $line, string $msg): void
    {
        $this->report[$sheet]['failed']++;
        $this->report[$sheet]['errors'][] = "{$sheet} baris {$line}: {$msg}";
    }
}
