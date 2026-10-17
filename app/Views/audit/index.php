<?php
/**
 * @var array $queue
 * @var array $progress
 * @var array $filters
 * @var array $groups
 * @var int $minPercent
 * @var array $history
 * @var array $recommendation
 * @var array $postedAll
 * @var array $recheck
 * @var string $tab
 */
use App\Core\View;

$sumTotal   = array_sum(array_map(static fn ($r) => (int) $r['total'], $progress));
$sumPosted  = array_sum(array_map(static fn ($r) => (int) $r['posted'], $progress));
$sumAudited = array_sum(array_map(static fn ($r) => (int) $r['audited'], $progress));
$groupsOk = 0;
foreach ($progress as $r) {
    if ((int) $r['needed'] > 0 && (int) $r['audited'] >= (int) $r['needed']) { $groupsOk++; }
}
$groupCount = count($progress);
?>
<?= page_hero() ?>
<div class="page-head has-hero">
    <div>
        <h1><?= __('Audit') ?></h1>
        <p>Lakukan pengecekan minimal <?= (int) $minPercent ?>% data per group yang didelegasikan.</p>
    </div>
    <div class="spacer"></div>
    <button type="button" class="btn btn-brand" data-scan-open aria-label="<?= __('Scan barcode atau QR') ?>">
        <?= icon('scan') ?> <?= __('Scan Barcode') ?>
    </button>
    <a class="btn btn-outline" href="<?= url('audit/history') ?>"><?= icon('list') ?> Riwayat Audit</a>
</div>

<?= \App\Core\View::partial('partials/process-trail', ['trail' => $trail ?? [], 'trailGroupId' => $trailGroupId ?? null]) ?>
<?php
$cfCol = $filters['col'] ?? [];
$xfBtn = static fn (string $k, string $lbl): string => '<button type="button" class="xf-btn' . (! empty($cfCol[$k]) ? ' is-active' : '') . '" data-xf="' . $k . '" aria-label="' . e(__('Filter kolom :l', ['l' => __($lbl)])) . '" aria-haspopup="dialog" title="Filter">' . icon('filter') . '</button>';

// --- Tiga tab Audit ---
$__at = $tab ?? 'sample';
$__tabBase = $_GET;
unset($__tabBase['tab'], $__tabBase['page']);
$__tabLink = static function (string $t) use ($__tabBase): string {
    $q = $__tabBase; $q['tab'] = $t;
    return url('audit') . '?' . http_build_query($q);
};
$__recCount  = count($recommendation);
$__postCount = (int) $postedAll['total'];
$__rechCount = (int) $recheck['total'];
?>
<div class="sf-tabs audit-tabs" role="tablist" aria-label="<?= __('Kelompok audit') ?>">
    <a class="tab-pill<?= $__at === 'sample' ? ' is-active' : '' ?>" role="tab" aria-selected="<?= $__at === 'sample' ? 'true' : 'false' ?>" href="<?= e($__tabLink('sample')) ?>">
        <?= icon('info') ?> Rekomendasi <?= (int) $minPercent ?>% <span class="tab-count"><?= $__recCount ?></span>
    </a>
    <a class="tab-pill<?= $__at === 'posted' ? ' is-active' : '' ?>" role="tab" aria-selected="<?= $__at === 'posted' ? 'true' : 'false' ?>" href="<?= e($__tabLink('posted')) ?>">
        <?= icon('send') ?> Semua diposting <span class="tab-count"><?= $__postCount ?></span>
    </a>
    <a class="tab-pill<?= $__at === 'recheck' ? ' is-active' : '' ?>" role="tab" aria-selected="<?= $__at === 'recheck' ? 'true' : 'false' ?>" href="<?= e($__tabLink('recheck')) ?>">
        <?= icon('refresh') ?> Cek ulang Admin STO <span class="tab-count"><?= $__rechCount ?></span>
    </a>
</div>

