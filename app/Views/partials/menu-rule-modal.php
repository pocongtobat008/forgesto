<?php
/**
 * Modal Pop-Up Quick Rule & Hak Akses per Menu.
 * Muncul di setiap halaman melalui tombol "Rule Menu" di topbar.
 * Konfigurasi dikelola murni berdasarkan peran (ROLE).
 */

use App\Core\Auth;

$__isPowerAdmin = (Auth::role() === 'poweradmin');
$__activeMenuKey = current_menu_key();
$__activeMenuName = current_menu_name();
$__user = Auth::user();
$__userId = (int) ($__user['id'] ?? 0);
$__userRole = (string) ($__user['role'] ?? 'observer');
?>
<div class="sf-modal-backdrop" id="sf-menu-rule-modal" role="dialog" aria-modal="true" style="display:none;position:fixed;inset:0;background:rgba(15,23,42,0.65);backdrop-filter:blur(6px);-webkit-backdrop-filter:blur(6px);z-index:10600;align-items:center;justify-content:center;padding:1rem;">
    <div class="sf-modal" style="width:100%;max-width:640px;background:var(--surface,#ffffff);border:1px solid var(--border,#e2e8f0);border-radius:var(--sf-radius-card, 8px);box-shadow:0 10px 24px rgba(31,41,51,.14);border:1px solid var(--sf-flat-border);overflow:hidden;animation:sfModalIn .2s ease;display:flex;flex-direction:column;max-height:calc(100vh - 2.5rem);">
        <!-- Header -->
        <div class="sf-modal-head" style="display:flex;align-items:center;justify-content:space-between;padding:1.15rem 1.35rem;border-bottom:1px solid var(--border,#e2e8f0);background:var(--surface-2,#f8fafc);flex-shrink:0;">
            <div style="display:flex;align-items:center;gap:.75rem;">
                <div style="width:38px;height:38px;border-radius:10px;background:rgba(240,180,41,0.16);color:var(--brand-ink,#7c2d12);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                    <?= icon('shield-check') ?>
                </div>
                <div>
                    <h3 style="margin:0;font-size:1.1rem;font-weight:700;color:var(--text,#0f172a);display:flex;align-items:center;gap:.5rem;flex-wrap:wrap;">
                        <span>Rule &amp; Akses: <strong id="mrm-menu-title"><?= e($__activeMenuName) ?></strong></span>
                        <code id="mrm-menu-badge" style="font-size:.75rem;padding:.15rem .45rem;border-radius:6px;background:rgba(240,180,41,0.15);color:var(--brand-ink,#7c2d12);font-weight:600;font-family:var(--font-mono, monospace);"><?= e($__activeMenuKey) ?></code>
                    </h3>
                    <p style="margin:0;font-size:.8rem;color:var(--text-muted,#64748b);">
                        Aturan tombol aksi &amp; batasan kolom tabel dikelola berdasarkan peran (Role).
                    </p>
                </div>
            </div>
            <button type="button" class="sf-modal-x" id="mrm-btn-close" aria-label="<?= __('Tutup') ?>" style="background:transparent;border:0;font-size:1.5rem;line-height:1;color:var(--text-muted);cursor:pointer;padding:.25rem .5rem;border-radius:6px;">&times;</button>
        </div>

        <!-- Body -->
        <div class="sf-modal-body" style="padding:1.25rem;display:flex;flex-direction:column;gap:1.15rem;background:var(--surface,#ffffff);overflow-y:auto;flex:1 1 auto;">
            <?php if ($__isPowerAdmin): ?>
                <!-- Mode PowerAdmin: Selector Role -->
                <div style="background:var(--surface-2,#f8fafc);border:1px solid var(--border,#e2e8f0);border-radius:12px;padding:.85rem;display:flex;flex-direction:column;gap:.65rem;">
                    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:.5rem;">
                        <span style="font-size:.8rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--text-muted,#64748b);display:flex;align-items:center;gap:.35rem;">
                            <?= icon('users') ?> Pilih Peran (Role) untuk Diatur:
                        </span>
                        <span class="badge" style="font-size:.72rem;background:rgba(16,185,129,0.12);color:#059669;padding:.2rem .55rem;border-radius:12px;font-weight:600;">Berlaku untuk Semua User Role Ini</span>
                    </div>
                    <div class="mrm-role-tabs" style="display:flex;gap:.4rem;flex-wrap:wrap;" role="tablist">
                        <button type="button" class="mrm-role-tab is-active" data-role="observer" style="padding:.45rem .85rem;border-radius:8px;font-size:.82rem;font-weight:600;cursor:pointer;border:1px solid var(--border);background:var(--surface);color:var(--text);transition:all .15s ease;">
                            Observer (Petugas)
                        </button>
                        <button type="button" class="mrm-role-tab" data-role="audit" style="padding:.45rem .85rem;border-radius:8px;font-size:.82rem;font-weight:600;cursor:pointer;border:1px solid var(--border);background:var(--surface);color:var(--text);transition:all .15s ease;">
                            Auditor
                        </button>
                        <button type="button" class="mrm-role-tab" data-role="adminsto" style="padding:.45rem .85rem;border-radius:8px;font-size:.82rem;font-weight:600;cursor:pointer;border:1px solid var(--border);background:var(--surface);color:var(--text);transition:all .15s ease;">
                            Admin STO
                        </button>
                        <button type="button" class="mrm-role-tab" data-role="poweradmin" style="padding:.45rem .85rem;border-radius:8px;font-size:.82rem;font-weight:600;cursor:pointer;border:1px solid var(--border);background:var(--surface);color:var(--text);transition:all .15s ease;">
                            Power Admin
                        </button>
                    </div>
                </div>
            <?php else: ?>
                <!-- Mode Regular User: Info Card -->
                <div style="background:var(--surface-2,#f8fafc);border:1px solid var(--border,#e2e8f0);border-radius:12px;padding:.85rem;display:flex;align-items:center;gap:.75rem;">
                    <div style="width:36px;height:36px;border-radius:8px;background:rgba(59,130,246,0.15);color:#2563eb;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                        <?= icon('user') ?>
                    </div>
                    <div>
                        <div style="font-size:.875rem;font-weight:700;color:var(--text,#0f172a);">
                            <?= e($__user['name'] ?? 'Pengguna') ?> (<?= e(role_label($__userRole)) ?>)
                        </div>
                        <div style="font-size:.78rem;color:var(--text-muted,#64748b);">
                            Berikut adalah hak akses dan batasan fitur peran <strong><?= e(role_label($__userRole)) ?></strong> pada menu <strong><?= e($__activeMenuName) ?></strong>:
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Loading Spinner State -->
            <div id="mrm-loading" style="display:none;text-align:center;padding:2rem;">
                <div style="display:inline-block;width:28px;height:28px;border:3px solid rgba(240,180,41,0.3);border-radius:50%;border-top-color:var(--brand-500,#f0b429);animation:spin 1s linear infinite;"></div>
                <div style="font-size:.85rem;color:var(--text-muted);margin-top:.6rem;">Memuat aturan peran...</div>
            </div>

            <!-- Content Area -->
            <div id="mrm-content" style="display:flex;flex-direction:column;gap:1.15rem;">
                <!-- Section 1: Hak Aksi Tombol -->
                <div>
                    <label style="font-size:.85rem;font-weight:700;color:var(--text,#0f172a);display:block;margin-bottom:.55rem;">
                        Hak Akses Tombol &amp; Tindakan Menu:
                    </label>
                    <div class="mrm-action-grid" style="display:grid;grid-template-columns:1fr 1fr;gap:.65rem;">
                        <!-- 1. LIHAT MENU -->
                        <div class="mrm-tile" data-action="can_view" style="display:flex;align-items:center;justify-content:space-between;gap:.6rem;padding:.75rem .85rem;border-radius:10px;background:var(--surface,#ffffff);border:1.5px solid var(--border,#e2e8f0);transition:all .15s ease;">
                            <div style="display:flex;align-items:center;gap:.6rem;">
                                <span style="color:var(--brand-500,#f0b429);display:flex;align-items:center;"><?= icon('eye') ?></span>
                                <div>
                                    <div style="font-size:.85rem;font-weight:600;color:var(--text,#0f172a);">Lihat &amp; Buka Menu</div>
                                    <div style="font-size:.72rem;color:var(--text-muted,#64748b);">Tampil di navigasi &amp; rute</div>
                                </div>
                            </div>
                            <?php if ($__isPowerAdmin): ?>
                                <label class="sf-switch" style="margin:0;">
                                    <input type="checkbox" id="mrm-act-view" name="can_view" value="1">
                                    <span class="sf-switch-slider"></span>
                                </label>
                            <?php else: ?>
                                <span class="mrm-status-badge" id="mrm-badge-view">...</span>
                            <?php endif; ?>
                        </div>

                        <!-- 2. TAMBAH DATA -->
                        <div class="mrm-tile" data-action="can_add" style="display:flex;align-items:center;justify-content:space-between;gap:.6rem;padding:.75rem .85rem;border-radius:10px;background:var(--surface,#ffffff);border:1.5px solid var(--border,#e2e8f0);transition:all .15s ease;">
                            <div style="display:flex;align-items:center;gap:.6rem;">
                                <span style="color:#059669;display:flex;align-items:center;"><?= icon('plus') ?></span>
                                <div>
                                    <div style="font-size:.85rem;font-weight:600;color:var(--text,#0f172a);">Tambah Data Baru</div>
                                    <div style="font-size:.72rem;color:var(--text-muted,#64748b);">Tombol tambah / input</div>
                                </div>
                            </div>
                            <?php if ($__isPowerAdmin): ?>
                                <label class="sf-switch" style="margin:0;">
                                    <input type="checkbox" id="mrm-act-add" name="can_add" value="1">
                                    <span class="sf-switch-slider"></span>
                                </label>
                            <?php else: ?>
                                <span class="mrm-status-badge" id="mrm-badge-add">...</span>
                            <?php endif; ?>
                        </div>

                        <!-- 3. EDIT DATA -->
                        <div class="mrm-tile" data-action="can_edit" style="display:flex;align-items:center;justify-content:space-between;gap:.6rem;padding:.75rem .85rem;border-radius:10px;background:var(--surface,#ffffff);border:1.5px solid var(--border,#e2e8f0);transition:all .15s ease;">
                            <div style="display:flex;align-items:center;gap:.6rem;">
                                <span style="color:#2563eb;display:flex;align-items:center;"><?= icon('edit') ?></span>
                                <div>
                                    <div style="font-size:.85rem;font-weight:600;color:var(--text,#0f172a);">Edit &amp; Ubah Data</div>
                                    <div style="font-size:.72rem;color:var(--text-muted,#64748b);">Tombol ubah / revisi</div>
                                </div>
                            </div>
                            <?php if ($__isPowerAdmin): ?>
                                <label class="sf-switch" style="margin:0;">
                                    <input type="checkbox" id="mrm-act-edit" name="can_edit" value="1">
                                    <span class="sf-switch-slider"></span>
                                </label>
                            <?php else: ?>
                                <span class="mrm-status-badge" id="mrm-badge-edit">...</span>
                            <?php endif; ?>
                        </div>

                        <!-- 4. HAPUS DATA -->
                        <div class="mrm-tile" data-action="can_delete" style="display:flex;align-items:center;justify-content:space-between;gap:.6rem;padding:.75rem .85rem;border-radius:10px;background:var(--surface,#ffffff);border:1.5px solid var(--border,#e2e8f0);transition:all .15s ease;">
                            <div style="display:flex;align-items:center;gap:.6rem;">
                                <span style="color:#dc2626;display:flex;align-items:center;"><?= icon('trash') ?></span>
                                <div>
                                    <div style="font-size:.85rem;font-weight:600;color:var(--text,#0f172a);">Hapus Data</div>
                                    <div style="font-size:.72rem;color:var(--text-muted,#64748b);">Tombol hapus baris / massal</div>
                                </div>
                            </div>
                            <?php if ($__isPowerAdmin): ?>
                                <label class="sf-switch" style="margin:0;">
                                    <input type="checkbox" id="mrm-act-delete" name="can_delete" value="1">
                                    <span class="sf-switch-slider"></span>
                                </label>
                            <?php else: ?>
                                <span class="mrm-status-badge" id="mrm-badge-delete">...</span>
                            <?php endif; ?>
                        </div>

                        <!-- 5. POSTING DATA -->
                        <div class="mrm-tile" data-action="can_post" style="display:flex;align-items:center;justify-content:space-between;gap:.6rem;padding:.75rem .85rem;border-radius:10px;background:var(--surface,#ffffff);border:1.5px solid var(--border,#e2e8f0);transition:all .15s ease;">
                            <div style="display:flex;align-items:center;gap:.6rem;">
                                <span style="color:#059669;display:flex;align-items:center;"><?= icon('upload') ?></span>
                                <div>
                                    <div style="font-size:.85rem;font-weight:600;color:var(--text,#0f172a);">Posting Hasil Hitung</div>
                                    <div style="font-size:.72rem;color:var(--text-muted,#64748b);">Tombol posting / submit STO</div>
                                </div>
                            </div>
                            <?php if ($__isPowerAdmin): ?>
                                <label class="sf-switch" style="margin:0;">
                                    <input type="checkbox" id="mrm-act-post" name="can_post" value="1">
                                    <span class="sf-switch-slider"></span>
                                </label>
                            <?php else: ?>
                                <span class="mrm-status-badge" id="mrm-badge-post">...</span>
                            <?php endif; ?>
                        </div>

                        <!-- 6. PIC POSTING -->
                        <div class="mrm-tile" data-action="can_pic_post" style="display:flex;align-items:center;justify-content:space-between;gap:.6rem;padding:.75rem .85rem;border-radius:10px;background:var(--surface,#ffffff);border:1.5px solid var(--border,#e2e8f0);transition:all .15s ease;">
                            <div style="display:flex;align-items:center;gap:.6rem;">
                                <span style="color:#d97706;display:flex;align-items:center;"><?= icon('shield') ?></span>
                                <div>
                                    <div style="font-size:.85rem;font-weight:600;color:var(--text,#0f172a);">PIC Posting Khusus</div>
                                    <div style="font-size:.72rem;color:var(--text-muted,#64748b);">Posting delegasi PIC group</div>
                                </div>
                            </div>
                            <?php if ($__isPowerAdmin): ?>
                                <label class="sf-switch" style="margin:0;">
                                    <input type="checkbox" id="mrm-act-pic-post" name="can_pic_post" value="1">
                                    <span class="sf-switch-slider"></span>
                                </label>
                            <?php else: ?>
                                <span class="mrm-status-badge" id="mrm-badge-pic-post">...</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Section 2: Batasan Kolom Tabel (Hanya tampil jika menu memiliki kolom yang dapat dibatasi) -->
                <div id="mrm-columns-section" style="display:none;">
                    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:.55rem;">
                        <label style="font-size:.85rem;font-weight:700;color:var(--text,#0f172a);margin:0;">
                            Batasan Kolom Tabel:
                        </label>
                        <span style="font-size:.75rem;color:var(--text-muted,#64748b);">
                            <?= $__isPowerAdmin ? 'Klik pill untuk menyembunyikan/menampilkan kolom' : 'Status kolom terlihat/tersembunyi' ?>
                        </span>
                    </div>
                    <div id="mrm-columns-container" style="display:flex;flex-wrap:wrap;gap:.4rem;padding:.75rem;border-radius:10px;background:var(--surface-2,#f8fafc);border:1px solid var(--border,#e2e8f0);">
                        <!-- Pills kolom akan di-generate via JavaScript -->
                    </div>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div class="sf-modal-foot" style="display:flex;align-items:center;justify-content:space-between;padding:1rem 1.35rem;border-top:1px solid var(--border,#e2e8f0);background:var(--surface-2,#f8fafc);flex-shrink:0;flex-wrap:wrap;gap:.65rem;">
            <div style="display:flex;align-items:center;gap:.75rem;">
                <?php if ($__isPowerAdmin): ?>
                    <a href="<?= url('user-rules') ?>" style="font-size:.8rem;color:var(--brand-ink,#7c2d12);font-weight:600;display:inline-flex;align-items:center;gap:.35rem;text-decoration:none;">
                        <?= icon('settings') ?> Kelola Matriks Semua Role &nearr;
                    </a>
                <?php else: ?>
                    <span style="font-size:.78rem;color:var(--text-muted,#64748b);">
                        Hubungi Power Admin jika memerlukan hak akses tambahan.
                    </span>
                <?php endif; ?>
            </div>

            <div style="display:flex;align-items:center;gap:.65rem;">
                <button type="button" class="btn btn-outline" id="mrm-btn-cancel" style="min-height:38px;padding:.45rem .95rem;font-size:.85rem;font-weight:600;">
                    Tutup
                </button>
                <?php if ($__isPowerAdmin): ?>
                    <button type="button" class="btn btn-primary" id="mrm-btn-save" style="min-height:38px;padding:.45rem 1.15rem;font-size:.85rem;font-weight:700;display:inline-flex;align-items:center;gap:.45rem;cursor:pointer;">
                        <?= icon('check') ?>
                        <span id="mrm-btn-save-text">Simpan Rule Role</span>
                    </button>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<style>
