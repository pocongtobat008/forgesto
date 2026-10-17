<?php
/**
 * View: Rules Akses per Peran (Role-Based Access Control)
 * Maintenance eksklusif oleh Power Admin.
 *
 * @var string $selectedRole
 * @var array  $rules
 * @var array  $systemMenus
 * @var array  $countsMap
 * @var array  $roles
 */

$selectedRole = (string) ($selectedRole ?? 'observer');

$mainRoles = [
    'observer' => [
        'name'      => 'Observer',
        'sub'       => 'Petugas Lapangan',
        'desc'      => 'Input hitung fisik, scan barcode, & memo STO',
        'icon'      => 'user-check',
        'color'     => '#10b981',
        'badge_cls' => 'badge-success',
    ],
    'audit' => [
        'name'      => 'Auditor',
        'sub'       => 'Pemeriksa Fisik',
        'desc'      => 'Sampling fisik min 10% & verifikasi audit',
        'icon'      => 'clipboard-check',
        'color'     => '#3b82f6',
        'badge_cls' => 'badge-info',
    ],
    'adminsto' => [
        'name'      => 'Admin STO',
        'sub'       => 'Koordinator STO',
        'desc'      => 'Kontrol data STO, approval & posting semua group',
        'icon'      => 'sliders',
        'color'     => '#f59e0b',
        'badge_cls' => 'badge-brand',
    ],
    'poweradmin' => [
        'name'      => 'Power Admin',
        'sub'       => 'Super Administrator',
        'desc'      => 'Hak akses penuh sistem & pengelola aturan role',
        'icon'      => 'shield',
        'color'     => '#ef4444',
        'badge_cls' => 'badge-danger',
    ],
];

$activeMeta = $mainRoles[$selectedRole] ?? $mainRoles['observer'];
$activeCount = (int) ($countsMap[$selectedRole] ?? 0);
?>

<?= page_hero() ?>
<div class="page-head has-hero" style="margin-bottom:1.25rem;">
    <div>
        <div class="breadcrumbs" style="margin-bottom:.35rem;font-size:.825rem;color:var(--text-muted);">
            <a href="<?= url('dashboard') ?>"><?= __('nav.dashboard') ?></a> &rsaquo;
            <a href="<?= url('users') ?>"><?= __('User & Role') ?></a> &rsaquo;
            <span><?= __('Rules Hak Akses Role') ?></span>
        </div>
        <h1 style="display:flex;align-items:center;gap:.6rem;margin:0 0 .35rem 0;">
            <span style="color:var(--brand);"><?= icon('shield') ?></span>
            <?= __('Rules Hak Akses per Peran (Role)') ?>
        </h1>
        <p style="margin:0;color:var(--text-muted);font-size:.9rem;">
            <?= __('Konfigurasi hak akses menu, tombol tindakan, dan batasan kolom tabel dikelola berdasarkan peran. Aturan ini otomatis berlaku untuk <strong>SEMUA</strong> pengguna yang bertugas di peran tersebut.') ?>
            <span class="badge badge-danger" style="margin-left:.5rem;"><?= icon('lock') ?> <?= __('Power Admin Control') ?></span>
        </p>
    </div>
    <div class="spacer"></div>
    <div class="btn-group">
        <a href="<?= url('users') ?>" class="btn btn-outline"><?= icon('users') ?> <?= __('Daftar User (:total)', ['total' => array_sum($countsMap)]) ?></a>
        <button type="button" class="btn btn-outline" id="btn-open-custom-modal"><?= icon('plus') ?> <?= __('Tambah Menu Khusus') ?></button>
    </div>
</div>

