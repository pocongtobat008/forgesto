<?php
/**
 * Report satu tahun buku (detail).
 *
 * @var array $period
 * @var array $metrics
 * @var array $groups
 * @var array $items
 * @var array $statuses
 * @var string $label
 */
$period   = $period ?? [];
$metrics  = $metrics ?? [];
$groups   = $groups ?? [];
$items    = $items ?? [];
$statuses = $statuses ?? ['hitung' => [], 'audit' => []];
$label    = (string) ($label ?? '');

$rp = static fn ($n): string => 'Rp ' . number_format((float) $n, 0, ',', '.');
$rpS = static function ($n): string {
    $v = (float) $n;
    return ($v < 0 ? '−' : ($v > 0 ? '+' : '')) . 'Rp ' . number_format(abs($v), 0, ',', '.');
};
$num = static fn ($n): string => number_format((float) $n, 0, ',', '.');
$signed = static fn ($n): string => ((float) $n > 0 ? '+' : '') . number_format((float) $n, 0, ',', '.');
$tone = static fn ($n): string => (float) $n < 0 ? 'var(--danger-text)' : ((float) $n > 0 ? 'var(--info-text)' : 'var(--text-3)');

$closed = (string) ($period['status'] ?? 'open') === 'closed';
$source = (string) ($metrics['source'] ?? 'empty');
$canExport = function_exists('can') ? can('export_data') : false;
$canQty    = can_see_stock_qty();

$countedPct = (float) ($metrics['counted_pct'] ?? 0);
$auditPct   = (float) ($metrics['audit_pct'] ?? 0);

$statusTone = static function (string $s): string {
    return match ($s) {
        'posted', 'submitted', 'audited', 'reconciled' => 'badge-success',
        'pending', 'open', '' => 'badge-warning',
        default => 'badge-muted',
    };
};
?>
<?= page_hero(['title' => 'Report']) ?>

<div class="page-head has-hero">
    <div>
        <h1><?= e($label) ?></h1>
        <p>
            <?= $closed ? __('Ditutup') : __('Berjalan') ?>
            · <?= $source === 'snapshot' ? __('snapshot periode (read-only)') : ($source === 'live' ? __('data berjalan') : __('belum ada data')) ?>
        </p>
    </div>
    <div class="spacer"></div>
    <div class="flex gap-sm items-center no-print" style="flex-wrap:wrap;">
        <a class="btn btn-outline" href="<?= url('reports') ?>" style="min-height:42px;">
            <?= icon('arrow-left') ?> <?= __('Semua report') ?>
        </a>
        <button type="button" class="btn btn-outline" onclick="window.print()" style="min-height:42px;">
            <?= icon('printer') ?> <?= __('Cetak') ?>
        </button>
        <?php if ($canExport): ?>
            <a class="btn btn-primary" href="<?= url('reports/' . (int) ($period['id'] ?? 0) . '/export') ?>" style="min-height:42px;">
                <?= icon('download') ?> <?= __('Export CSV') ?>
            </a>
        <?php endif; ?>
        <a class="btn btn-ghost" href="<?= url('tahun-buku/' . (int) ($period['id'] ?? 0)) ?>" style="min-height:42px;">
            <?= icon('archive') ?> <?= __('Tahun Buku') ?>
        </a>
    </div>
</div>

<!-- ============================ KPI ============================ -->
<div class="grid grid-4" style="margin-bottom:1rem;">
    <div class="stat is-info" style="--i:0">
        <div class="stat-icon"><?= icon('scale') ?></div>
        <div class="stat-label"><?= __('Nilai selisih') ?></div>
        <div class="stat-value" style="color:<?= $tone($metrics['diff_value'] ?? 0) ?>;"><?= $rpS($metrics['diff_value'] ?? 0) ?></div>
        <div class="stat-meta"><?= __('dari harga satuan') ?></div>
    </div>
    <div class="stat is-warning" style="--i:1">
        <div class="stat-icon"><?= icon('trending-up') ?></div>
        <div class="stat-label"><?= __('Selisih qty') ?></div>
        <div class="stat-value" style="color:<?= $tone($metrics['diff_qty'] ?? 0) ?>;"><?= $signed($metrics['diff_qty'] ?? 0) ?></div>
        <div class="stat-meta"><?= __('unit, seluruh group') ?></div>
    </div>
    <div class="stat is-brand" style="--i:2">
        <div class="stat-icon"><?= icon('package') ?></div>
        <div class="stat-label"><?= __('Item berselisih') ?></div>
        <div class="stat-value"><?= $num($metrics['diff_items'] ?? 0) ?></div>
        <div class="stat-meta"><?= __('dari') ?> <?= $num($metrics['items'] ?? 0) ?> <?= __('item tercatat') ?></div>
    </div>
    <div class="stat is-danger" style="--i:3">
        <div class="stat-icon"><?= icon('layers') ?></div>
        <div class="stat-label"><?= __('Group berselisih') ?></div>
        <div class="stat-value"><?= $num($metrics['groups_diff'] ?? 0) ?></div>
        <div class="stat-meta"><?= __('dari') ?> <?= $num($metrics['groups'] ?? 0) ?> <?= __('group') ?></div>
    </div>
