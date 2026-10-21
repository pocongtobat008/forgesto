<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\Database;
use App\Models\ActivityLog;
use App\Models\ImportLog;
use App\Models\Item;
use App\Models\Notification;
use RuntimeException;

/**
 * Maps spreadsheet rows onto `items`, creating new records and updating the
 * editable fields of existing ones (locked rows are skipped).
 *
 * Expected (flexible, aliased) columns:
 *   group_code | code | name | category | unit | expected_qty |
 *   counted_qty | location_code | device_code | condition_note
 */
class ItemImportService
{
    /** Alias map: canonical column => accepted header names. */
    private const ALIASES = [
        'group_code'     => ['group_code', 'kode_group', 'kode_grup', 'group', 'grup', 'group_code_sto'],
        'code'           => ['code', 'kode', 'kode_barang', 'item_code', 'kode_item'],
        'id_barang'      => ['id_barang', 'id barang', 'item_id'],
        'name'           => ['name', 'nama', 'nama_barang', 'item_name', 'nama_item', 'description'],
        'serial'         => ['serial', 'serial(jika ada)', 'serial_number', 'sn'],
        'category'       => ['category', 'kategori', 'type', 'jenis'],
        'business_unit'  => ['business_unit', 'business unit', 'bu', 'unit_bisnis'],
        'sub_inventory'  => ['sub_inventory', 'sub inventory', 'subinventory', 'sub_inv'],
        'unit'           => ['unit', 'satuan', 'uom'],
        'unit_price'     => ['unit_price', 'harga', 'price', 'harga_satuan'],
        'expected_qty'   => ['expected_qty', 'qty_sistem', 'qty_expected', 'expected', 'jumlah', 'qty', 'stok_sistem', 'system qty'],
        'counted_qty'    => ['counted_qty', 'qty_fisik', 'qty_hitung', 'hasil_hitung', 'counted', 'fisik'],
        'location_code'  => ['location_code', 'kode_lokasi', 'lokasi', 'location', 'lokasi item'],
        'device_code'    => ['device_code', 'kode_device', 'device', 'perangkat'],
        'condition_note' => ['condition_note', 'catatan', 'kondisi', 'note', 'keterangan'],
    ];

    public function __construct(private ?SpreadsheetReader $reader = null)
    {
        $this->reader ??= new SpreadsheetReader();
    }

