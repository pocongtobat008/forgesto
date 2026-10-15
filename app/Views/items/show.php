<?php
/**
 * @var array $item
 * @var array $audits
 * @var array|null $discrepancy
 */
$isLocked = (int) $item['is_locked'] === 1;
$expected = (int) $item['expected_qty'];
$counted = $item['counted_qty'] === null ? null : (int) $item['counted_qty'];
$difference = $counted === null ? null : $counted - $expected;
$diffClass = $difference === null || $difference === 0 ? 'is-zero' : ($difference > 0 ? 'is-pos' : 'is-neg');

$countBadge = match ((string) $item['count_status']) {
    'pending' => 'badge-danger', 'counted' => 'badge-warning', 'posted' => 'badge-info', default => '',
};
$countLabel = match ((string) $item['count_status']) {
    'pending' => 'Belum dihitung', 'counted' => 'Sudah dihitung', 'posted' => 'Terposting', default => $item['count_status'],
};
$auditBadge = match ((string) $item['audit_status']) {
    'pending' => '', 'audited' => 'badge-success', 'discrepancy' => 'badge-danger', 'resolved' => 'badge-brand', default => '',
};
$auditLabel = match ((string) $item['audit_status']) {
    'pending' => 'Belum diaudit', 'audited' => 'Sudah diaudit', 'discrepancy' => 'Selisih', 'resolved' => 'Selesai', default => $item['audit_status'],
};
?>
<div class="page-head">
    <div>
        <h1><span class="kbd"><?= e($item['code']) ?></span> <?= e($item['name']) ?></h1>
        <p><?= e($item['group_name']) ?> &middot; <?= e($item['location_name'] ?: 'Tanpa lokasi') ?></p>
    </div>
    <div class="spacer"></div>
    <div class="btn-group">
        <a class="btn btn-ghost" href="<?= url('items') ?>"><?= icon('arrow-left') ?> Kembali</a>
        <?php if (can('manage_data') && can_action('items', 'edit')): ?>
            <a class="btn btn-outline" href="<?= url('items/' . (int) $item['id'] . '/edit') ?>"><?= icon('edit') ?> Edit</a>
        <?php endif; ?>
        <?php if (can('manage_data') && can_action('items', 'delete') && ! $isLocked): ?>
            <form method="post" action="<?= url('items/' . (int) $item['id'] . '/delete') ?>" data-confirm="<?= e(__('Hapus data ini?')) ?>">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-danger"><?= icon('trash') ?> Hapus</button>
            </form>
        <?php endif; ?>
    </div>
</div>

