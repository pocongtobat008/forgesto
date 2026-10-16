<?php
/**
 * View Rekonsiliasi Hasil Hitung vs Qty Stock.
 *
 * @var array $rows
 * @var array $filters
 * @var array $groups
 * @var array $summaryGroups
 * @var array $totals
 * @var bool  $canSeeStockQty
 * @var array $directions
 * @var array $statuses
 */

use App\Core\View;

$statusBadge = static fn (string $s): string => match ($s) {
    'verified'       => 'badge-success',
    'pending_verify' => 'badge-warning',
    'rejected'       => 'badge-danger',
    default          => 'badge-neutral',
};

$statusLabel = static fn (string $s): string => match ($s) {
    'verified'       => __('Terverifikasi'),
    'pending_verify' => __('Menunggu Verifikasi'),
    'rejected'       => __('Ditolak'),
    default          => __('Draft'),
};

// Badge status diberi "dot" kecil agar status terbaca cepat & konsisten
// dengan halaman Audit/Rekonsiliasi lainnya (pola OSS modern).
$statusLabelDot = static fn (string $s): string => '<span class="status-dot" aria-hidden="true"></span>';

$dirBadge = static fn (string $d): string => match ($d) {
    'plus'  => 'badge-success',
    'minus' => 'badge-danger',
    default => 'badge-neutral',
};

$dirLabel = static fn (string $d): string => '<span class="status-dot"></span>' . match ($d) {
    'plus'  => 'Surplus (+)',
    'minus' => 'Defisit (-)',
    default => 'Sesuai (=)',
};

// URL halaman ini sendiri (tanpa host) → dipakai sebagai return_to aksi baris,
// supaya setelah Update / Kirim ke Petugas user tetap di daftar (bukan pindah
// ke halaman detail). Dibatasi ke path + query saja.
$returnTo = '/reconciliation';
if (! empty($filters)) {
    $qs = query(array_filter([
        'group_id'  => $filters['group_id']  ?? null,
        'direction' => $filters['direction'] ?? null,
        'status'    => $filters['status']    ?? null,
        'search'    => $filters['search']    ?? null,
        'highlight' => $filters['highlight'] ?? null,
        'min_diff'  => $filters['min_diff']  ?? null,
        'sort'      => $filters['sort']      ?? null,
        'page'      => $rows['page']         ?? null,
    ], static fn ($v) => $v !== null && $v !== '' && $v !== []));
    if ($qs !== '') {
        $returnTo = '/reconciliation?' . $qs;
    }
}

// Ringkas & konsisten: state filter aktif dipakai lagi oleh chip cepat & bulk.
$activeHighlight = $filters['highlight'] ?? null;
$activeMinDiff   = ($filters['min_diff'] ?? null) === '10jt';
$activeSort      = $filters['sort'] ?? 'diff_desc';
$isAdminUser     = in_array(\App\Core\Auth::role(), ['poweradmin', 'adminsto'], true);
?>
<div class="page-head">
    <div>
        <h1><?= __('Rekonsiliasi Hasil Hitung vs Qty Stock') ?></h1>
        <p>Cross-check hasil hitung fisik terhadap stok sistem dengan perhitungan nilai aset (amount) dan status per group.</p>
    </div>
    <div class="spacer"></div>
    <div class="flex gap-sm items-center">
        <?php if ($canSeeStockQty): ?>
            <?php if (can('export_data')): ?>
            <form method="post" action="<?= url('reconciliation/export-sap') ?>" class="inline-form" data-loading-label="Mengekspor...">
                <?= csrf_field() ?>
                <?php foreach (['group_id', 'direction', 'status', 'search', 'min_diff', 'stage', 'sort'] as $__k): ?>
                    <?php if (isset($_GET[$__k]) && $_GET[$__k] !== ''): ?>
                        <input type="hidden" name="<?= e($__k) ?>" value="<?= e((string) $_GET[$__k]) ?>">
                    <?php endif; ?>
                <?php endforeach; ?>
                <input type="hidden" name="cols" id="recon-export-cols" value="">
                <div class="recon-export-field">
                    <label for="recon-export-scope" class="recon-export-label"><?= __('Lingkup export') ?></label>
                <select name="scope" id="recon-export-scope" class="recon-export-scope" aria-label="<?= __('Lingkup export') ?>">
                    <option value="filter" <?= (! empty($_GET['group_id']) || ! empty($_GET['direction']) || ! empty($_GET['status']) || ! empty($_GET['search']) || ! empty($_GET['min_diff']) || ! empty($_GET['stage'])) ? 'selected' : '' ?>><?= __('Sesuai filter layar') ?></option>
                    <option value="all" <?= (empty($_GET['group_id']) && empty($_GET['direction']) && empty($_GET['status']) && empty($_GET['search']) && empty($_GET['min_diff']) && empty($_GET['stage'])) ? 'selected' : '' ?>><?= __('Semua data') ?></option>
                    <option value="group"><?= __('Per group (yang dipilih)') ?></option>
                    <option value="diff"><?= __('Selisih saja (plus/minus)') ?></option>
                </select>
                </div>
                <button type="submit" class="btn btn-primary btn-sm recon-export-btn" title="<?= __('Unduh Excel rekonsiliasi sesuai lingkup dan kolom yang tampil') ?>"><?= icon('download') ?> Export Excel</button>
            </form>
            <?php endif; ?>
            <?php if (empty($isReadOnly)): ?>
            <form method="post" class="recon-sync-form" action="<?= url('reconciliation/sync') ?>" data-confirm="<?= e(__('Sinkronkan data rekonsiliasi terbaru dari master item?')) ?>">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-outline btn-sm"><?= icon('refresh') ?> Sinkronisasi Data</button>
            </form>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<?= \App\Core\View::partial('partials/process-trail', ['trail' => $trail ?? [], 'trailGroupId' => $trailGroupId ?? null]) ?>
<!-- ====================================================================== -->
<!-- 1. KPI Metric Cards (komponen .stat — satu bahasa visual)               -->
<!-- ====================================================================== -->
<div class="kpi-grid">
    <?php $netNeg = (float) $totals['net_diff_amount'] < 0; $netPos = (float) $totals['net_diff_amount'] > 0; ?>
    <div class="stat is-brand" style="--i:0">
        <div class="stat-icon"><?= icon('chart') ?></div>
        <span class="stat-label"><?= __('Net Selisih Nilai') ?></span>
        <div class="stat-value kpi-amt <?= $netNeg ? 'is-neg' : ($netPos ? 'is-pos' : '') ?>" title="<?= e(format_rupiah((float) $totals['net_diff_amount'], true)) ?>">
            <?= format_rupiah_compact((float) $totals['net_diff_amount'], true) ?>
        </div>
        <div class="stat-meta">Selisih Qty: <strong class="mono"><?= (int) $totals['total_diff_qty'] > 0 ? '+' : '' ?><?= (int) $totals['total_diff_qty'] ?> pcs</strong></div>
    </div>

    <div class="stat is-success" style="--i:1">
        <div class="stat-icon"><?= icon('trending-up') ?></div>
        <span class="stat-label"><?= __('Surplus / Plus (+)') ?></span>
        <div class="stat-value kpi-amt" title="<?= e(format_rupiah((float) $totals['total_plus_amount'], true)) ?>"><?= format_rupiah_compact((float) $totals['total_plus_amount'], true) ?></div>
        <div class="stat-meta"><?= (int) $totals['plus_items'] ?> item dengan fisik lebih dari stok buku</div>
    </div>

    <div class="stat is-danger" style="--i:2">
        <div class="stat-icon"><?= icon('trending-down') ?></div>
        <span class="stat-label"><?= __('Defisit / Minus (-)') ?></span>
        <div class="stat-value kpi-amt" title="<?= e(format_rupiah((float) $totals['total_minus_amount'], true)) ?>"><?= format_rupiah_compact((float) $totals['total_minus_amount'], true) ?></div>
        <div class="stat-meta"><?= (int) $totals['minus_items'] ?> item dengan fisik kurang dari stok buku</div>
    </div>

    <div class="stat is-info" style="--i:3">
        <div class="stat-icon"><?= icon('check-circle') ?></div>
        <span class="stat-label"><?= __('Kesesuaian Stok') ?></span>
        <div class="stat-value"><?= (int) $totals['total_items'] > 0 ? round(((int) $totals['match_items'] / (int) $totals['total_items']) * 100, 1) : 0 ?>%</div>
        <div class="stat-meta"><?= (int) $totals['match_items'] ?> dari <?= (int) $totals['total_items'] ?> item cocok &bull; <?= (int) $totals['verified_items'] ?> verified &bull; <?= (int) $totals['pending_items'] ?> pending verifikasi</div>
    </div>
</div>

<!-- ====================================================================== -->
<!-- 1b. Analisis Materialitas (fitur baru) — ambang nilai + Pareto          -->
<!-- ====================================================================== -->
<?php
// Baseline ambang = filter cepat Rp10jt yang sudah ada, supaya konsisten.
// PENTING: view TIDAK di-namespace, jadi rujuk konstanta model lewat FQCN
// (\App\Models\Reconciliation::...) atau $bigThreshold yang dikirim controller.
// Tanpa backslash, PHP mencari "Reconciliation" di namespace global → fatal
// "Class Reconciliation not found" yang hanya muncul saat min_diff aktif.
$matDefault = $activeMinDiff ? (float) (\App\Models\Reconciliation::BIG_THRESHOLD) : 0.0;

