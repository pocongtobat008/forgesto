<?php
/**
 * @var array $logs
 * @var array $filters
 * @var array $advCols
 * @var array $advNumeric
 * @var int   $advMax
 */
$__expHasFilter = ! empty($filters['search']) || ! empty($filters['col']) || ! empty($filters['adv']);
$cfCol = $filters['col'] ?? [];
$xfBtn = static fn (string $k, string $lbl): string => '<button type="button" class="xf-btn' . (! empty($cfCol[$k]) ? ' is-active' : '') . '" data-xf="' . $k . '" aria-label="' . e(__('Filter kolom :l', ['l' => __($lbl)])) . '" aria-haspopup="dialog" title="Filter">' . icon('filter') . '</button>';
?>
<div class="page-head">
    <div>
        <h1><?= __('Riwayat Export') ?></h1>
        <p>Export rekonsiliasi untuk impor SAP. Unduh ulang file kapan saja selama masih tersimpan.</p>
    </div>
</div>

<div class="card">
    <div class="card-toolbar" style="display:flex;align-items:center;justify-content:space-between;gap:.75rem;flex-wrap:wrap;padding:.85rem 1.15rem;border-bottom:1px solid var(--border);">
        <form method="get" action="<?= url('exports') ?>" id="exp-filter" style="display:flex;align-items:center;flex:1;min-width:240px;gap:.65rem;margin:0;" autocomplete="off">
            <?php foreach (($filters['col'] ?? []) as $ck => $cvals): ?><?php foreach ((array) $cvals as $cv): ?><input type="hidden" name="col[<?= e($ck) ?>][]" value="<?= e($cv) ?>" class="col-hidden"><?php endforeach; ?><?php endforeach; ?>
            <div class="form-group is-search" style="flex:1;max-width:380px;margin:0;">
                <input type="search" id="exp-search" name="search" value="<?= e($filters['search'] ?? '') ?>"
                       placeholder="<?= __('Cari nama file, group, atau user...') ?>"
                       data-filter-live autocomplete="off" enterkeyhint="search" style="width:100%;min-height:38px;">
            </div>
            <span class="sf-filter-count" aria-live="polite"></span>
        </form>

        <div class="table-toolbar" style="margin-left:auto;display:flex;gap:.55rem;align-items:center;">
            <button type="button" class="btn btn-info btn-icon-only" id="exports-adv-btn" aria-label="<?= __('Buka pencarian lanjutan') ?>" title="<?= __('Pencarian lanjutan') ?>"><?= icon('filter') ?></button>
            <?php if ($__expHasFilter): ?>
                <a class="btn btn-ghost btn-icon-only col-clear-all" href="<?= url('exports') ?>" aria-label="<?= __('Hapus semua filter') ?>" title="<?= __('Hapus semua filter') ?>"><?= icon('x') ?></a>
            <?php endif; ?>
        </div>
    </div>

    <?php
    /* LF-BAR: satu pencarian lanjutan (pola Data STO) */
    $__adv = $filters['adv'] ?? [];
    $asForm    = 'exp-filter';
    $asCols    = $advCols ?? [];
    $asCurrent = $__adv;
    $asNumeric = $advNumeric ?? [];
    $asMax     = $advMax ?? 12;
    $asToggleId = 'exports-adv-btn';
    require __DIR__ . '/../partials/advanced-search.php';
    ?>

    <div class="table-wrap">
        <table class="data" id="exports-table" data-colmgr-key="exports-v1" data-colmgr-prefix="exports">
            <thead>
                <tr>
                    <th data-col="file" class="xf-th"><span class="xf-label"><?= __('File') ?></span><?= $xfBtn('file', 'File') ?></th>
                    <th data-col="group" class="xf-th"><span class="xf-label"><?= __('Group') ?></span><?= $xfBtn('group', 'Group') ?></th>
                    <th data-col="lines" class="t-center xf-th"><span class="xf-label"><?= __('Baris') ?></span><?= $xfBtn('lines', 'Baris') ?></th>
                    <th data-col="status" class="t-center xf-th"><span class="xf-label"><?= __('Status') ?></span><?= $xfBtn('status', 'Status') ?></th>
                    <th data-col="progress"><?= __('Progres') ?></th>
                    <th data-col="user" class="xf-th"><span class="xf-label"><?= __('Oleh') ?></span><?= $xfBtn('user', 'Oleh') ?></th>
                    <th data-col="time" class="xf-th"><span class="xf-label"><?= __('Waktu') ?></span><?= $xfBtn('time', 'Waktu') ?></th>
                    <th data-col="download" class="t-center"><?= __('Unduh') ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if ($logs === []): ?>
                    <tr><td colspan="8" class="text-center faint" style="padding:1.5rem;"><?= __('Belum ada riwayat export.') ?></td></tr>
                <?php else: ?>
                    <?php $paginator = $logs; $logs = $paginator['items']; ?>
                    <?php foreach ($logs as $log): ?>
                        <?php $badge = match ($log['status']) {
                            'success' => 'badge-success', 'partial' => 'badge-warning', 'failed' => 'badge-danger', default => 'badge-info',
                        }; ?>
                        <tr>
                            <td class="cell-title" style="max-width:240px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="<?= e($log['filename'] ?: '-') ?>"><?= e($log['filename'] ?: '-') ?></td>
                            <td><?= e($log['group_code'] ?: 'Semua') ?></td>
                            <td class="t-center mono tabular"><?= (int) $log['total_rows'] ?></td>
                            <td class="t-center"><span class="badge <?= $badge ?>"><?= e(ucfirst((string) $log['status'])) ?></span></td>
                            <td class="t-center" style="min-width:110px;">
                                <div class="progress-mini" role="progressbar" aria-valuenow="<?= (int) $log['progress'] ?>" aria-valuemin="0" aria-valuemax="100"><span style="width:<?= max(0, min(100, (int) $log['progress'])) ?>%"></span></div>
                                <span class="text-sm faint"><?= (int) $log['progress'] ?>%</span>
                            </td>
                            <td class="text-sm"><?= e($log['user_name'] ?: '-') ?></td>
                            <td class="text-sm faint"><?= e(format_date($log['created_at'])) ?></td>
                            <td class="t-center">
                                <?php if (! empty($log['stored_path'])): ?>
                                    <a class="btn btn-sm btn-outline" href="<?= url('exports/' . (int) $log['id'] . '/download') ?>"><?= icon('download') ?> Unduh</a>
                                <?php else: ?>
                                    <span class="faint text-sm">-</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?= \App\Core\View::partial('partials/pagination', ['paginator' => $paginator, 'query' => array_diff_key($_GET, ['page' => 1])]) ?>
</div>

<?php
$cfLabels = ['file' => __('File'), 'group' => __('Group'), 'lines' => __('Baris'), 'status' => __('Status'), 'user' => __('Oleh'), 'time' => __('Waktu')];
$cfDisplay = ['success' => 'Berhasil', 'partial' => 'Sebagian', 'failed' => 'Gagal', 'processing' => 'Proses'];
$cfForm = 'exp-filter';
$cfDistinct = url('exports/distinct');
require __DIR__ . '/../partials/colfilter.php';
?>
