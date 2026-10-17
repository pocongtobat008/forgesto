<?php
/**
 * @var array|null $current
 * @var array $users
 * @var array $groups
 * @var array $roles
 * @var array $abilities
 */
$abilityLabels = [
    'manage_users'       => 'Mengelola user',
    'manage_roles'       => 'Mengelola role akses',
    'manage_groups'      => 'Mengelola group',
    'manage_master'      => 'Mengelola lokasi & device',
    'manage_data'        => 'Tambah / ubah data STO',
    'view_all_groups'    => 'Melihat semua group',
    'count_data'         => 'Menghitung data lokasi',
    'post_data'          => 'Posting data ke audit',
    'audit_data'         => 'Melakukan audit',
    'manage_discrepancy' => 'Menangani selisih',
    'search_discrepancy' => 'Mencari selisih',
    'unlock_data'        => 'Unlock data terkunci',
    'import_data'        => 'Import data Excel/CSV',
    'view_activity'      => 'Melihat aktivitas',
];
$switcherEnabled = (bool) config('app.auth.dev_switcher', true);
?>
<?= page_hero() ?>
<div class="page-head has-hero">
    <div>
        <h1><?= __('Profil &amp; Akses') ?></h1>
        <p>Role aktif, kemampuan akses, dan pengalihan user untuk pengujian.</p>
    </div>
</div>

<?php if (! $switcherEnabled): ?>
    <div class="alert alert-info"><?= icon('info') ?><div><?= __('Pengalihan user sedang dimatikan.') ?></div></div>
<?php endif; ?>

<div class="grid grid-main">
    <div>
        <div class="card">
            <div class="card-header">
                <h2><?= __('Role Aktif') ?></h2>
                <div class="spacer"></div>
                <span class="badge badge-brand"><?= e(role_label($current['role'] ?? null)) ?></span>
            </div>
            <div class="card-body">
                <div class="flex items-center gap">
                    <span class="avatar" style="width:52px;height:52px;font-size:1.2rem;">
                        <?= e(strtoupper(mb_substr((string) ($current['name'] ?? 'S'), 0, 1))) ?>
                    </span>
                    <div>
                        <h2 class="mb-0"><?= e($current['name'] ?? '-') ?></h2>
                        <div class="text-sm muted"><?= e($current['username'] ?? '-') ?> &middot; <?= e($current['email'] ?? '-') ?></div>
                    </div>
                </div>

                <div class="divider"></div>

                <h3 class="mb-1"><?= __('Group yang dapat diakses') ?></h3>
                <?php if (in_array($current['role'] ?? '', ['poweradmin', 'adminsto'], true)): ?>
                    <span class="badge badge-brand"><?= __('Semua group') ?></span>
                <?php elseif ($groups === []): ?>
                    <span class="faint text-sm"><?= __('Belum ada group didelegasikan.') ?></span>
                <?php else: ?>
                    <div class="chip-group">
                        <?php foreach ($groups as $group): ?>
                            <span class="chip"><?= e($group['code']) ?> &middot; <?= e($group['name']) ?></span>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <div class="divider"></div>

                <h3 class="mb-1"><?= __('Kemampuan akses') ?></h3>
                <ul class="list-reset text-sm">
                    <?php foreach ($abilities as $ability => $allowedRoles): ?>
                        <?php $enabled = in_array($current['role'] ?? '', $allowedRoles, true); ?>
                        <li class="list-item" style="padding:.4rem 0;">
                            <span class="li-icon" style="color: <?= $enabled ? 'var(--success-text)' : 'var(--text-faint)' ?>;">
                                <?= icon($enabled ? 'check' : 'x') ?>
                            </span>
                            <span class="li-body">
                                <span class="li-title"><?= e($abilityLabels[$ability] ?? $ability) ?></span>
                                <span class="li-meta"><?= e(implode(', ', array_map('role_label', $allowedRoles))) ?></span>
                            </span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    </div>

    <div>
        <div class="card">
            <div class="card-header">
                <h2><?= __('Pengalihan User') ?></h2>
            </div>
            <div class="card-body">
                <div class="alert alert-info" style="font-size:.8rem;">
                    <?= icon('info') ?>
                    <div>Form login belum dibuat (sesuai fase testing). Pilih user di bawah untuk mengganti role aktif dan menguji hak akses tiap role.</div>
                </div>

                <?php foreach ($roles as $roleKey => $roleName): ?>
                    <?php $roleUsers = array_values(array_filter($users, static fn ($u) => $u['role'] === $roleKey)); ?>
                    <?php if ($roleUsers === []): continue; endif; ?>
                    <h3 class="mt-2 mb-1 text-sm"><?= e($roleName) ?></h3>
                    <ul class="list-reset">
                        <?php foreach ($roleUsers as $user): ?>
                            <?php $isCurrent = (int) ($current['id'] ?? 0) === (int) $user['id']; ?>
                            <li class="list-item">
                                <span class="avatar"><?= e(strtoupper(mb_substr((string) $user['name'], 0, 1))) ?></span>
                                <span class="li-body">
                                    <span class="li-title"><?= e($user['name']) ?></span>
                                    <span class="li-meta"><?= e($user['username']) ?> &middot; <?= e($user['group_codes'] ?: 'semua group') ?></span>
                                </span>
                                <?php if ($isCurrent): ?>
                                    <span class="badge badge-success"><?= __('Aktif') ?></span>
                                <?php else: ?>
                                    <form method="post" action="<?= url('profile/switch') ?>">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="user_id" value="<?= (int) $user['id'] ?>">
                                        <button type="submit" class="btn btn-outline btn-xs" <?= $switcherEnabled ? '' : 'disabled' ?>>Masuk sebagai</button>
                                    </form>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>
