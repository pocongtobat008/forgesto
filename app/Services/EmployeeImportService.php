<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use RuntimeException;

/**
 * Import master karyawan.
 *
 * Kolom yang didukung (header baris pertama, case-insensitive):
 *   employee_id | id_karyawan | id   -> ID Karyawan (WE000001)
 *   name        | nama              -> Nama lengkap
 *   role        | peran             -> audit | observer | adminsto | poweradmin (opsional, default observer)
 *   group_code  | kode_group        -> kode grup (opsional)
 *   password                      -> password awal (opsional; kosong = pakai default)
 *   is_active   | aktif             -> 1/0 (opsional, default 1)
 *
 * Efek samping: baris yang valid juga disinkronkan ke tabel `users` supaya
 * karyawan bisa langsung login memakai ID Karyawan + password.
 */
class EmployeeImportService
{
    public const DEFAULT_PASSWORD = 'Stockforge#2026';

    private const VALID_ROLES = ['audit', 'observer', 'adminsto', 'poweradmin'];

    /** Alias header -> nama kolom internal. */
    private const ALIASES = [
        'employee_id' => 'employee_id',
        'id_karyawan' => 'employee_id',
        'id'          => 'employee_id',
        'nik'         => 'employee_id',
        'name'        => 'name',
        'nama'        => 'name',
        'nama_karyawan' => 'name',
        'role'        => 'role',
        'peran'       => 'role',
        'group_code'  => 'group_code',
        'kode_group'  => 'group_code',
        'group'       => 'group_code',
        'password'    => 'password',
        'is_active'   => 'is_active',
        'aktif'       => 'is_active',
        'status'      => 'is_active',
    ];

