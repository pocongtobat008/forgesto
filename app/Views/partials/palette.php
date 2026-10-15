<?php
/**
 * Command palette overlay. Items are built client-side from the sidebar
 * (permission-accurate) plus a few quick actions — see app.js.
 */
?>
<div class="palette-backdrop" data-palette>
    <div class="palette" role="dialog" aria-modal="true" aria-label="<?= __('Pencarian perintah') ?>">
        <div class="palette-input-wrap">
            <?= icon('search') ?>
            <input type="text" class="palette-input" data-palette-input placeholder="<?= __('Cari halaman, data, atau perintah…') ?>" autocomplete="off" spellcheck="false" aria-label="<?= __('Cari perintah') ?>">
            <kbd><?= __('Esc') ?></kbd>
        </div>
        <div class="palette-list" data-palette-list role="listbox"></div>
        <div class="palette-foot">
            <span class="pf"><kbd>↑</kbd><kbd>↓</kbd> navigasi</span>
            <span class="pf"><kbd>↵</kbd> buka</span>
            <span class="pf"><kbd><?= __('Esc') ?></kbd> tutup</span>
        </div>
    </div>
</div>