<?php
/**
 * @var array $warehouses
 */
?>
<?= page_hero() ?>
<div class="page-head has-hero">
    <div>
        <h1><?= __('Gudang / Situs') ?></h1>
        <p>Gudang adalah induk dari Group. Ganti gudang aktif di header untuk berpindah lokasi kerja (workspace).</p>
    </div>
    <div class="spacer"></div>
    <?php if (can('manage_warehouses')): ?>
        <a class="btn btn-brand" href="<?= url('warehouses/create') ?>"><?= icon('plus') ?> Tambah Gudang</a>
    <?php endif; ?>
</div>

<?php
$__whHasFilter = ! empty($filters['search']) || ! empty($filters['col']) || ! empty($filters['adv']);
$cfCol = $filters['col'] ?? [];
$xfBtn = static fn (string $k, string $lbl): string => '<button type="button" class="xf-btn' . (! empty($cfCol[$k]) ? ' is-active' : '') . '" data-xf="' . $k . '" aria-label="' . e(__('Filter kolom :l', ['l' => __($lbl)])) . '" aria-haspopup aria-haspopup="dialog" title="Filter">' . icon('filter') . '</button>';
?>

<div class="card">
    <div class="card-toolbar" style="display:flex;align-items:center;justify-content:space-between;gap:.75rem;flex-wrap:wrap;padding:.85rem 1.15rem;border-bottom:1px solid var(--border);">
        <form method="get" action="<?= url('warehouses') ?>" id="wh-filter" style="display:flex;align-items:center;flex:1;min-width:240px;gap:.65rem;margin:0;" autocomplete="off">
            <?php foreach (($filters['col'] ?? []) as $ck => $cvals): ?><?php foreach ((array) $cvals as $cv): ?><input type="hidden" name="col[<?= e($ck) ?>][]" value="<?= e($cv) ?>" class="col-hidden"><?php endforeach; ?><?php endforeach; ?>
            <div class="form-group is-search" style="flex:1;max-width:380px;margin:0;">
                <input type="search" id="w-search" name="search" value="<?= e($filters['search'] ?? '') ?>"
                       placeholder="<?= __('Cari kode, nama gudang, atau kota...') ?>"
                       data-filter-live autocomplete="off" enterkeyhint="search" style="width:100%;min-height:38px;">
            </div>
            <span class="sf-filter-count" aria-live="polite"></span>
        </form>

        <div class="table-toolbar" style="margin-left:auto;display:flex;gap:.55rem;align-items:center;">
            <button type="button" class="btn btn-info btn-icon-only" id="warehouses-adv-btn" aria-label="<?= __('Buka pencarian lanjutan') ?>" title="<?= __('Pencarian lanjutan') ?>"><?= icon('filter') ?></button>
            <?php if ($__whHasFilter): ?>
                <a class="btn btn-ghost btn-icon-only col-clear-all" href="<?= url('warehouses') ?>" aria-label="<?= __('Hapus semua filter') ?>" title="<?= __('Hapus semua filter') ?>"><?= icon('x') ?></a>
            <?php endif; ?>
            <div class="col-mgr" id="warehouses-colmgr">
                <button type="button" class="btn btn-ghost btn-icon-only" id="warehouses-colmgr-btn" aria-expanded="false" aria-label="<?= __('Sesuaikan kolom') ?>" title="<?= __('Sesuaikan kolom') ?>"><?= icon('layers') ?></button>
                <div class="col-mgr-panel" id="warehouses-colmgr-panel" hidden></div>
            </div>
        </div>
    </div>

    <?php
    /* LF-BAR: satu pencarian lanjutan (pola Data STO) */
    $__adv = $filters['adv'] ?? [];
    $asForm     = 'wh-filter';
    $asCols     = $advCols ?? [
        'code'    => __('Kode'),
        'name'    => __('Nama Gudang'),
        'city'    => __('Kota'),
        'address' => __('Alamat'),
        'status'  => __('Status'),
    ];
    $asCurrent  = $__adv;
    $asNumeric  = $advNumeric ?? [];
    $asMax      = $advMax ?? 12;
    $asToggleId = 'warehouses-adv-btn';
    require __DIR__ . '/../partials/advanced-search.php';
    ?>

    <div class="table-wrap">
        <table class="data" id="wh-table" data-colmgr-key="warehouses-v1" data-colmgr-prefix="warehouses"><caption class="sr-only">Daftar gudang</caption>
            <thead>
                <tr>
                    <th class="xf-th" data-col="code" style="width:105px;"><span class="xf-label"><?= __('Kode') ?></span><?= $xfBtn('code', 'Kode') ?></th>
                    <th class="xf-th" data-col="name"><span class="xf-label"><?= __('Nama Gudang') ?></span><?= $xfBtn('name', 'Nama Gudang') ?></th>
                    <th class="xf-th" data-col="city" style="width:115px;"><span class="xf-label"><?= __('Kota') ?></span><?= $xfBtn('city', 'Kota') ?></th>
                    <th class="col-hide-tablet xf-th" data-col="address" style="max-width:180px;"><span class="xf-label"><?= __('Alamat') ?></span><?= $xfBtn('address', 'Alamat') ?></th>
                    <th class="t-center xf-th" data-col="groups" style="width:65px;"><span class="xf-label"><?= __('Group') ?></span><?= $xfBtn('groups', 'Group') ?></th>
                    <th class="t-center xf-th" data-col="racks" style="width:55px;"><span class="xf-label"><?= __('Rak') ?></span><?= $xfBtn('racks', 'Rak') ?></th>
                    <th class="t-center xf-th" data-col="items" style="width:55px;"><span class="xf-label"><?= __('Item') ?></span><?= $xfBtn('items', 'Item') ?></th>
                    <th class="t-center xf-th" data-col="status" style="width:105px;"><span class="xf-label"><?= __('Status') ?></span><?= $xfBtn('status', 'Status') ?></th>
                    <?php if (can('manage_warehouses')): ?>
                        <th data-col="actions" class="t-right" style="width:85px;min-width:85px;"><?= __('Aksi') ?></th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php if ($warehouses === []): ?>
                    <tr>
                        <td colspan="9">
                            <div class="empty-state">
                                <div class="empty-icon"><?= icon('building') ?></div>
                                <h3><?= __('Belum ada gudang') ?></h3>
                                <p>Tambahkan gudang/situs untuk mulai memisahkan data per lokasi.</p>
                            </div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php $activeId = \App\Core\Auth::warehouseId(); ?>
                    <?php $paginator = $warehouses; $warehouses = $paginator['items']; ?>
                    <?php foreach ($warehouses as $w): ?>
                        <tr data-wh-id="<?= (int) $w['id'] ?>"
                            data-status="<?= (int) $w['is_active'] === 1 ? 'aktif' : 'nonaktif' ?>"
                            data-search="<?= e(implode(' ', array_filter([$w['code'] ?? '', $w['name'] ?? '', $w['city'] ?? '', $w['address'] ?? '']))) ?>">
                            <td data-label="<?= __('Kode') ?>" data-col="code"><span class="kbd"><?= e($w['code']) ?></span></td>
                            <td data-label="<?= __('Nama Gudang') ?>" data-col="name">
                                <div class="cell-title cell-ico">
                                    <span class="cell-ico-badge"><?= icon('building') ?></span>
                                    <?= e($w['name']) ?>
                                    <?php if ((int) $w['id'] === (int) $activeId): ?>
                                        <span class="badge badge-brand"><?= __('Workspace aktif') ?></span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td data-label="<?= __('Kota') ?>" data-col="city"><?= e($w['city'] ?: '-') ?></td>
                            <td data-label="<?= __('Alamat') ?>" data-col="address" class="col-hide-tablet">
                                <span class="text-muted"><?= e($w['address'] ?: '—') ?></span>
                            </td>
                            <td data-label="<?= __('Group') ?>" data-col="groups" class="t-center"><?= (int) $w['group_count'] ?></td>
                            <td data-label="<?= __('Rak') ?>" data-col="racks" class="t-center"><?= (int) $w['location_count'] ?></td>
                            <td data-label="<?= __('Item') ?>" data-col="items" class="t-center"><?= (int) $w['item_count'] ?></td>
                            <td data-label="<?= __('Status') ?>" data-col="status" class="t-center">
                                <span class="badge <?= (int) $w['is_active'] === 1 ? 'badge-success' : 'badge-danger' ?>">
                                    <?= (int) $w['is_active'] === 1 ? __('Aktif') : __('Nonaktif') ?>
                                </span>
                            </td>
                            <?php if (can('manage_warehouses')): ?>
                                <td class="t-right" data-label="<?= __('Aksi') ?>">
                                    <div class="btn-group" style="justify-content:flex-end;">
                                        <a class="btn btn-outline btn-xs" href="<?= url('warehouses/edit/' . (int) $w['id']) ?>" aria-label="Edit gudang <?= e($w['name']) ?>"><?= icon('edit') ?></a>
                                        <form method="post" action="<?= url('warehouses/' . (int) $w['id'] . '/delete') ?>"
                                              data-confirm="<?= e(__('Hapus gudang :name?', ['name' => $w['name']])) ?>" style="display:inline;">
                                            <?= csrf_field() ?>
                                            <button type="submit" class="btn btn-danger btn-xs" aria-label="Hapus gudang <?= e($w['name']) ?>"><?= icon('trash') ?></button>
                                        </form>
                                    </div>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?= \App\Core\View::partial('partials/pagination', ['paginator' => $paginator, 'query' => array_diff_key($_GET, ['page' => 1])]) ?>
