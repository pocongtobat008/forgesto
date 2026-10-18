<?php
/**
 * Report tahun buku — daftar hasil semua periode + tren + versus.
 *
 * @var array $years   list<array{period: array, metrics: array}>
 * @var array $chart   ['years' => string[], 'value' => float[], 'qty' => int[]]
 * @var ?array $versus ['a' => ..., 'b' => ..., 'rows' => [...], 'groups' => [...]]
 * @var int   $aId
 * @var int   $bId
 * @var array $totals
 */
$years  = $years ?? [];
$chart  = $chart ?? ['years' => [], 'value' => [], 'qty' => []];
$versus = $versus ?? null;
$aId    = (int) ($aId ?? 0);
$bId    = (int) ($bId ?? 0);
$totals = $totals ?? [];

$rp = static fn ($n): string => 'Rp ' . number_format((float) $n, 0, ',', '.');
$rpS = static function ($n): string {
    $v = (float) $n;
    return ($v < 0 ? '−' : ($v > 0 ? '+' : '')) . 'Rp ' . number_format(abs($v), 0, ',', '.');
};
$num = static fn ($n): string => number_format((float) $n, 0, ',', '.');
$signed = static fn ($n): string => ((float) $n > 0 ? '+' : '') . number_format((float) $n, 0, ',', '.');
$tone = static fn ($n): string => (float) $n < 0 ? 'var(--danger-text)' : ((float) $n > 0 ? 'var(--info-text)' : 'var(--text-3)');

$bulan = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
$labelBulan = static function (string $ym) use ($bulan): string {
    $t = strtotime($ym . '-01');
    return $t === false ? $ym : $bulan[(int) date('n', $t)] . ' ' . date('Y', $t);
};
$labelShort = static function (string $ym) use ($bulan): string {
    $t = strtotime($ym . '-01');
    return $t === false ? $ym : mb_substr($bulan[(int) date('n', $t)], 0, 3) . ' ' . date('y', $t);
};

$canExport = function_exists('can') ? can('export_data') : false;

/* ---- data grafik ---- */
$cLabels = $chart['years'] ?? [];
$cValues = $chart['value'] ?? [];
$cQty    = $chart['qty'] ?? [];
$n       = count($cValues);
$max     = 1.0;
foreach ($cValues as $v) {
    $max = max($max, abs((float) $v));
}
$barW  = 44;
$gap   = 36;
if ($n > 0 && $n < 6) {
    $gap = 36 + (int) round((6 - $n) * 22); // sedikit periode -> renggangkan
}
$plotH = 140;
$top   = 26;
$vw    = max(360, $n * ($barW + $gap) + $gap);
$baseY = $top + $plotH;
?>
<?= page_hero() ?>

<div class="page-head has-hero">
    <div>
        <h1><?= __('Report Tahun Buku') ?></h1>
        <p>Hasil setiap tahun buku, tren antar periode, dan perbandingan (versus) dua tahun buku.</p>
    </div>
    <div class="spacer"></div>
    <div class="flex gap-sm items-center" style="flex-wrap:wrap;">
        <button type="button" class="btn btn-outline no-print" onclick="window.print()" style="min-height:42px;">
            <?= icon('printer') ?> <?= __('Cetak') ?>
        </button>
        <a class="btn btn-outline" href="<?= url('tahun-buku') ?>" style="min-height:42px;">
            <?= icon('archive') ?> <?= __('Tahun Buku') ?>
        </a>
    </div>
</div>

<?php if ($years === []): ?>
    <section class="card">
        <div class="card-header"><h2><?= icon('chart') ?> <?= __('Belum ada data') ?></h2></div>
        <div class="card-body">
            <p class="muted" style="margin:0;">
                Belum ada tahun buku yang tercatat. Buat periode di menu
                <a href="<?= url('tahun-buku') ?>">Tahun Buku</a> lalu jalankan hitung &amp; audit.
            </p>
        </div>
    </section>