// Daftar group untuk filter materialitas per-group (nilai = item_id, label = group).
// Hanya group yang memang punya baris di halaman ini, agar filter selalu berefek.
$matGroups = [];
foreach (($rows['items'] ?? []) as $mr) {
    $gid = (int) ($mr['group_id'] ?? 0);
    if ($gid > 0 && ! isset($matGroups[$gid])) {
        $matGroups[$gid] = (string) ($mr['group_name'] ?? 'Tanpa Group');
    }
}
asort($matGroups);
?>
<div class="card mb-lg sf-materiality" id="sf-materiality">
    <div class="flex flex-wrap gap-md items-center justify-between" style="margin-bottom:1rem;">
        <div>
            <h2 style="font-size:1.05rem;font-weight:600;margin:0;"><?= icon('spark') ?> Analisis Materialitas</h2>
            <p class="text-xs muted mt-1" style="margin-bottom:0;">Geser ambang nilai untuk melihat berapa item yang benar-benar material. Item yang menanggung porsi terbesar selisih nilai.</p>
        </div>
        <div class="flex items-center gap-sm" style="flex-wrap:wrap;">
            <span class="badge badge-brand"><?= __('Fokus nilai selisih') ?></span>
            <?php if ($isAdminUser): ?>
                <button type="button" class="btn btn-outline btn-sm" id="sf-mat-send"
                        title="Kirim item yang tampil di analisis materialitas ke petugas untuk dicek ulang">
                    <?= icon('send') ?> Kirim ke Petugas
                </button>
            <?php endif; ?>
            <button type="button" class="btn btn-outline btn-sm" id="sf-mat-print"
                    title="<?= __('Cetak / simpan sebagai PDF daftar item yang tampil') ?>">
                <?= icon('file-text') ?> Print ke PDF
            </button>
        </div>
    </div>
    <div class="card-body">
        <div class="sf-mat-scope-note" id="sf-mat-scope-note"></div>
        <div class="sf-mat-grid">
            <div>
                <?php if (count($matGroups) > 1): ?>
                <label class="sf-mat-field">
                    <span class="text-xs uppercase font-bold tracking-wider muted"><?= __('Group') ?></span>
                    <select id="sf-mat-group" class="sf-mat-select" aria-label="<?= __('Filter materialitas per group') ?>">
                        <option value=""><?= __('Semua group') ?></option>
                        <?php foreach ($matGroups as $gid => $gname): ?>
                            <option value="<?= (int) $gid ?>"><?= e($gname) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <?php endif; ?>
                <span class="text-xs uppercase font-bold tracking-wider muted"><?= __('Ambang materialitas') ?></span>
                <div class="sf-mat-readout" id="sf-mat-readout"><?= format_rupiah($matDefault, true) ?></div>
                <p class="text-xs muted" style="margin:.15rem 0 .2rem;">Geser untuk memfilter tabel di bawah. Item di atas ambang tetap tampil.</p>
                <input type="range" class="sf-mat-slider" id="sf-mat-slider" min="0" max="100000000"
                       value="<?= (int) $matDefault ?>" data-default="<?= (int) $matDefault ?>"
                       aria-label="<?= __('Ambang materialitas selisih nilai') ?>">
                <div class="sf-mat-range"><span><?= __('Rp 0') ?></span><span id="sf-mat-max" data-big-threshold="<?= (int) (\App\Models\Reconciliation::BIG_THRESHOLD) ?>">&mdash;</span></div>
                <div class="sf-mat-presets">
                    <?php foreach ([0 => 'Semua', 1000000 => '&ge; Rp 1jt', 5000000 => '&ge; Rp 5jt', 10000000 => '&ge; Rp 10jt', 50000000 => '&ge; Rp 50jt'] as $pv => $plabel): ?>
                        <button type="button" class="sf-mat-preset<?= (int) $matDefault === $pv ? ' is-active' : '' ?>" data-mat-preset="<?= $pv ?>"><?= $plabel ?></button>
                    <?php endforeach; ?>
                </div>
                <div class="sf-mat-count" id="sf-mat-count"></div>
                <div class="sf-mat-track"><span id="sf-mat-bar"></span></div>
                <p class="text-xs muted" style="margin:.5rem 0 0;">Nilai selisih di atas ambang: <strong class="mono" id="sf-mat-value"><?= __('Rp 0') ?></strong></p>
            </div>
            <div>
                <span class="text-xs uppercase font-bold tracking-wider muted"><?= __('Kontributor teratas (Pareto)') ?></span>
                <ul class="sf-pareto mt-2" id="sf-mat-pareto"><li class="sf-pareto-empty"><?= __('Memuat&hellip;') ?></li></ul>
            </div>
        </div>
        <?php if ($isAdminUser): ?>
            <!-- Form bulk: mengirim SEMUA item yang sedang tampil (lolos ambang
                 materialitas + group terpilih) ke petugas. id dikumpulkan oleh
                 sf-mat.js sebelum submit. -->
            <form method="post" action="<?= url('reconciliation/bulk-recheck') ?>"
                  id="sf-mat-send-form" data-confirm="<?= e(__('Kirim semua item yang tampil di analisis materialitas ke petugas untuk dicek ulang? Status akan kembali ke Draft.')) ?>"
                  style="display:none;">
                <?= csrf_field() ?>
                <input type="hidden" name="return_to" value="<?= e($returnTo) ?>">
                <input type="hidden" name="bulk_scope" value="ids">
                <select name="ids[]" id="sf-mat-ids" multiple hidden aria-hidden="true"></select>
            </form>
        <?php endif; ?>
    </div>
</div>

<!-- ====================================================================== -->
<!-- 1c. Alur & Kemajuan Stock Take (panduan posisi tiap item)              -->
<!-- ====================================================================== -->
<?php
// Link tahapan: pertahankan filter lain, ganti/lepas 'stage'.
$baseQuery = array_filter([
    'group_id' => $filters['group_id'] ?? null,
    'search'   => $filters['search'] ?? null,
    'status'   => $filters['status'] ?? null,
    'sort'     => $activeSort !== 'diff_desc' ? $activeSort : null,
], static fn ($v): bool => $v !== null && $v !== '');

$stageUrl = static function (string $s) use ($baseQuery): string {
    return url('reconciliation') . '?' . http_build_query(array_merge($baseQuery, ['stage' => $s]));
};

$curStage = $filters['stage'] ?? null;
$ssTotal  = max(1, (int) ($stageSummary['total'] ?? 0));

$stageTiles = [
    ['pending',     __('Belum Dihitung'),      __('Belum ada hasil hitung fisik.'),                      (int) $stageSummary['pending'],     'is-idle'],
    ['counted',     __('Sudah Dihitung'),      __('Hasil hitung masuk, belum diaudit.'),                 (int) $stageSummary['counted'],     'is-doing'],
    ['discrepancy', __('Ada Selisih'),         __('Perlu ditindaklanjuti / dikoreksi.'),                 (int) $stageSummary['discrepancy'], 'is-warn'],
    ['done',        __('Selesai'),             __('Sudah diaudit / selisih tuntas.'),                    (int) $stageSummary['done'],        'is-done'],
];
?>
<div class="card mb-lg" id="stage-card">
    <div class="flex flex-wrap gap-md items-center justify-between" style="margin-bottom:1rem;">
        <div>
            <h2 style="font-size:1.05rem;font-weight:600;margin:0;"><?= icon('activity') ?> Alur &amp; Kemajuan Stock Take</h2>
            <p class="text-xs muted mt-1" style="margin-bottom:0;">Klik salah satu tahapan untuk menyaring tabel di bawah. Alur: <strong><?= __('Belum Dihitung &rarr; Sudah Dihitung &rarr; Selesai') ?></strong>. Item dengan selisih masuk tahapan <em><?= __('Ada Selisih') ?></em> dulu untuk ditindaklanjuti.</p>
        </div>
        <div class="flex items-center gap-sm" style="flex-wrap:wrap;">
            <?php if ($curStage !== null): ?>
                <a href="<?= url('reconciliation') . ($baseQuery ? '?' . http_build_query($baseQuery) : '') ?>" class="btn btn-outline btn-xs"><?= icon('x') ?> Hapus Filter Tahapan</a>
            <?php endif; ?>
            <?php if ((int) ($stageSummary['locked'] ?? 0) > 0): ?>
                <span class="badge badge-neutral" title="<?= __('Item terkunci hanya bisa diubah Power Admin / Admin STO') ?>"><?= icon('lock') ?> <?= (int) $stageSummary['locked'] ?> item terkunci</span>
            <?php endif; ?>
        </div>
    </div>
    <div class="card-body">
        <div class="sf-stage-grid">
            <?php foreach ($stageTiles as $t): [$slug, $label, $hint, $cnt, $cls] = $t; ?>
                <a class="sf-stage-tile <?= $cls ?><?= $curStage === $slug ? ' is-active' : '' ?>"
                   href="<?= $stageUrl($slug) ?>">
                    <span class="sf-stage-num mono"><?= $cnt ?></span>
                    <span class="sf-stage-label"><?= e($label) ?></span>
                    <span class="sf-stage-hint text-xs muted"><?= e($hint) ?></span>
                    <span class="sf-stage-bar"><span style="width:<?= round($cnt / $ssTotal * 100, 1) ?>%"></span></span>
                </a>
            <?php endforeach; ?>
        </div>
        <p class="text-xs muted" style="margin:.75rem 0 0;">
            Total <strong><?= (int) $stageSummary['total'] ?></strong> item dipantau.
            Tahapan diambil dari status hitung &amp; status audit tiap item, jadi selalu ikut ter-update saat petugas menghitung atau audit.
        </p>

        <details class="sf-help">
            <summary><?= icon('chev-down', 'sf-help-chev') ?> Panduan singkat: cara baca &amp; siapa yang boleh mengubah</summary>
            <div class="sf-help-body">
                <div class="sf-help-col">
                    <strong><?= __('Alur stock take') ?></strong>
                    <ol>
                        <li><b><?= __('Hitung') ?></b>. Petugas memindai / mengetik qty fisik (menu Audit / Audit Rekonsiliasi).</li>
                        <li><b><?= __('Audit') ?></b>. Petugas audit mengecek hasil hitung vs stok buku.</li>
                        <li><b><?= __('Koreksi bila ada selisih') ?></b>. Buka detail item, pilih <em><?= __('Koreksi Angka') ?></em> (butuh alasan).</li>
                        <li><b><?= __('Verifikasi') ?></b>. Admin STO / Power Admin menyetujui; status berubah jadi <em><?= __('Terverifikasi') ?></em>.</li>
                    </ol>
                </div>
                <div class="sf-help-col">
                    <strong><?= __('Hak akses angka &amp; audit') ?></strong>
                    <ul>
                        <li><?= icon('eye') ?> <b><?= __('Observer') ?></b>. Hanya melihat, tidak bisa mengubah; kolom stok buku &amp; nilai disamarkan.</li>
                        <li><?= icon('clipboard-check') ?> <b><?= __('Petugas / Audit') ?></b>. Isi &amp; koreksi hasil hitung; perubahan masuk <em><?= __('Menunggu Verifikasi') ?></em>.</li>
                        <li><?= icon('shield-check') ?> <b><?= __('Admin STO / Power Admin') ?></b>. Lihat nilai penuh, verifikasi, dan bisa mengubah item <em><?= __('terkunci') ?></em>.</li>
                    </ul>
                    <p class="text-xs muted" style="margin:.4rem 0 0;">Tombol yang tidak bisa Anda tekan biasanya karena item <b><?= __('terkunci') ?></b> atau statusnya sudah <b><?= __('Terverifikasi') ?></b>. Hubungi Admin STO bila perlu.</p>
                </div>
            </div>
        </details>
    </div>
</div>


