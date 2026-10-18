<?php
/**
 * @var array 
 * @var array 
 */

/**
 * Render ringkas daftar petugas: maksimal $max chip nama, sisanya "+N".
 * Nama panjang dipotong dengan ellipsis supaya kolom tidak melebar.
 */
$officerChips = static function (?string $raw, string $kind, int $max = 2): string {
    $names = array_values(array_filter(array_map('trim', explode('|', (string) $raw)), static fn ($v) => $v !== ''));
    if ($names === []) {
        return '';
    }

    $shown = array_slice($names, 0, $max);
    $extra = count($names) - count($shown);
    $out = '<span class="off-chips">';
    foreach ($shown as $n) {
        $short = preg_match('/^(.*?)\s*\(/', $n, $m) ? trim($m[1]) : $n;
        $out .= '<span class="off-chip off-chip-' . $kind . '" title="' . e($n) . '">'
              . '<span class="off-dot"></span>' . e($short) . '</span>';
    }
    if ($extra > 0) {
        $out .= '<span class="off-chip off-chip-more" title="' . e(implode(', ', array_slice($names, $max))) . '">+' . $extra . '</span>';
    }
    $out .= '</span>';

    return $out;
};

$colCount = can('manage_groups') ? 9 : 8;
$__hasFilter = ! empty($filters['search']) || ! empty($filters['col']) || ! empty($filters['adv']);
$cfCol = $filters['col'] ?? [];
$xfBtn = static fn (string $k, string $lbl): string => '<button type="button" class="xf-btn' . (! empty($cfCol[$k]) ? ' is-active' : '') . '" data-xf="' . $k . '" aria-label="' . e(__('Filter kolom :l', ['l' => __($lbl)])) . '" aria-haspopup="dialog" title="Filter">' . icon('filter') . '</button>';
?>
<?= page_hero() ?>
<div class="page-head has-hero">
    <div>
        <h1><?= __('Group') ?></h1>
        <p>Setiap group terikat ke satu Gudang/Situs, dan memiliki observer serta auditor sendiri.</p>
    </div>
    <div class="spacer"></div>
    <?php if (can('manage_groups')): ?>
        <a class="btn btn-brand" href="<?= url('groups/create') ?>"><?= icon('plus') ?> Tambah Group</a>
    <?php endif; ?>
</div>

