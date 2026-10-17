<?php
/**
 * Blank layout - halaman tanpa sidebar (login, dsb).
 *
 * @var string $content
 * @var string $title
 */

$pageTitle = $title ?? 'StockForge';
?>
<!DOCTYPE html>
<html lang="<?= e(current_locale()) ?>" data-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="color-scheme" content="light dark">
    <meta name="theme-color" content="#f0b429">
    <meta name="sf-base" content="<?= e(rtrim(App\Core\Request::basePath(), '/')) ?>">
    <title><?= e($pageTitle) ?> &middot; <?= e(config('app.name')) ?></title>

    <link rel="icon" type="image/svg+xml" href="<?= asset_v('img/logo.svg') ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Google+Sans+Flex:wght@400..800&display=swap">
    <link rel="stylesheet" href="<?= asset_v('css/app.css') ?>">
    <link rel="stylesheet" href="<?= asset_v('css/sf-ui.css') ?>">
    <link rel="stylesheet" href="<?= asset_v('css/flat-material.css') ?>">
    <link rel="stylesheet" href="<?= asset_v('css/sf-auth.css') ?>">
    <?= $headExtra ?? '' ?>

    <script>
        (function () {
            try {
                var saved = localStorage.getItem('sf-theme');
                document.documentElement.setAttribute('data-theme', saved === 'dark' ? 'dark' : 'light');
            } catch (e) {}
        })();
    </script>
</head>
<body class="sf-auth-body">
    <?= $content ?>
</body>
</html>
