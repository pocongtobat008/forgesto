<?php
/**
 * Ringkasan + tabel selisih per group. Dipakai index dan detail Tahun Buku.
 * @var array $rows  dari BookYearController::groupDiff()
 */
$rows = $rows ?? [];
$rp     = static fn ($n): string => 'Rp ' . number_format((float) $n, 0, ',', '.');
$rpS    = static function ($n): string {
    $v = (float) $n;
    return ($v < 0 ? '−' : ($v > 0 ? '+' : '')) . 'Rp ' . number_format(abs($v), 0, ',', '.');
};
$num    = static fn ($n): string => number_format((float) $n, 0, ',', '.');
$signed = static fn ($n): string => ((float) $n > 0 ? '+' : '') . number_format((float) $n, 0, ',', '.');
/* AUDIT-FIX: pakai token TEKS AA-safe. var(--danger)=3.76:1 & var(--info)=3.68:1
   gagal AA utk teks kecil; token *-text >= 4.5:1 di atas putih. */
$tone   = static fn ($n): string => (float) $n < 0 ? 'var(--danger-text)' : ((float) $n > 0 ? 'var(--info-text)' : 'var(--text-3)');

$tot = ['items' => 0.0, 'expected' => 0.0, 'counted' => 0.0, 'not_counted' => 0.0, 'diff_qty' => 0.0, 'diff_value' => 0.0, 'diff_items' => 0.0];
$groupsDiff = 0;
foreach ($rows as $r) {
    foreach ($tot as $k => $_unused) {
        $tot[$k] += (float) $r[$k];
    }
    if ((float) $r['diff_items'] > 0) {
        $groupsDiff++;
    }
}
$groupCount = count($rows);
?>
<?php $canQty = can_see_stock_qty(); ?>
<div class="grid <?= $canQty ? 'grid-4' : 'grid-2' ?>" style="margin-bottom:1rem;">
    <?php if ($canQty): ?>
    <div class="stat is-warning" style="--i:0">
        <span class="stat-label"><?= __('Selisih qty') ?></span>
        <div class="stat-value" style="color:<?= $tone($tot['diff_qty']) ?>;"><?= $signed($tot['diff_qty']) ?></div>
        <div class="stat-meta"><?= __('unit, seluruh group') ?></div>
    </div>
    <div class="stat is-info" style="--i:1">
        <span class="stat-label"><?= __('Nilai selisih') ?></span>
        <div class="stat-value" style="font-size:1.3rem;color:<?= $tone($tot['diff_value']) ?>;"><?= $rpS($tot['diff_value']) ?></div>
        <div class="stat-meta"><?= __('dihitung dari harga satuan') ?></div>
    </div>
    <?php endif; ?>
    <div class="stat is-brand" style="--i:2">
        <span class="stat-label"><?= __('Item berselisih') ?></span>
        <div class="stat-value"><?= $num($tot['diff_items']) ?></div>
        <div class="stat-meta"><?= __('dari') ?> <?= $num($tot['items']) ?> <?= __('item tercatat') ?></div>
    </div>
    <div class="stat is-success" style="--i:3">
        <span class="stat-label"><?= __('Group berselisih') ?></span>
        <div class="stat-value"><?= $groupsDiff ?></div>
        <div class="stat-meta"><?= __('dari') ?> <?= $groupCount ?> <?= __('group') ?></div>
    </div>
</div>

<div class="table-wrap">
    <table class="data"><caption class="sr-only">Group pada tahun buku</caption>
        <thead>
            <tr>
                <th><?= __('Group') ?></th>
                <th class="col-hide-tablet t-center"><?= __('Item') ?></th>
                <?php if ($canQty): ?>
                <th class="col-hide-tablet t-center"><?= __('Sistem') ?></th>
                <th class="col-hide-tablet t-center"><?= __('Hitung') ?></th>
                <th class="t-center"><?= __('Selisih qty') ?></th>
                <th class="t-right"><?= __('Selisih nilai') ?></th>
                <?php endif; ?>
                <th class="col-hide-tablet t-center"><?= __('Belum hitung') ?></th>
            </tr>
        </thead>
        <tbody>
        <?php if ($rows === []): ?>
            <tr><td colspan="<?= $canQty ? 7 : 3 ?>" class="text-center faint" style="padding:1.2rem;"><?= __('Belum ada data snapshot.') ?></td></tr>
        <?php else: ?>
            <?php foreach ($rows as $r): ?>
            <tr>
                <td data-label="<?= __('Group') ?>">
                    <div class="cell-title"><?= e((string) $r['group_name']) ?></div>
                    <?php if ((string) $r['group_code'] !== ''): ?>
                        <div class="cell-sub"><?= e((string) $r['group_code']) ?></div>
                    <?php endif; ?>
                </td>
                <td data-label="<?= __('Item') ?>" class="col-hide-tablet t-center mono tabular"><?= $num($r['items']) ?></td>
                <?php if ($canQty): ?>
                <td data-label="<?= __('Sistem') ?>" class="col-hide-tablet t-center mono tabular"><?= $num($r['expected']) ?></td>
                <td data-label="<?= __('Hitung') ?>" class="col-hide-tablet t-center mono tabular"><?= $num($r['counted']) ?></td>
                <td data-label="<?= __('Selisih qty') ?>" class="t-center mono tabular" style="color:<?= $tone($r['diff_qty']) ?>;font-weight:700;"><?= $signed($r['diff_qty']) ?></td>
                <td data-label="<?= __('Selisih nilai') ?>" class="t-right mono tabular" style="color:<?= $tone($r['diff_value']) ?>;"><?= $rpS($r['diff_value']) ?></td>
                <?php endif; ?>
                <td data-label="<?= __('Belum hitung') ?>" class="col-hide-tablet t-center mono tabular"><?= $num($r['not_counted']) ?></td>
            </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
        <?php if ($rows !== []): ?>
        <tfoot>
            <tr>
                <th><?= __('Total') ?></th>
                <th class="col-hide-tablet t-center mono tabular"><?= $num($tot['items']) ?></th>
                <?php if ($canQty): ?>
                <th class="col-hide-tablet t-center mono tabular"><?= $num($tot['expected']) ?></th>
                <th class="col-hide-tablet t-center mono tabular"><?= $num($tot['counted']) ?></th>
                <th class="t-center mono tabular" style="color:<?= $tone($tot['diff_qty']) ?>;"><?= $signed($tot['diff_qty']) ?></th>
                <th class="t-right mono tabular" style="color:<?= $tone($tot['diff_value']) ?>;"><?= $rpS($tot['diff_value']) ?></th>
                <?php endif; ?>
                <th class="col-hide-tablet t-center mono tabular"><?= $num($tot['not_counted']) ?></th>
            </tr>
        </tfoot>
        <?php endif; ?>
    </table>
</div>
