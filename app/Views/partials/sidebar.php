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

$allNavPaths = ['dashboard', 'progress', 'items', 'items/memo', 'items/memo_index', 'audit', 'tahun-buku', 'reports', 'discrepancies', 'reconciliation', 'reconciliation/audit', 'notifications', 'informasi', 'employee-ids', 'master-data', 'groups', 'users', 'user-rules', 'warehouses', 'locations', 'devices', 'import', 'exports', 'activity', 'profile'];
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
<?php
// ---- Tahun Buku badge (moved from topbar header) ----
$__bp = $activeBookPeriod ?? null;
$__months = ['01'=>'Jan','02'=>'Feb','03'=>'Mar','04'=>'Apr','05'=>'Mei','06'=>'Jun',
             '07'=>'Jul','08'=>'Agu','09'=>'Sep','10'=>'Okt','11'=>'Nov','12'=>'Des'];
$__bpShort = '—'; $__bpFull = 'Belum diatur'; $__bpClosed = false;
if ($__bp) {
    $__p = explode('-', (string)($__bp['audit_month'] ?? ''));
    $__mm = $__p[1] ?? ''; $__yy = $__p[0] ?? '';
    $__bpShort = ($__months[$__mm] ?? ($__mm !== '' ? $__mm : '—')) . "'" . substr((string)$__yy, -2);
    $__bpFull  = ($__mm !== '' ? ($__months[$__mm] ?? $__mm) : '—') . ' ' . $__yy;
    $__bpClosed = ($__bp['status'] ?? 'open') === 'closed';
}
?>
<aside class="sidebar" data-sidebar>
    <div class="sidebar-header-mobile">
        <a href="<?= url('dashboard') ?>" class="brand-link" aria-label="<?= e(config('app.name')) ?>">
            <img src="<?= asset_v('img/logo.svg') ?>" alt="<?= e(config('app.name')) ?>" class="brand-logo" width="30" height="30">
        </a>
        <div class="brand-text">
            <strong><?= e(config('app.name')) ?></strong>
        </div>
        <a href="<?= url('tahun-buku') ?>"
           class="sf-year-badge <?= $__bpClosed ? 'is-closed' : 'is-open' ?>"
           title="<?= $__bpClosed ? 'Tahun Buku (Ditutup): ' : 'Tahun Buku Aktif: ' ?><?= e($__bpFull) ?>"
           aria-label="<?= $__bpClosed ? 'Tahun buku ditutup: ' : 'Tahun buku berjalan: ' ?><?= e($__bpFull) ?>"
           data-no-progress>
            <span class="sf-yb-dot" aria-hidden="true"></span>
            <span class="sf-yb-text"><?= e($__bpShort) ?></span>
        </a>
        <button type="button" class="icon-btn sidebar-close" data-sidebar-close aria-label="<?= __('Tutup menu') ?>" title="<?= __('Tutup menu') ?>">
            <?= icon('x') ?>
        </button>
    </div>

    

    <nav class="sidebar-nav" aria-label="<?= __('Navigasi utama') ?>">
        <?php
        // ----------------------------------------------------------------
        // Accordion group 1 · Operasional
        // ----------------------------------------------------------------
        /* Urutan utama mengikuti alur kerja: Dashboard -> Progress -> Observer
           -> Audit -> Rekonsiliasi. Sisanya (memo, tahun buku, report, selisih,
           audit rekonsiliasi, notifikasi) menyusul di bawahnya. */
        $opItems = [
            ['dashboard',     'dashboard', __('nav.dashboard'),   'dashboard'],
            ['progress',      'progress',  __('nav.progress'), 'chart'],
            ['items',         'items',     __('nav.items'),    'package'],
        ];
        if (can('audit_data')) {
            $opItems[] = ['audit', 'audit', __('nav.audit'), 'clipboard-check'];
        }
        if (in_array(\App\Core\Auth::role(), ['poweradmin', 'adminsto'], true)) {
            $opItems[] = ['reconciliation', 'reconciliation', __('nav.reconciliation'), 'scale'];
        }

        $opItems[] = ['items/memo',   'items/memo', __('nav.memo'), 'file-text'];
        if (can('manage_data')) {
            $opItems[] = ['tahun-buku', 'tahun-buku', __('nav.book'), 'archive'];
        }
        if (function_exists('can_view_menu') && can_view_menu('reports')) {
            $opItems[] = ['reports', 'reports', __('nav.reports'), 'chart'];
        }
        $opItems[] = ['discrepancies', 'discrepancies', __('nav.discrepancy'), 'trending-up', $openDiscrepancies];
        if (in_array(\App\Core\Auth::role(), ['poweradmin', 'adminsto'], true)) {
            $opItems[] = ['reconciliation/audit', 'reconciliation/audit', __('nav.recon_audit'), 'shield-check'];
        }
        $opItems[] = ['informasi', 'informasi', __('Informasi'), 'info'];
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
            $masterItems[] = ['employee-ids', 'employee-ids', __('ID Karyawan'), 'barcode'];
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

        
    </nav>

</aside>