<!-- ====================================================================== -->
<!-- 2. Summary Dashboard per Group (compact + live search + grand total)   -->
<!-- ====================================================================== -->
<?php
// Hitung grand total dari summaryGroups untuk kartu "Total Keseluruhan"
$gt = ['stock' => 0, 'counted' => 0, 'diff_qty' => 0, 'diff_amt' => 0.0, 'plus' => 0, 'minus' => 0, 'match' => 0, 'items' => 0];
foreach ($summaryGroups as $sg) {
    $gt['stock']    += (int) ($sg['total_stock'] ?? 0);
    $gt['counted']  += (int) ($sg['total_counted'] ?? 0);
    $gt['diff_qty'] += (int) ($sg['total_diff_qty'] ?? 0);
    $gt['diff_amt'] += (float) ($sg['total_diff_amount'] ?? 0);
    $gt['plus']     += (int) ($sg['plus_count'] ?? 0);
    $gt['minus']    += (int) ($sg['minus_count'] ?? 0);
    $gt['match']    += (int) ($sg['match_count'] ?? 0);
    $gt['items']    += (int) ($sg['item_count'] ?? ($sg['total_items'] ?? 0));
}
$gtCond = $gt['diff_qty'] > 0 ? 'plus' : ($gt['diff_qty'] < 0 ? 'minus' : 'match');
?>
<div class="card mb-lg" id="summary-card">
    <div class="flex flex-wrap gap-md items-center justify-between" style="margin-bottom:1rem;">
        <div>
            <h2 style="font-size:1.05rem;font-weight:600;margin:0;"><?= icon('layers') ?> Summary Rekonsiliasi per Group</h2>
            <p class="text-xs muted mt-1" style="margin-bottom:0;">Ringkasan kondisi selisih fisik vs sistem dan total nominal per group. <strong><?= __('Selalu total keseluruhan') ?></strong>, tidak ikut filter kolom tabel.</p>
        </div>
        <div class="flex items-center gap-sm" style="flex-wrap:wrap;">
            <div class="flex items-center gap-xs" style="position:relative;min-width:210px;">
                <span style="position:absolute;left:9px;top:50%;transform:translateY(-50%);color:var(--text-muted);pointer-events:none;"><?= icon('search') ?></span>
                <input type="search" id="group-search" aria-label="<?= __("Cari group atau lokasi dalam daftar") ?>" placeholder="<?= __('Cari group / lokasi...') ?>"
                       autocomplete="off"
                       style="width:100%;padding:.4rem .7rem .4rem 2.1rem;font-size:.82rem;border-radius:var(--radius-sm);border:1px solid var(--border);">
            </div>
            <span id="group-search-count" class="text-xs muted" style="white-space:nowrap;"></span>
            <?php if (! empty($filters['group_id'])): ?>
                <a href="<?= url('reconciliation') ?>" class="btn btn-outline btn-xs"><?= icon('x') ?> Reset Filter Group</a>
            <?php endif; ?>
        </div>
    </div>
    <div class="card-body" style="padding:1rem;">
        <!-- Grand total -->
        <div class="group-total-card" data-group-card data-group-name="total keseluruhan semua group">
            <div class="group-total-left">
                <span class="text-xs uppercase font-bold tracking-wider muted"><?= __('Total Keseluruhan') ?></span>
                <strong class="mono" style="font-size:1.2rem;color:var(--text);">
                    <?= format_rupiah($gt['diff_amt'], true) ?>
                </strong>
                <span class="text-xs muted">
                    <?= (int)$gt['items'] ?> item &bull; <?= $canSeeStockQty ? 'stok ' . (int)$gt['stock'] . ' &rarr; ' : '' ?>hitung <?= (int)$gt['counted'] ?>
                    (<?= $gt['diff_qty'] > 0 ? '+' : '' ?><?= (int)$gt['diff_qty'] ?>)
                </span>
            </div>
            <div class="group-total-right">
                <span class="badge <?= $dirBadge($gtCond) ?>"><?= $dirLabel($gtCond) ?></span>
                <div class="group-total-chips">
                    <span class="chip-plus"><?= (int)$gt['plus'] ?> plus</span>
                    <span class="chip-minus"><?= (int)$gt['minus'] ?> minus</span>
                    <span class="chip-match"><?= (int)$gt['match'] ?> cocok</span>
                </div>
            </div>
        </div>

        <div class="group-grid" id="group-grid">
            <?php foreach ($summaryGroups as $sg): ?>
                <?php
                $gDiffAmt  = (float) ($sg['total_diff_amount'] ?? 0);
                $gDiffQty  = (int) ($sg['total_diff_qty'] ?? 0);
                $gPlus     = (int) ($sg['plus_count'] ?? 0);
                $gMinus    = (int) ($sg['minus_count'] ?? 0);
                $gMatch    = (int) ($sg['match_count'] ?? 0);
                $gItems    = (int) ($sg['item_count'] ?? ($sg['total_items'] ?? ($gPlus + $gMinus + $gMatch)));
                $isActive  = ($filters['group_id'] ?? null) === (int) $sg['group_id'];

                $gCondition = 'match';
                if ($gDiffQty > 0 || $gDiffAmt > 0) {
                    $gCondition = 'plus';
                } elseif ($gDiffQty < 0 || $gDiffAmt < 0) {
                    $gCondition = 'minus';
                }
                ?>
                <a href="<?= url('reconciliation?group_id=' . (int)$sg['group_id']) ?>"
                   class="group-card <?= $isActive ? 'is-active' : '' ?>"
                   data-group-card
                   data-group-name="<?= strtolower(e($sg['group_name'])) ?>">
                    <div class="group-card-top">
                        <span class="group-card-title" title="<?= e($sg['group_name']) ?>"><?= e($sg['group_name']) ?></span>
                        <span class="badge <?= $dirBadge($gCondition) ?>"><?= $dirLabel($gCondition) ?></span>
                    </div>
                    <div class="group-card-amt <?= $gDiffAmt < 0 ? 'text-danger' : ($gDiffAmt > 0 ? 'text-success' : 'muted') ?>">
                        <?= format_rupiah($gDiffAmt, true) ?>
                    </div>
                    <div class="group-card-meta">
                        <span class="group-card-itemcount"><?= $gItems ?> item</span>
                        <div class="group-card-chips">
                            <span class="chip-plus" title="<?= $gPlus ?> item surplus (+)"><span class="chip-dot"></span><?= $gPlus ?>+</span>
                            <span class="chip-minus" title="<?= $gMinus ?> item defisit (-)"><span class="chip-dot"></span><?= $gMinus ?>&minus;</span>
                            <span class="chip-match" title="<?= $gMatch ?> item sesuai (=)"><span class="chip-dot"></span><?= $gMatch ?>=</span>
                        </div>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
        <div class="group-grid-empty" id="group-grid-empty" hidden>
            <span class="muted text-xs"><?= __('Tidak ada group yang cocok dengan pencarian.') ?></span>
        </div>
    </div>
</div>