<div class="card">
    <div class="card-toolbar" style="display:flex;align-items:center;justify-content:space-between;gap:.75rem;flex-wrap:wrap;padding:.85rem 1.15rem;border-bottom:1px solid var(--border);">
        <form method="get" action="<?= url('groups') ?>" id="groups-filter" style="display:flex;align-items:center;flex:1;min-width:240px;gap:.65rem;margin:0;" autocomplete="off">
            <?php foreach (($filters['col'] ?? []) as $ck => $cvals): ?><?php foreach ((array) $cvals as $cv): ?><input type="hidden" name="col[<?= e($ck) ?>][]" value="<?= e($cv) ?>" class="col-hidden"><?php endforeach; ?><?php endforeach; ?>
            <div class="form-group is-search" style="flex:1;max-width:380px;margin:0;">
                <input type="search" id="groups-search" name="search" value="<?= e($filters['search'] ?? '') ?>"
                       placeholder="<?= __('Cari kode, nama group, gudang, atau petugas...') ?>"
                       data-filter-live autocomplete="off" enterkeyhint="search" style="width:100%;min-height:38px;">
            </div>
            <span class="sf-filter-count" aria-live="polite"></span>
        </form>

        <div class="table-toolbar" style="margin-left:auto;display:flex;gap:.55rem;align-items:center;">
            <button type="button" class="btn btn-info btn-icon-only" id="groups-adv-btn" aria-label="<?= __('Buka pencarian lanjutan') ?>" title="<?= __('Pencarian lanjutan') ?>"><?= icon('filter') ?></button>
            <?php if ($__hasFilter): ?>
                <a class="btn btn-ghost btn-icon-only col-clear-all" href="<?= url('groups') ?>" aria-label="<?= __('Hapus semua filter') ?>" title="<?= __('Hapus semua filter') ?>"><?= icon('x') ?></a>
            <?php endif; ?>
            <div class="col-mgr" id="groups-colmgr">
                <button type="button" class="btn btn-ghost btn-icon-only" id="groups-colmgr-btn" aria-expanded="false" aria-label="<?= __('Sesuaikan kolom') ?>" title="<?= __('Sesuaikan kolom') ?>"><?= icon('layers') ?></button>
                <div class="col-mgr-panel" id="groups-colmgr-panel" hidden></div>
            </div>
        </div>
    </div>

    <?php
    $__adv = $filters['adv'] ?? [];
    $asForm     = 'groups-filter';
    $asCols     = $advCols ?? ['code' => __('Kode'), 'name' => __('Nama'), 'warehouse' => __('Gudang'), 'status' => __('Status')];
    $asCurrent  = $__adv;
    $asNumeric  = $advNumeric ?? [];
    $asMax      = $advMax ?? 12;
    $asToggleId = 'groups-adv-btn';
    require __DIR__ . '/../partials/advanced-search.php';
    ?>

    <div class="table-wrap">
        <table class="data" id="groups-table" data-colmgr-key="groups-v1" data-colmgr-prefix="groups"><caption class="sr-only">Daftar group barang</caption>
            <thead>
                <tr>
                    <th data-col="code" class="xf-th"><span class="xf-label"><?= __('Kode') ?></span><?= $xfBtn('code', 'Kode') ?></th>
                    <th data-col="name" class="xf-th"><span class="xf-label"><?= __('Nama Group') ?></span><?= $xfBtn('name', 'Nama Group') ?></th>
                    <th data-col="warehouse" class="col-hide-tablet xf-th"><span class="xf-label"><?= __('Gudang / Situs') ?></span><?= $xfBtn('warehouse', 'Gudang') ?></th>
                    <th data-col="officers" class="xf-th"><span class="xf-label"><?= __('Petugas') ?></span><?= $xfBtn('officers', 'Petugas') ?></th>
                    <th data-col="sto" class="col-hide-tablet xf-th"><span class="xf-label"><?= __('STO') ?></span><?= $xfBtn('sto', 'STO') ?></th>
                    <th data-col="racks" class="col-hide-tablet t-center xf-th"><span class="xf-label"><?= __('Rak') ?></span><?= $xfBtn('racks', 'Rak') ?></th>
                    <th data-col="items" class="col-hide-tablet t-center xf-th"><span class="xf-label"><?= __('Item') ?></span><?= $xfBtn('items', 'Item') ?></th>
                    <th data-col="status" class="t-center xf-th"><span class="xf-label"><?= __('Status') ?></span><?= $xfBtn('status', 'Status') ?></th>
                    <th data-col="actions" class="t-right"><?= __('Aksi') ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if ($groups === []): ?>
                    <tr>
                        <td colspan="<?= $colCount ?>">
                            <div class="empty-state">
                                <div class="empty-icon"><?= icon('layers') ?></div>
                                <h3><?= __('Belum ada group') ?></h3>
                                <p><?= __('Tambahkan group untuk mulai mengelola data STO.') ?></p>
                            </div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php $paginator = $groups; $groups = $paginator['items']; ?>
                    <?php foreach ($groups as $group): ?>
                        <?php
                        $searchBlob = implode(' ', array_filter([
                            $group['code'] ?? '',
                            $group['name'] ?? '',
                            $group['description'] ?? '',
                            $group['warehouse_name'] ?? '',
                            $group['warehouse_city'] ?? '',
                            $group['sto_name'] ?? '',
                            $group['observer_names'] ?? '',
                            $group['auditor_names'] ?? '',
                        ]));
                        ?>
                        <tr data-gid="<?= (int) $group['id'] ?>"
                            data-wh="<?= e(mb_strtolower((string) $group['warehouse_name'])) ?>"
                            data-status="<?= (int) $group['is_active'] === 1 ? 'aktif' : 'nonaktif' ?>"
                            data-search="<?= e($searchBlob) ?>">
                            <td data-col="code" data-label="<?= __('Kode') ?>"><span class="kbd"><?= e($group['code']) ?></span></td>
                            <td data-col="name" data-label="<?= __('Nama Group') ?>">
                                <div class="cell-title"><a href="<?= url('groups/' . (int) $group['id']) ?>" style="color:inherit;text-decoration:none;"><?= e($group['name']) ?></a></div>
                                <div class="cell-sub"><?= e($group['description'] ?: '-') ?></div>
                            </td>
                            <td data-col="warehouse" data-label="<?= __('Gudang / Situs') ?>" class="col-hide-tablet">
                                <?php if (!empty($group['warehouse_name'])): ?>
                                    <div class="cell-title cell-ico">
                                        <span class="cell-ico-badge"><?= icon('building') ?></span>
                                        <?= e($group['warehouse_name']) ?>
                                    </div>
                                    <div class="cell-sub"><?= e($group['warehouse_city'] ?: $group['warehouse_code']) ?></div>
                                <?php else: ?>
                                    <span class="text-muted">— belum ditetapkan —</span>
                                <?php endif; ?>
                            </td>
                            <td data-col="officers" data-label="<?= __('Petugas') ?>">
                                <?php $obsHtml = $officerChips($group['observer_names'] ?? null, 'obs', 2); ?>
                                <?php $audHtml = $officerChips($group['auditor_names'] ?? null, 'aud', 2); ?>
                                <?php if ($obsHtml === '' && $audHtml === ''): ?>
                                    <span class="text-muted">— belum ditunjuk —</span>
                                <?php else: ?>
                                    <div class="off-row">
                                        <span class="off-tag off-tag-obs" title="<?= __('Observer') ?>"><?= icon('eye') ?> Obs</span>
                                        <span class="off-val"><?= $obsHtml !== '' ? $obsHtml : '<span class="text-muted">—</span>' ?></span>
                                    </div>
                                    <div class="off-row">
                                        <span class="off-tag off-tag-aud" title="<?= __('Auditor') ?>"><?= icon('shield-check') ?> Aud</span>
                                        <span class="off-val"><?= $audHtml !== '' ? $audHtml : '<span class="text-muted">—</span>' ?></span>
                                    </div>
                                <?php endif; ?>
                                <div class="cell-sub"><?= (int) $group['member_count'] ?> anggota terdelegasi</div>
                            </td>
                            <td data-col="sto" data-label="<?= __('STO') ?>" class="col-hide-tablet">
                                <?php if (!empty($group['sto_code'])): ?>
                                    <span class="sto-pill" title="<?= e($group['sto_name']) ?>"><?= e($group['sto_code']) ?></span>
                                    <div class="cell-sub"><?= e($group['sto_name']) ?></div>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td data-col="racks" data-label="<?= __('Rak') ?>" class="col-hide-tablet t-center"><?= (int) $group['location_count'] ?></td>
                            <td data-col="items" data-label="<?= __('Item') ?>" class="col-hide-tablet t-center"><?= (int) $group['item_count'] ?></td>
                            <td data-col="status" data-label="<?= __('Status') ?>" class="t-center">
                                <span class="badge <?= (int) $group['is_active'] === 1 ? 'badge-success' : 'badge-danger' ?>">
                                    <?= (int) $group['is_active'] === 1 ? __('Aktif') : __('Nonaktif') ?>
                                </span>
                            </td>
                            <td data-col="actions" class="t-right" data-label="<?= __('Aksi') ?>">
                                <div class="btn-group" style="justify-content:flex-end;">
                                    <a class="btn btn-outline btn-xs" href="<?= url('groups/' . (int) $group['id']) ?>" aria-label="Lihat detail <?= e($group['name']) ?>"><?= icon('eye') ?></a>
                                <?php if (can('manage_groups')): ?>
                                    <a class="btn btn-outline btn-xs" href="<?= url('groups/edit/' . (int) $group['id']) ?>" aria-label="Edit group <?= e($group['name']) ?>"><?= icon('edit') ?></a>
                                    <form method="post" action="<?= url('groups/' . (int) $group['id'] . '/delete') ?>"
                                          data-confirm="<?= e(__('Hapus group :name? Semua data terkait akan ikut terhapus.', ['name' => $group['name']])) ?>" style="display:inline;">
                                        <?= csrf_field() ?>
                                        <button type="submit" class="btn btn-danger btn-xs" aria-label="Hapus group <?= e($group['name']) ?>"><?= icon('trash') ?></button>
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

    <?= \App\Core\View::partial('partials/pagination', ['paginator' => $paginator, 'query' => array_diff_key($_GET, ['page' => 1])]) ?>
