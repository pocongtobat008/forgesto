<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Models\BookPeriod;

/**
 * Report tahun buku.
 *
 *  - GET /reports              : daftar hasil semua tahun buku + tren + versus.
 *  - GET /reports/{id}         : report lengkap satu tahun buku.
 *  - GET /reports/{id}/export  : unduh CSV hasil tahun buku tersebut.
 *
 * Sumber data:
 *  - periode yang sudah ditutup  -> period_snapshots (snapshot beku).
 *  - periode yang masih berjalan -> items (data live), ditandai "live".
 */
class ReportController extends Controller
{
    private const MONTHS = [
        1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember',
    ];

    /* ------------------------------------------------------------------ */
    /* Routes                                                              */
    /* ------------------------------------------------------------------ */

    public function index(): string
    {
        $this->guard();

        $years = $this->allYearSummaries();

        $latestId = $years !== [] ? (int) $years[0]['period']['id'] : 0;
        $prevId   = $years[1]['period']['id'] ?? 0;

        $aId = (int) ($_GET['a'] ?? $prevId);
        $bId = (int) ($_GET['b'] ?? $latestId);

        $byId = [];
        foreach ($years as $y) {
            $byId[(int) $y['period']['id']] = $y;
        }

        if (! isset($byId[$aId]) && $years !== []) {
            $aId = (int) ($years[1]['period']['id'] ?? $years[0]['period']['id']);
        }
        if (! isset($byId[$bId]) && $years !== []) {
            $bId = (int) $years[0]['period']['id'];
        }

        $versus = null;
        if (isset($byId[$aId], $byId[$bId]) && $aId !== $bId) {
            $versus = [
                'a'      => $byId[$aId],
                'b'      => $byId[$bId],
                'rows'   => $this->versusRows($byId[$aId]['metrics'], $byId[$bId]['metrics']),
                'groups' => $this->versusGroups($byId[$aId], $byId[$bId]),
            ];
        }

        return $this->view('reports/index', [
            'title'   => 'Report',
            'years'   => $years,
            'chart'   => $this->chartData($years),
            'versus'  => $versus,
            'aId'     => $aId,
            'bId'     => $bId,
            'totals'  => $this->totals($years),
        ]);
    }

    public function detail(array $params = []): string
    {
        $this->guard();

        $period = $this->findPeriod((int) ($params['id'] ?? 0));
        $metrics = $this->metrics((int) $period['id'], (string) $period['status']);
        $snapshot = $metrics['source'] === 'snapshot';

        return $this->view('reports/detail', [
            'title'    => 'Report ' . $this->label((string) $period['audit_month']),
            'period'   => $period,
            'metrics'  => $metrics,
            'groups'   => $this->groupRows((int) $period['id'], $snapshot),
            'items'    => $this->topItems((int) $period['id'], $snapshot),
            'statuses' => $this->statusBreakdown((int) $period['id'], $snapshot),
            'label'    => $this->label((string) $period['audit_month']),
        ]);
    }

