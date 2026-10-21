<?php
/**
 * Hasil import master (ringkasan per sheet).
 *
 * @var array $out    ['dry_run'=>bool,'sheets'=>list<string>,'report'=>array<string,array>]
 * @var bool  $dryRun
 */

$labels = [
    'periode'  => 'Periode',
    'master'   => 'Master',
    'gudang'   => 'Gudang',
    'group'    => 'Group',
    'karyawan' => 'Karyawan',
    'petugas'  => 'Petugas',
    'lokasi'   => 'Lokasi',
    'tablet'   => 'Tablet',
    'item'     => 'Item',
];

$report = is_array($out['report'] ?? null) ? $out['report'] : [];

// Ambil hanya sheet yang benar-benar punya entri laporan. Sheet yang disebut di
// 'sheets' tapi tidak ada di 'report' (mis. sheet master kosong yang disembunyikan
// service) dilewati supaya tidak memicu warning "Undefined array key".
$rows = [];
foreach ((array) ($out['sheets'] ?? []) as $s) {
    if (isset($report[$s]) && is_array($report[$s])) {
        $rows[$s] = $report[$s];
    }
}
if ($rows === []) {
    foreach ($report as $s => $r) {
        if (is_array($r)) {
            $rows[$s] = $r;
        }
    }
}

$tot    = ['created' => 0, 'updated' => 0, 'failed' => 0];
$allErr = [];
foreach ($rows as $r) {
    $tot['created'] += (int) ($r['created'] ?? 0);
    $tot['updated'] += (int) ($r['updated'] ?? 0);
    $tot['failed']  += (int) ($r['failed'] ?? 0);
    foreach ((array) ($r['errors'] ?? []) as $err) {
        $allErr[] = $err;
    }
}
?>
<div class="page-head">
    <div>
        <h1><?= __('Hasil Import Master') ?></h1>
        <p>
            <?= $dryRun
                ? 'DRY-RUN: tidak ada data yang disimpan. Periksa laporan lalu jalankan mode simpan.'
                : 'Data master tersimpan.' ?>
        </p>
    </div>
    <div class="spacer"></div>
    <a class="btn" href="<?= url('import') ?>">Kembali ke Import</a>
</div>

<div class="card">
    <div class="card-header"><h2><?= __('Ringkasan per sheet') ?></h2></div>
    <?php if ($rows === []): ?>
        <div class="card-body">
            <p class="muted" style="margin:0;">
                Tidak ada baris data yang terbaca dari file ini. Pastikan nama sheet sesuai
                (<?= e(implode(', ', array_keys($labels))) ?>) dan baris pertama berisi header kolom.
            </p>
        </div>
    <?php else: ?>
    <div class="table-wrap">
        <table class="data">
            <caption class="sr-only">Ringkasan hasil import per sheet</caption>
            <thead>
                <tr>
                    <th><?= __('Sheet') ?></th>
                    <th class="t-center"><?= __('Baru') ?></th>
                    <th class="t-center"><?= __('Diperbarui') ?></th>
                    <th class="t-center"><?= __('Gagal') ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $s => $r): ?>
                    <tr>
                        <td class="cell-title"><?= e($labels[$s] ?? $s) ?></td>
                        <td class="t-center"><?= (int) ($r['created'] ?? 0) ?></td>
                        <td class="t-center"><?= (int) ($r['updated'] ?? 0) ?></td>
                        <td class="t-center<?= (int) ($r['failed'] ?? 0) > 0 ? ' text-danger' : '' ?>"><?= (int) ($r['failed'] ?? 0) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr>
                    <th>Total</th>
                    <th class="t-center"><?= $tot['created'] ?></th>
                    <th class="t-center"><?= $tot['updated'] ?></th>
                    <th class="t-center"><?= $tot['failed'] ?></th>
                </tr>
            </tfoot>
        </table>
    </div>
    <?php endif; ?>
</div>

<?php if ($allErr !== []): ?>
<div class="card mt-2">
    <div class="card-header"><h2>Pesan kesalahan (<?= count($allErr) ?>)</h2></div>
    <div class="card-body">
        <ul style="margin:0;padding-left:1.1rem;font-size:.85rem;">
            <?php foreach ($allErr as $err): ?><li><?= e($err) ?></li><?php endforeach; ?>
        </ul>
    </div>
</div>
<?php endif; ?>

<?php if ($dryRun): ?>
<div class="card mt-2">
    <div class="card-body">
        <p class="text-sm muted" style="margin:0 0 .75rem;">
            Mode simpan butuh file diunggah ulang. Kembali ke halaman Import, pilih file yang sama,
            lalu klik "Simpan Master".
        </p>
        <a class="btn btn-brand" href="<?= url('import') ?>">Kembali dan simpan</a>
    </div>
</div>
<?php endif; ?>