</div>

<script>
(function () {
    var form = document.getElementById('groups-filter');
    var table = document.getElementById('groups-table');
    if (!form || !table || !table.tBodies[0]) return;

    var tbody = table.tBodies[0];
    var q = form.querySelector('input[name="search"]');
    var count = form.querySelector('.sf-filter-count');
    var rows = Array.prototype.filter.call(tbody.rows, function (r) { return r.getAttribute('data-gid'); });

    function norm(s) { return (s || '').toLowerCase().replace(/\s+/g, ' ').trim(); }

    rows.forEach(function (r) {
        if (r.dataset.gblob) return;
        r.dataset.gblob = norm(r.getAttribute('data-search') || r.textContent);
    });

    function apply() {
        var term = norm(q ? q.value : '');
        var vis = 0;

        rows.forEach(function (r) {
            var blob = r.dataset.gblob || '';
            var ok = (!term || blob.indexOf(term) !== -1);

            if (window.sfRowVisible) { window.sfRowVisible(r, 'groups', ok); }
            else { r.style.display = ok ? '' : 'none'; }
            if (ok) vis++;
        });

        if (count) {
            count.textContent = term ? (vis + ' / ' + rows.length + ' group') : '';
        }

        var ph = tbody.querySelector('tr.gf-empty');
        if (rows.length && vis === 0) {
            if (!ph) {
                ph = document.createElement('tr');
                ph.className = 'gf-empty';
                var cols = table.tHead ? table.tHead.rows[0].cells.length : 9;
                ph.innerHTML = '<td colspan="' + cols + '"><div class="empty-state">' +
                    '<h3>Tidak ada group cocok</h3><p>Ubah kata kunci atau pencarian lanjutan yang dipakai.</p></div></td>';
                tbody.appendChild(ph);
            }
        } else if (ph) {
            ph.remove();
        }
    }

    var t;
    if (q) q.addEventListener('input', function () { clearTimeout(t); t = setTimeout(apply, 140); });
    form.addEventListener('submit', function () { setTimeout(apply, 60); });

    apply();
})();
</script>
<?php
$cfLabels = ['code' => __('Kode'), 'name' => __('Nama Group'), 'warehouse' => __('Gudang / Situs'), 'officers' => __('Petugas'), 'sto' => __('STO'), 'racks' => __('Rak'), 'items' => __('Item'), 'status' => __('Status')];
$cfDisplay = ['1' => 'Aktif', '0' => 'Nonaktif'];
$cfForm = 'groups-filter';
$cfDistinct = url('groups/distinct');
require __DIR__ . '/../partials/colfilter.php';
?>
<script src="<?= asset_v('js/table-colmgr.js') ?>" defer></script>
