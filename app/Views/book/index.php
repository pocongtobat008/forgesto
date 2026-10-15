<?php
/**
 * Tahun Buku: periode stok opname, status close buku, dan selisih per group.
 * @var ?array $active
 * @var array  $periods
 * @var ?array $lastClosed
 * @var array  $lastRows
 * @var bool   $canAdmin
 */
$active     = $active ?? null;
$periods    = $periods ?? [];
$lastClosed = $lastClosed ?? null;
$lastRows   = $lastRows ?? [];
$canAdmin   = (bool) ($canAdmin ?? false);
$isClosed   = $active !== null && ($active['status'] ?? 'open') === 'closed';

$bulan = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
$labelBulan = static function (string $ym) use ($bulan): string {
    $t = strtotime($ym . '-01');
    return $t === false ? $ym : $bulan[(int) date('n', $t)] . ' ' . date('Y', $t);
};
$tgl = static function (string $d) use ($bulan): string {
    $t = strtotime($d);
    return $t === false ? $d : (int) date('j', $t) . ' ' . mb_substr($bulan[(int) date('n', $t)], 0, 3) . ' ' . date('Y', $t);
};
$rentang = static fn (string $s, string $e): string => $tgl($s) . ' – ' . $tgl($e);

$statusLabel = $active === null ? 'Belum diatur' : ($isClosed ? __('Ditutup') : __('Berjalan'));
$statusClass = $active === null ? '' : ($isClosed ? 'badge-danger' : 'badge-success');
?>
<div class="page-head">
    <div>
        <h1><?= __('Tahun Buku') ?></h1>
        <p>Atur periode stok opname, tutup buku, dan lihat selisih per group.</p>
    </div>
</div>

<section class="card" aria-labelledby="book-active-title" style="margin-bottom:1rem;">
    <div class="card-header">
            <div>
                <p class="muted text-sm" style="margin:0;"><?= __('Periode aktif') ?></p>
                <h2 id="book-active-title" style="margin:.15rem 0 0;font-size:1.25rem;">
                    <?= $active !== null ? e($labelBulan((string) $active['audit_month'])) : 'Belum ada periode' ?>
                </h2>
                <p class="muted text-sm" style="margin:.2rem 0 0;">
                    <?= $active !== null ? e($rentang((string) $active['audit_start'], (string) $active['audit_end'])) : 'Atur periode pertama di bawah.' ?>
                </p>
            </div>
            <span class="badge <?= e($statusClass) ?>"><?= e($statusLabel) ?></span>
    </div>
    <div class="by-body">

    <?php if (! $canAdmin): ?>
        <p class="muted text-sm" style="margin:.8rem 0 0;">Hanya admin yang dapat mengubah periode. Anda tetap bisa melihat riwayat dan selisih.</p>

    <?php elseif ($active !== null && ! $isClosed): ?>
        <form method="post" action="<?= url('audit/period/close') ?>" class="mt-2"
              onsubmit="return confirm('Tutup buku <?= e((string) $active['audit_month']) ?>? Snapshot dibuat dan input dikunci.');">
            <?= csrf_field() ?>
            <p class="muted text-sm" style="margin:0 0 .6rem;">Tutup buku membuat snapshot semua item lalu mengunci input periode ini.</p>
            <button type="submit" class="btn btn-brand" style="min-height:44px;"><?= icon('lock') ?> Tutup buku <?= e($labelBulan((string) $active['audit_month'])) ?></button>
        </form>

    <?php elseif ($active === null): ?>
        <form method="post" action="<?= url('audit/period') ?>" class="filters mt-2" style="grid-template-columns:repeat(auto-fit,minmax(160px,1fr));">
            <?= csrf_field() ?>
            <div class="form-group"><label for="first_month"><?= __('Bulan') ?></label><input type="month" id="first_month" name="audit_month" required></div>
            <div class="form-group"><label for="first_start"><?= __('Tanggal mulai') ?></label><input type="date" id="first_start" name="audit_start" required></div>
            <div class="form-group"><label for="first_end"><?= __('Tanggal selesai') ?></label><input type="date" id="first_end" name="audit_end" required></div>
            <div class="form-actions" style="align-self:end;"><button type="submit" class="btn btn-brand" style="min-height:44px;"><?= icon('check') ?> Buka periode pertama</button></div>
        </form>

    <?php else: ?>
        <form method="post" action="<?= url('audit/period/new') ?>" class="filters mt-2" style="grid-template-columns:repeat(auto-fit,minmax(160px,1fr));">
            <?= csrf_field() ?>
            <div class="form-group"><label for="new_month"><?= __('Bulan baru') ?></label><input type="month" id="new_month" name="audit_month" required></div>
            <div class="form-group"><label for="new_start"><?= __('Tanggal mulai') ?></label><input type="date" id="new_start" name="audit_start" required></div>
            <div class="form-group"><label for="new_end"><?= __('Tanggal selesai') ?></label><input type="date" id="new_end" name="audit_end" required></div>
            <div class="form-actions" style="align-self:end;"><button type="submit" class="btn btn-brand" style="min-height:44px;"><?= icon('check') ?> Buka periode baru</button></div>
        </form>
        <p class="muted text-sm" style="margin:.6rem 0 0;">Hitung direset ke 0 untuk periode baru. Snapshot periode lama tetap tersimpan dan bisa dilihat di riwayat.</p>
    <?php endif; ?>
</div></section>