    /**
     * @param array{name:string,tmp_name:string,size:int} $file
     * @return array{total:int,success:int,failed:int,errors:list<string>,created:int,updated:int}
     */
    public function import(array $file, ?int $groupId, int $userId): array
    {
        $extension = strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION));
        $parsed = $this->reader->read((string) $file['tmp_name'], $extension);

        $rows = $parsed['rows'];
        $total = count($rows);
        $success = 0;
        $created = 0;
        $updated = 0;
        $failed = 0;
        $errors = [];

        $logId = (new ImportLog())->insert([
            'user_id'      => $userId,
            'group_id'     => $groupId,
            'type'         => 'items',
            'filename'     => (string) $file['name'],
            'total_rows'   => $total,
            'success_rows' => 0,
            'failed_rows'  => 0,
            'progress'     => 0,
            'status'       => 'processing',
        ]);

        // Simpan file asli agar bisa diunduh dari riwayat.
        $storedPath = ImportLog::storeOriginal($logId, (string) $file['name'], (string) $file['tmp_name']);
        if ($storedPath !== null) {
            (new ImportLog())->updateById($logId, ['stored_path' => $storedPath]);
        }

        if ($total === 0) {
            (new ImportLog())->updateById($logId, [
                'status'  => 'failed',
                'message' => 'File tidak berisi data.',
            ]);

            return ['total' => 0, 'success' => 0, 'failed' => 0, 'created' => 0, 'updated' => 0, 'errors' => ['File tidak berisi data.']];
        }

        $groupCache = $this->groupCache();

        $lastPct = -1;
        foreach ($rows as $index => $row) {
            $line = $index + 2; // account for the header row (1-based)
            $mapped = $this->mapRow($row);

            // Progres per 5% baris, bukan per baris, supaya hemat I/O DB.
            $pct = (int) floor((($index + 1) / max(1, $total)) * 100);
            if ($pct >= $lastPct + 5) {
                $lastPct = $pct;
                (new ImportLog())->updateById($logId, [
                    'progress'     => min(99, $pct),
                    'success_rows' => $success,
                    'failed_rows'  => $failed,
                ]);
            }

            // Resolve the target group.
            $rowGroupId = $groupId;
            if ($mapped['group_code'] !== '') {
                $code = strtoupper($mapped['group_code']);
                if (! isset($groupCache[$code])) {
                    $failed++;
                    $errors[] = "Baris {$line}: group '{$mapped['group_code']}' tidak ditemukan atau bukan wewenang Anda.";
                    continue;
                }
                $rowGroupId = $groupCache[$code];
            }

            if ($rowGroupId === null) {
                $failed++;
                $errors[] = "Baris {$line}: group belum ditentukan.";
                continue;
            }

            if (! Auth::canSeeGroup($rowGroupId)) {
                $failed++;
                $errors[] = "Baris {$line}: Anda tidak punya akses ke group tersebut.";
                continue;
            }

            if ($mapped['code'] === '' || $mapped['name'] === '') {
                $failed++;
                $errors[] = "Baris {$line}: kolom code dan name wajib diisi.";
                continue;
            }

            try {
                $result = $this->upsertRow($rowGroupId, $mapped, $userId);
                $success++;
                $result === 'created' ? $created++ : $updated++;
            } catch (RuntimeException $e) {
                $failed++;
                $errors[] = "Baris {$line}: " . $e->getMessage();
            }
        }

        $status = match (true) {
            $failed === 0        => 'success',
            $success === 0       => 'failed',
            default              => 'partial',
        };

        (new ImportLog())->updateById($logId, [
            'success_rows' => $success,
            'failed_rows'  => $failed,
            'progress'     => 100,
            'status'       => $status,
            'message'      => $errors === [] ? null : implode(' | ', array_slice($errors, 0, 5)),
            'finished_at'  => date('Y-m-d H:i:s'),
        ]);

        (new ActivityLog())->record(
            'import',
            'items',
            null,
            "Import {$file['name']}: {$success} baris berhasil, {$failed} gagal",
            $groupId
        );

        if ($success > 0) {
            (new Notification())->toRole(
                'adminsto',
                'Import data selesai',
                "{$success} baris data berhasil diimport dari {$file['name']}.",
                $failed > 0 ? 'warning' : 'success',
                '/items',
                $groupId
            );
        }

        return [
            'total'   => $total,
            'success' => $success,
            'failed'  => $failed,
            'created' => $created,
            'updated' => $updated,
            'errors'  => $errors,
        ];
    }

    /**
     * Proses baris item dari sheet bernama (dipakai import master 1-file).
     * Tidak membuat ImportLog sendiri; pemanggil yang mengelola log.
     *
     * @param list<array<string,string>> $rows
     * @return array{created:int,updated:int,failed:int,errors:list<string>}
     */
    public function importRows(array $rows, ?int $groupId, int $userId): array
    {
        $groupCache = $this->groupCache();
        $created = 0;
        $updated = 0;
        $failed = 0;
        $errors = [];

        foreach ($rows as $index => $row) {
            $line = $index + 2;
            $mapped = $this->mapRow($row);
            if (trim(implode('', $mapped)) === '') {
                continue;
            }

            $rowGroupId = $groupId;
            if ($mapped['group_code'] !== '') {
                $code = strtoupper($mapped['group_code']);
                if (! isset($groupCache[$code])) {
                    $failed++;
                    $errors[] = "item baris {$line}: group '{$mapped['group_code']}' tidak ditemukan atau bukan wewenang Anda.";
                    continue;
                }
                $rowGroupId = $groupCache[$code];
            }
            if ($rowGroupId === null) {
                $failed++;
                $errors[] = "item baris {$line}: group belum ditentukan.";
                continue;
            }
            if (! Auth::canSeeGroup($rowGroupId)) {
                $failed++;
                $errors[] = "item baris {$line}: Anda tidak punya akses ke group tersebut.";
                continue;
            }
            if ($mapped['code'] === '' || $mapped['name'] === '') {
                $failed++;
                $errors[] = "item baris {$line}: kolom code dan name wajib diisi.";
                continue;
            }
            try {
                $this->upsertRow($rowGroupId, $mapped, $userId) === 'created' ? $created++ : $updated++;
            } catch (RuntimeException $e) {
                $failed++;
                $errors[] = "item baris {$line}: " . $e->getMessage();
            }
        }

        return [
            'created' => $created,
            'updated' => $updated,
            'failed'  => $failed,
            'errors'  => $errors,
        ];
    }

    /**
     * @param array<string, string> $mapped
     * @return 'created'|'updated'
     */
    private function upsertRow(int $groupId, array $mapped, int $userId): string
    {
        $items = new Item();
        $existing = $items->first(
            'SELECT * FROM items WHERE group_id = ? AND code = ? LIMIT 1',
            [$groupId, $mapped['code']]
        );

        $locationId = $this->resolveLocation($groupId, $mapped['location_code']);
        $deviceId = $this->resolveDevice($mapped['device_code']);

        $expected = $this->toInt($mapped['expected_qty']);
        $counted = $mapped['counted_qty'] === '' ? null : $this->toInt($mapped['counted_qty']);

        if ($existing === null) {
            $data = [
                'group_id'       => $groupId,
                'location_id'    => $locationId,
                'device_id'      => $deviceId,
                'code'           => $mapped['code'],
                'id_barang'      => $mapped['id_barang'] !== '' ? $mapped['id_barang'] : null,
                'name'           => $mapped['name'],
                'serial'         => $mapped['serial'] !== '' ? $mapped['serial'] : null,
                'category'       => $mapped['category'] !== '' ? $mapped['category'] : null,
                'business_unit'  => $mapped['business_unit'] !== '' ? $mapped['business_unit'] : null,
                'sub_inventory'  => $mapped['sub_inventory'] !== '' ? $mapped['sub_inventory'] : null,
                'unit'           => $mapped['unit'] !== '' ? $mapped['unit'] : null,
                'unit_price'     => $mapped['unit_price'] !== '' ? $mapped['unit_price'] : null,
                'expected_qty'   => $expected,
                'count_status'   => Item::COUNT_PENDING,
                'audit_status'   => Item::AUDIT_PENDING,
                'created_by'     => $userId,
            ];

            if ($counted !== null) {
                $data['counted_qty'] = $counted;
                $data['count_status'] = Item::COUNT_COUNTED;
                $data['counted_by'] = $userId;
                $data['counted_at'] = date('Y-m-d H:i:s');
            }

            $items->insert($data);

            return 'created';
        }

        if ((int) $existing['is_locked'] === 1) {
            throw new RuntimeException("item {$mapped['code']} terkunci (sudah diposting), ajukan unlock terlebih dahulu.");
        }

        $update = [
            'name'           => $mapped['name'],
            'id_barang'      => $mapped['id_barang'] !== '' ? $mapped['id_barang'] : $existing['id_barang'],
            'serial'         => $mapped['serial'] !== '' ? $mapped['serial'] : $existing['serial'],
            'category'       => $mapped['category'] !== '' ? $mapped['category'] : $existing['category'],
            'business_unit'  => $mapped['business_unit'] !== '' ? $mapped['business_unit'] : $existing['business_unit'],
            'sub_inventory'  => $mapped['sub_inventory'] !== '' ? $mapped['sub_inventory'] : $existing['sub_inventory'],
            'unit'           => $mapped['unit'] !== '' ? $mapped['unit'] : $existing['unit'],
            'unit_price'     => $mapped['unit_price'] !== '' ? $mapped['unit_price'] : $existing['unit_price'],
            'expected_qty'   => $expected,
            'location_id'    => $locationId ?? $existing['location_id'],
            'device_id'      => $deviceId ?? $existing['device_id'],
            'condition_note' => $mapped['condition_note'] !== '' ? $mapped['condition_note'] : $existing['condition_note'],
        ];

        if ($counted !== null) {
            $update['counted_qty'] = $counted;
            $update['count_status'] = Item::COUNT_COUNTED;
            $update['counted_by'] = $userId;
            $update['counted_at'] = date('Y-m-d H:i:s');
        }

        $items->updateById((int) $existing['id'], $update);

        return 'updated';
    }

    /**
     * @param array<string, string> $row
     * @return array<string, string>
     */
    private function mapRow(array $row): array
    {
        $mapped = [];
        foreach (self::ALIASES as $canonical => $aliases) {
            $mapped[$canonical] = '';
            foreach ($aliases as $alias) {
                if (isset($row[$alias]) && trim((string) $row[$alias]) !== '') {
                    $mapped[$canonical] = trim((string) $row[$alias]);
                    break;
                }
            }
        }

        return $mapped;
    }

    /** @return array<string, int> group code (upper) => id */
    private function groupCache(): array
    {
        $rows = Database::connection()
            ->query('SELECT id, code FROM `groups`')
            ->fetchAll();

        $cache = [];
        foreach ($rows as $row) {
            $cache[strtoupper((string) $row['code'])] = (int) $row['id'];
        }

        return $cache;
    }

    private function resolveLocation(int $groupId, string $code): ?int
    {
        if ($code === '') {
            return null;
        }

        $stmt = Database::connection()->prepare(
            'SELECT id FROM locations WHERE group_id = ? AND (UPPER(code) = ? OR UPPER(name) = ?) LIMIT 1'
        );
        $stmt->execute([$groupId, strtoupper($code), strtoupper($code)]);
        $id = $stmt->fetchColumn();

        return $id === false ? null : (int) $id;
    }

    private function resolveDevice(string $code): ?int
    {
        if ($code === '') {
            return null;
        }

        $stmt = Database::connection()->prepare(
            'SELECT id FROM devices WHERE UPPER(code) = ? OR UPPER(serial_number) = ? LIMIT 1'
        );
        $stmt->execute([strtoupper($code), strtoupper($code)]);
        $id = $stmt->fetchColumn();

        return $id === false ? null : (int) $id;
    }

    /**
     * Angka dari Excel bisa berupa desimal (8308.256) atau format ribuan
     * (1.234,5 / 1,234.5). Jangan sekadar membuang titik/koma: itu membuat
     * 8308.256 menjadi 8.308.256 (1000x). Bulatkan ke bilangan bulat terdekat
     * dan jaga agar tetap masuk rentang kolom INT.
     */
    private function toInt(string $value): int
    {
        $clean = preg_replace('/[^0-9\-,\.]/', '', $value) ?? '0';
        if ($clean === '' || $clean === '-') {
            return 0;
        }

        $hasComma = strpos($clean, ',') !== false;
        $hasDot = strpos($clean, '.') !== false;

        if ($hasComma && $hasDot) {
            // Pemisah desimal = simbol yang muncul terakhir.
            $clean = strrpos($clean, ',') > strrpos($clean, '.')
                ? str_replace(['.', ','], ['', '.'], $clean)
                : str_replace(',', '', $clean);
        } elseif ($hasComma) {
            $clean = str_replace(',', '.', $clean);
        }

        $num = (float) $clean;
        if ($num > 2147483647) {
            return 2147483647;
        }
        if ($num < -2147483648) {
            return -2147483648;
        }

        return (int) round($num);
    }

    /**
     * CSV template content for the import screen.
     */
    public static function template(): string
    {
        $header = 'group_code,code,name,category,unit,expected_qty,counted_qty,location_code,device_code,condition_note';
        $sample = 'GRP-A,A-013,Kabel HDMI,Elektronik,unit,10,10,LOC-A1,,Kondisi baik';

        return $header . "\n" . $sample . "\n";
    }
}
