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

<div class="page-head">
    <div>
        <h1><?= __('Dashboard') ?></h1>
        <p><?= __('Ringkasan progres penghitungan dan audit seluruh group.') ?></p>
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

<!-- ====================================================================== -->
<!-- Progres keseluruhan penghitungan group (untuk observer)                 -->
<!-- ====================================================================== -->
<div class="card mt-2">
    <div class="card-header">
        <div>
            <h2 style="font-size:1.02rem;margin:0;"><?= icon('trending-up') ?> Progres Penghitungan Keseluruhan</h2>
            <p class="text-xs muted mt-1" style="margin:0;">Persentase keseluruhan penghitungan dan group yang sudah tuntas dihitung observer.</p>
        </div>
        <div class="spacer"></div>
        <span class="badge badge-brand"><?= (int) $groupCount ?> group</span>
    </div>
    <div class="card-body">
        <div class="sf-prog-row">
            <div class="sf-prog-head">
                <span class="sf-prog-label"><?= __('Item sudah dihitung') ?></span>
                <span class="sf-prog-num"><strong><?= (int) $counted ?></strong> / <?= (int) $total ?> item</span>
                <span class="sf-prog-pct is-brand"><?= (int) $totalCountPct ?>%</span>
            </div>
            <div class="sf-progbar is-brand" role="progressbar" aria-valuenow="<?= (int) $totalCountPct ?>" aria-valuemin="0" aria-valuemax="100"
                 aria-label="<?= __('Persentase item sudah dihitung') ?>">
                <span style="--progress: <?= (int) $totalCountPct ?>%"></span>
            </div>
        </div>

        <div class="sf-prog-row">
            <div class="sf-prog-head">
                <span class="sf-prog-label"><?= __('Group sudah selesai dihitung') ?></span>
                <span class="sf-prog-num"><strong><?= (int) $fullyCounted ?></strong> / <?= (int) $groupCount ?> group</span>
                <span class="sf-prog-pct is-success"><?= (int) $groupDonePct ?>%</span>
            </div>
            <div class="sf-progbar is-success" role="progressbar" aria-valuenow="<?= (int) $groupDonePct ?>" aria-valuemin="0" aria-valuemax="100"
                 aria-label="<?= __('Persentase group sudah selesai dihitung') ?>">
                <span style="--progress: <?= (int) $groupDonePct ?>%"></span>
            </div>
        </div>

        <div class="sf-prog-mini-grid mt-2" style="gap:.6rem;">
            <div class="sf-prog-mini">
                <span class="sf-prog-mini-ico is-success"><?= icon('check-circle') ?></span>
                <span><b><?= (int) $fullyCounted ?></b> group tuntas</span>
            </div>
            <div class="sf-prog-mini">
                <span class="sf-prog-mini-ico is-warning"><?= icon('clock') ?></span>
                <span><b><?= $groupCount - $fullyCounted - $neverCounted ?></b> group berjalan</span>
            </div>
            <div class="sf-prog-mini">
                <span class="sf-prog-mini-ico is-idle"><?= icon('alert-circle') ?></span>
                <span><b><?= (int) $neverCounted ?></b> group belum mulai</span>
            </div>
        </div>
    </div>
</div>

<!-- Kartu group: sudah/belum dihitung & diaudit -->
<div class="grid grid-4">
    <div class="stat is-success" style="--i:0">
        <div class="stat-icon"><?= icon('check') ?></div>
        <div class="stat-label"><?= __('Group 100% dihitung') ?></div>
        <div class="stat-value"><?= $fullyCounted ?></div>
        <div class="stat-meta">dari <?= $groupCount ?> group</div>
    </div>

    <div class="stat is-danger" style="--i:1">
        <div class="stat-icon"><?= icon('x') ?></div>
        <div class="stat-label"><?= __('Belum dihitung sama sekali') ?></div>
        <div class="stat-value"><?= $neverCounted ?></div>
        <div class="stat-meta"><?= __('group tanpa hasil hitung') ?></div>
    </div>

    <div class="stat is-info" style="--i:2">
        <div class="stat-icon"><?= icon('clipboard-check') ?></div>
        <div class="stat-label"><?= __('Group 100% diaudit') ?></div>
        <div class="stat-value"><?= $fullyAudited ?></div>
        <div class="stat-meta">dari <?= $groupCount ?> group</div>
    </div>

    <div class="stat is-warning" style="--i:3">
        <div class="stat-icon"><?= icon('alert-circle') ?></div>
        <div class="stat-label"><?= __('Belum diaudit sama sekali') ?></div>
        <div class="stat-value"><?= $neverAudited ?></div>
        <div class="stat-meta"><?= __('group menunggu audit') ?></div>
    </div>
