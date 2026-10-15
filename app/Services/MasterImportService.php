<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use RuntimeException;
use ZipArchive;

/**
 * Import satu file xlsx untuk banyak master: lokasi (rak), device (tablet), dan item.
 * Sheet dikenali dari namanya: "lokasi", "device", "item". Urutan proses: lokasi -> device -> item,
 * supaya item bisa merujuk location_code / device_code yang baru dibuat di file yang sama.
 */
class MasterImportService
{
    /** @return array{lokasi:array{created:int,updated:int,failed:int,errors:list<string>},device:array{created:int,updated:int,failed:int,errors:list<string>}} */
    public function importLocationsAndDevices(string $path, int $defaultGroupId, bool $allowGroupCode): array
    {
        $sheets = $this->readSheets($path);
        $report = [
            'lokasi' => ['created' => 0, 'updated' => 0, 'failed' => 0, 'errors' => []],
            'device' => ['created' => 0, 'updated' => 0, 'failed' => 0, 'errors' => []],
        ];

        foreach (($sheets['lokasi'] ?? []) as $i => $row) {
            $line = $i + 2;
            $gcode = strtoupper(trim((string) ($row['group_code'] ?? '')));
            $lcode = strtoupper(trim((string) ($row['location_code'] ?? '')));
            $name = trim((string) ($row['name'] ?? ''));
            if ($lcode === '' || $name === '') {
                $report['lokasi']['failed']++;
                $report['lokasi']['errors'][] = "lokasi baris {$line}: location_code dan name wajib diisi.";
                continue;
            }
            $gid = $this->resolveGroup($gcode, $defaultGroupId, $allowGroupCode);
            if ($gid === null) {
                $report['lokasi']['failed']++;
                $report['lokasi']['errors'][] = "lokasi baris {$line}: group '{$gcode}' tidak ditemukan atau bukan wewenang Anda.";
                continue;
            }
            $pdo = Database::connection();
            $stmt = $pdo->prepare('SELECT id FROM locations WHERE group_id = ? AND UPPER(code) = ? LIMIT 1');
            $stmt->execute([$gid, $lcode]);
            $existing = $stmt->fetchColumn();
            if ($existing === false) {
                $ins = $pdo->prepare('INSERT INTO locations (group_id, code, name, address, is_active, created_at, updated_at) VALUES (?, ?, ?, ?, 1, NOW(), NOW())');
                $ins->execute([$gid, $lcode, $name, trim((string) ($row['address'] ?? '')) ?: null]);
                $report['lokasi']['created']++;
            } else {
                $upd = $pdo->prepare('UPDATE locations SET name = ?, address = COALESCE(NULLIF(?, \'\'), address), updated_at = NOW() WHERE id = ?');
                $upd->execute([$name, trim((string) ($row['address'] ?? '')), (int) $existing]);
                $report['lokasi']['updated']++;
            }
        }

        foreach (($sheets['device'] ?? []) as $i => $row) {
            $line = $i + 2;
            $dcode = strtoupper(trim((string) ($row['device_code'] ?? '')));
            $lcode = strtoupper(trim((string) ($row['location_code'] ?? '')));
            $name = trim((string) ($row['name'] ?? ''));
            if ($dcode === '' || $name === '') {
                $report['device']['failed']++;
                $report['device']['errors'][] = "device baris {$line}: device_code dan name wajib diisi.";
                continue;
            }
            $pdo = Database::connection();
            // device harus menempel ke lokasi yang sudah ada (dari sheet lokasi atau data lama)
            $loc = $pdo->prepare('SELECT id FROM locations WHERE UPPER(code) = ? LIMIT 1');
            $loc->execute([$lcode]);
            $locId = $loc->fetchColumn();
            if ($locId === false) {
                $report['device']['failed']++;
                $report['device']['errors'][] = "device baris {$line}: location_code '{$lcode}' tidak ditemukan.";
                continue;
            }
            $stmt = $pdo->prepare('SELECT id FROM devices WHERE UPPER(code) = ? LIMIT 1');
            $stmt->execute([$dcode]);
            $existing = $stmt->fetchColumn();
            $type = trim((string) ($row['type'] ?? '')) ?: 'tablet';
            $serial = trim((string) ($row['serial_number'] ?? '')) ?: null;
            if ($existing === false) {
                $ins = $pdo->prepare('INSERT INTO devices (location_id, code, name, type, serial_number, is_active, created_at, updated_at) VALUES (?, ?, ?, ?, ?, 1, NOW(), NOW())');
                $ins->execute([(int) $locId, $dcode, $name, $type, $serial]);
                $report['device']['created']++;
            } else {
                $upd = $pdo->prepare('UPDATE devices SET location_id = ?, name = ?, type = ?, serial_number = ?, updated_at = NOW() WHERE id = ?');
                $upd->execute([(int) $locId, $name, $type, $serial, (int) $existing]);
                $report['device']['updated']++;
            }
        }

        return $report;
    }