<!-- SECTION 1: 4 ROLE SELECTION CARDS (UTAMA) -->
<div style="margin-bottom:1.25rem;">
    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:.5rem;margin-bottom:.65rem;">
        <label style="font-size:.8rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--text-muted);display:flex;align-items:center;gap:.4rem;margin:0;">
            <?= icon('users') ?> <?= __('Pilih Peran (Role) yang Ingin Dikelola:') ?>
        </label>
        <span style="font-size:.8rem;color:var(--text-muted);">
            Klik salah satu kartu peran untuk mengonfigurasi aturannya
        </span>
    </div>

    <!-- 4 Role Cards Grid -->
    <div class="ur-roles-grid" style="display:grid;grid-template-columns:repeat(auto-fit, minmax(240px, 1fr));gap:.85rem;">
        <?php foreach ($mainRoles as $rKey => $rMeta): ?>
            <?php 
                $isActive = ($selectedRole === $rKey);
                $uCount = (int) ($countsMap[$rKey] ?? 0);
            ?>
            <a href="<?= url('user-rules?role=' . $rKey) ?>" 
               class="ur-role-card <?= $isActive ? 'is-active' : '' ?>"
               style="text-decoration:none;display:flex;flex-direction:column;gap:.65rem;padding:1rem 1.15rem;border-radius:var(--sf-radius-card, 8px);border:1px solid <?= $isActive ? 'var(--sf-flat-focus, #2563eb)' : 'var(--sf-flat-border, #dfe3e8)' ?>;background:<?= $isActive ? 'var(--sf-flat-active-bg, #e9eef3)' : 'var(--sf-flat-surface, #ffffff)' ?>;box-shadow:none;transition:border-color .15s ease, background-color .15s ease;position:relative;overflow:hidden;">
                
                <div style="display:flex;align-items:center;justify-content:space-between;gap:.5rem;">
                    <div style="display:flex;align-items:center;gap:.65rem;">
                        <div style="width:38px;height:38px;border-radius:10px;background:<?= $isActive ? 'rgba(37, 99, 235, 0.12)' : 'rgba(0,0,0,0.05)' ?>;color:<?= $isActive ? '#2563eb' : $rMeta['color'] ?>;display:flex;align-items:center;justify-content:center;font-size:1.15rem;flex-shrink:0;">
                            <?= icon($rMeta['icon']) ?>
                        </div>
                        <div>
                            <div style="font-weight:700;font-size:1rem;color:var(--text, #0f172a);line-height:1.2;">
                                <?= e($rMeta['name']) ?>
                            </div>
                            <div style="font-size:.78rem;color:var(--text-muted, #64748b);">
                                <?= e($rMeta['sub']) ?>
                            </div>
                        </div>
                    </div>
                    <?php if ($isActive): ?>
                        <span class="badge badge-brand" style="font-size:.72rem;font-weight:700;padding:.2rem .55rem;border-radius:999px;">
                            Aktif
                        </span>
                    <?php endif; ?>
                </div>

                <div style="font-size:.8rem;color:var(--text-muted, #64748b);line-height:1.4;min-height:2.4em;">
                    <?= e($rMeta['desc']) ?>
                </div>

                <div style="display:flex;align-items:center;justify-content:space-between;padding-top:.5rem;border-top:1px solid <?= $isActive ? 'rgba(240,180,41,0.3)' : 'var(--border, #e2e8f0)' ?>;font-size:.78rem;">
                    <span class="badge <?= $rMeta['badge_cls'] ?>" style="font-size:.75rem;">
                        <?= icon('users') ?> <?= $uCount ?> Pengguna
                    </span>
                    <span style="font-weight:600;color:<?= $isActive ? 'var(--brand-ink, #7c2d12)' : 'var(--text-muted)' ?>;">
                        <?= $isActive ? 'Sedang Dikelola &rarr;' : 'Pilih &rarr;' ?>
                    </span>
                </div>
            </a>
        <?php endforeach; ?>
    </div>

    <!-- Active Role Information & Quick Presets Banner -->
    <div class="card" style="margin-top:.85rem;border-left:4px solid <?= $activeMeta['color'] ?>;background:var(--surface,#ffffff);padding:1rem 1.25rem;">
        <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:.85rem;">
            <div style="display:flex;align-items:center;gap:.85rem;flex:1;min-width:280px;">
                <div style="width:42px;height:42px;border-radius:10px;background:rgba(240,180,41,0.15);color:var(--brand-ink);display:flex;align-items:center;justify-content:center;font-size:1.25rem;flex-shrink:0;">
                    <?= icon('shield-check') ?>
                </div>
                <div>
                    <div style="display:flex;align-items:center;gap:.5rem;flex-wrap:wrap;">
                        <span style="font-size:.78rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--text-muted);">Sedang Mengatur Peran:</span>
                        <strong style="font-size:1.05rem;color:var(--text);"><?= e($activeMeta['name']) ?> (<?= e($activeMeta['sub']) ?>)</strong>
                        <span class="badge <?= $activeMeta['badge_cls'] ?>"><?= $activeCount ?> Pengguna Terdaftar</span>
                    </div>
                    <div style="font-size:.825rem;color:var(--text-muted);margin-top:.2rem;">
                        Aturan menu, tombol aksi, dan kolom di bawah ini berlaku untuk <strong>SEMUA</strong> petugas yang bertugas sebagai <strong><?= e($activeMeta['name']) ?></strong>. Petugas baru otomatis mewarisi konfigurasi ini tanpa perlu pengaturan per-orang.
                    </div>
                </div>
            </div>

            <!-- Quick Presets -->
            <div class="flex items-center gap" style="gap:.5rem;">
                <button type="button" class="btn btn-outline btn-sm" id="btn-preset-all" title="<?= __('Beri semua centang hak akses untuk role ini') ?>">
                    <?= icon('check') ?> <?= __('Buka Semua') ?>
                </button>
                <button type="button" class="btn btn-outline btn-sm" id="btn-preset-readonly" title="<?= __('Hanya izin melihat menu, matikan tombol aksi') ?>">
                    <?= icon('eye') ?> <?= __('Read-Only') ?>
                </button>
                <form method="post" action="<?= url('user-rules/reset') ?>" data-confirm="<?= e(__('Reset semua rules role :role kembali ke standar sistem?', ['role' => $activeMeta['name']])) ?>" style="margin:0;">
                    <?= csrf_field() ?>
                    <input type="hidden" name="role" value="<?= e($selectedRole) ?>">
                    <button type="submit" class="btn btn-ghost btn-sm" style="color:var(--danger);" title="<?= __('Kembalikan ke aturan bawaan sistem') ?>">
                        <?= icon('refresh') ?> <?= __('Reset Default') ?>
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- SECTION 2: STATS / SUMMARY COUNTERS -->
<?php
$totalMenus = count($rules);
$viewCount = 0;
$actionCount = 0;
$postCount = 0;
$hiddenColsCount = 0;
foreach ($rules as $r) {
    if (! empty($r['can_view'])) $viewCount++;
    if (! empty($r['can_add']) || ! empty($r['can_edit']) || ! empty($r['can_delete'])) $actionCount++;
    if (! empty($r['can_post']) || ! empty($r['can_pic_post'])) $postCount++;
    $hiddenColsCount += count($r['hidden_columns'] ?? []);
}
?>
<div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(210px, 1fr));gap:.85rem;margin-bottom:1.25rem;">
    <div class="card" style="padding:.9rem 1.1rem;display:flex;align-items:center;gap:.85rem;">
        <div style="width:40px;height:40px;border-radius:8px;background:rgba(59,130,246,0.12);color:#3b82f6;display:flex;align-items:center;justify-content:center;">
            <?= icon('eye') ?>
        </div>
        <div>
            <div class="mono tabular" style="font-size:1.35rem;font-weight:700;line-height:1.1;color:var(--text);"><?= $viewCount ?> <span style="font-size:.85rem;font-weight:400;color:var(--text-muted);">/ <?= $totalMenus ?></span></div>
            <div style="font-size:.78rem;color:var(--text-muted);margin-top:.15rem;"><?= __('Menu Diizinkan') ?></div>
        </div>
    </div>

    <div class="card" style="padding:.9rem 1.1rem;display:flex;align-items:center;gap:.85rem;">
        <div style="width:40px;height:40px;border-radius:8px;background:rgba(16,185,129,0.12);color:#10b981;display:flex;align-items:center;justify-content:center;">
            <?= icon('plus') ?>
        </div>
        <div>
            <div class="mono tabular" style="font-size:1.35rem;font-weight:700;line-height:1.1;color:var(--text);"><?= $actionCount ?> <span style="font-size:.85rem;font-weight:400;color:var(--text-muted);"><?= __('Menu') ?></span></div>
            <div style="font-size:.78rem;color:var(--text-muted);margin-top:.15rem;"><?= __('Hak Tambah / Edit / Hapus') ?></div>
        </div>
    </div>

    <div class="card" style="padding:.9rem 1.1rem;display:flex;align-items:center;gap:.85rem;">
        <div style="width:40px;height:40px;border-radius:8px;background:rgba(240,180,41,0.15);color:var(--brand);display:flex;align-items:center;justify-content:center;">
            <?= icon('send') ?>
        </div>
        <div>
            <div class="mono tabular" style="font-size:1.35rem;font-weight:700;line-height:1.1;color:var(--text);"><?= $postCount ?> <span style="font-size:.85rem;font-weight:400;color:var(--text-muted);"><?= __('Menu') ?></span></div>
            <div style="font-size:.78rem;color:var(--text-muted);margin-top:.15rem;"><?= __('Hak Posting &amp; PIC') ?></div>
        </div>
    </div>

    <div class="card" style="padding:.9rem 1.1rem;display:flex;align-items:center;gap:.85rem;">
        <div style="width:40px;height:40px;border-radius:8px;background:rgba(239,68,68,0.12);color:#ef4444;display:flex;align-items:center;justify-content:center;">
            <?= icon('eye-off') ?>
        </div>
        <div>
            <div class="mono tabular" style="font-size:1.35rem;font-weight:700;line-height:1.1;color:var(--text);"><?= $hiddenColsCount ?> <span style="font-size:.85rem;font-weight:400;color:var(--text-muted);"><?= __('Kolom') ?></span></div>
            <div style="font-size:.78rem;color:var(--text-muted);margin-top:.15rem;"><?= __('Kolom Tabel Dibatasi') ?></div>
        </div>
    </div>
