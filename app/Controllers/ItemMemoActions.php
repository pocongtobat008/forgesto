<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Request;
use App\Models\Item;

/**
 * Marking Memo.
 * - memo(): halaman kelola (total tercetak, contoh memo, daftar 50 item per halaman).
 * - memoPreview(): preview di tab baru, 50 memo per halaman (sheet A4 berisi 4 memo).
 * Helper bersama dipakai juga oleh ItemMemoPdfActions.
 */
trait ItemMemoActions
{
    /** GET /items/memo : halaman kelola marking memo. */
    public function memo(): string
    {
        $this->authorize('count_data');

        [$where, $bind] = Auth::groupScope('i.group_id');
        $w = $where !== '' ? ' WHERE ' . $where : '';

        $total   = $this->countScalar('SELECT COUNT(*) FROM items i' . $w, $bind);
        $printed = $this->countScalar(
            'SELECT COUNT(*) FROM memo_prints mp JOIN items i ON i.id = mp.item_id' . $w,
            $bind
        );
        $unique = $this->countScalar(
            'SELECT COUNT(DISTINCT mp.item_id) FROM memo_prints mp JOIN items i ON i.id = mp.item_id' . $w,
            $bind
        );

        $perPage  = 50;

        // --- Tab + pencarian (Semua / Sudah dicetak / Belum dicetak) ---
        $filter = Request::string('printed', '');
        $filter = in_array($filter, ['yes', 'no'], true) ? $filter : '';
        $q      = trim(Request::string('q', ''));

        $conds = [];
        if ($where !== '') {
            $conds[] = $where;
        }
        if ($q !== '') {
            $conds[] = '(i.code LIKE ? OR i.name LIKE ? OR g.name LIKE ? OR l.name LIKE ?)';
        }
        if ($filter === 'yes') {
            $conds[] = 'EXISTS (SELECT 1 FROM memo_prints mp WHERE mp.item_id = i.id)';
        } elseif ($filter === 'no') {
            $conds[] = 'NOT EXISTS (SELECT 1 FROM memo_prints mp WHERE mp.item_id = i.id)';
        }
        $cw     = $conds === [] ? '' : ' WHERE ' . implode(' AND ', $conds);
        $bind2  = $bind;
        if ($q !== '') {
            $like = '%' . $q . '%';
            array_push($bind2, $like, $like, $like, $like);
        }

        $filteredTotal = $this->countScalar(
            'SELECT COUNT(*) FROM items i
               JOIN `groups` g ON g.id = i.group_id
               LEFT JOIN locations l ON l.id = i.location_id' . $cw,
            $bind2
        );

        $lastPage = max(1, (int) ceil($filteredTotal / $perPage));
        $page     = min(max(1, Request::int('page', 1)), $lastPage);
        $offset   = ($page - 1) * $perPage;

        $stmt = Database::connection()->prepare(
            'SELECT i.id, i.code, i.name, i.expected_qty, g.name AS group_name, l.name AS location_name,
                    (SELECT COUNT(*) FROM memo_prints mp WHERE mp.item_id = i.id) AS print_count,
                    (SELECT MAX(mp.created_at) FROM memo_prints mp WHERE mp.item_id = i.id) AS last_printed
               FROM items i
               JOIN `groups` g ON g.id = i.group_id
               LEFT JOIN locations l ON l.id = i.location_id'
            . $cw . ' ORDER BY i.code LIMIT ' . $perPage . ' OFFSET ' . $offset
        );
        $stmt->execute($bind2);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $sampleIds = $this->scopeIds(4);
        $sampleMemos = $this->buildMemos($sampleIds);

        return $this->view('items/memo_index', [
            'periodLabel' => $this->periodLabel(),
            'title'     => __('Marking Memo'),
            'q'         => $q,
            'filter'    => $filter,
            'stats'     => [
                'total'   => $total,
                'printed' => $printed,
                'unique'  => $unique,
                'never'   => max(0, $total - $unique),
            ],
            'paginator' => [
                'items'     => $rows,
                'total'     => $filteredTotal,
                'page'      => $page,
                'per_page'  => $perPage,
                'last_page' => $lastPage,
                'from'      => $filteredTotal === 0 ? 0 : $offset + 1,
                'to'        => min($offset + $perPage, $filteredTotal),
            ],
            'warehouseLabel' => (string) ($sampleMemos[0]['warehouse'] ?? ''),
            'sample'    => $sampleMemos,
            'sampleUrl' => $sampleIds !== [] ? url('items/memo/preview?ids=' . implode(',', $sampleIds)) : null,
        ]);
    }

