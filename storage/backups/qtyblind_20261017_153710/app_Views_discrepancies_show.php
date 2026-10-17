<?php
/**
 * @var array $discrepancy
 * @var array|null $item
 */
$status = (string) $discrepancy['status'];
$statusBadge = match ($status) {
    'open' => 'badge-danger', 'searching' => 'badge-warning', 'resolved' => 'badge-success', default => '',
};
$statusLabel = match ($status) {
    'open' => 'Terbuka', 'searching' => 'Dicari', 'resolved' => 'Selesai', default => $status,
};
$counted = (int) ($item['counted_qty'] ?? $discrepancy['counted_qty']);
?>
<div class="page-head">
    <div>
        <h1>Selisih <?= e($discrepancy['item_code']) ?></h1>
        <p><?= e($discrepancy['item_name']) ?> &middot; <?= e($discrepancy['group_name']) ?></p>
    </div>
    <div class="spacer"></div>
    <a class="btn btn-ghost" href="<?= url('discrepancies') ?>"><?= icon('arrow-left') ?> Kembali</a>
</div>

<div class="grid grid-main">
    <div>
        <div class="card">
            <div class="card-header">
                <h2><?= __('Ringkasan Selisih') ?></h2>
                <div class="spacer"></div>
                <span class="badge <?= $statusBadge ?>"><?= e($statusLabel) ?></span>
            </div>
            <div class="card-body">
                <div class="grid grid-4">
                    <div><div class="stat-label"><?= __('Sistem') ?></div><div class="stat-value"><?= can_see_stock_qty() ? (int) $discrepancy['expected_qty'] : '<span class="faint">***</span>' ?></div></div>
                    <div><div class="stat-label"><?= __('Hasil hitung') ?></div><div class="stat-value"><?= (int) $discrepancy['counted_qty'] ?></div></div>
                    <div><div class="stat-label"><?= __('Hasil audit') ?></div><div class="stat-value"><?= $discrepancy['audited_qty'] === null ? '—' : (int) $discrepancy['audited_qty'] ?></div></div>
                    <div><div class="stat-label"><?= __('Selisih') ?></div><div class="stat-value" style="color:var(--danger)"><?= can_see_stock_qty() && $discrepancy['difference'] !== null ? (int) $discrepancy['difference'] : '<span class="faint">***</span>' ?></div></div>
                </div>

                <div class="divider"></div>
                <div class="text-sm">
                    <div class="mb-1"><span class="faint"><?= __('Catatan:') ?></span> <?= e($discrepancy['note'] ?: '-') ?></div>
                    <div class="mb-1"><span class="faint"><?= __('Dilaporkan:') ?></span> <?= e($discrepancy['created_by_name'] ?: '-') ?> &middot; <?= e(format_date($discrepancy['created_at'])) ?></div>
                    <?php if ($status === 'resolved'): ?>
                        <div class="mb-1"><span class="faint"><?= __('Diselesaikan oleh:') ?></span> <?= e($discrepancy['resolved_by_name'] ?: '-') ?> &middot; <?= e(format_date($discrepancy['resolved_at'])) ?></div>
                        <div><span class="faint"><?= __('Keputusan:') ?></span> <?= e($discrepancy['resolution_note'] ?: '-') ?></div>
                    <?php endif; ?>
                </div>

                <?php if ($item !== null): ?>
                    <div class="divider"></div>
                    <a class="btn btn-outline btn-sm" href="<?= url('items/' . (int) $item['id']) ?>"><?= icon('eye') ?> Lihat detail data</a>
                <?php endif; ?>
            </div>
        </div>

        <?php if (can('search_discrepancy') && $status !== 'resolved'): ?>
            <div class="card">
                <div class="card-header"><h2><?= icon('search') ?> Input Hasil Pencarian</h2></div>
                <div class="card-body">
                    <p class="text-sm muted">Cari fisik barang di lokasi, lalu perbarui jumlah temuan Anda.</p>
                    <form method="post" action="<?= url('discrepancies/' . (int) $discrepancy['id'] . '/search') ?>">
                        <?= csrf_field() ?>
                        <div class="form-group">
                            <label for="counted_qty"><?= __('Jumlah hasil pencarian terbaru') ?></label>
                            <input type="number" id="counted_qty" name="counted_qty" min="0" value="<?= $counted ?>" required>
                        </div>
                        <div class="form-group mt-1">
                            <label for="note"><?= __('Catatan pencarian') ?></label>
                            <textarea id="note" name="note" maxlength="255" placeholder="<?= __('Misal: barang ditemukan di gudang lain') ?>"></textarea>
                        </div>
                        <button type="submit" class="btn mt-1"><?= icon('check') ?> Simpan Hasil Pencarian</button>
                    </form>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <div>
        <?php if (can('manage_discrepancy') && $status !== 'resolved'): ?>
            <div class="card">
                <div class="card-header"><?= icon('send') ?> Lapor Selisih</div>
                <div class="card-body">
                    <form method="post" action="<?= url('discrepancies/' . (int) $discrepancy['id'] . '/resolve') ?>"
                          data-confirm="<?= e(__('Laporkan selisih ini sebagai selesai?')) ?>">
                        <?= csrf_field() ?>
                        <div class="form-group mb-md">
                            <label for="resolve_counted_qty" class="font-semibold text-xs uppercase tracking-wider"><?= __('Revisi Hasil Hitung (Qty Fisik Terbaru)') ?></label>
                            <input type="number" id="resolve_counted_qty" name="counted_qty" min="0" inputmode="numeric"
                                   value="<?= (int) ($discrepancy['counted_qty'] ?? 0) ?>"
                                   style="font-size:1.2rem;font-weight:700;padding:.6rem .8rem;font-family:var(--font-mono);">
                            <p class="text-xs muted mt-1"><?= __('Shortcut: ubah angka ini untuk merevisi hasil hitung fisik langsung.') ?></p>
                        </div>
                        <div class="form-group">
                            <label for="resolution_note"><?= __('Keputusan / tindak lanjut') ?></label>
                            <textarea id="resolution_note" name="resolution_note" maxlength="255" placeholder="<?= __('Misal: disesuaikan dengan hasil hitung fisik') ?>"></textarea>
                        </div>
                        <button type="submit" class="btn btn-success btn-block mt-1"><?= icon('send') ?> Lapor</button>
                    </form>
                </div>
            </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header"><h3><?= __('Alur Penanganan') ?></h3></div>
            <div class="card-body">
                <ul class="list-reset">
                    <li class="list-item">
                        <span class="li-icon"><?= icon('alert') ?></span>
                        <span class="li-body"><span class="li-title"><?= __('1. Selisih ditemukan audit / admin') ?></span>
                        <span class="li-meta"><?= __('Data otomatis terbuka (unlock)') ?></span></span>
                    </li>
                    <li class="list-item">
                        <span class="li-icon"><?= icon('search') ?></span>
                        <span class="li-body"><span class="li-title"><?= __('2. Observer mencari fisik') ?></span>
                        <span class="li-meta"><?= __('Perbarui hasil temuan') ?></span></span>
                    </li>
                    <li class="list-item">
                        <span class="li-icon"><?= icon('check') ?></span>
                        <span class="li-body"><span class="li-title"><?= __('3. Admin STO menyelesaikan') ?></span>
                        <span class="li-meta"><?= __('Data kembali terkunci') ?></span></span>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</div>
