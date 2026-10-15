<?php
/**
 * Panel reset hitungan periode berjalan. Disisipkan sebelum "Riwayat periode".
 * @var array $active
 * @var bool  $canAdmin
 * @var ?array $resetPreview  (dari BookYearController::index)
 */
$resetPreview = $resetPreview ?? null;
$showReset = $canAdmin && $active !== null && ! $isClosed && is_array($resetPreview);
?>
<?php if ($showReset): ?>
<?php $rp = $resetPreview; $cmp = $rp['compare']; $code = (string) $active['audit_month']; ?>
<section class="card" aria-labelledby="book-reset-title" style="margin-bottom:1rem;border-color:<?= $rp['significant'] ? 'var(--warning-text)' : 'var(--border)' ?>;">
    <div class="card-header">
        <div>
            <h2 id="book-reset-title"><?= icon('alert') ?> Reset hitungan periode berjalan</h2>
            <p class="muted text-sm" style="margin:.2rem 0 0;">Hanya menghapus data hitung &amp; audit periode ini. Master item tidak diubah.</p>
        </div>
        <div class="spacer"></div>
        <?php if ($rp['significant']): ?>
            <span class="badge badge-warning"><?= __('Perubahan signifikan') ?></span>
        <?php else: ?>
            <span class="badge badge-success"><?= __('Tidak signifikan') ?></span>
        <?php endif; ?>
    </div>
    <div class="by-body">

    <?php if ($rp['nothing_to_reset']): ?>
        <p class="muted text-sm"><?= __('Tidak ada data hitung/audit untuk direset.') ?></p>
    <?php else: ?>
        <div class="grid grid-4" style="margin:.6rem 0;">
            <div class="stat"><span class="stat-label"><?= __('Item akan dikembalikan') ?></span><span class="stat-value"><?= (int) $rp['items_touched'] ?></span></div>
            <div class="stat"><span class="stat-label"><?= __('Baris audit') ?></span><span class="stat-value"><?= (int) $rp['counts']['audits'] ?></span></div>
            <div class="stat"><span class="stat-label"><?= __('Riwayat hitung') ?></span><span class="stat-value"><?= (int) $rp['counts']['count_history'] ?></span></div>
            <div class="stat"><span class="stat-label"><?= __('Nilai hitungan') ?></span><span class="stat-value" style="font-size:1rem;">Rp <?= number_format((float) $rp['counted_value'], 0, ',', '.') ?></span></div>
        </div>

        <h3 style="font-size:.95rem;margin:.8rem 0 .3rem;">Dibanding close buku terakhir<?= $cmp['has_last_closed'] ? ' (' . e($labelBulan((string) $cmp['last_closed_month'])) . ')' : '' ?></h3>
        <?php if (! $cmp['has_last_closed']): ?>
            <p class="muted text-sm"><?= __('Belum ada close buku sebelumnya untuk pembanding.') ?></p>
        <?php else: ?>
            <ul class="muted text-sm" style="margin:0 0 .6rem 1rem;">
                <li>Item pindah group: <strong><?= (int) $cmp['moved_group'] ?></strong></li>
                <li>Item qty master berubah: <strong><?= (int) $cmp['qty_changed'] ?></strong></li>
                <li>Item hitungan berubah: <strong><?= (int) $cmp['value_delta_items'] ?></strong> (<?= e((string) ($rp['total_items'] > 0 ? round($cmp['value_delta_items'] / $rp['total_items'] * 100, 1) : 0)) ?>%)</li>
                <li>Selisih nilai: <strong>Rp <?= number_format((float) $cmp['value_delta'], 0, ',', '.') ?></strong></li>
            </ul>
        <?php endif; ?>

        <?php if ($rp['significant']): ?>
            <p class="text-sm" style="color:var(--warning-text);margin:.4rem 0;"><strong><?= __('Alasan signifikan:') ?></strong> <?= e(implode('; ', $rp['reasons'])) ?></p>
        <?php endif; ?>

        <details style="margin-top:.8rem;">
            <summary class="btn btn-outline btn-sm" style="min-height:44px;cursor:pointer;">Lanjut reset…</summary>
            <form method="post" action="<?= url('tahun-buku/reset') ?>" class="mt-2"
                  onsubmit="return confirm('Hapus hitungan periode <?= e($code) ?>? Cadangan dibuat otomatis. Tindakan ini tidak bisa dibatalkan dari menu.');">
                <?= csrf_field() ?>
                <div class="form-group">
                    <label for="reset_confirm">Ketik kode periode <strong><?= e($code) ?></strong> untuk konfirmasi</label>
                    <input type="text" id="reset_confirm" name="confirm_code" autocomplete="off" required
                           pattern="<?= e($code) ?>" placeholder="<?= e($code) ?>" style="min-height:44px;">
                </div>
                <button type="submit" class="btn btn-danger" style="min-height:44px;"><?= icon('trash') ?> Reset hitungan periode ini</button>
                <p class="muted text-sm" style="margin:.4rem 0 0;">Cadangan JSON disimpan di storage/backups sebelum data dihapus.</p>
            </form>
        </details>
    <?php endif; ?>
</div>
</section>
<?php endif; ?>
