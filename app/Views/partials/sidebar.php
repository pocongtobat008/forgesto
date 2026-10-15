<?php
/**
 * Sidebar navigation — Mantine AppShell Navbar.
 * Placed beneath the full-width enterprise topbar.
 * Supports:
 * - Desktop Persistent (Expanded 260px or Collapsed Mini Rail 68px)
 * - Mobile / Tablet Off-canvas Drawer (280px with backdrop blur)
 *
 * @var array|null $user
 * @var int        $unread
 * @var int        $openDiscrepancies
 * @var array|null $activeBookPeriod
 */

use App\Core\Request;

$path = trim(Request::path(), '/');

$allNavPaths = ['dashboard', 'items', 'items/memo', 'items/memo_index', 'audit', 'tahun-buku', 'discrepancies', 'reconciliation', 'reconciliation/audit', 'notifications', 'master-data', 'groups', 'users', 'user-rules', 'warehouses', 'locations', 'devices', 'import', 'exports', 'activity', 'profile'];
$isActivePath = '';
foreach ($allNavPaths as $cand) {
    $candTrim = trim($cand, '/');
    $matches = ($path === $candTrim) || ($candTrim !== '' && str_starts_with($path, $candTrim . '/'));
    if ($matches && strlen($candTrim) > strlen($isActivePath)) {
        $isActivePath = $candTrim;
    }
}

$is = static function (string $prefix) use ($path, $isActivePath): string {
    $prefix = trim($prefix, '/');

    // The dashboard is served both at "/" and "/dashboard".
    if ($prefix === 'dashboard' && $path === '') {
        return 'is-active';
    }
    if ($prefix === '') {
        return $path === '' ? 'is-active' : '';
    }

    return $prefix === $isActivePath ? 'is-active' : '';
};

