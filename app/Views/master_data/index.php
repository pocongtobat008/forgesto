<?php
/**
 * View Master Data:
 * Menampilkan seluruh data master inventaris barang (semua tahapan perhitungan & audit).
 *
 * @var array $items
 * @var array $filters
 * @var array $groups
 * @var array $locations
 * @var array $summary
 * @var float $totalSystemValue
 * @var float $totalCountedValue
 * @var bool  $canSeeStockQty
 * @var bool  $showAll
 */

$countBadge = static fn (string $status): string => match ($status) {
    'pending'    => 'badge-danger',
    'counted'    => 'badge-warning',
    'posted'     => 'badge-info',
    'submitted'  => 'badge-purple',
    'audited'    => 'badge-cyan',
    'reconciled' => 'badge-success',
    default      => '',
};

$countLabel = static fn (string $status): string => match ($status) {
    'pending'    => __('Belum dihitung'),
    'counted'    => __('Sudah dihitung'),
    'posted'     => __('Terposting'),
    'submitted'  => __('Di audit'),
    'audited'    => __('Diaudit'),
    'reconciled' => __('Selesai'),
    default      => ucfirst($status),
};

$auditBadge = static fn (string $status): string => match ($status) {
    'pending'     => '',
    'audited'     => 'badge-success',
    'discrepancy' => 'badge-danger',
    'resolved'    => 'badge-brand',
    'recount'     => 'badge-warning',
    default       => '',
};

$auditLabel = static fn (string $status): string => match ($status) {
    'pending'     => __('Belum diaudit'),
    'audited'     => __('Diaudit'),
    'discrepancy' => __('Ada selisih'),
    'resolved'    => __('Selesai'),
    'recount'     => __('Cek ulang'),
    default       => ucfirst($status),
};

// URL query string untuk export & filter
$__xfQs = [];
foreach ((array) ($filters['col'] ?? []) as $__k => $__vals) {
    foreach ((array) $__vals as $__v) {
        $__xfQs[] = 'col[' . rawurlencode($__k) . '][]=' . rawurlencode((string) $__v);
    }
}
foreach ((array) ($filters['adv'] ?? []) as $__i => $__c) {
    foreach (['col', 'op', 'val'] as $__f) {
        $__xfQs[] = 'adv[' . $__i . '][' . $__f . ']=' . rawurlencode((string) ($__c[$__f] ?? ''));
    }
}
if (! empty($filters['search'])) {
    $__xfQs[] = 'search=' . rawurlencode($filters['search']);
}
foreach (['group_id', 'location_id', 'count_status', 'audit_status', 'locked', 'sort'] as $k) {
    if (! empty($filters[$k])) {
        if (is_array($filters[$k])) {
            foreach ($filters[$k] as $v) $__xfQs[] = $k . '[]=' . rawurlencode((string) $v);
        } else {
            $__xfQs[] = $k . '=' . rawurlencode((string) $filters[$k]);
        }
    }
}
$activeQs = $__xfQs ? '?' . implode('&', $__xfQs) : '';
$exportUrl = url('master-data/export.xlsx' . $activeQs);

$curCountStatus = is_array($filters['count_status'] ?? null)
    ? ($filters['count_status'][0] ?? '')
    : (string) ($filters['count_status'] ?? '');

$curAuditStatus = is_array($filters['audit_status'] ?? null)
    ? ($filters['audit_status'][0] ?? '')
    : (string) ($filters['audit_status'] ?? '');
?>

<div class="page-head">
    <div>
        <nav class="breadcrumb" aria-label="<?= __('Navigasi') ?>">
            <a href="<?= url('dashboard') ?>"><?= __('Beranda') ?></a>
            <span class="sep">/</span>
            <span><?= __('Data Master') ?></span>
            <span class="sep">/</span>
            <span aria-current="page"><?= __('Master Data') ?></span>
        </nav>
        <h1><?= __('Master Data Barang') ?></h1>
        <p class="text-secondary"><?= __('Seluruh inventaris barang: status fisik, stok sistem, dan progres audit lengkap.') ?></p>
    </div>
