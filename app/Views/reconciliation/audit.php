<?php
/**
 * Audit Riwayat Koreksi Rekonsiliasi (count_history) — konsisten UI/UX
 * dengan halaman Rekonsiliasi. Filter: group, aksi, field, tanggal, cari.
 *
 * @var array $rows
 * @var array $filters
 * @var array $groups
 * @var array $totals
 * @var array $actionLabels
 * @var array $fieldLabels
 */

$actionBadge = static fn (string $a): string => match (true) {
    str_contains($a, 'recheck')      => 'badge-info',
    str_contains($a, 'verification') => 'badge-success',
    str_contains($a, 'reject')       => 'badge-danger',
    str_contains($a, 'price')        => 'badge-warning',
    default                          => 'badge-neutral',
};
?><?= page_hero() ?><div class="page-head has-hero">
    <div>
        <h1><?= __('Audit Riwayat Koreksi Rekonsiliasi') ?></h1>
        <p>Jejak lengkap setiap perubahan angka rekonsiliasi — siapa, kapan, dari berapa ke berapa, dan alasannya.</p>
    </div>
    <div class="spacer"></div>
    <a class="btn btn-outline" href="<?= url('reconciliation') ?>"><?= icon('scale') ?> Ke Rekonsiliasi</a>
</div>

<!-- Panduan cepat halaman ini (akses & cara pakai) -->
<details class="sf-help mb-lg">
    <summary><?= icon('chev-down', 'sf-help-chev') ?> Panduan singkat: fungsi halaman &amp; siapa yang boleh mengubah angka</summary>
    <div class="sf-help-body">
        <div class="sf-help-col">
            <strong><?= __('Apa yang Anda lihat di sini') ?></strong>
            <ol>
                <li><b><?= __('Jejak audit') ?></b>. Tiap perubahan angka rekonsiliasi (qty &amp; harga) tercatat otomatis: nilai lama &rarr; nilai baru, pelaku, waktu, dan alasan.</li>
                <li><b><?= __('Koreksi Angka') ?></b>. Dilakukan dari halaman <a href="<?= url('reconciliation') ?>">Rekonsiliasi</a> &rarr; buka detail item &rarr; tombol <em><?= __('Koreksi Angka') ?></em>.</li>
                <li><b><?= __('Verifikasi') ?></b>. Perubahan non-admin berstatus <em><?= __('Menunggu Verifikasi') ?></em> sampai Admin STO menyetujuinya.</li>
            </ol>
        </div>
        <div class="sf-help-col">
            <strong><?= __('Hak akses angka &amp; audit') ?></strong>
            <ul>
                <li><?= icon('eye') ?> <b><?= __('Observer') ?></b> — hanya melihat riwayat; nilai stok buku &amp; nominal disamarkan.</li>
                <li><?= icon('clipboard-check') ?> <b><?= __('Petugas / Audit') ?></b> — boleh mengisi &amp; koreksi (dengan alasan); hasilnya menunggu verifikasi.</li>
                <li><?= icon('shield-check') ?> <b><?= __('Admin STO / Power Admin') ?></b> — verifikasi perubahan, lihat nominal penuh, dan ubah item terkunci.</li>
            </ul>
            <p class="text-xs muted" style="margin:.4rem 0 0;">Halaman ini <b><?= __('read-only') ?></b>: tidak ada tombol yang mengubah data — semua perubahan terjadi di halaman Rekonsiliasi / Audit.</p>
        </div>
    </div>
</details>

<!-- ====================================================================== -->
<!-- 1. KPI (komponen .stat — konsisten dengan halaman Rekonsiliasi)         -->
<!-- ====================================================================== -->
<div class="kpi-grid">
    <div class="stat is-brand" style="--i:0">
        <div class="stat-icon"><?= icon('clock') ?></div>
        <span class="stat-label"><?= __('Total Perubahan') ?></span>
        <div class="stat-value"><?= (int) ($totals['total'] ?? 0) ?></div>
        <div class="stat-meta"><?= __('baris audit tercatat') ?></div>
    </div>
    <div class="stat is-info" style="--i:1">
        <div class="stat-icon"><?= icon('users') ?></div>
        <span class="stat-label"><?= __('Pelaku Unik') ?></span>
        <div class="stat-value"><?= (int) ($totals['actors'] ?? 0) ?></div>
        <div class="stat-meta"><?= __('user yang melakukan koreksi') ?></div>
    </div>
    <div class="stat is-warning" style="--i:2">
        <div class="stat-icon"><?= icon('edit') ?></div>
        <span class="stat-label"><?= __('Koreksi Angka') ?></span>
        <div class="stat-value"><?= (int) ($totals['qty_changes'] ?? 0) ?></div>
        <div class="stat-meta">perubahan counted_qty</div>
    </div>
    <div class="stat is-success" style="--i:3">
        <div class="stat-icon"><?= icon('check-circle') ?></div>
        <span class="stat-label"><?= __('Hari Ini') ?></span>
        <div class="stat-value"><?= (int) ($totals['today'] ?? 0) ?></div>
        <div class="stat-meta"><?= __('perubahan dalam 24 jam terakhir') ?></div>
    </div>