<div class="grid grid-main">
    <div>
        <div class="card">
            <div class="card-header">
                <h2><?= icon('box') ?> Informasi Data</h2>
                <div class="spacer"></div>
                <span class="badge <?= $countBadge ?>"><?= e($countLabel) ?></span>
                <span class="badge <?= $auditBadge ?>"><?= e($auditLabel) ?></span>
                <?php if ($isLocked): ?><span class="badge badge-danger"><?= icon('shield-lock') ?> Terkunci</span><?php endif; ?>
            </div>
            <div class="card-body">
                <div class="compare">
                    <div class="compare-card">
                        <div class="cc-label"><?= __('Qty sistem') ?></div>
                        <div class="cc-value"><?= can_see_stock_qty() ? $expected : '<span class="badge badge-brand">***</span>' ?></div>
                    </div>
                    <div class="compare-card">
                        <div class="cc-label"><?= __('Hasil hitung') ?></div>
                        <div class="cc-value"><?= $counted === null ? '—' : $counted ?></div>
                    </div>
                    <div class="compare-card">
                        <div class="cc-label"><?= __('Selisih') ?></div>
                        <div class="cc-value">
                            <?= can_see_stock_qty() ? ($difference === null ? '—' : (($difference > 0 ? '+' : '') . $difference)) : '<span class="badge badge-brand">***</span>' ?>
                        </div>
                        <?php if (can_see_stock_qty() && $difference !== null): ?>
                            <div class="mt-1"><span class="diff-pill <?= $diffClass ?>"><?= $difference === 0 ? 'cocok' : ($difference > 0 ? 'lebih' : 'kurang') ?></span></div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="divider"></div>

                <div class="grid grid-2 text-sm">
                    <div><span class="faint"><?= __('Kategori:') ?></span> <?= e($item['category'] ?: '-') ?></div>
                    <div><span class="faint"><?= __('Satuan:') ?></span> <?= e($item['unit'] ?: '-') ?></div>
                    <div><span class="faint"><?= __('Device:') ?></span> <?= e($item['device_name'] ?: '-') ?></div>
                    <div><span class="faint"><?= __('Dihitung oleh:') ?></span> <?= e($item['counted_by_name'] ?: '-') ?></div>
                    <div><span class="faint"><?= __('Waktu hitung:') ?></span> <?= e(format_date($item['counted_at'])) ?></div>
                    <div><span class="faint"><?= __('Diposting oleh:') ?></span> <?= e($item['posted_by_name'] ?: '-') ?></div>
                    <div><span class="faint"><?= __('Waktu posting:') ?></span> <?= e(format_date($item['posted_at'])) ?></div>
                </div>

                <?php if (! empty($item['condition_note'])): ?>
                    <div class="divider"></div>
                    <div class="text-sm"><span class="faint"><?= __('Catatan kondisi:') ?></span><br><?= e($item['condition_note']) ?></div>
                <?php endif; ?>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h2><?= icon('clock') ?> Riwayat Audit</h2></div>
            <div class="card-body">
                <?php if ($audits === []): ?>
                    <p class="faint text-sm mb-0"><?= __('Belum ada riwayat audit untuk data ini.') ?></p>
                <?php else: ?>
                    <ul class="list-reset">
                        <?php foreach ($audits as $audit): ?>
                            <li class="list-item">
                                <span class="li-icon" style="color: <?= $audit['result'] === 'match' ? 'var(--success-text)' : 'var(--danger-text)' ?>;">
                                    <?= icon($audit['result'] === 'match' ? 'check' : 'alert') ?>
                                </span>
                                <span class="li-body">
                                    <span class="li-title">
                                        <?= $audit['result'] === 'match' ? __('Sesuai') : __('Selisih') ?>
                                        &middot; audit <?= (int) $audit['audited_qty'] ?><?= can_see_stock_qty() ? ' (sistem ' . (int) $audit['expected_qty'] . ')' : '' ?> (hitung <?= (int) $audit['counted_qty'] ?>)
                                    </span>
                                    <span class="li-meta"><?= e($audit['auditor_name'] ?: 'Auditor') ?> &middot; <?= e(format_date($audit['audited_at'])) ?></span>
                                    <?php if (! empty($audit['note'])): ?>
                                        <span class="li-meta">&ldquo;<?= e($audit['note']) ?>&rdquo;</span>
                                    <?php endif; ?>
                                </span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div>
        <?php if (can('count_data')): ?>
            <div class="card">
                <div class="card-header"><h3><?= icon('box') ?> Input Hasil Hitung</h3></div>
                <div class="card-body">
                    <?php if ($isLocked && ! can('unlock_data')): ?>
                        <div class="alert alert-warning"><?= icon('shield-lock') ?><div>Data terkunci karena sudah diposting. Ajukan unlock ke Admin STO untuk mengubah.</div></div>
                    <?php else: ?>
                        <form method="post" action="<?= url('items/' . (int) $item['id'] . '/count') ?>">
                            <?= csrf_field() ?>
                            <div class="form-group">
                                <label for="counted_qty"><?= __('Jumlah hasil hitung fisik') ?></label>
                                <input type="number" id="counted_qty" name="counted_qty" min="0" value="<?= $counted ?? (can_see_stock_qty() ? $expected : '') ?>" required>
                            </div>
                            <div class="form-group mt-1">
                                <label for="condition_note"><?= __('Catatan kondisi') ?></label>
                                <textarea id="condition_note" name="condition_note" maxlength="255"><?= e($item['condition_note']) ?></textarea>
                            </div>
                            <button type="submit" class="btn btn-block mt-1"><?= icon('check') ?> Simpan Hasil Hitung</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header"><h3><?= icon('send') ?> Alur Data</h3></div>
            <div class="card-body">
                <div class="list-reset">
                    <div class="list-item">
                        <span class="li-icon"><?= icon($counted !== null ? 'check' : 'clock') ?></span>
                        <span class="li-body"><span class="li-title"><?= __('1. Hitung oleh observer') ?></span>
                        <span class="li-meta"><?= $counted !== null ? __('Selesai') : __('Menunggu') ?></span></span>
                    </div>
                    <div class="list-item">
                        <span class="li-icon"><?= icon($item['count_status'] === 'posted' ? 'check' : 'clock') ?></span>
                        <span class="li-body"><span class="li-title"><?= __('2. Posting ke audit') ?></span>
                        <span class="li-meta"><?= $item['count_status'] === 'posted' ? 'Terkirim ' . e(format_date($item['posted_at'])) : 'Belum diposting' ?></span></span>
                    </div>
                    <div class="list-item">
                        <span class="li-icon"><?= icon(in_array($item['audit_status'], ['audited', 'resolved'], true) ? 'check' : ($item['audit_status'] === 'discrepancy' ? 'alert' : 'clock')) ?></span>
                        <span class="li-body"><span class="li-title"><?= __('3. Audit minimal 10%') ?></span>
                        <span class="li-meta"><?= e($auditLabel) ?></span></span>
                    </div>
                </div>

                <div class="divider"></div>

                <div class="card-actions-stack" style="display:flex;flex-direction:column;gap:.75rem;margin-top:.75rem;">
                <?php if (can('post_data') && can_action('items', 'post') && $item['count_status'] !== 'pending' && $item['count_status'] !== 'posted'): ?>
                    <form method="post" action="<?= url('items/' . (int) $item['id'] . '/post') ?>"
                          data-confirm="<?= e(__('Posting data ini ke audit? Data akan terkunci.')) ?>" style="margin:0;">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn btn-primary btn-block" style="min-height:44px;font-weight:600;"><?= icon('send') ?> Posting ke Audit</button>
                    </form>
                <?php endif; ?>

                <?php if ($isLocked && can('unlock_data')): ?>
                    <form method="post" action="<?= url('items/' . (int) $item['id'] . '/unlock') ?>"
                          data-confirm="<?= e(__('Buka kunci data ini untuk pembaruan?')) ?>" style="margin:0;">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn btn-warning btn-block" style="min-height:44px;font-weight:600;"><?= icon('unlock') ?> Unlock Data</button>
                    </form>
                <?php endif; ?>
                </div>
            </div>
        </div>

        <?php if ($discrepancy !== null): ?>
            <div class="card">
                <div class="card-header"><h3 style="color:var(--danger-text)"><?= icon('alert') ?> Selisih</h3></div>
                <div class="card-body text-sm">
                    <div class="mb-1"><span class="faint"><?= __('Status:') ?></span> <span class="badge <?= $discrepancy['status'] === 'resolved' ? 'badge-brand' : 'badge-danger' ?>"><?= e(ucfirst($discrepancy['status'])) ?></span></div>
                    <div class="mb-1"><span class="faint"><?= __('Selisih:') ?></span> <strong><?= (int) $discrepancy['difference'] ?></strong></div>
                    <div class="mb-2"><span class="faint"><?= __('Catatan:') ?></span> <?= e($discrepancy['note'] ?: '-') ?></div>
                    <a class="btn btn-outline btn-sm btn-block" href="<?= url('discrepancies/' . (int) $discrepancy['id']) ?>">Lihat penanganan selisih</a>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($discrepancy === null && can('manage_discrepancy')): ?>
            <div class="card">
                <div class="card-header"><h3><?= icon('alert') ?> Tandai Selisih</h3></div>
                <div class="card-body">
                    <p class="text-sm muted">Tandai data ini sebagai selisih. Observer pada group ini akan dinotifikasi untuk mencari fisiknya.</p>
                    <form method="post" action="<?= url('discrepancies') ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="item_id" value="<?= (int) $item['id'] ?>">
                        <div class="form-group">
                            <label for="note"><?= __('Catatan selisih') ?></label>
                            <textarea id="note" name="note" maxlength="255" required placeholder="<?= __('Misal: hasil audit tidak sesuai dengan fisik') ?>"></textarea>
                        </div>
                        <button type="submit" class="btn btn-warning btn-block mt-1"><?= icon('alert') ?> Tandai sebagai selisih</button>
                    </form>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>