</div>

<!-- SECTION 3: FORM ATURAN MENU & TABEL -->
<form method="post" action="<?= url('user-rules/save') ?>" id="form-user-rules">
    <?= csrf_field() ?>
    <input type="hidden" name="role" value="<?= e($selectedRole) ?>">

    <div class="card" style="overflow:visible !important;">
        <!-- Toolbar & Filter Tabs -->
        <div class="card-header" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:.75rem;padding:.9rem 1.25rem;">
            <div class="flex items-center gap" style="gap:.5rem;">
                <span class="sf-pill is-active" data-group-filter="all" style="cursor:pointer;"><?= __('Semua Menu') ?> (<?= $totalMenus ?>)</span>
                <span class="sf-pill" data-group-filter="operasional" style="cursor:pointer;"><?= __('Operasional') ?></span>
                <span class="sf-pill" data-group-filter="master" style="cursor:pointer;"><?= __('Data Master') ?></span>
                <span class="sf-pill" data-group-filter="lainnya" style="cursor:pointer;"><?= __('Lainnya') ?></span>
                <span class="sf-pill" data-group-filter="custom" style="cursor:pointer;"><?= __('Menu Khusus') ?></span>
            </div>

            <div style="min-width:220px;max-width:320px;position:relative;">
                <input type="search" id="search-rules-menu" class="sf-rule-input" placeholder="<?= __('Cari menu atau kolom...') ?>" style="padding-left:2.2rem;font-size:.875rem;min-height:38px;height:38px;">
                <span style="position:absolute;left:.75rem;top:50%;transform:translateY(-50%);color:var(--text-muted);pointer-events:none;display:flex;align-items:center;"><?= icon('search') ?></span>
            </div>
        </div>

        <!-- Tabel Matriks Rules -->
        <div class="table-wrap">
            <table class="data" id="table-user-rules" style="table-layout:auto !important;min-width:1050px;">
                <thead>
                    <tr style="background:var(--bg-subtle, rgba(0,0,0,0.02));">
                        <th style="min-width:180px;"><?= __('Menu / Halaman') ?></th>
                        <th class="t-center" style="width:100px;" title="<?= __('Tampilkan menu di sidebar dan izinkan akses buka halaman') ?>"><?= __('Lihat Menu') ?></th>
                        <th class="t-center" style="width:90px;" title="<?= __('Izinkan tombol tambah data baru') ?>"><?= __('Tambah') ?></th>
                        <th class="t-center" style="width:90px;" title="<?= __('Izinkan tombol edit/ubah data') ?>"><?= __('Edit') ?></th>
                        <th class="t-center" style="width:90px;" title="<?= __('Izinkan tombol hapus data') ?>"><?= __('Hapus') ?></th>
                        <th class="t-center" style="width:90px;" title="<?= __('Izinkan tombol posting data') ?>"><?= __('Posting') ?></th>
                        <th class="t-center" style="width:95px;" title="<?= __('Otoritas khusus PIC posting observer') ?>"><?= __('PIC Posting') ?></th>
                        <th style="min-width:260px;" title="<?= __('Batasi kolom tabel yang tidak boleh dilihat oleh role ini') ?>"><?= __('Batasan Kolom Tabel') ?></th>
                        <th class="t-right" style="width:80px;"><?= __('Tipe') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rules as $mKey => $r): ?>
                        <?php
                        $isCustom = ! empty($r['is_custom']);
                        $grp = $r['group'] ?? 'operasional';
                        $availCols = $r['available_cols'] ?? [];
                        $hiddenCols = $r['hidden_columns'] ?? [];
                        ?>
                        <tr class="rule-row" data-group="<?= e($grp) ?>" data-key="<?= e($mKey) ?>" data-name="<?= e(strtolower($r['menu_name'])) ?>">
                            <!-- Nama Menu & Icon -->
                            <td>
                                <div style="display:flex;align-items:center;gap:.65rem;">
                                    <div style="width:32px;height:32px;border-radius:6px;background:var(--bg-subtle);display:flex;align-items:center;justify-content:center;color:var(--text-muted);flex-shrink:0;">
                                        <?= icon($r['icon'] ?: 'sliders') ?>
                                    </div>
                                    <div>
                                        <strong style="color:var(--text);font-size:.925rem;"><?= e($r['menu_name']) ?></strong>
                                        <div style="font-size:.78rem;color:var(--text-muted);" class="mono">/<?= e($mKey) ?></div>
                                    </div>
                                </div>
                                <input type="hidden" name="rules[<?= e($mKey) ?>][menu_name]" value="<?= e($r['menu_name']) ?>">
                                <input type="hidden" name="rules[<?= e($mKey) ?>][is_custom]" value="<?= $isCustom ? '1' : '0' ?>">
                            </td>

                            <!-- 1. LIHAT MENU -->
                            <td class="t-center">
                                <label class="sf-switch" title="<?= e(__('Izin lihat menu /:k', ['k' => $mKey])) ?>">
                                    <input type="checkbox" name="rules[<?= e($mKey) ?>][can_view]" value="1" class="chk-action chk-view" <?= ! empty($r['can_view']) ? 'checked' : '' ?>>
                                    <span class="sf-slider"></span>
                                </label>
                            </td>

                            <!-- 2. TAMBAH -->
                            <td class="t-center">
                                <label class="sf-switch" title="<?= __('Tombol Tambah') ?>">
                                    <input type="checkbox" name="rules[<?= e($mKey) ?>][can_add]" value="1" class="chk-action chk-add" <?= ! empty($r['can_add']) ? 'checked' : '' ?>>
                                    <span class="sf-slider"></span>
                                </label>
                            </td>

                            <!-- 3. EDIT -->
                            <td class="t-center">
                                <label class="sf-switch" title="<?= __('Tombol Edit') ?>">
                                    <input type="checkbox" name="rules[<?= e($mKey) ?>][can_edit]" value="1" class="chk-action chk-edit" <?= ! empty($r['can_edit']) ? 'checked' : '' ?>>
                                    <span class="sf-slider"></span>
                                </label>
                            </td>

                            <!-- 4. HAPUS -->
                            <td class="t-center">
                                <label class="sf-switch" title="<?= __('Tombol Hapus') ?>">
                                    <input type="checkbox" name="rules[<?= e($mKey) ?>][can_delete]" value="1" class="chk-action chk-delete" <?= ! empty($r['can_delete']) ? 'checked' : '' ?>>
                                    <span class="sf-slider"></span>
                                </label>
                            </td>

                            <!-- 5. POSTING -->
                            <td class="t-center">
                                <label class="sf-switch" title="<?= __('Tombol Posting') ?>">
                                    <input type="checkbox" name="rules[<?= e($mKey) ?>][can_post]" value="1" class="chk-action chk-post" <?= ! empty($r['can_post']) ? 'checked' : '' ?>>
                                    <span class="sf-slider"></span>
                                </label>
                            </td>

                            <!-- 6. PIC POSTING -->
                            <td class="t-center">
                                <label class="sf-switch" title="<?= __('Otoritas PIC Posting') ?>">
                                    <input type="checkbox" name="rules[<?= e($mKey) ?>][can_pic_post]" value="1" class="chk-action chk-pic-post" <?= ! empty($r['can_pic_post']) ? 'checked' : '' ?>>
                                    <span class="sf-slider"></span>
                                </label>
                            </td>

                            <!-- 7. BATASAN KOLOM TABEL -->
                            <td>
                                <?php if ($availCols !== []): ?>
                                    <div class="col-restriction-box" style="display:flex;flex-wrap:wrap;gap:.35rem;align-items:center;">
                                        <?php foreach ($availCols as $colKey => $colLabel): ?>
                                            <?php $isColHidden = in_array($colKey, $hiddenCols, true); ?>
                                            <label class="col-pill-toggle <?= $isColHidden ? 'is-hidden-pill' : 'is-shown-pill' ?>" style="display:inline-flex;align-items:center;gap:.3rem;padding:.2rem .55rem;border-radius:14px;font-size:.78rem;cursor:pointer;user-select:none;border:1px solid var(--border);transition:all .15s ease;" title="<?= $isColHidden ? __('Kolom ini DISEMBUYIKAN dari role ini') : __('Kolom ini DITAMPILKAN ke role ini') ?>">
                                                <input type="checkbox" name="rules[<?= e($mKey) ?>][hidden_columns][]" value="<?= e($colKey) ?>" class="col-hidden-input" <?= $isColHidden ? 'checked' : '' ?> style="display:none;">
                                                <span class="col-pill-icon"><?= $isColHidden ? icon('eye-off') : icon('eye') ?></span>
                                                <span class="col-pill-name"><?= e($colLabel) ?></span>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <span style="color:var(--text-muted);font-size:.8rem;">- <?= __('Tidak ada batasan tabel') ?> -</span>
                                <?php endif; ?>
                            </td>

                            <!-- 8. TIPE / AKSI KHUSUS -->
                            <td class="t-right">
                                <?php if ($isCustom): ?>
                                    <div class="flex items-center gap" style="justify-content:flex-end;">
                                        <span class="badge badge-warning" style="font-size:.725rem;"><?= __('Khusus') ?></span>
                                        <button type="button" class="btn btn-ghost btn-xs btn-del-custom" data-key="<?= e($mKey) ?>" title="<?= __('Hapus Menu Khusus') ?>" style="color:var(--danger);">
                                            <?= icon('trash') ?>
                                        </button>
                                    </div>
                                <?php else: ?>
                                    <span class="badge badge-outline" style="font-size:.725rem;"><?= __('Sistem') ?></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Sticky Bottom Bar -->
        <div class="card-footer" style="position:sticky;bottom:0;background:var(--surface,#ffffff);border-top:1px solid var(--border);padding:1rem 1.35rem;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:1rem;z-index:10;box-shadow:none;">
            <div class="flex items-center gap">
                <span style="display:flex;align-items:center;gap:.5rem;font-size:.88rem;color:var(--text-muted);">
                    <span style="color:var(--brand);"><?= icon('shield-check') ?></span>
                    <span><?= __('Aturan ini akan diterapkan langsung untuk') ?> <strong><?= __('SEMUA') ?></strong> <?= __('pengguna ber-role') ?> <strong style="color:var(--text);"><?= e($activeMeta['name']) ?></strong> (<?= $activeCount ?> <?= __('user') ?>).</span>
                </span>
            </div>

            <div class="flex items-center gap">
                <a href="<?= url('user-rules?role=' . $selectedRole) ?>" class="btn btn-ghost"><?= __('Batal / Muat Ulang') ?></a>
                <button type="submit" class="btn btn-primary" style="min-height:44px;padding:0 1.5rem;font-weight:700;display:inline-flex;align-items:center;gap:.5rem;box-shadow:none;">
                    <?= icon('shield-check') ?> <?= __('Simpan Rule Role :role', ['role' => $activeMeta['name']]) ?>
                </button>
            </div>
        </div>
    </div>