</div>

<!-- Ringkasan item -->
<div class="grid grid-4 mt-2">
    <div class="stat is-brand" style="--i:4">
        <div class="stat-label"><?= __('Total item') ?></div>
        <div class="stat-value"><?= $total ?></div>
        <div class="stat-meta"><?= $totals['groups'] ?> group &middot; <?= (int) $totals['locations'] ?> lokasi &middot; <?= (int) $totals['devices'] ?> device</div>
    </div>
    <div class="stat is-success" style="--i:5">
        <div class="stat-label"><?= __('Sudah dihitung') ?></div>
        <div class="stat-value"><?= $counted ?></div>
        <div class="stat-meta"><?= $countPercent ?>% dari total &middot; <?= (int) $summary['posted'] ?> terposting</div>
    </div>
    <div class="stat is-info" style="--i:6">
        <div class="stat-label"><?= __('Sudah diaudit') ?></div>
        <div class="stat-value"><?= $audited ?></div>
        <div class="stat-meta"><?= $auditPercent ?>% dari total</div>
    </div>
    <div class="stat is-danger" style="--i:7">
        <div class="stat-label"><?= __('Selisih terbuka') ?></div>
        <div class="stat-value"><?= (int) $discrepancyCount ?></div>
        <div class="stat-meta"><?= (int) $summary['locked'] ?> item terkunci</div>
    </div>
</div>

<!-- ====================================================================== -->
<!-- Summary Selisih per Group (Reconciliation Summary & Recount Status)     -->
<!-- ====================================================================== -->
<?php
$summaryTotalDiffAmt      = 0.0;
$summaryTotalPlusAmt      = 0.0;
$summaryTotalMinusAmt     = 0.0;
$summaryTotalDiffQty      = 0;
$summaryRecountGroupCount = 0;
$summaryWithDiffCount     = 0;
$summaryDeficitCount      = 0;
$summarySurplusCount      = 0;