    /** CSV hasil satu tahun buku (item level + ringkasan). */
    public function export(array $params = []): string
    {
        $this->guard();

        $period  = $this->findPeriod((int) ($params['id'] ?? 0));
        $id      = (int) $period['id'];
        $metrics = $this->metrics($id, (string) $period['status']);
        $snapshot = $metrics['source'] === 'snapshot';

        $rows = $snapshot
            ? $this->query(
                'SELECT s.item_code, s.item_name, COALESCE(g.name, \'\') AS group_name, \'\' AS unit,
                        s.expected_qty, s.counted_qty, s.difference, s.unit_price,
                        COALESCE(s.difference, 0) * s.unit_price AS diff_value,
                        s.count_status, s.audit_status
                 FROM period_snapshots s
                 LEFT JOIN `groups` g ON g.id = s.group_id
                 WHERE s.period_id = ?
                 ORDER BY ABS(COALESCE(s.difference, 0) * s.unit_price) DESC, s.item_code ASC',
                [$id]
            )
            : $this->query(
                'SELECT i.code AS item_code, i.name AS item_name, COALESCE(g.name, \'\') AS group_name, i.unit,
                        i.expected_qty, i.counted_qty,
                        (COALESCE(i.counted_qty, 0) - i.expected_qty) AS difference, i.unit_price,
                        (COALESCE(i.counted_qty, 0) - i.expected_qty) * i.unit_price AS diff_value,
                        i.count_status, i.audit_status
                 FROM items i
                 LEFT JOIN `groups` g ON g.id = i.group_id
                 WHERE i.counted_qty IS NOT NULL
                 ORDER BY ABS((COALESCE(i.counted_qty, 0) - i.expected_qty) * i.unit_price) DESC, i.code ASC',
                []
            );

        $label = $this->label((string) $period['audit_month']);
        $out = "\xEF\xBB\xBF";
        $out .= "Report Tahun Buku;" . $this->csvCell($label) . "\n";
        $out .= "Status;" . $this->csvCell((string) $period['status']) . "\n";
        $out .= "Sumber data;" . ($snapshot ? 'snapshot (ditutup)' : 'live (berjalan)') . "\n";
        $out .= "Dibuat;" . date('d/m/Y H:i') . "\n\n";

        $out .= "Ringkasan;Nilai\n";
        $out .= "Item tercatat;" . $metrics['items'] . "\n";
        $out .= "Qty sistem;" . $metrics['expected'] . "\n";
        $out .= "Qty hitung;" . $metrics['counted'] . "\n";
        $out .= "Belum dihitung;" . $metrics['not_counted'] . "\n";
        $out .= "Selisih qty;" . $metrics['diff_qty'] . "\n";
        $out .= "Nilai selisih;" . $this->num($metrics['diff_value']) . "\n";
        $out .= "Item berselisih;" . $metrics['diff_items'] . "\n";
        $out .= "Group berselisih;" . $metrics['groups_diff'] . " dari " . $metrics['groups'] . "\n\n";

        $out .= implode(';', [
            'Kode Item', 'Nama Item', 'Group', 'Satuan', 'Qty Sistem', 'Qty Hitung',
            'Selisih Qty', 'Harga Satuan', 'Nilai Selisih', 'Status Hitung', 'Status Audit',
        ]) . "\n";

        foreach ($rows as $r) {
            $out .= implode(';', [
                $this->csvCell((string) ($r['item_code'] ?? '')),
                $this->csvCell((string) ($r['item_name'] ?? '')),
                $this->csvCell((string) ($r['group_name'] ?? '')),
                $this->csvCell((string) ($r['unit'] ?? '')),
                (string) (int) ($r['expected_qty'] ?? 0),
                $r['counted_qty'] === null ? '' : (string) (int) $r['counted_qty'],
                (string) (int) ($r['difference'] ?? 0),
                $this->num((float) ($r['unit_price'] ?? 0)),
                $this->num((float) ($r['diff_value'] ?? 0)),
                $this->csvCell((string) ($r['count_status'] ?? '')),
                $this->csvCell((string) ($r['audit_status'] ?? '')),
            ]) . "\n";
        }

        if (! headers_sent()) {
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="report-' . preg_replace('/[^0-9a-z\-]/i', '-', (string) $period['audit_month']) . '.csv"');
        }

        return $out;
    }

    /* ------------------------------------------------------------------ */
    /* Data                                                               */
    /* ------------------------------------------------------------------ */

    /** @return list<array{period: array, metrics: array}> */
    private function allYearSummaries(): array
    {
        $periods = $this->query(
            'SELECT id, audit_month, audit_start, audit_end, status, is_active, closed_at
             FROM audit_periods
             ORDER BY audit_month DESC, id DESC'
        );

        $out = [];
        foreach ($periods as $p) {
            $out[] = [
                'period'  => $p,
                'metrics' => $this->metrics((int) $p['id'], (string) $p['status']),
            ];
        }

        return $out;
    }