</div>

<!-- ====================================================================== -->
<!-- 1. KPI Cards Ringkasan (Harmonisasi dengan Menu Lain)                  -->
<!-- ====================================================================== -->
<div class="grid <?= $canSeeStockQty ? 'grid-5 grid-tablet-3' : 'grid-4 grid-tablet-4' ?> mb-3">
    <div class="stat is-brand" style="--i:0">
        <div class="stat-icon"><?= icon('package') ?></div>
        <span class="stat-label"><?= __('Total Barang') ?></span>
        <div class="stat-value mono tabular"><?= number_format((int) ($summary['total'] ?? 0), 0, ',', '.') ?></div>
        <div class="stat-meta"><?= __('Semua data terdaftar') ?></div>
    </div>
    <div class="stat is-danger" style="--i:1">
        <div class="stat-icon"><?= icon('alert-circle') ?></div>
        <span class="stat-label"><?= __('Belum Dihitung') ?></span>
        <div class="stat-value mono tabular"><?= number_format((int) ($summary['not_counted'] ?? 0), 0, ',', '.') ?></div>
        <div class="stat-meta"><?= percent((int) ($summary['not_counted'] ?? 0), (int) ($summary['total'] ?? 1)) ?>% <?= __('menunggu fisik') ?></div>
    </div>
    <div class="stat is-success" style="--i:2">
        <div class="stat-icon"><?= icon('check') ?></div>
        <span class="stat-label"><?= __('Sudah Dihitung') ?></span>
        <div class="stat-value mono tabular"><?= number_format((int) (($summary['counted'] ?? 0) + ($summary['posted'] ?? 0)), 0, ',', '.') ?></div>
        <div class="stat-meta"><?= (int) ($summary['counted'] ?? 0) ?> hitung &bull; <?= (int) ($summary['posted'] ?? 0) ?> terposting</div>
    </div>
    <div class="stat is-info" style="--i:3">
        <div class="stat-icon"><?= icon('clipboard-check') ?></div>
        <span class="stat-label"><?= __('Diaudit / Selesai') ?></span>
        <div class="stat-value mono tabular"><?= number_format((int) (($summary['audited'] ?? 0) + ($summary['resolved'] ?? 0)), 0, ',', '.') ?></div>
        <div class="stat-meta"><?= (int) ($summary['audited'] ?? 0) ?> diaudit &bull; <?= (int) ($summary['resolved'] ?? 0) ?> tuntas</div>
    </div>
    <?php if ($canSeeStockQty): ?>
    <div class="stat is-warning" style="--i:4">
        <div class="stat-icon"><?= icon('coins') ?></div>
        <span class="stat-label"><?= __('Nilai Stok Sistem') ?></span>
        <div class="stat-value mono tabular" title="<?= e(format_rupiah($totalSystemValue)) ?>">
            <?= format_rupiah_compact($totalSystemValue) ?>
        </div>
        <div class="stat-meta"><?= __('Fisik: ') ?><strong class="mono"><?= format_rupiah_compact($totalCountedValue) ?></strong></div>
    </div>
    <?php endif; ?>
</div>

