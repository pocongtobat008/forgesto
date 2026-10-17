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

// ---- Tahun Buku (active period) — compact format: Okt'26 ----
$bp = $activeBookPeriod ?? null;
$bpMonths = ['01'=>'Jan','02'=>'Feb','03'=>'Mar','04'=>'Apr','05'=>'Mei','06'=>'Jun',
             '07'=>'Jul','08'=>'Agu','09'=>'Sep','10'=>'Okt','11'=>'Nov','12'=>'Des'];
$bpFullMonths = ['01'=>'Januari','02'=>'Februari','03'=>'Maret','04'=>'April','05'=>'Mei','06'=>'Juni',
                 '07'=>'Juli','08'=>'Agustus','09'=>'September','10'=>'Oktober','11'=>'November','12'=>'Desember'];
$bpShort = '—';
$bpFull  = 'Belum diatur';
$isClosed = false;
if ($bp) {
    $bpParts = explode('-', (string) ($bp['audit_month'] ?? ''));
    $mm = $bpParts[1] ?? '';
    $yy = $bpParts[0] ?? '';
    $bpShort = ($bpMonths[$mm] ?? ($mm !== '' ? $mm : '—')) . "'" . substr((string) $yy, -2);
    $bpFull  = ($bpFullMonths[$mm] ?? ($mm !== '' ? $mm : '—')) . ' ' . $yy;
    $isClosed = ($bp['status'] ?? 'open') === 'closed';
}
?>
<header class="topbar">
    <div class="topbar-left">
        <!-- Modern SaaS & Startup Sidebar Toggle (Expanded/Collapsed state indicator) -->
        <button type="button" class="topbar-sidebar-toggle" data-sidebar-toggle aria-label="<?= __('Toggle navigasi sidebar') ?>" title="<?= __('Ciutkan / Bentangkan Menu (Ctrl+\\\\)') ?>">
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
            </a>
                <div class="topbar-brand-col">
                    <div class="tb-brand-title">
                        <strong class="tb-name"><?= e(config('app.name')) ?></strong>
                    </div>
                    <a href="<?= url('tahun-buku') ?>" class="tb-period-sub <?= $isClosed ? 'is-closed' : 'is-open' ?>"
                       title="<?= $isClosed ? 'Tahun Buku (Read-Only): ' . e($bpFull) : 'Tahun Buku Aktif: ' . e($bpFull) ?>"
                       data-tip="<?= $isClosed ? 'Tahun Buku (Ditutup): ' . e($bpFull) : 'Tahun Buku Aktif: ' . e($bpFull) ?>"
                       aria-label="<?= $isClosed ? 'Tahun buku ditutup: ' . e($bpFull) : 'Tahun buku berjalan: ' . e($bpFull) ?>"
                       data-no-progress>
                        <span class="env-dot <?= $isClosed ? 'dot-closed' : 'dot-open' ?>" aria-hidden="true"></span>
                        <span class="tb-period-text"><?= e($bpShort) ?></span>
                    </a>
                </div>
        </div>


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

        <!-- Warehouse Selector -->
        <div class="wh-slot">
            <?php
            $__whs = \App\Core\Auth::visibleWarehouseIds();
            $__whList = (new \App\Models\Warehouse())->forActiveUser(\App\Core\Auth::groupIds(), \App\Core\Auth::allGroups());
            $__cur = \App\Core\Auth::activeWarehouse();
            $__curName = $__cur['name'] ?? '—';
            $__allowAll = (bool) \App\Core\Auth::allGroups();
            $__isAll = ! \App\Core\Auth::hasWarehouse();
            ?>
            <form method="post" action="<?= url('warehouses/switch') ?>" class="wh-switch">
                <?= csrf_field() ?>
                <input type="hidden" name="return_to" value="<?= e($_SERVER['REQUEST_URI'] ?? '/dashboard') ?>">
                <?php if (count($__whs) > 1): ?>
                    <!-- Desktop: native select (compact) -->
                    <div class="wh-switch-inner wh-inner-desk" title="<?= __('Gudang Aktif (Pilih untuk berganti)') ?>">
                        <span class="wh-switch-ico" aria-hidden="true"><?= icon('building') ?></span>
                        <select name="warehouse_id" data-wh-switch data-auto-submit onchange="this.form.submit()" aria-label="<?= __('Pilih Gudang') ?>">
                            <?php if ($__allowAll): ?>
                                <option value="all" <?= $__isAll ? 'selected' : '' ?>><?= __('Semua Gudang') ?></option>
                            <?php endif; ?>
                            <?php foreach ($__whList as $__w): ?>
                                <option value="<?= (int) $__w['id'] ?>" <?= (int) \App\Core\Auth::warehouseId() === (int) $__w['id'] ? 'selected' : '' ?>>
                                    <?= e($__w['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Mobile: button that opens a warehouse picker modal -->
                    <button type="button" class="wh-trigger wh-trigger-mob" data-wh-modal-open
                            aria-haspopup="dialog" aria-label="<?= e(__('Pilih Gudang') . ': ' . ($__isAll ? __('Semua Gudang') : $__curName)) ?>"
                            title="<?= e(__('Pilih Gudang')) ?>">
                        <span class="wh-trigger-ico" aria-hidden="true"><?= icon('building') ?></span>
                        <span class="wh-trigger-label"><?= e($__isAll ? __('Semua Gudang') : $__curName) ?></span>
                        <span class="wh-trigger-chev" aria-hidden="true">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg>
                        </span>
                    </button>
                <?php else: ?>
                    <div class="wh-switch-inner">
                        <span class="wh-switch-ico" aria-hidden="true"><?= icon('building') ?></span>
                        <span class="wh-switch-static"><?= e($__curName) ?></span>
                    </div>
                <?php endif; ?>
            </form>
        </div>

        <!-- Theme toggle (moved from sidebar -> header, next to notifications) -->
        <button type="button" class="icon-btn theme-btn" data-theme-toggle
                aria-label="<?= e(__('Ganti Tema (Gelap / Terang)')) ?>"
                title="<?= e(__('Ganti Tema')) ?>">
            <span class="theme-ico theme-ico-sun" aria-hidden="true"><?= icon('sun') ?></span>
            <span class="theme-ico theme-ico-moon" aria-hidden="true"><?= icon('moon') ?></span>
        </button>

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

                <?php $__loc = current_locale(); ?>
                <div class="dropdown-sep"></div>

                <div class="dropdown-group-title"><?= __('Bahasa') ?></div>
                <div class="dropdown-lang" role="group" aria-label="<?= e(__('lang.label')) ?>">
                    <a class="dropdown-lang-opt<?= $__loc === 'id' ? ' is-active' : '' ?>" href="<?= e(locale_url('id')) ?>" hreflang="id" title="<?= e(__('lang.id')) ?>" data-no-progress>
                        <span class="lang-code">ID</span><span class="lang-name">Indonesia</span>
                        <span class="dropdown-lang-check" aria-hidden="true"><?= icon('check') ?></span>
                    </a>
                    <a class="dropdown-lang-opt<?= $__loc === 'en' ? ' is-active' : '' ?>" href="<?= e(locale_url('en')) ?>" hreflang="en" title="<?= e(__('lang.en')) ?>" data-no-progress>
                        <span class="lang-code">EN</span><span class="lang-name">English</span>
                        <span class="dropdown-lang-check" aria-hidden="true"><?= icon('check') ?></span>
                    </a>
                </div>

                <div class="dropdown-sep"></div>

                <a class="dropdown-item dropdown-item-danger" href="<?= url('logout') ?>" role="menuitem">
                    <span class="dropdown-item-ico"><?= icon('log-out') ?></span>
                    <span class="dropdown-item-label"><?= __('Keluar') ?></span>
                </a>
            </div>
        </div>
    </div>
</header>

<?php if (count($__whs) > 1): ?>
<!-- ============================ Warehouse Picker Modal (Mobile) ============================ -->
<div class="wh-modal-backdrop" data-wh-modal-backdrop hidden></div>
<div class="wh-modal" data-wh-modal-dialog role="dialog" aria-modal="true" aria-label="<?= e(__('Pilih Gudang')) ?>" hidden>
    <div class="wh-modal-panel">
        <div class="wh-modal-head">
            <span class="wh-modal-ico" aria-hidden="true"><?= icon('building') ?></span>
            <span class="wh-modal-title"><?= __('Pilih Gudang') ?></span>
            <button type="button" class="wh-modal-close" data-wh-modal-close aria-label="<?= __('Tutup') ?>"><?= icon('x') ?></button>
        </div>
        <div class="wh-modal-body">
            <form method="post" action="<?= url('warehouses/switch') ?>" data-wh-modal-form>
                <?= csrf_field() ?>
                <input type="hidden" name="return_to" value="<?= e($_SERVER['REQUEST_URI'] ?? '/dashboard') ?>">
                <input type="hidden" name="warehouse_id" value="" data-wh-modal-input>
                <div class="wh-list" role="listbox" aria-label="<?= e(__('Daftar Gudang')) ?>">
                    <?php if ($__allowAll): ?>
                    <button type="button" class="wh-opt <?= $__isAll ? 'is-active' : '' ?>" role="option"
                            aria-selected="<?= $__isAll ? 'true' : 'false' ?>" data-wh-value="all">
                        <span class="wh-opt-ico" aria-hidden="true"><?= icon('layers') ?></span>
                        <span class="wh-opt-name"><?= __('Semua Gudang') ?></span>
                        <span class="wh-opt-check" aria-hidden="true"><?= icon('check') ?></span>
                    </button>
                    <?php endif; ?>
                    <?php foreach ($__whList as $__w): $__sel = (int) \App\Core\Auth::warehouseId() === (int) $__w['id']; ?>
                    <button type="button" class="wh-opt <?= $__sel ? 'is-active' : '' ?>" role="option"
                            aria-selected="<?= $__sel ? 'true' : 'false' ?>" data-wh-value="<?= (int) $__w['id'] ?>">
                        <span class="wh-opt-ico" aria-hidden="true"><?= icon('box') ?></span>
                        <span class="wh-opt-name"><?= e($__w['name']) ?></span>
                        <span class="wh-opt-check" aria-hidden="true"><?= icon('check') ?></span>
                    </button>
                    <?php endforeach; ?>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>