    /**
     * Metrik ringkas satu periode.
     *
     * @return array<string, mixed>
     */
    private function metrics(int $periodId, string $status): array
    {
        $hasSnapshot = (int) ($this->query(
            'SELECT COUNT(*) AS c FROM period_snapshots WHERE period_id = ?',
            [$periodId]
        )[0]['c'] ?? 0) > 0;

        if ($hasSnapshot) {
            $row = $this->query(
                "SELECT COUNT(*) AS items,
                        COALESCE(SUM(s.expected_qty), 0) AS expected,
                        COALESCE(SUM(COALESCE(s.counted_qty, 0)), 0) AS counted,
                        COALESCE(SUM(s.counted_qty IS NULL), 0) AS not_counted,
                        COALESCE(SUM(COALESCE(s.difference, 0)), 0) AS diff_qty,
                        COALESCE(SUM(COALESCE(s.difference, 0) * s.unit_price), 0) AS diff_value,
                        COALESCE(SUM(CASE WHEN COALESCE(s.difference, 0) <> 0 THEN 1 ELSE 0 END), 0) AS diff_items,
                        COUNT(DISTINCT s.group_id) AS `groups`,
                        COUNT(DISTINCT CASE WHEN COALESCE(s.difference, 0) <> 0 THEN s.group_id END) AS groups_diff,
                        COALESCE(SUM(CASE WHEN s.count_status IN ('posted','submitted','audited','reconciled') THEN 1 ELSE 0 END), 0) AS posted,
                        COALESCE(SUM(CASE WHEN s.audit_status IN ('audited','resolved') THEN 1 ELSE 0 END), 0) AS audited
                 FROM period_snapshots s
                 WHERE s.period_id = ?",
                [$periodId]
            )[0] ?? [];

            $source = 'snapshot';
        } else {
            $row = $this->query(
                "SELECT COUNT(*) AS items,
                        COALESCE(SUM(i.expected_qty), 0) AS expected,
                        COALESCE(SUM(COALESCE(i.counted_qty, 0)), 0) AS counted,
                        COALESCE(SUM(i.counted_qty IS NULL), 0) AS not_counted,
                        COALESCE(SUM(COALESCE(i.counted_qty, 0) - i.expected_qty), 0) AS diff_qty,
                        COALESCE(SUM((COALESCE(i.counted_qty, 0) - i.expected_qty) * i.unit_price), 0) AS diff_value,
                        COALESCE(SUM(CASE WHEN i.counted_qty IS NOT NULL AND i.counted_qty <> i.expected_qty THEN 1 ELSE 0 END), 0) AS diff_items,
                        COUNT(DISTINCT i.group_id) AS `groups`,
                        COUNT(DISTINCT CASE WHEN i.counted_qty IS NOT NULL AND i.counted_qty <> i.expected_qty THEN i.group_id END) AS groups_diff,
                        COALESCE(SUM(CASE WHEN i.count_status IN ('posted','submitted','audited','reconciled') THEN 1 ELSE 0 END), 0) AS posted,
                        COALESCE(SUM(CASE WHEN i.audit_status IN ('audited','resolved') THEN 1 ELSE 0 END), 0) AS audited
                 FROM items i",
                []
            )[0] ?? [];

            $source = $status === 'open' ? 'live' : 'empty';
        }

        $items      = (int) ($row['items'] ?? 0);
        $posted     = (int) ($row['posted'] ?? 0);
        $audited    = (int) ($row['audited'] ?? 0);
        $notCounted = (int) ($row['not_counted'] ?? 0);

        return [
            'source'      => $source,
            'items'       => $items,
            'expected'    => (int) round((float) ($row['expected'] ?? 0)),
            'counted'     => (int) round((float) ($row['counted'] ?? 0)),
            'not_counted' => $notCounted,
            'diff_qty'    => (int) round((float) ($row['diff_qty'] ?? 0)),
            'diff_value'  => (float) ($row['diff_value'] ?? 0),
            'diff_items'  => (int) ($row['diff_items'] ?? 0),
            'groups'      => (int) ($row['groups'] ?? 0),
            'groups_diff' => (int) ($row['groups_diff'] ?? 0),
            'posted'      => $posted,
            'audited'     => $audited,
            'counted_pct' => $items > 0 ? round((($items - $notCounted) / $items) * 100, 1) : 0.0,
            'audit_pct'   => $items > 0 ? round(($audited / $items) * 100, 1) : 0.0,
        ];
    }