<!-- ====================================================================== -->
<!-- 2. Quick Filter Chips & Toolbar                                        -->
<!-- ====================================================================== -->
<div class="tab-pills-wrap mb-3" style="display:flex;gap:.5rem;flex-wrap:wrap;align-items:center;">
    <a href="<?= url('master-data') ?>" class="tab-pill <?= empty($filters['count_status']) && empty($filters['audit_status']) ? 'is-active' : '' ?>">
        <?= __('Semua Data') ?> <span class="tab-count"><?= (int) ($summary['total'] ?? 0) ?></span>
    </a>
    <a href="<?= url('master-data?count_status[]=pending') ?>" class="tab-pill <?= $curCountStatus === 'pending' ? 'is-active' : '' ?>">
        <?= __('Belum dihitung') ?> <span class="tab-count"><?= (int) ($summary['not_counted'] ?? 0) ?></span>
    </a>
    <a href="<?= url('master-data?count_status[]=counted') ?>" class="tab-pill <?= $curCountStatus === 'counted' ? 'is-active' : '' ?>">
        <?= __('Sudah dihitung') ?> <span class="tab-count"><?= (int) ($summary['counted'] ?? 0) ?></span>
    </a>
    <a href="<?= url('master-data?count_status[]=posted') ?>" class="tab-pill <?= $curCountStatus === 'posted' ? 'is-active' : '' ?>">
        <?= __('Terposting') ?> <span class="tab-count"><?= (int) ($summary['posted'] ?? 0) ?></span>
    </a>
    <a href="<?= url('master-data?count_status[]=audited') ?>" class="tab-pill <?= $curCountStatus === 'audited' ? 'is-active' : '' ?>">
        <?= __('Diaudit') ?> <span class="tab-count"><?= (int) ($summary['audited'] ?? 0) ?></span>
    </a>
    <a href="<?= url('master-data?audit_status[]=resolved') ?>" class="tab-pill <?= $curAuditStatus === 'resolved' ? 'is-active' : '' ?>">
        <?= __('Selesai') ?> <span class="tab-count"><?= (int) ($summary['resolved'] ?? 0) ?></span>
    </a>
    <?php if ((int) ($summary['discrepancy'] ?? 0) > 0): ?>
    <a href="<?= url('master-data?audit_status[]=discrepancy') ?>" class="tab-pill <?= $curAuditStatus === 'discrepancy' ? 'is-active' : '' ?>" style="color:var(--danger,#e53e3e);">
        <?= __('Ada Selisih') ?> <span class="tab-count"><?= (int) $summary['discrepancy'] ?></span>
    </a>
    <?php endif; ?>
</div>

