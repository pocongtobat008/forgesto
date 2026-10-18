<?php
/**
 * Lembar kerja periode tutup buku (read-only).
 * Tiga tab: Data STO, Audit, Rekonsiliasi — semuanya dari period_snapshots.
 *
 * @var array $period
 * @var string $tab
 * @var string $q
 * @var int $groupId
 * @var array $rows
 * @var array $groups
 */
$period  = $period ?? [];
$tab     = $tab ?? 'sto';
$q       = $q ?? '';
$groupId = (int) ($groupId ?? 0);
$rows    = $rows ?? [];
$groups  = $groups ?? [];

$months = ['01'=>'Januari','02'=>'Februari','03'=>'Maret','04'=>'April','05'=>'Mei','06'=>'Juni',
           '07'=>'Juli','08'=>'Agustus','09'=>'September','10'=>'Oktober','11'=>'November','12'=>'Desember'];
$parts  = explode('-', (string) ($period['audit_month'] ?? ''));
$label  = trim(($months[$parts[1] ?? ''] ?? '') . ' ' . ($parts[0] ?? ''));

$__base = $_GET;
unset($__base['tab'], $__base['page']);
$__tabLink = static function (string $t) use ($__base, $period): string {
    $qq = $__base; $qq['tab'] = $t;
    return url('tahun-buku/' . (int) ($period['id'] ?? 0) . '/worksheet') . '?' . http_build_query($qq);
};

$isRecon = $tab === 'recon';
$isAudit = $tab === 'audit';
$isSto   = $tab === 'sto';
?>
<div class="page-head">
    <div>
        <h1>Lembar kerja <?= e($label) ?></h1>
        <p>Periode ditutup <?= e((string) ($period['closed_at'] ?? '-')) ?> &middot; data historis <strong><?= __('read-only') ?></strong> dari snapshot.</p>
    </div>
    <div class="spacer"></div>
    <div class="flex gap-sm items-center" style="flex-wrap:wrap;">
        <a class="btn btn-outline" href="<?= url('tahun-buku/' . (int) ($period['id'] ?? 0)) ?>" style="min-height:44px;"><?= icon('arrow-left') ?> Ringkasan selisih</a>
        <?php if (can('export_data')): ?>
            <a class="btn btn-primary" href="<?= url('tahun-buku/' . (int) ($period['id'] ?? 0) . '/export') ?>" style="min-height:44px;"><?= icon('download') ?> Export</a>
        <?php endif; ?>
    </div>
</div>

<div class="sf-alert sf-alert-info mb-3" style="display:flex;align-items:center;gap:.85rem;padding:.9rem 1.15rem;border-radius:8px;background:rgba(59,130,246,0.08);border:1px solid rgba(59,130,246,0.25);">
    <div style="font-size:1.4rem;line-height:1;color:var(--brand);"><?= icon('shield-check') ?></div>
    <div style="font-size:.875rem;line-height:1.45;">
        <strong>Mode Arsip Historis (Read-Only) &mdash; Periode <?= e($label) ?></strong>
        <p class="muted" style="margin:.15rem 0 0;font-size:.825rem;">
            Seluruh data di menu <strong>Data STO</strong>, <strong>Audit</strong>, dan <strong>Rekonsiliasi</strong> di bawah ini adalah data historis tahun lalu / periode <?= e($label) ?> yang terkunci dalam mode baca saja.
        </p>
    </div>
</div>

<div class="sf-tabs audit-tabs" role="tablist" aria-label="<?= __('Lembar kerja periode') ?>">
    <a class="tab-pill<?= $isSto ? ' is-active' : '' ?>" role="tab" aria-selected="<?= $isSto ? 'true' : 'false' ?>" href="<?= e($__tabLink('sto')) ?>">
        <?= icon('package') ?> Data STO <span class="tab-count"><?= (int) ($tabCounts['sto'] ?? 0) ?></span>
    </a>
    <a class="tab-pill<?= $isAudit ? ' is-active' : '' ?>" role="tab" aria-selected="<?= $isAudit ? 'true' : 'false' ?>" href="<?= e($__tabLink('audit')) ?>">
        <?= icon('clipboard-check') ?> Audit <span class="tab-count"><?= (int) ($tabCounts['audit'] ?? 0) ?></span>
    </a>
    <a class="tab-pill<?= $isRecon ? ' is-active' : '' ?>" role="tab" aria-selected="<?= $isRecon ? 'true' : 'false' ?>" href="<?= e($__tabLink('recon')) ?>">
        <?= icon('scale') ?> Rekonsiliasi <span class="tab-count"><?= (int) ($tabCounts['recon'] ?? 0) ?></span>
    </a>
</div>

