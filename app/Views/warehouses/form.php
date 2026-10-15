<?php
/**
 * @var array|null $warehouse
 */
$isEdit = $warehouse !== null;
$action = $isEdit ? url('warehouses/' . (int) $warehouse['id']) : url('warehouses');

$value = static function (string $key, mixed $default = '') use ($warehouse) {
    $fromOld = old($key, null);
    if ($fromOld !== null) {
        return $fromOld;
    }

    return $warehouse[$key] ?? $default;
};
?>
<div class="page-head">
    <div>
        <h1><?= $isEdit ? __('Edit Gudang') : __('Tambah Gudang') ?></h1>
        <p>Gudang/situs adalah induk dari Group. Satu group terikat ke satu gudang.</p>
    </div>
    <div class="spacer"></div>
    <a class="btn btn-ghost" href="<?= url('warehouses') ?>"><?= icon('arrow-left') ?> Kembali</a>
</div>

<form method="post" action="<?= $action ?>" class="wh-form">
    <?= csrf_field() ?>

    <div class="card">
        <div class="card-header">
            <h2><?= icon('building') ?> Identitas Gudang</h2>
            <div class="spacer"></div>
            <span class="wh-form-step">1</span>
        </div>
        <div class="card-body">
            <div class="form-grid">
                <div class="form-group">
                    <label for="code">Kode Gudang <span class="faint">*</span></label>
                    <input type="text" id="code" name="code" value="<?= e($value('code')) ?>" required maxlength="50" placeholder="<?= __('WH-DPK') ?>" autocomplete="off">
                    <span class="field-hint"><?= __('Singkatan unik, mis. WH-DPK untuk Gudang Depok.') ?></span>
                    <?php if ($error = errors('code')): ?><span class="field-error"><?= e($error) ?></span><?php endif; ?>
                </div>

                <div class="form-group">
                    <label for="name">Nama Gudang <span class="faint">*</span></label>
                    <input type="text" id="name" name="name" value="<?= e($value('name')) ?>" required maxlength="150" placeholder="<?= __('Gudang Depok') ?>" autocomplete="off">
                    <?php if ($error = errors('name')): ?><span class="field-error"><?= e($error) ?></span><?php endif; ?>
                </div>

                <div class="form-group form-span-2">
                    <label for="city"><?= __('Kota / Plant') ?></label>
                    <input type="text" id="city" name="city" value="<?= e($value('city')) ?>" maxlength="100" placeholder="<?= __('Depok') ?>" autocomplete="off">
                    <span class="field-hint"><?= __('Dipakai sebagai label lokasi pada daftar dan header switcher.') ?></span>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h2><?= icon('pin') ?> Alamat &amp; Status</h2>
            <div class="spacer"></div>
            <span class="wh-form-step">2</span>
        </div>
        <div class="card-body">
            <div class="form-grid">
                <div class="form-group form-span-2">
                    <label for="address"><?= __('Alamat Lengkap') ?></label>
                    <textarea id="address" name="address" rows="3" maxlength="255" placeholder="<?= __('Jl. Raya Bogor KM 30, Cimanggis, Depok, Jawa Barat 16452') ?>"><?= e($value('address')) ?></textarea>
                </div>

                <div class="form-group form-span-2">
                    <label><?= __('Status Gudang') ?></label>
                    <label class="switch-field">
                        <input type="checkbox" name="is_active" value="1" <?= (int) $value('is_active', 1) === 1 ? 'checked' : '' ?>>
                        <span class="switch-track" aria-hidden="true"><span class="switch-knob"></span></span>
                        <span class="switch-text">
                            <strong><?= __('Gudang aktif') ?></strong>
                            <small>Nonaktifkan bila situs ini tidak lagi dipakai untuk stock take.</small>
                        </span>
                    </label>
                </div>
            </div>
        </div>
        <div class="card-footer wh-form-footer">
            <a class="btn btn-ghost" href="<?= url('warehouses') ?>">Batal</a>
            <button type="submit" class="btn btn-brand"><?= icon('check') ?> <?= $isEdit ? __('Simpan Perubahan') : __('Simpan Gudang') ?></button>
        </div>
    </div>
</form>