<div class="card mb-3" style="padding:1rem;">
    <form method="get" action="<?= url('master-data') ?>" id="master-filter" class="table-toolbar" style="display:flex;gap:.75rem;flex-wrap:wrap;align-items:center;">
        <div class="search-input-wrap" style="flex:1;min-width:220px;max-width:360px;">
            <input type="search" name="search" class="input" placeholder="<?= __('Cari kode, nama, kategori...') ?>" value="<?= e((string) ($filters['search'] ?? '')) ?>" aria-label="<?= __('Pencarian') ?>">
        </div>

        <select name="group_id[]" class="select" style="min-width:140px;" aria-label="<?= __('Filter Group') ?>" onchange="this.form.submit()">
            <option value=""><?= __('Semua Group') ?></option>
            <?php foreach ($groups as $g): ?>
                <?php $sel = in_array((int) $g['id'], array_map('intval', (array) ($filters['group_id'] ?? [])), true); ?>
                <option value="<?= (int) $g['id'] ?>" <?= $sel ? 'selected' : '' ?>><?= e($g['name']) ?></option>
            <?php endforeach; ?>
        </select>

        <select name="count_status[]" class="select" style="min-width:140px;" aria-label="<?= __('Status Hitung') ?>" onchange="this.form.submit()">
            <option value=""><?= __('Semua Status Hitung') ?></option>
            <?php foreach (['pending' => 'Belum dihitung', 'counted' => 'Sudah dihitung', 'posted' => 'Terposting', 'submitted' => 'Di audit', 'audited' => 'Diaudit', 'reconciled' => 'Selesai'] as $st => $lbl): ?>
                <option value="<?= $st ?>" <?= $curCountStatus === $st ? 'selected' : '' ?>><?= e(__($lbl)) ?></option>
            <?php endforeach; ?>
        </select>

        <select name="audit_status[]" class="select" style="min-width:140px;" aria-label="<?= __('Status Audit') ?>" onchange="this.form.submit()">
            <option value=""><?= __('Semua Status Audit') ?></option>
            <?php foreach (['pending' => 'Belum diaudit', 'audited' => 'Diaudit', 'discrepancy' => 'Ada selisih', 'resolved' => 'Selesai', 'recount' => 'Cek ulang'] as $st => $lbl): ?>
                <option value="<?= $st ?>" <?= $curAuditStatus === $st ? 'selected' : '' ?>><?= e(__($lbl)) ?></option>
            <?php endforeach; ?>
        </select>

        <button type="submit" class="btn btn-primary"><?= icon('search') ?> <span><?= __('Cari') ?></span></button>


        <!-- Toolbar Rata Kanan: Filter, Clear, Kolom & Export (Konsisten Data STO) -->
        <div class="table-toolbar" style="margin-left:auto;display:flex;gap:.55rem;align-items:center;">
            <button type="button" class="btn btn-info btn-icon-only" id="master-adv-btn" aria-label="<?= __('Buka pencarian lanjutan') ?>" title="<?= __('Pencarian lanjutan') ?>"><?= icon('filter') ?></button>
            <?php if (! empty($filters['search']) || ! empty($filters['group_id']) || ! empty($filters['count_status']) || ! empty($filters['audit_status']) || ! empty($filters['col']) || ! empty($filters['adv'])): ?>
                <a href="<?= url('master-data') ?>" class="btn btn-ghost btn-icon-only col-clear-all" aria-label="<?= __('Hapus semua filter') ?>" title="<?= __('Hapus semua filter') ?>"><?= icon('x') ?></a>
            <?php endif; ?>
            <div class="col-mgr" id="master-colmgr">
                <button type="button" class="btn btn-ghost btn-icon-only" id="master-colmgr-btn" aria-expanded="false" aria-label="<?= __('Sesuaikan kolom') ?>" title="<?= __('Sesuaikan kolom') ?>">
                    <?= icon('layers') ?>
                </button>
                <div class="col-mgr-panel" id="master-colmgr-panel" hidden></div>
            </div>

            <?php if (can('export_data')): ?>
            <a href="<?= $exportUrl ?>" class="btn btn-brand btn-icon-only" aria-label="<?= __('Unduh data Excel (.xlsx)') ?>" title="<?= __('Unduh data Excel (.xlsx)') ?>">
                <?= icon('download') ?>
            </a>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- ====================================================================== -->
<!-- 3. Tabel Master Data                                                   -->
<!-- ====================================================================== -->
<div class="card">
    <div class="table-wrap">