<!-- ====================================================================== -->
<!-- 3. Toolbar & Live Search Filter                                        -->
<!-- ====================================================================== -->
<div>
    <div class="flex flex-wrap gap-md items-center justify-between" style="margin-bottom:1rem;" >
        <form method="get" action="<?= url('reconciliation') ?>" class="flex flex-wrap gap-sm items-center" style="margin:0;" id="recon-filter">
            <?php foreach (($filters['col'] ?? []) as $ck => $cvals): ?><?php foreach ((array) $cvals as $cv): ?><input type="hidden" name="col[<?= e($ck) ?>][]" value="<?= e($cv) ?>" class="col-hidden"><?php endforeach; ?><?php endforeach; ?>
            <div class="form-group" style="margin:0;">
                <select name="group_id" onchange="this.form.submit()" aria-label="<?= __('Filter Group') ?>" style="font-size:.82rem;padding:.35rem .6rem;">
                    <option value=""><?= __('Semua Group') ?></option>
                    <?php foreach ($groups as $g): ?>
                        <option value="<?= (int) $g['id'] ?>" <?= ($filters['group_id'] ?? null) === (int) $g['id'] ? 'selected' : '' ?>>
                            <?= e($g['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group" style="margin:0;">
                <select name="direction" onchange="this.form.submit()" aria-label="<?= __('Filter Arah Selisih') ?>" style="font-size:.82rem;padding:.35rem .6rem;">
                    <option value=""><?= __('Semua Arah Selisih') ?></option>
                    <option value="minus" <?= ($filters['direction'] ?? null) === 'minus' ? 'selected' : '' ?>><?= __('Defisit / Minus (-)') ?></option>
                    <option value="plus" <?= ($filters['direction'] ?? null) === 'plus' ? 'selected' : '' ?>><?= __('Surplus / Plus (+)') ?></option>
                    <option value="match" <?= ($filters['direction'] ?? null) === 'match' ? 'selected' : '' ?>><?= __('Sesuai / Cocok (=)') ?></option>
                </select>
            </div>

            <div class="form-group" style="margin:0;">
                <select name="status" onchange="this.form.submit()" aria-label="<?= __('Filter Status') ?>" style="font-size:.82rem;padding:.35rem .6rem;">
                    <option value=""><?= __('Semua Status') ?></option>
                    <option value="draft" <?= ($filters['status'] ?? null) === 'draft' ? 'selected' : '' ?>><?= __('Draft') ?></option>
                    <option value="pending_verify" <?= ($filters['status'] ?? null) === 'pending_verify' ? 'selected' : '' ?>><?= __('Menunggu Verifikasi') ?></option>
                    <option value="verified" <?= ($filters['status'] ?? null) === 'verified' ? 'selected' : '' ?>><?= __('Terverifikasi') ?></option>
                    <option value="rejected" <?= ($filters['status'] ?? null) === 'rejected' ? 'selected' : '' ?>><?= __('Ditolak') ?></option>
                </select>
            </div>

            <div class="form-group" style="margin:0;">
                <select name="stage" class="sf-filter-stage" onchange="this.form.submit()" aria-label="<?= __('Filter Tahapan Stock Take') ?>" style="font-size:.82rem;padding:.35rem .6rem;">
                    <option value=""><?= __('Semua Tahapan') ?></option>
                    <option value="pending" <?= ($filters['stage'] ?? null) === 'pending' ? 'selected' : '' ?>><?= __('Belum Dihitung') ?></option>
                    <option value="counted" <?= ($filters['stage'] ?? null) === 'counted' ? 'selected' : '' ?>><?= __('Sudah Dihitung') ?></option>
                    <option value="discrepancy" <?= ($filters['stage'] ?? null) === 'discrepancy' ? 'selected' : '' ?>><?= __('Ada Selisih') ?></option>
                    <option value="done" <?= ($filters['stage'] ?? null) === 'done' ? 'selected' : '' ?>><?= __('Selesai') ?></option>
                </select>
            </div>

            <?php if (! empty($filters['group_id']) || ! empty($filters['direction']) || ! empty($filters['status']) || ! empty($filters['search']) || ! empty($activeHighlight) || $activeMinDiff || ! empty($filters['stage'])): ?>
                <a href="<?= url('reconciliation') ?>" class="btn btn-outline btn-xs" title="<?= __('Reset filter') ?>"><?= icon('x') ?> Reset</a>
            <?php endif; ?>

            <div class="form-group" style="margin:0;">
                <select name="sort" onchange="this.form.submit()" aria-label="<?= __('Urutkan hasil selisih') ?>" style="font-size:.82rem;padding:.35rem .6rem;">
                    <?php foreach ($sortLabels as $sk => $slabel): ?>
                        <option value="<?= e($sk) ?>" <?= $activeSort === $sk ? 'selected' : '' ?>><?= e($slabel) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </form>

        <!-- Live Search Box (Client-side instant filter + Enter to server search) -->
        <div class="flex items-center gap-xs" style="min-width:260px;max-width:360px;position:relative;">
            <span style="position:absolute;left:10px;top:50%;transform:translateY(-50%);color:var(--text-muted);pointer-events:none;">
                <?= icon('search') ?>
            </span>
            <input type="search" id="live-search-input" aria-label="<?= __("Pencarian langsung kode, nama, atau lokasi") ?>" value="<?= e($filters['search'] ?? '') ?>"
                   placeholder="<?= __('Pencarian langsung (kode, nama, lokasi)...') ?>"
                   style="width:100%;padding:.4rem .7rem .4rem 2.2rem;font-size:.82rem;border-radius:var(--radius-sm);border:1px solid var(--border);"
                   autocomplete="off">
            <span id="live-search-count" class="text-xs muted" style="white-space:nowrap;"></span>
        </div>
    </div>

    <!-- ================================================================== -->
    <!-- 3b. Baris filter cepat (defisit/surplus > Rp10jt) + aksi bulk       -->
    <!-- ================================================================== -->
    <div class="recon-quickbar">
        <div class="recon-quickbar-left">
            <span class="recon-quickbar-label"><?= icon('filter') ?> Filter cepat:</span>
            <a class="recon-chip <?= ($activeHighlight === 'minus' && $activeMinDiff) ? 'is-active' : '' ?>"
               href="<?= url('reconciliation?' . query(['highlight' => 'minus', 'min_diff' => '10jt', 'page' => null])) ?>">
                <span class="recon-chip-dot is-minus"></span>
                Defisit &gt; Rp10jt
                <span class="recon-chip-count js-mat-chip" data-count-key="minus"><?= (int) ($totals['minus_items_big'] ?? 0) ?></span>
            </a>
            <a class="recon-chip <?= ($activeHighlight === 'plus' && $activeMinDiff) ? 'is-active' : '' ?>"
               href="<?= url('reconciliation?' . query(['highlight' => 'plus', 'min_diff' => '10jt', 'page' => null])) ?>">
                <span class="recon-chip-dot is-plus"></span>
                Surplus &gt; Rp10jt
                <span class="recon-chip-count js-mat-chip" data-count-key="plus"><?= (int) ($totals['plus_items_big'] ?? 0) ?></span>
            </a>
            <?php if (! empty($activeHighlight) || $activeMinDiff): ?>
                <a class="recon-chip is-clear" href="<?= url('reconciliation?' . query(['highlight' => null, 'min_diff' => null, 'page' => null])) ?>">
                    <?= icon('x') ?> Bersihkan
                </a>
            <?php endif; ?>
            <?php
                $__rcActive = ! empty($activeHighlight) || $activeMinDiff
                    || ! empty($filters['col']) || ! empty($filters['rng']) || ! empty($filters['txt'])
                    || ! empty($filters['group_id']) || ! empty($filters['status']) || ! empty($filters['direction'])
                    || ! empty($filters['stage']) || ! empty($filters['search']);
                // Hapus semua filter; pertahankan sort & per_page agar pilihan tampilan tidak ikut hilang.
                $__rcKeep = array_intersect_key($_GET, array_flip(['sort', 'per_page']));
                $__rcClearQs = http_build_query($__rcKeep);
            ?>
            <?php if ($__rcActive): ?>
                <a class="btn btn-ghost btn-icon-only recon-clear-all" href="<?= url('reconciliation') ?><?= $__rcClearQs !== '' ? '?' . $__rcClearQs : '' ?>" aria-label="<?= __('Hapus semua filter') ?>" title="<?= __('Hapus semua filter (sort & jumlah baris tetap)') ?>"><?= icon('x') ?></a>
            <?php endif; ?>
        </div>

        <?php if ($isAdminUser): ?>
            <form method="post" action="<?= url('reconciliation/bulk-recheck') ?>" class="recon-bulk" id="recon-bulk-form">
                <?= csrf_field() ?>
                <input type="hidden" name="return_to" value="<?= e($returnTo) ?>">
                <input type="hidden" name="bulk_scope" value="filter" id="recon-bulk-scope">
                <?php foreach (['group_id', 'direction', 'status', 'search', 'highlight', 'min_diff', 'stage'] as $bk): ?>
                    <?php if (! empty($filters[$bk])): ?>
                        <input type="hidden" name="<?= $bk ?>" value="<?= e((string) $filters[$bk]) ?>">
                    <?php endif; ?>
                <?php endforeach; ?>
                <?php $bigFilterActive = (! empty($activeHighlight) || $activeMinDiff); ?>
                <button type="submit" class="btn btn-outline btn-sm" id="recon-bulk-btn"
                        data-filter-active="<?= $bigFilterActive ? '1' : '0' ?>"
                        data-confirm="<?= e(__('Kirim item ke petugas untuk dicek ulang? Status akan kembali ke Draft.')) ?>">
                    <?= icon('refresh') ?> <span id="recon-bulk-label"><?= $bigFilterActive ? 'Kirim semua hasil filter ke Petugas' : 'Kirim 0 terpilih ke Petugas' ?></span>
                </button>
            </form>
        <?php endif; ?>
    </div>

    <!-- ================================================================== -->
    <!-- 4. Data Table Rekonsiliasi                                         -->
    <!-- ================================================================== -->
    <?php
    $__reconHasFilter = ! empty($filters['group_id']) || ! empty($filters['direction']) || ! empty($filters['status']) || ! empty($filters['search']) || ! empty($filters['col']) || ! empty($filters['adv']) || ! empty($activeHighlight) || ! empty($activeMinDiff) || ! empty($filters['stage']);
    ?>
    <div class="table-toolbar" style="display:flex;gap:.55rem;margin-bottom:.75rem;margin-left:auto;justify-content:flex-end;align-items:center;">
        <button type="button" class="btn btn-info btn-icon-only" id="recon-adv-btn" aria-label="<?= __('Buka pencarian lanjutan') ?>" title="<?= __('Pencarian lanjutan') ?>"><?= icon('filter') ?></button>
        <?php if ($__reconHasFilter): ?>
            <a class="btn btn-ghost btn-icon-only col-clear-all" href="<?= url('reconciliation') ?>" aria-label="<?= __('Hapus semua filter') ?>" title="<?= __('Hapus semua filter') ?>"><?= icon('x') ?></a>
        <?php endif; ?>
        <div class="col-mgr" id="col-mgr">
            <button type="button" class="btn btn-ghost btn-icon-only" id="col-mgr-btn" aria-expanded="false" aria-label="<?= __('Sesuaikan kolom') ?>" title="<?= __('Sesuaikan kolom') ?>"><?= icon('layers') ?></button>
            <div class="col-mgr-panel" id="col-mgr-panel" hidden></div>
        </div>
    </div>
    <div class="table-wrap recon-table-wrap">
        <table class="data" id="recon-table" data-colmgr-key="recon-v1" data-colmgr-prefix="recon">
            <?php $cfCol = $filters['col'] ?? []; $xfBtn = static fn (string $k, string $lbl): string => '<button type="button" class="xf-btn' . (! empty($cfCol[$k]) ? ' is-active' : '') . '" data-xf="' . $k . '" aria-label="' . e(__('Filter kolom :l', ['l' => __($lbl)])) . '" aria-haspopup aria-haspopup="dialog" title="Filter">' . icon('filter') . '</button>'; ?>
<!-- RNG_V1 -->
<thead>
                <tr>
                    <?php if (empty($isReadOnly) && $isAdminUser): ?>
                        <th class="t-center recon-check-col" style="width:34px;">
                            <input type="checkbox" id="recon-check-all" aria-label="<?= __('Pilih semua baris') ?>" style="width:16px;height:16px;">
                        </th>
                    <?php endif; ?>
                    <th data-col="kode" class="xf-th"><span class="xf-label"><?= __('Kode') ?></span><?= $xfBtn('kode', 'Kode') ?></th>
                    <th data-col="nama" class="xf-th"><span class="xf-label"><?= __('Nama Item') ?></span><?= $xfBtn('nama', 'Nama Item') ?></th>
                                        <th data-col="grp" class="xf-th"><span class="xf-label"><?= __('Group') ?></span><?= $xfBtn('group', 'Group') ?></th>
                    <th data-col="loc" class="xf-th"><span class="xf-label"><?= __('Lokasi') ?></span><?= $xfBtn('location', 'Lokasi') ?></th>
                    <th data-col="kat" class="xf-th"><span class="xf-label"><?= __('Kategori') ?></span><?= $xfBtn('kategori', 'Kategori') ?></th>
                    <th data-col="sat" class="xf-th"><span class="xf-label"><?= __('Satuan') ?></span><?= $xfBtn('satuan', 'Satuan') ?></th>
                    <th data-col="obs" class="xf-th"><span class="xf-label"><?= __('Observer') ?></span><?= $xfBtn('observer', 'Observer') ?></th>
                    <th data-col="aud" class="xf-th"><span class="xf-label"><?= __('Auditor') ?></span><?= $xfBtn('auditor', 'Auditor') ?></th>
                    <th data-col="diajukan" class="xf-th"><span class="xf-label"><?= __('Diajukan Oleh') ?></span><?= $xfBtn('diajukan', 'Diajukan Oleh') ?></th>
                    <th data-col="tgl_ajuan" class="xf-th"><span class="xf-label"><?= __('Tgl Diajukan') ?></span><?= '<button type="button" class="xf-btn rng-btn' . ((!empty($filters["rng"]["tgl_ajuan"])) ? ' is-active' : '') . '" data-rng="tgl_ajuan" data-kind="rng" aria-label="Filter Tgl Diajukan" title="Filter">' . icon('filter') . '</button>' ?></th>
                    <th data-col="verif" class="xf-th"><span class="xf-label"><?= __('Diverifikasi Oleh') ?></span><?= $xfBtn('verif', 'Diverifikasi Oleh') ?></th>
                    <th data-col="tgl_verif" class="xf-th"><span class="xf-label"><?= __('Tgl Verifikasi') ?></span><?= '<button type="button" class="xf-btn rng-btn' . ((!empty($filters["rng"]["tgl_verif"])) ? ' is-active' : '') . '" data-rng="tgl_verif" data-kind="rng" aria-label="Filter Tgl Verifikasi" title="Filter">' . icon('filter') . '</button>' ?></th>
                    <th data-col="catatan" class="xf-th"><span class="xf-label"><?= __('Catatan') ?></span><?= '<button type="button" class="xf-btn rng-btn' . ((!empty($filters["txt"]["catatan"])) ? ' is-active' : '') . '" data-rng="catatan" data-kind="txt" aria-label="Filter Catatan" title="Filter">' . icon('filter') . '</button>' ?></th>
                    <th class="t-right xf-th" data-col="harga"><span class="xf-label"><?= __('Harga Satuan') ?></span><?= $xfBtn('harga', 'Harga Satuan') ?></th>
                    <?php if ($canSeeStockQty): ?>
                        <th class="t-center mono xf-th" data-col="stok"><span class="xf-label"><?= __('Stok Buku') ?></span><?= $xfBtn('stok', 'Stok Buku') ?></th>
                    <?php endif; ?>
                    <th class="t-center mono xf-th" data-col="hitung"><span class="xf-label"><?= __('Hasil Hitung') ?></span><?php if ($canSeeStockQty): ?><?= $xfBtn('hitung', 'Hasil Hitung') ?><?php endif; ?></th>
                    <th class="t-center mono xf-th" data-col="selqty"><span class="xf-label"><?= __('Selisih Qty') ?></span><?= '<button type="button" class="xf-btn rng-btn' . ((!empty($filters["rng"]["selqty"])) ? ' is-active' : '') . '" data-rng="selqty" data-kind="rng" aria-label="Filter Selisih Qty" title="Filter">' . icon('filter') . '</button>' ?></th>
                    <th class="t-right mono xf-th" data-col="selnil"><span class="xf-label"><?= __('Selisih Nilai (Amount)') ?></span><?= '<button type="button" class="xf-btn rng-btn' . ((!empty($filters["rng"]["selnil"])) ? ' is-active' : '') . '" data-rng="selnil" data-kind="rng" aria-label="Filter Selisih Nilai" title="Filter">' . icon('filter') . '</button>' ?></th>
                    <th class="t-center xf-th" data-col="status"><span class="xf-label"><?= __('Status') ?></span><?= $xfBtn('status', 'Status') ?></th>
                    <th class="t-right" data-col="aksi"><?= __('Aksi') ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if ($rows['items'] === []): ?>
                    <tr>
                        <td colspan="<?= 19 + ($canSeeStockQty ? 1 : 0) + ($isAdminUser ? 1 : 0) ?>">
                            <div class="empty-state">
                                <div class="empty-icon"><?= icon('scale') ?></div>
                                <h3><?= __('Tidak ada data rekonsiliasi') ?></h3>
                                <p>Silakan sesuaikan filter atau lakukan sinkronisasi data dari master item.</p>
                            </div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($rows['items'] as $r): ?>
                        <?php
                        $diffAmt   = (float) $r['diff_amount'];
                        $diffQty   = (int) $r['difference'];
                        $direction = (string) $r['direction'];
                        $stg       = $stageMap[(int) $r['item_id']] ?? null;
                        ?>
                        <tr class="recon-row"
                            data-code="<?= strtolower(e($r['item_code'])) ?>"
                            data-name="<?= strtolower(e($r['item_name'])) ?>"
                            data-group="<?= strtolower(e($r['group_name'])) ?>"
                            data-loc="<?= strtolower(e($r['location_name'] ?? '')) ?>"
                            data-price="<?= (float) $r['unit_price'] ?>"
                            data-diff="<?= (float) $diffAmt ?>"
                            data-stage="<?= e($stg['stage'] ?? '') ?>"
                            data-gid="<?= (int) $r['group_id'] ?>"
                            data-dir="<?= e($direction) ?>">
                            <?php if ($isAdminUser): ?>
                                <td class="t-center recon-check-col" data-label="">
                                    <input type="checkbox" class="recon-row-check" name="ids[]"
                                           value="<?= (int) $r['id'] ?>" data-label=""
                                           data-id="<?= (int) $r['id'] ?>"
                                           form="recon-bulk-form"
                                           aria-label="Pilih <?= e($r['item_code']) ?>">
                                </td>
                            <?php endif; ?>
                            <td data-label="<?= __('Kode') ?>" data-col="kode">
                                <span class="code-badge mono"><span class="code-badge-prefix"><?= e(explode('-', (string) $r['item_code'])[0]) ?></span><?= e(substr((string) $r['item_code'], strlen(explode('-', (string) $r['item_code'])[0]))) ?></span>
                            </td>
                            <td data-label="<?= __('Nama Item') ?>" class="col-name" data-col="nama" title="<?= e($r['item_name']) ?>">
                                <div class="cell-stack">
                                    <div class="cell-title font-semibold"><?= e($r['item_name']) ?></div>
                                    <div class="cell-sub muted text-xs"><?= e($r['category'] ?? 'Umum') ?> &bull; <?= e($r['unit'] ?? 'pcs') ?></div>
                                </div>
                            </td>
                            <td data-label="<?= __('Group') ?>" data-col="grp">
                                <span class="ell cell-title" title="<?= e($r['group_name']) ?>"><?= e($r['group_name']) ?></span>
                                <span class="ell cell-sub muted text-xs" title="<?= e($r['group_code']) ?>"><?= e($r['group_code']) ?></span>
                            </td>
                            <td data-label="<?= __('Lokasi') ?>" data-col="loc"><span class="ell" title="<?= e($r['location_name'] ?: '-') ?>"><?= e($r['location_name'] ?: '—') ?></span></td>
                            <td data-label="<?= __('Kategori') ?>" data-col="kat"><span class="ell" title="<?= e($r['category'] ?? 'Umum') ?>"><?= e($r['category'] ?? 'Umum') ?></span></td>
                            <td data-label="<?= __('Satuan') ?>" data-col="sat"><span class="ell"><?= e($r['unit'] ?? 'pcs') ?></span></td>
                            <td data-label="<?= __('Observer') ?>" data-col="obs"><span class="ell" title="<?= e(str_replace(' | ', ', ', (string) ($r['group_observers'] ?? ''))) ?>"><?= e(str_replace(' | ', ', ', (string) ($r['group_observers'] ?? '')) ?: '—') ?></span></td>
                            <td data-label="<?= __('Auditor') ?>" data-col="aud"><span class="ell" title="<?= e(str_replace(' | ', ', ', (string) ($r['group_auditors'] ?? ''))) ?>"><?= e(str_replace(' | ', ', ', (string) ($r['group_auditors'] ?? '')) ?: '—') ?></span></td>
                            <td data-label="<?= __('Diajukan Oleh') ?>" data-col="diajukan"><span class="ell"><?= e($r['submitted_by_name'] ?: '—') ?></span></td>
                            <td data-label="<?= __('Tgl Diajukan') ?>" data-col="tgl_ajuan" class="mono text-xs"><?= (!empty($r['submitted_at']) ? date('d/m/Y H:i', strtotime($r['submitted_at'])) : '—') ?></td>
                            <td data-label="<?= __('Diverifikasi Oleh') ?>" data-col="verif"><span class="ell"><?= e($r['verified_by_name'] ?: '—') ?></span></td>
                            <td data-label="<?= __('Tgl Verifikasi') ?>" data-col="tgl_verif" class="mono text-xs"><?= (!empty($r['verified_at']) ? date('d/m/Y H:i', strtotime($r['verified_at'])) : '—') ?></td>
                            <td data-label="<?= __('Catatan') ?>" data-col="catatan"><span class="ell" title="<?= e($r['verify_note'] ?: '') ?>"><?= e($r['verify_note'] ?: '—') ?></span></td>
                                                        <td data-label="<?= __('Harga Satuan') ?>" data-col="harga" class="t-right mono tabular">
                                <?= format_rupiah((float) $r['unit_price']) ?>
                            </td>
                            <?php if ($canSeeStockQty): ?>
                                <td data-label="<?= __('Stok Buku') ?>" data-col="stok" class="t-center mono tabular font-bold">
                                    <?= (int) $r['stock_qty'] ?>
                                </td>
                            <?php endif; ?>
                            <td data-label="<?= __('Hasil Hitung') ?>" data-col="hitung" class="t-center mono tabular font-bold text-brand-cell">
                                <?= (int) $r['counted_qty'] ?>
                            </td>
                            <td data-label="<?= __('Selisih Qty') ?>" data-col="selqty" class="t-center mono tabular">
                                <?php if ($canSeeStockQty): ?>
                                    <span class="badge <?= $dirBadge($direction) ?>">
                                        <?= $diffQty > 0 ? '+' : '' ?><?= $diffQty ?>
                                    </span>
                                <?php else: ?>
                                    <span class="badge <?= $dirBadge($direction) ?>">
                                        <?= $direction === 'match' ? 'Cocok' : ($direction === 'plus' ? __('Lebih') : __('Kurang')) ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td data-label="<?= __('Selisih Nilai') ?>" data-col="selnil" class="t-right mono tabular font-bold <?= $diffAmt < 0 ? 'text-danger' : ($diffAmt > 0 ? 'text-success' : 'muted') ?>">
                                <?= format_rupiah($diffAmt, true) ?>
                            </td>
                            <td data-label="<?= __('Status') ?>" data-col="status" class="t-center">
                                <div class="cell-stack cell-stack-center">
                                    <span class="badge <?= $statusBadge((string) $r['status']) ?>">
                                        <?= $statusLabelDot((string) $r['status']) ?><?= e($statusLabel((string) $r['status'])) ?>
                                    </span>
                                    <?php if ($stg !== null): ?>
                                        <div class="cell-sub text-xs muted mt-1"><?= e($stg['stage_label']) ?></div>
                                        <?php if (($stg['is_locked'] ?? 0) === 1): ?>
                                            <div class="text-xs mt-1" style="color:var(--text-muted);" title="<?= __('Item terkunci: hanya Power Admin / Admin STO yang bisa mengubah') ?>">
                                                <?= icon('lock') ?> Terkunci
                                            </div>
                                        <?php elseif (! empty($stg['counted_by_name'])): ?>
                                            <div class="cell-sub text-xs muted">Oleh: <?= e($stg['counted_by_name']) ?></div>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                    <?php if (! empty($r['recheck_requested'])): ?>
                                        <span class="badge badge-recheck" title="<?= __('Sudah diminta cek ulang ke petugas') ?>"><?= icon('refresh') ?> Cek ulang</span>
                                    <?php endif; ?>
                                    <?php if (! empty($r['verify_note'])): ?>
                                        <span class="status-note" title="<?= e($r['verify_note']) ?>"><?= icon('info') ?> <?= e(mb_strimwidth((string) $r['verify_note'], 0, 34, '…')) ?></span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td data-label="<?= __('Aksi') ?>" data-col="aksi" class="t-right cell-actions">
                                <div class="row-actions">
                                    <a href="<?= url('reconciliation/' . (int) $r['id']) ?>"
                                       class="btn-icon btn-icon-detail"
                                       title="<?= __('Lihat detail &amp; cross-check') ?>"
                                       aria-label="Detail <?= e($r['item_code']) ?>">
                                        <?= icon('eye') ?>
                                    </a>
                                    <?php if (empty($isReadOnly)): ?>
                                    <button type="button"
                                            class="btn-icon btn-icon-edit js-row-correct"
                                            title="<?= __('Koreksi angka item ini') ?>"
                                            aria-label="Koreksi <?= e($r['item_code']) ?>"
                                            data-id="<?= (int) $r['id'] ?>"
                                            data-code="<?= e($r['item_code']) ?>"
                                            data-name="<?= e($r['item_name']) ?>"
                                            data-unit="<?= e($r['unit'] ?? 'unit') ?>"
                                            data-qty="<?= (int) $r['counted_qty'] ?>"
                                            data-price="<?= (float) $r['unit_price'] ?>">
                                        <?= icon('edit') ?>
                                    </button>
                                    <form method="post"
                                          action="<?= url('reconciliation/' . (int) $r['id'] . '/recheck') ?>"
                                          class="js-row-recheck"
                                          data-confirm="<?= e(__('Kirim :code ke petugas untuk dicek ulang? Status akan kembali ke Draft.', ['code' => $r['item_code']])) ?>"
                                          style="margin:0;display:inline;">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="return_to" value="<?= e($returnTo) ?>">
                                        <button type="submit" class="btn-icon btn-icon-recheck"
                                                title="<?= __('Kirim ke petugas untuk dicek ulang') ?>"
                                                aria-label="Minta cek ulang <?= e($r['item_code']) ?>">
                                            <?= icon('refresh') ?>
                                        </button>
                                    </form>
                                    <?php endif; ?>
                                </div>


                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination (custom per halaman + nomor halaman) -->
    <?php
        $currentPage = (int) ($rows['page'] ?? 1);
        $lastPage    = max(1, (int) ($rows['last_page'] ?? 1));
        $totalRows   = (int) ($rows['total'] ?? 0);
        $pp          = (int) ($perPage ?? 50);
        $ppOpts      = $perPageOptions ?? [20, 50, 100, 200];
        $isCustomPp  = ! in_array($pp, $ppOpts, true);
        $pageQs      = function (int $p) use ($pp): string {
            return url('reconciliation?' . query(array_filter([
                'group_id' => $_GET['group_id'] ?? null, 'direction' => $_GET['direction'] ?? null,
                'status' => $_GET['status'] ?? null, 'search' => $_GET['search'] ?? null,
                'highlight' => $_GET['highlight'] ?? null, 'min_diff' => $_GET['min_diff'] ?? null,
                'stage' => $_GET['stage'] ?? null, 'sort' => $_GET['sort'] ?? null,
                'per_page' => $pp, 'page' => $p,
            ], static fn ($v) => $v !== null && $v !== '')));
        };
        // Rentang nomor: halaman 1, terakhir, dan sekitar halaman aktif (elipsis di antaranya).
        $pageNums = [];
        for ($i = 1; $i <= $lastPage; $i++) {
            if ($i === 1 || $i === $lastPage || abs($i - $currentPage) <= 1) { $pageNums[] = $i; }
        }
    ?>
    <div class="card-footer recon-pager" id="recon-pager">
        <form method="get" action="<?= url('reconciliation') ?>" class="recon-pager-size" id="recon-pp-form">
            <?php foreach (($filters['col'] ?? []) as $ck => $cvals): ?><?php foreach ((array) $cvals as $cv): ?><input type="hidden" name="col[<?= e($ck) ?>][]" value="<?= e($cv) ?>"><?php endforeach; ?><?php endforeach; ?>
            <?php foreach (['group_id', 'direction', 'status', 'search', 'highlight', 'min_diff', 'stage', 'sort'] as $__k): ?>
                <?php if (isset($_GET[$__k]) && $_GET[$__k] !== ''): ?>
                    <input type="hidden" name="<?= $__k ?>" value="<?= e((string) $_GET[$__k]) ?>">
                <?php endif; ?>
            <?php endforeach; ?>
            <label for="recon-pp-select" class="text-xs muted"><?= __('Baris / halaman') ?></label>
            <select id="recon-pp-select" class="recon-pp-select" aria-label="<?= __('Jumlah baris per halaman') ?>">
                <?php foreach ($ppOpts as $opt): ?>
                    <option value="<?= (int) $opt ?>" <?= (! $isCustomPp && $pp === (int) $opt) ? 'selected' : '' ?>><?= (int) $opt ?></option>
                <?php endforeach; ?>
                <option value="custom" <?= $isCustomPp ? 'selected' : '' ?>><?= __('Custom') ?></option>
            </select>
            <input type="number" id="recon-pp-custom" name="per_page" class="recon-pp-custom" min="10" max="500" step="1"
                   value="<?= $isCustomPp ? $pp : '' ?>" placeholder="10-500" aria-label="<?= __('Jumlah baris custom (10 sampai 500)') ?>"
                   <?= $isCustomPp ? '' : 'hidden' ?>>
            <button type="submit" class="btn btn-outline btn-xs recon-pp-apply" id="recon-pp-apply" <?= $isCustomPp ? '' : 'hidden' ?>>Terapkan</button>
            <span class="text-xs muted recon-pager-total">Total <?= $totalRows ?> item</span>
        </form>
        <?php $__allQs = []; foreach ($_GET as $__k => $__v) { if ($__k === 'page' || $__k === 'all' || $__k === 'per_page' || $__v === '' || $__v === null) continue; $__allQs[$__k] = $__v; } $__allShow = (($_GET['all'] ?? '') === '1'); $__allHref = url('reconciliation') . '?' . http_build_query($__allShow ? array_merge($__allQs, ['per_page' => $pp ?? 50]) : array_merge($__allQs, ['all' => '1'])); ?>
        <a class="pg-switch<?= $__allShow ? ' is-on' : '' ?>" href="<?= e($__allHref) ?>" role="switch" aria-checked="<?= $__allShow ? 'true' : 'false' ?>">
            <span class="pg-switch-track" aria-hidden="true"><span class="pg-switch-thumb"></span></span>
            <span><?= __('Tampilkan semua') ?></span>
        </a>

        <nav class="recon-pager-nav" aria-label="<?= __('Navigasi halaman') ?>">
            <?php if ($currentPage > 1): ?>
                <a class="recon-pg-btn" href="<?= $pageQs($currentPage - 1) ?>" aria-label="<?= __('Halaman sebelumnya') ?>">&larr;</a>
            <?php else: ?>
                <span class="recon-pg-btn is-disabled" aria-hidden="true">&larr;</span>
            <?php endif; ?>

            <?php $prevN = 0; foreach ($pageNums as $n): ?>
                <?php if ($prevN && $n - $prevN > 1): ?><span class="recon-pg-gap" aria-hidden="true">&hellip;</span><?php endif; ?>
                <?php if ($n === $currentPage): ?>
                    <span class="recon-pg-btn is-current" aria-current="page"><?= $n ?></span>
                <?php else: ?>
                    <a class="recon-pg-btn" href="<?= $pageQs($n) ?>" aria-label="Halaman <?= $n ?>"><?= $n ?></a>
                <?php endif; ?>
                <?php $prevN = $n; endforeach; ?>

            <?php if ($currentPage < $lastPage): ?>
                <a class="recon-pg-btn" href="<?= $pageQs($currentPage + 1) ?>" aria-label="<?= __('Halaman berikutnya') ?>">&rarr;</a>
            <?php else: ?>
                <span class="recon-pg-btn is-disabled" aria-hidden="true">&rarr;</span>
            <?php endif; ?>
        </nav>

        <div class="text-xs muted recon-pager-info" aria-live="polite">
            Halaman <strong><?= $currentPage ?></strong> dari <?= $lastPage ?>
            <?php if ($totalRows > 0): ?>&middot; baris <?= (int) ($rows['from'] ?? 0) ?>&ndash;<?= (int) ($rows['to'] ?? 0) ?><?php endif; ?>
        </div>
    </div>
</div>

<!-- ====================================================================== -->
<!-- 5. Instant Live Search Script (tabel + summary group)                  -->
<!-- ====================================================================== -->
<script>
(function() {
    var input = document.getElementById('live-search-input');
    var table = document.getElementById('recon-table');
    var countDisplay = document.getElementById('live-search-count');
    if (!input || !table) return;

    var rows = table.querySelectorAll('tbody tr.recon-row');
    var totalCount = rows.length;

    function filterTable() {
        var query = input.value.trim().toLowerCase();
        var visibleCount = 0;

        for (var i = 0; i < rows.length; i++) {
            var row = rows[i];
            var code = row.getAttribute('data-code') || '';
            var name = row.getAttribute('data-name') || '';
            var group = row.getAttribute('data-group') || '';
            var loc = row.getAttribute('data-loc') || '';

            var matches = !query ||
                code.indexOf(query) !== -1 ||
                name.indexOf(query) !== -1 ||
                group.indexOf(query) !== -1 ||
                loc.indexOf(query) !== -1;

            // Pakai arbiter bersama supaya tidak menimpa filter materialitas /
            // instant-filter (baris tampil hanya bila semua 'reason' setuju).
            if (window.sfRowVisible) { window.sfRowVisible(row, 'search', matches); }
            else { row.style.display = matches ? '' : 'none'; }
            if (matches) visibleCount++;
        }

        if (countDisplay) {
            if (query) {
                countDisplay.textContent = visibleCount + '/' + totalCount + ' cocok';
            } else {
                countDisplay.textContent = '';
            }
        }
    }

    input.addEventListener('input', filterTable);

    if (input.value.trim()) {
        filterTable();
    }
})();

// Live search khusus kartu summary group
(function() {
    var gInput = document.getElementById('group-search');
    var grid   = document.getElementById('group-grid');
    var empty  = document.getElementById('group-grid-empty');
    var count  = document.getElementById('group-search-count');
    if (!gInput || !grid) return;

    var cards = grid.querySelectorAll('[data-group-card]');
    var total = cards.length;

    function filterGroups() {
        var q = gInput.value.trim().toLowerCase();
        var shown = 0;
        for (var i = 0; i < cards.length; i++) {
            var name = cards[i].getAttribute('data-group-name') || '';
            var ok = !q || name.indexOf(q) !== -1;
            cards[i].style.display = ok ? '' : 'none';
            if (ok) shown++;
        }
        if (count) count.textContent = q ? (shown + '/' + total + ' group') : '';
        if (empty) empty.hidden = !(q && shown === 0);
        if (window.sfVisNotify) window.sfVisNotify();
    }

    gInput.addEventListener('input', filterGroups);
    if (gInput.value.trim()) filterGroups();
})();

/* ====================================================================== */
/* Row actions — modal koreksi + kirim ke petugas, langsung dari tabel    */
/* Tanpa pindah halaman: form diposting, controller balik ke return_to.   */
/* CATATAN: markup modal ada SETELAH blok <script> ini, jadi init harus   */
/* ditunda sampai DOM siap (kalau tidak, getElementById → null).          */
/* ====================================================================== */
function initRowActions() {
    var modal      = document.getElementById('row-correct-modal');
    var form       = document.getElementById('row-correct-form');
    var titleEl    = document.getElementById('row-correct-title');
    var ctxEl      = document.getElementById('row-correct-context');
    var inputQty   = document.getElementById('row-input-qty');
    var inputPrice = document.getElementById('row-input-price');
    var inputReason= document.getElementById('row-input-reason');
    var deltaValue = document.getElementById('row-delta-value');
    var checkBox   = document.getElementById('row-check-confirm');
    var submitBtn  = document.getElementById('row-btn-submit');
    if (!modal || !form) return;
    if (modal.dataset.sfInit) return;      // idempotent: sf:ready may re-run it
    modal.dataset.sfInit = '1';

    var curQty = 0, curPrice = 0;

    function openModal(btn) {
        curQty   = parseInt(btn.getAttribute('data-qty'), 10) || 0;
        curPrice = parseFloat(btn.getAttribute('data-price')) || 0;

        form.setAttribute('action', <?= json_encode(url('reconciliation/')) ?> + btn.getAttribute('data-id'));

        if (titleEl) titleEl.textContent = 'Koreksi Angka · ' + btn.getAttribute('data-code');
        if (ctxEl) {
            ctxEl.innerHTML = '<strong>' + btn.getAttribute('data-name') + '</strong>'
                + ' &bull; angka saat ini: <strong class="mono">' + curQty + '</strong> '
                + btn.getAttribute('data-unit')
                + ' &bull; harga: <strong class="mono">' + rupiah(curPrice) + '</strong>';
        }
        if (inputQty)   inputQty.value = curQty;
        if (inputPrice) inputPrice.value = curPrice;
        if (inputReason) inputReason.value = '';
        if (checkBox)   checkBox.checked = false;

        modal.classList.add('is-open');
        document.body.style.overflow = 'hidden';
        setTimeout(function() { if (inputQty) { inputQty.focus(); inputQty.select(); } }, 60);
        refresh();
    }

    function closeModal() {
        modal.classList.remove('is-open');
        document.body.style.overflow = '';
    }

    document.querySelectorAll('.js-row-correct').forEach(function(btn) {
        btn.addEventListener('click', function() { openModal(btn); });
    });

    modal.addEventListener('click', function(e) {
        if (e.target.closest('[data-modal-panel]')) {
            // di dalam panel: hanya tombol tutup eksplisit yang menutup
            if (e.target.closest('button[data-row-modal-close], button[data-modal-close], .modal-x')) {
                closeModal();
            }
            return;
        }
        if (e.target === modal || e.target.closest('[data-row-modal-close]')) { closeModal(); }
    });
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && modal.classList.contains('is-open')) closeModal();
    });

    function rupiah(n) { return 'Rp ' + Number(n || 0).toLocaleString('id-ID'); }

    function refresh() {
        if (!inputQty) return;
        var newQty = parseInt(inputQty.value, 10);
        if (isNaN(newQty)) newQty = 0;
        var delta = newQty - curQty;
        var deltaStr = delta > 0 ? '(+' + delta + ')' : (delta < 0 ? '(' + delta + ')' : '(sama)');
        if (deltaValue) {
            deltaValue.textContent = curQty + ' \u2192 ' + newQty + ' ' + deltaStr +
                '  \u00b7  Rp ' + Number(newQty * (parseFloat(inputPrice && inputPrice.value) || 0)).toLocaleString('id-ID');
        }
        var qtyOk    = !isNaN(newQty) && newQty >= 0;
        var reasonOk = inputReason && inputReason.value.trim().length >= 3;
        var ticked   = checkBox && checkBox.checked;
        var priceOk  = inputPrice ? (parseFloat(inputPrice.value) >= 0) : true;
        if (submitBtn) submitBtn.disabled = !(qtyOk && ticked && reasonOk && priceOk);
        if (checkBox) checkBox.closest('.recon-check').classList.toggle('is-on', checkBox.checked);
    }

    if (inputQty)    inputQty.addEventListener('input', refresh);
    if (inputPrice)  inputPrice.addEventListener('input', refresh);
    if (inputReason) inputReason.addEventListener('input', refresh);
    if (checkBox)    checkBox.addEventListener('change', refresh);

    form.addEventListener('submit', function(e) {
        var newQty = parseInt(inputQty ? inputQty.value : '', 10);
        if (isNaN(newQty) || newQty < 0) { e.preventDefault(); if (inputQty) inputQty.focus(); return; }
        if (!inputReason || inputReason.value.trim().length < 3) { e.preventDefault(); if (inputReason) inputReason.focus(); return; }
        if (!checkBox || !checkBox.checked) { e.preventDefault(); return; }
        if (submitBtn) { submitBtn.disabled = true; submitBtn.textContent = 'Menyimpan...'; }
    });
}