<div class="grid grid-4 grid-tablet-4">
    <div class="stat is-brand" style="--i:0">
        <div class="stat-icon"><?= icon('layers') ?></div>
        <span class="stat-label"><?= __('Group capai target') ?></span>
        <div class="stat-value"><?= (int) $groupsOk ?></div>
        <div class="stat-meta">dari <?= (int) $groupCount ?> group sudah memenuhi <?= (int) $minPercent ?>%</div>
    </div>
    <div class="stat is-info" style="--i:1">
        <div class="stat-icon"><?= icon('package') ?></div>
        <span class="stat-label"><?= __('Total item') ?></span>
        <div class="stat-value"><?= (int) $sumTotal ?></div>
        <div class="stat-meta"><?= (int) $sumPosted ?> terposting</div>
    </div>
    <div class="stat is-success" style="--i:2">
        <div class="stat-icon"><?= icon('clipboard-check') ?></div>
        <span class="stat-label"><?= __('Sudah diaudit') ?></span>
        <div class="stat-value"><?= (int) $sumAudited ?></div>
        <div class="stat-meta"><?= percent((int) $sumAudited, (int) $sumTotal) ?>% dari total</div>
    </div>
    <div class="stat is-warning" style="--i:3">
        <div class="stat-icon"><?= icon('clock') ?></div>
        <span class="stat-label"><?= __('Antrean audit') ?></span>
        <div class="stat-value"><?= (int) $queue['total'] ?></div>
        <div class="stat-meta"><?= __('item menunggu dicek') ?></div>
    </div>
</div>

<div class="card mt-2">
    <div class="card-toolbar" style="display:flex;align-items:center;justify-content:space-between;gap:.75rem;flex-wrap:wrap;padding:.85rem 1.15rem;border-bottom:1px solid var(--border);">
        <h2 style="font-size:1.05rem;font-weight:700;margin:0;display:flex;align-items:center;gap:.5rem;"><?= icon('chart') ?> Cakupan Audit per Group</h2>
        <div class="spacer"></div>
        <span class="badge badge-brand">Target minimal <?= (int) $minPercent ?>%</span>
    </div>
    <div class="table-wrap">
        <table class="data" id="audit-coverage-table" data-colmgr-key="audit-cov-v1" data-colmgr-prefix="audit-cov">
            <thead>
                <tr>
                    <th data-col="group"><?= __('Group') ?></th>
                    <th data-col="total" class="col-hide-tablet t-center"><?= __('Total') ?></th>
                    <th data-col="posted" class="col-hide-tablet t-center"><?= __('Terposting') ?></th>
                    <th data-col="audited" class="col-hide-tablet t-center"><?= __('Sudah diaudit') ?></th>
                    <th data-col="target" class="t-center">Target (<?= (int) $minPercent ?>%)</th>
                    <th data-col="progress" class="col-progress"><?= __('Progres') ?></th>
                    <th data-col="aksi" class="t-right"><?= __('Aksi') ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if ($progress === []): ?>
                    <tr><td colspan="7" class="text-center faint" style="padding:1.5rem;"><?= __('Belum ada group.') ?></td></tr>
                <?php else: ?>
                    <?php foreach ($progress as $row): ?>
                        <?php
                        $needed = (int) $row['needed'];
                        $audited = (int) $row['audited'];
                        $ok = $audited >= $needed && $needed > 0;
                        $pct = $needed > 0 ? min(100, (int) round($audited / $needed * 100)) : 0;
                        ?>
                        <tr>
                            <td data-label="<?= __('Group') ?>">
                                <div class="cell-title"><?= e($row['name']) ?></div>
                                <div class="cell-sub"><?= e($row['code']) ?></div>
                            </td>
                            <td data-label="<?= __('Total') ?>" class="col-hide-tablet t-center mono tabular"><?= (int) $row['total'] ?></td>
                            <td data-label="<?= __('Terposting') ?>" class="col-hide-tablet t-center mono tabular"><?= (int) $row['posted'] ?></td>
                            <td data-label="<?= __('Diaudit') ?>" class="col-hide-tablet t-center mono tabular"><?= $audited ?></td>
                            <td data-label="<?= __('Target') ?>" class="t-center mono tabular"><?= $needed ?></td>
                            <td data-label="<?= __('Progres') ?>">
                                <div class="coverage <?= $ok ? 'is-ok' : 'is-warn' ?>">
                                    <div class="bar <?= $ok ? 'is-success' : 'is-warning' ?> cov-bar">
                                        <span style="width:<?= $pct ?>%"></span>
                                    </div>
                                    <span class="cov-pct"><?= $pct ?>%</span>
                                </div>
                            </td>
                            <td data-label="" class="t-right">
                                <a class="btn btn-outline btn-xs" href="<?= url('items?group_id=' . (int) $row['id'] . '&count_status=posted&audit_status=pending') ?>"><?= icon('eye') ?> Lihat data</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if ($__at === 'sample'): ?>
