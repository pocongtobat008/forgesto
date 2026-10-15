<?php
/**
 * Topbar navigation — Enterprise SaaS Layout (Full Width).
 *
 * @var array|null $user
 * @var string     $title
 * @var string     $subtitle
 * @var int        $unread
 * @var array|null $activeBookPeriod
 */
$displayTitle = $title ?? 'Dashboard';
$displaySubtitle = $subtitle ?? (string) config('app.tagline');
$initial = strtoupper(mb_substr((string) ($user['name'] ?? 'S'), 0, 1));
?>
<header class="topbar">
    <div class="topbar-left">
        <!-- Modern SaaS & Startup Sidebar Toggle (Expanded/Collapsed state indicator) -->
        <button type="button" class="topbar-sidebar-toggle" data-sidebar-toggle aria-label="<?= __('Toggle navigasi sidebar') ?>" title="<?= __('Ciutkan / Bentangkan Menu (Ctrl+\\)') ?>">
            <span class="st-icon st-icon-panel-collapse" aria-hidden="true" title="<?= __('Ciutkan sidebar') ?>">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                    <rect width="18" height="18" x="3" y="3" rx="3.5"/>
                    <path d="M9 3v18"/>
                    <path d="m14 9-3 3 3 3"/>
                </svg>
            </span>
            <span class="st-icon st-icon-panel-expand" aria-hidden="true" title="<?= __('Bentangkan sidebar') ?>">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                    <rect width="18" height="18" x="3" y="3" rx="3.5"/>
                    <path d="M9 3v18"/>
                    <path d="m11 9 3 3-3 3"/>
                </svg>
            </span>
            <span class="st-icon st-icon-mobile" aria-hidden="true">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="3" y1="6" x2="21" y2="6"/>
                    <line x1="3" y1="12" x2="16" y2="12"/>
                    <line x1="3" y1="18" x2="19" y2="18"/>
                </svg>
            </span>
        </button>

        <!-- Brand Block: Logo + Name STO on top, Active Tahun Buku directly underneath -->
        <div class="topbar-brand-wrap">
            <a href="<?= url('dashboard') ?>" class="topbar-brand" aria-label="<?= e(config('app.name')) ?>">
                <img src="<?= asset_v('img/logo.svg') ?>" alt="<?= e(config('app.name')) ?>" class="topbar-logo" width="30" height="30">
                <div class="topbar-brand-col">
                    <div class="tb-brand-title">
                        <strong class="tb-name"><?= e(config('app.name')) ?></strong>
                        <span class="tb-badge">STO</span>
                    </div>
                    <?php
                    $bp = $activeBookPeriod ?? null;
                    $bpMonths = ['01'=>'Jan','02'=>'Feb','03'=>'Mar','04'=>'Apr','05'=>'Mei','06'=>'Jun',
                                 '07'=>'Jul','08'=>'Agu','09'=>'Sep','10'=>'Okt','11'=>'Nov','12'=>'Des'];
                    $bpFullMonths = ['01'=>'Januari','02'=>'Februari','03'=>'Maret','04'=>'April','05'=>'Mei','06'=>'Juni',
                                     '07'=>'Juli','08'=>'Agustus','09'=>'September','10'=>'Oktober','11'=>'November','12'=>'Desember'];
                    $bpSubText = 'Belum diatur';
                    $isClosed = false;
                    if ($bp) {
                        $bpParts = explode('-', (string) ($bp['audit_month'] ?? ''));
                        $mFullName = $bpFullMonths[$bpParts[1] ?? ''] ?? ($bp['audit_month'] ?? '');
                        $bpSubText = $mFullName . ' ' . ($bpParts[0] ?? '');
                        $isClosed = ($bp['status'] ?? 'open') === 'closed';
                    }
                    ?>
                    <a href="<?= url('tahun-buku') ?>" class="tb-period-sub <?= $isClosed ? 'is-closed' : 'is-open' ?>" title="<?= $isClosed ? 'Tahun Buku (Read-Only): ' . e($bpSubText) : 'Tahun Buku Aktif: ' . e($bpSubText) ?>" data-no-progress>
                        <span class="env-dot <?= $isClosed ? 'dot-closed' : 'dot-open' ?>"></span>
                        <span class="tb-period-text"><?= e($bpSubText) ?></span>
                        <span class="tb-period-tag"><?= $isClosed ? 'Read-Only' : 'Berjalan' ?></span>
                    </a>
                </div>
            </a>
        </div>

        <!-- Warehouse Selector -->
        <form method="post" action="<?= url('warehouses/switch') ?>" class="wh-switch">
            <?= csrf_field() ?>
            <input type="hidden" name="return_to" value="<?= e($_SERVER['REQUEST_URI'] ?? '/dashboard') ?>">
            <div class="wh-switch-inner" title="<?= __('Gudang Aktif (Pilih untuk berganti)') ?>">
                <span class="wh-switch-ico" aria-hidden="true"><?= icon('building') ?></span>
                <?php $__whs = \App\Core\Auth::visibleWarehouseIds(); ?>
                <?php if (count($__whs) > 1): ?>
                    <select name="warehouse_id" data-wh-switch data-auto-submit onchange="this.form.submit()" aria-label="<?= __('Pilih Gudang') ?>">
                        <?php if (\App\Core\Auth::allGroups()): ?>
                            <option value="all" <?= ! \App\Core\Auth::hasWarehouse() ? 'selected' : '' ?>><?= __('Semua Gudang') ?></option>
                        <?php endif; ?>
                        <?php foreach ((new \App\Models\Warehouse())->forActiveUser(\App\Core\Auth::groupIds(), \App\Core\Auth::allGroups()) as $__w): ?>
                            <option value="<?= (int) $__w['id'] ?>" <?= (int) \App\Core\Auth::warehouseId() === (int) $__w['id'] ? 'selected' : '' ?>>
                                <?= e($__w['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                <?php else: ?>
                    <?php $__cur = \App\Core\Auth::activeWarehouse(); ?>
                    <span class="wh-switch-static"><?= e($__cur['name'] ?? '—') ?></span>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- Spotlight Search -->
    <div class="topbar-center">
        <button type="button" class="topbar-search" data-palette-open aria-label="<?= __('Buka aksi cepat') ?>">
            <span class="ts-icon"><?= icon('search') ?></span>
            <span class="ts-label"><?= __('Cari cepat di Spotlight...') ?></span>
            <kbd data-palette-hint><?= __('Ctrl K') ?></kbd>
        </button>
    </div>

    <!-- Header Actions (Clean & Minimalist: Notifications + User Profile) -->
    <div class="topbar-actions">
        <!-- Hidden button hook for programmatic [data-scan-open] calls -->
        <button type="button" id="global-scan-trigger" data-scan-open class="sr-only" style="display:none;" aria-hidden="true"></button>

        <div class="notif-wrap" data-notif>
            <button type="button" class="icon-btn" data-notif-toggle aria-haspopup="true" aria-expanded="false" aria-label="<?= __('Notifikasi') ?>" title="<?= __('Notifikasi') ?>">
                <?= icon('bell') ?>
                <span class="dot" data-notif-badge<?= ($unread ?? 0) > 0 ? '' : ' hidden' ?>><?= (int) ($unread ?? 0) > 99 ? '99+' : (int) ($unread ?? 0) ?></span>
            </button>
            <div class="notif-panel" data-notif-panel role="dialog" aria-label="<?= __('Notifikasi') ?>" hidden>
                <div class="notif-head">
                    <div class="notif-head-title">
                        <?= icon('bell') ?>
                        <strong><?= __('Notifikasi') ?></strong>
                        <span class="notif-head-count"<?= ($unread ?? 0) > 0 ? '' : ' data-zero="1"' ?>><?= (int) ($unread ?? 0) > 0 ? (int) $unread : '' ?></span>
                    </div>
                    <button type="button" class="notif-x" data-notif-close aria-label="<?= __('Tutup') ?>"><?= icon('x') ?></button>
                </div>
                <div class="notif-body" data-notif-list></div>
                <div class="notif-foot">
                    <button type="button" class="notif-mark" data-notif-readall<?= ($unread ?? 0) > 0 ? '' : ' disabled' ?>><?= icon('check') ?> <?= __('Tandai semua dibaca') ?></button>
                    <a class="notif-all" href="<?= url('notifications') ?>"><?= __('Lihat semua') ?> <?= icon('chev-right') ?></a>
                </div>
            </div>
        </div>

        <div class="dropdown" data-dropdown>
            <button type="button" class="user-chip" data-dropdown-toggle aria-haspopup="true" aria-expanded="false" title="<?= __('Menu Pengguna & Pengaturan') ?>">
                <span class="avatar"><?= e($initial) ?></span>
                <span class="user-chip-info">
                    <span class="u-name"><?= e($user['name'] ?? 'User') ?></span>
                    <span class="u-role"><?= e(role_label($user['role'] ?? null)) ?></span>
                </span>
                <span class="chev"><?= icon('chev-down') ?></span>
            </button>
            <div class="dropdown-menu" role="menu">
                <div class="dropdown-head">
                    <div class="dh-name"><?= e($user['name'] ?? '-') ?></div>
                    <div class="dh-meta"><?= e($user['username'] ?? '-') ?> &middot; <?= e(role_label($user['role'] ?? null)) ?></div>
                </div>

                <div class="dropdown-group-title"><?= __('Akun') ?></div>

                <a class="dropdown-item" href="<?= url('profile') ?>" role="menuitem">
                    <span class="dropdown-item-ico"><?= icon('user-circle') ?></span>
                    <span class="dropdown-item-label"><?= __('Profil & Akses') ?></span>
                </a>

                <div class="dropdown-sep"></div>

                <a class="dropdown-item dropdown-item-danger" href="<?= url('logout') ?>" role="menuitem">
                    <span class="dropdown-item-ico"><?= icon('log-out') ?></span>
                    <span class="dropdown-item-label"><?= __('Keluar') ?></span>
                </a>
            </div>
        </div>
    </div>
</header>
