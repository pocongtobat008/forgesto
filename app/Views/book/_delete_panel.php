<?php
/**
 * Panel hapus periode tutup buku untuk halaman Tahun Buku.
 * @var array $deletable  daftar periode yang boleh dihapus (dari BookYearController::index)
 */
$deletable = $deletable ?? [];
if ($canAdmin && $deletable !== []):
?>
<section class="card" aria-labelledby="book-del-title" style="margin-bottom:1rem;">
    <div class="card-header">
        <div>
            <h2 id="book-del-title"><?= icon('trash') ?> Hapus periode tutup buku</h2>
            <p class="muted text-sm" style="margin:.2rem 0 0;">Periode yang dihapus tidak dapat dipulihkan. Periode yang sedang berjalan tidak dapat dihapus.</p>
        </div>
    </div>
    <div class="by-body">
    <div class="table-wrap">
        <table class="data"><caption class="sr-only">Periode yang akan dihapus</caption>
            <thead><tr><th><?= __('Periode') ?></th><th class="col-hide-tablet"><?= __('Catatan') ?></th><th class="t-right"><?= __('Aksi') ?></th></tr></thead>
            <tbody>
            <?php foreach ($deletable as $d): ?>
                <tr>
                    <td data-label="<?= __('Periode') ?>"><div class="cell-title"><?= e($labelBulan((string) $d['audit_month'])) ?></div><div class="cell-sub"><?= e((string) $d['audit_month']) ?></div></td>
                    <td data-label="<?= __('Catatan') ?>" class="col-hide-tablet"><?= e((string) $d['note']) ?></td>
                    <td data-label="" class="t-right">
                        <a class="btn btn-danger btn-sm" href="<?= url('tahun-buku/hapus/' . (int) $d['id']) ?>"><?= icon('trash') ?> Hapus</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div></section>
<?php endif; ?>