<section class="card">
    <div class="card-header">
        <h2>
            <?php if ($isSto): ?><?= icon('package') ?> Data STO periode ini
            <?php elseif ($isAudit): ?><?= icon('clipboard-check') ?> Audit periode ini
            <?php else: ?><?= icon('scale') ?> Rekonsiliasi (ada selisih)<?php endif; ?>
        </h2>
        <div class="spacer"></div>
        <span class="badge badge-info"><?= count($rows) ?> baris</span>
        <span class="badge"><?= __('read-only') ?></span>
    </div>

    <p class="text-sm muted" style="padding:0 1.1rem .6rem;margin:0;">
        <?php if ($isAudit): ?>
            Menampilkan item yang <strong><?= __('sudah diposting observer') ?></strong> pada periode ini (siap diaudit).
        <?php elseif ($isRecon): ?>
            Menampilkan item yang memiliki <strong><?= __('selisih') ?></strong> (selisih ≠ 0) pada saat periode ditutup.
        <?php else: ?>
            Seluruh isi snapshot periode ini, sama seperti tampilan Data STO.
        <?php endif; ?>
    </p>

    <form class="memo-search" method="get" action="<?= url('tahun-buku/' . (int) ($period['id'] ?? 0) . '/worksheet') ?>">
        <input type="hidden" name="tab" value="<?= e($tab) ?>">
        <div class="memo-search-field">
            <?= icon('search') ?>
            <input type="search" name="q" value="<?= e($q) ?>" autocomplete="off" placeholder="<?= __('Cari kode atau nama item...') ?>" aria-label="<?= __('Cari item periode') ?>">
        </div>
        <select name="group_id" class="btn" aria-label="<?= __('Group') ?>" style="min-height:44px;max-width:220px;">
            <option value="0"><?= __('Semua group') ?></option>
            <?php foreach ($groups as $g): ?>
                <option value="<?= (int) $g['id'] ?>" <?= $groupId === (int) $g['id'] ? 'selected' : '' ?>><?= e($g['name']) ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="btn btn-brand" style="min-height:44px;"><?= icon('filter') ?> Terapkan</button>
        <?php if ($q !== '' || $groupId > 0): ?>
            <a class="btn btn-ghost" style="min-height:44px;" href="<?= e($__tabLink($tab)) ?>"><?= icon('x') ?> Hapus</a>
        <?php endif; ?>
    </form>

    <div class="table-wrap">
        <table class="data"><caption class="sr-only">Lembar kerja hitung fisik</caption>
            <thead>
                <tr>
                    <th><?= __('Kode') ?></th>
                    <th class="col-hide-tablet"><?= __('Nama item') ?></th>
                    <th class="col-hide-tablet"><?= __('Group') ?></th>
                    <th class="t-center"><?= __('Sistem') ?></th>
                    <th class="t-center"><?= __('Hitung') ?></th>
                    <th class="t-center"><?= __('Selisih') ?></th>
                    <th class="col-hide-tablet t-right"><?= __('Nilai selisih') ?></th>
                    <th class="t-center"><?= __('Status') ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if ($rows === []): ?>
                    <tr><td colspan="8">
                        <div class="empty-state">
                            <div class="empty-icon"><?= icon('archive') ?></div>
                            <h3><?= __('Tidak ada data') ?></h3>
                            <p><?= $q !== '' ? 'Tidak ada baris cocok dengan pencarian.' : 'Snapshot periode ini kosong untuk kelompok ini.' ?></p>
                        </div>
                    </td></tr>
                <?php else: ?>
                    <?php foreach ($rows as $r): ?>
                        <?php
                        $diff = (int) ($r['difference'] ?? 0);
                        $cls  = $diff > 0 ? 'is-plus' : ($diff < 0 ? 'is-minus' : 'is-match');
                        $amt  = (float) ($r['diff_amount'] ?? 0);
                        ?>
                        <tr>
                            <td data-label="<?= __('Kode') ?>"><span class="mono"><?= e((string) $r['item_code']) ?></span></td>
                            <td data-label="<?= __('Nama item') ?>" class="cell-title"><?= e((string) $r['item_name']) ?></td>
                            <td data-label="<?= __('Group') ?>" class="col-hide-tablet"><?= e((string) $r['group_name']) ?></td>
                            <td data-label="<?= __('Sistem') ?>" class="t-center mono tabular"><?= can_see_stock_qty() ? (int) $r['expected_qty'] : '***' ?></td>
                            <td data-label="<?= __('Hitung') ?>" class="t-center mono tabular"><?= $r['counted_qty'] === null ? '—' : (int) $r['counted_qty'] ?></td>
                            <td data-label="<?= __('Selisih') ?>" class="t-center mono tabular"><span class="diff-pill <?= $cls ?>"><?= $diff > 0 ? '+' : '' ?><?= $diff ?></span></td>
                            <td data-label="<?= __('Nilai selisih') ?>" class="col-hide-tablet t-right mono tabular">Rp <?= number_format($amt, 0, ',', '.') ?></td>
                            <td data-label="<?= __('Status') ?>" class="t-center">
                                <span class="badge <?= (string) $r['audit_status'] === 'audited' ? 'badge-success' : ((string) $r['audit_status'] === 'discrepancy' ? 'badge-danger' : 'badge-warning') ?>">
                                    <?= e(['pending' => 'Belum', 'audited' => 'Sesuai', 'discrepancy' => 'Selisih', 'resolved' => 'Selesai'][(string) $r['audit_status']] ?? (string) $r['audit_status']) ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <p class="text-sm muted" style="padding:.7rem 1.1rem 0;margin:0;">Ditampilkan maksimal 2.000 baris. Gunakan pencarian / filter group untuk mempersempit.</p>
</section>