<?php
/**
 * Satu kartu Marking Memo (dipakai preview/PDF dan contoh di halaman kelola).
 *
 * @var array<string,mixed> $m
 * @var string              $periodLabel
 */
$m           = $m ?? [];
$periodLabel = $periodLabel ?? '';

$v = static fn (string $key, string $fallback = '—'): string => trim((string) ($m[$key] ?? '')) !== ''
    ? (string) $m[$key]
    : $fallback;

$location = $v('location', 'Belum diatur');
$device   = trim((string) ($m['device'] ?? ''));
if ($device !== '') {
    $location .= ' · ' . $device;
}
?>
<article class="memo">
    <header class="memo-head">
        <span class="memo-head-l">
            <span class="memo-kicker"><?= __('Marking memo') ?></span>
            <span class="memo-period"><?= e($periodLabel) ?></span>
            <span class="memo-wh"><?= e($v('warehouse', '')) ?></span>
        </span>
        <span class="memo-code"><?= e($v('code')) ?></span>
    </header>

    <div class="memo-body">
        <div class="memo-info">
            <div class="memo-grid">
            <div class="memo-field memo-span2">
                <span class="memo-k"><?= __('Nama barang') ?></span>
                <span class="memo-v memo-v-lg"><?= e($v('name')) ?></span>
            </div>

            <div class="memo-field">
                <span class="memo-k"><?= __('Sub inventory') ?></span>
                <span class="memo-v"><?= e($v('sub_inventory')) ?></span>
            </div>
            <div class="memo-field">
                <span class="memo-k"><?= __('Satuan') ?></span>
                <span class="memo-v"><?= e(ucfirst($v('unit'))) ?></span>
            </div>

            <div class="memo-field">
                <span class="memo-k"><?= __('Kategori') ?></span>
                <span class="memo-v"><?= e($v('category')) ?></span>
            </div>
            <div class="memo-field">
                <span class="memo-k"><?= __('Group') ?></span>
                <span class="memo-v"><?= e($v('group')) ?></span>
            </div>

            <div class="memo-field memo-span2">
                <span class="memo-k"><?= __('Lokasi') ?></span>
                <span class="memo-v"><?= e($location) ?></span>
            </div>

            <div class="memo-field memo-field-qty memo-span2">
                <span class="memo-k"><?= __('Total QTY') ?></span>
                <span class="memo-qty memo-qty-blank"><b>&nbsp;</b></span>
            </div>

            </div>

            <div class="memo-field memo-field-note">
                <span class="memo-k"><?= __('Catatan') ?></span>
                <span class="memo-line"></span>
            </div>
        </div>

        <div class="memo-qr">
            <?= $m['qr'] /* SVG dari sf_qr_svg.py */ ?>
            <small><?= e($v('code')) ?></small>
            <span class="memo-qr-hint"><?= __('Scan') ?></span>
        </div>
    </div>

    <footer class="memo-foot">
        <span class="memo-sign">
            <i><?= __('Dihitung') ?></i><b></b>
            <small class="memo-sign-name"><span><?= __('Observer') ?></span><?= e($v('observers')) ?></small>
        </span>
        <span class="memo-sign">
            <i><?= __('Diperiksa') ?></i><b></b>
            <small class="memo-sign-name"><span><?= __('Auditor') ?></span><?= e($v('auditors')) ?></small>
        </span>
    </footer>
</article>