</div>

<!-- ======================= PROGRESS & STATUS ======================= -->
<section class="card" style="margin-bottom:1rem;">
    <div class="card-header">
        <h2><?= icon('activity') ?> <?= __('Progres & status') ?></h2>
        <div class="spacer"></div>
        <span class="badge <?= $closed ? 'badge-success' : 'badge-warning' ?>">
            <?= $closed ? __('Ditutup') : __('Berjalan') ?>
        </span>
    </div>
    <div class="card-body">
        <div class="grid grid-2" style="gap:1rem;margin-bottom:1rem;">
            <div>
                <div class="flex items-center" style="justify-content:space-between;margin-bottom:.35rem;">
                    <span style="font-size:.8rem;font-weight:600;"><?= __('Progress hitung') ?></span>
                    <strong style="font-variant-numeric:tabular-nums;"><?= number_format($countedPct, 1, ',', '.') ?>%</strong>
                </div>
                <div style="height:9px;border-radius:999px;background:color-mix(in srgb, var(--brand-500) 14%, transparent);overflow:hidden;">
                    <div style="height:100%;width:<?= max(0, min(100, $countedPct)) ?>%;border-radius:999px;background:var(--brand-500);"></div>
                </div>
                <div class="muted" style="font-size:.75rem;margin-top:.35rem;">
                    <?= $num(($metrics['items'] ?? 0) - ($metrics['not_counted'] ?? 0)) ?> / <?= $num($metrics['items'] ?? 0) ?> <?= __('item sudah dihitung') ?>
                    · <?= $num($metrics['posted'] ?? 0) ?> <?= __('terposting') ?>
                </div>
            </div>
            <div>
                <div class="flex items-center" style="justify-content:space-between;margin-bottom:.35rem;">
                    <span style="font-size:.8rem;font-weight:600;"><?= __('Progress audit') ?></span>
                    <strong style="font-variant-numeric:tabular-nums;"><?= number_format($auditPct, 1, ',', '.') ?>%</strong>
                </div>
                <div style="height:9px;border-radius:999px;background:color-mix(in srgb, var(--success) 16%, transparent);overflow:hidden;">
                    <div style="height:100%;width:<?= max(0, min(100, $auditPct)) ?>%;border-radius:999px;background:var(--success);"></div>
                </div>
                <div class="muted" style="font-size:.75rem;margin-top:.35rem;">
                    <?= $num($metrics['audited'] ?? 0) ?> / <?= $num($metrics['items'] ?? 0) ?> <?= __('item sudah diaudit') ?>
                </div>
            </div>
        </div>

        <div class="flex gap-sm" style="flex-wrap:wrap;">
            <?php foreach (($statuses['hitung'] ?? []) as $s): ?>
                <span class="badge <?= $statusTone((string) $s['status']) ?>">
                    <?= e((string) $s['status']) ?>: <?= $num($s['c']) ?>
                </span>
            <?php endforeach; ?>
            <span style="width:1px;height:18px;background:var(--border);"></span>
            <?php foreach (($statuses['audit'] ?? []) as $s): ?>
                <span class="badge <?= $statusTone((string) $s['status']) ?>">
                    audit <?= e((string) $s['status']) ?>: <?= $num($s['c']) ?>
                </span>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<?php if (! $canQty): ?>
<section class="card">
    <div class="card-body" style="padding:1.6rem;">
        <div class="empty-state">
            <div class="empty-icon"><?= icon('lock') ?></div>
            <h3><?= __('Akses terbatas') ?></h3>
            <p><?= __('Qty sistem dan nilai selisih hanya dapat dilihat oleh Admin STO / Power Admin.') ?></p>
        </div>
    </div>
