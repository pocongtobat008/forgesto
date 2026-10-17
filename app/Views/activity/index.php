<?php
/**
 * @var array $logs
 * @var array $filters
 * @var array $groups
 * @var array $actions
 * @var array $advCols
 * @var array $advNumeric
 * @var int   $advMax
 */
use App\Core\View;

$actionBadge = static fn (string $action): string => match ($action) {
    'create'  => 'badge-success',
    'update'  => 'badge-info',
    'delete'  => 'badge-danger',
    'audit'   => 'badge-brand',
    'count'   => 'badge-warning',
    'unlock'  => 'badge-danger',
    'resolve' => 'badge-success',
    'import'  => 'badge-info',
    default   => '',
};
$__actHasFilter = ! empty($filters['search']) || ! empty($filters['col']) || ! empty($filters['adv']);
?>
<?= page_hero() ?>
<div class="page-head has-hero">
    <div>
        <h1><?= __('Aktivitas') ?></h1>
        <p><?= __('Jejak seluruh aksi yang dilakukan di dalam aplikasi.') ?></p>
    </div>
</div>

<div class="card">
    <div class="card-toolbar" style="display:flex;align-items:center;justify-content:space-between;gap:.75rem;flex-wrap:wrap;padding:.85rem 1.15rem;border-bottom:1px solid var(--border);">
        <form method="get" action="<?= url('activity') ?>" id="act-filter" style="display:flex;align-items:center;flex:1;min-width:240px;gap:.65rem;margin:0;" autocomplete="off">
            <?php foreach (($filters['col'] ?? []) as $ck => $cvals): ?><?php foreach ((array) $cvals as $cv): ?><input type="hidden" name="col[<?= e($ck) ?>][]" value="<?= e($cv) ?>" class="col-hidden"><?php endforeach; ?><?php endforeach; ?>
            <div class="form-group is-search" style="flex:1;max-width:380px;margin:0;">
                <input type="search" id="search" name="search" value="<?= e($filters['search'] ?? '') ?>"
                       placeholder="<?= __('Deskripsi atau nama user...') ?>"
                       data-filter-live autocomplete="off" enterkeyhint="search" style="width:100%;min-height:38px;">
            </div>
            <span class="sf-filter-count" aria-live="polite"></span>
        </form>

        <div class="table-toolbar" style="margin-left:auto;display:flex;gap:.55rem;align-items:center;">
            <button type="button" class="btn btn-info btn-icon-only" id="activity-adv-btn" aria-label="<?= __('Buka pencarian lanjutan') ?>" title="<?= __('Pencarian lanjutan') ?>"><?= icon('filter') ?></button>
            <?php if ($__actHasFilter): ?>
                <a class="btn btn-ghost btn-icon-only col-clear-all" href="<?= url('activity') ?>" aria-label="<?= __('Hapus semua filter') ?>" title="<?= __('Hapus semua filter') ?>"><?= icon('x') ?></a>
            <?php endif; ?>
        </div>
    </div>

    <?php
    /* LF-BAR: satu pencarian lanjutan (pola Data STO) */
    $__adv = $filters['adv'] ?? [];
    $asForm    = 'act-filter';
    $asCols    = $advCols ?? [];
    $asCurrent = $__adv;
    $asNumeric = $advNumeric ?? [];
    $asMax     = $advMax ?? 12;
    $asToggleId = 'activity-adv-btn';
    require __DIR__ . '/../partials/advanced-search.php';
    ?>

    <div class="table-wrap">
        <table class="data" id="activity-table" data-colmgr-key="activity-v1" data-colmgr-prefix="activity">
            <thead>
                <tr>
<?php
$cfCol = $filters['col'] ?? [];
$xfBtn = static fn (string $k, string $lbl): string => '<button type="button" class="xf-btn' . (! empty($cfCol[$k]) ? ' is-active' : '') . '" data-xf="' . $k . '" aria-label="' . e(__('Filter kolom :l', ['l' => __($lbl)])) . '" aria-haspopup="dialog" title="Filter">' . icon('filter') . '</button>';
?>
                    <th data-col="time" class="xf-th"><span class="xf-label"><?= __('Waktu') ?></span><?= $xfBtn('time', 'Waktu') ?></th>
                    <th data-col="user" class="xf-th"><span class="xf-label"><?= __('User') ?></span><?= $xfBtn('user', 'User') ?></th>
                    <th data-col="action" class="t-center xf-th"><span class="xf-label"><?= __('Aksi') ?></span><?= $xfBtn('action', 'Aksi') ?></th>
                    <th data-col="desc" class="col-hide-tablet xf-th"><span class="xf-label"><?= __('Deskripsi') ?></span><input type="search" form="act-filter" name="text[description]" value="<?= e($filters['text']['description'] ?? '') ?>" placeholder="<?= __('Cari teks...') ?>" class="xf-q" aria-label="<?= __('Cari deskripsi') ?>" style="max-width:100%"></th>
                    <th data-col="group" class="xf-th"><span class="xf-label"><?= __('Group') ?></span><?= $xfBtn('group', 'Group') ?></th>
                    <th data-col="ip" class="col-hide-tablet xf-th"><span class="xf-label"><?= __('IP') ?></span><input type="search" form="act-filter" name="text[ip]" value="<?= e($filters['text']['ip'] ?? '') ?>" placeholder="<?= __('Cari IP...') ?>" class="xf-q" aria-label="<?= __('Cari IP') ?>" style="max-width:100%"></th>
                </tr>
            </thead>
            <tbody>
                <?php if ($logs['items'] === []): ?>
                    <tr><td colspan="6" class="text-center faint" style="padding:1.5rem;"><?= __('Belum ada aktivitas.') ?></td></tr>
                <?php else: ?>
                    <?php foreach ($logs['items'] as $log): ?>
                        <tr>
                            <td data-label="<?= __('Waktu') ?>" class="text-sm faint nowrap"><?= e(format_date($log['created_at'])) ?></td>
                            <td data-label="<?= __('User') ?>">
                                <div class="cell-title"><?= e($log['user_name'] ?? 'Sistem') ?></div>
                                <div class="cell-sub"><?= e(role_label($log['user_role'] ?? null)) ?></div>
                            </td>
                            <td data-label="<?= __('Aksi') ?>" class="t-center"><span class="badge <?= $actionBadge((string) $log['action']) ?>"><?= e($log['action']) ?></span></td>
                            <td data-label="<?= __('Deskripsi') ?>" class="col-hide-tablet"><?= e($log['description'] ?? '-') ?></td>
                            <td data-label="<?= __('Group') ?>"><?= e($log['group_code'] ?? '—') ?></td>
                            <td data-label="<?= __('IP') ?>" class="col-hide-tablet mono text-xs faint"><?= e($log['ip_address'] ?? '-') ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?= View::partial('partials/pagination', [
        'paginator' => $logs,
        'query'     => array_filter(['group_id' => $filters['group_id'] ?? '', 'action' => $filters['action'] ?? '', 'search' => $filters['search'] ?? ''], static fn ($v) => $v !== '' && $v !== null),
    ]) ?>
</div>

<?php
$cfLabels = ['time' => __('Waktu'), 'user' => __('User'), 'action' => __('Aksi'), 'group' => __('Group')];
$cfDisplay = [];
$cfForm = 'act-filter';
$cfDistinct = url('activity/distinct');
require __DIR__ . '/../partials/colfilter.php';
?>