<!-- ====================================================================== -->
<!-- Tab 1: Rekomendasi sampel 10%                                          -->
<!-- ====================================================================== -->
<div class="card mt-2">
    <div class="card-toolbar" style="display:flex;align-items:center;justify-content:space-between;gap:.75rem;flex-wrap:wrap;padding:.85rem 1.15rem;border-bottom:1px solid var(--border);">
        <form method="get" action="<?= url('audit') ?>" id="audit-filter" class="filters" style="display:flex;align-items:center;gap:.75rem;margin:0;flex-wrap:wrap;">
            <input type="hidden" name="tab" value="<?= e($__at) ?>">
            <div class="form-group" style="margin:0;">
                <select id="group_id" name="group_id" data-auto-submit style="min-height:38px;">
                    <option value=""><?= __('Semua group') ?></option>
                    <?php foreach ($groups as $group): ?>
                        <option value="<?= (int) $group['id'] ?>" <?= (int) ($filters['group_id'][0] ?? 0) === (int) $group['id'] ? 'selected' : '' ?>>
                            <?= e($group['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <a class="btn btn-ghost btn-sm" href="<?= url('audit?tab=' . e($__at)) ?>"><?= icon('x') ?> Reset</a>
        </form>
        <div class="table-toolbar" style="margin-left:auto;">
            <span class="badge badge-warn"><?= (int) $__recCount ?> item disarankan</span>
        </div>
    </div>

    <div class="table-wrap">
        <table class="data" id="audit-sample-table" data-colmgr-key="audit-sample-v1" data-colmgr-prefix="audit-sample">
            <thead>
                <tr>
                    <th data-col="code" class="xf-th"><span class="xf-label"><?= __('Kode') ?></span><?= $xfBtn('code', 'Kode') ?></th>
                    <th data-col="name" class="col-hide-tablet xf-th"><span class="xf-label"><?= __('Nama') ?></span><?= $xfBtn('name', 'Nama') ?></th>
                    <th data-col="group" class="col-hide-tablet xf-th"><span class="xf-label"><?= __('Group') ?></span><?= $xfBtn('group', 'Group') ?></th>
                    <th data-col="expected" class="col-hide-tablet t-center xf-th"><span class="xf-label"><?= __('Sistem') ?></span><?= can_see_stock_qty() ? $xfBtn('expected', 'Sistem') : '' ?></th>
                    <th data-col="counted" class="col-hide-tablet t-center xf-th"><span class="xf-label"><?= __('Hasil hitung') ?></span><?= $xfBtn('counted', 'Hasil hitung') ?></th>
                    <th data-col="aksi" class="t-right"><?= __('Aksi') ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if ($__recCount === 0): ?>
                    <tr><td colspan="6"><div class="empty-state"><div class="empty-icon"><?= icon('check') ?></div><h3><?= __('Tidak ada rekomendasi') ?></h3><p>Semua group sudah memenuhi target <?= (int) $minPercent ?>% audit.</p></div></td></tr>
                <?php else: ?>
                    <?php foreach ($recommendation as $item): ?>
                        <tr>
                            <td data-label="<?= __('Kode') ?>"><?= e($item['code']) ?></td>
                            <td data-label="<?= __('Nama') ?>" class="cell-title"><?= e($item['name']) ?></td>
                            <td data-label="<?= __('Group') ?>" class="col-hide-tablet"><?= e($item['group_name']) ?></td>
                            <td data-label="<?= __('Sistem') ?>" class="col-hide-tablet t-center mono tabular"><?= can_see_stock_qty() ? (int) $item['expected_qty'] : '<span class="faint">***</span>' ?></td>
                            <td data-label="<?= __('Hasil hitung') ?>" class="col-hide-tablet t-center mono tabular"><?= $item['counted_qty'] === null ? '—' : (int) $item['counted_qty'] ?></td>
                            <td data-label="" class="t-right">
                                <?php if (empty($isReadOnly)): ?>
                                <button type="button" class="btn btn-sm js-audit-open"
                                        data-action="<?= url('audit/' . (int) $item['id'] . '/check') ?>"
                                        data-code="<?= e($item['code']) ?>" data-name="<?= e($item['name']) ?>"
                                        data-group="<?= e($item['group_name']) ?>"
                                        data-expected="<?= can_see_stock_qty() ? (int) $item['expected_qty'] : '—' ?>"
                                        data-counted="<?= $item['counted_qty'] === null ? '—' : (int) $item['counted_qty'] ?>">
                                    <?= icon('clipboard-check') ?> Audit
                                </button>
                                <?php else: ?>
                                    <span class="badge badge-subtle"><?= icon('lock') ?> Terkunci</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php elseif ($__at === 'posted'): ?>
<!-- ====================================================================== -->
<!-- Tab 2: Semua item diposting observer                                   -->
<!-- ====================================================================== -->
<div class="card mt-2">
    <div class="card-toolbar" style="display:flex;align-items:center;justify-content:space-between;gap:.75rem;flex-wrap:wrap;padding:.85rem 1.15rem;border-bottom:1px solid var(--border);">
        <form method="get" action="<?= url('audit') ?>" id="audit-filter" class="filters" style="display:flex;align-items:center;gap:.75rem;margin:0;flex-wrap:wrap;">
            <input type="hidden" name="tab" value="<?= e($__at) ?>">
            <div class="form-group" style="margin:0;">
                <select id="group_id" name="group_id" data-auto-submit style="min-height:38px;">
                    <option value=""><?= __('Semua group') ?></option>
                    <?php foreach ($groups as $group): ?>
                        <option value="<?= (int) $group['id'] ?>" <?= (int) ($filters['group_id'][0] ?? 0) === (int) $group['id'] ? 'selected' : '' ?>>
                            <?= e($group['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <a class="btn btn-ghost btn-sm" href="<?= url('audit?tab=' . e($__at)) ?>"><?= icon('x') ?> Reset</a>
        </form>
        <div class="table-toolbar" style="margin-left:auto;">
            <span class="badge badge-info"><?= $__postCount ?> item</span>
        </div>
    </div>
    <div class="table-wrap">
        <table class="data" id="audit-posted-table" data-colmgr-key="audit-posted-v1" data-colmgr-prefix="audit-posted">
            <thead>
                <tr>
                    <th data-col="code" class="xf-th"><span class="xf-label"><?= __('Kode') ?></span><?= $xfBtn('code', 'Kode') ?></th>
                    <th data-col="name" class="col-hide-tablet xf-th"><span class="xf-label"><?= __('Nama') ?></span><?= $xfBtn('name', 'Nama') ?></th>
                    <th data-col="group" class="col-hide-tablet xf-th"><span class="xf-label"><?= __('Group') ?></span><?= $xfBtn('group', 'Group') ?></th>
                    <th data-col="expected" class="col-hide-tablet t-center xf-th"><span class="xf-label"><?= __('Sistem') ?></span><?= can_see_stock_qty() ? $xfBtn('expected', 'Sistem') : '' ?></th>
                    <th data-col="counted" class="col-hide-tablet t-center xf-th"><span class="xf-label"><?= __('Hasil hitung') ?></span><?= $xfBtn('counted', 'Hasil hitung') ?></th>
                    <th data-col="audit" class="t-center xf-th"><span class="xf-label"><?= __('Status audit') ?></span><?= $xfBtn('audit', 'Status audit') ?></th>
                    <th data-col="aksi" class="t-right"><?= __('Aksi') ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if ($postedAll['items'] === []): ?>
                    <tr><td colspan="7" class="text-center faint" style="padding:1.4rem;"><?= __('Belum ada item diposting.') ?></td></tr>
                <?php else: ?>
                    <?php foreach ($postedAll['items'] as $item): ?>
                        <?php $__as = (string) $item['audit_status']; ?>
                        <tr>
                            <td data-label="<?= __('Kode') ?>"><?= e($item['code']) ?></td>
                            <td data-label="<?= __('Nama') ?>" class="cell-title"><?= e($item['name']) ?></td>
                            <td data-label="<?= __('Group') ?>" class="col-hide-tablet"><?= e($item['group_name']) ?></td>
                            <td data-label="<?= __('Sistem') ?>" class="col-hide-tablet t-center mono tabular"><?= can_see_stock_qty() ? (int) $item['expected_qty'] : '<span class="faint">***</span>' ?></td>
                            <td data-label="<?= __('Hasil hitung') ?>" class="col-hide-tablet t-center mono tabular"><?= $item['counted_qty'] === null ? '—' : (int) $item['counted_qty'] ?></td>
                            <td data-label="<?= __('Status audit') ?>" class="t-center">
                                <span class="badge <?= $__as === 'audited' ? 'badge-success' : ($__as === 'discrepancy' ? 'badge-danger' : ($__as === 'resolved' ? 'badge-info' : 'badge-warn')) ?>">
                                    <?= e(['pending' => __('Belum diaudit'), 'audited' => __('Sesuai'), 'discrepancy' => __('Selisih'), 'resolved' => __('Selesai')][$__as] ?? $__as) ?>
                                </span>
                            </td>
                            <td data-label="" class="t-right">
                                <?php if ($__as === 'pending'): ?>
                                    <button type="button" class="btn btn-sm js-audit-open"
                                            data-action="<?= url('audit/' . (int) $item['id'] . '/check') ?>"
                                            data-code="<?= e($item['code']) ?>" data-name="<?= e($item['name']) ?>"
                                            data-group="<?= e($item['group_name']) ?>"
                                            data-expected="<?= can_see_stock_qty() ? (int) $item['expected_qty'] : '—' ?>"
                                            data-counted="<?= $item['counted_qty'] === null ? '—' : (int) $item['counted_qty'] ?>">
                                        <?= icon('clipboard-check') ?> Audit
                                    </button>
                                <?php else: ?>
                                    <a class="btn btn-outline btn-xs" href="<?= url('items/' . (int) $item['id']) ?>"><?= icon('eye') ?> Detail</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?= View::partial('partials/pagination', ['paginator' => $postedAll, 'query' => array_filter(['tab' => $__at, 'group_id' => $filters['group_id'][0] ?? ''])]) ?>
</div>

<?php elseif ($__at === 'recheck'): ?>
<!-- ====================================================================== -->
<!-- Tab 3: Instruksi cek ulang Admin STO                                  -->
<!-- ====================================================================== -->
<div class="card mt-2">
    <div class="card-toolbar" style="display:flex;align-items:center;justify-content:space-between;gap:.75rem;flex-wrap:wrap;padding:.85rem 1.15rem;border-bottom:1px solid var(--border);">
        <form method="get" action="<?= url('audit') ?>" id="audit-filter" class="filters" style="display:flex;align-items:center;gap:.75rem;margin:0;flex-wrap:wrap;">
            <input type="hidden" name="tab" value="<?= e($__at) ?>">
            <div class="form-group" style="margin:0;">
                <select id="group_id" name="group_id" data-auto-submit style="min-height:38px;">
                    <option value=""><?= __('Semua group') ?></option>
                    <?php foreach ($groups as $group): ?>
                        <option value="<?= (int) $group['id'] ?>" <?= (int) ($filters['group_id'][0] ?? 0) === (int) $group['id'] ? 'selected' : '' ?>>
                            <?= e($group['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <a class="btn btn-ghost btn-sm" href="<?= url('audit?tab=' . e($__at)) ?>"><?= icon('x') ?> Reset</a>
        </form>
        <div class="table-toolbar" style="margin-left:auto;">
            <span class="badge badge-warn"><?= $__rechCount ?> item</span>
        </div>
    </div>
    <div class="table-wrap">
        <table class="data" id="audit-recheck-table" data-colmgr-key="audit-recheck-v1" data-colmgr-prefix="audit-recheck">
            <thead>
                <tr>
                    <th data-col="code" class="xf-th"><span class="xf-label"><?= __('Kode') ?></span><?= $xfBtn('code', 'Kode') ?></th>
                    <th data-col="name" class="col-hide-tablet xf-th"><span class="xf-label"><?= __('Nama') ?></span><?= $xfBtn('name', 'Nama') ?></th>
                    <th data-col="group" class="col-hide-tablet xf-th"><span class="xf-label"><?= __('Group') ?></span><?= $xfBtn('group', 'Group') ?></th>
                    <th data-col="location" class="col-hide-tablet xf-th"><span class="xf-label"><?= __('Lokasi') ?></span><?= $xfBtn('location', 'Lokasi') ?></th>
                    <th data-col="expected" class="col-hide-tablet t-center xf-th"><span class="xf-label"><?= __('Sistem') ?></span><?= can_see_stock_qty() ? $xfBtn('expected', 'Sistem') : '' ?></th>
                    <th data-col="counted" class="col-hide-tablet t-center xf-th"><span class="xf-label"><?= __('Hitung') ?></span><?= $xfBtn('counted', 'Hitung') ?></th>
                    <th data-col="status" class="t-center xf-th"><span class="xf-label"><?= __('Status') ?></span><?= $xfBtn('status', 'Status') ?></th>
                    <th data-col="aksi" class="t-right"><?= __('Aksi') ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if ($recheck['items'] === []): ?>
                    <tr><td colspan="8"><div class="empty-state"><div class="empty-icon"><?= icon('check') ?></div><h3><?= __('Tidak ada permintaan cek ulang') ?></h3><p><?= __('Belum ada item yang diminta dicek ulang oleh Admin STO.') ?></p></div></td></tr>
                <?php else: ?>
                    <?php foreach ($recheck['items'] as $item): ?>
                        <?php $__rs = (string) $item['recon_status']; ?>
                        <tr>
                            <td data-label="<?= __('Kode') ?>"><?= e($item['code']) ?></td>
                            <td data-label="<?= __('Nama') ?>" class="cell-title"><?= e($item['name']) ?></td>
                            <td data-label="<?= __('Group') ?>" class="col-hide-tablet"><?= e($item['group_name']) ?></td>
                            <td data-label="<?= __('Lokasi') ?>" class="col-hide-tablet"><?= e($item['location_name'] ?: '-') ?></td>
                            <td data-label="<?= __('Sistem') ?>" class="col-hide-tablet t-center mono tabular"><?= can_see_stock_qty() ? (int) $item['expected_qty'] : '<span class="faint">***</span>' ?></td>
                            <td data-label="<?= __('Hitung') ?>" class="col-hide-tablet t-center mono tabular"><?= $item['recon_counted'] === null ? '—' : (int) $item['recon_counted'] ?></td>
                            <td data-label="<?= __('Status') ?>" class="t-center">
                                <span class="badge <?= $__rs === 'verified' ? 'badge-success' : ($__rs === 'rejected' ? 'badge-danger' : 'badge-warn') ?>">
                                    <?= e(['draft' => 'Draft', 'pending_verify' => 'Menunggu verifikasi', 'verified' => 'Terverifikasi', 'rejected' => 'Ditolak'][$__rs] ?? $__rs) ?>
                                </span>
                            </td>
                            <td data-label="" class="t-right">
                                <div class="btn-group" style="justify-content:flex-end;gap:8px;">
                                    <a class="btn btn-outline btn-xs" href="<?= url('reconciliation/' . (int) $item['recon_id']) ?>"><?= icon('eye') ?> Detail</a>
                                    <button type="button" class="btn btn-sm js-audit-open"
                                            data-action="<?= url('audit/' . (int) $item['id'] . '/check') ?>"
                                            data-code="<?= e($item['code']) ?>" data-name="<?= e($item['name']) ?>"
                                            data-group="<?= e($item['group_name']) ?>"
                                            data-expected="<?= can_see_stock_qty() ? (int) $item['expected_qty'] : '—' ?>"
                                            data-counted="<?= $item['recon_counted'] === null ? '—' : (int) $item['recon_counted'] ?>">
                                        <?= icon('clipboard-check') ?> Audit
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?= View::partial('partials/pagination', ['paginator' => $recheck, 'query' => array_filter(['tab' => $__at, 'group_id' => $filters['group_id'][0] ?? ''])]) ?>
</div>
<?php endif; ?>

<!-- ====================================================================== -->
<!-- Riwayat Audit Terakhir                                                 -->
<!-- ====================================================================== -->
<div class="card mt-2">
    <div class="card-toolbar" style="display:flex;align-items:center;justify-content:space-between;gap:.75rem;flex-wrap:wrap;padding:.85rem 1.15rem;border-bottom:1px solid var(--border);">
        <h3 style="font-size:1rem;font-weight:700;margin:0;display:flex;align-items:center;gap:.5rem;"><?= icon('clock') ?> Audit terakhir</h3>
        <div class="spacer"></div>
        <a class="btn btn-ghost btn-xs" href="<?= url('audit/history') ?>">Semua riwayat</a>
    </div>
    <div class="table-wrap">
        <table class="data">
            <thead>
                <tr><th><?= __('Item') ?></th><th class="col-hide-tablet"><?= __('Group') ?></th><th class="col-hide-tablet t-center"><?= __('Hasil') ?></th><th class="col-hide-tablet"><?= __('Auditor') ?></th><th class="col-hide-tablet"><?= __('Waktu') ?></th></tr>
            </thead>
            <tbody>
                <?php if ($history['items'] === []): ?>
                    <tr><td colspan="5" class="faint text-center" style="padding:1.2rem;"><?= __('Belum ada audit.') ?></td></tr>
                <?php else: ?>
                    <?php foreach ($history['items'] as $audit): ?>
                        <tr>
                            <td data-label="<?= __('Item') ?>"><?= e($audit['item_code']) ?> <?= e($audit['item_name']) ?></td>
                            <td data-label="<?= __('Group') ?>" class="col-hide-tablet"><?= e($audit['group_name']) ?></td>
                            <td data-label="<?= __('Hasil') ?>" class="col-hide-tablet t-center">
                                <span class="badge <?= $audit['result'] === 'match' ? 'badge-success' : 'badge-danger' ?>">
                                    <?= $audit['result'] === 'match' ? __('Sesuai') : __('Selisih') ?>
                                </span>
                            </td>
                            <td data-label="<?= __('Auditor') ?>" class="col-hide-tablet"><?= e($audit['auditor_name'] ?: '-') ?></td>
                            <td data-label="<?= __('Waktu') ?>" class="col-hide-tablet text-sm faint nowrap"><?= e(format_date($audit['audited_at'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ====================================================================== -->
<!-- Modal Audit inline (menggantikan lompat halaman /audit/{id}/check)     -->
<!-- ====================================================================== -->
<div class="sf-modal-backdrop" id="audit-modal" role="dialog" aria-modal="true" aria-labelledby="audit-modal-title">
    <div class="sf-modal">
        <div class="sf-modal-head">
            <h3 id="audit-modal-title"><?= icon('clipboard-check') ?> <?= __('Input Hasil Pengecekan') ?></h3>
            <button type="button" class="sf-modal-x" data-sf-close aria-label="<?= __('Tutup') ?>">&times;</button>
        </div>
        <form method="post" action="<?= url('audit/0/check') ?>">
            <?= csrf_field() ?>
            <div class="sf-modal-body">
                <div class="sf-ctx">
                    <span><?= __('Kode') ?>: <strong id="audit-modal-code">—</strong></span>
                    <span><?= __('Nama') ?>: <strong id="audit-modal-name">—</strong></span>
                    <span>Group: <strong id="audit-modal-group">—</strong></span>
                </div>
                <div class="sf-compare">
                    <?php if (can_see_stock_qty()): ?>
                        <div><div class="k"><?= __('Qty Sistem') ?></div><div class="v" id="audit-modal-expected">—</div></div>
                    <?php endif; ?>
                    <div><div class="k"><?= __('Hasil Hitung Observer') ?></div><div class="v" id="audit-modal-counted">—</div></div>
                </div>
                <div class="form-group mb-md">
                    <label for="audit-modal-qty" class="font-semibold text-xs uppercase tracking-wider"><?= __('Jumlah Hasil Audit Fisik') ?> <span class="text-danger">*</span></label>
                    <input type="number" id="audit-modal-qty" name="audited_qty" min="0" required
                           inputmode="numeric" style="font-size:1.25rem;font-weight:700;padding:.6rem .8rem;font-family:var(--font-mono);">
                </div>
                <div class="sf-delta-chip" id="audit-modal-delta" <?= can_see_stock_qty() ? '' : 'hidden' ?>>—</div>
                <div class="form-group">
                    <label for="audit-modal-note" class="font-semibold text-xs uppercase tracking-wider"><?= __('Catatan Audit') ?></label>
                    <textarea id="audit-modal-note" name="note" maxlength="255" rows="2"
                              placeholder="<?= __('Keterangan hasil pengecekan (opsional)') ?>"></textarea>
                </div>
            </div>
            <div class="sf-modal-foot">
                <button type="button" class="btn btn-outline btn-sm" data-sf-close><?= __('Batal') ?></button>
                <button type="submit" class="btn btn-brand btn-sm"><?= icon('check') ?> <?= __('Simpan Hasil Audit') ?></button>
            </div>
        </form>
    </div>
</div>


<!-- Bottom Action Bar (Mobile/Tablet) -->
<div class="bottom-action-bar">
    <button type="button" class="btn btn-primary" data-scan-open style="flex: 1;">
        <?= icon('scan') ?> <?= __('Scan Barcode') ?>
    </button>
</div>

<!-- Modal scanner barcode kamera -->
<div class="modal-backdrop" data-scan-modal role="dialog" aria-modal="true" aria-label="<?= __('Scan barcode') ?>">
    <div class="modal">
        <div class="modal-header">
            <h3><?= icon('scan') ?> <?= __('Scan Barcode / QR') ?></h3>
            <div class="spacer"></div>
            <button type="button" class="icon-btn" data-scan-close aria-label="<?= __('Tutup') ?>"><?= icon('x') ?></button>
        </div>
        <div class="modal-body">
            <div id="scanner-viewport"></div>
            <p class="scanner-hint"><?= __('Arahkan kamera ke barcode atau QR code untuk memeriksa item audit.') ?></p>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    // Audit-specific barcode handler (USB scanner & Camera scanner)
    window.stoHandleBarcodeScan = function (code) {
        code = (code || '').trim();
        if (!code) return;
        if (typeof showToast === 'function') {
            showToast('Mencari ' + code + '…', 'info');
        } else if (typeof sfToast === 'function') {
            sfToast('Mencari ' + code + '…', 'info');
        }

        // 1. Cari dulu pada baris tabel di halaman aktif
        var targetBtn = null;
        var rows = document.querySelectorAll('table.data tbody tr');
        for (var i = 0; i < rows.length; i++) {
            var btn = rows[i].querySelector('.js-audit-open');
            if (btn) {
                var c = (btn.getAttribute('data-code') || '').toLowerCase();
                if (c === code.toLowerCase()) {
                    targetBtn = btn;
                    break;
                }
            }
        }

        if (targetBtn) {
            targetBtn.click();
            if (typeof showToast === 'function') showToast('Item ' + code + ' ditemukan', 'success');
            return;
        }

        // 2. Lookup via /items/lookup bila tidak ada di tabel aktif
        fetch('/items/lookup?code=' + encodeURIComponent(code), {
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin'
        })
        .then(function (res) { return res.json(); })
        .then(function (data) {
            if (!data || !data.found) {
                var msg = data && data.message ? data.message : 'Item ' + code + ' tidak ditemukan.';
                if (typeof showToast === 'function') showToast(msg, 'warning');
                else alert(msg);
                return;
            }
            var it = data.item;
            var m = document.getElementById('audit-modal');
            if (!m) return;
            var form = m.querySelector('form');
            form.setAttribute('action', '/audit/' + it.id + '/check');
            var set = function (sel, val, isText) {
                var el = m.querySelector(sel); if (!el) return;
                if (isText) el.textContent = val; else el.value = val;
            };
            set('#audit-modal-code', it.code, true);
            set('#audit-modal-name', it.name, true);
            set('#audit-modal-group', it.group, true);
            set('#audit-modal-expected', it.expected_qty !== null ? it.expected_qty : '—', true);
            set('#audit-modal-counted', it.counted_qty !== null ? it.counted_qty : '—', true);
            var ai = m.querySelector('#audit-modal-qty');
            if (ai) ai.value = it.counted_qty !== null ? it.counted_qty : '';
            var note = m.querySelector('#audit-modal-note'); if (note) note.value = '';

            var exp = it.expected_qty !== null ? parseInt(it.expected_qty, 10) : NaN;
            var q = it.counted_qty !== null ? parseInt(it.counted_qty, 10) : NaN;
            var out = m.querySelector('#audit-modal-delta');
            if (out) {
                if (isNaN(exp) || isNaN(q)) {
                    out.textContent = '—';
                    out.className = 'sf-delta-chip';
                } else {
                    var d = q - exp;
                    out.textContent = (d > 0 ? '+' : '') + d + ' unit (' + q + ' vs sistem ' + exp + ')';
                    out.className = 'sf-delta-chip ' + (d === 0 ? 'is-zero' : (d > 0 ? 'is-pos' : 'is-neg'));
                }
            }
            if (typeof openModal === 'function') openModal('audit-modal');
            else m.classList.add('is-open');
            if (typeof showToast === 'function') showToast('Item ' + it.code + ' dibuka untuk audit', 'success');
        })
        .catch(function () {
            if (typeof showToast === 'function') showToast('Gagal memuat item ' + code, 'danger');
        });
    };
});
</script>

<?php
$cfLabels = [
    'code' => __('Kode'), 'name' => __('Nama Item'), 'group' => __('Group'), 'location' => __('Lokasi'),
    'expected' => __('Stok Sistem'), 'counted' => __('Hitung'), 'audit' => __('Status Audit'), 'status' => __('Status')
];
$cfDisplay = [
    'unaudited' => 'Belum audit', 'audited' => 'Sudah audit', 'sample' => 'Sampel', 'recheck' => 'Cek ulang',
    'draft' => 'Draft', 'pending_verify' => 'Menunggu verifikasi', 'verified' => 'Terverifikasi', 'rejected' => 'Ditolak'
];
$cfForm = 'audit-filter';
$cfDistinct = url('items/distinct');
require __DIR__ . '/../partials/colfilter.php';
?>
