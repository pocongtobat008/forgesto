<?php
/**
 * Main application layout — Mantine AppShell & Enterprise SaaS Architecture.
 * Topbar spans 100% full-width at the top, sidebar sits below header.
 *
 * @var string $content
 * @var string $title
 */

use App\Core\View;

$user = auth();

// Sidebar counters. Guarded so a navigation query never breaks the page.
$unread = 0;
$openDiscrepancies = 0;
$activeBookPeriod = null;
try {
    $unread = (new \App\Models\Notification())->unreadCount();
    $openDiscrepancies = (new \App\Models\Discrepancy())->openCount();
    $activeBookPeriod = (new \App\Models\AuditPeriod())->active();
} catch (\Throwable $e) {
    // Ignore: the sidebar simply shows no badges.
}

$pageTitle = $title ?? 'Dashboard';
?>
<!DOCTYPE html>
<html lang="<?= e(current_locale()) ?>" data-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="color-scheme" content="light dark">
    <meta name="theme-color" content="#228be6">
    <meta name="csrf-token" content="<?= csrf_token() ?>">
    <meta name="sf-base" content="<?= e(rtrim(App\Core\Request::basePath(), '/')) ?>">
    <title><?= e($pageTitle) ?> &middot; <?= e(config('app.name')) ?></title>

    <link rel="icon" type="image/svg+xml" href="<?= asset_v('img/logo.svg') ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Google+Sans+Flex:wght@400..800&display=swap">
    <link rel="stylesheet" href="<?= asset_v('css/tokens.css') ?>">
    <link rel="stylesheet" href="<?= asset_v('css/app.css') ?>">
    <link rel="stylesheet" href="<?= asset_v('css/sf-ui.css') ?>">
    <link rel="stylesheet" href="<?= asset_v('css/flat-material.css') ?>">
    <!-- Aurora Glass 2.0 — WAJIB dimuat TERAKHIR agar menjadi lapisan visual final -->
    <link rel="stylesheet" href="<?= asset_v('css/sf-glass.css') ?>">
    <!-- Forge Gradient 1.0 — header gradient + floating cards (lapisan final) -->
    <link rel="stylesheet" href="<?= asset_v('css/forge-gradient.css') ?>">
    <link rel="stylesheet" href="<?= asset_v('css/hero-menu.css') ?>">
    <link rel="stylesheet" href="<?= asset_v('css/sf-progress.css') ?>">
    <link rel="stylesheet" href="<?= asset_v('css/items-mobile.css') ?>">
    <!-- View mode: kartu / tabel untuk semua table.data (semua breakpoint) -->
    <link rel="stylesheet" href="<?= asset_v('css/sf-viewmode.css') ?>">
    <link rel="stylesheet" href="<?= asset_v('css/money-fit.css') ?>">
    <link rel="stylesheet" href="<?= asset_v('css/palette-scan.css') ?>">
    <link rel="stylesheet" href="<?= asset_v('css/sf-usermenu.css') ?>">
    <link rel="stylesheet" href="<?= asset_v('css/sf-rowdetail.css') ?>">
    <link rel="stylesheet" href="<?= asset_v('css/sf-tables.css') ?>">
    <!-- A11y & Polish Layer - WAJIB paling akhir (menang atas semua lapisan) -->
    <link rel="stylesheet" href="<?= asset_v('css/a11y-fixes.css') ?>">
    <link rel="stylesheet" href="<?= asset_v('css/a11y-pass2.css') ?>">
    <link rel="stylesheet" href="<?= asset_v('css/topbar-brand.css') ?>">
    <link rel="stylesheet" href="<?= asset_v('css/topbar-quicksearch.css') ?>">
    <script>try{var __v=localStorage.getItem('sf:viewmode'),__q=new URLSearchParams(location.search).get('view');if(__q==='cards'||__q==='table'||__q==='auto')__v=__q;if(__v==='cards'||__v==='table')document.documentElement.setAttribute('data-view',__v);}catch(e){}</script>
    <?= $headExtra ?? '' ?>

    <script>
        (function () {
            try {
                var saved = localStorage.getItem('sf-theme');
                // Default theme = LIGHT. Dark hanya bila user memilihnya sendiri.
                if (saved === 'dark') {
                    document.documentElement.setAttribute('data-theme', 'dark');
                } else {
                    document.documentElement.setAttribute('data-theme', 'light');
                }
                // Persistent collapsed desktop rail
                if (window.innerWidth >= 1024 && localStorage.getItem('stockforge-sidebar') === 'collapsed') {
                    document.documentElement.classList.add('sidebar-collapsed');
                }
            } catch (e) {}
        })();
    </script>
