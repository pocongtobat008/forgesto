<?php
/**
 * Marking Memo: preview (tab baru) dan sumber PDF. 4 memo per halaman A4 (2x2).
 * @var array  $memos  list of ['id','code','name','location','qty','qr']
 * @var bool   $pdf    true saat dirender untuk PDF (toolbar disembunyikan)
 * @var ?array $pager  ['page','last_page','total','from','to','base'] (mode preview)
 * @var ?string $pdfUrl URL unduh PDF untuk halaman ini
 */
$memos   = $memos ?? [];
$isPdf   = ! empty($pdf);
$pager   = $pager ?? null;
$pdfUrl  = $pdfUrl ?? null;
$pages   = array_chunk($memos, 6);
$pageUrl = static fn (int $p): string => url('items/memo/preview') . '?' . $pager['base'] . '&page=' . $p;
?>
<?php if (! $isPdf): ?>
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<?php endif; ?>
<?= \App\Core\View::partial('items/_memo_styles', ['standalone' => $isPdf]) ?>

<div class="memo-stage<?= $isPdf ? ' is-pdf' : '' ?>">
<?php if (! $isPdf): ?>
    <header class="memo-bar no-print">
        <div>
            <h1><?= __('Preview Marking Memo') ?></h1>
            <p>
                <?= count($memos) ?> memo di halaman ini
                <?php if ($pager): ?>
                    · halaman <?= (int) $pager['page'] ?> dari <?= (int) $pager['last_page'] ?>
                    · total <?= (int) $pager['total'] ?> item
                <?php endif; ?>
            </p>
        </div>
        <div class="memo-bar-actions">
            <button type="button" class="btn btn-outline" onclick="window.print()"><?= icon('file-text') ?> Cetak</button>
            <?php if (! empty($pdfUrl)): ?>
                <a class="btn btn-brand" href="<?= e($pdfUrl . '&dl=1') ?>"><?= icon('download') ?> Unduh PDF halaman ini</a>
            <?php endif; ?>
            <a class="btn" href="<?= url('items/memo') ?>"><?= icon('arrow-left') ?> Kelola memo</a>
        </div>
    </header>

    <?php if ($pager && (int) $pager['last_page'] > 1): ?>
        <nav class="memo-pager no-print" aria-label="<?= __('Halaman preview memo') ?>">
            <?php if ((int) $pager['page'] > 1): ?>
                <a class="btn btn-outline" href="<?= e($pageUrl((int) $pager['page'] - 1)) ?>"><?= icon('chev-left') ?> Sebelumnya</a>
            <?php endif; ?>
            <span>Halaman <?= (int) $pager['page'] ?> dari <?= (int) $pager['last_page'] ?></span>
            <?php if ((int) $pager['page'] < (int) $pager['last_page']): ?>
                <a class="btn btn-outline" href="<?= e($pageUrl((int) $pager['page'] + 1)) ?>">Berikutnya <?= icon('chev-right') ?></a>
            <?php endif; ?>
        </nav>
    <?php endif; ?>
<?php endif; ?>

<?php if ($memos === []): ?>
    <main class="memo-sheet">
        <div class="memo-empty"><?= __('Tidak ada item dipilih untuk dicetak.') ?></div>
    </main>
<?php endif; ?>

<?php foreach ($pages as $sheet): ?>
    <main class="memo-sheet">
        <?php foreach ($sheet as $m): ?>
            <article class="memo">
                <header class="memo-head">
                    <span class="memo-kicker"><?= __('Marking memo') ?></span>
                    <span class="memo-meta">
                        <span class="memo-period"><?= e($periodLabel ?? '') ?></span>
                        <span class="memo-wh"><?= e($m['warehouse'] ?? '') ?></span>
                    </span>
                    <span class="memo-code"><?= e($m['code']) ?></span>
                </header>
                <div class="memo-body">
                    <div class="memo-info">
                        <div class="memo-row-name">
                            <span class="memo-k"><?= __('Nama barang') ?></span>
                            <span class="memo-v"><?= e($m['name']) ?></span>
                        </div>
                        <div class="memo-row-loc">
                            <span class="memo-k"><?= __('Lokasi') ?></span>
                            <span class="memo-v"><?= e($m['location'] !== '' ? $m['location'] : 'Belum diatur') ?></span>
                        </div>
                        <div class="memo-row-qty">
                            <span class="memo-k">Total QTY</span>
                            <span class="memo-qty memo-qty-blank"><b>&nbsp;</b></span>
                        </div>
                        <div>
                            <span class="memo-k">Remark</span>
                            <span class="memo-v"><?= e($m['group'] ?? '') ?></span>
                        </div>
                    </div>
                    <div class="memo-qr">
                        <?= $m['qr'] /* SVG dari sf_qr_svg.py */ ?>
                        <small><?= e($m['code']) ?></small>
                    </div>
                </div>
            </article>
        <?php endforeach; ?>
    </main>
<?php endforeach; ?>
</div>
