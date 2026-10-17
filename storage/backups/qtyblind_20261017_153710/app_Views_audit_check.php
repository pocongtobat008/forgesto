<?php
/**
 * @var array $item
 * @var int $minPercent
 */
$expected = (int) $item['expected_qty'];
$counted = (int) ($item['counted_qty'] ?? 0);
$diff = $counted - $expected;
$diffClass = $diff === 0 ? 'is-zero' : ($diff > 0 ? 'is-pos' : 'is-neg');
?>
<div class="page-head">
    <div>
        <h1>Pengecekan <span class="kbd"><?= e($item['code']) ?></span></h1>
        <p><?= e($item['name']) ?> &middot; <?= e($item['group_name']) ?></p>
    </div>
    <div class="spacer"></div>
    <a class="btn btn-ghost" href="<?= url('audit') ?>"><?= icon('arrow-left') ?> Kembali</a>
</div>

<div class="card" style="max-width:680px;">
    <div class="card-header"><h2><?= icon('audit') ?> <?= __('Input Hasil Pengecekan') ?></h2></div>
    <div class="card-body">
        <div class="compare">
            <div class="compare-card">
                <div class="cc-label"><?= __('Qty sistem') ?></div>
                <div class="cc-value"><?= can_see_stock_qty() ? $expected : '<span class="badge badge-brand">***</span>' ?></div>
            </div>
            <div class="compare-card">
                <div class="cc-label"><?= __('Hasil hitung observer') ?></div>
                <div class="cc-value"><?= $counted ?></div>
            </div>
            <div class="compare-card is-accent">
                <div class="cc-label"><?= __('Selisih hitung') ?></div>
                <div class="cc-value"><?= can_see_stock_qty() ? (($diff > 0 ? '+' : '') . $diff) : '<span class="badge badge-brand">***</span>' ?></div>
                <?php if (can_see_stock_qty()): ?>
                    <div class="mt-1"><span class="diff-pill <?= $diffClass ?>"><?= $diff === 0 ? 'cocok' : ($diff > 0 ? 'lebih' : 'kurang') ?></span></div>
                <?php endif; ?>
            </div>
        </div>

        <?php if (! empty($item['unit'])): ?>
            <p class="text-sm faint mb-2">Satuan: <strong class="muted"><?= e($item['unit']) ?></strong></p>
        <?php endif; ?>

        <?php if (! empty($item['condition_note'])): ?>
            <div class="alert alert-info"><?= icon('info') ?><div>Catatan observer: <?= e($item['condition_note']) ?></div></div>
        <?php endif; ?>

        <form method="post" action="<?= url('audit/' . (int) $item['id'] . '/check') ?>">
            <?= csrf_field() ?>

            <div class="form-group">
                <label for="audited_qty">Jumlah hasil audit fisik <span class="faint">*</span></label>
                <input type="number" id="audited_qty" name="audited_qty" min="0" value="<?= $counted ?>" required>
                <span class="hint">Bila jumlah ini berbeda dengan hasil hitung observer, data otomatis ditandai selisih dan dibuka untuk dicari.</span>
            </div>

            <div class="form-group mt-1">
                <label for="note"><?= __('Catatan audit') ?></label>
                <textarea id="note" name="note" maxlength="255" placeholder="<?= __('Keterangan hasil pengecekan (opsional)') ?>"></textarea>
            </div>

            <div class="flex gap-sm mt-2">
                <button type="submit" class="btn"><?= icon('check') ?> <?= __('Simpan Hasil Audit') ?></button>
                <a class="btn btn-ghost" href="<?= url('audit') ?>">Batal</a>
            </div>
        </form>
    </div>
</div>