    /** @return list<array<string, mixed>> */
    private function groupRows(int $periodId, bool $snapshot): array
    {
        if ($snapshot) {
            return $this->query(
                "SELECT s.group_id,
                        COALESCE(g.name, '(tanpa group)') AS group_name,
                        COALESCE(g.code, '') AS group_code,
                        COUNT(*) AS items,
                        COALESCE(SUM(s.expected_qty), 0) AS expected,
                        COALESCE(SUM(COALESCE(s.counted_qty, 0)), 0) AS counted,
                        COALESCE(SUM(s.counted_qty IS NULL), 0) AS not_counted,
                        COALESCE(SUM(COALESCE(s.difference, 0)), 0) AS diff_qty,
                        COALESCE(SUM(COALESCE(s.difference, 0) * s.unit_price), 0) AS diff_value,
                        COALESCE(SUM(CASE WHEN COALESCE(s.difference, 0) <> 0 THEN 1 ELSE 0 END), 0) AS diff_items
                 FROM period_snapshots s
                 LEFT JOIN `groups` g ON g.id = s.group_id
                 WHERE s.period_id = ?
                 GROUP BY s.group_id, g.name, g.code
                 ORDER BY ABS(COALESCE(SUM(COALESCE(s.difference, 0) * s.unit_price), 0)) DESC, group_name ASC",
                [$periodId]
            );
        }

        return $this->query(
            "SELECT i.group_id,
                    COALESCE(g.name, '(tanpa group)') AS group_name,
                    COALESCE(g.code, '') AS group_code,
                    COUNT(*) AS items,
                    COALESCE(SUM(i.expected_qty), 0) AS expected,
                    COALESCE(SUM(COALESCE(i.counted_qty, 0)), 0) AS counted,
                    COALESCE(SUM(i.counted_qty IS NULL), 0) AS not_counted,
                    COALESCE(SUM(COALESCE(i.counted_qty, 0) - i.expected_qty), 0) AS diff_qty,
                    COALESCE(SUM((COALESCE(i.counted_qty, 0) - i.expected_qty) * i.unit_price), 0) AS diff_value,
                    COALESCE(SUM(CASE WHEN i.counted_qty IS NOT NULL AND i.counted_qty <> i.expected_qty THEN 1 ELSE 0 END), 0) AS diff_items
             FROM items i
             LEFT JOIN `groups` g ON g.id = i.group_id
             GROUP BY i.group_id, g.name, g.code
             ORDER BY ABS(COALESCE(SUM((COALESCE(i.counted_qty, 0) - i.expected_qty) * i.unit_price), 0)) DESC, group_name ASC",
            []
        );
    }

    /** @return list<array<string, mixed>> */
    private function topItems(int $periodId, bool $snapshot, int $limit = 15): array
    {
        if ($snapshot) {
            return $this->query(
                "SELECT s.item_code, s.item_name, COALESCE(g.name, '-') AS group_name, '' AS unit,
                        s.expected_qty, s.counted_qty, COALESCE(s.difference, 0) AS difference,
                        s.unit_price, COALESCE(s.difference, 0) * s.unit_price AS diff_value
                 FROM period_snapshots s
                 LEFT JOIN `groups` g ON g.id = s.group_id
                 WHERE s.period_id = ? AND COALESCE(s.difference, 0) <> 0
                 ORDER BY ABS(COALESCE(s.difference, 0) * s.unit_price) DESC, ABS(COALESCE(s.difference, 0)) DESC
                 LIMIT " . $limit,
                [$periodId]
            );
        }

        return $this->query(
            "SELECT i.code AS item_code, i.name AS item_name, COALESCE(g.name, '-') AS group_name, i.unit,
                    i.expected_qty, i.counted_qty,
                    (COALESCE(i.counted_qty, 0) - i.expected_qty) AS difference,
                    i.unit_price,
                    (COALESCE(i.counted_qty, 0) - i.expected_qty) * i.unit_price AS diff_value
             FROM items i
             LEFT JOIN `groups` g ON g.id = i.group_id
             WHERE i.counted_qty IS NOT NULL AND i.counted_qty <> i.expected_qty
             ORDER BY ABS((COALESCE(i.counted_qty, 0) - i.expected_qty) * i.unit_price) DESC,
                      ABS(COALESCE(i.counted_qty, 0) - i.expected_qty) DESC
             LIMIT " . $limit,
            []
        );
    }