</div>

<div class="card">
    <div class="card-header flex flex-wrap gap-md items-center justify-between" style="border-bottom:1px solid var(--border);padding:.85rem 1.1rem;">
        <form method="get" action="<?= url('reconciliation/audit') ?>#audit-table-wrap" class="flex flex-wrap gap-sm items-center" style="margin:0;">
            <div class="form-group" style="margin:0;">
                <select name="group_id" onchange="this.form.submit()" aria-label="<?= __('Filter Group') ?>" style="font-size:.82rem;padding:.35rem .6rem;">
                    <option value=""><?= __('Semua Group') ?></option>
                    <?php foreach ($groups as $g): ?>
                        <option value="<?= (int) $g['id'] ?>" <?= ($filters['group_id'] ?? null) === (int) $g['id'] ? 'selected' : '' ?>><?= e($g['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group" style="margin:0;">
                <select name="action" onchange="this.form.submit()" aria-label="<?= __('Filter Aksi') ?>" style="font-size:.82rem;padding:.35rem .6rem;">
                    <option value=""><?= __('Semua Aksi') ?></option>
                    <?php foreach ($actionLabels as $ak => $al): ?>
                        <option value="<?= e($ak) ?>" <?= ($filters['action'] ?? null) === $ak ? 'selected' : '' ?>><?= e($al) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group" style="margin:0;">
                <select name="field" onchange="this.form.submit()" aria-label="<?= __('Filter Field') ?>" style="font-size:.82rem;padding:.35rem .6rem;">
                    <option value=""><?= __('Semua Field') ?></option>
                    <?php foreach ($fieldLabels as $fk => $fl): ?>
                        <option value="<?= e($fk) ?>" <?= ($filters['field'] ?? null) === $fk ? 'selected' : '' ?>><?= e($fl) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php if (! empty($filters['group_id']) || ! empty($filters['action']) || ! empty($filters['field']) || ! empty($filters['search'])): ?>
                <a href="<?= url('reconciliation/audit') ?>#audit-table-wrap" class="btn btn-outline btn-xs" title="<?= __('Reset filter') ?>"><?= icon('x') ?> Reset</a>
            <?php endif; ?>
        </form>

        <div class="flex items-center gap-xs" style="min-width:240px;max-width:340px;position:relative;">
            <span style="position:absolute;left:10px;top:50%;transform:translateY(-50%);color:var(--text-muted);pointer-events:none;"><?= icon('search') ?></span>
            <input type="search" id="audit-search" value="<?= e($filters['search'] ?? '') ?>"
                   placeholder="<?= __('Cari kode / nama item / alasan...') ?>"
                   style="width:100%;padding:.4rem .7rem .4rem 2.2rem;font-size:.82rem;border-radius:var(--radius-sm);border:1px solid var(--border);"
                   autocomplete="off">
            <span id="audit-search-count" class="text-xs muted" style="white-space:nowrap;"></span>
        </div>
    </div>

    <div class="table-wrap recon-table-wrap" id="audit-table-wrap">
        <table class="data" id="audit-table" data-colmgr-key="recon-audit-v1" data-colmgr-prefix="recon-audit"><caption class="sr-only">Rincian rekonsiliasi</caption>
            <thead>
                <tr>
                    <th data-col="time"><?= __('Waktu') ?></th>
                    <th data-col="item"><?= __('Item') ?></th>
                    <th data-col="group" class="col-group-loc"><?= __('Group') ?></th>
                    <th data-col="field" class="t-center"><?= __('Field') ?></th>
                    <th data-col="change" class="t-center"><?= __('Perubahan') ?></th>
                    <th data-col="action" class="t-center"><?= __('Aksi') ?></th>
                    <th data-col="user"><?= __('Pelaku') ?></th>
                    <th data-col="reason"><?= __('Alasan') ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if ($rows['items'] === []): ?>
                    <tr>
                        <td colspan="8">
                            <div class="empty-state">
                                <div class="empty-icon"><?= icon('clipboard-list') ?></div>
                                <h3><?= __('Belum ada riwayat') ?></h3>
                                <p><?= __('Belum ada koreksi angka yang tercatat untuk filter ini.') ?></p>
                            </div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($rows['items'] as $h): ?>
                        <?php
                        $delta = $h['delta'] !== null ? (int) $h['delta'] : null;
                        $isQty = ($h['field_name'] === 'counted_qty');
                        $oldV  = $isQty ? number_format((int) $h['old_value'], 0, ',', '.') : format_rupiah((float) $h['old_value']);
                        $newV  = $isQty ? number_format((int) $h['new_value'], 0, ',', '.') : format_rupiah((float) $h['new_value']);
                        ?>
                        <tr class="audit-row"
                            data-code="<?= strtolower(e($h['item_code'] ?? '')) ?>"
                            data-name="<?= strtolower(e($h['item_name'] ?? '')) ?>"
                            data-reason="<?= strtolower(e($h['reason'] ?? '')) ?>">
                            <td data-label="<?= __('Waktu') ?>" class="text-sm nowrap"><?= e(format_date($h['changed_at'], 'd M Y H:i')) ?></td>
                            <td data-label="<?= __('Item') ?>">
                                <span class="code-badge mono" title="<?= e((string) ($h['item_code'] ?? '-')) ?>"><span class="code-badge-prefix"><?= e(explode('-', (string) ($h['item_code'] ?? '-'))[0]) ?></span><span class="code-badge-val"><?= e(substr((string) ($h['item_code'] ?? '-'), strlen(explode('-', (string) ($h['item_code'] ?? '-'))[0]))) ?></span></span>
                                <div class="cell-sub muted text-xs"><?= e($h['item_name'] ?? '') ?></div>
                            </td>
                            <td data-label="<?= __('Group') ?>" class="col-group-loc"><?= e($h['group_name'] ?? '—') ?></td>
                            <td data-label="<?= __('Field') ?>" class="t-center">
                                <code><?= e($fieldLabels[$h['field_name']] ?? $h['field_name']) ?></code>
                            </td>
                            <td data-label="<?= __('Perubahan') ?>" class="t-center">
                                <div class="audit-delta text-xs mono">
                                    <span class="audit-old"><?= e($oldV) ?></span>
                                    <?= icon('chev-right') ?>
                                    <strong class="audit-new"><?= e($newV) ?></strong>
                                    <?php if ($delta !== null && $delta !== 0 && $isQty): ?>
                                        <span class="badge <?= $delta > 0 ? 'badge-success' : 'badge-danger' ?>"><?= $delta > 0 ? '+' : '' ?><?= $delta ?></span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td data-label="<?= __('Aksi') ?>" class="t-center">
                                <span class="badge <?= $actionBadge((string) $h['action']) ?>">
                                    <span class="status-dot"></span><?= e($actionLabels[$h['action']] ?? $h['action']) ?>
                                </span>
                            </td>
                            <td data-label="<?= __('Pelaku') ?>">
                                <div class="cell-title"><?= e($h['user_name'] ?? 'User') ?></div>
                                <div class="cell-sub muted text-xs"><?= e(role_label($h['user_role'] ?? '')) ?></div>
                            </td>
                            <td data-label="<?= __('Alasan') ?>" class="audit-reason"><?= $h['reason'] !== null ? e(mb_strimwidth((string) $h['reason'], 0, 70, '…')) : '<span class="muted">—</span>' ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if (($rows['last_page'] ?? 1) > 1): ?>
        <div class="card-footer flex items-center justify-between" style="padding:.85rem 1.1rem;border-top:1px solid var(--border);">
            <div class="text-xs muted">Halaman <?= (int) $rows['page'] ?> dari <?= (int) $rows['last_page'] ?> (Total <?= (int) $rows['total'] ?> audit)</div>
            <div class="pagination flex gap-xs">
                <?php if ((int) $rows['page'] > 1): ?>
                    <a href="<?= url('reconciliation/audit?' . query(['page' => (int) $rows['page'] - 1])) ?>#audit-table-wrap" class="btn btn-outline btn-xs">&larr; Sebelumnya</a>
                <?php endif; ?>
                <?php if ((int) $rows['page'] < (int) $rows['last_page']): ?>
                    <a href="<?= url('reconciliation/audit?' . query(['page' => (int) $rows['page'] + 1])) ?>#audit-table-wrap" class="btn btn-outline btn-xs">Berikutnya &rarr;</a>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
(function () {
    var input = document.getElementById('audit-search');
    var table = document.getElementById('audit-table');
    var count = document.getElementById('audit-search-count');
    if (!input || !table) return;

    var rows = table.querySelectorAll('tbody tr.audit-row');
    var total = rows.length;

    function filter() {
        var q = input.value.trim().toLowerCase();
        var shown = 0;
        for (var i = 0; i < rows.length; i++) {
            var r = rows[i];
            var hay = (r.getAttribute('data-code') || '') + ' ' + (r.getAttribute('data-name') || '') + ' ' + (r.getAttribute('data-reason') || '');
            var ok = !q || hay.indexOf(q) !== -1;
            r.style.display = ok ? '' : 'none';
            if (ok) shown++;
        }
        if (count) count.textContent = q ? (shown + '/' + total + ' cocok') : '';
    }
    input.addEventListener('input', filter);
    if (input.value.trim()) filter();
})();
</script>