foreach ($progress as $r) {
    $diffAmt = (float) ($r['diff_amount'] ?? 0);
    $diffQty = (int) ($r['diff_qty'] ?? 0);
    $recount = (int) ($r['recount_count'] ?? 0);

    $summaryTotalDiffAmt += $diffAmt;
    $summaryTotalDiffQty += $diffQty;
    if ($recount > 0) {
        $summaryRecountGroupCount++;
    }
    if ($diffAmt != 0.0) {
        $summaryWithDiffCount++;
    }
    if ($diffAmt > 0) {
        $summaryTotalPlusAmt += $diffAmt;
        $summarySurplusCount++;
    } elseif ($diffAmt < 0) {
        $summaryTotalMinusAmt += $diffAmt;
        $summaryDeficitCount++;
    }
}
$canSeeAmt = can_see_stock_qty();
?>
<div class="sf-summary-box">
    <!-- Header Summary Card -->
    <div class="sf-summary-box-head">
        <div class="sf-summary-title-wrap">
            <div class="sf-summary-icon-badge" aria-hidden="true">
                <?= icon('scale') ?>
            </div>
            <div>
                <h2 style="font-size:1.05rem;font-weight:700;margin:0;letter-spacing:-0.02em;color:var(--text,#1e293b);">
                    <?= __('Summary Selisih per Group') ?>
                </h2>
                <p class="text-xs muted mt-1" style="margin:0;">
                    <?= __('Rekapitulasi selisih nilai fisik vs sistem (Rp) dengan deteksi otomatis group recount.') ?>
                </p>
            </div>
        </div>
        <div class="sf-summary-kpis">
            <?php if ($summaryRecountGroupCount > 0): ?>
                <span class="sf-summary-pill is-recount" title="<?= e($summaryRecountGroupCount) ?> group memiliki item dalam status cek ulang">
                    <?= icon('alert-circle') ?> <span><?= (int) $summaryRecountGroupCount ?> Group Recount</span>
                </span>
            <?php endif; ?>
            <span class="sf-summary-pill <?= $summaryTotalDiffAmt < 0 ? 'is-net-neg' : ($summaryTotalDiffAmt > 0 ? 'is-net-pos' : 'is-neutral') ?>" title="<?= __('Net Total Selisih Nilai') ?>">
                <span class="text-xs faint">Net:</span>
                <strong><?= $canSeeAmt ? (($summaryTotalDiffAmt > 0 ? '+' : '') . format_rupiah($summaryTotalDiffAmt)) : '***' ?></strong>
            </span>
            <span class="sf-summary-pill is-neutral">
                <?= count($progress) ?> Group
            </span>
        </div>
    </div>

    <!-- Filter & Toolbar -->
    <div class="sf-summary-toolbar">
        <div class="sf-summary-search-box">
            <?= icon('search') ?>
            <input type="text" id="sf-summary-search" class="sf-summary-search-input" placeholder="<?= __('Cari nama atau kode group...') ?>" autocomplete="off" aria-label="<?= __('Filter nama group') ?>" style="padding-left: 2.5rem !important;">
        </div>

        <div class="sf-summary-chips" role="tablist" aria-label="<?= __('Filter kondisi selisih') ?>">
            <button type="button" class="sf-summary-chip is-active" data-filter="all">
                <?= __('Semua') ?> <span class="chip-count"><?= count($progress) ?></span>
            </button>
            <button type="button" class="sf-summary-chip" data-filter="diff">
                <?= __('Ada Selisih') ?> <span class="chip-count"><?= (int) $summaryWithDiffCount ?></span>
            </button>
            <button type="button" class="sf-summary-chip" data-filter="deficit">
                <?= __('Defisit (-)') ?> <span class="chip-count"><?= (int) $summaryDeficitCount ?></span>
            </button>
            <button type="button" class="sf-summary-chip" data-filter="surplus">
                <?= __('Surplus (+)') ?> <span class="chip-count"><?= (int) $summarySurplusCount ?></span>
            </button>
            <?php if ($summaryRecountGroupCount > 0): ?>
                <button type="button" class="sf-summary-chip chip-recount" data-filter="recount">
                    <?= icon('alert-circle') ?> <?= __('Sedang Recount') ?> <span class="chip-count"><?= (int) $summaryRecountGroupCount ?></span>
                </button>
            <?php endif; ?>
        </div>

        <div class="flex items-center gap-xs">
            <label for="sf-summary-sort" class="text-xs muted col-hide-mobile"><?= __('Urutkan:') ?></label>
            <select id="sf-summary-sort" class="form-select form-select-xs" style="font-size:0.8rem;border-radius:8px;padding:0.35rem 0.65rem;" aria-label="<?= __('Pilihan urutan ringkasan') ?>">
                <option value="name_asc"><?= __('Nama Group (A-Z)') ?></option>
                <option value="diff_desc"><?= __('Selisih Terbesar (|Rp|)') ?></option>
                <option value="neg_first"><?= __('Defisit Terbanyak (-)') ?></option>
                <option value="pos_first"><?= __('Surplus Terbanyak (+)') ?></option>
            </select>
        </div>
    </div>

    <!-- Table matching visual reference -->
    <div class="sf-summary-table-wrap">
        <table class="sf-summary-table" id="sf-summary-table">
            <thead>
                <tr>
                    <th style="min-width:260px;">
                        <div class="flex items-center gap-xs">
                            <span><?= __('Nama Group') ?></span>
                            <button type="button" class="sf-summary-filter-btn" id="sf-btn-sort-name" title="<?= __('Urutkan berdasarkan Nama Group') ?>" aria-label="<?= __('Urutkan nama group') ?>">
                                <?= icon('chev-down') ?>
                            </button>
                        </div>
                    </th>
                    <th style="width:150px;" class="col-hide-mobile"><?= __('Status') ?></th>
                    <th style="width:130px;" class="t-right col-hide-mobile"><?= __('Selisih QTY') ?></th>
                    <th style="min-width:200px;" class="t-right">
                        <div class="flex items-center justify-end gap-xs">
                            <span><?= __('Selisih Amount') ?></span>
                            <button type="button" class="sf-summary-filter-btn" id="sf-btn-sort-amt" title="<?= __('Urutkan berdasarkan Selisih Amount') ?>" aria-label="<?= __('Urutkan selisih amount') ?>">
                                <?= icon('chev-down') ?>
                            </button>
                        </div>
                    </th>
                </tr>
            </thead>
            <tbody id="sf-summary-tbody">
                <?php if ($progress === []): ?>
                    <tr>
                        <td colspan="4" class="t-center muted" style="padding:2rem;">
                            <?= __('Tidak ada data group.') ?>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($progress as $r): ?>
                        <?php
                        $rAmt        = (float) ($r['diff_amount'] ?? 0);
                        $rQty        = (int) ($r['diff_qty'] ?? 0);
                        $isRecount   = (int) ($r['recount_count'] ?? 0) > 0;
                        $amtClass    = $rAmt < 0 ? 'is-negative' : ($rAmt > 0 ? 'is-positive' : 'is-zero');
                        $amtSign     = $rAmt > 0 ? '+' : '';
                        $groupLink   = url('items?col[group][]=' . rawurlencode((string) $r['name']));
                        if ($isRecount) {
                            $groupLink = url('items?col[group][]=' . rawurlencode((string) $r['name']) . '&count_status[]=recount');
                        }
                        ?>
                        <tr class="sf-summary-row <?= $isRecount ? 'is-recount' : '' ?>"
                            data-group-id="<?= (int) $r['id'] ?>"
                            data-group-name="<?= strtolower(e($r['name'])) ?>"
                            data-group-code="<?= strtolower(e($r['code'])) ?>"
                            data-diff-amount="<?= $rAmt ?>"
                            data-diff-qty="<?= $rQty ?>"
                            data-is-recount="<?= $isRecount ? '1' : '0' ?>">
                            <td>
                                <div class="sf-group-cell">
                                    <a href="<?= $groupLink ?>" class="sf-group-name" title="<?= __('Klik untuk melihat item group ini') ?>">
                                        <?= e($r['name']) ?>
                                    </a>
                                    <span class="sf-group-code"><?= e($r['code']) ?></span>
                                    <?php if ($isRecount): ?>
                                        <span class="sf-recount-badge" title="<?= __('Group ini sedang dalam proses recount / cek ulang fisik') ?>">
                                            <?= icon('alert-circle') ?> <?= __('Sedang Recount') ?> (<?= (int) $r['recount_count'] ?>)
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td class="col-hide-mobile">
                                <?php if ($isRecount): ?>
                                    <span class="badge badge-warning" style="font-size:0.75rem;padding:0.2rem 0.55rem;">
                                        <?= icon('clock') ?> <?= __('Recount') ?>
                                    </span>
                                <?php elseif ($rAmt !== 0.0): ?>
                                    <span class="badge <?= $rAmt < 0 ? 'badge-danger' : 'badge-success' ?>" style="font-size:0.75rem;padding:0.2rem 0.55rem;">
                                        <?= $rAmt < 0 ? __('Defisit') : __('Surplus') ?>
                                    </span>
                                <?php else: ?>
                                    <span class="badge badge-neutral" style="font-size:0.75rem;padding:0.2rem 0.55rem;">
                                        <?= icon('check') ?> <?= __('Seimbang') ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="t-right col-hide-mobile mono tabular" style="font-size:0.85rem;color:var(--text-2,#64748b);">
                                <?= ($rQty > 0 ? '+' : '') . number_format($rQty, 0, ',', '.') ?> pcs
                            </td>
                            <td class="sf-amount-cell <?= $amtClass ?>" title="Selisih Nilai: <?= format_rupiah($rAmt, true) ?>">
                                <?php if ($canSeeAmt): ?>
                                    <?= $amtSign . number_format($rAmt, 2, ',', '.') ?>
                                <?php else: ?>
                                    <span class="faint" title="<?= __('Nilai dirahasiakan (blind count)') ?>">***</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                <tr id="sf-summary-no-match" style="display:none;">
                    <td colspan="4" class="t-center muted" style="padding:2rem;">
                        <?= icon('alert-circle') ?> <?= __('Tidak ada group yang cocok dengan filter.') ?>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- Summary Footer Row -->
    <div class="sf-summary-foot">
        <div class="flex items-center gap-md wrap">
            <span class="text-xs muted" id="sf-summary-visible-count">
                <?= __('Menampilkan') ?> <strong><?= count($progress) ?></strong> <?= __('group') ?>
            </span>
            <span class="text-xs muted col-hide-mobile">&middot;</span>
            <span class="text-xs muted col-hide-mobile">
                <?= __('Surplus:') ?> <strong class="text-success mono" style="font-variant-numeric:tabular-nums;"><?= $canSeeAmt ? ('+' . format_rupiah($summaryTotalPlusAmt)) : '***' ?></strong>
            </span>
            <span class="text-xs muted col-hide-mobile">&middot;</span>
            <span class="text-xs muted col-hide-mobile">
                <?= __('Defisit:') ?> <strong class="text-danger mono" style="font-variant-numeric:tabular-nums;"><?= $canSeeAmt ? ('-' . format_rupiah(abs($summaryTotalMinusAmt))) : '***' ?></strong>
            </span>
        </div>
        <div class="flex items-center gap-xs">
            <span class="text-xs faint"><?= __('Total Selisih Net:') ?></span>
            <span class="mono font-bold <?= $summaryTotalDiffAmt < 0 ? 'text-danger' : ($summaryTotalDiffAmt > 0 ? 'text-success' : 'muted') ?>"
                  id="sf-summary-net-total"
                  style="font-size:0.95rem;font-variant-numeric:tabular-nums;">
                <?= $canSeeAmt ? (($summaryTotalDiffAmt > 0 ? '+' : '') . format_rupiah($summaryTotalDiffAmt)) : '***' ?>
            </span>
        </div>
    </div>
