<?php
/**
 * Berita Acara STO per group - halaman cetak.
 * @var array $group
 * @var array $stats
 * @var array $observers
 * @var array $auditors
 * @var array $tablets
 * @var array $pendingItems
 * @var bool  $canSeeStockQty
 * @var string $periode
 */
$pct = $stats['items'] > 0 ? (int) round($stats['counted'] * 100 / $stats['items']) : 0;
$tgl = date('d F Y');
$periode = $periode ?? '-';
?>
<div class="no-print page-head">
    <div>
        <h1><?= __('Berita Acara STO') ?></h1>
        <p><?= e($group['name']) ?> &middot; siap dicetak</p>
    </div>
    <div class="spacer"></div>
    <button type="button" class="btn btn-brand" onclick="window.print()"><?= icon('download') ?> Cetak / Simpan PDF</button>
    <a class="btn btn-outline" href="<?= url('groups/' . (int) $group['id']) ?>"><?= icon('arrow-left') ?> Kembali</a>
</div>

<article class="ba-doc card">
    <header class="ba-head">
        <div class="ba-kop">
            <strong><?= __('BERITA ACARA STOCK TAKE OPNAME (STO)') ?></strong>
            <span><?= e($group['sto_name'] ?? 'Stock Take Opname') ?></span>
        </div>
        <table class="ba-meta">
            <tr><td><?= __('Group') ?></td><td>: <?= e($group['name']) ?> (<span class="mono"><?= e($group['code']) ?></span>)</td></tr>
            <tr><td><?= __('Gudang / Situs') ?></td><td>: <?= e($group['warehouse_name'] ?? '-') ?><?= ! empty($group['warehouse_city']) ? ', ' . e($group['warehouse_city']) : '' ?></td></tr>
            <tr><td><?= __('Periode hitung') ?></td><td>: <?= e($periode) ?></td></tr>
            <tr><td><?= __('Tanggal cetak') ?></td><td>: <?= e($tgl) ?></td></tr>
        </table>
    </header>

    <section class="ba-sec">
        <h3><?= __('1. Ringkasan hitung fisik') ?></h3>
        <table class="ba-table">
            <tbody>
                <tr><td><?= __('Total barang') ?></td><td class="t-right"><?= (int) $stats['items'] ?></td></tr>
                <tr><td><?= __('Sudah dihitung') ?></td><td class="t-right"><?= (int) $stats['counted'] ?> (<?= $pct ?>%)</td></tr>
                <tr><td><?= __('Masih pending') ?></td><td class="t-right"><?= (int) $stats['pending'] ?></td></tr>
                <tr><td><?= __('Permintaan cek ulang') ?></td><td class="t-right"><?= (int) $stats['recheck'] ?></td></tr>
                <tr><td><?= __('Jumlah rak / lokasi') ?></td><td class="t-right"><?= (int) $stats['locations'] ?></td></tr>
                <?php if ($canSeeStockQty): ?>
                <tr><td><?= __('Total qty sistem') ?></td><td class="t-right"><?= (int) $stats['stock'] ?></td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </section>

    <section class="ba-sec">
        <h3><?= __('2. Petugas') ?></h3>
        <div class="ba-scroll"><table class="ba-table">
            <thead><tr><th><?= __('Peran') ?></th><th><?= __('Nama') ?></th><th><?= __('No. telp') ?></th></tr></thead>
            <tbody>
                <?php foreach ($observers as $u): ?>
                    <tr><td><?= __('Observer') ?></td><td><?= e($u['name']) ?></td><td><?= $u['phone'] ? e($u['phone']) : '<span class="ba-fill"></span>' ?></td></tr>
                <?php endforeach; ?>
                <?php foreach ($auditors as $u): ?>
                    <tr><td><?= __('Auditor') ?></td><td><?= e($u['name']) ?></td><td><?= $u['phone'] ? e($u['phone']) : '<span class="ba-fill"></span>' ?></td></tr>
                <?php endforeach; ?>
                <?php if ($observers === [] && $auditors === []): ?>
                    <tr><td colspan="3" class="faint"><?= __('Belum ada petugas terdaftar.') ?></td></tr>
                <?php endif; ?>
            </tbody>
        </table></div>
    </section>

    <section class="ba-sec">
        <h3><?= __('3. Tablet yang digunakan') ?></h3>
        <table class="ba-table">
            <thead><tr><th><?= __('Kode') ?></th><th><?= __('Nama') ?></th><th><?= __('Serial') ?></th><th><?= __('Rak') ?></th></tr></thead>
            <tbody>
                <?php foreach ($tablets as $t): ?>
                    <tr><td class="mono"><?= e($t['code']) ?></td><td><?= e($t['name']) ?></td><td class="mono"><?= e($t['serial_number'] ?: '-') ?></td><td><?= e($t['location_name']) ?></td></tr>
                <?php endforeach; ?>
                <?php if ($tablets === []): ?>
                    <tr><td colspan="4" class="faint"><?= __('Tidak ada tablet terpasang.') ?></td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </section>

    <section class="ba-sec">
        <h3>4. Barang belum dihitung (<?= (int) $stats['pending'] ?>)</h3>
        <?php if ($pendingItems === []): ?>
            <p><?= __('Seluruh barang pada group ini sudah dihitung.') ?></p>
        <?php else: ?>
            <table class="ba-table">
                <thead><tr><th><?= __('Kode') ?></th><th><?= __('Nama barang') ?></th><th><?= __('Rak') ?></th></tr></thead>
                <tbody>
                    <?php foreach ($pendingItems as $it): ?>
                        <tr><td class="mono"><?= e($it['code']) ?></td><td><?= e($it['name']) ?></td><td><?= e($it['location_name'] ?? '-') ?></td></tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </section>

    <section class="ba-sign">
        <p>Demikian berita acara ini dibuat dengan sebenarnya untuk dipergunakan sebagaimana mestinya.</p>
        <div class="ba-sign-grid">
            <div><span><?= __('Observer') ?></span><div class="ba-line"></div><small>(nama &amp; tanda tangan)</small></div>
            <div><span><?= __('Auditor') ?></span><div class="ba-line"></div><small>(nama &amp; tanda tangan)</small></div>
            <div><span><?= __('Admin STO') ?></span><div class="ba-line"></div><small>(nama &amp; tanda tangan)</small></div>
        </div>
    </section>
