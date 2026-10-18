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
<!DOCTYPE html>
<html lang="<?= e(current_locale()) ?>" data-theme="light">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="color-scheme" content="light">
<title><?= e(__('Preview Marking Memo')) ?> &middot; <?= e(config('app.name')) ?></title>
<link rel="icon" type="image/svg+xml" href="<?= asset_v('img/logo.svg') ?>">
<link rel="stylesheet" href="<?= asset_v('css/tokens.css') ?>">
<link rel="stylesheet" href="<?= asset_v('css/app.css') ?>">
<link rel="stylesheet" href="<?= asset_v('css/sf-ui.css') ?>">
<link rel="stylesheet" href="<?= asset_v('css/flat-material.css') ?>">
<link rel="stylesheet" href="<?= asset_v('css/a11y-fixes.css') ?>">
<link rel="stylesheet" href="<?= asset_v('css/a11y-pass2.css') ?>">
<?= \App\Core\View::partial('items/_memo_styles', ['standalone' => false]) ?>
</head>
<body class="memo-doc">
<?php else: ?>
<?= \App\Core\View::partial('items/_memo_styles', ['standalone' => true]) ?>
<?php endif; ?>

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
            <?= \App\Core\View::partial('items/_memo_card', ['m' => $m, 'periodLabel' => $periodLabel ?? '']) ?>
        <?php endforeach; ?>
    </main>
<?php endforeach; ?>
</div>
<?php if (! $isPdf): ?>
</body>
</html>
<?php endif; ?>