// Jalankan sekarang bila DOM sudah siap, kalau belum tunggu event DOMContentLoaded.
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initRowActions);
} else {
    initRowActions();
}
// sf-ui wraps <select>s into custom dropdowns after DOMContentLoaded; re-run
// so any control rebuilt by that pass is still wired (idempotent).
document.addEventListener('sf:ready', initRowActions);

/* ====================================================================== */
/* Checkbox bulk kirim ke petugas — pilih baris, tombol menampilkan jumlah */
/* ====================================================================== */
(function () {
    var checkAll = document.getElementById('recon-check-all');
    var bulkForm = document.getElementById('recon-bulk-form');
    var bulkBtn  = document.getElementById('recon-bulk-btn');
    var bulkLabel = document.getElementById('recon-bulk-label');
    var scopeEl  = document.getElementById('recon-bulk-scope');
    var rowChecks = Array.prototype.slice.call(document.querySelectorAll('.recon-row-check'));
    if (!bulkForm || rowChecks.length === 0) return;

    var filterActive = bulkBtn && bulkBtn.getAttribute('data-filter-active') === '1';

    // Hanya baris yang BENAR-BENAR TAMPIL boleh dicentang oleh "pilih semua".
    // Baris yang disembunyikan filter (materialitas / instant / live-search /
    // tahapan) tidak ikut terpilih — ini memperbaiki bug "Kirim 15 terpilih"
    // padahal tabel cuma menampilkan 2 baris.
    function visibleChecks() {
        return rowChecks.filter(function (c) {
            var row = c.closest('tr');
            if (!row) return true;
            return window.sfRowIsVisible ? window.sfRowIsVisible(row) : row.style.display !== 'none';
        });
    }

    function selected() { return rowChecks.filter(function (c) { return c.checked; }); }

    function sync() {
        var n = selected().length;
        var vChecks = visibleChecks();
        var vCount = vChecks.length;
        if (bulkBtn) bulkBtn.disabled = (n === 0 && !filterActive);
        if (checkAll) {
            // Pilih-semua mencerminkan baris yang terlihat, bukan seluruh halaman.
            checkAll.checked = vCount > 0 && n > 0 && n === vCount && selected().every(function (c) { return vChecks.indexOf(c) !== -1; });
            checkAll.indeterminate = n > 0 && !checkAll.checked;
            checkAll.disabled = vCount === 0;
        }
        if (bulkLabel) {
            bulkLabel.textContent = n > 0
                ? ('Kirim ' + n + ' terpilih ke Petugas')
                : (filterActive ? 'Kirim semua hasil filter ke Petugas' : 'Kirim 0 terpilih ke Petugas');
        }
    }

    if (checkAll) {
        checkAll.addEventListener('change', function () {
            var turnOn = checkAll.checked;
            visibleChecks().forEach(function (c) { c.checked = turnOn; });
            // baris tersembunyi selalu dilepas agar jumlah = yang terlihat.
            rowChecks.forEach(function (c) {
                if (visibleChecks().indexOf(c) === -1) c.checked = false;
            });
            sync();
        });
    }
    rowChecks.forEach(function (c) { c.addEventListener('change', sync); });

    // Selaraskan ulang saat filter apa pun mengubah visibilitas baris.
    document.addEventListener('sf:rowvis', sync);

    sync();

    // Ada baris dicentang → mode id eksplisit; tidak ada → mode filter aktif.
    bulkForm.addEventListener('submit', function (e) {
        var n = selected().length;
        if (n === 0 && !filterActive) { e.preventDefault(); return; }
        if (scopeEl) scopeEl.value = n > 0 ? 'ids' : 'filter';
    });
})();
</script>

