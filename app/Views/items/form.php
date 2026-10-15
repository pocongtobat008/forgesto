<?php
/**
 * @var array|null $item
 * @var array $groups
 * @var array $locations
 * @var array $devices
 */
$isEdit = $item !== null;
$action = $isEdit ? url('items/' . (int) $item['id']) : url('items');

$value = static function (string $key, mixed $default = '') use ($item) {
    $fromOld = old($key, null);
    if ($fromOld !== null) {
        return $fromOld;
    }

    return $item[$key] ?? $default;
};
?>
<div class="page-head">
    <div>
        <h1><?= $isEdit ? __('Edit Data') : __('Tambah Data') ?></h1>
        <p><?= __('Data master yang nantinya dihitung oleh observer.') ?></p>
    </div>
</div>

<div class="card" style="max-width:860px;">
    <div class="card-header"><h2><?= __('Informasi Data') ?></h2></div>
    <div class="card-body">
        <form method="post" action="<?= $action ?>">
            <?= csrf_field() ?>

            <div class="form-grid">
                <div class="form-group">
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
                    <label for="code">Kode <span class="faint">*</span></label>
                    <input type="text" id="code" name="code" value="<?= e($value('code')) ?>" required maxlength="80" placeholder="<?= __('A-013') ?>">
                    <?php if ($error = errors('code')): ?><span class="field-error"><?= e($error) ?></span><?php endif; ?>
                </div>

                <div class="form-group col-span-2">
                    <label for="name">Nama <span class="faint">*</span></label>
                    <input type="text" id="name" name="name" value="<?= e($value('name')) ?>" required maxlength="200" placeholder="<?= __('Kabel HDMI 2 meter') ?>">
                    <?php if ($error = errors('name')): ?><span class="field-error"><?= e($error) ?></span><?php endif; ?>
                </div>

                <div class="form-group">
                    <label for="category"><?= __('Kategori') ?></label>
                    <input type="text" id="category" name="category" value="<?= e($value('category')) ?>" maxlength="100" placeholder="<?= __('Elektronik') ?>">
                </div>

                <div class="form-group">
                    <label for="unit"><?= __('Satuan') ?></label>
                    <input type="text" id="unit" name="unit" value="<?= e($value('unit')) ?>" maxlength="40" placeholder="<?= __('unit') ?>">
                </div>

                <div class="form-group">
                    <label for="expected_qty">Qty Sistem <span class="faint">*</span></label>
                    <input type="number" id="expected_qty" name="expected_qty" value="<?= e($value('expected_qty', 0)) ?>" min="0" required>
                    <?php if ($error = errors('expected_qty')): ?><span class="field-error"><?= e($error) ?></span><?php endif; ?>
                </div>

                <div class="form-group">
                    <label for="location_id"><?= __('Lokasi') ?></label>
                    <select id="location_id" name="location_id">
                        <option value="">— Tanpa lokasi —</option>
                        <?php foreach ($locations as $location): ?>
                            <option value="<?= (int) $location['id'] ?>" <?= (int) $value('location_id', 0) === (int) $location['id'] ? 'selected' : '' ?>>
                                <?= e($location['group_name']) ?> &middot; <?= e($location['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <?php if ($isEdit && $devices !== []): ?>
                    <div class="form-group col-span-2">
                        <label for="device_id"><?= __('Device (tablet lokasi)') ?></label>
                        <select id="device_id" name="device_id">
                            <option value="">— Tanpa device —</option>
                            <?php foreach ($devices as $device): ?>
                                <option value="<?= (int) $device['id'] ?>" <?= (int) $value('device_id', 0) === (int) $device['id'] ? 'selected' : '' ?>>
                                    <?= e($device['name']) ?> (<?= e($device['code']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endif; ?>
            </div>

            <div class="divider"></div>

            <div class="flex gap-sm">
                <button type="submit" class="btn"><?= icon('check') ?> <?= $isEdit ? __('Simpan Perubahan') : __('Simpan Data') ?></button>
                <a class="btn btn-ghost" href="<?= $isEdit ? url('items/' . (int) $item['id']) : url('items') ?>">Batal</a>
            </div>
        </form>
    </div>
</div>
