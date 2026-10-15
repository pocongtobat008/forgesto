<?php
/**
 * Halaman login - ID Karyawan + Password.
 *
 * @var string $title
 * @var array  $errors
 * @var array  $old
 */

$errors  = $errors ?? [];
$old     = $old ?? [];
$errVal  = static fn (string $k): string => (string) ($errors[$k] ?? '');
$oldVal  = static fn (string $k): string => (string) ($old[$k] ?? '');
?>
<div class="sf-auth-wrap">
    <div class="sf-auth-card">

        <div class="sf-auth-brand">
            <div class="sf-auth-logo"><?= icon('box') ?></div>
            <h1 class="sf-auth-title"><?= e(config('app.name')) ?></h1>
            <p class="sf-auth-sub"><?= e(__('Masuk untuk melanjutkan')) ?></p>
        </div>

        <?php foreach (consume_flash() as $flash): ?>
            <div class="sf-auth-alert sf-auth-alert--<?= e((string) $flash['type']) ?>"><?= e((string) $flash['message']) ?></div>
        <?php endforeach; ?>

        <form method="post" action="<?= url('/login') ?>" class="sf-auth-form" novalidate>
            <?= csrf_field() ?>

            <div class="sf-auth-field">
                <label for="employee_id"><?= e(__('ID Karyawan')) ?></label>
                <div class="sf-auth-input">
                    <span class="sf-auth-input-icon"><?= icon('user') ?></span>
                    <input
                        type="text"
                        id="employee_id"
                        name="employee_id"
                        value="<?= e($oldVal('employee_id')) ?>"
                        placeholder="WE0001"
                        autocomplete="username"
                        inputmode="text"
                        autocapitalize="characters"
                        spellcheck="false"
                        autofocus
                        required>
                </div>
                <?php if ($errVal('employee_id') !== ''): ?>
                    <span class="sf-auth-error"><?= e($errVal('employee_id')) ?></span>
                <?php endif; ?>
            </div>

            <div class="sf-auth-field">
                <label for="password"><?= e(__('Password')) ?></label>
                <div class="sf-auth-input">
                    <span class="sf-auth-input-icon"><?= icon('lock') ?></span>
                    <input
                        type="password"
                        id="password"
                        name="password"
                        placeholder="<?= e(__('Masukkan password')) ?>"
                        autocomplete="current-password"
                        required>
                    <button
                        type="button"
                        class="sf-auth-toggle"
                        id="togglePassword"
                        aria-label="<?= e(__('Tampilkan password')) ?>"
                        tabindex="-1">
                        <?= icon('eye') ?>
                    </button>
                </div>
                <?php if ($errVal('password') !== ''): ?>
                    <span class="sf-auth-error"><?= e($errVal('password')) ?></span>
                <?php endif; ?>
            </div>

            <button type="submit" class="sf-auth-submit">
                <?= icon('send') ?>
                <span><?= e(__('Masuk')) ?></span>
            </button>

            <p class="sf-auth-hint">
                <?= e(__('Gunakan ID Karyawan yang diberikan administrator.')) ?>
            </p>
        </form>

    </div>
</div>

<script>
(function () {
    var btn = document.getElementById('togglePassword');
    var inp = document.getElementById('password');
    if (!btn || !inp) return;
    btn.addEventListener('click', function () {
        var show = inp.type === 'password';
        inp.type = show ? 'text' : 'password';
        btn.setAttribute('aria-label', show ? <?= json_encode(__('Sembunyikan password')) ?> : <?= json_encode(__('Tampilkan password')) ?>);
        inp.focus();
    });
})();
</script>