    /**
     * @param array{name:string,tmp_name:string,size:int,error:int} $file
     * @return array{
     *   success:int,created:int,updated:int,failed:int,
     *   headers:list<string>,rows:list<array<string,mixed>>
     * }
     */
    public function import(array $file, int $actorId): array
    {
        $path = (string) $file['tmp_name'];
        $extension = strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION));

        $sheet = (new SpreadsheetReader())->read($path, $extension);
        $headers = $sheet['headers'];
        $rows = $sheet['rows'];

        $hasEmployeeId = $this->hasAnyColumn($headers, ['employee_id', 'id_karyawan', 'id', 'nik']);
        $hasName = $this->hasAnyColumn($headers, ['name', 'nama', 'nama_karyawan']);

        if (! $hasEmployeeId || ! $hasName) {
            throw new RuntimeException(
                'Header wajib tidak ditemukan. File harus punya kolom "employee_id" (atau id_karyawan/id) '
                . 'dan kolom "name" (atau nama).'
            );
        }

        $pdo = Database::connection();
        $pdo->beginTransaction();

        $result = [
            'success' => 0,
            'created' => 0,
            'updated' => 0,
            'failed'  => 0,
            'headers' => $headers,
            'rows'    => [],
        ];
        $seen = [];
        $memo = []; // employee_id => data lengkap, agar baris group tambahan boleh kosong

        try {
            foreach ($rows as $index => $raw) {
                $line = $index + 2; // baris 1 = header
                $data = $this->normaliseRow($raw);
                $errors = [];

                if ($data['employee_id'] === '') {
                    $errors[] = 'ID Karyawan kosong.';
                } elseif (! preg_match('/^[A-Za-z0-9._-]{3,40}$/', $data['employee_id'])) {
                    $errors[] = 'Format ID Karyawan tidak valid.';
                }

                // ID yang sudah muncul = baris group tambahan (bukan error).
                // Warisi name/role/password dari baris pertama ID tersebut.
                $isRepeat = isset($memo[$data['employee_id']]);
                if ($isRepeat) {
                    foreach (['name', 'role', 'password'] as $field) {
                        if ($data[$field] === '' || $data[$field] === null) {
                            $data[$field] = $memo[$data['employee_id']][$field];
                        }
                    }
                    if ($data['is_active'] === null) {
                        $data['is_active'] = $memo[$data['employee_id']]['is_active'];
                    }
                } else {
                    $memo[$data['employee_id']] = $data;
                }

                if ($data['name'] === '') {
                    $errors[] = 'Nama kosong pada baris pertama ID ini.';
                }
                if ($data['role'] !== '' && ! in_array($data['role'], self::VALID_ROLES, true)) {
                    $errors[] = 'Role tidak dikenal: ' . $data['role'] . '.';
                }

                if ($errors !== []) {
                    $result['failed']++;
                    $result['rows'][] = ['line' => $line, 'status' => 'failed', 'data' => $data, 'errors' => $errors];
                    continue;
                }

                $seen[$data['employee_id']] = $line;

                try {
                    $action = $this->upsert($data, $actorId);
                    $result[$action === 'created' ? 'created' : 'updated']++;
                    $result['success']++;
                    $result['rows'][] = ['line' => $line, 'status' => $action, 'data' => $data, 'errors' => []];
                } catch (\Throwable $e) {
                    $result['failed']++;
                    $result['rows'][] = ['line' => $line, 'status' => 'failed', 'data' => $data, 'errors' => [$e->getMessage()]];
                }
            }

            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        return $result;
    }

    /**
     * Dipakai saat sheet `karyawan` di dalam workbook master.
     * Satu baris = satu kombinasi karyawan x group, sehingga karyawan dengan
     * beberapa group ditulis berulang (ID sama, group_code berbeda).
     * Nama/role/password cukup diisi di baris pertama; baris berikutnya boleh kosong.
     *
     * @param list<array<string,string>> $rows
     * @return array{created:int,updated:int,failed:int,errors:list<string>}
     */
    public function importRows(array $rows, int $actorId): array
    {
        $report = ['created' => 0, 'updated' => 0, 'failed' => 0, 'errors' => []];

        // Ingat nama/role/password per employee_id dari baris sebelumnya,
        // supaya baris lanjutan (group tambahan) tidak perlu mengulang data.
        $memo = [];

        foreach ($rows as $index => $raw) {
            $line = $index + 2;
            $data = $this->normaliseRow($raw);
            $employeeId = $data['employee_id'];

            if ($employeeId === '') {
                $report['failed']++;
                $report['errors'][] = "Baris {$line}: ID Karyawan kosong.";
                continue;
            }

            // Warisi data dari baris sebelumnya bila kolom dikosongkan.
            if (isset($memo[$employeeId])) {
                foreach (['name', 'role', 'password'] as $field) {
                    if ($data[$field] === '') {
                        $data[$field] = $memo[$employeeId][$field];
                    }
                }
            } else {
                $memo[$employeeId] = $data;
            }
            $memo[$employeeId] = array_merge($memo[$employeeId] ?? [], $data);

            if ($data['name'] === '') {
                $report['failed']++;
                $report['errors'][] = "Baris {$line}: nama karyawan {$employeeId} kosong pada baris pertama dan belum pernah diisi sebelumnya.";
                continue;
            }
            if ($data['role'] !== '' && ! in_array($data['role'], self::VALID_ROLES, true)) {
                $report['failed']++;
                $report['errors'][] = "Baris {$line}: role '{$data['role']}' tidak dikenal.";
                continue;
            }

            try {
                $isNew = $data['group_code'] === ''
                    ? $this->upsert($data, $actorId)
                    : $this->upsertWithGroup($data, $actorId);
                $report[$isNew === 'created' ? 'created' : 'updated']++;
            } catch (\Throwable $e) {
                $report['failed']++;
                $report['errors'][] = "Baris {$line}: " . $e->getMessage();
            }
        }

        return $report;
    }

    /**
     * Upsert karyawan sekaligus menambahkan keanggotaan group (idempotent).
     *
     * @return 'created'|'updated'
     */
    public function upsertWithGroup(array $data, int $actorId): string
    {
        $action = $this->upsert($data, $actorId);

        $pdo = Database::connection();
        $gid = $pdo->prepare('SELECT id FROM `groups` WHERE UPPER(code) = ? LIMIT 1');
        $gid->execute([$data['group_code']]);
        $groupId = $gid->fetchColumn();

        if ($groupId === false) {
            throw new RuntimeException("Group '{$data['group_code']}' tidak ditemukan, keanggotaan dilewati.");
        }

        $uid = $pdo->prepare('SELECT id FROM users WHERE username = ? LIMIT 1');
        $uid->execute([$data['employee_id']]);
        $userId = $uid->fetchColumn();

        if ($userId !== false) {
            $pdo->prepare('INSERT IGNORE INTO user_groups (user_id, group_id, created_at) VALUES (?, ?, NOW())')
                ->execute([(int) $userId, (int) $groupId]);
        }

        return $action;
    }

    /** @param array<string,string> $raw */
    private function normaliseRow(array $raw): array
    {
        $mapped = [];
        foreach ($raw as $key => $value) {
            $normalised = self::ALIASES[strtolower(trim((string) $key))] ?? null;
            if ($normalised !== null && ! isset($mapped[$normalised])) {
                $mapped[$normalised] = trim((string) $value);
            }
        }

        $employeeId = strtoupper($mapped['employee_id'] ?? '');
        $role = strtolower($mapped['role'] ?? '');
        $active = $mapped['is_active'] ?? '1';

        return [
            'employee_id' => $employeeId,
            'name'        => $mapped['name'] ?? '',
            'role'        => $role,
            'group_code'  => strtoupper(trim($mapped['group_code'] ?? '')),
            'password'    => $mapped['password'] ?? '',
            'is_active'   => in_array(strtolower($active), ['0', 'no', 'n', 'false', 'nonaktif', 'tidak'], true) ? 0 : 1,
        ];
    }

    /**
     * Upsert ke tabel employees + sinkron ke users.
     *
     * @return 'created'|'updated'
     */
    private function upsert(array $data, int $actorId): string
    {
        $pdo = Database::connection();

        $existing = $pdo->prepare('SELECT id, password FROM employees WHERE employee_id = ? LIMIT 1');
        $existing->execute([$data['employee_id']]);
        $row = $existing->fetch();

        $role = $data['role'] !== '' ? $data['role'] : 'observer';
        $passwordPlain = $data['password'] !== '' ? $data['password'] : self::DEFAULT_PASSWORD;

        if ($row === false) {
            $insert = $pdo->prepare(
                'INSERT INTO employees (employee_id, name, role, password, group_code, is_active)
                 VALUES (?, ?, ?, ?, ?, ?)'
            );
            $insert->execute([
                $data['employee_id'],
                $data['name'],
                $role,
                password_hash($passwordPlain, PASSWORD_DEFAULT),
                $data['group_code'] !== '' ? $data['group_code'] : null,
                $data['is_active'],
            ]);
            $action = 'created';
        } else {
            $set = 'name = ?, role = ?, group_code = ?, is_active = ?';
            $params = [$data['name'], $role, $data['group_code'] !== '' ? $data['group_code'] : null, $data['is_active']];

            // Password hanya diganti bila kolom password diisi eksplisit.
            if ($data['password'] !== '') {
                $set .= ', password = ?';
                $params[] = password_hash($data['password'], PASSWORD_DEFAULT);
            }
            $params[] = (int) $row['id'];

            $pdo->prepare("UPDATE employees SET {$set} WHERE id = ?")->execute($params);
            $action = 'updated';
        }

        $this->syncUser($data, $role, $passwordPlain);

        return $action;
    }

    /** Sinkronkan akun login di tabel users. */
    private function syncUser(array $data, string $role, string $passwordPlain): void
    {
        $pdo = Database::connection();

        $stmt = $pdo->prepare('SELECT id, password FROM users WHERE username = ? LIMIT 1');
        $stmt->execute([$data['employee_id']]);
        $user = $stmt->fetch();

        if ($user === false) {
            // Cari berdasarkan nama (untuk akun lama yang username-nya bukan ID karyawan).
            $byName = $pdo->prepare('SELECT id FROM users WHERE name = ? LIMIT 1');
            $byName->execute([$data['name']]);
            $legacy = $byName->fetch();

            if ($legacy !== false) {
                $pdo->prepare('UPDATE users SET name = ?, username = ?, role = ?, is_active = ? WHERE id = ?')
                    ->execute([$data['name'], $data['employee_id'], $role, $data['is_active'], (int) $legacy['id']]);
                if ($data['password'] !== '') {
                    $pdo->prepare('UPDATE users SET password = ? WHERE id = ?')
                        ->execute([password_hash($data['password'], PASSWORD_DEFAULT), (int) $legacy['id']]);
                }

                return;
            }

            $pdo->prepare(
                'INSERT INTO users (name, username, role, is_active, password) VALUES (?, ?, ?, ?, ?)'
            )->execute([
                $data['name'],
                $data['employee_id'],
                $role,
                $data['is_active'],
                password_hash($passwordPlain, PASSWORD_DEFAULT),
            ]);

            return;
        }

        $set = 'name = ?, role = ?, is_active = ?';
        $params = [$data['name'], $role, $data['is_active']];

        if ($user['password'] === null || $user['password'] === '') {
            $set .= ', password = ?';
            $params[] = password_hash($passwordPlain, PASSWORD_DEFAULT);
        } elseif ($data['password'] !== '') {
            $set .= ', password = ?';
            $params[] = password_hash($data['password'], PASSWORD_DEFAULT);
        }

        $params[] = (int) $user['id'];
        $pdo->prepare("UPDATE users SET {$set} WHERE id = ?")->execute($params);
    }

    /** @param list<string> $headers @param list<string> $candidates */
    private function hasAnyColumn(array $headers, array $candidates): bool
    {
        foreach ($headers as $header) {
            if (in_array(strtolower(trim($header)), $candidates, true)) {
                return true;
            }
        }

        return false;
    }
}