<!-- ====================================================================== -->
<!-- 6. Popup Modal Koreksi — dipakai ulang oleh SEMUA baris tabel          -->
<!-- ====================================================================== -->
<div class="modal-backdrop" id="row-correct-modal" data-row-modal-close>
    <div class="modal" role="dialog" aria-modal="true" aria-labelledby="row-correct-title" data-modal-panel>
        <div class="modal-header">
            <h3 id="row-correct-title"><?= icon('edit') ?> Koreksi Angka</h3>
            <div class="spacer"></div>
            <button type="button" class="modal-x" data-row-modal-close aria-label="<?= __('Tutup') ?>">&times;</button>
        </div>
        <form method="post" action="<?= url('reconciliation/0') ?>" id="row-correct-form">
            <?= csrf_field() ?>
            <input type="hidden" name="return_to" value="<?= e($returnTo) ?>">
            <div class="modal-body">
                <div class="recon-hint-strip" id="row-correct-context" style="margin-top:0;margin-bottom:.85rem;"></div>

                <div class="form-group mb-md">
                    <label for="row-input-qty" class="font-semibold text-xs uppercase tracking-wider">
                        <?= __('Hasil Hitung Fisik Baru') ?> <span class='text-danger'>*</span>
                    </label>
                    <input type="number" id="row-input-qty" name="counted_qty" min="0" step="1" required
                           inputmode="numeric"
                           style="font-size:1.25rem;font-weight:700;padding:.65rem .8rem;font-family:monospace;">
                </div>

                <?php if ($canSeeStockQty): ?>
                    <div class="form-group mb-md">
                        <label for="row-input-price" class="font-semibold text-xs uppercase tracking-wider">
                            <?= __('Harga Satuan (Rp)') ?>
                        </label>
                        <input type="number" id="row-input-price" name="unit_price" min="0" step="1000"
                               style="font-size:1rem;padding:.6rem .8rem;font-family:monospace;">
                    </div>
                <?php else: ?>
                    <!-- Non-admin: JANGAN kirim unit_price sama sekali, agar
                         controller mempertahankan harga lama (bukan di-set 0). -->
                <?php endif; ?>

                <div class="recon-delta-strip" id="row-delta-strip">
                    <span class="muted"><?= __('Pratinjau perubahan:') ?></span>
                    <strong class="mono" id="row-delta-value">—</strong>
                </div>

                <label class="recon-check recon-check-single" for="row-check-confirm">
                    <input type="checkbox" id="row-check-confirm" class="recon-check-box">
                    <span class="recon-check-text">
                        Saya sudah <strong><?= __('menghitung fisik') ?></strong> item ini langsung dan angka yang saya masukkan <strong><?= __('sudah benar') ?></strong>.
                    </span>
                </label>

                <div class="form-group" style="margin:.75rem 0 0;">
                    <label for="row-input-reason" class="font-semibold text-xs uppercase tracking-wider">
                        Alasan Perubahan / Catatan <span class="text-danger">*</span>
                    </label>
                    <textarea id="row-input-reason" name="reason" rows="2" required
                              placeholder="Contoh: Ditemukan 2 unit tambahan di rak bawah B-04 saat hitung ulang..."
                              style="width:100%;padding:.6rem .8rem;font-size:.85rem;border-radius:var(--radius-sm);border:1px solid var(--border);"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline btn-sm" data-row-modal-close><?= __('Batal') ?></button>
                <button type="submit" id="row-btn-submit" class="btn btn-brand btn-sm" disabled>
                    <?= icon('check') ?> Update
                </button>
            </div>
        </form>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
  var expForm = document.querySelector('form[action*="export-sap"]');
  if (expForm) {
    expForm.addEventListener('submit', function () {
      var inp = document.getElementById('recon-export-cols');
      if (!inp) return;
      var headRow = document.querySelector('#recon-table thead tr');
      if (!headRow) return;
      var visible = [].slice.call(headRow.children)
        .map(function (th) { return th.getAttribute('data-col'); })
        .filter(function (k) {
          if (!k || k === 'aksi') return false;
          var th = headRow.querySelector('th[data-col="' + k + '"]');
          return th && th.style.display !== 'none' && !th.classList.contains('is-col-hidden');
        });
      inp.value = visible.join(',');
    });
  }
});
</script>