<?php elseif (! can_see_stock_qty()): ?>
<section class="card">
    <div class="card-body" style="padding:1.6rem;">
        <div class="empty-state">
            <div class="empty-icon"><?= icon('lock') ?></div>
            <h3><?= __('Akses terbatas') ?></h3>
            <p><?= __('Qty sistem dan nilai selisih hanya dapat dilihat oleh Admin STO / Power Admin.') ?></p>
        </div>
    </div>
</section>

<?php else: ?>

<!-- ============================ KPI ============================ -->
<div class="grid grid-4" style="margin-bottom:1rem;">
    <div class="stat is-brand" style="--i:0">
        <div class="stat-icon"><?= icon('archive') ?></div>
        <div class="stat-label"><?= __('Tahun buku') ?></div>
        <div class="stat-value"><?= $num($totals['years'] ?? 0) ?></div>
        <div class="stat-meta"><?= $num($totals['closed'] ?? 0) ?> <?= __('sudah ditutup') ?></div>
    </div>
    <div class="stat is-info" style="--i:1">
        <div class="stat-icon"><?= icon('scale') ?></div>
        <div class="stat-label"><?= __('Akumulasi nilai selisih') ?></div>
        <div class="stat-value" style="color:<?= $tone($totals['diff_value'] ?? 0) ?>;"><?= $rpS($totals['diff_value'] ?? 0) ?></div>
        <div class="stat-meta"><?= __('seluruh tahun buku') ?></div>
    </div>
    <div class="stat is-warning" style="--i:2">
        <div class="stat-icon"><?= icon('alert-circle') ?></div>
        <div class="stat-label"><?= __('Akumulasi item berselisih') ?></div>
        <div class="stat-value"><?= $num($totals['diff_items'] ?? 0) ?></div>
        <div class="stat-meta"><?= __('dari') ?> <?= $num($totals['items'] ?? 0) ?> <?= __('baris item') ?></div>
    </div>
    <div class="stat is-danger" style="--i:3">
        <div class="stat-icon"><?= icon('trending-down') ?></div>
        <div class="stat-label"><?= __('Selisih terbesar') ?></div>
        <div class="stat-value" style="font-size:1.15rem;">
            <?= e($labelShort((string) ($totals['worst']['period']['audit_month'] ?? ''))) ?>
        </div>
        <div class="stat-meta"><?= $rpS($totals['worst']['metrics']['diff_value'] ?? 0) ?></div>
    </div>
</div>

<!-- ============================ TREN ============================ -->
<section class="card" style="margin-bottom:1rem;">
    <div class="card-header">
        <h2><?= icon('chart') ?> <?= __('Tren nilai selisih per tahun buku') ?></h2>
        <div class="spacer"></div>
        <span class="badge badge-muted"><?= count($cValues) ?> <?= __('periode') ?></span>
    </div>
    <div class="card-body">
        <?php if ($n === 0): ?>
            <p class="muted" style="margin:0;"><?= __('Belum ada data untuk digambarkan.') ?></p>
        <?php else: ?>
            <div style="overflow-x:auto;">
                <svg class="sf-chart" viewBox="0 0 <?= $vw ?> <?= $baseY + 42 ?>"
                     width="<?= $vw ?>" height="<?= $baseY + 42 ?>"
                     role="img" aria-label="<?= __('Grafik nilai selisih per tahun buku') ?>"
                     style="width:<?= $vw ?>px;max-width:100%;height:auto;display:block;margin-inline:auto;">
                    <?php for ($g = 0; $g <= 3; $g++): ?>
                        <?php $gy = $top + ($plotH / 3) * $g; ?>
                        <line x1="0" y1="<?= round($gy, 1) ?>" x2="<?= $vw ?>" y2="<?= round($gy, 1) ?>"
                              stroke="currentColor" stroke-opacity="0.10" stroke-width="1"/>
                    <?php endfor; ?>
                    <line x1="0" y1="<?= $baseY ?>" x2="<?= $vw ?>" y2="<?= $baseY ?>"
                          stroke="currentColor" stroke-opacity="0.28" stroke-width="1"/>

                    <?php foreach ($cValues as $i => $v): ?>
                        <?php
                        $v = (float) $v;
                        $h = (int) round(abs($v) / $max * $plotH);
                        $h = max($h, $v == 0.0 ? 2 : 4);
                        $x = $gap / 2 + $i * ($barW + $gap);
                        $y = $baseY - $h;
                        $fill = $v < 0 ? 'var(--danger)' : 'var(--success)';
                        ?>
                        <g>
                            <title><?= e($labelBulan((string) ($cLabels[$i] ?? ''))) ?>: <?= $rp($v) ?></title>
                            <rect x="<?= round($x, 1) ?>" y="<?= $y ?>" width="<?= $barW ?>" height="<?= $h ?>"
                                  rx="6" style="fill:<?= $fill ?>;fill-opacity:.85;"></rect>
                            <text x="<?= round($x + $barW / 2, 1) ?>" y="<?= max($y - 8, 12) ?>"
                                  text-anchor="middle" font-size="10.5" font-weight="700"
                                  style="fill:<?= $tone($v) ?>;"><?= e(format_rupiah_compact($v)) ?></text>
                            <text x="<?= round($x + $barW / 2, 1) ?>" y="<?= $baseY + 18 ?>"
                                  text-anchor="middle" font-size="11" font-weight="600"
                                  style="fill:var(--text-3);"><?= e((string) ($cLabels[$i] ?? '')) ?></text>
                            <text x="<?= round($x + $barW / 2, 1) ?>" y="<?= $baseY + 32 ?>"
                                  text-anchor="middle" font-size="9.5"
                                  style="fill:var(--text-faint);"><?= $signed($cQty[$i] ?? 0) ?> unit</text>
                        </g>
                    <?php endforeach; ?>
                </svg>
            </div>
        <?php endif; ?>
    </div>
