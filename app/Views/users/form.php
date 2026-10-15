<?php
/**
 * @var array|null $user
 * @var array $groups
 * @var array $roles
 * @var array $roleDescriptions
 * @var array $selectedGroups
 */
$isEdit = $user !== null;
$action = $isEdit ? url('users/' . (int) $user['id']) : url('users');

$value = static function (string $key, mixed $default = '') use ($user) {
    $fromOld = old($key, null);
    if ($fromOld !== null) {
        return $fromOld;
    }

    return $user[$key] ?? $default;
};

// Preserve group delegation selections after a validation error.
$oldGroups = old('group_ids', null);
$selected = array_map('intval', (array) ($oldGroups ?? $selectedGroups));
?>
<div class="page-head">
    <div>
        <h1><?= $isEdit ? __('Edit User') : __('Tambah User') ?></h1>
        <p><?= __('Tentukan role akses dan delegasi group untuk user ini.') ?></p>
    </div>
</div>

<form method="post" action="<?= $action ?>">
    <?= csrf_field() ?>

    <div class="grid grid-main">
        <div class="card">
            <div class="card-header"><h2><?= __('Informasi User') ?></h2></div>
            <div class="card-body">
                <div class="form-grid">
                    <div class="form-group col-span-2">
                        <label for="name">Nama Lengkap <span class="faint">*</span></label>
                        <input type="text" id="name" name="name" value="<?= e($value('name')) ?>" required maxlength="150">
                        <?php if ($error = errors('name')): ?><span class="field-error"><?= e($error) ?></span><?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label for="username">Username <span class="faint">*</span></label>
                        <input type="text" id="username" name="username" value="<?= e($value('username')) ?>" required maxlength="80">
                        <?php if ($error = errors('username')): ?><span class="field-error"><?= e($error) ?></span><?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label for="email"><?= __('Email') ?></label>
                        <input type="email" id="email" name="email" value="<?= e($value('email')) ?>" maxlength="150">
                        <?php if ($error = errors('email')): ?><span class="field-error"><?= e($error) ?></span><?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label for="phone"><?= __('No. HP') ?></label>
                        <input type="text" id="phone" name="phone" value="<?= e($value('phone')) ?>" maxlength="30">
                    </div>

                    <div class="form-group">
                        <label for="password"><?= __('Password') ?></label>
                        <input type="password" id="password" name="password" autocomplete="new-password" placeholder="<?= $isEdit ? 'Kosongkan bila tidak diubah' : 'Kosongkan dulu (login dimatikan)' ?>">
                        <span class="hint">Form login belum diaktifkan (fase testing), password boleh dikosongkan.</span>
                    </div>

                    <div class="form-group col-span-2">
                        <label><?= __('Status') ?></label>
                        <label class="checkbox">
                            <input type="checkbox" name="is_active" value="1" <?= (int) $value('is_active', 1) === 1 ? 'checked' : '' ?>>
                            User aktif
                        </label>
                    </div>
                </div>
            </div>
        </div>

        <div>
            <div class="card">
                <div class="card-header"><h2><?= __('Role Akses') ?></h2></div>
                <div class="card-body">
                    <div class="form-group">
                        <select name="role" id="role">
                            <?php foreach ($roles as $key => $label): ?>
                                <option value="<?= e($key) ?>" <?= $value('role', 'observer') === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php if ($error = errors('role')): ?><span class="field-error"><?= e($error) ?></span><?php endif; ?>
                    </div>

                    <div class="divider"></div>
                    <ul class="list-reset text-sm">
                        <?php foreach ($roleDescriptions as $key => $description): ?>
                            <li class="list-item" style="padding:.45rem 0;">
                                <span class="li-body">
                                    <span class="li-title"><?= e($roles[$key] ?? $key) ?></span>
                                    <span class="li-meta"><?= e($description) ?></span>
                                </span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><h2><?= __('Delegasi Group') ?></h2></div>
                <div class="card-body">
                    <p class="text-sm muted">Power Admin &amp; Admin STO otomatis melihat semua group. Untuk Auditor &amp; Observer, pilih group yang didelegasikan.</p>
                    <?php if ($groups === []): ?>
                        <p class="faint text-sm mb-0"><?= __('Belum ada group.') ?></p>
                    <?php else: ?>
                        <div class="chip-group">
                            <?php foreach ($groups as $group): ?>
                                <label class="chip">
                                    <input type="checkbox" name="group_ids[]" value="<?= (int) $group['id'] ?>"
                                        <?= in_array((int) $group['id'], $selected, true) ? 'checked' : '' ?>>
                                    <?= e($group['code']) ?> &middot; <?= e($group['name']) ?>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="flex gap-sm mt-1">
        <button type="submit" class="btn"><?= icon('check') ?> <?= $isEdit ? __('Simpan Perubahan') : __('Simpan User') ?></button>
        <a class="btn btn-ghost" href="<?= url('users') ?>">Batal</a>
    </div>
</form>
