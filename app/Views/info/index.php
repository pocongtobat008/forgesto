<?php
/**
 * Menu Informasi — daftar informasi penting yang dikirim sebagai notifikasi.
 *
 * @var list<array<string,mixed>> $rows
 * @var array{total:int,active:int,notified:int} $summary
 */
$__roleLabel = static function (string $role): string {
    return match ($role) {
        'poweradmin' => 'Power Admin',
        'adminsto'   => 'Admin STO',
        'audit'      => 'Auditor',
        'observer'   => 'Observer',
        default      => 'Semua Pengguna',
    };
};
$__typeBadge = static function (string $type): string {
    return match ($type) {
        'success' => 'badge-success',
        'warning' => 'badge-warning',
        'danger'  => 'badge-danger',
        default   => 'badge-info',
    };
};
?>
<?= page_hero() ?>
<div class="page-head has-hero">
    <div>
        <h1><?= __('Informasi') ?></h1>
        <p><?= __('Informasi penting yang langsung masuk ke notifikasi pengguna.') ?></p>
    </div>
    <div class="spacer"></div>
    <?php if ($canManage): ?>
        <a class="btn btn-brand" href="<?= url('informasi/create') ?>"><?= icon('plus') ?> <?= __('Tulis Informasi') ?></a>
    <?php endif; ?>
</div>

<div class="grid grid-3" style="margin-bottom:1rem;">
    <div class="card"><div class="card-body">
        <div class="cell-sub"><?= __('Total Informasi') ?></div>
        <div class="cell-title" id="stat-total" style="font-size:1.5rem;"><?= (int) $summary['total'] ?></div>
    </div></div>
    <div class="card"><div class="card-body">
        <div class="cell-sub"><?= __('Aktif') ?></div>
        <div class="cell-title" id="stat-active" style="font-size:1.5rem;"><?= (int) $summary['active'] ?></div>
    </div></div>
    <div class="card"><div class="card-body">
        <div class="cell-sub"><?= __('Notifikasi Terkirim') ?></div>
        <div class="cell-title" id="stat-notified" style="font-size:1.5rem;"><?= (int) $summary['notified'] ?></div>
    </div></div>
</div>

<div class="card">
    <div class="card-header">
        <h2><?= __('Daftar Informasi') ?></h2>
        <div class="sf-info-tools">
            <span class="faint text-sm" id="info-updated" data-label="<?= __('Diperbarui') ?>" data-tz="<?= e(date_default_timezone_get()) ?>"><?= __('Diperbarui') ?> <?= e(date('H:i:s')) ?></span>
            <button type="button" class="btn btn-outline btn-xs" data-info-refresh data-mode="page"
                    title="<?= __('Muat ulang daftar informasi tanpa logout') ?>">
                <?= icon('refresh') ?> <span><?= __('Muat ulang') ?></span>
            </button>
        </div>
    </div>
    <div class="table-wrap" id="info-page-zone">
        <table class="data" id="info-table">
            <caption class="sr-only"><?= __('Daftar informasi') ?></caption>
            <thead>
                <tr>
                    <th style="min-width:220px;"><?= __('Judul') ?></th>
                    <th><?= __('Audiens') ?></th>
                    <th class="t-center" style="width:100px;"><?= __('Tipe') ?></th>
                    <th class="t-center" style="width:100px;"><?= __('Status') ?></th>
                    <th style="width:150px;"><?= __('Dibuat') ?></th>
                    <?php if ($canManage): ?>
                        <th class="t-center" style="width:130px;"><?= __('Aksi') ?></th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php if ($rows === []): ?>
                    <tr>
                        <td colspan="<?= $canManage ? 6 : 5 ?>">
                            <div class="empty-state">
                                <div class="empty-icon"><?= icon('info') ?></div>
                                <h3><?= __('Belum ada informasi') ?></h3>
                                <p><?= $canManage ? __('Tulis informasi pertama; pengguna akan menerima notifikasi.') : __('Belum ada informasi dari admin.') ?></p>
                            </div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($rows as $row): ?>
                        <tr>
                            <td data-label="<?= __('Judul') ?>">
                                <div class="cell-title"><?= e((string) $row['title']) ?></div>
                                <?php if (! empty($row['body'])): ?>
                                    <div class="cell-sub"><?= e(mb_strimwidth((string) $row['body'], 0, 110, '…')) ?></div>
                                <?php endif; ?>
                            </td>
                            <td data-label="<?= __('Audiens') ?>"><?= e($__roleLabel((string) ($row['audience_role'] ?? ''))) ?></td>
                            <td data-label="<?= __('Tipe') ?>" class="t-center">
                                <span class="badge <?= $__typeBadge((string) $row['type']) ?>"><?= e(ucfirst((string) $row['type'])) ?></span>
                            </td>
                            <td data-label="<?= __('Status') ?>" class="t-center">
                                <span class="badge <?= (int) $row['is_active'] === 1 ? 'badge-success' : 'badge-neutral' ?>">
                                    <?= (int) $row['is_active'] === 1 ? __('Aktif') : __('Arsip') ?>
                                </span>
                            </td>
                            <td data-label="<?= __('Dibuat') ?>">
                                <div class="text-sm"><?= e(date('d M Y H:i', strtotime((string) $row['created_at']))) ?></div>
                                <div class="cell-sub"><?= e((string) ($row['creator_name'] ?? '—')) ?></div>
                            </td>
                            <?php if ($canManage): ?>
                            <td data-label="<?= __('Aksi') ?>" class="t-center">
                                <div class="btn-group" style="justify-content:center;">
                                    <form method="post" action="<?= url('informasi/' . (int) $row['id'] . '/resend') ?>" style="display:inline;">
                                        <?= csrf_field() ?>
                                        <button type="submit" class="btn btn-outline btn-xs" title="<?= __('Kirim ulang notifikasi') ?>" aria-label="<?= __('Kirim ulang notifikasi') ?>"><?= icon('bell') ?></button>
                                    </form>
                                    <a class="btn btn-outline btn-xs" href="<?= url('informasi/edit/' . (int) $row['id']) ?>" title="<?= __('Edit') ?>" aria-label="<?= __('Edit') ?>"><?= icon('edit') ?></a>
                                    <form method="post" action="<?= url('informasi/' . (int) $row['id'] . '/delete') ?>"
                                          data-confirm="<?= e(__('Hapus informasi ini?')) ?>" style="display:inline;">
                                        <?= csrf_field() ?>
                                        <button type="submit" class="btn btn-danger btn-xs" title="<?= __('Hapus') ?>" aria-label="<?= __('Hapus') ?>"><?= icon('trash') ?></button>
                                    </form>
                                </div>
                            </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