    /** GET /items/memo/preview?ids=1,2  atau  ?all=1 ; 50 memo per halaman (buka di tab baru). */
    public function memoPreview(): string
    {
        $this->authorize('count_data');

        $all = Request::string('all', '') === '1';
        $ids = $all ? $this->scopeIds(0) : $this->parseIds(Request::string('ids', ''));

        $total    = count($ids);
        $perPage  = 50;
        $lastPage = max(1, (int) ceil($total / $perPage));
        $page     = min(max(1, Request::int('page', 1)), $lastPage);
        $offset   = ($page - 1) * $perPage;
        $chunk    = array_slice($ids, $offset, $perPage);

        $base = $all ? 'all=1' : 'ids=' . implode(',', $ids);

        return $this->view('items/memo', [
            'title'  => __('Preview Marking Memo'),
            'periodLabel' => $this->periodLabel(),
            'memos'  => $this->buildMemos($chunk),
            'pdf'    => false,
            'pager'  => [
                'page'      => $page,
                'last_page' => $lastPage,
                'total'     => $total,
                'from'      => $total === 0 ? 0 : $offset + 1,
                'to'        => min($offset + $perPage, $total),
                'base'      => $base,
            ],
            'pdfUrl' => $chunk !== [] ? url('items/memo.pdf?ids=' . implode(',', $chunk)) : null,
        ], null);
    }

    /** Ambil id dari string "1,2,3": unik, positif, dibatasi $max. */
    protected function parseIds(string $raw, int $max = 500): array
    {
        if ($raw === '') {
            return [];
        }
        $ids = array_values(array_unique(array_filter(
            array_map('intval', explode(',', $raw)),
            static fn (int $n): bool => $n > 0
        )));

        return array_slice($ids, 0, $max);
    }

    /** Id item dalam scope user, urut kode. $limit 0 = semua. */
    protected function scopeIds(int $limit = 0): array
    {
        [$where, $bind] = Auth::groupScope('i.group_id');
        $sql = 'SELECT i.id FROM items i' . ($where !== '' ? ' WHERE ' . $where : '') . ' ORDER BY i.code';
        if ($limit > 0) {
            $sql .= ' LIMIT ' . $limit;
        }
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($bind);

        return array_map('intval', array_column($stmt->fetchAll(\PDO::FETCH_ASSOC), 'id'));
    }

    /**
     * Bentuk data memo per item (dengan QR), menghormati akses group.
     *
     * @param int[] $ids
     * @return array<int, array<string, mixed>>
     */
    /** Nama gudang milik grup (cache per request). */
    protected function warehouseNameForGroup(int $groupId): string
    {
        static $cache = [];
        if (! array_key_exists($groupId, $cache)) {
            $st = \App\Core\Database::connection()->prepare(
                'SELECT w.name FROM `groups` g JOIN warehouses w ON w.id = g.warehouse_id WHERE g.id = ? LIMIT 1'
            );
            $st->execute([$groupId]);
            $cache[$groupId] = (string) ($st->fetchColumn() ?: '');
        }
        return $cache[$groupId];
    }

