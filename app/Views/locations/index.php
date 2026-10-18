<?php
/**
 * @var array $locations
 * @var array $filters
 * @var array $groups
 */
use App\Core\View;

$__locHasFilter = ! empty($filters['search']) || ! empty($filters['col']) || ! empty($filters['adv']) || ! empty($filters['group_id']);
$cfCol = $filters['col'] ?? [];
$xfBtn = static fn (string $k, string $lbl): string => '<button type="button" class="xf-btn' . (! empty($cfCol[$k]) ? ' is-active' : '') . '" data-xf="' . $k . '" aria-label="' . e(__('Filter kolom :l', ['l' => __($lbl)])) . '" aria-haspopup aria-haspopup="dialog" title="Filter">' . icon('filter') . '</button>';
?>
<?= page_hero() ?>
<div class="page-head has-hero">
    <div>
        <h1><?= __('Lokasi') ?></h1>
        <p><?= __('Lokasi fisik tempat data dihitung oleh observer.') ?></p>
    </div>
    <div class="spacer"></div>
    <?php if (can('manage_master')): ?>
        <a class="btn btn-brand" href="<?= url('locations/create') ?>"><?= icon('plus') ?> Tambah Lokasi</a>
    <?php endif; ?>
</div>

<div class="card">
    <div class="card-toolbar" style="display:flex;align-items:center;justify-content:space-between;gap:.75rem;flex-wrap:wrap;padding:.85rem 1.15rem;border-bottom:1px solid var(--border);">
        <form method="get" action="<?= url('locations') ?>" id="loc-filter" style="display:flex;align-items:center;flex:1;min-width:240px;gap:.65rem;margin:0;" autocomplete="off">
            <?php foreach (($filters['col'] ?? []) as $ck => $cvals): ?><?php foreach ((array) $cvals as $cv): ?><input type="hidden" name="col[<?= e($ck) ?>][]" value="<?= e($cv) ?>" class="col-hidden"><?php endforeach; ?><?php endforeach; ?>
            <div class="form-group is-search" style="flex:1;max-width:380px;margin:0;">
                <input type="search" id="loc-search" name="search" value="<?= e($filters['search'] ?? '') ?>"
                       placeholder="<?= __('Cari nama atau kode lokasi...') ?>"
                       data-filter-live autocomplete="off" enterkeyhint="search" style="width:100%;min-height:38px;">
            </div>
            <span class="sf-filter-count" aria-live="polite"></span>
        </form>

        <div class="table-toolbar" style="margin-left:auto;display:flex;gap:.55rem;align-items:center;">
            <button type="button" class="btn btn-info btn-icon-only" id="locations-adv-btn" aria-label="<?= __('Buka pencarian lanjutan') ?>" title="<?= __('Pencarian lanjutan') ?>"><?= icon('filter') ?></button>
            <?php if ($__locHasFilter): ?>
                <a class="btn btn-ghost btn-icon-only col-clear-all" href="<?= url('locations') ?>" aria-label="<?= __('Hapus semua filter') ?>" title="<?= __('Hapus semua filter') ?>"><?= icon('x') ?></a>
            <?php endif; ?>
            <div class="col-mgr" id="locations-colmgr">
                <button type="button" class="btn btn-ghost btn-icon-only" id="locations-colmgr-btn" aria-expanded="false" aria-label="<?= __('Sesuaikan kolom') ?>" title="<?= __('Sesuaikan kolom') ?>"><?= icon('layers') ?></button>
                <div class="col-mgr-panel" id="locations-colmgr-panel" hidden></div>
            </div>
        </div>
    </div>

    <?php
    /* LF-BAR: satu pencarian lanjutan (pola Data STO) */
    $__adv = $filters['adv'] ?? [];
    $asForm     = 'loc-filter';
    $asCols     = $advCols ?? [
        'code'   => __('Kode'),
        'name'   => __('Nama Lokasi'),
        'group'  => __('Group'),
        'status' => __('Status'),
    ];
    $asCurrent  = $__adv;
    $asNumeric  = $advNumeric ?? [];
    $asMax      = $advMax ?? 12;
    $asToggleId = 'locations-adv-btn';
    require __DIR__ . '/../partials/advanced-search.php';
    ?>

    <div class="table-wrap">
        <table class="data" id="locations-table" data-colmgr-key="locations-v1" data-colmgr-prefix="locations"><caption class="sr-only">Daftar lokasi</caption>
            <thead>
                <tr>
                    <th data-col="code" class="xf-th" style="width:110px;"><span class="xf-label"><?= __('Kode') ?></span><?= $xfBtn('code', 'Kode') ?></th>
                    <th data-col="name" class="xf-th"><span class="xf-label"><?= __('Nama Lokasi') ?></span><?= $xfBtn('name', 'Nama Lokasi') ?></th>
                    <th data-col="group" class="xf-th"><span class="xf-label"><?= __('Group') ?></span><?= $xfBtn('group', 'Group') ?></th>
                    <th data-col="device" class="t-center xf-th" style="width:75px;"><span class="xf-label"><?= __('Device') ?></span><?= $xfBtn('device', 'Device') ?></th>
                    <th data-col="item" class="t-center xf-th" style="width:75px;"><span class="xf-label"><?= __('Item') ?></span><?= $xfBtn('item', 'Item') ?></th>
                    <th data-col="status" class="t-center xf-th" style="width:110px;"><span class="xf-label"><?= __('Status') ?></span><?= $xfBtn('status', 'Status') ?></th>
                    <?php if (can('manage_master')): ?>
                        <th data-col="actions" class="t-right" style="width:85px;min-width:85px;"><?= __('Aksi') ?></th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php if ($locations['items'] === []): ?>
                    <tr>
                        <td colspan="7">
                            <div class="empty-state">
                                <div class="empty-icon"><?= icon('pin') ?></div>
                                <h3><?= __('Belum ada lokasi') ?></h3>
                                <p><?= __('Tambahkan lokasi untuk setiap group.') ?></p>
                            </div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($locations['items'] as $location): ?>
                        <tr>
                            <td data-label="<?= __('Kode') ?>" data-col="code"><span class="kbd"><?= e($location['code']) ?></span></td>
                            <td data-label="<?= __('Nama Lokasi') ?>" data-col="name">
                                <div class="cell-title"><?= e($location['name']) ?></div>
                                <div class="cell-sub"><?= e($location['address'] ?: '-') ?></div>
                            </td>
                            <td data-label="<?= __('Group') ?>" data-col="group"><?= e($location['group_name']) ?></td>
                            <td data-label="<?= __('Device') ?>" data-col="device" class="t-center"><?= (int) $location['device_count'] ?></td>
                            <td data-label="<?= __('Item') ?>" data-col="item" class="t-center"><?= (int) $location['item_count'] ?></td>
                            <td data-label="<?= __('Status') ?>" data-col="status" class="t-center">
                                <span class="badge <?= (int) $location['is_active'] === 1 ? 'badge-success' : 'badge-danger' ?>">
                                    <?= (int) $location['is_active'] === 1 ? __('Aktif') : __('Nonaktif') ?>
                                </span>
                            </td>
                            <?php if (can('manage_master')): ?>
                                <td data-label="<?= __('Aksi') ?>" class="t-right">
                                    <div class="btn-group" style="justify-content:flex-end;">
                                        <a class="btn btn-outline btn-xs" href="<?= url('locations/edit/' . (int) $location['id']) ?>" aria-label="Edit lokasi <?= e($location['name']) ?>"><?= icon('edit') ?></a>
                                        <form method="post" action="<?= url('locations/' . (int) $location['id'] . '/delete') ?>"
                                              data-confirm="<?= e(__('Hapus lokasi :name?', ['name' => $location['name']])) ?>" style="display:inline;">
                                            <?= csrf_field() ?>
                                            <button type="submit" class="btn btn-danger btn-xs" aria-label="Hapus lokasi <?= e($location['name']) ?>"><?= icon('trash') ?></button>
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

    <?= View::partial('partials/pagination', [
        'paginator' => $locations,
        'query'     => array_filter(['group_id' => $filters['group_id'] ?? '', 'search' => $filters['search'] ?? ''], static fn ($v) => $v !== '' && $v !== null),
    ]) ?>
</div>

<?php
$cfLabels = ['code' => __('Kode'), 'name' => __('Nama Lokasi'), 'group' => __('Group'), 'device' => __('Device'), 'item' => __('Item'), 'status' => __('Status')];
$cfDisplay = ['1' => 'Aktif', '0' => 'Nonaktif'];
$cfForm = 'loc-filter';
$cfDistinct = url('locations/distinct');
require __DIR__ . '/../partials/colfilter.php';
?>

<script src="<?= asset_v('js/table-colmgr.js') ?>" defer></script>
