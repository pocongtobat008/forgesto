<?php
/**
 * View Detail Rekonsiliasi, Form Koreksi (popup modal), & Timeline Audit History.
 *
 * @var array      $recon
 * @var array|null $item
 * @var array      $timeline
 * @var bool       $canSeeStockQty
 * @var bool       $canVerify
 * @var string     $csrf
 */

use App\Core\Auth;

$statusBadge = static fn (string $s): string => match ($s) {
    'verified'       => 'badge-success',
    'pending_verify' => 'badge-warning',
    'rejected'       => 'badge-danger',
    default          => 'badge-neutral',
};

$statusLabel = static fn (string $s): string => match ($s) {
    'verified'       => 'Terverifikasi',
    'pending_verify' => 'Menunggu Verifikasi',
    'rejected'       => 'Ditolak',
    default          => 'Draft',
};

$diffAmt   = (float) $recon['diff_amount'];
$diffQty   = (int) $recon['difference'];
$direction = (string) $recon['direction'];

$myRole  = Auth::role();
$isAdmin = in_array($myRole, ['poweradmin', 'adminsto'], true);
?>

<div class="page-head">
    <div>
        <div class="flex items-center gap-xs mb-1 text-xs muted">
            <a href="<?= url('reconciliation') ?>" class="muted">&larr; Kembali ke Rekonsiliasi</a>
            <span>/</span>
            <span><?= __('Detail Cross-check') ?></span>
        </div>
        <h1><?= e($recon['item_name']) ?> <span class="kbd"><?= e($recon['item_code']) ?></span></h1>
        <p>Group: <strong><?= e($recon['group_name']) ?></strong> &bull; Lokasi: <strong><?= e($recon['location_name'] ?: 'Belum ditentukan') ?></strong></p>
    </div>
    <div class="spacer"></div>
    <div class="flex gap-xs items-center">
        <span class="badge <?= $statusBadge((string) $recon['status']) ?>" style="font-size:.85rem;padding:.4rem .8rem;">
            <?= e($statusLabel((string) $recon['status'])) ?>
        </span>
    </div>
</div>

<div class="recon-officer-strip">
    <div class="ros-item">
        <span class="ros-label"><?= icon('building') ?> Group</span>
        <span class="ros-val"><?= e($recon['group_name']) ?> <span class="kbd"><?= e($recon['group_code'] ?? '') ?></span></span>
    </div>
    <div class="ros-item">
        <span class="ros-label"><?= icon('eye') ?> Observer</span>
        <span class="ros-val"><?= $recon['group_observers'] ? e($recon['group_observers']) : '<span class="muted">— belum ditunjuk —</span>' ?></span>
    </div>
    <div class="ros-item">
        <span class="ros-label"><?= icon('audit') ?> Auditor</span>
        <span class="ros-val"><?= $recon['group_auditors'] ? e($recon['group_auditors']) : '<span class="muted">— belum ditunjuk —</span>' ?></span>
    </div>
</div>