$role = $user['role'] ?? null;
?>
<aside class="sidebar" data-sidebar>
    <div class="sidebar-header-mobile">
        <a href="<?= url('dashboard') ?>" class="brand-link" aria-label="<?= e(config('app.name')) ?>">
            <img src="<?= asset_v('img/logo.svg') ?>" alt="<?= e(config('app.name')) ?>" class="brand-logo" width="30" height="30">
        </a>
        <div class="brand-text">
            <strong><?= e(config('app.name')) ?></strong>
            <span><?= e(config('app.company')) ?></span>
        </div>
        <button type="button" class="icon-btn sidebar-close" data-sidebar-close aria-label="<?= __('Tutup menu') ?>" title="<?= __('Tutup menu') ?>">
            <?= icon('x') ?>
        </button>
    </div>

    

    <nav class="sidebar-nav" aria-label="<?= __('Navigasi utama') ?>">
        <?php
        // ----------------------------------------------------------------
        // Accordion group 1 · Operasional
        // ----------------------------------------------------------------
        $opItems = [
            ['dashboard',     'dashboard', __('nav.dashboard'),   'dashboard'],
            ['items',         'items',     __('nav.items'),    'package'],
            ['items/memo',   'items/memo', __('nav.memo'), 'file-text'],
        ];
        if (can('audit_data')) {
            $opItems[] = ['audit', 'audit', __('nav.audit'), 'clipboard-check'];
        }
        if (can('manage_data')) {
            $opItems[] = ['tahun-buku', 'tahun-buku', __('nav.book'), 'archive'];
        }
        $opItems[] = ['discrepancies', 'discrepancies', __('nav.discrepancy'), 'trending-up', $openDiscrepancies];
        if (in_array(\App\Core\Auth::role(), ['poweradmin', 'adminsto'], true)) {
            $opItems[] = ['reconciliation', 'reconciliation', __('nav.reconciliation'), 'scale'];
        }
        if (in_array(\App\Core\Auth::role(), ['poweradmin', 'adminsto'], true)) {
            $opItems[] = ['reconciliation/audit', 'reconciliation/audit', __('nav.recon_audit'), 'shield-check'];
        }
        $opItems[] = ['notifications', 'notifications', __('nav.notifications'), 'bell', $unread];

        if (function_exists('can_view_menu')) {
            $opItems = array_values(array_filter($opItems, static fn ($it): bool => can_view_menu($it[1])));
        }

        $opActive = false;
        foreach ($opItems as $it) { if ($is($it[1])) { $opActive = true; break; } }
        ?>
        <div class="sidebar-group<?= $opActive ? ' is-open' : '' ?>">
            <button type="button" class="sidebar-group-toggle" data-tip="<?= e(__('nav.operational')) ?>" aria-expanded="<?= $opActive ? 'true' : 'false' ?>">
                <?= icon('activity') ?>
                <span class="nav-label"><?= e(__('nav.operational')) ?></span>
                <span class="sidebar-group-chev"><?= icon('chev-right') ?></span>
            </button>
            <div class="sidebar-group-body"><div class="sb-group-inner">
                <?php foreach ($opItems as $it): ?>
                    <a class="nav-item <?= $is($it[1]) ?>" href="<?= url($it[0]) ?>" data-tip="<?= e($it[2]) ?>">
                        <?= icon($it[3]) ?> <span class="nav-label"><?= e($it[2]) ?></span>
                        <?php if (! empty($it[4])): ?>
                            <span class="nav-count"><?= (int) $it[4] > 99 ? '99+' : (int) $it[4] ?></span>
                        <?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </div></div>
        </div>

        <?php
        // ----------------------------------------------------------------
        // Accordion group 2 · Data Master
        // ----------------------------------------------------------------
        $masterItems = [
            ['master-data', 'master-data', __('nav.master_items'), 'layers'],
        ];
        if (can('manage_warehouses')) {
            $masterItems[] = ['warehouses', 'warehouses', __('nav.warehouses'), 'building'];
        }
        if (can('view_all_groups')) {
            $masterItems[] = ['groups',    'groups',    __('nav.groups'),    'layers'];
        }
        if (can('manage_users')) {
            $masterItems[] = ['users',     'users',     __('nav.users'),     'users'];
        }
        if (function_exists('can_view_menu') && can_view_menu('user-rules')) {
            $masterItems[] = ['user-rules', 'user-rules', __('Rules User'), 'shield'];
        }
        if (can('view_all_groups')) {
            $masterItems[] = ['locations', 'locations', __('nav.locations'), 'pin'];
            $masterItems[] = ['devices',   'devices',   __('nav.devices'),   'tablet'];
        }
        if (can('import_data')) {
            $masterItems[] = ['import',    'import',    __('nav.import'),    'upload'];
            $masterItems[] = ['exports',   'exports',   __('nav.exports'),   'download'];
        }

        if (function_exists('can_view_menu')) {
            $masterItems = array_values(array_filter($masterItems, static fn ($it): bool => can_view_menu($it[1])));
        }

        if ($masterItems !== []):
            $masterActive = false;
            foreach ($masterItems as $it) { if ($is($it[1])) { $masterActive = true; break; } }
        ?>
        <div class="sidebar-group<?= $masterActive ? ' is-open' : '' ?>">
            <button type="button" class="sidebar-group-toggle" data-tip="<?= e(__('nav.master')) ?>" aria-expanded="<?= $masterActive ? 'true' : 'false' ?>">
                <?= icon('building') ?>
                <span class="nav-label"><?= e(__('nav.master')) ?></span>
                <span class="sidebar-group-chev"><?= icon('chev-right') ?></span>
            </button>
            <div class="sidebar-group-body"><div class="sb-group-inner">
                <?php foreach ($masterItems as $it): ?>
                    <a class="nav-item <?= $is($it[1]) ?>" href="<?= url($it[0]) ?>" data-tip="<?= e($it[2]) ?>">
                        <?= icon($it[3]) ?> <span class="nav-label"><?= e($it[2]) ?></span>
                    </a>
                <?php endforeach; ?>
            </div></div>
        </div>
        <?php endif; ?>

        <?php
        // ----------------------------------------------------------------
        // Accordion group 3 · Lainnya
        // ----------------------------------------------------------------
        $otherItems = [];
        if (can('view_activity')) {
            $otherItems[] = ['activity', 'activity', __('nav.activity'), 'activity'];
        }
        $otherItems[] = ['profile', 'profile', __('nav.profile'), 'user-circle'];

        if (function_exists('can_view_menu')) {
            $otherItems = array_values(array_filter($otherItems, static fn ($it): bool => can_view_menu($it[1])));
        }

        $otherActive = false;
        foreach ($otherItems as $it) { if ($is($it[1])) { $otherActive = true; break; } }
        ?>
        <?php if ($otherItems !== []): ?>
        <div class="sidebar-group<?= $otherActive ? ' is-open' : '' ?>">
            <button type="button" class="sidebar-group-toggle" data-tip="<?= e(__('nav.other')) ?>" aria-expanded="<?= $otherActive ? 'true' : 'false' ?>">
                <?= icon('list') ?>
                <span class="nav-label"><?= e(__('nav.other')) ?></span>
                <span class="sidebar-group-chev"><?= icon('chev-right') ?></span>
            </button>
            <div class="sidebar-group-body"><div class="sb-group-inner">
                <?php foreach ($otherItems as $it): ?>
                    <a class="nav-item <?= $is($it[1]) ?>" href="<?= url($it[0]) ?>" data-tip="<?= e($it[2]) ?>">
                        <?= icon($it[3]) ?> <span class="nav-label"><?= e($it[2]) ?></span>
                    </a>
                <?php endforeach; ?>
            </div></div>
        </div>
        <?php endif; ?>

        <?php
        // ----------------------------------------------------------------
        // Accordion group 4 · Menu Khusus (Custom Menus per User)
        // ----------------------------------------------------------------
        $customItems = [];
        if (class_exists(\App\Models\UserRule::class) && \App\Core\Auth::check()) {
            $userRulesAll = \App\Models\UserRule::forUser((int) \App\Core\Auth::id(), (string) \App\Core\Auth::role());
            foreach ($userRulesAll as $mKey => $rMeta) {
                if (! empty($rMeta['is_custom']) && ! empty($rMeta['can_view'])) {
                    $customItems[] = [$mKey, $mKey, $rMeta['menu_name'], 'sliders'];
                }
            }
        }

        if ($customItems !== []):
            $customActive = false;
            foreach ($customItems as $it) { if ($is($it[1])) { $customActive = true; break; } }
        ?>
        <div class="sidebar-group<?= $customActive ? ' is-open' : '' ?>">
            <button type="button" class="sidebar-group-toggle" data-tip="<?= e(__('Menu Khusus')) ?>" aria-expanded="<?= $customActive ? 'true' : 'false' ?>">
                <?= icon('sliders') ?>
                <span class="nav-label"><?= e(__('Menu Khusus')) ?></span>
                <span class="sidebar-group-chev"><?= icon('chev-right') ?></span>
            </button>
            <div class="sidebar-group-body"><div class="sb-group-inner">
                <?php foreach ($customItems as $it): ?>
                    <a class="nav-item <?= $is($it[1]) ?>" href="<?= url($it[0]) ?>" data-tip="<?= e($it[2]) ?>">
                        <?= icon($it[3]) ?> <span class="nav-label"><?= e($it[2]) ?></span>
                    </a>
                <?php endforeach; ?>
            </div></div>
        </div>
        <?php endif; ?>

        <!-- Sidebar Footer: Modern Language Switcher & Role Badge (UI UX Max Pro) -->
        <div class="sidebar-footer">
            <?php $__loc = current_locale(); ?>
            <div class="sidebar-theme-box">
                <button type="button" class="sidebar-theme-toggle" data-theme-toggle data-tip="<?= e(__('Ganti Tema')) ?>" aria-label="<?= e(__('Ganti Tema (Gelap / Terang)')) ?>">
                    <span class="stt-ico stt-ico-sun" aria-hidden="true"><?= icon('sun') ?></span>
                    <span class="stt-ico stt-ico-moon" aria-hidden="true"><?= icon('moon') ?></span>
                    <span class="stt-label"><?= __('Tema') ?></span>
                    <span class="stt-track" aria-hidden="true"><span class="stt-knob"></span></span>
                </button>
            </div>
            <div class="sidebar-lang-box" data-tip="<?= e(__('lang.label')) ?>">
                <div class="sidebar-lang-header">
                    <span class="sidebar-lang-ico"><?= icon('globe') ?></span>
                    <span class="sidebar-lang-title"><?= __('Bahasa') ?></span>
                </div>
                <div class="sidebar-lang-switch" role="group" aria-label="<?= e(__('lang.label')) ?>">
                    <a class="sidebar-lang-opt<?= $__loc === 'id' ? ' is-active' : '' ?>" href="<?= e(locale_url('id')) ?>" hreflang="id" title="<?= e(__('lang.id')) ?>" data-no-progress>
                        <span class="lang-code">ID</span>
                        <span class="lang-name">Indonesia</span>
                    </a>
                    <a class="sidebar-lang-opt<?= $__loc === 'en' ? ' is-active' : '' ?>" href="<?= e(locale_url('en')) ?>" hreflang="en" title="<?= e(__('lang.en')) ?>" data-no-progress>
                        <span class="lang-code">EN</span>
                        <span class="lang-name">English</span>
                    </a>
                </div>
            </div>

            <div class="sidebar-role-box">
                <div class="sidebar-role-label"><?= e(__('nav.role_active')) ?></div>
                <div class="sidebar-role-wrap">
                    <span class="badge badge-brand"><?= e(role_label($role)) ?></span>
                    <?php if ($role === 'observer' || $role === 'audit'): ?>
                        <div class="text-xs faint mt-1">
                            <?= count(\App\Core\Auth::groupIds()) ?> group didelegasikan
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>
</aside>