    /** Label periode tahun buku aktif, mis. "Oktober 2026". */
    protected function periodLabel(): string
    {
        $months = ['01'=>'Januari','02'=>'Februari','03'=>'Maret','04'=>'April','05'=>'Mei','06'=>'Juni',
                   '07'=>'Juli','08'=>'Agustus','09'=>'September','10'=>'Oktober','11'=>'November','12'=>'Desember'];
        $p = (new \App\Models\AuditPeriod())->active();
        if (! $p) { return ''; }
        $parts = explode('-', (string) ($p['audit_month'] ?? ''));
        return trim(($months[$parts[1] ?? ''] ?? '') . ' ' . ($parts[0] ?? ''));
    }

    protected function buildMemos(array $ids): array
    {
        $memos = [];
        $model = new Item();
        foreach ($ids as $id) {
            $item = $model->findDetailed((int) $id);
            if ($item === null) {
                continue;
            }
            $this->ensureGroupAccess((int) $item['group_id']);
            $memos[] = [
                'id'            => (int) $item['id'],
                'code'          => (string) $item['code'],
                'name'          => (string) $item['name'],
                'id_barang'     => $this->idBarang($item),
                'sub_inventory' => (string) ($item['sub_inventory'] ?? ''),
                'category'      => (string) ($item['category'] ?? ''),
                'unit'          => (string) ($item['unit'] ?? ''),
                'location'      => (string) ($item['location_name'] ?? ''),
                'device'        => (string) ($item['device_name'] ?? ''),
                'warehouse'     => $this->warehouseNameForGroup((int) $item['group_id']),
                'group'         => (string) ($item['group_name'] ?? ''),
                'observers'     => (string) ($item['observers'] ?? ''),
                'auditors'      => (string) ($item['auditors'] ?? ''),
                'qty'           => '',
                'qr'            => $this->qrSvg((string) $item['code']),
            ];
        }

        return $memos;
    }

    /**
     * ID barang cetak. Pakai kolom id_barang bila ada; kalau kosong susun
     * seperti aturan gudang: WE1 + kode + sub inventory + lokasi.
     *
     * @param array<string,mixed> $item
     */
    protected function idBarang(array $item): string
    {
        $stored = trim((string) ($item['id_barang'] ?? ''));
        if ($stored !== '') {
            return $stored;
        }

        $parts = 'WE1' . (string) ($item['code'] ?? '')
            . (string) ($item['sub_inventory'] ?? '')
            . (string) ($item['location_code'] ?? '');

        return str_replace(' ', '', $parts);
    }

    protected function countScalar(string $sql, array $bind): int
    {
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($bind);

        return (int) $stmt->fetchColumn();
    }

    /** Hasilkan SVG QR via helper Python (sf_qr_svg.py). Payload lewat stdin, bukan argumen shell. */
    private function qrSvg(string $payload): string
    {
        // Cache file per payload: QR deterministik, jadi tidak perlu proses Python tiap render.
        $cacheDir = dirname(__DIR__, 2) . '/storage/qr_cache';
        $cacheFile = $cacheDir . '/' . sha1($payload) . '.svg';
        if (is_file($cacheFile)) {
            $cached = (string) file_get_contents($cacheFile);
            if (str_contains($cached, '<svg')) {
                return $cached;
            }
        }

        $cmd = ['/usr/bin/python3', '/usr/local/bin/sf_qr_svg.py'];
        $spec = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $proc = proc_open($cmd, $spec, $pipes);
        if (! is_resource($proc)) {
            return '';
        }
        fwrite($pipes[0], $payload);
        fclose($pipes[0]);
        $svg = (string) stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($proc);

        // Hanya keluarkan SVG yang valid (tidak ada markup lain dari stdout).
        $start = strpos($svg, '<svg');
        if ($start === false) {
            return '';
        }
        $out = substr($svg, $start);
        if (! is_dir($cacheDir)) {
            @mkdir($cacheDir, 0775, true);
        }
        @file_put_contents($cacheFile, $out);

        return $out;
    }
}
/* I18N5-CTRL */
