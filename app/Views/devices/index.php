<?php
/**
 * @var array $devices
 * @var array $filters
 * @var array $locations
 */
use App\Core\View;

$__devHasFilter = ! empty($filters['search']) || ! empty($filters['col']) || ! empty($filters['adv']) || ! empty($filters['location_id']);
$cfCol = $filters['col'] ?? [];
$xfBtn = static fn (string $k, string $lbl): string => '<button type="button" class="xf-btn' . (! empty($cfCol[$k]) ? ' is-active' : '') . '" data-xf="' . $k . '" aria-label="' . e(__('Filter kolom :l', ['l' => __($lbl)])) . '" aria-haspopup aria-haspopup="dialog" title="Filter">' . icon('filter') . '</button>';
?>
<div class="page-head">
    <div>
        <h1><?= __('Device') ?></h1>
        <p><?= __('Perangkat yang terpasang di lokasi, seperti tablet lokasi.') ?></p>
    </div>
    <div class="spacer"></div>
    <?php if (can('manage_master')): ?>
        <a class="btn btn-brand" href="<?= url('devices/create') ?>"><?= icon('plus') ?> Tambah Device</a>
    <?php endif; ?>
</div>

<div class="card">
    <div class="card-toolbar" style="display:flex;align-items:center;justify-content:space-between;gap:.75rem;flex-wrap:wrap;padding:.85rem 1.15rem;border-bottom:1px solid var(--border);">
        <form method="get" action="<?= url('devices') ?>" id="dev-filter" style="display:flex;align-items:center;flex:1;min-width:240px;gap:.65rem;margin:0;" autocomplete="off">
            <?php foreach (($filters['col'] ?? []) as $ck => $cvals): ?><?php foreach ((array) $cvals as $cv): ?><input type="hidden" name="col[<?= e($ck) ?>][]" value="<?= e($cv) ?>" class="col-hidden"><?php endforeach; ?><?php endforeach; ?>
            <div class="form-group is-search" style="flex:1;max-width:380px;margin:0;">
                <input type="search" id="dev-search" name="search" value="<?= e($filters['search'] ?? '') ?>"
                       placeholder="<?= __('Cari nama, kode, atau serial...') ?>"
                       data-filter-live autocomplete="off" enterkeyhint="search" style="width:100%;min-height:38px;">
            </div>
            <span class="sf-filter-count" aria-live="polite"></span>
        </form>

        <div class="table-toolbar" style="margin-left:auto;display:flex;gap:.55rem;align-items:center;">
            <button type="button" class="btn btn-info btn-icon-only" id="devices-adv-btn" aria-label="<?= __('Buka pencarian lanjutan') ?>" title="<?= __('Pencarian lanjutan') ?>"><?= icon('filter') ?></button>
            <?php if ($__devHasFilter): ?>
                <a class="btn btn-ghost btn-icon-only col-clear-all" href="<?= url('devices') ?>" aria-label="<?= __('Hapus semua filter') ?>" title="<?= __('Hapus semua filter') ?>"><?= icon('x') ?></a>
            <?php endif; ?>
            <div class="col-mgr" id="devices-colmgr">
                <button type="button" class="btn btn-ghost btn-icon-only" id="devices-colmgr-btn" aria-expanded="false" aria-label="<?= __('Sesuaikan kolom') ?>" title="<?= __('Sesuaikan kolom') ?>"><?= icon('layers') ?></button>
                <div class="col-mgr-panel" id="devices-colmgr-panel" hidden></div>
            </div>
        </div>
    </div>

    <?php
    /* LF-BAR: satu pencarian lanjutan (pola Data STO) */
    $__adv = $filters['adv'] ?? [];
    $asForm     = 'dev-filter';
    $asCols     = $advCols ?? [
        'code'     => __('Kode'),
        'name'     => __('Nama Device'),
        'type'     => __('Tipe'),
        'serial'   => __('Serial'),
        'location' => __('Lokasi'),
        'status'   => __('Status'),
    ];
    $asCurrent  = $__adv;
    $asNumeric  = $advNumeric ?? [];
    $asMax      = $advMax ?? 12;
    $asToggleId = 'devices-adv-btn';
    require __DIR__ . '/../partials/advanced-search.php';
    ?>

    <div class="table-wrap">
        <table class="data" id="devices-table" data-colmgr-key="devices-v1" data-colmgr-prefix="devices">
            <thead>
                <tr>
                    <th class="xf-th" data-col="code" style="width:110px;"><span class="xf-label"><?= __('Kode') ?></span><?= $xfBtn('code', 'Kode') ?></th>
                    <th class="xf-th" data-col="name"><span class="xf-label"><?= __('Nama Device') ?></span><?= $xfBtn('name', 'Nama Device') ?></th>
                    <th class="xf-th" data-col="type" style="width:105px;"><span class="xf-label"><?= __('Tipe') ?></span><?= $xfBtn('type', 'Tipe') ?></th>
                    <th class="xf-th" data-col="serial" style="width:130px;"><span class="xf-label"><?= __('Serial') ?></span><?= $xfBtn('serial', 'Serial') ?></th>
                    <th class="xf-th" data-col="location"><span class="xf-label"><?= __('Lokasi / Group') ?></span><?= $xfBtn('location', 'Lokasi') ?></th>
                    <th class="t-center xf-th" data-col="status" style="width:110px;"><span class="xf-label"><?= __('Status') ?></span><?= $xfBtn('status', 'Status') ?></th>
                    <?php if (can('manage_master')): ?>
                        <th data-col="actions" class="t-right" style="width:85px;min-width:85px;"><?= __('Aksi') ?></th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php if ($devices['items'] === []): ?>
                    <tr>
                        <td colspan="7">
                            <div class="empty-state">
                                <div class="empty-icon"><?= icon('tablet') ?></div>
                                <h3><?= __('Belum ada device') ?></h3>
                                <p><?= __('Tambahkan device dan hubungkan dengan lokasi.') ?></p>
                            </div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($devices['items'] as $device): ?>
                        <tr>
                            <td data-label="<?= __('Kode') ?>" data-col="code"><span class="kbd"><?= e($device['code']) ?></span></td>
                            <td data-label="<?= __('Nama Device') ?>" data-col="name" class="cell-title"><?= e($device['name']) ?></td>
                            <td data-label="<?= __('Tipe') ?>" data-col="type"><span class="badge badge-info"><?= e($device['type'] ?: 'other') ?></span></td>
                            <td data-label="<?= __('Serial') ?>" data-col="serial" class="mono text-sm"><?= e($device['serial_number'] ?: '-') ?></td>
                            <td data-label="<?= __('Lokasi / Group') ?>" data-col="location">
                                <div class="cell-title"><?= e($device['location_name']) ?></div>
                                <div class="cell-sub"><?= e($device['group_name']) ?></div>
                            </td>
                            <td data-label="<?= __('Status') ?>" data-col="status" class="t-center">
                                <span class="badge <?= (int) $device['is_active'] === 1 ? 'badge-success' : 'badge-danger' ?>">
                                    <?= (int) $device['is_active'] === 1 ? __('Aktif') : __('Nonaktif') ?>
                                </span>
                            </td>
                            <?php if (can('manage_master')): ?>
                                <td data-label="<?= __('Aksi') ?>" class="t-right">
                                    <div class="btn-group" style="justify-content:flex-end;">
                                        <a class="btn btn-outline btn-xs" href="<?= url('devices/edit/' . (int) $device['id']) ?>" aria-label="Edit device <?= e($device['name']) ?>"><?= icon('edit') ?></a>
                                        <form method="post" action="<?= url('devices/' . (int) $device['id'] . '/delete') ?>"
                                              data-confirm="<?= e(__('Hapus device :name?', ['name' => $device['name']])) ?>" style="display:inline;">
                                            <?= csrf_field() ?>
                                            <button type="submit" class="btn btn-danger btn-xs" aria-label="Hapus device <?= e($device['name']) ?>"><?= icon('trash') ?></button>
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
        'paginator' => $devices,
        'query'     => array_filter(['location_id' => $filters['location_id'] ?? '', 'search' => $filters['search'] ?? ''], static fn ($v) => $v !== '' && $v !== null),
    ]) ?>
</div>

<?php
$cfLabels = ['code' => __('Kode'), 'name' => __('Nama Device'), 'type' => __('Tipe'), 'serial' => __('Serial'), 'location' => __('Lokasi'), 'status' => __('Status')];
$cfDisplay = ['1' => 'Aktif', '0' => 'Nonaktif'];
$cfForm = 'dev-filter';
$cfDistinct = url('devices/distinct');
require __DIR__ . '/../partials/colfilter.php';
?>

<script src="<?= asset_v('js/table-colmgr.js') ?>" defer></script>
