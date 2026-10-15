<?php
/**
 * @var array $out
 * @var bool $dryRun
 */
$labels = ['master' => 'Master (1 sheet)', 'gudang' => 'Gudang', 'group' => 'Group', 'petugas' => 'Petugas', 'lokasi' => 'Lokasi', 'tablet' => 'Tablet', 'item' => 'Item'];
?>
<div class="page-head">
    <div>
        <h1><?= __('Hasil Import Master') ?></h1>
        <p><?= $dryRun ? 'DRY-RUN: tidak ada data yang disimpan. Periksa laporan lalu import ulang dengan mode simpan.' : 'Data master tersimpan.' ?></p>
    </div>
    <div class="spacer"></div>
    <a class="btn" href="<?= url('import') ?>">Kembali ke Import</a>
</div>

<div class="card">
    <div class="card-header"><h2><?= __('Ringkasan per sheet') ?></h2></div>
    <div class="table-wrap">
        <table class="data">
            <thead>
                <tr>
                    <th><?= __('Sheet') ?></th>
                    <th class="t-center"><?= __('Baru') ?></th>
                    <th class="t-center"><?= __('Diperbarui') ?></th>
                    <th class="t-center"><?= __('Gagal') ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($out['sheets'] as $s): $r = $out['report'][$s]; ?>
                    <tr>
                        <td class="cell-title"><?= e($labels[$s] ?? $s) ?></td>
                        <td class="t-center"><?= (int) $r['created'] ?></td>
                        <td class="t-center"><?= (int) $r['updated'] ?></td>
                        <td class="t-center"><?= (int) $r['failed'] ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php $allErr = []; foreach ($out['report'] as $r) { $allErr = array_merge($allErr, $r['errors']); } ?>
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
<form method="post" action="<?= url('import/master') ?>" enctype="multipart/form-data" class="mt-2">
    <?= csrf_field() ?>
    <p class="text-sm muted">Form simpan membutuhkan file lagi. Pilih file yang sama di halaman Import lalu klik "Simpan Master".</p>
    <a class="btn btn-brand" href="<?= url('import') ?>">Kembali dan simpan</a>
</form>
<?php endif; ?>