</article>

<style>
    .ba-scroll{overflow-x:auto;-webkit-overflow-scrolling:touch;max-width:100%;}
    .ba-scroll>.ba-table{min-width:100%;}
.ba-doc { padding: 1.5rem; }
.ba-head { border-bottom: 2px solid var(--text); padding-bottom: .75rem; margin-bottom: 1rem; }
.ba-kop strong { display: block; font-size: 1.05rem; letter-spacing: .02em; }
.ba-kop span { color: var(--muted); font-size: .9rem; }
.ba-meta { margin-top: .6rem; border-collapse: collapse; font-size: .9rem; }
.ba-meta td { padding: .15rem .5rem .15rem 0; vertical-align: top; }
.ba-meta td:first-child { width: 9.5rem; color: var(--muted); }
.ba-sec { margin-top: 1.25rem; page-break-inside: avoid; }
.ba-sec h3 { font-size: .95rem; margin: 0 0 .5rem; }
.ba-table { width: 100%; border-collapse: collapse; font-size: .88rem; }
.ba-table th, .ba-table td { border: 1px solid var(--border, #d8d2c4); padding: .4rem .55rem; text-align: left; }
.ba-table th { background: #f6f3ea; }
.ba-sign { margin-top: 2rem; page-break-inside: avoid; }
.ba-sign-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1.5rem; margin-top: 1.5rem; text-align: center; font-size: .9rem; }
.ba-line { border-bottom: 1.5px solid #1a1a1a; height: 4rem; margin: .75rem 0 .35rem; }
.ba-sign-grid small { color: var(--muted); }
.ba-fill { display:inline-block; width:8rem; border-bottom:1px solid #1a1a1a; }
@media print {
    .no-print, .sidebar, .topbar, .card-toolbar, .page-head, .toast-stack, .alert, [data-auto-dismiss], .flash-stack { display: none !important; }
    html, body, .app-shell, .main, .main-inner, .content, .page { height: auto !important; min-height: 0 !important; overflow: visible !important; }
    .card, .ba-doc, .card-body { overflow: visible !important; }
    .ba-doc { page-break-inside: auto; }
    .ba-table, .ba-meta { page-break-inside: auto; }
    .ba-table tr, .ba-meta tr { page-break-inside: avoid; }
    .ba-sec h3 { page-break-after: avoid; }
    .main { margin: 0 !important; }
    .ba-doc { box-shadow: none !important; border: none !important; padding: 0 !important; }
    body { background: #fff !important; }
    @page { size: A4; margin: 16mm; }
}
@media (max-width: 640px) {
    .ba-sign-grid { grid-template-columns: 1fr; }
}
</style>
