<?php
/**
 * Halaman konfirmasi hapus periode (3 langkah).
 * @var array $p  periode
 * @var array $pv hasil preview
 * @var string $ver token CSRF dihasilkan oleh csrf_field()
 */
$p = $p ?? [];
$pv = $pv ?? ['snapshots' => 0, 'groups' => 0, 'diff_value' => 0];
$code = (string) ($p['audit_month'] ?? '');
?>
<div class="page-head"><div><h1>Hapus periode <?= e($code) ?></h1><p>Verifikasi 3 langkah. Penghapusan bersifat permanen dan dicadangkan sebelum dieksekusi.</p></div></div>

<section class="card" style="margin-bottom:1rem;">
    <h2 style="font-size:1rem;">Langkah 1 · Pratinjau</h2>
    <div class="grid grid-3" style="margin:.6rem 0;">
        <div class="stat"><span class="stat-label"><?= __('Periode') ?></span><span class="stat-value"><?= e($code) ?></span></div>
        <div class="stat"><span class="stat-label"><?= __('Snapshot akan dihapus') ?></span><span class="stat-value"><?= (int) $pv['snapshots'] ?></span></div>
        <div class="stat"><span class="stat-label"><?= __('Group terdampak') ?></span><span class="stat-value"><?= (int) $pv['groups'] ?></span></div>
    </div>
    <p class="muted text-sm">Catatan: <?= e((string) ($p['note'] ?? '')) ?></p>
</section>

<section class="card" style="margin-bottom:1rem;">
    <h2 style="font-size:1rem;">Langkah 2 · Cadangan otomatis</h2>
    <p class="muted text-sm">Snapshot dan data periode disimpan sebagai JSON di <code>storage/backups</code> sebelum penghapusan. Jika cadangan gagal, penghapusan dibatalkan.</p>
</section>

<section class="card">
    <h2 style="font-size:1rem;">Langkah 3 · Ketik kode periode</h2>
    <form method="post" action="<?= url('tahun-buku/hapus/' . (int) ($p['id'] ?? 0)) ?>" class="mt-2">
        <?= csrf_field() ?>
        <div class="form-group">
            <label for="del_confirm">Ketik <strong><?= e($code) ?></strong> persis untuk konfirmasi</label>
            <input type="text" id="del_confirm" name="confirm_code" autocomplete="off" required pattern="<?= e($code) ?>" placeholder="<?= e($code) ?>" style="min-height:44px;">
        </div>
        <div class="flex gap wrap" style="margin-top:.6rem;">
            <a class="btn btn-outline" style="min-height:44px;" href="<?= url('tahun-buku') ?>">Batal</a>
            <button type="submit" class="btn btn-danger" style="min-height:44px;"><?= icon('trash') ?> Hapus periode ini</button>
        </div>
    </form>
</section>