</head>
<body
    data-user-role="<?= e((string) \App\Core\Auth::role()) ?>"
    data-user-id="<?= (int) \App\Core\Auth::id() ?>"
    data-can-see-qty="<?= can_see_stock_qty() ? '1' : '0' ?>"
    data-pic-groups="<?= e(implode(',', array_map('intval', $picGroups ?? []))) ?>"
    data-active-group="<?= isset($activeGroup) && $activeGroup !== null ? (int) $activeGroup : '' ?>"
    data-group-pending="<?= (int) ($groupPending ?? 0) ?>">

    <a class="sf-skip-link" href="#main-content">Lewati ke konten utama</a>

    <!-- Layer 0: ambient Aurora Glass + wave (dekoratif, tidak diakses AT) -->
    <div class="sf-aurora" aria-hidden="true">
        <span class="sf-aurora-blob b1"></span><span class="sf-aurora-blob b2"></span>
        <span class="sf-aurora-blob b3"></span><span class="sf-aurora-blob b4"></span>
        <svg class="sf-aurora-waves" viewBox="0 0 1440 320" preserveAspectRatio="none" aria-hidden="true" focusable="false">
            <path class="sf-w3" d="M0,250 C240,206 480,290 720,254 C960,218 1200,268 1440,232 L1440,320 L0,320 Z"/>
            <path class="sf-w2" d="M0,272 C260,236 520,300 780,266 C1040,232 1220,286 1440,252 L1440,320 L0,320 Z"/>
            <path class="sf-w1" d="M0,292 C240,268 500,314 760,290 C1020,266 1220,306 1440,282 L1440,320 L0,320 Z"/>
        </svg>
    </div>
    <div class="app-shell">
        <?= View::partial('partials/topbar', [
            'user'              => $user,
            'title'             => $pageTitle,
            'subtitle'          => $subtitle ?? (string) config('app.tagline'),
            'unread'            => $unread,
            'activeBookPeriod'  => $activeBookPeriod,
        ]) ?>

        <div class="app-body">
            <?= View::partial('partials/sidebar', [
                'user'              => $user,
                'unread'            => $unread,
                'openDiscrepancies' => $openDiscrepancies,
                'activeBookPeriod'  => $activeBookPeriod,
            ]) ?>

            <div class="sidebar-backdrop" data-sidebar-backdrop></div>

            <div class="main">
                <main class="content" id="main-content" tabindex="-1">
                    <?= View::partial('partials/flash') ?>
                    <?php
                    $__bp = $activeBookPeriod ?? null;
                    $__isReadOnly = $__bp !== null && (($__bp['status'] ?? 'open') === 'closed' || ! empty($__bp['is_archive_view']));
                    if ($__isReadOnly):
                        $__bpMonths = ['01'=>'Januari','02'=>'Februari','03'=>'Maret','04'=>'April','05'=>'Mei','06'=>'Juni',
                                       '07'=>'Juli','08'=>'Agustus','09'=>'September','10'=>'Oktober','11'=>'November','12'=>'Desember'];
                        $__bpParts = explode('-', (string) ($__bp['audit_month'] ?? ''));
                        $__bpLabel = trim(($__bpMonths[$__bpParts[1] ?? ''] ?? ($__bp['audit_month'] ?? '')) . ' ' . ($__bpParts[0] ?? ''));
                    ?>
                    <div class="sf-archive-banner" style="background:#fffbeb;border:1px solid #fde68a;color:#92400e;padding:.65rem 1.15rem;border-radius:8px;font-size:.875rem;display:flex;align-items:center;justify-content:space-between;gap:1rem;margin-bottom:1rem;box-shadow:none;">
                        <div style="display:flex;align-items:center;gap:.6rem;">
                            <span class="sf-archive-icon" style="display:inline-flex;align-items:center;color:#b45309;" aria-hidden="true"><?= icon('lock') ?></span>
                            <div>
                                <strong><?= __('Mode Read-Only') ?>:</strong> <?= __('Anda sedang membuka') ?> <strong>Tahun Buku <?= e($__bpLabel) ?></strong> (<?= __('Periode Ditutup') ?>). <?= __('Seluruh data terkunci dan hanya untuk ditinjau.') ?>
                            </div>
                        </div>
                        <a href="<?= url('tahun-buku/kembali') ?>" class="btn btn-outline btn-xs" style="background:#fff;border-color:#d97706;color:#b45309;font-weight:600;white-space:nowrap;min-height:34px;display:inline-flex;align-items:center;gap:.35rem;">
                            <?= icon('corner-up-left') ?> <?= __('Kembali ke Periode Berjalan') ?>
                        </a>
                    </div>
                    <?php endif; ?>

                    <?php
                    $currentPath = trim(App\Core\Request::path(), '/');
                    $pathTitles = [
                        'dashboard'            => __('Dashboard'),
                        'items'                => __('Observer'),
                        'items/create'         => __('Tambah Data STO'),
                        'items/memo'           => __('Marking Memo'),
                        'items/memo_index'     => __('Marking Memo'),
                        'audit'                => __('Audit'),
                        'discrepancies'        => __('Selisih'),
                        'reconciliation'       => __('Rekonsiliasi'),
                        'reconciliation/audit' => __('Audit Rekonsiliasi'),
                        'tahun-buku'           => __('Tahun Buku'),
                        'reports'              => __('Report'),
                        'groups'               => __('Group & Tim'),
                        'master-data'          => __('Data Master'),
                        'warehouses'           => __('Gudang'),
                        'locations'            => __('Lokasi'),
                        'devices'              => __('Device'),
                        'users'                => __('Pengguna'),
                        'employee-ids'         => __('ID Karyawan'),
                        'user-rules'           => __('Aturan Menu'),
                        'activity'             => __('Aktivitas'),
                        'import'               => __('Impor Data'),
                        'exports'              => __('Ekspor Data'),
                        'notifications'        => __('Notifikasi'),
                        'profile'              => __('Profil & Akses'),
                    ];
                    $pageLabel = $pathTitles[$currentPath] ?? ($pageTitle ?? '');
                    if ($currentPath !== '' && $currentPath !== 'dashboard'):
                    ?>
                    <nav class="sf-breadcrumb" aria-label="Breadcrumb">
                        <a href="<?= url('dashboard') ?>" class="sf-bc-link"><?= __('Beranda') ?></a>
                        <span class="sf-bc-sep">/</span>
                        <span class="sf-bc-current"><?= e($pageLabel) ?></span>
                    </nav>
                    <?php endif; ?>

                    <?= $content ?>
                </main>
            </div>
        </div>
    </div>

    <?= View::partial('partials/palette') ?>
    <?= View::partial('partials/menu-rule-modal') ?>

    <script src="<?= asset_v('js/app.js') ?>"></script>
    <script src="<?= asset_v('js/spotlight.js') ?>"></script>
    <script src="<?= asset_v('js/palette-scan.js') ?>"></script>
    <script src="<?= asset_v('js/sf-ui.js') ?>"></script>
    <script src="<?= asset_v('js/notif-popup.js') ?>"></script>
    <script src="<?= asset_v('js/wh-modal.js') ?>"></script>
    <script src="<?= asset_v('js/sf-mat.js') ?>"></script>
    <script src="<?= asset_v('js/sf-motion.js') ?>" defer></script>
    <script src="<?= asset_v('js/money-fit.js') ?>" defer></script>
    <script src="<?= asset_v('js/table-colmgr.js') ?>"></script>
    <script src="<?= asset_v('js/sf-viewmode.js') ?>" defer></script>
    <!-- Expand/collapse + detail baris untuk semua tabel data -->
    <script src="<?= asset_v('js/sf-tables-expand.js') ?>" defer></script>
    <?= $bodyExtra ?? '' ?>
</body>
</html>
