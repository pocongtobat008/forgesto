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
 * Sheet 'group' tidak wajib: bila tidak ada, group dibuat dari group_code +
 * group_name pada sheet master/karyawan.
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

    /**
     * Peta group_code -> petugas & device, dibangun dari sheet 'karyawan'.
     * Dipakai supaya sheet 'master' tidak perlu lagi memuat kolom petugas/device:
     * semuanya di-mapping lewat group_code.
     *
     * @var array<string, array{device_code:string, device_name:string, auditors:list<string>, observers:list<string>}>
     */
    private array $groupStaff = [];

    /** Penugasan petugas yang sudah diproses (role|employee|group) agar tidak diulang tiap baris. */
    private array $petugasDone = [];

    /** Device per group yang sudah diproses (group|device) agar upsert tidak diulang tiap baris. */
    private array $tabletDone = [];



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
        // 'periode' bukan sheet (diturunkan dari sheet master) tapi tetap dilaporkan.
        $this->report['periode'] = ['created' => 0, 'updated' => 0, 'failed' => 0, 'errors' => []];

        if ($present === []) {
            throw new RuntimeException('Tidak ada sheet yang dikenali. Nama sheet harus salah satu dari: ' . implode(', ', self::SHEETS) . '.');
        }

        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            // Gudang yang disebut di sheet 'master' dibuat lebih dulu supaya sheet
            // 'group' (dan baris master itu sendiri) langsung bisa menempel ke gudangnya.
            if (isset($sheets['master'])) {
                $this->preCreateWarehouses($sheets['master'], $userId);
            }

            // Sheet 'group' opsional. Bila tidak ada, group diturunkan dari
            // group_code + group_name di sheet master/karyawan (tidak perlu sheet group).
            if (! isset($sheets['group'])) {
                $this->ensureGroupsFromSheets($sheets, $userId);
            }

            // Urutan: gudang -> group -> karyawan -> master.
            // Group harus ada sebelum karyawan (keanggotaan) dan sebelum master (lokasi/item).
            foreach (['gudang', 'group'] as $s) {
                if (isset($sheets[$s])) {
                    foreach ($sheets[$s] as $i => $row) {
                        $this->{'row' . ucfirst($s)}($row, $i + 2, $userId);
                    }
                    unset($sheets[$s]);
                }
            }

            // Sheet 'karyawan': sumber lookup employee_id -> name dan peta
            // group -> petugas & device untuk sheet 'master'.
            if (isset($sheets['karyawan'])) {
                $this->report['karyawan'] = (new EmployeeImportService())->importRows($sheets['karyawan'], $userId);
                $this->buildGroupStaffMap($sheets['karyawan']);
                // PIC group boleh ditulis di kolom pic_employee_id sheet karyawan
                // (sekali per group); dijalankan setelah karyawan ada supaya namanya ketemu.
                $this->applyGroupPicFromKaryawan($sheets['karyawan']);
                unset($sheets['karyawan']);
            }

            if (isset($sheets['master'])) {
                $this->importMaster($sheets['master'], $userId, $defaultGroupId);
                unset($sheets['master']);
            }

            foreach (['petugas', 'lokasi', 'tablet'] as $s) {
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

        // Section 'master' hanya berisi error baris; sembunyikan bila kosong.
        if (isset($this->report['master'])
            && $this->report['master']['created'] === 0
            && $this->report['master']['updated'] === 0
            && $this->report['master']['failed'] === 0) {
            unset($this->report['master']);
        }

        // Daftar sheet untuk laporan HARUS konsisten dengan kunci yang ada di
        // $this->report: kalau tidak, view mengakses kunci yang tidak ada
        // (mis. 'master' yang baru di-unset di atas) dan memunculkan warning.
        $order   = array_merge(['periode'], self::SHEETS);
        $present = array_values(array_filter($order, function (string $s): bool {
            if (! isset($this->report[$s])) {
                return false;
            }
            // 'periode' bukan sheet: tampilkan hanya bila benar-benar menyentuh data.
            if ($s === 'periode') {
                return $this->report[$s]['created'] > 0 || $this->report[$s]['updated'] > 0;
            }

            return true;
        }));

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
        // Jangan reset counter sheet lain: sheet 'gudang' & 'group' sudah diproses
        // sebelum master, angkanya harus tetap terlihat di laporan.
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
            // Group WAJIB sudah ada di database (tidak dibuat otomatis): lokasi & item
            // tidak bisa ditempel ke group yang belum ada, jadi baris dilaporkan gagal
            // dengan pesan yang jelas alih-alih error berlapis di bawah.
            $groupMissing = false;
            if ($groupCodeOrName === '') {
                $groupMissing = true;
                $this->fail('master', $line, 'group_code kosong. Isi group_code (kode group yang sudah ada di menu Group) agar lokasi & item bisa diimport.');
            } elseif ($groupResolved === null) {
                $groupMissing = true;
                $this->fail('master', $line, "group '{$groupCodeOrName}' tidak ada di database. Buat group dengan kode tersebut di menu Group (kode harus sama persis), lalu import ulang.");
            }
            $groupCode = $groupResolved ? $groupResolved[1] : '';
            
            if ($g('gudang_kode') !== '') {
                $this->rowGudang(['code' => $g('gudang_kode'), 'name' => $g('gudang_nama'), 'city' => $g('gudang_kota'), 'address' => $g('gudang_alamat')], $line, $userId);
            }
            if (! $groupMissing && $groupCode !== '') {
                $this->rowGroup(['group_code' => $groupCode, 'name' => $g('group_nama') ?: $groupCodeOrName, 'gudang_code' => $g('gudang_kode'), 'description' => $g('group_deskripsi')], $line, $userId);
            }
            // Petugas & device: mapping lewat group_code dari sheet 'karyawan'.
            // Kolom lama di sheet 'master' (auditor_employee_id / observer_employee_id /
            // device_code / device_name) tetap didukung bila masih ada isinya.
            $staff = $this->staffForGroup($groupCode, $groupCodeOrName);
            $auditorIds  = $g('petugas_auditor_id')  !== '' ? [$g('petugas_auditor_id')]  : ($staff['auditors']  ?? []);
            $observerIds = $g('petugas_observer_id') !== '' ? [$g('petugas_observer_id')] : ($staff['observers'] ?? []);
            $gid = $this->groupIdFor($groupCode);
            foreach ($groupMissing ? [] : ['observer' => $observerIds, 'audit' => $auditorIds] as $role => $employeeIds) {
                foreach ($employeeIds as $employeeId) {
                    $doneKey = $role . '|' . $employeeId . '|' . (string) $gid;
                    if (isset($this->petugasDone[$doneKey])) {
                        continue;
                    }
                    // Nama di-lookup dari master karyawan. Bila tidak ada, tolak baris
                    // supaya tidak lahir petugas "hantu" tanpa data karyawan.
                    $employeeName = $this->employeeName($employeeId);
                    if ($employeeName === null) {
                        $this->fail('petugas', $line, "ID Karyawan {$role} '{$employeeId}' tidak ada di master karyawan. Tambahkan sheet 'karyawan' atau import karyawan lebih dulu.");
                        continue;
                    }
                    $this->petugasDone[$doneKey] = true;
                    $this->rowPetugas([
                        'username' => $employeeId,
                        'name' => $employeeName,
                        'role' => $role,
                        'email' => '',
                        'group_codes' => '',
                    ], $line, $userId);
                    // Tambah keanggotaan group tanpa menghapus group lain milik user ini.
                    $uid = $this->scalar('SELECT id FROM users WHERE username=? LIMIT 1', [$employeeId]);
                    if ($gid !== null && $uid !== null) {
                        Database::connection()->prepare('INSERT IGNORE INTO user_groups (user_id,group_id,created_at) VALUES (?,?,NOW())')
                            ->execute([(int) $uid, (int) $gid]);
                    }
                }
            }
            if (! $groupMissing && $g('lokasi_kode') !== '') {
                $this->rowLokasi([
                    'group_code' => $groupCode,
                    'location_code' => $g('lokasi_kode'),
                    'name' => $g('lokasi_nama') ?: $g('lokasi_kode'),
                    'address' => '',
                ], $line, $userId);
            }
            // Device: kolom lama di sheet 'master' + semua device group dari sheet 'karyawan'.
            $deviceRows = [];
            if ($g('tablet_kode') !== '') {
                $deviceRows[] = [
                    'code' => $g('tablet_kode'),
                    'name' => $g('tablet_nama') ?: $g('tablet_kode'),
                    'employee' => '',
                    'type' => $g('tablet_tipe'),
                    'serial' => $g('tablet_serial'),
                ];
            }
            foreach ($staff['devices'] ?? [] as $dev) {
                $deviceRows[] = $dev + ['type' => '', 'serial' => ''];
            }
            $tabletKode = $deviceRows !== [] ? $deviceRows[0]['code'] : '';
            foreach ($groupMissing ? [] : $deviceRows as $dev) {
                $tabletKey = $groupCode . '|' . $dev['code'];
                if (isset($this->tabletDone[$tabletKey])) {
                    continue;
                }
                $this->tabletDone[$tabletKey] = true;
                $this->rowTablet([
                    'group_code' => $groupCode,
                    'location_code' => $g('lokasi_kode'),
                    'device_code' => $dev['code'],
                    'name' => $dev['name'] ?: $dev['code'],
                    'type' => $dev['type'],
                    'serial_number' => $dev['serial'],
                    'employee_id' => $dev['employee'] ?? '',
                ], $line, $userId);
            }
            if (! $groupMissing && ($g('item_kode') !== '' || $g('item_nama') !== '')) {
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
                    'device_code' => $tabletKode,
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

    /* ---------- mapping group -> petugas & device (dari sheet karyawan) ---------- */

    /**
     * Bangun peta group_code -> { device_code, device_name, auditors[], observers[] }
     * dari sheet 'karyawan'. Inilah pengganti kolom petugas & device yang dulu
     * ditulis ulang di setiap baris sheet 'master'.
     *
     * Aturan:
     *   - device: baris pertama (paling atas) yang punya device_code untuk group tsb.
     *   - auditor: karyawan role 'audit' pada group tsb.
     *   - observer: karyawan role 'observer' (atau role kosong) pada group tsb.
     *
     * @param list<array<string,string>> $rows
     */
    private function buildGroupStaffMap(array $rows): void
    {
        $this->groupStaff = [];
        foreach ($rows as $r) {
            $gcode = strtoupper(trim((string) ($r['group_code'] ?? $r['kode_group'] ?? $r['group'] ?? '')));
            if ($gcode === '') {
                continue;
            }
            $employeeId = trim((string) ($r['employee_id'] ?? $r['id_karyawan'] ?? $r['id'] ?? $r['nik'] ?? ''));
            $role = strtolower(trim((string) ($r['role'] ?? $r['peran'] ?? '')));
            $deviceCode = trim((string) ($r['device_code'] ?? $r['tablet_kode'] ?? $r['kode_device'] ?? ''));
            $deviceName = trim((string) ($r['device_name'] ?? $r['tablet_nama'] ?? $r['nama_device'] ?? ''));

            $entry = $this->groupStaff[$gcode] ?? ['device_code' => '', 'device_name' => '', 'devices' => [], 'auditors' => [], 'observers' => []];
            if ($deviceCode !== '') {
                // Satu group bisa punya beberapa device (tablet auditor & observer).
                if (! in_array($deviceCode, array_column($entry['devices'], 'code'), true)) {
                    $entry['devices'][] = [
                        'code' => $deviceCode,
                        'name' => $deviceName !== '' ? $deviceName : $deviceCode,
                        'employee' => $employeeId,
                    ];
                }
                if ($entry['device_code'] === '') {
                    $entry['device_code'] = $deviceCode;
                    $entry['device_name'] = $deviceName;
                }
            }
            if ($employeeId !== '') {
                if ($role === '') {
                    $role = 'observer';
                }
                if ($role === 'audit' && ! in_array($employeeId, $entry['auditors'], true)) {
                    $entry['auditors'][] = $employeeId;
                }
                if ($role === 'observer' && ! in_array($employeeId, $entry['observers'], true)) {
                    $entry['observers'][] = $employeeId;
                }
            }
            $this->groupStaff[$gcode] = $entry;
        }
    }

    /**
     * @return array{device_code:string, device_name:string, devices:list<array{code:string,name:string,employee:string}>, auditors:list<string>, observers:list<string>}|null
     */
    private function staffForGroup(string $groupCode, string $fallback = ''): ?array
    {
        foreach ([$groupCode, $fallback] as $key) {
            $k = strtoupper(trim($key));
            if ($k !== '' && isset($this->groupStaff[$k])) {
                return $this->groupStaff[$k];
            }
        }

        return null;
    }

    /**
     * Buat gudang yang belum ada dari kolom warehouse_code di sheet 'master'.
     * Dipakai supaya satu file bisa berisi sheet 'group' + 'master' sekaligus.
     */
    private function preCreateWarehouses(array $rows, int $userId): void
    {
        $seen = [];
        foreach ($rows as $r) {
            $code = strtoupper(trim((string) ($r['warehouse_code'] ?? $r['gudang_kode'] ?? $r['gudang'] ?? '')));
            if ($code === '' || isset($seen[$code])) {
                continue;
            }
            $seen[$code] = true;
            if ($this->scalar('SELECT id FROM warehouses WHERE UPPER(code)=? LIMIT 1', [$code]) !== null) {
                continue;
            }
            $name = trim((string) ($r['warehouse_name'] ?? $r['gudang_nama'] ?? '')) ?: $code;
            $this->rowGudang(['code' => $code, 'name' => $name, 'city' => '', 'address' => ''], 0, $userId);
        }
    }

    /**
     * Sheet 'group' opsional. Bila tidak ada (atau hanya sebagian), group dibuat
     * dari data yang sudah ada di sheet lain:
     *   - master  : group_code + group_name + warehouse_code
     *   - karyawan: group_code
     * Nama group hanya dirapikan bila nama lama masih sama dengan kodenya.
     */
    private function ensureGroupsFromSheets(array $sheets, int $userId): void
    {
        $wanted = [];
        foreach ($sheets['master'] ?? [] as $row) {
            $code = $this->up($row, 'group_code');
            if ($code === '') {
                continue;
            }
            $wanted[$code] ??= ['name' => '', 'wh' => ''];
            if ($wanted[$code]['name'] === '') {
                $wanted[$code]['name'] = $this->v($row, 'group_name');
            }
            if ($wanted[$code]['wh'] === '') {
                $wanted[$code]['wh'] = $this->up($row, 'warehouse_code');
            }
        }
        foreach ($sheets['karyawan'] ?? [] as $row) {
            $code = $this->up($row, 'group_code');
            if ($code !== '' && ! isset($wanted[$code])) {
                $wanted[$code] = ['name' => '', 'wh' => ''];
            }
        }
        if ($wanted === []) {
            return;
        }

        if (! isset($this->report['group'])) {
            $this->report['group'] = ['created' => 0, 'updated' => 0, 'failed' => 0, 'errors' => []];
        }
        $this->touch('group');

        $pdo = Database::connection();
        foreach ($wanted as $code => $meta) {
            $existing = $pdo->prepare('SELECT id, name FROM `groups` WHERE UPPER(code)=? LIMIT 1');
            $existing->execute([$code]);
            $row = $existing->fetch(\PDO::FETCH_ASSOC);
            $whId = $meta['wh'] !== ''
                ? $this->scalar('SELECT id FROM warehouses WHERE UPPER(code)=? LIMIT 1', [$meta['wh']])
                : null;

            if ($row === false) {
                $name = $meta['name'] !== '' ? $meta['name'] : $code;
                try {
                    $pdo->prepare('INSERT INTO `groups` (warehouse_id,code,name,is_active,created_at,updated_at) VALUES (?,?,?,1,NOW(),NOW())')
                        ->execute([$whId, $code, $name]);
                    $this->ok('group', 'created');
                } catch (\Throwable $e) {
                    $this->fail('group', 0, "group '{$code}' gagal dibuat: " . $e->getMessage());
                }
                continue;
            }

            $oldName = (string) ($row['name'] ?? '');
            $newName = $meta['name'];
            if ($newName !== '' && strcasecmp($oldName, $newName) !== 0 && strcasecmp($oldName, $code) === 0) {
                $pdo->prepare('UPDATE `groups` SET name=?, updated_at=NOW() WHERE id=?')
                    ->execute([$newName, (int) $row['id']]);
                $this->ok('group', 'updated');
            }
        }
    }

    /**
     * PIC group dari sheet karyawan: kolom pic_employee_id (opsional).
     * Satu baris per group sudah cukup; nama diambil dari tabel employees.
     */
    private function applyGroupPicFromKaryawan(array $rows): void
    {
        $picked = [];
        foreach ($rows as $row) {
            $code = $this->up($row, 'group_code');
            $pic = $this->up($row, 'pic_employee_id');
            if ($code === '' || $pic === '' || isset($picked[$code])) {
                continue;
            }
            $picked[$code] = $pic;
        }
        if ($picked === []) {
            return;
        }

        $pdo = Database::connection();
        foreach ($picked as $code => $pic) {
            $name = $this->employeeName($pic);
            $pdo->prepare('UPDATE `groups` SET pic_employee_id=?, pic_name=COALESCE(?,pic_name), updated_at=NOW() WHERE UPPER(code)=? AND (pic_employee_id IS NULL OR UPPER(pic_employee_id)<>?)')
                ->execute([$pic, $name, $code, $pic]);
        }
    }

    /* ---------- periode ---------- */
    private function rowPeriode(array $row, int $line, int $userId): void
    {
        $month = trim((string) ($row['audit_month'] ?? ''));
        $start = $this->normaliseDate((string) ($row['audit_start'] ?? ''));
        $end   = $this->normaliseDate((string) ($row['audit_end'] ?? ''));

        if ($month === '' && $start !== '') {
            $month = substr($start, 0, 7);
        }
        if ($month === '' || ! preg_match('/^\d{4}-\d{2}$/', $month)) {
            $this->fail('periode', $line, 'audit_month wajib diisi dengan format YYYY-MM (misal: 2025-09).');
            return;
        }

        [$defStart, $defEnd] = $this->monthBounds($month);

        // Tanggal start/end boleh kosong atau tidak konsisten dengan bulannya
        // (file nyata sering berisi 2025-10-01 s/d 2025-09-31): pakai batas bulan.
        if ($start === '' || $end === '' || substr($start, 0, 7) !== $month || substr($end, 0, 7) !== $month) {
            $start = $defStart;
            $end = $defEnd;
        }
        if ($start > $end) {
            $start = $defStart;
            $end = $defEnd;
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
        // PIC group (opsional): employee_id + nama. Bila hanya ID diisi, nama
        // diambil dari master karyawan supaya konsisten.
        $picId = trim((string) ($row['pic_employee_id'] ?? $row['pic_id'] ?? $row['id_pic'] ?? ''));
        $picName = trim((string) ($row['pic_name'] ?? $row['nama_pic'] ?? ''));
        if ($picId !== '' && $picName === '') {
            $picName = (string) ($this->employeeName($picId) ?? '');
        }

        $pdo = Database::connection();
        $id = $this->scalar('SELECT id FROM `groups` WHERE UPPER(code)=? LIMIT 1', [$code]);
        if ($id === null) {
            $pdo->prepare('INSERT INTO `groups` (warehouse_id,code,name,description,pic_employee_id,pic_name,is_active,created_at,updated_at) VALUES (?,?,?,?,?,?,1,NOW(),NOW())')
                ->execute([$whId, $code, $name, $this->v($row, 'description') ?: null, $picId ?: null, $picName ?: null]);
            $this->ok('group', 'created');
        } else {
            $pdo->prepare('UPDATE `groups` SET name=?, warehouse_id=COALESCE(?,warehouse_id), description=COALESCE(NULLIF(?,""),description), pic_employee_id=COALESCE(NULLIF(?,""),pic_employee_id), pic_name=COALESCE(NULLIF(?,""),pic_name), updated_at=NOW() WHERE id=?')
                ->execute([$name, $whId, $this->v($row, 'description'), $picId, $picName, (int) $id]);
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
        $locId = $lcode !== ''
            ? $this->scalar('SELECT id FROM locations WHERE group_id=? AND UPPER(code)=? LIMIT 1', [$gid, $lcode])
            : null;
        if ($locId === null) {
            // Device tetap bisa dipasang walau baris tidak menyebut lokasi:
            // tempel ke lokasi pertama group tsb.
            $locId = $this->scalar('SELECT id FROM locations WHERE group_id=? ORDER BY id LIMIT 1', [$gid]);
        }
        if ($locId === null) {
            { $this->fail('tablet', $line, "location_code '{$lcode}' tidak ada di group {$gcode}."); return; }
        }
        $type = $this->v($row, 'type') ?: 'tablet';
        $serial = $this->v($row, 'serial_number') ?: null;

        // Pemilik device (opsional): employee_id dari sheet karyawan -> users.id.
        $employee = strtoupper(trim((string) ($row['employee_id'] ?? '')));
        $ownerId = $employee !== '' ? $this->scalar('SELECT id FROM users WHERE UPPER(username)=? LIMIT 1', [$employee]) : null;

        $pdo = Database::connection();
        $id = $this->scalar('SELECT id FROM devices WHERE UPPER(code)=? LIMIT 1', [$dcode]);
        if ($id === null) {
            $pdo->prepare('INSERT INTO devices (location_id,code,name,type,serial_number,user_id,is_active,created_at,updated_at) VALUES (?,?,?,?,?,?,1,NOW(),NOW())')
                ->execute([(int) $locId, $dcode, $name, $type, $serial, $ownerId !== null ? (int) $ownerId : null]);
            $this->ok('tablet', 'created');
        } else {
            $pdo->prepare('UPDATE devices SET location_id=?, name=?, type=?, serial_number=COALESCE(?,serial_number), user_id=COALESCE(?,user_id), updated_at=NOW() WHERE id=?')
                ->execute([(int) $locId, $name, $type, $serial, $ownerId !== null ? (int) $ownerId : null, (int) $id]);
            $this->ok('tablet', 'updated');
        }
    }

    /* ---------- helpers ---------- */

    /** Normalisasi tanggal dari Excel: DateTime, serial number, atau teks. */
    private function normaliseDate(string $raw): string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return '';
        }

        // Serial number Excel (mis. 45931) -> tanggal.
        if (preg_match('/^\d+(\.\d+)?$/', $raw)) {
            $ts = (int) round(((float) $raw - 25569) * 86400);
            return gmdate('Y-m-d', $ts);
        }

        // Ambil bagian tanggal dari "2025-10-01 00:00:00" / "2025-10-01T00:00:00".
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $raw, $m)) {
            [$y, $mo, $d] = [(int) $m[1], (int) $m[2], (int) $m[3]];
            $max = (int) (new \DateTimeImmutable(sprintf('%04d-%02d-01', $y, $mo)))->format('t');
            $d = max(1, min($d, $max)); // 2025-09-31 -> 2025-09-30

            return sprintf('%04d-%02d-%02d', $y, $mo, $d);
        }

        // Format Indonesia d/m/Y.
        if (preg_match('#^(\d{1,2})/(\d{1,2})/(\d{4})$#', $raw, $m)) {
            return sprintf('%04d-%02d-%02d', (int) $m[3], (int) $m[2], (int) $m[1]);
        }

        return '';
    }

    /** @return array{0:string,1:string} tanggal awal & akhir bulan (Y-m-d). */
    private function monthBounds(string $month): array
    {
        $first = new \DateTimeImmutable($month . '-01');

        return [$first->format('Y-m-01'), $first->format('Y-m-t')];
    }

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

    /** Pastikan struktur laporan lengkap walau sheet tidak ada di daftar awal. */
    private function touch(string $sheet): void
    {
        if (! isset($this->report[$sheet])) {
            $this->report[$sheet] = ['created' => 0, 'updated' => 0, 'failed' => 0, 'errors' => []];
        }
    }

    private function ok(string $sheet, string $kind): void
    {
        $this->touch($sheet);
        $this->report[$sheet][$kind] = ($this->report[$sheet][$kind] ?? 0) + 1;
    }

    private function fail(string $sheet, int $line, string $msg): void
    {
        $this->touch($sheet);
        $this->report[$sheet]['failed']++;
        // Batasi daftar error agar laporan tetap ringan saat ribuan baris gagal
        // (jumlah 'failed' tetap dihitung penuh).
        $n = count($this->report[$sheet]['errors']);
        if ($n < 100) {
            $this->report[$sheet]['errors'][] = "{$sheet} baris {$line}: {$msg}";
        } elseif ($n === 100) {
            $this->report[$sheet]['errors'][] = '… daftar error dipotong (lihat jumlah gagal).';
        }
    }
}
