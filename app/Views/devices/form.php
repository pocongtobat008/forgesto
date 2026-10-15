<?php
/**
 * @var array|null $device
 * @var array $locations
 * @var array $types
 */
$isEdit = $device !== null;
$action = $isEdit ? url('devices/' . (int) $device['id']) : url('devices');

$value = static function (string $key, mixed $default = '') use ($device) {
    $fromOld = old($key, null);
    if ($fromOld !== null) {
        return $fromOld;
    }

    return $device[$key] ?? $default;
};
?>
<div class="page-head">
    <div>
        <h1><?= $isEdit ? __('Edit Device') : __('Tambah Device') ?></h1>
        <p><?= __('Device selalu terikat pada satu lokasi.') ?></p>
    </div>
</div>

<div class="card" style="max-width:760px;">
    <div class="card-header"><h2><?= __('Informasi Device') ?></h2></div>
    <div class="card-body">
        <form method="post" action="<?= $action ?>">
            <?= csrf_field() ?>

            <div class="form-grid">
                <div class="form-group col-span-2">
                    <label for="location_id">Lokasi <span class="faint">*</span></label>
                    <select id="location_id" name="location_id" required>
                        <option value="">— Pilih Lokasi —</option>
                        <?php foreach ($locations as $location): ?>
                            <option value="<?= (int) $location['id'] ?>" <?= (int) $value('location_id', 0) === (int) $location['id'] ? 'selected' : '' ?>>
                                <?= e($location['group_name']) ?> &middot; <?= e($location['name']) ?> (<?= e($location['code']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if ($error = errors('location_id')): ?><span class="field-error"><?= e($error) ?></span><?php endif; ?>
                </div>

                <div class="form-group">
                    <label for="code">Kode Device <span class="faint">*</span></label>
                    <input type="text" id="code" name="code" value="<?= e($value('code')) ?>" required maxlength="50" placeholder="<?= __('DEV-A1') ?>">
                    <?php if ($error = errors('code')): ?><span class="field-error"><?= e($error) ?></span><?php endif; ?>
                </div>

                <div class="form-group">
                    <label for="name">Nama Device <span class="faint">*</span></label>
                    <input type="text" id="name" name="name" value="<?= e($value('name')) ?>" required maxlength="150" placeholder="<?= __('Tablet lokasi Alpha 1') ?>">
                    <?php if ($error = errors('name')): ?><span class="field-error"><?= e($error) ?></span><?php endif; ?>
                </div>

                <div class="form-group">
                    <label for="type"><?= __('Tipe') ?></label>
                    <select id="type" name="type">
                        <option value="">— Pilih tipe —</option>
                        <?php foreach ($types as $type): ?>
                            <option value="<?= e($type) ?>" <?= $value('type') === $type ? 'selected' : '' ?>><?= e(ucfirst($type)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="serial_number"><?= __('Serial Number') ?></label>
                    <input type="text" id="serial_number" name="serial_number" value="<?= e($value('serial_number')) ?>" maxlength="120">
                </div>

                <div class="form-group col-span-2">
                    <label class="checkbox">
                        <input type="checkbox" name="is_active" value="1" <?= (int) $value('is_active', 1) === 1 ? 'checked' : '' ?>>
                        Device aktif
                    </label>
                </div>
            </div>

            <div class="divider"></div>

            <div class="flex gap-sm">
                <button type="submit" class="btn"><?= icon('check') ?> <?= $isEdit ? __('Simpan Perubahan') : __('Simpan Device') ?></button>
                <a class="btn btn-ghost" href="<?= url('devices') ?>">Batal</a>
            </div>
        </form>
    </div>
</div>
