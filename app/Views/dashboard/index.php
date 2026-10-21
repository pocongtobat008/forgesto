<?php
/**
 * Dashboard.
 *
 * @var array $summary
 * @var array $progress
 * @var array $chart
 * @var array $filters
 * @var array $groups
 * @var int|null $selectedGroup
 * @var int $discrepancyCount
 * @var array $recentActivity
 * @var array $recentImports
 * @var array $totals
 */
$total = (int) $summary['total'];
$counted = (int) $summary['counted'];
$audited = (int) $summary['audited'];
$notCounted = (int) ($summary['not_counted'] ?? ($total - $counted));

$countPercent = percent($counted, $total);
$auditPercent = percent($audited, $total);

$fullyCounted = 0;
$neverCounted = 0;
$fullyAudited = 0;
$neverAudited = 0;
foreach ($progress as $row) {
    $t = (int) $row['total'];
    if ($t > 0 && (int) $row['counted'] === $t) { $fullyCounted++; }
    if ((int) $row['counted'] === 0) { $neverCounted++; }
    if ($t > 0 && (int) $row['audited'] === $t) { $fullyAudited++; }
    if ((int) $row['audited'] === 0) { $neverAudited++; }
}

$groupCount = count($progress);
$groupDonePct = percent($fullyCounted, $groupCount);
$totalCountPct = $countPercent;
$activityIcon = static fn (string $action): string => match ($action) {
    'count'   => 'box',
    'post'    => 'send',
    'audit'   => 'audit',
    'unlock'  => 'unlock',
    'resolve' => 'check',
    'import'  => 'upload',
    'delete'  => 'trash',
    default   => 'activity',
};

// Data untuk Chart.js
$chartGroups = array_map(static fn ($r) => [
    'name'          => (string) ($r['name'] ?? ''),
    'count_percent' => (float) ($r['count_percent'] ?? 0),
    'audit_percent' => (float) ($r['audit_percent'] ?? 0),
], $progress);
$sfDash = [
    'counted'      => $counted,
    'notCounted'   => $notCounted,
    'audited'      => $audited,
    'countPercent' => $countPercent,
    'groups'       => $chartGroups,
];