</div>

<script>
(function() {
    var searchInput = document.getElementById('sf-summary-search');
    var filterChips = document.querySelectorAll('.sf-summary-chip');
    var sortSelect  = document.getElementById('sf-summary-sort');
    var tbody       = document.getElementById('sf-summary-tbody');
    var noMatchRow  = document.getElementById('sf-summary-no-match');
    var countLabel  = document.getElementById('sf-summary-visible-count');
    var btnSortName = document.getElementById('sf-btn-sort-name');
    var btnSortAmt  = document.getElementById('sf-btn-sort-amt');

    if (!tbody) return;

    var activeFilter = 'all';
    var sortDirName  = 1;
    var sortDirAmt   = -1;

    function updateView() {
        var term = (searchInput ? searchInput.value : '').trim().toLowerCase();
        var rows = Array.from(tbody.querySelectorAll('.sf-summary-row'));
        var visibleCount = 0;

        rows.forEach(function(row) {
            var name = row.getAttribute('data-group-name') || '';
            var code = row.getAttribute('data-group-code') || '';
            var amt  = parseFloat(row.getAttribute('data-diff-amount') || '0');
            var isRecount = row.getAttribute('data-is-recount') === '1';

            var matchSearch = !term || name.indexOf(term) !== -1 || code.indexOf(term) !== -1;
            var matchFilter = true;

            if (activeFilter === 'diff') {
                matchFilter = amt !== 0;
            } else if (activeFilter === 'deficit') {
                matchFilter = amt < 0;
            } else if (activeFilter === 'surplus') {
                matchFilter = amt > 0;
            } else if (activeFilter === 'recount') {
                matchFilter = isRecount;
            }

            if (matchSearch && matchFilter) {
                row.style.display = '';
                visibleCount++;
            } else {
                row.style.display = 'none';
            }
        });

        if (noMatchRow) {
            noMatchRow.style.display = visibleCount === 0 ? '' : 'none';
        }

        if (countLabel) {
            countLabel.innerHTML = 'Menampilkan <strong>' + visibleCount + '</strong> group';
        }
    }

    function sortRows(mode) {
        var rows = Array.from(tbody.querySelectorAll('.sf-summary-row'));
        rows.sort(function(a, b) {
            var nameA = a.getAttribute('data-group-name') || '';
            var nameB = b.getAttribute('data-group-name') || '';
            var amtA  = parseFloat(a.getAttribute('data-diff-amount') || '0');
            var amtB  = parseFloat(b.getAttribute('data-diff-amount') || '0');

            if (mode === 'name_asc') {
                return nameA.localeCompare(nameB);
            } else if (mode === 'name_desc') {
                return nameB.localeCompare(nameA);
            } else if (mode === 'diff_desc') {
                return Math.abs(amtB) - Math.abs(amtA);
            } else if (mode === 'neg_first') {
                return amtA - amtB;
            } else if (mode === 'pos_first') {
                return amtB - amtA;
            }
            return 0;
        });

        rows.forEach(function(row) {
            tbody.appendChild(row);
        });
        if (noMatchRow) {
            tbody.appendChild(noMatchRow);
        }
    }

    if (searchInput) {
        searchInput.addEventListener('input', updateView);
    }

    filterChips.forEach(function(chip) {
        chip.addEventListener('click', function() {
            filterChips.forEach(function(c) { c.classList.remove('is-active'); });
            this.classList.add('is-active');
            activeFilter = this.getAttribute('data-filter') || 'all';
            updateView();
        });
    });

    if (sortSelect) {
        sortSelect.addEventListener('change', function() {
            sortRows(this.value);
            updateView();
        });
    }

    if (btnSortName) {
        btnSortName.addEventListener('click', function(e) {
            e.preventDefault();
            sortDirName = -sortDirName;
            var mode = sortDirName > 0 ? 'name_asc' : 'name_desc';
            if (sortSelect) sortSelect.value = mode;
            sortRows(mode);
            updateView();
        });
    }

    if (btnSortAmt) {
        btnSortAmt.addEventListener('click', function(e) {
            e.preventDefault();
            sortDirAmt = -sortDirAmt;
            var mode = sortDirAmt < 0 ? 'neg_first' : 'pos_first';
            if (sortSelect) sortSelect.value = mode;
            sortRows(mode);
            updateView();
        });
    }
})();
</script>