<?php
$cfLabels = ['group' => __('Group'), 'location' => __('Lokasi'), 'status' => __('Status'), 'kategori' => __('Kategori'), 'satuan' => __('Satuan')];
$cfDisplay = ['draft' => __('Draft'), 'pending_verify' => __('Menunggu Verifikasi'), 'verified' => __('Terverifikasi'), 'rejected' => __('Ditolak')];
$cfForm = 'recon-filter';
$cfDistinct = url('reconciliation/distinct');
require __DIR__ . '/../partials/colfilter.php';
?>

<div class="xf-pop" id="rng-pop" role="dialog" aria-modal="false" hidden>
    <div class="xf-pop-head"><strong id="rng-title"><?= __('Filter') ?></strong></div>
    <div id="rng-body"></div>
    <div class="xf-foot">
        <button type="button" class="btn btn-ghost btn-sm" id="rng-clear"><?= __('Hapus') ?></button>
        <span class="spacer"></span>
        <button type="button" class="btn btn-outline btn-sm" id="rng-cancel"><?= __('Batal') ?></button>
        <button type="button" class="btn btn-brand btn-sm" id="rng-apply"><?= __('Terapkan') ?></button>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
(function () {
  /* RNG_V1: popup rentang angka/tanggal & teks untuk kolom selisih/tanggal/catatan */
  var form = document.getElementById('recon-filter');
  var pop = document.getElementById('rng-pop');
  if (!form || !pop) return;
  var body = document.getElementById('rng-body');
  var title = document.getElementById('rng-title');
  var cur = null;
  var CFG = {
    selqty:    {kind:'rng', type:'number', label:'Selisih Qty (unit)'},
    selnil:    {kind:'rng', type:'number', label:'Selisih Nilai (Rp)'},
    tgl_ajuan: {kind:'rng', type:'date',   label:'Tgl Diajukan'},
    tgl_verif: {kind:'rng', type:'date',   label:'Tgl Verifikasi'},
    catatan:   {kind:'txt', type:'text',   label:'Catatan'}
  };
  function esc(s){return String(s).replace(/[&<>"']/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c];});}
  function existing(key){
    var o={min:'',max:'',txt:''};
    form.querySelectorAll('input.rng-hidden').forEach(function(i){
      var m=i.name.match(/^(rng|txt)\[([a-z_]+)\](?:\[(min|max)\])?$/);
      if(!m||m[2]!==key) return;
      if(m[1]==='txt') o.txt=i.value; else o[m[3]]=i.value;
    });
    return o;
  }
  function open(btn){
    cur=btn.getAttribute('data-rng'); var c=CFG[cur]; if(!c) return;
    var v=existing(cur);
    title.textContent='Filter: '+c.label;
    if(c.kind==='txt'){
      body.innerHTML='<label class="rng-lbl">Mengandung teks<input type="search" id="rng-txt" class="rng-in" maxlength="120" value="'+esc(v.txt)+'" placeholder="Ketik sebagian teks"></label>';
    } else {
      body.innerHTML='<div class="rng-row"><label class="rng-lbl">Dari<input type="'+c.type+'" id="rng-min" class="rng-in" value="'+esc(v.min)+'"'+(c.type==='number'?' step="0.01"':'')+'></label>'+
                     '<label class="rng-lbl">Sampai<input type="'+c.type+'" id="rng-max" class="rng-in" value="'+esc(v.max)+'"'+(c.type==='number'?' step="0.01"':'')+'></label></div>';
    }
    var r=btn.getBoundingClientRect();
    pop.hidden=false;
    var left=Math.min(r.left+window.scrollX, window.scrollX+document.documentElement.clientWidth-300);
    pop.style.left=Math.max(8,left)+'px';
    pop.style.top=(r.bottom+window.scrollY+6)+'px';
    var first=body.querySelector('input'); if(first) first.focus();
  }
  function close(){ pop.hidden=true; cur=null; }
  function clearCur(){
    form.querySelectorAll('input.rng-hidden').forEach(function(i){
      if(i.name.indexOf('rng['+cur+']')===0||i.name.indexOf('txt['+cur+']')===0) i.remove();
    });
  }
  function add(name,val){
    var h=document.createElement('input'); h.type='hidden'; h.className='rng-hidden'; h.name=name; h.value=val; form.appendChild(h);
  }
  function apply(){
    if(!cur) return; var c=CFG[cur]; clearCur();
    if(c.kind==='txt'){
      var t=document.getElementById('rng-txt').value.trim();
      if(t) add('txt['+cur+']', t);
    } else {
      var mn=document.getElementById('rng-min').value.trim();
      var mx=document.getElementById('rng-max').value.trim();
      if(c.type==='number'){
        if(mn!=='' && isNaN(Number(mn))){ alert('Nilai "Dari" harus angka.'); return; }
        if(mx!=='' && isNaN(Number(mx))){ alert('Nilai "Sampai" harus angka.'); return; }
      }
      if(mn!=='' && mx!=='' && Number(mn)>Number(mx)){ alert('"Dari" tidak boleh lebih besar dari "Sampai".'); return; }
      if(mn!=='') add('rng['+cur+'][min]', mn);
      if(mx!=='') add('rng['+cur+'][max]', mx);
    }
    close();
    form.requestSubmit ? form.requestSubmit() : form.submit();
  }
  document.querySelectorAll('.rng-btn').forEach(function(b){
    b.addEventListener('click', function(e){
      e.stopPropagation();
      if(!pop.hidden && cur===b.getAttribute('data-rng')){ close(); return; }
      open(b);
    });
  });
  document.getElementById('rng-apply').addEventListener('click', apply);
  document.getElementById('rng-cancel').addEventListener('click', close);
  document.getElementById('rng-clear').addEventListener('click', function(){
    clearCur(); close(); form.requestSubmit ? form.requestSubmit() : form.submit();
  });
  pop.addEventListener('keydown', function(e){ if(e.key==='Enter'){ e.preventDefault(); apply(); } });
  document.addEventListener('click', function(e){ if(!pop.hidden && !pop.contains(e.target)) close(); });
  document.addEventListener('keydown', function(e){ if(e.key==='Escape') close(); });
})();
});
</script>


<script>
/* RECON-TH-TITLE: gabungkan nama kolom lengkap ke tooltip header (tooltip drag tetap ada) */
(function(){
  function fill(){
    document.querySelectorAll('#recon-table thead th').forEach(function(th){
      var l = th.querySelector('.xf-label');
      if (!l) return;
      var name = (l.textContent||'').trim();
      var cur = th.getAttribute('title') || '';
      if (cur.indexOf(name) === 0) return;
      th.setAttribute('title', cur ? (name + ' · ' + cur) : name);
    });
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', fill); else fill();
})();
</script>

<?php
// Advanced search drawer (konsisten dengan Data STO)
$asForm = 'recon-filter';
$asCols = [
    'code'        => __('Kode Item'),
    'name'        => __('Nama Item'),
    'group'       => __('Group'),
    'location'    => __('Lokasi'),
    'category'    => __('Kategori'),
    'status'      => __('Status'),
    'difference'  => __('Selisih QTY'),
    'diff_amount' => __('Selisih Nilai (Rp)'),
];
$asCurrent = $filters['adv'] ?? [];
$asNumeric = ['difference', 'diff_amount'];
$asMax = 12;
$asToggleId = 'recon-adv-btn';
require __DIR__ . '/../partials/advanced-search.php';
?>