$selectedGroupLabel = 'Semua Group';
foreach ($groups as $g) {
    if ((int) $selectedGroup === (int) $g['id']) {
        $selectedGroupLabel = (string) $g['name'] . ' (' . (string) $g['code'] . ')';
        break;
    }
}
?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js" defer></script>
<script>
    window.SF_DASH = <?= json_encode($sfDash, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
</script>

<?= \App\Core\View::partial('partials/hero-welcome', [
    'title'            => __('Dashboard'),
    'user'             => auth(),
    'summary'          => $summary,
    'progress'         => $progress,
    'discrepancyCount' => $discrepancyCount,
]) ?>

<div class="page-head">
    <div>
        <h2 class="page-head-title"><?= __('Dashboard') ?></h2>
        <p><?= __('Ringkasan stok, aktivitas, dan progres periode berjalan.') ?></p>
    </div>
    <div class="spacer"></div>
    <form method="get" action="<?= url('dashboard') ?>" class="flex gap-sm items-center wrap">
        <div class="search-select" data-search-select data-auto-submit style="min-width:230px;">
            <input type="text" placeholder="<?= __('Cari group...') ?>" value="<?= e($selectedGroupLabel) ?>" autocomplete="off" aria-label="<?= __('Filter group') ?>">
            <span class="search-select-chev"><?= icon('chev-down') ?></span>
            <input type="hidden" name="group_id" value="<?= $selectedGroup ? (int) $selectedGroup : '' ?>">
            <div class="ss-list">
                <div class="ss-item <?= !$selectedGroup ? 'is-selected' : '' ?>" data-value="" data-label="<?= __('Semua Group') ?>">
                    <span><?= __('Semua Group') ?></span>
                </div>
                <?php foreach ($groups as $group): ?>
                    <div class="ss-item <?= (int) $selectedGroup === (int) $group['id'] ? 'is-selected' : '' ?>"
                         data-value="<?= (int) $group['id'] ?>"
                         data-label="<?= e($group['name']) ?> (<?= e($group['code']) ?>)">
                        <span><?= e($group['name']) ?></span>
                        <span class="code"><?= e($group['code']) ?></span>
                    </div>
                <?php endforeach; ?>
                <div class="ss-empty" style="display:none;"><?= __('Tidak ada group yang cocok.') ?></div>
            </div>
        </div>
    </form>
</div>

<!-- Informasi penting dari admin -->
<div class="card" id="dash-info-card" style="margin-top:1rem;border-left:4px solid var(--warning-500, #f59e0b);">
    <div class="card-header">
        <h2><?= icon('info') ?> <?= __('Informasi Penting') ?></h2>
        <a class="btn btn-outline btn-xs" href="<?= url('informasi') ?>"><?= __('Lihat semua') ?></a>
        <div class="sf-info-tools">
            <span class="faint text-sm" id="info-updated" data-label="<?= __('Diperbarui') ?>" data-tz="<?= e(date_default_timezone_get()) ?>"><?= __('Diperbarui') ?> <?= e(date('H:i:s')) ?></span>
            <button type="button" class="btn btn-outline btn-xs" data-info-refresh data-card="#dash-info-card"
                    title="<?= __('Muat ulang informasi tanpa logout') ?>">
                <?= icon('refresh') ?> <span><?= __('Muat ulang') ?></span>
            </button>
        </div>
    </div>
    <div class="card-body" style="display:flex;flex-direction:column;gap:.7rem;">
        <?php if ($infos === []): ?>
            <div class="faint text-sm"><?= __('Belum ada informasi aktif. Tekan "Muat ulang" untuk memeriksa.') ?></div>
        <?php endif; ?>
        <?php foreach ($infos as $i => $info): ?>
            <div<?= $i > 0 ? ' style="padding-top:.7rem;border-top:1px dashed var(--border);"' : '' ?>>
                <div class="cell-title"><?= e((string) $info['title']) ?></div>
                <?php if (! empty($info['body'])): ?>
                    <div class="cell-sub"><?= e(mb_strimwidth((string) $info['body'], 0, 170, '…')) ?></div>
                <?php endif; ?>
                <div class="cell-sub faint"><?= e(date('d M Y H:i', strtotime((string) $info['created_at']))) ?></div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Ringkasan item -->
<div class="grid grid-4 mt-2">
    <div class="stat is-brand" style="--i:4">
        <div class="stat-icon"><?= icon('layers') ?></div>
        <div class="stat-label"><?= __('Total item') ?></div>
        <div class="stat-value"><?= $total ?></div>
        <div class="stat-meta"><?= $totals['groups'] ?> group &middot; <?= (int) $totals['locations'] ?> lokasi &middot; <?= (int) $totals['devices'] ?> device</div>
    </div>
    <div class="stat is-success" style="--i:5">
        <div class="stat-icon"><?= icon('check') ?></div>
        <div class="stat-label"><?= __('Sudah dihitung') ?></div>
        <div class="stat-value"><?= $counted ?></div>
        <div class="stat-meta"><?= $countPercent ?>% dari total &middot; <?= (int) $summary['posted'] ?> terposting</div>
    </div>
    <div class="stat is-info" style="--i:6">
        <div class="stat-icon"><?= icon('clipboard-check') ?></div>
        <div class="stat-label"><?= __('Sudah diaudit') ?></div>
        <div class="stat-value"><?= $audited ?></div>
        <div class="stat-meta"><?= $auditPercent ?>% dari total</div>
    </div>
    <div class="stat is-danger" style="--i:7">
        <div class="stat-icon"><?= icon('alert-circle') ?></div>
        <div class="stat-label"><?= __('Selisih terbuka') ?></div>
        <div class="stat-value"><?= (int) $discrepancyCount ?></div>
        <div class="stat-meta"><?= (int) $summary['locked'] ?> item terkunci</div>
    </div>
</div>

<!-- ====================================================================== -->
<!-- Summary Selisih per Group (Reconciliation Summary & Recount Status)     -->
<?php
/**
 * Bagian analitik dashboard: tren harian, distribusi group, progres per group.
 * Data: $trend (labels/counted/audited), $progress, $summary, $recentActivity, $recentImports
 */
$groupRows = array_values($progress ?? []);
$groupTotal = count($groupRows);
$doneGroups = $runGroups = 0;
$pctSum = 0;
foreach ($groupRows as $g) {
    $p = (int) ($g['count_percent'] ?? 0);
    $pctSum += $p;
    if ($p >= 100) { $doneGroups++; } elseif ($p > 0) { $runGroups++; }
}
$idleGroups = max(0, $groupTotal - $doneGroups - $runGroups);
$avgPct = $groupTotal > 0 ? (int) round($pctSum / $groupTotal) : 0;
$itemsTotal = $itemsAudited = $itemsCounted = 0;
foreach ($groupRows as $g) {
    $itemsTotal   += (int) ($g['total'] ?? 0);
    $itemsCounted += (int) ($g['counted'] ?? 0);
    $itemsAudited += (int) ($g['audited'] ?? 0);
}
$auditCoverage = $itemsTotal > 0 ? (int) round($itemsAudited / $itemsTotal * 100) : 0;

usort($groupRows, static fn (array $a, array $b): int => (int) ($b['count_percent'] ?? 0) <=> (int) ($a['count_percent'] ?? 0));
$topGroup = $groupRows[0] ?? null;
$lagGroup = $groupRows !== [] ? $groupRows[count($groupRows) - 1] : null;

$donutR = 46.0;
$donutC = 2 * M_PI * $donutR;
$donutSegs = [
    ['label' => __('Selesai'),     'val' => $doneGroups, 'cls' => 'is-brand'],
    ['label' => __('Berjalan'),    'val' => $runGroups,  'cls' => 'is-warning'],
    ['label' => __('Belum mulai'), 'val' => $idleGroups, 'cls' => 'is-idle'],
];
$donutTotal = max(1, $groupTotal);
$donutDonePct = (int) round($doneGroups / $donutTotal * 100);
$activityIcon = $activityIcon ?? static fn (string $a): string => 'activity';
?>

<!-- ====================================================================== -->
<!-- Analitik: tren harian, distribusi group, progres per group              -->
<!-- ====================================================================== -->
<section class="sf-analytics mt-2">

    <div class="sf-insights">
        <article class="sf-insight is-brand">
            <span class="sf-insight-ico"><?= icon('trending-up') ?></span>
            <span class="sf-insight-body">
                <span class="sf-insight-label"><?= __('Rata-rata progres') ?></span>
                <span class="sf-insight-value"><?= $avgPct ?>%</span>
                <span class="sf-insight-sub"><?= sprintf(__('dari %d group aktif'), $groupTotal) ?></span>
            </span>
        </article>
        <article class="sf-insight is-success">
            <span class="sf-insight-ico"><?= icon('check') ?></span>
            <span class="sf-insight-body">
                <span class="sf-insight-label"><?= __('Cakupan audit') ?></span>
                <span class="sf-insight-value"><?= $auditCoverage ?>%</span>
                <span class="sf-insight-sub"><?= sprintf(__('%d dari %d item'), $itemsAudited, $itemsTotal) ?></span>
            </span>
        </article>
        <article class="sf-insight is-warning">
            <span class="sf-insight-ico"><?= icon('alert') ?></span>
            <span class="sf-insight-body">
                <span class="sf-insight-label"><?= __('Selisih terbuka') ?></span>
                <span class="sf-insight-value"><?= (int) $discrepancyCount ?></span>
                <span class="sf-insight-sub"><?= ((int) $discrepancyCount) > 0 ? __('perlu ditelusuri observer') : __('semua selisih tertangani') ?></span>
            </span>
        </article>
    </div>

    <div class="sf-chart-grid">
        <section class="sf-chart-card sf-span-8">
            <header class="sf-chart-head">
                <div class="sf-chart-heading">
                    <span class="sf-eyebrow"><?= __('Tren aktivitas') ?></span>
                    <h3 class="sf-chart-title"><?= __('Penghitungan & audit harian') ?></h3>
                    <p class="sf-chart-sub"><?= __('Jumlah item yang dihitung dan audit yang tercatat setiap hari.') ?></p>
                </div>
                <div class="spacer"></div>
                <div class="sf-seg" role="group" aria-label="<?= __('Rentang hari') ?>" data-sf-range>
                    <button type="button" data-days="7">7H</button>
                    <button type="button" data-days="14" class="is-active" aria-pressed="true">14H</button>
                    <button type="button" data-days="30">30H</button>
                </div>
            </header>

            <div class="sf-trend" data-sf-trend>
                <svg viewBox="0 0 760 240" role="img"
                     aria-label="<?= __('Grafik tren penghitungan dan audit harian') ?>">
                    <defs>
                        <linearGradient id="sfAreaBrand" x1="0" y1="0" x2="0" y2="1">
                            <stop offset="0%" stop-color="#2563eb" stop-opacity=".26"/>
                            <stop offset="100%" stop-color="#2563eb" stop-opacity="0"/>
                        </linearGradient>
                        <linearGradient id="sfAreaOk" x1="0" y1="0" x2="0" y2="1">
                            <stop offset="0%" stop-color="#10b981" stop-opacity=".22"/>
                            <stop offset="100%" stop-color="#10b981" stop-opacity="0"/>
                        </linearGradient>
                    </defs>
                    <g class="sf-grid"></g>
                    <path class="sf-area is-brand" fill="url(#sfAreaBrand)" d=""></path>
                    <path class="sf-area is-success" fill="url(#sfAreaOk)" d=""></path>
                    <path class="sf-line is-brand" fill="none" stroke="#2563eb" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" d=""></path>
                    <path class="sf-line is-success" fill="none" stroke="#10b981" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" d=""></path>
                    <g class="sf-xaxis"></g>
                    <g class="sf-dots"></g>
                    <line class="sf-cursor" x1="0" y1="0" x2="0" y2="0" stroke="#94a3b8" stroke-width="1" stroke-dasharray="3 3" opacity="0"/>
                </svg>
                <div class="sf-tip" hidden></div>
            </div>

            <footer class="sf-legend">
                <span><i class="sf-dot is-brand"></i><?= __('Dihitung') ?></span>
                <span><i class="sf-dot is-success"></i><?= __('Diaudit') ?></span>
                <span class="sf-legend-spacer"></span>
                <span class="sf-legend-total" data-sf-total></span>
            </footer>
        </section>

        <section class="sf-chart-card sf-span-4">
            <header class="sf-chart-head">
                <div class="sf-chart-heading">
                    <span class="sf-eyebrow"><?= __('Distribusi') ?></span>
                    <h3 class="sf-chart-title"><?= __('Status group') ?></h3>
                </div>
            </header>

            <div class="sf-donut-wrap">
                <svg class="sf-donut" viewBox="0 0 120 120" role="img"
                     aria-label="<?= sprintf(__('%d group selesai dari %d group'), $doneGroups, $groupTotal) ?>">
                    <circle cx="60" cy="60" r="<?= $donutR ?>" fill="none" stroke="currentColor" stroke-width="13" class="sf-donut-track"/>
                    <?php $acc = 0.0; foreach ($donutSegs as $seg):
                        $len = $donutTotal > 0 ? ($seg['val'] / $donutTotal) * $donutC : 0;
                        if ($len <= 0) { continue; } ?>
                        <circle cx="60" cy="60" r="<?= $donutR ?>" fill="none"
                                stroke-width="13" stroke-linecap="butt"
                                class="sf-donut-seg <?= $seg['cls'] ?>"
                                stroke-dasharray="<?= round($len, 2) ?> <?= round($donutC - $len, 2) ?>"
                                stroke-dashoffset="<?= round(-$acc, 2) ?>"
                                transform="rotate(-90 60 60)"/>
                    <?php $acc += $len; endforeach; ?>
                </svg>
                <div class="sf-donut-center">
                    <strong><?= $donutDonePct ?>%</strong>
                    <span><?= __('selesai') ?></span>
                    <em><?= sprintf(__('%d dari %d group'), $doneGroups, $groupTotal) ?></em>
                </div>
            </div>

            <ul class="sf-donut-legend">
                <?php foreach ($donutSegs as $seg):
                    $pct = $donutTotal > 0 ? (int) round($seg['val'] / $donutTotal * 100) : 0; ?>
                    <li>
                        <i class="sf-dot <?= $seg['cls'] ?>"></i>
                        <span class="sf-donut-label"><?= $seg['label'] ?></span>
                        <span class="sf-donut-pct"><?= $pct ?>%</span>
                        <span class="sf-donut-val"><?= (int) $seg['val'] ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>
    </div>

    <div class="sf-side-grid">
        <div class="card lift">
            <div class="card-header"><h3><?= __('Aktivitas terbaru') ?></h3></div>
            <div class="card-body" style="padding-top:.4rem;">
                <?php if ($recentActivity === []): ?>
                    <p class="faint text-sm mb-0"><?= __('Belum ada aktivitas.') ?></p>
                <?php else: ?>
                    <ul class="list-reset">
                        <?php foreach ($recentActivity as $log): ?>
                            <li class="list-item">
                                <span class="li-icon"><?= icon($activityIcon((string) $log['action'])) ?></span>
                                <span class="li-body">
                                    <span class="li-title"><?= e($log['description'] ?? $log['action']) ?></span>
                                    <span class="li-meta">
                                        <?= e($log['user_name'] ?? 'Sistem') ?> &middot; <?= e(format_date($log['created_at'], 'd M H:i')) ?>
                                    </span>
                                </span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>

        <div class="card lift">
            <div class="card-header">
                <h3><?= __('Import terakhir') ?></h3>
                <div class="spacer"></div>
                <?php if (can('import_data')): ?>
                    <a class="btn btn-outline btn-xs" href="<?= url('import') ?>">Import</a>
                <?php endif; ?>
            </div>
            <div class="card-body" style="padding-top:.4rem;">
                <?php if ($recentImports === []): ?>
                    <p class="faint text-sm mb-0"><?= __('Belum ada import.') ?></p>
                <?php else: ?>
                    <ul class="list-reset">
                        <?php foreach ($recentImports as $log): ?>
                            <li class="list-item">
                                <span class="li-icon"><?= icon('upload') ?></span>
                                <span class="li-body">
                                    <span class="li-title"><?= e($log['filename'] ?? '-') ?></span>
                                    <span class="li-meta">
                                        <?= (int) $log['success_rows'] ?> berhasil &middot; <?= (int) $log['failed_rows'] ?> gagal
                                    </span>
                                </span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<script type="application/json" id="sf-trend-json"><?= json_encode($trend ?? ['labels' => [], 'counted' => [], 'audited' => []], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?></script>
<script>
(function () {
    var node = document.getElementById('sf-trend-json');
    var box = document.querySelector('[data-sf-trend]');
    if (!node || !box) { return; }
    var data;
    try { data = JSON.parse(node.textContent); } catch (e) { return; }

    var svg = box.querySelector('svg');
    var areaB = svg.querySelector('.sf-area.is-brand');
    var areaO = svg.querySelector('.sf-area.is-success');
    var lineB = svg.querySelector('.sf-line.is-brand');
    var lineO = svg.querySelector('.sf-line.is-success');
    var grid = svg.querySelector('.sf-grid');
    var xaxis = svg.querySelector('.sf-xaxis');
    var dots = svg.querySelector('.sf-dots');
    var cursor = svg.querySelector('.sf-cursor');
    var tip = box.querySelector('.sf-tip');
    var totalEl = document.querySelector('[data-sf-total]');
    var H = 240, W = 760, PL = 34, PR = 10, PT = 16, PB = 28;
    var days = 14, current = null;

    function smooth(pts) {
        if (!pts.length) { return ''; }
        if (pts.length === 1) { return 'M' + pts[0][0] + ' ' + pts[0][1]; }
        var d = 'M' + pts[0][0].toFixed(1) + ' ' + pts[0][1].toFixed(1);
        for (var i = 0; i < pts.length - 1; i++) {
            var p0 = pts[Math.max(0, i - 1)], p1 = pts[i], p2 = pts[i + 1], p3 = pts[Math.min(pts.length - 1, i + 2)];
            var c1x = p1[0] + (p2[0] - p0[0]) / 6, c1y = p1[1] + (p2[1] - p0[1]) / 6;
            var c2x = p2[0] - (p3[0] - p1[0]) / 6, c2y = p2[1] - (p3[1] - p1[1]) / 6;
            d += ' C' + c1x.toFixed(1) + ' ' + c1y.toFixed(1) + ' ' + c2x.toFixed(1) + ' ' + c2y.toFixed(1) + ' ' + p2[0].toFixed(1) + ' ' + p2[1].toFixed(1);
        }
        return d;
    }

    function render(n) {
        days = n;
        var cw = box.clientWidth || 760;
        W = Math.max(300, Math.min(760, Math.round(cw)));
        H = W < 480 ? 196 : 240;
        PB = W < 480 ? 30 : 28;
        svg.setAttribute('viewBox', '0 0 ' + W + ' ' + H);
        var L = data.labels.slice(-n), C = data.counted.slice(-n), A = data.audited.slice(-n);
        if (!L.length) { return; }
        var raw = C.concat(A).concat([4]);
        var max = Math.max.apply(null, raw);
        var step = Math.max(1, Math.ceil(max / 4));
        max = step * 4;
        var iw = W - PL - PR, ih = H - PT - PB;
        var X = function (i) { return L.length === 1 ? PL + iw / 2 : PL + (iw * i) / (L.length - 1); };
        var Y = function (v) { return PT + ih - (v / max) * ih; };

        var pb = [], po = [];
        for (var i = 0; i < L.length; i++) { pb.push([X(i), Y(C[i])]); po.push([X(i), Y(A[i])]); }

        lineB.setAttribute('d', smooth(pb));
        lineO.setAttribute('d', smooth(po));
        areaB.setAttribute('d', smooth(pb) + ' L' + X(L.length - 1).toFixed(1) + ' ' + (PT + ih) + ' L' + X(0).toFixed(1) + ' ' + (PT + ih) + ' Z');
        areaO.setAttribute('d', smooth(po) + ' L' + X(L.length - 1).toFixed(1) + ' ' + (PT + ih) + ' L' + X(0).toFixed(1) + ' ' + (PT + ih) + ' Z');

        var g = '', ax = '', dt = '';
        for (var k = 0; k <= 4; k++) {
            var y = PT + (ih * k) / 4;
            var val = Math.round(max - (max * k) / 4);
            g += '<line x1="' + PL + '" y1="' + y.toFixed(1) + '" x2="' + (W - PR) + '" y2="' + y.toFixed(1) + '"/>';
            ax += '<text x="' + (PL - 8) + '" y="' + (y + 3.5).toFixed(1) + '" text-anchor="end">' + val + '</text>';
        }
        var every = L.length > 16 ? Math.ceil(L.length / 8) : (L.length > 9 ? 2 : 1);
        if (W < 460) { every = Math.max(every, 3); }
        var idx = [];
        for (var j = 0; j < L.length; j++) { if (j % every === 0) { idx.push(j); } }
        var lastIx = L.length - 1;
        if (idx[idx.length - 1] !== lastIx) {
            if (idx.length > 1 && lastIx - idx[idx.length - 1] < every) { idx.pop(); }
            idx.push(lastIx);
        }
        for (var k2 = 0; k2 < idx.length; k2++) {
            var j = idx[k2];
            var anchor = j === 0 ? 'start' : (j === lastIx ? 'end' : 'middle');
            var lx = X(j) + (j === 0 ? -2 : (j === lastIx ? 2 : 0));
            ax += '<text x="' + lx.toFixed(1) + '" y="' + (H - 8) + '" text-anchor="' + anchor + '">' + L[j] + '</text>';
        }
        for (var m = 0; m < L.length; m++) {
            dt += '<circle cx="' + X(m).toFixed(1) + '" cy="' + Y(C[m]).toFixed(1) + '" r="3" class="sf-dot-point is-brand"/>';
            dt += '<circle cx="' + X(m).toFixed(1) + '" cy="' + Y(A[m]).toFixed(1) + '" r="3" class="sf-dot-point is-success"/>';
        }
        grid.innerHTML = g;
        xaxis.innerHTML = ax;
        dots.innerHTML = dt;
        cursor.setAttribute('y1', PT);
        cursor.setAttribute('y2', PT + ih);

        var sumC = C.reduce(function (a, b) { return a + b; }, 0);
        var sumA = A.reduce(function (a, b) { return a + b; }, 0);
        if (totalEl) {
            totalEl.textContent = n + ' hari: ' + sumC + ' item dihitung · ' + sumA + ' audit';
        }
        current = { X: X, Y: Y, C: C, A: A, L: L, n: n };
    }

    function hideTip() {
        tip.hidden = true;
        cursor.setAttribute('opacity', '0');
    }

    function showTip(ev) {
        if (!current) { return; }
        var r = svg.getBoundingClientRect();
        var vx = ((ev.clientX - r.left) / r.width) * W;
        var best = 0, bestD = Infinity;
        for (var i = 0; i < current.n; i++) {
            var d = Math.abs(current.X(i) - vx);
            if (d < bestD) { bestD = d; best = i; }
        }
        var px = current.X(best);
        cursor.setAttribute('x1', px);
        cursor.setAttribute('x2', px);
        cursor.setAttribute('opacity', '.55');
        tip.hidden = false;
        tip.innerHTML = '<b>' + current.L[best] + '</b><span><i class="sf-dot is-brand"></i>Dihitung <em>' + current.C[best] + '</em></span><span><i class="sf-dot is-success"></i>Diaudit <em>' + current.A[best] + '</em></span>';
        var left = (px / W) * r.width;
        var tw = tip.offsetWidth;
        left = Math.min(Math.max(6, left - tw / 2), r.width - tw - 6);
        tip.style.left = left + 'px';
    }

    svg.addEventListener('mousemove', showTip);
    svg.addEventListener('mouseleave', hideTip);

    var seg = document.querySelector('[data-sf-range]');
    if (seg) {
        seg.addEventListener('click', function (e) {
            var b = e.target.closest('button[data-days]');
            if (!b) { return; }
            seg.querySelectorAll('button').forEach(function (x) {
                x.classList.remove('is-active');
                x.setAttribute('aria-pressed', 'false');
            });
            b.classList.add('is-active');
            b.setAttribute('aria-pressed', 'true');
            hideTip();
            render(parseInt(b.getAttribute('data-days'), 10) || 14);
        });
    }

    render(14);
    var rt;
    window.addEventListener('resize', function () {
        hideTip();
        clearTimeout(rt);
        rt = setTimeout(function () { render(days); }, 160);
    });
})();
</script>
