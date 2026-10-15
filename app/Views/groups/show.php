<?php
/**
 * @var array $group
 * @var array $stats
 * @var array $observers
 * @var array $auditors
 * @var array $tablets
 * @var array $locations
 * @var array $pendingItems
 * @var bool  $canSeeStockQty
 */
$pct = $stats['items'] > 0 ? (int) round($stats['counted'] * 100 / $stats['items']) : 0;
?>
<div class="page-head">
    <div>
        <h1><?= e($group['name']) ?></h1>
        <p><span class="mono"><?= e($group['code']) ?></span> &middot; <?= e($group['warehouse_name'] ?? '-') ?><?= ! empty($group['warehouse_city']) ? ' &middot; ' . e($group['warehouse_city']) : '' ?></p>
    </div>
    <div class="spacer"></div>
    <a class="btn btn-brand" href="<?= url('groups/' . (int) $group['id'] . '/berita-acara') ?>"><?= icon('download') ?> Berita Acara</a> <a class="btn btn-outline" href="<?= url('groups') ?>"><?= icon('arrow-left') ?> Kembali</a>
</div>

<div class="stat-grid">
    <div class="stat"><span class="stat-label"><?= __('Total barang') ?></span><strong class="stat-value"><?= (int) $stats['items'] ?></strong></div>
    <div class="stat"><span class="stat-label"><?= __('Sudah dihitung') ?></span><strong class="stat-value"><?= (int) $stats['counted'] ?> <small>(<?= $pct ?>%)</small></strong></div>
    <div class="stat"><span class="stat-label"><?= __('Masih pending') ?></span><strong class="stat-value"><?= (int) $stats['pending'] ?></strong></div>
    <div class="stat"><span class="stat-label"><?= __('Notifikasi recheck') ?></span><strong class="stat-value"><?= (int) $stats['recheck'] ?></strong></div>
    <?php if ($canSeeStockQty): ?>
    <div class="stat"><span class="stat-label"><?= __('Total qty sistem') ?></span><strong class="stat-value"><?= (int) $stats['stock'] ?></strong></div>
    <?php endif; ?>
</div>

<div class="card">
    <div class="card-header"><h2><?= __('Informasi group') ?></h2></div>
    <div class="card-body">
        <dl class="kv-grid">
            <div><dt><?= __('Kode group') ?></dt><dd class="mono"><?= e($group['code']) ?></dd></div>
            <div><dt><?= __('Nama group') ?></dt><dd><?= e($group['name']) ?></dd></div>
            <div><dt><?= __('Gudang / Situs') ?></dt><dd><?= e($group['warehouse_name'] ?? '-') ?></dd></div>
            <div><dt><?= __('STO') ?></dt><dd><?= e($group['sto_name'] ?? '-') ?></dd></div>
            <div><dt><?= __('Jumlah rak / lokasi') ?></dt><dd><?= (int) $stats['locations'] ?></dd></div>
            <div><dt><?= __('Status') ?></dt><dd><span class="badge <?= (int) $group['is_active'] === 1 ? 'badge-success' : 'badge-danger' ?>"><?= (int) $group['is_active'] === 1 ? __('Aktif') : __('Nonaktif') ?></span></dd></div>
        </dl>
    </div>
</div>

<div class="card">
    <div class="card-header"><h2><?= __('Petugas') ?></h2></div>
    <div class="card-body">
        <h3 class="text-sm"><?= __('Observer') ?></h3>
        <?php if ($observers === []): ?><p class="faint text-sm"><?= __('Belum ada observer.') ?></p><?php endif; ?>
        <ul class="list-reset">
            <?php foreach ($observers as $u): ?>
                <li class="list-item"><span class="li-body"><span class="li-title"><?= e($u['name']) ?></span><span class="li-meta mono"><?= e($u['username']) ?> &middot; <?= e($u['phone'] ?: 'No. telp belum diisi') ?></span></span></li>
            <?php endforeach; ?>
        </ul>
        <h3 class="text-sm"><?= __('Auditor') ?></h3>
        <?php if ($auditors === []): ?><p class="faint text-sm"><?= __('Belum ada auditor.') ?></p><?php endif; ?>
        <ul class="list-reset">
            <?php foreach ($auditors as $u): ?>
                <li class="list-item"><span class="li-body"><span class="li-title"><?= e($u['name']) ?></span><span class="li-meta mono"><?= e($u['username']) ?> &middot; <?= e($u['phone'] ?: 'No. telp belum diisi') ?></span></span></li>
            <?php endforeach; ?>
        </ul>
    </div>
</div>

<div class="card">
    <div class="card-header"><h2><?= __('Tablet yang dipakai') ?></h2></div>
    <div class="card-body">
        <?php if ($tablets === []): ?><p class="faint text-sm"><?= __('Belum ada tablet terpasang di group ini.') ?></p><?php endif; ?>
        <ul class="list-reset">
            <?php foreach ($tablets as $t): ?>
                <li class="list-item"><span class="li-body"><span class="li-title"><?= e($t['name']) ?></span><span class="li-meta mono"><?= e($t['code']) ?> &middot; SN <?= e($t['serial_number'] ?: '-') ?> &middot; <?= e($t['location_name']) ?></span></span></li>
            <?php endforeach; ?>
        </ul>
    </div>
</div>

<div class="card">
    <div class="card-header"><h2>Barang masih pending (<?= (int) $stats['pending'] ?>)</h2></div>
    <div class="card-body">
        <?php if ($pendingItems === []): ?><p class="faint text-sm"><?= __('Semua barang sudah dihitung.') ?></p><?php endif; ?>
        <ul class="list-reset">
            <?php foreach ($pendingItems as $it): ?>
                <li class="list-item"><span class="li-body"><span class="li-title mono"><?= e($it['code']) ?></span><span class="li-meta"><?= e($it['name']) ?> &middot; <?= e($it['location_name'] ?? '-') ?></span></span></li>
            <?php endforeach; ?>
        </ul>
    </div>
</div>