<?php
$cfCol = $filters['col'] ?? [];
$xfBtn = static fn (string $k, string $lbl): string => '<button type="button" class="xf-btn' . (! empty($cfCol[$k]) ? ' is-active' : '') . '" data-xf="' . $k . '" aria-label="' . e(__( 'Filter kolom :l', ['l' => __($lbl)])) . '" aria-haspopup="dialog" title="Filter">' . icon('filter') . '</button>';
?>
        <table class="data" id="master-table" data-colmgr-key="master-data-v1" data-colmgr-prefix="master">
            <thead>
                <tr>
                    <th style="width:44px;" class="t-center" data-col="no">#</th>
                    <th data-col="code" class="xf-th"><span class="xf-label"><?= __('Kode') ?></span><?= $xfBtn('code', 'Kode') ?></th>
                    <th data-col="name" class="xf-th"><span class="xf-label"><?= __('Nama Item') ?></span><?= $xfBtn('name', 'Nama Item') ?></th>
                    <th data-col="group" class="xf-th"><span class="xf-label"><?= __('Group') ?></span><?= $xfBtn('group', 'Group') ?></th>
                    <th data-col="location" class="col-hide-tablet xf-th"><span class="xf-label"><?= __('Lokasi') ?></span><?= $xfBtn('location', 'Lokasi') ?></th>
                    <th data-col="category" class="col-hide-tablet xf-th"><span class="xf-label"><?= __('Kategori') ?></span><?= $xfBtn('category', 'Kategori') ?></th>
                    <th data-col="unit" class="col-hide-tablet t-center xf-th"><span class="xf-label"><?= __('Satuan') ?></span><?= $xfBtn('unit', 'Satuan') ?></th>
                    <th data-col="price" class="col-hide-tablet t-right xf-th"><span class="xf-label"><?= __('Harga') ?></span><?= $xfBtn('price', 'Harga') ?></th>
                    <?php if ($canSeeStockQty): ?>
                    <th data-col="expected" class="t-center mono xf-th"><span class="xf-label"><?= __('Stok Sistem') ?></span><?= $xfBtn('expected', 'Stok Sistem') ?></th>
                    <?php endif; ?>
                    <th data-col="counted" class="t-center mono xf-th"><span class="xf-label"><?= __('Hitung') ?></span><?= $xfBtn('counted', 'Hitung') ?></th>
                    <?php if ($canSeeStockQty): ?>
                    <th data-col="diff" class="t-center mono xf-th"><span class="xf-label"><?= __('Selisih') ?></span><?= $xfBtn('diff', 'Selisih') ?></th>
                    <?php endif; ?>
                    <th data-col="count" class="t-center xf-th"><span class="xf-label"><?= __('Status Hitung') ?></span><?= $xfBtn('count', 'Status Hitung') ?></th>
                    <th data-col="audit" class="t-center xf-th"><span class="xf-label"><?= __('Status Audit') ?></span><?= $xfBtn('audit', 'Status Audit') ?></th>
                    <th data-col="locked" class="t-center xf-th" style="width:75px;"><span class="xf-label"><?= __('Kunci') ?></span><?= $xfBtn('locked', 'Kunci') ?></th>
                    <th data-col="aksi" class="t-right" style="width:80px;"><?= __('Aksi') ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if ($items['items'] === []): ?>
                    <tr>
                        <td colspan="<?= 13 + ($canSeeStockQty ? 2 : 0) ?>">
                            <div class="empty-state" style="padding:2.5rem;text-align:center;">
                                <div class="empty-icon" style="margin:0 auto .75rem;"><?= icon('package') ?></div>
                                <h3><?= __('Tidak ada data') ?></h3>
                                <p class="text-secondary"><?= __('Tidak ditemukan barang sesuai kriteria filter saat ini.') ?></p>
                            </div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($items['items'] as $idx => $row): ?>
                        <?php
                        $exp = (int) ($row['expected_qty'] ?? 0);
                        $cnt = $row['counted_qty'] !== null ? (int) $row['counted_qty'] : null;
                        $diff = $cnt !== null ? ($cnt - $exp) : null;
                        $rowNo = ($items['from'] ?? 1) + $idx;
                        $cSt = (string) ($row['count_status'] ?? 'pending');
                        $aSt = (string) ($row['audit_status'] ?? 'pending');
                        $isLocked = (int) ($row['is_locked'] ?? 0) === 1;
                        ?>
                        <tr class="master-row">
                            <td class="t-center text-secondary mono" data-col="no"><?= $rowNo ?></td>
                            <td data-label="<?= __('Kode') ?>" data-col="code">
                                <a href="<?= url('items/' . (int) $row['id']) ?>" class="code-badge mono" style="text-decoration:none;">
                                    <?= e($row['code']) ?>
                                </a>
                            </td>
                            <td data-label="<?= __('Nama Item') ?>" data-col="name">
                                <strong><?= e($row['name']) ?></strong>
                            </td>
                            <td data-label="<?= __('Group') ?>" data-col="group">
                                <?= e($row['group_name'] ?: '-') ?>
                            </td>
                            <td data-label="<?= __('Lokasi') ?>" class="col-hide-tablet text-secondary" data-col="location">
                                <?= e($row['location_name'] ?: '-') ?>
                            </td>
                            <td data-label="<?= __('Kategori') ?>" class="col-hide-tablet text-secondary" data-col="category">
                                <?= e($row['category'] ?: '-') ?>
                            </td>
                            <td data-label="<?= __('Satuan') ?>" class="col-hide-tablet t-center mono" data-col="unit">
                                <?= e($row['unit'] ?: 'UNIT') ?>
                            </td>
                            <td data-label="<?= __('Harga') ?>" class="col-hide-tablet t-right mono tabular" data-col="price">
                                <?= format_rupiah((float) ($row['unit_price'] ?? 0)) ?>
                            </td>

                            <!-- Stok Sistem (Protected) -->
                            <?php if ($canSeeStockQty): ?>
                            <td data-label="<?= __('Stok Sistem') ?>" class="t-center mono tabular" data-col="expected">
                                <?= number_format($exp, 0, ',', '.') ?>
                            </td>
                            <?php endif; ?>

                            <!-- Hasil Hitung Fisik -->
                            <td data-label="<?= __('Hitung') ?>" class="t-center mono tabular" data-col="counted">
                                <?php if ($cnt !== null): ?>
                                    <strong><?= number_format($cnt, 0, ',', '.') ?></strong>
                                <?php else: ?>
                                    <span class="faint">—</span>
                                <?php endif; ?>
                            </td>

                            <!-- Selisih (Protected) -->
                            <?php if ($canSeeStockQty): ?>
                            <td data-label="<?= __('Selisih') ?>" class="t-center mono tabular" data-col="diff">
                                <?php if ($cnt !== null): ?>
                                    <?php if ($diff === 0): ?>
                                        <span class="badge badge-success" style="font-size:.75rem;padding:2px 6px;">0</span>
                                    <?php elseif ($diff < 0): ?>
                                        <span class="badge badge-danger" style="font-size:.75rem;padding:2px 6px;"><?= $diff ?></span>
                                    <?php else: ?>
                                        <span class="badge badge-info" style="font-size:.75rem;padding:2px 6px;">+<?= $diff ?></span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="faint">—</span>
                                <?php endif; ?>
                            </td>
                            <?php endif; ?>

                            <!-- Status Hitung -->
                            <td data-label="<?= __('Status Hitung') ?>" class="t-center" data-col="count">
                                <span class="badge <?= $countBadge($cSt) ?>"><?= e($countLabel($cSt)) ?></span>
                            </td>

                            <!-- Status Audit -->
                            <td data-label="<?= __('Status Audit') ?>" class="t-center" data-col="audit">
                                <span class="badge <?= $auditBadge($aSt) ?>"><?= e($auditLabel($aSt)) ?></span>
                            </td>

                            <!-- Status Kunci -->
                            <td data-label="<?= __('Kunci') ?>" class="t-center" data-col="locked">
                                <?php if ($isLocked): ?>
                                    <span title="<?= __('Terkunci untuk input') ?>" style="color:var(--warning,#dd6b20);"><?= icon('lock') ?></span>
                                <?php else: ?>
                                    <span class="faint" title="<?= __('Terbuka') ?>"><?= icon('unlock') ?></span>
                                <?php endif; ?>
                            </td>

                            <!-- Aksi -->
                            <td data-label="<?= __('Aksi') ?>" class="t-right" data-col="aksi">
                                <a href="<?= url('items/' . (int) $row['id']) ?>" class="icon-btn" title="<?= __('Lihat Detail') ?>" aria-label="<?= __('Lihat detail :c', ['c' => $row['code']]) ?>">
                                    <?= icon('eye') ?>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- ================================================================== -->
    <!-- 4. Pagination / Footer                                             -->
    <!-- ================================================================== -->
    <div class="card-footer" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:1rem;padding:1rem;">
        <div class="text-secondary text-sm">
            <?= __('Menampilkan') ?> <?= (int) ($items['from'] ?? 0) ?> &ndash; <?= (int) ($items['to'] ?? 0) ?> <?= __('dari') ?> <?= (int) ($items['total'] ?? 0) ?> <?= __('data') ?>
        </div>

        <?php if (! $showAll && (int) ($items['last_page'] ?? 1) > 1): ?>
        <nav class="pagination" aria-label="<?= __('Paginasi') ?>" style="display:flex;gap:.25rem;align-items:center;">
            <?php
            $curPage  = (int) ($items['page'] ?? 1);
            $lastPage = (int) ($items['last_page'] ?? 1);
            $pageUrl = static function (int $p) use ($filters): string {
                $q = $_GET;
                $q['page'] = $p;
                return url('master-data') . '?' . http_build_query($q);
            };
            ?>
            <?php if ($curPage > 1): ?>
                <a href="<?= $pageUrl($curPage - 1) ?>" class="btn btn-ghost btn-sm" aria-label="<?= __('Sebelumnya') ?>">&laquo; <?= __('Prev') ?></a>
            <?php endif; ?>

            <span class="text-sm mono tabular px-2" style="padding:0 .5rem;">
                <?= $curPage ?> / <?= $lastPage ?>
            </span>

            <?php if ($curPage < $lastPage): ?>
                <a href="<?= $pageUrl($curPage + 1) ?>" class="btn btn-ghost btn-sm" aria-label="<?= __('Berikutnya') ?>"><?= __('Next') ?> &raquo;</a>
            <?php endif; ?>
        </nav>
        <?php endif; ?>

        <div>
            <?php if ($showAll): ?>
                <a href="<?= url('master-data') ?>" class="btn btn-ghost btn-sm"><?= __('Tampilkan Berhalaman') ?></a>
            <?php else: ?>
                <a href="<?= url('master-data?all=1') ?>" class="btn btn-ghost btn-sm"><?= __('Tampilkan Semua') ?></a>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="<?= asset_v('js/table-colmgr.js') ?>"></script>

