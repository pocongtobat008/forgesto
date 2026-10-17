<?php
/**
 * Detail selisih close buku per group untuk satu periode (read-only).
 * @var array $period
 * @var array $rows
 */
$period = $period ?? [];
$rows   = $rows ?? [];
$isClosed = ($period['status'] ?? '') === 'closed';

$bulan = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
$labelBulan = static function (string $ym) use ($bulan): string {
    $t = strtotime($ym . '-01');
    return $t === false ? $ym : $bulan[(int) date('n', $t)] . ' ' . date('Y', $t);
};
$tgl = static function (string $d) use ($bulan): string {
    $t = strtotime($d);
    return $t === false ? $d : (int) date('j', $t) . ' ' . mb_substr($bulan[(int) date('n', $t)], 0, 3) . ' ' . date('Y', $t);
};

/* Blind count: halaman ini seluruhnya berisi qty sistem & selisih. */
if (! can_see_stock_qty()) {
    echo '<div class="page-head"><div><h1>Tutup buku ' . e($labelBulan((string) ($period['audit_month'] ?? ''))) . '</h1>'
        . '<p>Periode: ' . e($tgl((string) ($period['audit_start'] ?? ''))) . ' – ' . e($tgl((string) ($period['audit_end'] ?? ''))) . '</p></div>'
        . '<div class="spacer"></div><a class="btn btn-outline" href="' . url('tahun-buku') . '">' . icon('arrow-left') . ' Tahun Buku</a></div>'
        . '<section class="card"><div class="card-body">'
        . '<p class="muted" style="margin:0;">Ringkasan selisih per group hanya dapat dilihat oleh admin. '
        . 'Status periode: <strong>' . ($isClosed ? 'ditutup' : 'belum ditutup') . '</strong>.</p>'
        . '</div></section>';
    return;
}
?>
<div class="page-head">
    <div>
        <h1>Tutup buku <?= e($labelBulan((string) ($period['audit_month'] ?? ''))) ?></h1>
        <p>
            <?= e($tgl((string) ($period['audit_start'] ?? ''))) ?> – <?= e($tgl((string) ($period['audit_end'] ?? ''))) ?>
            · <?= $isClosed ? 'ditutup ' . e($tgl(substr((string) ($period['closed_at'] ?? ''), 0, 10))) : 'belum ditutup' ?>
        </p>
    </div>
    <div class="spacer"></div>
    <div class="flex gap-sm items-center" style="flex-wrap:wrap;">
        <a class="btn btn-outline" href="<?= url('tahun-buku') ?>" style="min-height:44px;"><?= icon('arrow-left') ?> Tahun Buku</a>
        <?php if ($isClosed): ?>
            <a class="btn btn-brand" href="<?= url('tahun-buku/' . (int) ($period['id'] ?? 0) . '/worksheet') ?>" style="min-height:44px;font-weight:600;" title="<?= __('Buka seluruh data menu Data STO, Audit, dan Rekonsiliasi periode ini dalam mode read-only') ?>"><?= icon('folder-open') ?> Buka Menu Historis (Read-Only)</a>
        <?php endif; ?>
        <?php if ($isClosed && can('export_data')): ?>
            <a class="btn btn-primary" href="<?= url('tahun-buku/' . (int) ($period['id'] ?? 0) . '/export') ?>" style="min-height:44px;" title="<?= __('Unduh Excel selisih per item dari snapshot periode ini') ?>"><?= icon('download') ?> Export hasil tutup buku</a>
        <?php endif; ?>
    </div>
</div>

<?php if (! $isClosed): ?>
    <section class="card">
        <p class="muted" style="margin:0;">Periode ini masih berjalan. Laporan selisih tersedia setelah periode ditutup.</p>
    </section>
<?php else: ?>
    <section class="card">
        <div class="card-header">
            <h2><?= icon('scale') ?> Selisih per group</h2>
            <div class="spacer"></div>
            <span class="badge badge-danger">Snapshot · read-only</span>
        </div>
        <?php include __DIR__ . '/_groups.php'; ?>
    </section>
<?php endif; ?>