</section>

<!-- ==================== HASIL SETIAP TAHUN BUKU ==================== -->
<section class="card" style="margin-bottom:1rem;">
    <div class="card-header">
        <h2><?= icon('clipboard-list') ?> <?= __('Hasil setiap tahun buku') ?></h2>
        <div class="spacer"></div>
        <span class="badge badge-muted"><?= count($years) ?> <?= __('tahun buku') ?></span>
    </div>
    <div class="table-wrap">
        <table class="data"><caption class="sr-only">Hasil setiap tahun buku</caption>
            <thead>
                <tr>
                    <th><?= __('Tahun buku') ?></th>
                    <th><?= __('Status') ?></th>
                    <th class="col-hide-tablet t-center"><?= __('Item') ?></th>
                    <th class="t-right"><?= __('Selisih qty') ?></th>
                    <th class="t-right"><?= __('Nilai selisih') ?></th>
                    <th class="col-hide-tablet t-center"><?= __('Item berselisih') ?></th>
                    <th class="col-hide-tablet t-center"><?= __('Group') ?></th>
                    <th class="col-hide-tablet t-center"><?= __('Hitung') ?></th>
                    <th class="t-center"><?= __('Report') ?></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($years as $y): ?>
                <?php
                $p = $y['period'];
                $m = $y['metrics'];
                $closed = (string) $p['status'] === 'closed';
                $live = $m['source'] === 'live';
                ?>
                <tr>
                    <td>
                        <div class="cell-title"><?= e($labelBulan((string) $p['audit_month'])) ?></div>
                        <div class="cell-sub">
                            <?php if ($closed): ?>
                                <?= __('ditutup') ?> <?= e((string) ($p['closed_at'] ?? '')) ?>
                            <?php elseif ((int) $p['is_active'] === 1): ?>
                                <?= __('periode aktif') ?>
                            <?php else: ?>
                                <?= __('belum ditutup') ?>
                            <?php endif; ?>
                        </div>
                    </td>
                    <td>
                        <span class="badge <?= $closed ? 'badge-success' : 'badge-warning' ?>">
                            <?= $closed ? __('Ditutup') : __('Berjalan') ?>
                        </span>
                        <?php if ($live): ?>
                            <span class="badge badge-muted" title="<?= __('Angka diambil dari data berjalan (belum ditutup)') ?>">live</span>
                        <?php endif; ?>
                    </td>
                    <td class="col-hide-tablet t-center"><?= $num($m['items']) ?></td>
                    <td class="t-right" style="color:<?= $tone($m['diff_qty']) ?>;font-weight:600;"><?= $signed($m['diff_qty']) ?></td>
                    <td class="t-right" style="color:<?= $tone($m['diff_value']) ?>;font-weight:700;white-space:nowrap;">
                        <?= $rpS($m['diff_value']) ?>
                    </td>
                    <td class="col-hide-tablet t-center"><?= $num($m['diff_items']) ?></td>
                    <td class="col-hide-tablet t-center"><?= (int) $m['groups_diff'] ?>/<?= (int) $m['groups'] ?></td>
                    <td class="col-hide-tablet t-center"><?= (float) $m['counted_pct'] ?>%</td>
                    <td class="t-center" style="white-space:nowrap;">
                        <a class="btn btn-sm btn-outline" href="<?= url('reports/' . (int) $p['id']) ?>"><?= icon('eye') ?> <?= __('Lihat') ?></a>
                        <?php if ($canExport): ?>
                            <a class="btn btn-sm btn-ghost" href="<?= url('reports/' . (int) $p['id'] . '/export') ?>"
                               title="<?= __('Unduh CSV hasil tahun buku ini') ?>"><?= icon('download') ?></a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr>
                    <th colspan="3" class="t-right"><?= __('Total') ?></th>
                    <th class="t-right" style="color:<?= $tone($totals['diff_qty'] ?? 0) ?>;"><?= $signed($totals['diff_qty'] ?? 0) ?></th>
                    <th class="t-right" style="color:<?= $tone($totals['diff_value'] ?? 0) ?>;"><?= $rpS($totals['diff_value'] ?? 0) ?></th>
                    <th class="col-hide-tablet t-center"><?= $num($totals['diff_items'] ?? 0) ?></th>
                    <th colspan="3"></th>
                </tr>
            </tfoot>
        </table>
    </div>