<!-- ====================================================================== -->
<!-- 1. Side-by-Side Comparison Metric Cards                               -->
<!-- ====================================================================== -->
<div class="grid grid-4 gap-md mb-lg">
    <div class="card stat-card">
        <div class="card-body">
            <span class="text-xs uppercase muted font-bold tracking-wider"><?= __('Harga Satuan') ?></span>
            <div class="stat-val mono" style="font-size:1.35rem;font-weight:700;margin-top:.4rem;">
                <?= format_rupiah((float) $recon['unit_price']) ?>
            </div>
            <div class="text-xs muted mt-1">Per <?= e($recon['unit'] ?? 'unit') ?></div>
        </div>
    </div>

    <div class="card stat-card">
        <div class="card-body">
            <span class="text-xs uppercase muted font-bold tracking-wider"><?= __('Stok Sistem (Buku)') ?></span>
            <?php if ($canSeeStockQty): ?>
                <div class="stat-val mono" style="font-size:1.35rem;font-weight:700;margin-top:.4rem;">
                    <?= (int) $recon['stock_qty'] ?> <span class="text-xs font-normal muted"><?= e($recon['unit'] ?? 'unit') ?></span>
                </div>
                <div class="text-xs muted mt-1">Nilai: <?= format_rupiah((float) $recon['stock_amount']) ?></div>
            <?php else: ?>
                <div class="stat-val text-brand" style="font-size:1.1rem;font-weight:600;margin-top:.4rem;">
                    <span class="badge badge-brand"><?= icon('shield-lock') ?> Rahasia (Admin STO)</span>
                </div>
                <div class="text-xs muted mt-1"><?= __('Hanya Power Admin &amp; Admin STO') ?></div>
            <?php endif; ?>
        </div>
    </div>

    <div class="card stat-card">
        <div class="card-body">
            <span class="text-xs uppercase muted font-bold tracking-wider"><?= __('Hasil Hitung Fisik') ?></span>
            <div class="stat-val mono text-brand" style="font-size:1.35rem;font-weight:700;margin-top:.4rem;">
                <?= (int) $recon['counted_qty'] ?> <span class="text-xs font-normal muted"><?= e($recon['unit'] ?? 'unit') ?></span>
            </div>
            <div class="text-xs muted mt-1">Nilai Fisik: <?= format_rupiah((float) $recon['count_amount']) ?></div>
        </div>
    </div>

    <div class="card stat-card">
        <div class="card-body">
            <span class="text-xs uppercase muted font-bold tracking-wider"><?= __('Selisih &amp; Nilai Discrepancy') ?></span>
            <?php if ($canSeeStockQty): ?>
                <div class="stat-val mono font-bold <?= $diffAmt < 0 ? 'text-danger' : ($diffAmt > 0 ? 'text-success' : 'muted') ?>" style="font-size:1.35rem;margin-top:.4rem;">
                    <?= format_rupiah($diffAmt, true) ?>
                </div>
                <div class="text-xs font-bold <?= $diffQty < 0 ? 'text-danger' : ($diffQty > 0 ? 'text-success' : 'muted') ?> mt-1">
                    Selisih: <?= $diffQty > 0 ? '+' : '' ?><?= $diffQty ?> <?= e($recon['unit'] ?? 'unit') ?> (<?= $direction === 'plus' ? 'Surplus' : ($direction === 'minus' ? __('Defisit') : __('Cocok')) ?>)
                </div>
            <?php else: ?>
                <div class="stat-val" style="font-size:1.2rem;font-weight:600;margin-top:.4rem;">
                    <span class="badge <?= $direction === 'match' ? 'badge-success' : 'badge-warning' ?>">
                        <?= $direction === 'match' ? __('Sesuai') : __('Ada Selisih Fisik') ?>
                    </span>
                </div>
                <div class="text-xs muted mt-1"><?= __('Perhitungan nilai dirahasiakan') ?></div>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="grid grid-main gap-lg">
    <!-- ================================================================== -->
    <!-- 2. Panel Aksi: Koreksi (modal) + Kirim ke Petugas                  -->
    <!-- ================================================================== -->
    <div>
        <div class="card mb-lg">
            <div class="card-header flex items-center justify-between">
                <div>
                    <h2 style="font-size:1rem;font-weight:600;margin:0;"><?= icon('edit', 'h-icon') ?> Koreksi &amp; Cross-check Angka</h2>
                    <p class="text-xs muted mt-1" style="margin-bottom:0;">Perubahan angka diverifikasi dua langkah untuk mencegah kesalahan ketik / salah klik.</p>
                </div>
                <span class="badge badge-brand"><?= icon('shield') ?> Proteksi Verifikasi</span>
            </div>
            <div class="card-body">
                <div class="recon-action-grid">
                    <div class="recon-action-tile">
                        <div class="recon-action-ico"><?= icon('edit') ?></div>
                        <div class="recon-action-body">
                            <strong><?= __('Koreksi Angka') ?></strong>
                            <span class="text-xs muted">Ubah hasil hitung fisik / harga satuan dengan konfirmasi anti-salah-klik.</span>
                        </div>
                        <button type="button" class="btn btn-brand btn-sm" id="btn-open-correct" style="min-height:44px;">
                            <?= icon('edit') ?> Buka Form Koreksi
                        </button>
                    </div>

                    <div class="recon-action-tile">
                        <div class="recon-action-ico recon-action-ico-warn"><?= icon('clock') ?></div>
                        <div class="recon-action-body">
                            <strong><?= __('Minta Cek Ulang') ?></strong>
                            <span class="text-xs muted"><?= __('Tandai item ini untuk dihitung ulang oleh petugas lapangan.') ?></span>
                        </div>
                        <form method="post" action="<?= url('reconciliation/' . (int) $recon['id'] . '/recheck') ?>"
                              data-confirm="<?= e(__('Kirim item ini ke petugas untuk dicek ulang? Status akan kembali ke Draft.')) ?>"
                              style="margin:0;">
                            <?= csrf_field() ?>
                            <button type="submit" class="btn btn-outline btn-sm" style="min-height:44px;">
                                <?= icon('refresh') ?> Kirim ke Petugas
                            </button>
                        </form>
                    </div>
                </div>

                <div class="recon-hint-strip">
                    <?= icon('info') ?>
                    <span>Angka saat ini: <strong class="mono"><?= (int) $recon['counted_qty'] ?></strong> <?= e($recon['unit'] ?? 'unit') ?>
                    &bull; Harga: <strong class="mono"><?= format_rupiah((float) $recon['unit_price']) ?></strong></span>
                </div>
            </div>
        </div>

        <!-- Panel Approval Verifikasi untuk Power Admin / Admin STO -->
        <?php if ($canVerify && in_array($recon['status'], ['draft', 'pending_verify'], true)): ?>
            <div class="card mb-lg" style="border-left:4px solid var(--brand);">
                <div class="card-header">
                    <h2 style="font-size:1rem;font-weight:600;margin:0;"><?= icon('shield-check', 'h-icon') ?> Panel Otorisasi Verifikasi (Admin)</h2>
                    <p class="text-xs muted mt-1" style="margin-bottom:0;">Anda memiliki hak otorisasi untuk memverifikasi atau menolak hasil rekonsiliasi ini.</p>
                </div>
                <div class="card-body">
                    <form method="post" action="<?= url('reconciliation/' . (int) $recon['id'] . '/verify') ?>" class="flex flex-col gap-md">
                        <?= csrf_field() ?>
                        <div class="form-group" style="margin:0;">
                            <label for="verify_note" class="font-semibold text-xs"><?= __('Catatan Verifikator') ?></label>
                            <input type="text" id="verify_note" name="verify_note" placeholder="<?= __('Catatan persetujuan / verifikasi...') ?>" style="width:100%;padding:.5rem .7rem;font-size:.85rem;">
                        </div>
                        <div class="verify-actions">
                            <button type="submit" name="action" value="approve" class="btn btn-sm btn-success">
                                <?= icon('check-circle') ?> Setujui &amp; Verifikasi Rekonsiliasi
                            </button>
                            <button type="submit" name="action" value="reject" class="btn btn-sm btn-outline text-danger">
                                <?= icon('x-circle') ?> Tolak
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- ================================================================== -->
    <!-- 3. Audit Trail History Timeline (`count_history`)                  -->
    <!-- ================================================================== -->
    <div>
        <div class="card">
            <div class="card-header flex items-center justify-between">
                <div>
                    <h2 style="font-size:1rem;font-weight:600;margin:0;"><?= icon('clock', 'h-icon') ?> Riwayat Perubahan Angka</h2>
                    <p class="text-xs muted mt-1" style="margin-bottom:0;"><?= __('Log lengkap cross-check ulang dan koreksi data.') ?></p>
                </div>
                <span class="badge badge-neutral"><?= count($timeline) ?> riwayat</span>
            </div>
            <div class="card-body" style="padding:1rem;">
                <?php if ($timeline === []): ?>
                    <div class="empty-state" style="padding:1.5rem 1rem;">
                        <div class="empty-icon"><?= icon('clock') ?></div>
                        <p class="text-xs muted"><?= __('Belum ada riwayat perubahan angka untuk item ini.') ?></p>
                    </div>
                <?php else: ?>
                    <div class="timeline" style="position:relative;padding-left:1.5rem;border-left:2px solid var(--border);">
                        <?php foreach ($timeline as $h): ?>
                            <?php
                            $delta = $h['delta'] !== null ? (int) $h['delta'] : null;
                            $deltaBadge = '';
                            if ($delta !== null && $delta !== 0) {
                                $deltaBadge = $delta > 0
                                    ? '<span class="badge badge-success" style="font-size:.7rem;padding:4px 8px;">+' . $delta . '</span>'
                                    : '<span class="badge badge-danger" style="font-size:.7rem;padding:4px 8px;">' . $delta . '</span>';
                            }
                            ?>
                            <div class="timeline-item mb-md" style="position:relative;">
                                <div style="position:absolute;left:-1.95rem;top:2px;width:10px;height:10px;border-radius:50%;background:var(--brand);border:2px solid var(--surface);"></div>

                                <div class="flex items-center justify-between text-xs muted mb-1">
                                    <span class="font-bold text-main" style="color:var(--text);"><?= e($h['user_name'] ?? 'User') ?> (<?= e(role_label($h['user_role'] ?? '')) ?>)</span>
                                    <span><?= format_date($h['changed_at']) ?></span>
                                </div>

                                <div class="text-xs font-semibold mb-1 flex items-center gap-xs">
                                    <span>Field: <code><?= e($h['field_name']) ?></code></span>
                                    <span class="mono"><?= e((string)$h['old_value']) ?> &rarr; <strong class="text-brand"><?= e((string)$h['new_value']) ?></strong></span>
                                    <?= $deltaBadge ?>
                                </div>

                                <?php if (! empty($h['reason'])): ?>
                                    <div class="text-xs" style="background:var(--bg-subtle);padding:.4rem .6rem;border-radius:var(--radius-sm);border:1px solid var(--border);color:var(--text-subtle);">
                                        &ldquo;<?= e($h['reason']) ?>&rdquo;
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- ====================================================================== -->
<!-- 4. Popup Modal: Form Koreksi Angka (1 checklist + input + update)      -->
<!-- ====================================================================== -->
<div class="modal-backdrop" id="correct-modal" data-modal-close>
    <div class="modal" role="dialog" aria-modal="true" aria-labelledby="correct-modal-title" data-modal-panel>
        <div class="modal-header">
            <h3 id="correct-modal-title"><?= icon('edit') ?> Koreksi Angka Rekonsiliasi</h3>
            <div class="spacer"></div>
            <button type="button" class="modal-x" data-modal-close aria-label="<?= __('Tutup') ?>">&times;</button>
        </div>
        <form method="post" action="<?= url('reconciliation/' . (int) $recon['id']) ?>" id="recon-edit-form">
            <?= csrf_field() ?>
            <div class="modal-body">
                <div class="form-group mb-md">
                    <label for="input-counted-qty" class="font-semibold text-xs uppercase tracking-wider">
                        Hasil Hitung Fisik Baru <span class="text-danger">*</span>
                    </label>
                    <input type="number" id="input-counted-qty" name="counted_qty" min="0" step="1"
                           value="<?= (int) $recon['counted_qty'] ?>" required
                           inputmode="numeric"
                           style="font-size:1.25rem;font-weight:700;padding:.65rem .8rem;font-family:monospace;">
                    <span class="text-xs muted">Angka fisik saat ini di sistem: <strong class="mono"><?= (int) $recon['counted_qty'] ?></strong> <?= e($recon['unit'] ?? 'unit') ?></span>
                </div>

                <?php if ($canSeeStockQty): ?>
                    <div class="form-group mb-md">
                        <label for="input-unit-price" class="font-semibold text-xs uppercase tracking-wider">
                            <?= __('Harga Satuan (Rp)') ?>
                        </label>
                        <input type="number" id="input-unit-price" name="unit_price" min="0" step="1000"
                               value="<?= (float) $recon['unit_price'] ?>"
                               style="font-size:1rem;padding:.6rem .8rem;font-family:monospace;">
                        <span class="text-xs muted"><?= __('Dapat disesuaikan jika ada perubahan harga aset.') ?></span>
                    </div>
                <?php else: ?>
                    <input type="hidden" id="input-unit-price" name="unit_price" value="<?= (float) $recon['unit_price'] ?>">
                <?php endif; ?>

                <!-- Pra-pratinjau selisih -->
                <div class="recon-delta-strip" id="recon-delta-strip">
                    <span class="muted"><?= __('Pratinjau perubahan:') ?></span>
                    <strong class="mono" id="recon-delta-value">—</strong>
                </div>

                <!-- SATU checklist konfirmasi -->
                <label class="recon-check recon-check-single" for="check-confirm">
                    <input type="checkbox" id="check-confirm" class="recon-check-box">
                    <span class="recon-check-text">
                        Saya sudah <strong><?= __('menghitung fisik') ?></strong> item ini langsung dan angka yang saya masukkan <strong><?= __('sudah benar') ?></strong>.
                    </span>
                </label>

                <div class="form-group" style="margin:.75rem 0 0;">
                    <label for="input-reason" class="font-semibold text-xs uppercase tracking-wider">
                        Alasan Perubahan / Catatan <span class="text-danger">*</span>
                    </label>
                    <textarea id="input-reason" name="reason" rows="2" required
                              placeholder="Contoh: Ditemukan 2 unit tambahan di rak bawah B-04 saat hitung ulang..."
                              style="width:100%;padding:.6rem .8rem;font-size:.85rem;border-radius:var(--radius-sm);border:1px solid var(--border);"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline btn-sm" data-modal-close><?= __('Batal') ?></button>
                <button type="submit" id="btn-trigger-verify" class="btn btn-brand btn-sm" disabled>
                    <?= icon('check') ?> Update
                </button>
            </div>
        </form>
    </div>