</form>

<!-- MODAL TAMBAH MENU KHUSUS -->
<div class="sf-modal-backdrop" id="modal-custom-menu" role="dialog" aria-modal="true" style="display:none;position:fixed;inset:0;background:rgba(15,23,42,0.6);backdrop-filter:blur(6px);-webkit-backdrop-filter:blur(6px);z-index:10500;align-items:center;justify-content:center;padding:1rem;">
    <div class="sf-modal" style="width:100%;max-width:540px;background:var(--surface,#ffffff);border:1px solid var(--border,#e2e8f0);border-radius:var(--sf-radius-card, 8px);box-shadow:0 10px 24px rgba(31,41,51,.14);border:1px solid var(--sf-flat-border);overflow:hidden;animation:sfModalIn .2s ease;">
        <div class="sf-modal-head" style="display:flex;align-items:center;justify-content:space-between;padding:1.15rem 1.35rem;border-bottom:1px solid var(--border,#e2e8f0);background:var(--surface-2,#f8fafc);">
            <div style="display:flex;align-items:center;gap:.65rem;">
                <div style="width:34px;height:34px;border-radius:8px;background:rgba(240,180,41,0.15);color:var(--brand-ink);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                    <?= icon('plus') ?>
                </div>
                <h3 style="margin:0;font-size:1.1rem;font-weight:700;color:var(--text,#0f172a);"><?= __('Tambah Menu Khusus untuk Role :role', ['role' => $activeMeta['name']]) ?></h3>
            </div>
            <button type="button" class="sf-modal-x" id="btn-close-custom-modal" aria-label="<?= __('Tutup') ?>">&times;</button>
        </div>
        <form method="post" action="<?= url('user-rules/custom/add') ?>" style="margin:0;">
            <?= csrf_field() ?>
            <input type="hidden" name="role" value="<?= e($selectedRole) ?>">
            <div class="sf-modal-body" style="padding:1.35rem;display:flex;flex-direction:column;gap:1.15rem;background:var(--surface,#ffffff);">
                <p style="font-size:.875rem;color:var(--text-muted,#64748b);margin:0;line-height:1.5;">
                    <?= __('Tambahkan menu khusus atau jalur rute baru untuk role <strong>:role</strong>.', ['role' => $activeMeta['name']]) ?>
                </p>

                <div class="form-group" style="margin:0;">
                    <label for="custom_name" style="font-weight:600;font-size:.875rem;display:block;margin-bottom:.4rem;color:var(--text,#0f172a);">
                        <?= __('Nama Menu Khusus:') ?>
                    </label>
                    <input type="text" id="custom_name" name="custom_name" class="sf-rule-input" placeholder="<?= __('Contoh: Laporan Penyesuaian STO') ?>" required autocomplete="off">
                </div>

                <div class="form-group" style="margin:0;">
                    <label for="custom_key" style="font-weight:600;font-size:.875rem;display:block;margin-bottom:.4rem;color:var(--text,#0f172a);">
                        <?= __('Key / Slug Rute Menu:') ?>
                    </label>
                    <input type="text" id="custom_key" name="custom_key" class="sf-rule-input mono" placeholder="<?= __('Contoh: laporan-khusus') ?>" required autocomplete="off">
                    <span class="hint" style="font-size:.78rem;color:var(--text-muted,#64748b);display:block;margin-top:.25rem;"><?= __('Gunakan huruf kecil, angka, garis hubung (-).') ?></span>
                </div>

                <div style="margin:0;">
                    <label style="font-weight:600;font-size:.875rem;display:block;margin-bottom:.5rem;color:var(--text,#0f172a);">
                        <?= __('Izin Akses Default Menu Khusus:') ?>
                    </label>
                    <div class="custom-perm-grid" style="display:grid;grid-template-columns:1fr 1fr;gap:.65rem;background:var(--surface-2,#f8fafc);border:1px solid var(--border,#e2e8f0);border-radius:12px;padding:.85rem;">
                        <!-- 1. LIHAT MENU -->
                        <div class="custom-perm-tile" data-chk="chk_custom_view">
                            <div class="perm-tile-label">
                                <span class="perm-tile-icon" style="color:var(--brand-ink);"><?= icon('eye') ?></span>
                                <span><?= __('Lihat Menu') ?></span>
                            </div>
                            <label class="sf-switch" style="margin:0;" onclick="event.stopPropagation();">
                                <input type="checkbox" id="chk_custom_view" name="can_view" value="1" checked class="custom-perm-chk">
                                <span class="sf-slider"></span>
                            </label>
                        </div>

                        <!-- 2. TAMBAH DATA -->
                        <div class="custom-perm-tile" data-chk="chk_custom_add">
                            <div class="perm-tile-label">
                                <span class="perm-tile-icon" style="color:var(--brand-ink);"><?= icon('plus') ?></span>
                                <span><?= __('Tambah Data') ?></span>
                            </div>
                            <label class="sf-switch" style="margin:0;" onclick="event.stopPropagation();">
                                <input type="checkbox" id="chk_custom_add" name="can_add" value="1" class="custom-perm-chk">
                                <span class="sf-slider"></span>
                            </label>
                        </div>

                        <!-- 3. EDIT DATA -->
                        <div class="custom-perm-tile" data-chk="chk_custom_edit">
                            <div class="perm-tile-label">
                                <span class="perm-tile-icon" style="color:var(--brand-ink);"><?= icon('edit') ?></span>
                                <span><?= __('Edit Data') ?></span>
                            </div>
                            <label class="sf-switch" style="margin:0;" onclick="event.stopPropagation();">
                                <input type="checkbox" id="chk_custom_edit" name="can_edit" value="1" class="custom-perm-chk">
                                <span class="sf-slider"></span>
                            </label>
                        </div>

                        <!-- 4. HAPUS DATA -->
                        <div class="custom-perm-tile" data-chk="chk_custom_del">
                            <div class="perm-tile-label">
                                <span class="perm-tile-icon" style="color:var(--danger-ink, #b91c1c);"><?= icon('trash') ?></span>
                                <span><?= __('Hapus Data') ?></span>
                            </div>
                            <label class="sf-switch" style="margin:0;" onclick="event.stopPropagation();">
                                <input type="checkbox" id="chk_custom_del" name="can_delete" value="1" class="custom-perm-chk">
                                <span class="sf-slider"></span>
                            </label>
                        </div>

                        <!-- 5. POSTING DATA -->
                        <div class="custom-perm-tile" data-chk="chk_custom_post">
                            <div class="perm-tile-label">
                                <span class="perm-tile-icon" style="color:var(--success-ink, #047857);"><?= icon('upload') ?></span>
                                <span><?= __('Posting Data') ?></span>
                            </div>
                            <label class="sf-switch" style="margin:0;" onclick="event.stopPropagation();">
                                <input type="checkbox" id="chk_custom_post" name="can_post" value="1" class="custom-perm-chk">
                                <span class="sf-slider"></span>
                            </label>
                        </div>

                        <!-- 6. PIC POSTING -->
                        <div class="custom-perm-tile" data-chk="chk_custom_pic">
                            <div class="perm-tile-label">
                                <span class="perm-tile-icon" style="color:var(--warning-ink, #b45309);"><?= icon('shield-check') ?></span>
                                <span><?= __('PIC Posting') ?></span>
                            </div>
                            <label class="sf-switch" style="margin:0;" onclick="event.stopPropagation();">
                                <input type="checkbox" id="chk_custom_pic" name="can_pic_post" value="1" class="custom-perm-chk">
                                <span class="sf-slider"></span>
                            </label>
                        </div>
                    </div>
                </div>
            </div>
            <div class="sf-modal-foot" style="padding:.95rem 1.35rem;display:flex;justify-content:flex-end;gap:.65rem;border-top:1px solid var(--border,#e2e8f0);background:var(--surface-2,#f8fafc);">
                <button type="button" class="btn btn-outline" id="btn-cancel-custom-modal" style="min-height:40px;"><?= __('Batal') ?></button>
                <button type="submit" class="btn btn-primary" style="min-height:40px;padding:0 1.25rem;font-weight:700;display:inline-flex;align-items:center;gap:.4rem;">
                    <?= icon('plus') ?> <?= __('Simpan Menu Khusus') ?>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- FORM HAPUS CUSTOM MENU (Hidden) -->