<?php
// Advanced search drawer (konsisten dengan Data STO)
$asForm = 'master-filter';
$asCols = array_filter([
    'code'      => __('Kode'),
    'name'      => __('Nama Item'),
    'group'     => __('Group'),
    'location'  => __('Lokasi'),
    'category'  => __('Kategori'),
    'unit'      => __('Satuan'),
    'price'     => __('Harga'),
    'expected'  => __('Stok Sistem'),
    'counted'   => __('Hitung'),
    'count'     => __('Status Hitung'),
    'audit'     => __('Status Audit'),
], static fn ($lbl, $k) => $k !== 'expected' || can_see_stock_qty(), ARRAY_FILTER_USE_BOTH);
$asCurrent = $filters['adv'] ?? [];
$asNumeric = ['price', 'expected', 'counted'];
$asMax = 12;
$asToggleId = 'master-adv-btn';
require __DIR__ . '/../partials/advanced-search.php';
?>

<?php
$cfLabels = [
    'code' => __('Kode'), 'name' => __('Nama Item'), 'group' => __('Group'), 'location' => __('Lokasi'),
    'category' => __('Kategori'), 'unit' => __('Satuan'), 'price' => __('Harga'),
    'expected' => __('Stok Sistem'), 'counted' => __('Hitung'), 'diff' => __('Selisih'),
    'count' => __('Status Hitung'), 'audit' => __('Status Audit'), 'locked' => __('Kunci')
];
$cfDisplay = [
    'pending' => 'Belum dihitung', 'counted' => 'Sudah dihitung', 'posted' => 'Terposting', 'reconciled' => 'Selesai',
    'unaudited' => 'Belum audit', 'audited' => 'Sudah audit', 'sample' => 'Sampel', 'recheck' => 'Cek ulang',
    '1' => 'Terkunci', '0' => 'Terbuka'
];
$cfForm = 'master-filter';
$cfDistinct = url('master-data/distinct');
require __DIR__ . '/../partials/colfilter.php';
?>
