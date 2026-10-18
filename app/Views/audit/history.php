<?php
/**
 * @var array $audits
 * @var array $filters
 * @var array $groups
 */
use App\Core\View;
?>
<div class="page-head">
    <div>
        <h1><?= __('Riwayat Audit') ?></h1>
        <p><?= __('Seluruh pengecekan yang sudah dilakukan beserta hasilnya.') ?></p>
    </div>
    <div class="spacer"></div>
    <a class="btn btn-ghost" href="<?= url('audit') ?>"><?= icon('arrow-left') ?> Antrean Audit</a>
</div>

<div class="card">
    <div class="card-toolbar" style="display:flex;align-items:center;justify-content:space-between;gap:.75rem;flex-wrap:wrap;padding:.85rem 1.15rem;border-bottom:1px solid var(--border);">
        <form method="get" action="<?= url('audit/history') ?>" class="filters" id="hist-filter" style="display:flex;align-items:center;gap:.75rem;flex-wrap:wrap;margin:0;">
            <?php foreach (($filters['col'] ?? []) as $ck => $cvals): ?><?php foreach ((array) $cvals as $cv): ?><input type="hidden" name="col[<?= e($ck) ?>][]" value="<?= e($cv) ?>" class="col-hidden"><?php endforeach; ?><?php endforeach; ?>
            <div class="form-group" style="margin:0;">
                <select id="group_id" name="group_id" data-auto-submit aria-label="<?= __('Group') ?>" style="min-height:38px;">
                    <option value=""><?= __('Semua group') ?></option>
                    <?php foreach ($groups as $group): ?>
                        <option value="<?= (int) $group['id'] ?>" <?= (int) ($filters['group_id'] ?? 0) === (int) $group['id'] ? 'selected' : '' ?>>
                            <?= e($group['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group" style="margin:0;">
                <select id="result" name="result" data-auto-submit aria-label="<?= __('Hasil audit') ?>" style="min-height:38px;">
                    <option value=""><?= __('Semua hasil') ?></option>
                    <option value="match" <?= ($filters['result'] ?? '') === 'match' ? 'selected' : '' ?>><?= __('Sesuai') ?></option>
                    <option value="discrepancy" <?= ($filters['result'] ?? '') === 'discrepancy' ? 'selected' : '' ?>><?= __('Selisih') ?></option>
                </select>
            </div>
            <a class="btn btn-ghost btn-sm" href="<?= url('audit/history') ?>"><?= icon('x') ?> Reset</a>
        </form>
    </div>

    <div class="table-wrap">
        <table class="data" id="audit-history-table" data-colmgr-key="audit-hist-v1" data-colmgr-prefix="audit-hist"><caption class="sr-only">Riwayat audit</caption>
            <thead>
                <tr>
<?php $cfCol = $filters['col'] ?? []; $xfBtn = static fn (string $k, string $lbl): string => '<button type="button" class="xf-btn' . (! empty($cfCol[$k]) ? ' is-active' : '') . '" data-xf="' . $k . '" aria-label="' . e(__('Filter kolom :l', ['l' => __($lbl)])) . '" aria-haspopup="dialog" title="Filter">' . icon('filter') . '</button>'; ?>
                    <th data-col="item" class="xf-th"><span class="xf-label"><?= __('Item') ?></span><?= $xfBtn('item', 'Item') ?></th>
                    <th data-col="group" class="xf-th"><span class="xf-label"><?= __('Group') ?></span><?= $xfBtn('group', 'Group') ?></th>
                    <?php if (can_see_stock_qty()): ?><th data-col="expected" class="t-center xf-th"><span class="xf-label"><?= __('Sistem') ?></span><?= $xfBtn('expected', 'Sistem') ?></th><?php endif; ?>
                    <th data-col="counted" class="t-center xf-th"><span class="xf-label"><?= __('Hitung') ?></span><?= $xfBtn('counted', 'Hitung') ?></th>
                    <th data-col="audited" class="t-center xf-th"><span class="xf-label"><?= __('Audit') ?></span><?= $xfBtn('audited', 'Audit') ?></th>
                    <th data-col="result" class="t-center xf-th"><span class="xf-label"><?= __('Hasil') ?></span><?= $xfBtn('result', 'Hasil') ?></th>
                    <th data-col="auditor" class="xf-th"><span class="xf-label"><?= __('Auditor') ?></span><?= $xfBtn('auditor', 'Auditor') ?></th>
                    <th data-col="time" class="xf-th"><span class="xf-label"><?= __('Waktu') ?></span><?= $xfBtn('time', 'Waktu') ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if ($audits['items'] === []): ?>
                    <tr><td colspan="<?= can_see_stock_qty() ? 8 : 7 ?>" class="text-center faint" style="padding:1.5rem;"><?= __('Belum ada riwayat audit.') ?></td></tr>
                <?php else: ?>
                    <?php foreach ($audits['items'] as $audit): ?>
                        <tr>
                            <td>
                                <div class="cell-title"><?= e($audit['item_code']) ?></div>
                                <div class="cell-sub"><?= e($audit['item_name']) ?></div>
                            </td>
                            <td><?= e($audit['group_name']) ?></td>
                            <?php if (can_see_stock_qty()): ?><td class="t-center mono tabular"><?= (int) $audit['expected_qty'] ?></td><?php endif; ?>
                            <td class="t-center mono tabular"><?= (int) $audit['counted_qty'] ?></td>
                            <td class="t-center mono tabular"><?= (int) $audit['audited_qty'] ?></td>
                            <td class="t-center">
                                <span class="badge <?= $audit['result'] === 'match' ? 'badge-success' : 'badge-danger' ?>">
                                    <?= $audit['result'] === 'match' ? __('Sesuai') : __('Selisih') ?>
                                </span>
                                <?php if ((int) $audit['is_sample'] === 1): ?>
                                    <span class="badge"><?= __('sample') ?></span>
                                <?php endif; ?>
                            </td>
                            <td><?= e($audit['auditor_name'] ?: '-') ?></td>
                            <td class="text-sm faint nowrap"><?= e(format_date($audit['audited_at'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?= View::partial('partials/pagination', ['paginator' => $audits, 'query' => array_filter(['group_id' => $filters['group_id'] ?? '', 'result' => $filters['result'] ?? ''], static fn ($v) => $v !== '' && $v !== null)]) ?>
</div>
<?php
$cfLabels = ['item' => __('Item'), 'group' => __('Group'), 'result' => __('Hasil'), 'auditor' => __('Auditor'), 'expected' => __('Sistem'), 'counted' => __('Hitung'), 'audited' => __('Audit'), 'time' => __('Waktu')];
$cfDisplay = ['match' => 'Cocok', 'discrepancy' => 'Selisih'];
$cfForm = 'hist-filter';
$cfDistinct = url('audit/history/distinct');
require __DIR__ . '/../partials/colfilter.php';
?>