<!-- ====================================================================== -->
<!-- Status per Group: selesai / proses / audit / pending (dengan tab)      -->
<!-- ====================================================================== -->
<?php
$minPercent = (int) config('app.audit.min_percent', 10);
$groupById = [];
foreach ($groups as $g) { $groupById[(string) $g['name']] = (int) $g['id']; }

$stDone = []; $stCounting = []; $stPending = [];
foreach ($progress as $row) {
    $t = (int) $row['total'];
    $c = (int) $row['counted'];
    $a = (int) $row['audited'];
    $needed = $t > 0 ? max(1, (int) ceil($t * $minPercent / 100)) : 0;
    $item = [
        'id' => $groupById[(string) $row['name']] ?? 0,
        'name' => (string) $row['name'], 'code' => (string) $row['code'],
        'total' => $t, 'counted' => $c, 'audited' => $a, 'needed' => $needed,
        'cpct' => (float) $row['count_percent'], 'apct' => (float) $row['audit_percent'],
    ];
    if ($t > 0 && $a >= $needed && $a > 0) { $stDone[] = $item; }
    elseif ($c > 0) { $stCounting[] = $item; }
    else { $stPending[] = $item; }
}
$statusBuckets = [
    'done'     => ['label' => 'Selesai', 'hint' => 'Audit memenuhi target', 'cls' => 'is-done', 'rows' => $stDone],
    'counting' => ['label' => 'Proses',  'hint' => 'Sedang dihitung',       'cls' => 'is-doing', 'rows' => $stCounting],
    'pending'  => ['label' => 'Pending', 'hint' => 'Belum dihitung',        'cls' => 'is-idle',  'rows' => $stPending],
];
$statusIcon = static fn (string $k): string => match ($k) {
    'done' => 'check-circle', 'counting' => 'clock', default => 'alert-circle',
};
?>
<div class="card mt-2">
    <div class="card-header flex flex-wrap gap-md items-center justify-between">
        <div>
            <h2 style="font-size:1.02rem;margin:0;"><?= icon('layers') ?> Status Group</h2>
            <p class="text-xs muted mt-1" style="margin:0;">Kelompokkan group berdasarkan kondisi: selesai audit, sedang diproses, atau masih pending.</p>
        </div>
        <div class="sf-tabs" role="tablist" aria-label="<?= __('Filter status group') ?>">
            <?php $first = true; foreach ($statusBuckets as $k => $b): ?>
                <button type="button" class="tab-pill<?= $first ? ' is-active' : '' ?>" role="tab"
                        aria-selected="<?= $first ? 'true' : 'false' ?>" data-tab="<?= $k ?>">
                    <?= icon($statusIcon($k)) ?> <?= e($b['label']) ?>
                    <span class="tab-count"><?= count($b['rows']) ?></span>
                </button>
            <?php $first = false; endforeach; ?>
        </div>
    </div>
    <div class="card-body">
        <?php $first = true; foreach ($statusBuckets as $k => $b): ?>
            <div class="sf-tabpanel" data-panel="<?= $k ?>"<?= $first ? '' : ' hidden' ?>>
                <?php if ($b['rows'] === []): ?>
                    <p class="faint text-sm mb-0"><?= __('Tidak ada group pada kondisi ini.') ?></p>
                <?php else: ?>
                    <div class="sf-status-grid">
                        <?php foreach ($b['rows'] as $it): ?>
                            <a class="sf-status-item" href="<?= url('items?col[group][]=' . rawurlencode($it['name'])) ?>" style="text-decoration:none;color:inherit;">
                                <span class="sf-status-dot" style="background:var(--<?= $k === 'done' ? 'success' : ($k === 'counting' ? 'warning' : 'text-3') ?>)"></span>
                                <span class="sf-status-body">
                                    <span class="sf-status-name"><?= e($it['name']) ?></span>
                                    <span class="sf-status-meta"><?= $it['counted'] ?>/<?= $it['total'] ?> dihitung &middot; <?= $it['audited'] ?>/<?= $it['needed'] ?> audit</span>
                                </span>
                                <span class="sf-status-badge <?= $b['cls'] ?>"><?= $b['label'] ?></span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php $first = false; endforeach; ?>
    </div>