</section>
<?php else: ?>
<!-- ========================= SELISIH / GROUP ========================= -->
<section class="card" style="margin-bottom:1rem;">
    <div class="card-header">
        <h2><?= icon('layers') ?> <?= __('Selisih per group') ?></h2>
        <div class="spacer"></div>
        <span class="badge badge-muted"><?= count($groups) ?> <?= __('group') ?></span>
    </div>
    <div class="table-wrap">
        <table class="data">
            <thead>
                <tr>
                    <th><?= __('Group') ?></th>
                    <th class="col-hide-tablet t-center"><?= __('Item') ?></th>
                    <th class="t-right col-hide-tablet"><?= __('Sistem') ?></th>
                    <th class="t-right col-hide-tablet"><?= __('Hitung') ?></th>
                    <th class="t-right"><?= __('Selisih qty') ?></th>
                    <th class="t-right"><?= __('Nilai selisih') ?></th>
                    <th class="col-hide-tablet t-center"><?= __('Item selisih') ?></th>
                </tr>
            </thead>
            <tbody>
            <?php if ($groups === []): ?>
                <tr><td colspan="7" class="muted t-center"><?= __('Belum ada data.') ?></td></tr>
            <?php endif; ?>
            <?php foreach ($groups as $g): ?>
                <tr>
                    <td>
                        <div class="cell-title"><?= e((string) $g['group_name']) ?></div>
                        <?php if (! empty($g['group_code'])): ?>
                            <div class="cell-sub"><?= e((string) $g['group_code']) ?></div>
                        <?php endif; ?>
                    </td>
                    <td class="col-hide-tablet t-center"><?= $num($g['items']) ?></td>
                    <td class="t-right col-hide-tablet"><?= $num($g['expected']) ?></td>
                    <td class="t-right col-hide-tablet"><?= $num($g['counted']) ?></td>
                    <td class="t-right" style="font-weight:600;color:<?= $tone($g['diff_qty']) ?>;white-space:nowrap;"><?= $signed($g['diff_qty']) ?></td>
                    <td class="t-right" style="font-weight:700;color:<?= $tone($g['diff_value']) ?>;white-space:nowrap;"><?= $rpS($g['diff_value']) ?></td>
                    <td class="col-hide-tablet t-center"><?= $num($g['diff_items']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<!-- ======================== TOP SELISIH ITEM ======================== -->
<section class="card">
    <div class="card-header">
        <h2><?= icon('alert-circle') ?> <?= __('Item dengan selisih terbesar') ?></h2>
        <div class="spacer"></div>
        <span class="badge badge-muted"><?= count($items) ?> <?= __('item') ?></span>
    </div>
    <div class="table-wrap">
        <table class="data">
            <thead>
                <tr>
                    <th><?= __('Kode') ?></th>
                    <th><?= __('Nama item') ?></th>
                    <th class="col-hide-tablet"><?= __('Group') ?></th>
                    <th class="t-right"><?= __('Sistem') ?></th>
                    <th class="t-right"><?= __('Hitung') ?></th>
                    <th class="t-right"><?= __('Selisih') ?></th>
                    <th class="t-right"><?= __('Nilai selisih') ?></th>
                </tr>
            </thead>
            <tbody>
            <?php if ($items === []): ?>
                <tr><td colspan="7" class="muted t-center"><?= __('Tidak ada item berselisih.') ?></td></tr>
            <?php endif; ?>
            <?php foreach ($items as $it): ?>
                <tr>
                    <td class="mono" style="white-space:nowrap;"><?= e((string) $it['item_code']) ?></td>
                    <td>
                        <div class="cell-title"><?= e((string) $it['item_name']) ?></div>
                        <div class="cell-sub"><?= e((string) ($it['unit'] ?? '')) ?></div>
                    </td>
                    <td class="col-hide-tablet"><?= e((string) $it['group_name']) ?></td>
                    <td class="t-right"><?= $num($it['expected_qty']) ?></td>
                    <td class="t-right"><?= $it['counted_qty'] === null ? '—' : $num($it['counted_qty']) ?></td>
                    <td class="t-right" style="font-weight:600;color:<?= $tone($it['difference']) ?>;white-space:nowrap;"><?= $signed($it['difference']) ?></td>
                    <td class="t-right" style="font-weight:700;color:<?= $tone($it['diff_value']) ?>;white-space:nowrap;"><?= $rpS($it['diff_value']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<?php endif; ?>