<?php if ($lastClosed !== null): ?>
<section class="card" aria-labelledby="book-last-title" style="margin-bottom:1rem;">
    <div class="card-header">
        <div>
            <h2 id="book-last-title"><?= icon('scale') ?> Selisih close buku terakhir</h2>
            <p class="muted text-sm" style="margin:.2rem 0 0;">
                <?= e($labelBulan((string) $lastClosed['audit_month'])) ?> · ditutup <?= e($tgl(substr((string) $lastClosed['closed_at'], 0, 10))) ?>
            </p>
        </div>
        <div class="spacer"></div>
        <a class="btn btn-outline btn-sm" href="<?= url('tahun-buku/' . (int) $lastClosed['id']) ?>" style="min-height:44px;"><?= icon('eye') ?> Detail</a>
    </div><div class="by-body">
    <?php $rows = $lastRows; include __DIR__ . '/_groups.php'; ?>
</div></section>
<?php endif; ?>

<?php include __DIR__ . '/_reset_panel.php'; ?>

<?php include __DIR__ . '/_delete_panel.php'; ?>

<section class="card">
    <div class="card-header">
        <h2><?= icon('list') ?> Riwayat periode</h2>
    </div><div class="by-body">
        <div class="card-body" style="padding:1.25rem 1.35rem !important;">
    <div class="table-wrap">
        <table class="data" id="book-table" data-colmgr-key="book-v1" data-colmgr-prefix="book">
            <thead>
                <tr>
                    <th data-col="period"><?= __('Periode') ?></th>
                    <th data-col="range" class="col-hide-tablet"><?= __('Rentang') ?></th>
                    <th data-col="status"><?= __('Status') ?></th>
                    <th data-col="closed" class="col-hide-tablet"><?= __('Ditutup') ?></th>
                    <th data-col="aksi" class="t-right"><?= __('Aksi') ?></th>
                </tr>
            </thead>
            <tbody>
            <?php if ($periods === []): ?>
                <tr><td colspan="5" class="text-center faint" style="padding:1.2rem;"><?= __('Belum ada periode.') ?></td></tr>
            <?php else: ?>
                <?php
                $activeId = (int) (($active['id'] ?? 0));
                $curBp = \App\Models\BookPeriod::current();
                $curViewId = (int) (($curBp['id'] ?? $activeId));
                ?>
                <?php foreach ($periods as $p): ?>
                <?php
                $closed = ($p['status'] ?? 'open') === 'closed';
                $isCurrentlyOpened = ((int) $p['id'] === $curViewId);
                ?>
                <tr<?= $isCurrentlyOpened ? ' style="background:var(--surface-2,#f8fafc);"' : '' ?>>
                    <td data-label="<?= __('Periode') ?>">
                        <div class="cell-title"><?= e($labelBulan((string) $p['audit_month'])) ?></div>
                        <?php if ($isCurrentlyOpened): ?>
                            <div class="cell-sub" style="color:var(--brand,#f0b429);font-weight:600;">
                                <?= icon('eye') ?> <?= $closed ? __('Sedang Dibuka (Read-Only)') : __('Sedang Dibuka (Aktif)') ?>
                            </div>
                        <?php elseif ((int) $p['is_active'] === 1): ?>
                            <div class="cell-sub"><?= __('Periode Berjalan') ?></div>
                        <?php endif; ?>
                    </td>
                    <td data-label="<?= __('Rentang') ?>" class="col-hide-tablet"><?= e($rentang((string) $p['audit_start'], (string) $p['audit_end'])) ?></td>
                    <td data-label="<?= __('Status') ?>"><span class="badge <?= $closed ? 'badge-danger' : 'badge-success' ?>"><?= $closed ? __('Ditutup') : __('Berjalan') ?></span></td>
                    <td data-label="<?= __('Ditutup') ?>" class="col-hide-tablet"><?= $closed && ! empty($p['closed_at']) ? e($tgl(substr((string) $p['closed_at'], 0, 10))) : '—' ?></td>
                    <td data-label="" class="t-right" style="white-space:nowrap;">
                        <div class="btn-group" style="justify-content:flex-end;gap:8px;">
                            <?php if ($isCurrentlyOpened && $closed): ?>
                                <a class="btn btn-outline btn-xs" href="<?= url('tahun-buku/kembali') ?>" style="min-height:36px;display:inline-flex;align-items:center;gap:.35rem;" title="<?= __('Tutup mode arsip dan kembali ke periode berjalan') ?>">
                                    <?= icon('corner-up-left') ?> Tutup / Kembali
                                </a>
                            <?php else: ?>
                                <form method="post" action="<?= url('tahun-buku/' . (int) $p['id'] . '/buka') ?>" style="display:inline-flex;margin:0;">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="btn <?= $isCurrentlyOpened ? 'btn-ghost' : 'btn-brand' ?> btn-xs" style="min-height:36px;display:inline-flex;align-items:center;gap:.35rem;" title="<?= __('Buka Tahun Buku ini di menu-menu yang sudah ada (Data STO, Audit, Rekonsiliasi) dalam mode read-only') ?>">
                                        <?= icon('folder-open') ?> <?= $isCurrentlyOpened ? __('Buka Menu') : __('Buka Tahun Buku') ?>
                                    </button>
                                </form>
                            <?php endif; ?>

                            <a class="btn btn-outline btn-xs" href="<?= url('tahun-buku/' . (int) $p['id']) ?>" style="min-height:36px;display:inline-flex;align-items:center;gap:.35rem;" title="<?= __('Lihat ringkasan selisih per group') ?>">
                                <?= icon('scale') ?> Selisih
                            </a>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
    </div>  <!-- /card-body -->
</div></section>
