<?php
/**
 * @var array|null $location
 * @var array $groups
 */
$isEdit = $location !== null;
$action = $isEdit ? url('locations/' . (int) $location['id']) : url('locations');

$value = static function (string $key, mixed $default = '') use ($location) {
    $fromOld = old($key, null);
    if ($fromOld !== null) {
        return $fromOld;
    }

    return $location[$key] ?? $default;
};
?>
<div class="page-head">
    <div>
        <h1><?= $isEdit ? __('Edit Lokasi') : __('Tambah Lokasi') ?></h1>
        <p><?= __('Lokasi dimiliki oleh satu group.') ?></p>
    </div>
</div>

<div class="card" style="max-width:760px;">
    <div class="card-header"><h2><?= __('Informasi Lokasi') ?></h2></div>
    <div class="card-body">
        <form method="post" action="<?= $action ?>">
            <?= csrf_field() ?>

            <div class="form-grid">
                <div class="form-group col-span-2">
                    <label for="group_id">Group <span class="faint">*</span></label>
                    <select id="group_id" name="group_id" required>
                        <option value="">— Pilih Group —</option>
                        <?php foreach ($groups as $group): ?>
                            <option value="<?= (int) $group['id'] ?>" <?= (int) $value('group_id', 0) === (int) $group['id'] ? 'selected' : '' ?>>
                                <?= e($group['name']) ?> (<?= e($group['code']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if ($error = errors('group_id')): ?><span class="field-error"><?= e($error) ?></span><?php endif; ?>
                </div>

                <div class="form-group">
                    <label for="code">Kode Lokasi <span class="faint">*</span></label>
                    <input type="text" id="code" name="code" value="<?= e($value('code')) ?>" required maxlength="50" placeholder="<?= __('LOC-A1') ?>">
                    <?php if ($error = errors('code')): ?><span class="field-error"><?= e($error) ?></span><?php endif; ?>
                </div>

                <div class="form-group">
                    <label for="name">Nama Lokasi <span class="faint">*</span></label>
                    <input type="text" id="name" name="name" value="<?= e($value('name')) ?>" required maxlength="150" placeholder="<?= __('Gudang Alpha 1') ?>">
                    <?php if ($error = errors('name')): ?><span class="field-error"><?= e($error) ?></span><?php endif; ?>
                </div>

                <div class="form-group col-span-2">
                    <label for="address"><?= __('Alamat') ?></label>
                    <textarea id="address" name="address" maxlength="255"><?= e($value('address')) ?></textarea>
                </div>

                <div class="form-group col-span-2">
                    <label class="checkbox">
                        <input type="checkbox" name="is_active" value="1" <?= (int) $value('is_active', 1) === 1 ? 'checked' : '' ?>>
                        Lokasi aktif
                    </label>
                </div>
            </div>

            <div class="divider"></div>

            <div class="flex gap-sm">
                <button type="submit" class="btn"><?= icon('check') ?> <?= $isEdit ? __('Simpan Perubahan') : __('Simpan Lokasi') ?></button>
                <a class="btn btn-ghost" href="<?= url('locations') ?>">Batal</a>
            </div>
        </form>
    </div>
</div>