    /** @return array{hitung: list<array>, audit: list<array>} */
    private function statusBreakdown(int $periodId, bool $snapshot): array
    {
        $t = $snapshot ? 'period_snapshots' : 'items';
        $where = $snapshot ? 'period_id = ?' : '1 = 1';
        $bind = $snapshot ? [$periodId] : [];

        return [
            'hitung' => $this->query(
                "SELECT COALESCE(count_status, 'pending') AS status, COUNT(*) AS c
                 FROM {$t} WHERE {$where} GROUP BY COALESCE(count_status, 'pending') ORDER BY c DESC",
                $bind
            ),
            'audit' => $this->query(
                "SELECT COALESCE(audit_status, 'pending') AS status, COUNT(*) AS c
                 FROM {$t} WHERE {$where} GROUP BY COALESCE(audit_status, 'pending') ORDER BY c DESC",
                $bind
            ),
        ];
    }

    /**
     * Baris tabel versus antar dua tahun buku.
     *
     * @return list<array{label: string, type: string, a: float, b: float}>
     */
    private function versusRows(array $a, array $b): array
    {
        return [
            ['label' => 'Item tercatat', 'type' => 'int', 'a' => $a['items'], 'b' => $b['items']],
            ['label' => 'Qty sistem', 'type' => 'int', 'a' => $a['expected'], 'b' => $b['expected']],
            ['label' => 'Qty hitung fisik', 'type' => 'int', 'a' => $a['counted'], 'b' => $b['counted']],
            ['label' => 'Selisih qty', 'type' => 'int', 'a' => $a['diff_qty'], 'b' => $b['diff_qty']],
            ['label' => 'Nilai selisih', 'type' => 'money', 'a' => $a['diff_value'], 'b' => $b['diff_value']],
            ['label' => 'Item berselisih', 'type' => 'int', 'a' => $a['diff_items'], 'b' => $b['diff_items']],
            ['label' => 'Group berselisih', 'type' => 'int', 'a' => $a['groups_diff'], 'b' => $b['groups_diff']],
            ['label' => 'Progress hitung (%)', 'type' => 'pct', 'a' => $a['counted_pct'], 'b' => $b['counted_pct']],
            ['label' => 'Progress audit (%)', 'type' => 'pct', 'a' => $a['audit_pct'], 'b' => $b['audit_pct']],
        ];
    }

    /** @return list<array<string, mixed>> */
    private function versusGroups(array $a, array $b): array
    {
        $aRows = $this->groupRows((int) $a['period']['id'], $a['metrics']['source'] === 'snapshot');
        $bRows = $this->groupRows((int) $b['period']['id'], $b['metrics']['source'] === 'snapshot');

        $index = [];
        foreach ($aRows as $r) {
            $key = (string) ($r['group_id'] ?? '0');
            $index[$key] = [
                'name'  => (string) $r['group_name'],
                'a_qty' => (float) $r['diff_qty'],
                'a_val' => (float) $r['diff_value'],
                'b_qty' => 0.0,
                'b_val' => 0.0,
            ];
        }
        foreach ($bRows as $r) {
            $key = (string) ($r['group_id'] ?? '0');
            if (! isset($index[$key])) {
                $index[$key] = [
                    'name'  => (string) $r['group_name'],
                    'a_qty' => 0.0,
                    'a_val' => 0.0,
                    'b_qty' => 0.0,
                    'b_val' => 0.0,
                ];
            }
            $index[$key]['b_qty'] = (float) $r['diff_qty'];
            $index[$key]['b_val'] = (float) $r['diff_value'];
        }

        $out = array_values($index);
        usort($out, static fn (array $x, array $y): int => abs($y['b_val']) <=> abs($x['b_val']));

        return $out;
    }

