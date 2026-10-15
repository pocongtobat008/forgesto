<?php
$messages = consume_flash();
$iconMap = ['success' => 'check', 'error' => 'x', 'warning' => 'alert', 'info' => 'info'];
foreach ($messages as $message):
    $type = in_array($message['type'] ?? 'info', ['success', 'error', 'warning', 'info'], true) ? $message['type'] : 'info';
    // Toast global: data-sf-flash dibaca sf-ui.js lalu disembunyikan (low-noise).
?>
    <div class="alert alert-<?= e($type) ?>" data-auto-dismiss role="alert"
         data-sf-flash="<?= e($type) ?>" data-sf-msg="<?= e((string) ($message['message'] ?? '')) ?>">
        <?= icon($iconMap[$type] ?? 'info') ?>
        <div><?= e($message['message'] ?? '') ?></div>
    </div>
<?php endforeach; ?>

<?php if (($allErrors = errors()) && $allErrors !== []): ?>
    <div class="alert alert-error" role="alert">
        <?= icon('alert') ?>
        <div>
            <strong><?= __('Periksa kembali data berikut:') ?></strong>
            <ul style="margin:.3rem 0 0;padding-left:1.1rem;">
                <?php foreach ($allErrors as $error): ?>
                    <li><?= e($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
<?php endif; ?>