<form method="post" action="<?= url('user-rules/custom/delete') ?>" id="form-delete-custom" style="display:none;">
    <?= csrf_field() ?>
    <input type="hidden" name="role" value="<?= e($selectedRole) ?>">
    <input type="hidden" name="menu_key" id="delete-custom-menu-key" value="">
</form>

<style>
/* Styling Tambahan User Rules */
.sf-rule-input {
    width: 100%;
    padding: .5rem .75rem;
    border: 1px solid var(--border, #cbd5e1);
    background: var(--surface, #ffffff);
    border-radius: var(--radius-sm, 8px);
    color: var(--text, #0f172a);
    font: inherit;
    box-sizing: border-box;
    transition: all .15s ease;
}
.sf-rule-input:focus {
    outline: none;
    border-color: var(--brand, #f0b429);
    box-shadow: 0 0 0 2px rgba(37,99,235,.16);
}

.ur-role-card:hover {
    border-color: var(--cc-face, #f4eddb) !important;
    transform: none;
}
.ur-role-card.is-active {
    border-color: var(--cc-face, #f4eddb) !important;
}

.col-pill-toggle {
    transition: all .15s ease;
}
.col-pill-toggle.is-shown-pill {
    background: rgba(16, 185, 129, 0.1);
    color: #059669;
    border-color: rgba(16, 185, 129, 0.3) !important;
}
.col-pill-toggle.is-shown-pill:hover {
    background: rgba(16, 185, 129, 0.2);
}
.col-pill-toggle.is-hidden-pill {
    background: rgba(239, 68, 68, 0.1);
    color: #dc2626;
    border-color: rgba(239, 68, 68, 0.3) !important;
    text-decoration: line-through;
}
.col-pill-toggle.is-hidden-pill:hover {
    background: rgba(239, 68, 68, 0.2);
}

.custom-perm-tile {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: .5rem;
    padding: .65rem .85rem;
    border-radius: 8px;
    background: var(--surface, #ffffff);
    border: 1.5px solid var(--border, #e2e8f0);
    cursor: pointer;
    user-select: none;
    transition: all .15s ease;
}
.custom-perm-tile:hover {
    border-color: var(--cc-face, #f4eddb);
}
.custom-perm-tile.is-active {
    border-color: var(--cc-face, #f4eddb);
    background: rgba(240, 180, 41, 0.08);
}
.perm-tile-label {
    display: flex;
    align-items: center;
    gap: .5rem;
    font-size: .85rem;
    font-weight: 600;
    color: var(--text, #0f172a);
}
.perm-tile-icon {
    display: flex;
    align-items: center;
}
.perm-tile-icon svg {
    width: 16px;
    height: 16px;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function () {
    // 1. Column Restriction Toggle (Interactive Pills)
    document.querySelectorAll('.col-pill-toggle').forEach(function (lbl) {
        var chk = lbl.querySelector('.col-hidden-input');
        if (!chk) return;
        lbl.addEventListener('click', function (e) {
            e.preventDefault();
            chk.checked = !chk.checked;
            if (chk.checked) {
                lbl.classList.remove('is-shown-pill');
                lbl.classList.add('is-hidden-pill');
                lbl.title = 'Kolom ini DISEMBUNYIKAN dari role ini';
                var iconSpan = lbl.querySelector('.col-pill-icon');
                if (iconSpan) iconSpan.innerHTML = '<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line></svg>';
            } else {
                lbl.classList.remove('is-hidden-pill');
                lbl.classList.add('is-shown-pill');
                lbl.title = 'Kolom ini DITAMPILKAN ke role ini';
                var iconSpan = lbl.querySelector('.col-pill-icon');
                if (iconSpan) iconSpan.innerHTML = '<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>';
            }
        });
    });

    // 2. Preset Buka Semua
    var btnPresetAll = document.getElementById('btn-preset-all');
    if (btnPresetAll) {
        btnPresetAll.addEventListener('click', function () {
            document.querySelectorAll('.chk-action').forEach(function (chk) {
                chk.checked = true;
            });
            document.querySelectorAll('.col-hidden-input').forEach(function (chk) {
                chk.checked = false;
            });
            document.querySelectorAll('.col-pill-toggle').forEach(function (lbl) {
                lbl.classList.remove('is-hidden-pill');
                lbl.classList.add('is-shown-pill');
                var iconSpan = lbl.querySelector('.col-pill-icon');
                if (iconSpan) iconSpan.innerHTML = '<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>';
            });
        });
    }

    // 3. Preset Read-Only
    var btnPresetRo = document.getElementById('btn-preset-readonly');
    if (btnPresetRo) {
        btnPresetRo.addEventListener('click', function () {
            document.querySelectorAll('.chk-view').forEach(function (chk) {
                chk.checked = true;
            });
            document.querySelectorAll('.chk-add, .chk-edit, .chk-delete, .chk-post, .chk-pic-post').forEach(function (chk) {
                chk.checked = false;
            });
        });
    }

    // 4. Tab Filter Kategori Menu
    var pills = document.querySelectorAll('.sf-pill[data-group-filter]');
    var rows = document.querySelectorAll('.rule-row');
    pills.forEach(function (p) {
        p.addEventListener('click', function () {
            pills.forEach(function (x) { x.classList.remove('is-active'); });
            p.classList.add('is-active');
            var grp = p.getAttribute('data-group-filter');
            rows.forEach(function (r) {
                if (grp === 'all' || r.getAttribute('data-group') === grp) {
                    r.style.display = '';
                } else {
                    r.style.display = 'none';
                }
            });
        });
    });

    // 5. Live Search Menu Filter
    var searchInput = document.getElementById('search-rules-menu');
    if (searchInput) {
        searchInput.addEventListener('input', function () {
            var q = this.value.toLowerCase().trim();
            rows.forEach(function (r) {
                var name = r.getAttribute('data-name') || '';
                var key = r.getAttribute('data-key') || '';
                if (q === '' || name.indexOf(q) !== -1 || key.indexOf(q) !== -1) {
                    r.style.display = '';
                } else {
                    r.style.display = 'none';
                }
            });
        });
    }

    // 6. Modal Tambah Menu Khusus
    var modal = document.getElementById('modal-custom-menu');
    var btnOpenModal = document.getElementById('btn-open-custom-modal');
    var btnCloseModal = document.getElementById('btn-close-custom-modal');
    var btnCancelModal = document.getElementById('btn-cancel-custom-modal');

    if (btnOpenModal && modal) {
        btnOpenModal.addEventListener('click', function () {
            modal.style.display = 'flex';
            modal.classList.add('is-open');
            var inp = document.getElementById('custom_name');
            if (inp) inp.focus();
        });
        if (window.location.hash === '#modal-custom' || new URLSearchParams(window.location.search).get('open_modal') === '1') {
            modal.style.display = 'flex';
            modal.classList.add('is-open');
        }
    }
    var closeModalFn = function () {
        if (modal) {
            modal.style.display = 'none';
            modal.classList.remove('is-open');
        }
    };
    if (btnCloseModal) btnCloseModal.addEventListener('click', closeModalFn);
    if (btnCancelModal) btnCancelModal.addEventListener('click', closeModalFn);
    if (modal) {
        modal.addEventListener('click', function (e) {
            if (e.target === modal) closeModalFn();
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && modal.style.display !== 'none') {
                closeModalFn();
            }
        });
    }

    // Permission Tiles Click Handling in Modal
    document.querySelectorAll('.custom-perm-tile').forEach(function (tile) {
        var chkId = tile.getAttribute('data-chk');
        var chk = document.getElementById(chkId);
        if (!chk) return;
        var sync = function () {
            if (chk.checked) {
                tile.classList.add('is-active');
            } else {
                tile.classList.remove('is-active');
            }
        };
        sync();
        chk.addEventListener('change', sync);
        tile.addEventListener('click', function (e) {
            if (e.target.closest('.sf-switch')) return;
            chk.checked = !chk.checked;
            chk.dispatchEvent(new Event('change'));
        });
    });

    // Auto-generate slug key from name
    var customName = document.getElementById('custom_name');
    var customKey = document.getElementById('custom_key');
    if (customName && customKey) {
        customName.addEventListener('input', function () {
            if (!customKey.dataset.touched) {
                customKey.value = this.value.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');
            }
        });
        customKey.addEventListener('input', function () {
            this.dataset.touched = 'true';
        });
    }

    // 7. Delete Custom Menu Action
    document.querySelectorAll('.btn-del-custom').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var k = this.getAttribute('data-key');
            if (!k) return;
            if (confirm('Hapus menu khusus "' + k + '"?')) {
                var delForm = document.getElementById('form-delete-custom');
                var inp = document.getElementById('delete-custom-menu-key');
                if (delForm && inp) {
                    inp.value = k;
                    delForm.submit();
                }
            }
        });
    });
});
</script>
