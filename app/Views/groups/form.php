<?php
/**
 * @var array|null $group
 * @var array $stos
 * @var array $warehouses
 */
$isEdit = $group !== null;
$action = $isEdit ? url('groups/' . (int) $group['id']) : url('groups');

$value = static function (string $key, mixed $default = '') use ($group) {
    $fromOld = old($key, null);
    if ($fromOld !== null) {
        return $fromOld;
    }

    return $group[$key] ?? $default;
};
?>
<div class="page-head">
    <div>
        <h1><?= $isEdit ? __('Edit Group') : __('Tambah Group') ?></h1>
        <p>Group adalah unit kerja yang menjadi batas akses data, terikat pada satu Gudang/Situs.</p>
    </div>
    <div class="spacer"></div>
    <a class="btn btn-ghost" href="<?= url('groups') ?>"><?= icon('arrow-left') ?> Kembali</a>
</div>

<form method="post" action="<?= $action ?>" class="wh-form">
    <?= csrf_field() ?>

    <div class="card">
        <div class="card-header">
            <h2><?= icon('layers') ?> Identitas Group</h2>
            <div class="spacer"></div>
            <span class="wh-form-step">1</span>
        </div>
        <div class="card-body">
            <div class="form-grid">
                <div class="form-group">
                    <label for="code">Kode Group <span class="faint">*</span></label>
                    <input type="text" id="code" name="code" value="<?= e($value('code')) ?>" required maxlength="50" placeholder="<?= __('GRP-A') ?>" autocomplete="off">
                    <span class="field-hint"><?= __('Singkatan unik group, biasanya diawali GRP-.') ?></span>
                    <?php if ($error = errors('code')): ?><span class="field-error"><?= e($error) ?></span><?php endif; ?>
                </div>

                <div class="form-group">
                    <label for="name">Nama Group <span class="faint">*</span></label>
                    <input type="text" id="name" name="name" value="<?= e($value('name')) ?>" required maxlength="150" placeholder="<?= __('Gudang Mesin &amp; Engine') ?>" autocomplete="off">
                    <?php if ($error = errors('name')): ?><span class="field-error"><?= e($error) ?></span><?php endif; ?>
                </div>

                <div class="form-group form-span-2">
                    <label for="description"><?= __('Deskripsi') ?></label>
                    <textarea id="description" name="description" rows="2" maxlength="255" placeholder="<?= __('Keterangan singkat group') ?>"><?= e($value('description')) ?></textarea>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h2><?= icon('building') ?> Penempatan &amp; Akses</h2>
            <div class="spacer"></div>
            <span class="wh-form-step">2</span>
        </div>
        <div class="card-body">
            <div class="form-grid">
                <div class="form-group">
                    <label for="warehouse_id">Gudang / Situs <span class="faint">*</span></label>
                    <select id="warehouse_id" name="warehouse_id" required>
                        <option value="">— Pilih Gudang —</option>
                        <?php foreach (($warehouses ?? []) as $wh): ?>
                            <option value="<?= (int) $wh['id'] ?>" <?= (int) $value('warehouse_id', 0) === (int) $wh['id'] ? 'selected' : '' ?>>
                                <?= e($wh['name']) ?> (<?= e($wh['code']) ?>)<?= ! empty($wh['city']) ? ' — ' . e($wh['city']) : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <span class="field-hint"><?= __('Gudang induk: pilih kota/plant tempat group ini berada.') ?></span>
                    <?php if ($error = errors('warehouse_id')): ?><span class="field-error"><?= e($error) ?></span><?php endif; ?>
                </div>

                <div class="form-group">
                    <label for="sto_id"><?= __('STO') ?></label>
                    <select id="sto_id" name="sto_id">
                        <option value="">— Tanpa STO —</option>
                        <?php foreach ($stos as $sto): ?>
                            <option value="<?= (int) $sto['id'] ?>" <?= (int) $value('sto_id', 0) === (int) $sto['id'] ? 'selected' : '' ?>>
                                <?= e($sto['name']) ?> (<?= e($sto['code']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if ($error = errors('sto_id')): ?><span class="field-error"><?= e($error) ?></span><?php endif; ?>
                </div>

                <div class="form-group form-span-2">
                    <label><?= __('Status Group') ?></label>
                    <label class="switch-field">
                        <input type="checkbox" name="is_active" value="1" <?= (int) $value('is_active', 1) === 1 ? 'checked' : '' ?>>
                        <span class="switch-track" aria-hidden="true"><span class="switch-knob"></span></span>
                        <span class="switch-text">
                            <strong><?= __('Group aktif') ?></strong>
                            <small>Nonaktifkan bila group ini tidak lagi dipakai untuk stock take.</small>
                        </span>
                    </label>
                </div>
            </div>
        </div>
        <div class="card-footer wh-form-footer">
            <a class="btn btn-ghost" href="<?= url('groups') ?>">Batal</a>
            <button type="submit" class="btn btn-brand"><?= icon('check') ?> <?= $isEdit ? __('Simpan Perubahan') : __('Simpan Group') ?></button>
        </div>
    </div>
</form>