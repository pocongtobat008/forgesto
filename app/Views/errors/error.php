<?php
/**
 * Standalone error view (never uses the DB / app layout).
 *
 * @var int             $status
 * @var string          $message
 * @var Throwable|null  $exception
 */
$titles = [
    403 => 'Akses ditolak',
    404 => 'Tidak ditemukan',
    419 => 'Sesi kedaluwarsa',
    422 => 'Data tidak valid',
    500 => 'Terjadi kesalahan',
];
$title = $titles[$status] ?? 'Terjadi kesalahan';
?>
<!DOCTYPE html>
<html lang="id" data-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($status) ?> &middot; <?= e(config('app.name')) ?></title>
    <link rel="icon" type="image/svg+xml" href="<?= asset_v('img/logo.svg') ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Google+Sans+Flex:wght@400..800&display=swap">
    <link rel="stylesheet" href="<?= asset_v('css/app.css') ?>">
</head>
<body>
    <div class="center-page">
        <div class="card error-card">
            <div class="card-body">
                <div class="error-code"><?= (int) $status ?></div>
                <h1><?= e($title) ?></h1>
                <p class="muted"><?= e($message) ?></p>

                <?php if ($exception !== null && config('app.debug')): ?>
                    <details class="disclosure" style="text-align:left;margin-top:1rem;">
                        <summary><?= __('Detail teknis') ?></summary>
                        <div class="text-xs faint" style="word-break:break-word;font-family:var(--font-mono);">
                            <div><strong><?= e($exception::class) ?></strong></div>
                            <div><?= e($exception->getMessage()) ?></div>
                            <div style="margin-top:.4rem;">
                                <?= e($exception->getFile()) ?>:<?= (int) $exception->getLine() ?>
                            </div>
                        </div>
                    </details>
                <?php endif; ?>

                <div class="mt-3">
                    <a class="btn btn-outline" href="<?= url('dashboard') ?>">Kembali ke Dashboard</a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