</div>

<script>
(function () {
    var form = document.getElementById('wh-filter');
    var table = document.getElementById('wh-table');
    if (!form || !table || !table.tBodies[0]) return;

    var tbody = table.tBodies[0];
    var q = document.getElementById('w-search');
    var count = form.querySelector('.sf-filter-count');
    var rows = Array.prototype.filter.call(tbody.rows, function (r) { return r.getAttribute('data-wh-id'); });

    function norm(s) { return (s || '').toLowerCase().replace(/\s+/g, ' ').trim(); }
    rows.forEach(function (r) { if (!r.dataset.wblob) r.dataset.wblob = norm(r.getAttribute('data-search') || r.textContent); });

    function apply() {
        var term = norm(q ? q.value : '');
        var vis = 0;
        rows.forEach(function (r) {
            var blob = r.dataset.wblob || '';
            var ok = (!term || blob.indexOf(term) !== -1);
            if (window.sfRowVisible) { window.sfRowVisible(r, 'warehouses', ok); } else { r.style.display = ok ? '' : 'none'; }
            if (ok) vis++;
        });
        if (count) count.textContent = term ? (vis + ' / ' + rows.length + ' gudang') : '';
        var ph = tbody.querySelector('tr.gf-empty');
        if (rows.length && vis === 0) {
            if (!ph) {
                ph = document.createElement('tr');
                ph.className = 'gf-empty';
                var cols = table.tHead ? table.tHead.rows[0].cells.length : 9;
                ph.innerHTML = '<td colspan="' + cols + '"><div class="empty-state"><h3>Tidak ada gudang cocok</h3><p>Ubah kata kunci atau filter yang dipakai.</p></div></td>';
                tbody.appendChild(ph);
            }
        } else if (ph) { ph.remove(); }
    }

    var t;
    if (q) q.addEventListener('input', function () { clearTimeout(t); t = setTimeout(apply, 140); });
    form.addEventListener('submit', function (e) { e.preventDefault(); apply(); });
    apply();
})();
</script>
<?php
$cfLabels = ['code' => __('Kode'), 'name' => __('Nama Gudang'), 'city' => __('Kota'), 'address' => __('Alamat'), 'groups' => __('Group'), 'racks' => __('Rak'), 'items' => __('Item'), 'status' => __('Status')];
$cfDisplay = ['1' => 'Aktif', '0' => 'Nonaktif'];
$cfForm = 'wh-filter';
$cfDistinct = url('warehouses/distinct');
require __DIR__ . '/../partials/colfilter.php';
?>

<script src="<?= asset_v('js/table-colmgr.js') ?>" defer></script>