    /**
     * Data grafik tren: nilai & qty selisih per tahun (urut lama -> baru).
     *
     * @param list<array{period: array, metrics: array}> $years
     * @return array{years: list<string>, value: list<float>, qty: list<int>}
     */
    private function chartData(array $years): array
    {
        $years = array_reverse($years);
        $labels = [];
        $value  = [];
        $qty    = [];

        foreach ($years as $y) {
            $labels[] = $this->label((string) $y['period']['audit_month'], true);
            $value[]  = (float) $y['metrics']['diff_value'];
            $qty[]    = (int) $y['metrics']['diff_qty'];
        }

        return ['years' => $labels, 'value' => $value, 'qty' => $qty];
    }

    /**
     * @param list<array{period: array, metrics: array}> $years
     * @return array<string, mixed>
     */
    private function totals(array $years): array
    {
        $t = [
            'years' => count($years), 'closed' => 0, 'items' => 0, 'diff_value' => 0.0,
            'diff_qty' => 0, 'diff_items' => 0, 'worst' => null, 'best' => null,
        ];

        foreach ($years as $y) {
            if ((string) $y['period']['status'] === 'closed') {
                $t['closed']++;
            }
            $t['items'] += (int) $y['metrics']['items'];
            $t['diff_value'] += (float) $y['metrics']['diff_value'];
            $t['diff_qty'] += (int) $y['metrics']['diff_qty'];
            $t['diff_items'] += (int) $y['metrics']['diff_items'];

            $v = (float) $y['metrics']['diff_value'];
            if ($t['worst'] === null || abs($v) > abs((float) $t['worst']['metrics']['diff_value'])) {
                $t['worst'] = $y;
            }
            if ($t['best'] === null || abs($v) < abs((float) $t['best']['metrics']['diff_value'])) {
                $t['best'] = $y;
            }
        }

        return $t;
    }

    /* ------------------------------------------------------------------ */
    /* Helpers                                                            */
    /* ------------------------------------------------------------------ */

    private function guard(): void
    {
        if (! \App\Core\Auth::check()) {
            if (! headers_sent()) {
                header('Location: ' . url('login'));
            }
            exit;
        }

        if (! function_exists('can_view_menu') || ! can_view_menu('reports')) {
            throw new \App\Core\HttpException(403, 'Anda tidak memiliki akses ke menu Report.');
        }
    }

    /** @return array<string, mixed> */
    private function findPeriod(int $id): array
    {
        $p = (new BookPeriod())->find($id);
        if ($p === null) {
            throw new \App\Core\HttpException(404, 'Tahun buku tidak ditemukan.');
        }

        return $p;
    }

    /** @return list<array<string, mixed>> */
    private function query(string $sql, array $bindings = []): array
    {
        return (new BookPeriod())->query($sql, $bindings);
    }

    private function label(string $ym, bool $short = false): string
    {
        $t = strtotime($ym . '-01');
        if ($t === false) {
            return $ym;
        }
        $name = self::MONTHS[(int) date('n', $t)] ?? '';
        if ($short) {
            return mb_substr($name, 0, 3) . ' ' . date('y', $t);
        }

        return $name . ' ' . date('Y', $t);
    }

    private function num(float $v): string
    {
        return number_format($v, 0, ',', '.');
    }

    private function csvCell(string $v): string
    {
        $v = str_replace(["\r", "\n", ';'], ' ', $v);

        return trim($v);
    }
}