</div>

<div class="grid grid-main mt-2">
    <!-- Grafik -->
    <div class="card lift">
        <div class="card-header">
            <h2><?= icon('chart') ?> Grafik Progres</h2>
            <div class="spacer"></div>
            <span class="badge badge-brand"><?= $selectedGroup ? __('Filter group') : __('Semua group') ?></span>
        </div>
        <div class="card-body">
            <div class="donut-wrap mb-2">
                <div class="chart-box doughnut">
                    <canvas id="chart-doughnut" height="220" aria-label="<?= __('Grafik progres') ?>"></canvas>
                    <div style="position:absolute;inset:0;display:grid;place-items:center;pointer-events:none;">
                        <div style="text-align:center;">
                            <div id="chart-doughnut-pct" style="font-size:1.5rem;font-weight:750;letter-spacing:-.03em;"><?= $countPercent ?>%</div>
                            <div class="text-xs faint"><?= __('dihitung') ?></div>
                        </div>
                    </div>
                </div>
                <div class="legend">
                    <div class="legend-item"><span class="legend-dot" style="background:var(--success)"></span> Sudah dihitung: <strong><?= $counted ?></strong></div>
                    <div class="legend-item"><span class="legend-dot" style="background:var(--surface-3)"></span> Belum dihitung: <strong><?= $notCounted ?></strong></div>
                    <div class="legend-item"><span class="legend-dot" style="background:var(--info)"></span> Sudah diaudit: <strong><?= $audited ?></strong></div>
                    <div class="legend-item"><span class="legend-dot" style="background:var(--warning)"></span> Belum diaudit: <strong><?= (int) $summary['not_audited'] ?></strong></div>
                </div>
            </div>

            <div class="divider"></div>

            <h3 class="mb-2"><?= __('Progres per group') ?></h3>
            <?php if ($progress === []): ?>
                <div class="empty-state">
                    <div class="empty-icon"><?= icon('layers') ?></div>
                    <h3><?= __('Belum ada group') ?></h3>
                    <p><?= __('Tambahkan group terlebih dahulu melalui menu Group.') ?></p>
                </div>
            <?php else: ?>
                <div class="chart-scroll">
                    <div class="chart-box" style="height:<?= max(220, min(count($progress), 25) * 34) ?>px;">
                        <canvas id="chart-groups" aria-label="<?= __('Grafik progres per group') ?>"></canvas>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Sisi kanan -->
    <div>
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
</div>
