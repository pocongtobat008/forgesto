<?php
/**
 * Form tulis / edit Informasi.
 *
 * @var array<string,mixed>|null $row
 * @var list<string> $types
 * @var list<string> $roles
 */
$isEdit = $row !== null;
$action = $isEdit ? url('informasi/' . (int) $row['id']) : url('informasi');
$val = static function (string $key, mixed $default = '') use ($row) {
    $fromOld = old($key, null);
    if ($fromOld !== null) {
        return $fromOld;
    }

    return $row[$key] ?? $default;
};
$roleLabel = static fn (string $r): string => match ($r) {
    'poweradmin' => 'Power Admin',
    'adminsto'   => 'Admin STO',
    'audit'      => 'Auditor',
    'observer'   => 'Observer',
    default      => $r,
};
?>
<div class="page-head">
    <div>
        <h1><?= $isEdit ? __('Edit Informasi') : __('Tulis Informasi') ?></h1>
        <p><?= __('Informasi akan dikirim sebagai notifikasi ke audiens yang dipilih.') ?></p>
    </div>
</div>

<div class="card" style="max-width:820px;">
    <div class="card-header"><h2><?= __('Isi Informasi') ?></h2></div>
    <div class="card-body">
        <form method="post" action="<?= $action ?>">
            <?= csrf_field() ?>

            <div class="form-grid">
                <div class="form-group col-span-2">
                    <label for="title"><?= __('Judul') ?> <span class="faint">*</span></label>
                    <input type="text" id="title" name="title" value="<?= e((string) $val('title')) ?>" required maxlength="150"
                           placeholder="<?= __('Contoh: Stock opname dimulai Senin, 08.00') ?>">
                    <?php if ($error = errors('title')): ?><span class="field-error"><?= e($error) ?></span><?php endif; ?>
                </div>

                <div class="form-group col-span-2">
                    <label for="body"><?= __('Pesan') ?></label>
                    <textarea id="body" name="body" rows="6" maxlength="2000"
                              placeholder="<?= __('Detail informasi yang perlu diketahui pengguna...') ?>"><?= e((string) $val('body')) ?></textarea>
                    <span class="faint text-sm"><?= __('Maksimal 2000 karakter. Kalimat pertama muncul di notifikasi.') ?></span>
                </div>

                <div class="form-group">
                    <label for="audience_role"><?= __('Audiens') ?></label>
                    <select id="audience_role" name="audience_role">
                        <option value=""><?= __('Semua Pengguna') ?></option>
                        <?php foreach ($roles as $r): ?>
                            <option value="<?= e($r) ?>" <?= (string) $val('audience_role') === $r ? 'selected' : '' ?>><?= e($roleLabel($r)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="type"><?= __('Tipe Notifikasi') ?></label>
                    <select id="type" name="type">
                        <?php foreach ($types as $t): ?>
                            <option value="<?= e($t) ?>" <?= (string) $val('type', 'info') === $t ? 'selected' : '' ?>><?= e(ucfirst($t)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group col-span-2">
                    <label class="checkbox">
                        <input type="checkbox" name="is_active" value="1" <?= (int) $val('is_active', 1) === 1 ? 'checked' : '' ?>>
                        <?= __('Tampilkan sebagai informasi aktif') ?>
                    </label>
                </div>
            </div>

            <div class="divider"></div>

            <div class="flex gap-sm">
                <button type="submit" class="btn"><?= icon('check') ?> <?= $isEdit ? __('Simpan Perubahan') : __('Simpan & Kirim Notifikasi') ?></button>
                <a class="btn btn-ghost" href="<?= url('informasi') ?>"><?= __('Batal') ?></a>
            </div>
        </form>
    </div>
</div>