/* Styling Pop-up Rule Menu */
.topbar-btn-rule {
    display: inline-flex;
    align-items: center;
    gap: .45rem;
    height: 40px;
    min-height: 40px;
    padding: 0 .75rem;
    border: 1px solid var(--border, #cbd5e1);
    background: var(--surface-2, #f8fafc);
    border-radius: var(--radius-sm, 8px);
    color: var(--text, #0f172a);
    font-size: .85rem;
    font-weight: 600;
    cursor: pointer;
    transition: all .15s ease;
}
.topbar-btn-rule:hover {
    border-color: var(--brand-500, #f0b429);
    background: rgba(240, 180, 41, 0.1);
    color: var(--brand-ink, #7c2d12);
}
.tbr-icon {
    display: flex;
    align-items: center;
    color: var(--brand-500, #f0b429);
}
.tbr-label {
    white-space: nowrap;
}
@media (max-width: 900px) {
    .tbr-label { display: none; }
    .topbar-btn-rule { width: 40px; padding: 0; justify-content: center; }
}

.sf-switch-slider {
    position: relative;
    display: inline-block;
    width: 36px;
    height: 20px;
    background-color: #cbd5e1;
    border-radius: 20px;
    transition: all .2s ease;
    cursor: pointer;
}
.sf-switch-slider:before {
    position: absolute;
    content: "";
    height: 14px;
    width: 14px;
    left: 3px;
    bottom: 3px;
    background-color: white;
    border-radius: 50%;
    transition: all .2s ease;
    box-shadow: none; border: 1px solid var(--sf-flat-border-strong, #c9d0d8);
}
.sf-switch input:checked + .sf-switch-slider {
    background-color: var(--brand-500, #f0b429);
}
.sf-switch input:checked + .sf-switch-slider:before {
    transform: translateX(16px);
}

.mrm-tile {
    cursor: pointer;
    user-select: none;
}
.mrm-tile:hover {
    border-color: var(--brand-500, #f0b429) !important;
    background: var(--surface-2, #f8fafc) !important;
}

.mrm-role-tab.is-active {
    background: #2563eb !important;
    color: #ffffff !important;
    border-color: #1d4ed8 !important;
    box-shadow: 0 1px 3px rgba(37, 99, 235, 0.3) !important;
}
.mrm-col-pill {
    display: inline-flex;
    align-items: center;
    gap: .35rem;
    padding: .35rem .75rem;
    border-radius: 20px;
    font-size: .8rem;
    font-weight: 600;
    cursor: pointer;
    border: 1px solid var(--border, #e2e8f0);
    user-select: none;
    transition: all .15s ease;
}
.mrm-col-pill.is-shown {
    background: rgba(16,185,129,0.1);
    color: #059669;
    border-color: rgba(16,185,129,0.3);
}
.mrm-col-pill.is-shown:hover {
    background: rgba(16,185,129,0.18);
}
.mrm-col-pill.is-hidden {
    background: rgba(239,68,68,0.12);
    color: #dc2626;
    border-color: rgba(239,68,68,0.35);
    text-decoration: line-through;
}
.mrm-col-pill.is-hidden:hover {
    background: rgba(239,68,68,0.22);
}
.mrm-status-badge {
    display: inline-flex;
    align-items: center;
    padding: .2rem .55rem;
    border-radius: 6px;
    font-size: .75rem;
    font-weight: 700;
}
.mrm-status-badge.badge-allowed {
    background: rgba(16,185,129,0.12);
    color: #059669;
}
.mrm-picto {
    width: 14px;
    height: 14px;
    flex: 0 0 auto;
    vertical-align: -2px;
}
.mrm-col-pill.is-hidden {
    text-decoration: none;
}
.mrm-status-badge.badge-restricted {
    background: rgba(239,68,68,0.12);
    color: #dc2626;
}
[data-theme="dark"] .mrm-tile {
    background: #1e293b !important;
    border-color: #334155 !important;
}
[data-theme="dark"] .mrm-role-tab {
    background: #1e293b !important;
    border-color: #334155 !important;
    color: #f8fafc !important;
}
[data-theme="dark"] .mrm-role-tab.is-active {
    background: #2563eb !important;
    color: #ffffff !important;
    border-color: #1d4ed8 !important;
}
@keyframes spin {
    to { transform: rotate(360deg); }
}
</style>

<script>
var MRM_ICON_CHECK = <?= json_encode(icon('check','mrm-picto')) ?>;
var MRM_ICON_X     = <?= json_encode(icon('x','mrm-picto')) ?>;
</script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var modal = document.getElementById('sf-menu-rule-modal');
    var btnOpen = document.getElementById('btn-topbar-menu-rule');
    var btnClose = document.getElementById('mrm-btn-close');
    var btnCancel = document.getElementById('mrm-btn-cancel');
    var btnSave = document.getElementById('mrm-btn-save');
    var isPowerAdmin = <?= $__isPowerAdmin ? 'true' : 'false' ?>;

    var currentMenuKey = '<?= e($__activeMenuKey) ?>';
    var activeRole = 'observer';

    var currentRuleData = null;
    var availableCols = {};
    var hiddenColsSet = new Set();

    function openModal() {
        if (!modal) return;
        modal.style.display = 'flex';
        modal.classList.add('is-open');
        fetchMenuRule();
    }

    function closeModal() {
        if (!modal) return;
        modal.style.display = 'none';
        modal.classList.remove('is-open');
    }

    if (btnOpen) btnOpen.addEventListener('click', openModal);
    if (btnClose) btnClose.addEventListener('click', closeModal);
    if (btnCancel) btnCancel.addEventListener('click', closeModal);

    if (window.location.hash === '#menu-rule' || new URLSearchParams(window.location.search).get('open_menu_rule') === '1') {
        openModal();
    }

    // Tile click to toggle switch (Power Admin)
    document.querySelectorAll('.mrm-tile').forEach(function (tile) {
        tile.addEventListener('click', function (e) {
            if (!isPowerAdmin) return;
            if (e.target.closest('.sf-switch')) return;
            var chk = tile.querySelector('input[type="checkbox"]');
            if (chk) {
                chk.checked = !chk.checked;
                chk.dispatchEvent(new Event('change'));
            }
        });
    });

    if (modal) {
        modal.addEventListener('click', function (e) {
            if (e.target === modal) closeModal();
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && modal.style.display !== 'none') closeModal();
        });
    }

    // Role Tab Switching (Power Admin only)
    document.querySelectorAll('.mrm-role-tab').forEach(function (tab) {
        tab.addEventListener('click', function () {
            document.querySelectorAll('.mrm-role-tab').forEach(function (t) { t.classList.remove('is-active'); });
            this.classList.add('is-active');
            activeRole = this.getAttribute('data-role') || 'observer';
            var saveText = document.getElementById('mrm-btn-save-text');
            if (saveText) {
                saveText.textContent = 'Simpan Rule Role ' + this.textContent.trim();
            }
            fetchMenuRule();
        });
    });

    // Fetch Rule Data from Backend (Purely per Role)
    function fetchMenuRule() {
        var loadingEl = document.getElementById('mrm-loading');
        var contentEl = document.getElementById('mrm-content');
        if (loadingEl) loadingEl.style.display = 'block';
        if (contentEl) contentEl.style.opacity = '0.5';

        var url = '/api/menu-rule?menu_key=' + encodeURIComponent(currentMenuKey);
        if (isPowerAdmin) {
            url += '&role=' + encodeURIComponent(activeRole);
        }

        fetch(url, { headers: { 'Accept': 'application/json' } })
            .then(function (res) { return res.json(); })
            .then(function (resp) {
                if (loadingEl) loadingEl.style.display = 'none';
                if (contentEl) contentEl.style.opacity = '1';
                if (!resp || !resp.success) {
                    alert('Gagal memuat aturan menu: ' + (resp.error || 'Server error'));
                    return;
                }
                renderRuleData(resp);
            })
            .catch(function (err) {
                if (loadingEl) loadingEl.style.display = 'none';
                if (contentEl) contentEl.style.opacity = '1';
                console.error('Error fetching menu rule:', err);
            });
    }

    // Render Data into Form / View
    function renderRuleData(resp) {
        currentRuleData = resp.rule || {};
        availableCols = resp.available_cols || {};

        // Update titles
        var titleEl = document.getElementById('mrm-menu-title');
        var badgeEl = document.getElementById('mrm-menu-badge');
        if (titleEl && resp.menu_name) titleEl.textContent = resp.menu_name;
        if (badgeEl && resp.menu_key) badgeEl.textContent = resp.menu_key;

        // Set action switches / badges
        var actions = ['view', 'add', 'edit', 'delete', 'post', 'pic_post'];
        actions.forEach(function (act) {
            var valKey = act === 'view' ? 'can_view' :
                         act === 'add' ? 'can_add' :
                         act === 'edit' ? 'can_edit' :
                         act === 'delete' ? 'can_delete' :
                         act === 'post' ? 'can_post' : 'can_pic_post';

            var isAllowed = !!currentRuleData[valKey];

            if (isPowerAdmin) {
                var chk = document.getElementById('mrm-act-' + (act === 'pic_post' ? 'pic-post' : act));
                if (chk) chk.checked = isAllowed;
            } else {
                var badge = document.getElementById('mrm-badge-' + (act === 'pic_post' ? 'pic-post' : act));
                if (badge) {
                    badge.className = 'mrm-status-badge ' + (isAllowed ? 'badge-allowed' : 'badge-restricted');
                    var badgeIcon = <?= json_encode(icon('check', 'mrm-picto')) ?>;
                    var badgeIconX = <?= json_encode(icon('x', 'mrm-picto')) ?>;
                    badge.innerHTML = (isAllowed
                        ? badgeIcon  + '<span>Diizinkan</span>'
                        : badgeIconX + '<span>Dibatasi</span>');
                }
            }
        });

        // Column Restriction Section
        var colSec = document.getElementById('mrm-columns-section');
        var colCont = document.getElementById('mrm-columns-container');
        hiddenColsSet = new Set(currentRuleData.hidden_columns || []);

        var colKeys = Object.keys(availableCols);
        if (colKeys.length > 0 && colSec && colCont) {
            colSec.style.display = 'block';
            colCont.innerHTML = '';
            colKeys.forEach(function (k) {
                var colLabel = availableCols[k] || k;
                var isHidden = hiddenColsSet.has(k);

                var pill = document.createElement('div');
                pill.className = 'mrm-col-pill ' + (isHidden ? 'is-hidden' : 'is-shown');
                pill.setAttribute('data-col', k);
                pill.innerHTML = (isHidden ? MRM_ICON_X : MRM_ICON_CHECK) + '<span>' + colLabel + '</span>';

                if (isPowerAdmin) {
                    pill.addEventListener('click', function () {
                        if (hiddenColsSet.has(k)) {
                            hiddenColsSet.delete(k);
                            this.className = 'mrm-col-pill is-shown';
                            this.innerHTML = MRM_ICON_CHECK + '<span>' + colLabel + '</span>';
                        } else {
                            hiddenColsSet.add(k);
                            this.className = 'mrm-col-pill is-hidden';
                            this.innerHTML = MRM_ICON_X + '<span>' + colLabel + '</span>';
                        }
                    });
                }
                colCont.appendChild(pill);
            });
        } else if (colSec) {
            colSec.style.display = 'none';
        }
    }

    // Save Rule Data for Role (Power Admin)
    if (btnSave) {
        btnSave.addEventListener('click', function () {
            var csrfMeta = document.querySelector('meta[name="csrf-token"]');
            var csrf = csrfMeta ? csrfMeta.getAttribute('content') : '';

            var payload = {
                menu_key: currentMenuKey,
                role: activeRole,
                can_view: document.getElementById('mrm-act-view')?.checked ? 1 : 0,
                can_add: document.getElementById('mrm-act-add')?.checked ? 1 : 0,
                can_edit: document.getElementById('mrm-act-edit')?.checked ? 1 : 0,
                can_delete: document.getElementById('mrm-act-delete')?.checked ? 1 : 0,
                can_post: document.getElementById('mrm-act-post')?.checked ? 1 : 0,
                can_pic_post: document.getElementById('mrm-act-pic-post')?.checked ? 1 : 0,
                hidden_columns: Array.from(hiddenColsSet)
            };

            var origHtml = btnSave.innerHTML;
            btnSave.disabled = true;
            btnSave.innerHTML = '<span>Menyimpan...</span>';

            var formData = new URLSearchParams();
            formData.append('_token', csrf);
            for (var key in payload) {
                if (key === 'hidden_columns') {
                    payload[key].forEach(function (col) {
                        formData.append('hidden_columns[]', col);
                    });
                } else {
                    formData.append(key, payload[key]);
                }
            }

            fetch('/api/menu-rule/save', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'Accept': 'application/json'
                },
                body: formData.toString()
            })
            .then(function (res) { return res.json(); })
            .then(function (resp) {
                btnSave.disabled = false;
                btnSave.innerHTML = origHtml;

                if (resp && resp.success) {
                    if (window.sfToast) {
                        window.sfToast(resp.message || 'Rule peran berhasil disimpan!', 'success');
                    } else {
                        alert(resp.message || 'Rule peran berhasil disimpan!');
                    }
                    closeModal();
                    // Reload page to reflect new permissions/columns
                    setTimeout(function () { window.location.reload(); }, 600);
                } else {
                    alert('Gagal menyimpan: ' + (resp.error || 'Server error'));
                }
            })
            .catch(function (err) {
                btnSave.disabled = false;
                btnSave.innerHTML = origHtml;
                console.error('Error saving menu rule:', err);
                alert('Terjadi kesalahan jaringan.');
            });
        });
    }
});
</script>