</section>

<!-- ============================ VERSUS ============================ -->
<section class="card" id="versus">
    <div class="card-header">
        <h2><?= icon('trending-up') ?> <?= __('Report versus tahun buku') ?></h2>
        <div class="spacer"></div>
        <span class="badge badge-muted"><?= __('bandingkan 2 periode') ?></span>
    </div>
    <div class="card-body">
        <form method="get" action="<?= url('reports') ?>" class="flex gap-sm items-end no-print"
              style="flex-wrap:wrap;gap:.75rem;margin-bottom:1rem;">
            <div class="form-group" style="margin:0;min-width:190px;">
                <label for="vs-a"><?= __('Tahun buku A (pembanding)') ?></label>
                <select name="a" id="vs-a" class="input">
                    <?php foreach ($years as $y): ?>
                        <option value="<?= (int) $y['period']['id'] ?>" <?= (int) $y['period']['id'] === $aId ? 'selected' : '' ?>>
                            <?= e($labelBulan((string) $y['period']['audit_month'])) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group" style="margin:0;min-width:190px;">
                <label for="vs-b"><?= __('Tahun buku B (utama)') ?></label>
                <select name="b" id="vs-b" class="input">
                    <?php foreach ($years as $y): ?>
                        <option value="<?= (int) $y['period']['id'] ?>" <?= (int) $y['period']['id'] === $bId ? 'selected' : '' ?>>
                            <?= e($labelBulan((string) $y['period']['audit_month'])) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button class="btn btn-primary" type="submit" style="min-height:42px;">
                <?= icon('filter') ?> <?= __('Bandingkan') ?>
            </button>
        </form>

        <?php if ($versus === null): ?>
            <p class="muted" style="margin:0;">
                <?= __('Pilih dua tahun buku yang berbeda untuk melihat perbandingan.') ?>
            </p>
        <?php else: ?>
            <?php
            $a = $versus['a'];
            $b = $versus['b'];
            ?>
            <div class="flex gap-sm items-center" style="flex-wrap:wrap;margin-bottom:.85rem;">
                <span class="badge badge-muted"><?= e($labelBulan((string) $a['period']['audit_month'])) ?></span>
                <span style="color:var(--text-faint);">→</span>
                <span class="badge badge-brand"><?= e($labelBulan((string) $b['period']['audit_month'])) ?></span>
                <span class="muted" style="font-size:.8rem;">
                    <?= __('Δ = nilai tahun B dikurangi tahun A') ?>
                </span>
            </div>

            <div class="table-wrap" style="margin-bottom:1.25rem;">
                <table class="data"><caption class="sr-only">Report versus tahun buku</caption>
                    <thead>
                        <tr>
                            <th><?= __('Metrik') ?></th>
                            <th class="t-right"><?= e($labelShort((string) $a['period']['audit_month'])) ?></th>
                            <th class="t-right"><?= e($labelShort((string) $b['period']['audit_month'])) ?></th>
                            <th class="t-right">Δ</th>
                            <th class="t-right">Δ %</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($versus['rows'] as $r): ?>
                        <?php
                        $va = (float) $r['a'];
                        $vb = (float) $r['b'];
                        $delta = $vb - $va;
                        $pct = $va != 0.0 ? ($delta / abs($va)) * 100 : null;
                        $isMoney = $r['type'] === 'money';
                        $isPct = $r['type'] === 'pct';
                        $fmt = static function (float $x) use ($isMoney, $isPct): string {
                            if ($isMoney) {
                                return 'Rp ' . number_format($x, 0, ',', '.');
                            }
                            if ($isPct) {
                                return number_format($x, 1, ',', '.') . '%';
                            }
                            return number_format($x, 0, ',', '.');
                        };
                        $dFmt = ($delta > 0 ? '+' : ($delta < 0 ? '−' : ''))
                            . ($isMoney ? 'Rp ' . number_format(abs($delta), 0, ',', '.') : number_format(abs($delta), $isPct ? 1 : 0, ',', '.') . ($isPct ? '%' : ''));
                        ?>
                        <tr>
                            <td><?= e((string) $r['label']) ?></td>
                            <td class="t-right" style="white-space:nowrap;"><?= $fmt($va) ?></td>
                            <td class="t-right" style="white-space:nowrap;"><?= $fmt($vb) ?></td>
                            <td class="t-right" style="white-space:nowrap;font-weight:700;color:<?= $tone($delta) ?>;">
                                <?= $dFmt ?>
                            </td>
                            <td class="t-right" style="white-space:nowrap;color:var(--text-3);">
                                <?= $pct === null ? '—' : (($pct > 0 ? '+' : ($pct < 0 ? '−' : '')) . number_format(abs($pct), 1, ',', '.') . '%') ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <h3 style="font-size:.95rem;margin:0 0 .6rem;"><?= __('Selisih per group') ?></h3>
            <div class="table-wrap">
                <table class="data"><caption class="sr-only">Selisih per group</caption>
                    <thead>
                        <tr>
                            <th><?= __('Group') ?></th>
                            <th class="t-right"><?= __('Nilai selisih A') ?></th>
                            <th class="t-right"><?= __('Nilai selisih B') ?></th>
                            <th class="t-right">Δ <?= __('nilai') ?></th>
                            <th class="t-right col-hide-tablet">Δ <?= __('qty') ?></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if ($versus['groups'] === []): ?>
                        <tr><td colspan="5" class="muted t-center"><?= __('Tidak ada data group.') ?></td></tr>
                    <?php endif; ?>
                    <?php foreach ($versus['groups'] as $g): ?>
                        <?php $dv = (float) $g['b_val'] - (float) $g['a_val']; $dq = (float) $g['b_qty'] - (float) $g['a_qty']; ?>
                        <tr>
                            <td><?= e((string) $g['name']) ?></td>
                            <td class="t-right" style="white-space:nowrap;color:<?= $tone($g['a_val']) ?>;"><?= $rpS($g['a_val']) ?></td>
                            <td class="t-right" style="white-space:nowrap;color:<?= $tone($g['b_val']) ?>;"><?= $rpS($g['b_val']) ?></td>
                            <td class="t-right" style="white-space:nowrap;font-weight:700;color:<?= $tone($dv) ?>;"><?= $rpS($dv) ?></td>
                            <td class="t-right col-hide-tablet" style="white-space:nowrap;"><?= $signed($dq) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</section>
<?php endif; ?>
