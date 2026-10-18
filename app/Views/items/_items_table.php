<?php
// Shared table markup for all item views
?>
<div class="xf-pop" id="xf-pop" role="dialog" aria-modal="false" hidden>
    <div class="xf-pop-head"><strong id="xf-title"><?= __('Filter') ?></strong></div>
    <input type="search" id="xf-q" class="xf-q" placeholder="<?= __('Cari di daftar...') ?>" autocomplete="off">
    <div class="xf-sort" role="group" aria-label="<?= __('Urutkan') ?>"><button type="button" class="xf-sort-btn" data-dir="asc" id="xf-sort-asc"><?= __('Urutkan naik') ?></button><button type="button" class="xf-sort-btn" data-dir="desc" id="xf-sort-desc"><?= __('Urutkan turun') ?></button></div>
    <div class="xf-list" id="xf-list" role="listbox" aria-multiselectable="true"><div class="xf-loading"><?= __('Memuat...') ?></div></div>
    <div class="xf-foot">
        <span class="spacer"></span>
        <button type="button" class="btn btn-outline btn-sm" id="xf-cancel"><?= __('Batal') ?></button>
        <button type="button" class="btn btn-brand btn-sm" id="xf-apply"><?= __('Terapkan') ?></button>
    </div>
</div>

<div class="table-wrap">
    <table class="data" id="items-table" data-colmgr-key="items-v1" data-colmgr-prefix="items"><caption class="sr-only">Daftar barang</caption>
        <thead>
            <tr>
                <th style="width:40px"><span class="sr-only"><?= __('Pilih') ?></span></th>
                <th style="width:140px" class="t-center"><?= __('Aksi') ?></th>
                <th style="width:160px" class="t-center"><?= __('Status Hitung') ?></th>
                <?php
                $XF_COLS = [
                  ['code','Kode',''], ['name','Nama',''], ['group','Group',''], ['location','Lokasi',''],
                  ['device','Device',' col-hide-tablet'], 
                  ['counted','Hitung',' col-hide-tablet t-center'], ['unit','Satuan',' col-hide-tablet t-center'],
                  ['category','Kategori',' col-hide-tablet'], ['price','Harga',' col-hide-tablet t-right'],
                  ['audit','Status Audit',' t-center'],
                ];
                $sortKey = $filters['sort']['key'] ?? null; $sortDir = $filters['sort']['dir'] ?? null;
                $colActive = function (string $k) use ($filters): bool {
                    foreach (($filters['col'] ?? []) as $ck => $vals) { if ($ck === $k && $vals) return true; }
                    return false;
                };
                foreach ($XF_COLS as [$k, $lbl, $cls]):
                    $on = $colActive($k) || $sortKey === $k;
                    $arrow = $sortKey === $k ? ($sortDir === 'DESC' ? ' &#9662;' : ' &#9652;') : '';
                ?>
                <th class="xf-th<?= $cls ?>" data-col="<?= $k ?>"><span class="xf-label"><?= e(__($lbl)) ?><?= $arrow ?></span><button type="button" class="xf-btn<?= $on ? ' is-active' : '' ?>" data-xf="<?= $k ?>" aria-label="<?= e(__( 'Filter &amp; urutkan kolom :l', ['l' => __($lbl)])) ?>" aria-haspopup="dialog" title="<?= __('Filter &amp; urutkan') ?>"><?= icon('filter') ?></button></th>
                <?php endforeach; ?>
            </tr>
        </thead>
        <tbody>
            <?php if ($items['items'] === []): ?>
                <tr>
                    <td colspan="12">
                        <div class="empty-state">
                            <div class="empty-icon"><?= icon('package') ?></div>
                            <h3><?= __('Tidak ada data') ?></h3>
                            <p>Sesuaikan filter atau tambahkan data baru / import dari Excel.</p>
                        </div>
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($items['items'] as $item): ?>
                    <?php
                    $isLocked = (int) $item['is_locked'] === 1;
                    $canCount = can('count_data') && (! $isLocked || can('unlock_data'));
                    ?>
                    <tr data-item-id="<?= (int) $item['id'] ?>">
                        <td class="memo-pick"><input type="checkbox" class="memo-check" value="<?= (int) $item['id'] ?>" aria-label="Pilih item <?= e($item['code']) ?>"></td>
                        <td data-label="<?= __('Aksi') ?>" class="t-center">
                            <?php if ($canCount): ?>
                                <form method="post" action="<?= url('items/' . (int) $item['id'] . '/count') ?>" class="flex gap-sm items-center" style="justify-content:center;">
                                    <?= csrf_field() ?>
                                    <input type="number" name="counted_qty" min="0" value="<?= $item['counted_qty'] === null ? '' : (int) $item['counted_qty'] ?>" placeholder="0"
                                           style="width:84px;padding:.3rem .5rem;font-size:.8rem;" aria-label="<?= __('Jumlah hitung') ?>" required>
                                    <button type="submit" class="btn btn-outline btn-xs" aria-label="<?= __('Simpan hitung') ?>"><?= icon('check') ?></button>
                                </form>
                            <?php else: ?>
                                <a class="btn btn-outline btn-xs" href="<?= url('items/' . (int) $item['id']) ?>"><?= icon('eye') ?> Detail</a>
                            <?php endif; ?>
                        </td>
                        <td data-label="<?= __('Status Hitung') ?>" class="t-center">
                            <span class="badge <?= $countBadge((string) $item['count_status']) ?>"><?= e($countLabel((string) $item['count_status'])) ?></span>
                        </td>
                        <td data-label="<?= __('Kode') ?>">
                            <div class="code-cell">
                                <span class="kbd"><?= e($item['code']) ?></span>
                                <?php if ($isLocked): ?>
                                    <span class="lock-pill" title="<?= __('Data terkunci - tidak bisa diubah tanpa permission unlock') ?>">
                                        <?= icon('shield-lock') ?><span class="lock-pill-text"><?= __('Terkunci') ?></span>
                                    </span>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td data-label="<?= __('Nama') ?>">
                            <div class="cell-title"><a href="<?= url('items/' . (int) $item['id']) ?>"><?= e($item['name']) ?></a></div>
                            <div class="cell-sub"><?= e($item['category'] ?: 'Tanpa kategori') ?><?= $item['unit'] ? ' · ' . e($item['unit']) : '' ?></div>
                        </td>
                        <td data-label="<?= __('Group') ?>"><?= e($item['group_name']) ?></td>
                        <td data-label="<?= __('Lokasi') ?>"><?= e($item['location_name'] ?: '-') ?></td>
                        <td data-label="<?= __('Device') ?>" class="col-hide-tablet"><?= e($item['device_name'] ?: '-') ?></td>

                        <td data-label="<?= __('Hitung') ?>" class="col-hide-tablet t-center mono tabular"><?= $item['counted_qty'] === null ? '—' : (int) $item['counted_qty'] ?></td>
                        <td data-label="<?= __('Satuan') ?>" class="col-hide-tablet t-center"><?= e($item['unit'] ?: '-') ?></td>
                        <td data-label="<?= __('Kategori') ?>" class="col-hide-tablet"><?= e($item['category'] ?: '-') ?></td>
                        <td data-label="<?= __('Harga') ?>" class="col-hide-tablet t-right mono tabular"><?= $item['unit_price'] === null ? '-' : 'Rp ' . number_format((float) $item['unit_price'], 0, ',', '.') ?></td>
                        <td data-label="<?= __('Status Audit') ?>" class="t-center">
                            <span class="badge <?= $auditBadge((string) $item['audit_status']) ?>"><?= e($auditLabel((string) $item['audit_status'])) ?></span>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>