    private function resolveGroup(string $gcode, int $defaultGroupId, bool $allowGroupCode): ?int
    {
        if ($gcode === '') {
            return $defaultGroupId > 0 ? $defaultGroupId : null;
        }
        if (! $allowGroupCode) {
            return null;
        }
        $stmt = Database::connection()->prepare('SELECT id FROM `groups` WHERE UPPER(code) = ? LIMIT 1');
        $stmt->execute([$gcode]);
        $id = $stmt->fetchColumn();
        if ($id === false) {
            return null;
        }
        return \App\Core\Auth::canSeeGroup((int) $id) ? (int) $id : null;
    }

    /**
     * Baca semua worksheet xlsx berdasarkan nama sheet. Mengembalikan map nama(lower) => list<assoc>.
     * @return array<string, list<array<string,string>>>
     */
    public function readSheets(string $path): array
    {
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            throw new RuntimeException('File .xlsx tidak dapat dibaca (arsip rusak).');
        }
        $wbXml = simplexml_load_string((string) $zip->getFromName('xl/workbook.xml'));
        $relXml = simplexml_load_string((string) $zip->getFromName('xl/_rels/workbook.xml.rels'));
        $out = [];
        if ($wbXml === false || $relXml === false) {
            $zip->close();
            return $out;
        }
        $relNs = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';
        $targets = [];
        foreach ($relXml->Relationship as $rel) {
            $targets[(string) $rel['Id']] = ltrim((string) $rel['Target'], '/');
        }
        foreach ($wbXml->sheets->sheet as $sheet) {
            $name = strtolower(trim((string) $sheet['name']));
            $rid = (string) $sheet->attributes($relNs)->id;
            $target = $targets[$rid] ?? null;
            if ($target === null) {
                continue;
            }
            if (! str_starts_with($target, 'xl/')) {
                $target = 'xl/' . $target;
            }
            $xml = (string) $zip->getFromName($target);
            $out[$name] = $this->toAssocFromSheetXml($xml, $zip);
        }
        $zip->close();

        return $out;
    }

    /** @return list<array<string,string>> */
    private function toAssocFromSheetXml(string $xml, ZipArchive $zip): array
    {
        $shared = [];
        $ss = $zip->getFromName('xl/sharedStrings.xml');
        if ($ss !== false) {
            $sx = simplexml_load_string($ss);
            if ($sx !== false) {
                foreach ($sx->si as $si) {
                    $shared[] = (string) $si->t;
                }
            }
        }
        $sheet = simplexml_load_string($xml);
        if ($sheet === false) {
            return [];
        }
        $matrix = [];
        foreach ($sheet->sheetData->row as $row) {
            $cells = [];
            foreach ($row->c as $c) {
                $ref = (string) $c['r'];
                $col = preg_replace('/\d+/', '', $ref);
                $idx = 0;
                foreach (str_split($col) as $ch) {
                    $idx = $idx * 26 + (ord($ch) - 64);
                }
                $idx--;
                $type = (string) $c['t'];
                $val = $type === 's' ? ($shared[(int) $c->v] ?? '') : (string) ($c->v ?? ($c->is->t ?? ''));
                $cells[$idx] = $val;
            }
            if ($cells === []) {
                continue;
            }
            $max = max(array_keys($cells));
            $line = [];
            for ($i = 0; $i <= $max; $i++) {
                $line[] = $cells[$i] ?? '';
            }
            $matrix[] = $line;
        }
        if ($matrix === []) {
            return [];
        }
        $headers = array_map(static fn ($h) => strtolower(trim((string) $h)), array_shift($matrix));
        $out = [];
        foreach ($matrix as $line) {
            if (trim(implode('', $line)) === '') {
                continue;
            }
            $assoc = [];
            foreach ($headers as $i => $h) {
                if ($h !== '') {
                    $assoc[$h] = (string) ($line[$i] ?? '');
                }
            }
            $out[] = $assoc;
        }

        return $out;
    }
}