</div>

<script>
(function() {
    var modal      = document.getElementById('correct-modal');
    var openBtn    = document.getElementById('btn-open-correct');
    var form       = document.getElementById('recon-edit-form');
    var submitBtn  = document.getElementById('btn-trigger-verify');
    var inputQty   = document.getElementById('input-counted-qty');
    var inputPrice = document.getElementById('input-unit-price');
    var inputReason= document.getElementById('input-reason');
    var deltaValue = document.getElementById('recon-delta-value');
    var checkBox   = document.getElementById('check-confirm');

    var currentQty  = <?= (int) $recon['counted_qty'] ?>;
    var currentPrice= <?= (float) $recon['unit_price'] ?>;

    function openModal() {
        if (!modal) return;
        modal.classList.add('is-open');
        document.body.style.overflow = 'hidden';
        setTimeout(function() { if (inputQty) inputQty.focus(); }, 60);
        refresh();
    }
    function closeModal() {
        if (!modal) return;
        modal.classList.remove('is-open');
        document.body.style.overflow = '';
    }

    if (openBtn) openBtn.addEventListener('click', openModal);

    // Tutup HANYA bila: klik backdrop kosong, ATAU klik tombol tutup
    // (modal-x / Batal). Klik di dalam panel (input, textarea, checkbox,
    // label) TIDAK boleh menutup modal -> dulu modal langsung hilang saat
    // user mengetik sehingga "Update tidak berfungsi".
    if (modal) {
        modal.addEventListener('click', function(e) {
            if (e.target.closest('[data-modal-panel]')) {
                // di dalam panel: hanya tombol tutup eksplisit yang menutup
                if (e.target.closest('button[data-modal-close], button[data-row-modal-close], .modal-x')) {
                    closeModal();
                }
                return;
            }
            if (e.target === modal || e.target.closest('[data-modal-close]')) { closeModal(); }
        });
    }
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && modal && modal.classList.contains('is-open')) closeModal();
    });

    function rupiah(n) { return 'Rp ' + Number(n || 0).toLocaleString('id-ID'); }

    function refresh() {
        if (!inputQty) return;
        var newQty = parseInt(inputQty.value, 10);
        if (isNaN(newQty)) newQty = 0;
        var delta = newQty - currentQty;
        var deltaStr = delta > 0 ? '(+' + delta + ')' : (delta < 0 ? '(' + delta + ')' : '(sama)');
        if (deltaValue) {
            deltaValue.textContent = currentQty + ' \u2192 ' + newQty + ' ' + deltaStr +
                '  \u00b7  Rp ' + Number(newQty * (parseFloat(inputPrice && inputPrice.value) || 0)).toLocaleString('id-ID');
        }

        var qtyOk    = !isNaN(newQty) && newQty >= 0;
        var reasonOk = inputReason && inputReason.value.trim().length >= 3;
        var ticked   = checkBox && checkBox.checked;
        var priceOk  = inputPrice ? (parseFloat(inputPrice.value) >= 0) : true;

        if (submitBtn) submitBtn.disabled = !(qtyOk && ticked && reasonOk && priceOk);

        if (checkBox) checkBox.closest('.recon-check').classList.toggle('is-on', checkBox.checked);
    }

    if (inputQty)    inputQty.addEventListener('input', refresh);
    if (inputPrice)  inputPrice.addEventListener('input', refresh);
    if (inputReason) inputReason.addEventListener('input', refresh);
    if (checkBox)    checkBox.addEventListener('change', refresh);

    if (form) {
        form.addEventListener('submit', function(e) {
            var newQty = parseInt(inputQty ? inputQty.value : '', 10);
            if (isNaN(newQty) || newQty < 0) { e.preventDefault(); if (inputQty) inputQty.focus(); return; }
            if (!inputReason || inputReason.value.trim().length < 3) { e.preventDefault(); if (inputReason) inputReason.focus(); return; }
            if (!checkBox || !checkBox.checked) { e.preventDefault(); return; }
            if (submitBtn) { submitBtn.disabled = true; submitBtn.textContent = 'Menyimpan...'; }
        });
    }

    refresh();
})();
</script>
