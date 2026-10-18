<?php
/**
 * @var array $discrepancies
 * @var array $filters
 * @var array $groups
 * @var array $statuses
 * @var array $advCols
 * @var array $advNumeric
 * @var int   $advMax
 */
use App\Core\View;

$statusBadge = static fn (string $status): string => match ($status) {
    'open'      => 'badge-danger',
    'searching' => 'badge-warning',
    'resolved'  => 'badge-success',
    default     => '',
};
$statusLabel = static fn (string $status): string => match ($status) {
    'open'      => 'Terbuka',
    'searching' => 'Dicari',
    'resolved'  => 'Selesai',
    default     => $status,
};
$openCount = 0;
foreach ($discrepancies['items'] as $r) { if (($r['status'] ?? '') !== 'resolved') { $openCount++; } }
$__disHasFilter = ! empty($filters['search']) || ! empty($filters['col']) || ! empty($filters['adv']) || ! empty($filters['group_id']) || ! empty($filters['status']);
?>
<?= page_hero() ?>
<div class="page-head has-hero">
    <div>
        <nav class="breadcrumb" aria-label="<?= __('Navigasi') ?>">
            <a href="<?= url('dashboard') ?>"><?= __('Beranda') ?></a>
            <span class="sep">/</span>
            <span><?= __('Operasional') ?></span>
            <span class="sep">/</span>
            <span aria-current="page"><?= __('Selisih') ?></span>
        </nav>
        <h1><?= __('Selisih') ?></h1>
        <p><?= __('Data yang memiliki perbedaan antara qty sistem, hasil hitung, dan hasil audit.') ?></p>
    </div>
    <div class="spacer"></div>
    <span class="badge <?= $openCount > 0 ? 'badge-danger' : 'badge-success' ?>">
        <?= $openCount > 0 ? '• ' . $openCount . ' perlu ditangani' : 'Semua beres' ?>
    </span>
</div>

<div class="card">
    <div class="card-toolbar" style="display:flex;align-items:center;justify-content:space-between;gap:.75rem;flex-wrap:wrap;padding:.85rem 1.15rem;border-bottom:1px solid var(--border);">
        <form method="get" action="<?= url('discrepancies') ?>" id="dis-filter" style="display:flex;align-items:center;flex:1;min-width:240px;gap:.65rem;margin:0;" autocomplete="off">
            <?php foreach (($filters['col'] ?? []) as $ck => $cvals): ?>
                <?php foreach ((array) $cvals as $cv): ?>
                    <input type="hidden" name="col[<?= e($ck) ?>][]" value="<?= e($cv) ?>" class="col-hidden">
                <?php endforeach; ?>
            <?php endforeach; ?>
            <div class="form-group is-search" style="flex:1;max-width:380px;margin:0;">
                <input type="search" id="dis-search" name="search" value="<?= e($filters['search'] ?? '') ?>"
                       placeholder="<?= __('Kode item, nama item, group, atau catatan...') ?>"
                       data-filter-live autocomplete="off" enterkeyhint="search" style="width:100%;min-height:38px;">
            </div>
            <span class="sf-filter-count" aria-live="polite"></span>
        </form>

        <div class="table-toolbar" style="margin-left:auto;display:flex;gap:.55rem;align-items:center;">
            <button type="button" class="btn btn-info btn-icon-only" id="discrepancies-adv-btn" aria-label="<?= __('Buka pencarian lanjutan') ?>" title="<?= __('Pencarian lanjutan') ?>"><?= icon('filter') ?></button>
            <?php if ($__disHasFilter): ?>
                <a class="btn btn-ghost btn-icon-only col-clear-all" href="<?= url('discrepancies') ?>" aria-label="<?= __('Hapus semua filter') ?>" title="<?= __('Hapus semua filter') ?>"><?= icon('x') ?></a>
            <?php endif; ?>
            <div class="col-mgr" id="discrepancies-colmgr">
                <button type="button" class="btn btn-ghost btn-icon-only" id="discrepancies-colmgr-btn" aria-expanded="false" aria-label="<?= __('Sesuaikan kolom') ?>" title="<?= __('Sesuaikan kolom') ?>"><?= icon('layers') ?></button>
                <div class="col-mgr-panel" id="discrepancies-colmgr-panel" hidden></div>
            </div>
        </div>
    </div>

    <?php
    /* LF-BAR: satu pencarian lanjutan (pola Data STO) */
    $__adv = $filters['adv'] ?? [];
    $asForm    = 'dis-filter';
    $asCols    = $advCols ?? [];
    $asCurrent = $__adv;
    $asNumeric = $advNumeric ?? [];
    $asMax     = $advMax ?? 12;
    $asToggleId = 'discrepancies-adv-btn';
    require __DIR__ . '/../partials/advanced-search.php';
    ?>

    <div class="table-wrap">
        <table class="data" id="discrepancies-table" data-colmgr-key="discrepancies-v1" data-colmgr-prefix="discrepancies"><caption class="sr-only">Daftar selisih stok</caption>
            <thead>
                <tr>
<?php $cfCol = $filters['col'] ?? []; $xfBtn = static fn (string $k, string $lbl): string => '<button type="button" class="xf-btn' . (! empty($cfCol[$k]) ? ' is-active' : '') . '" data-xf="' . $k . '" aria-label="' . e(__('Filter kolom :l', ['l' => __($lbl)])) . '" aria-haspopup="dialog" title="Filter">' . icon('filter') . '</button>'; ?>
                    <th data-col="item" class="xf-th"><span class="xf-label"><?= __('Item') ?></span><?= $xfBtn('item', 'Item') ?></th>
                    <th data-col="group" class="xf-th"><span class="xf-label"><?= __('Group') ?></span><?= $xfBtn('group', 'Group') ?></th>
                    <?php if (can_see_stock_qty()): ?>
                    <th data-col="expected" class="col-hide-tablet t-center xf-th"><span class="xf-label"><?= __('Sistem') ?></span><?= $xfBtn('expected', 'Sistem') ?></th>
                    <?php endif; ?>
                    <th data-col="counted" class="col-hide-tablet t-center xf-th"><span class="xf-label"><?= __('Hitung') ?></span><?= $xfBtn('counted', 'Hitung') ?></th>
                    <th data-col="audited" class="t-center xf-th"><span class="xf-label"><?= __('Audit') ?></span><?= $xfBtn('audited', 'Audit') ?></th>
                    <?php if (can_see_stock_qty()): ?>
                    <th data-col="difference" class="t-center xf-th"><span class="xf-label"><?= __('Selisih') ?></span><?= $xfBtn('difference', 'Selisih') ?></th>
                    <?php endif; ?>
                    <th data-col="status" class="col-hide-tablet t-center xf-th"><span class="xf-label"><?= __('Status') ?></span><?= $xfBtn('status', 'Status') ?></th>
                    <th data-col="reported" class="xf-th"><span class="xf-label"><?= __('Dilaporkan') ?></span><?= $xfBtn('reported', 'Dilaporkan') ?></th>
                    <th data-col="aksi" class="t-right"><?= __('Aksi') ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if ($discrepancies['items'] === []): ?>
                    <tr>
                        <td colspan="<?= can_see_stock_qty() ? 9 : 7 ?>">
                            <div class="empty-state">
                                <div class="empty-icon"><?= icon('check') ?></div>
                                <h3><?= __('Tidak ada selisih') ?></h3>
                                <p><?= __('Semua data sesuai antara sistem, hasil hitung, dan hasil audit.') ?></p>
                            </div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($discrepancies['items'] as $row): ?>
                        <?php
                        $d = (int) $row['difference'];
                        $dClass = $d === 0 ? 'is-zero' : ($d > 0 ? 'is-pos' : 'is-neg');
                        $isOpen = ($row['status'] ?? '') !== 'resolved';
                        $canSearch  = empty($isReadOnly) && can('search_discrepancy') && $isOpen;
                        $canResolve = empty($isReadOnly) && can('manage_discrepancy') && $isOpen;
                        ?>
                        <tr data-diff="<?= can_see_stock_qty() && $row['difference'] !== null ? $d : '' ?>">
                            <td data-col="item" data-label="<?= __('Item') ?>">
                                <div class="cell-title"><a href="<?= url('items/' . (int) $row['item_id']) ?>"><?= e($row['item_code']) ?></a></div>
                                <div class="cell-sub"><?= e($row['item_name']) ?></div>
                            </td>
                            <td data-col="group" data-label="<?= __('Group') ?>"><?= e($row['group_name']) ?></td>
                            <?php if (can_see_stock_qty()): ?>
                            <td data-col="expected" data-label="<?= __('Sistem') ?>" class="col-hide-tablet t-center mono tabular"><?= (int) $row['expected_qty'] ?></td>
                            <?php endif; ?>
                            <td data-col="counted" data-label="<?= __('Hitung') ?>" class="col-hide-tablet t-center mono tabular"><?= (int) $row['counted_qty'] ?></td>
                            <td data-col="audited" data-label="<?= __('Audit') ?>" class="t-center mono tabular"><?= $row['audited_qty'] === null ? '—' : (int) $row['audited_qty'] ?></td>
                            <?php if (can_see_stock_qty()): ?>
                            <td data-col="difference" data-label="<?= __('Selisih') ?>" class="t-center">
                                <span class="diff-pill <?= $row['difference'] !== null ? $dClass : 'is-neg' ?>">
                                    <?= $row['difference'] !== null ? (($d > 0 ? '+' : '') . $d) : '—' ?>
                                </span>
                            </td>
                            <?php endif; ?>
                            <td data-col="status" data-label="<?= __('Status') ?>" class="col-hide-tablet t-center"><span class="badge <?= $statusBadge((string) $row['status']) ?>"><?= e($statusLabel((string) $row['status'])) ?></span></td>
                            <td data-col="reported" data-label="<?= __('Dilaporkan') ?>" class="text-sm faint nowrap">
                                <?= e($row['created_by_name'] ?: '-') ?><br>
                                <?= e(format_date($row['created_at'], 'd M H:i')) ?>
                            </td>
                            <td data-col="aksi" data-label="" class="t-right">
                                <div class="row-actions" style="justify-content:flex-end;">
                                    <?php if ($canSearch): ?>
                                        <button type="button" class="btn btn-outline btn-xs js-disc-action"
                                                data-kind="search"
                                                data-action="<?= url('discrepancies/' . (int) $row['id'] . '/search') ?>"
                                                data-code="<?= e($row['item_code']) ?>"
                                                data-name="<?= e($row['item_name']) ?>"
                                                data-group="<?= e($row['group_name']) ?>"
                                                data-counted="<?= (int) $row['counted_qty'] ?>"
                                                <?= can_see_stock_qty() ? 'data-expected="' . (int) $row['expected_qty'] . '"' : '' ?>
                                                data-audited="<?= $row['audited_qty'] === null ? '' : (int) $row['audited_qty'] ?>"
                                                data-diff="<?= can_see_stock_qty() && $row['difference'] !== null ? (int) $row['difference'] : '' ?>">
                                            <?= icon('search') ?> <?= __('Cari') ?>
                                        </button>
                                    <?php endif; ?>
                                    <?php if ($canResolve): ?>
                                        <button type="button" class="btn btn-outline btn-xs js-disc-action"
                                                data-kind="resolve"
                                                data-action="<?= url('discrepancies/' . (int) $row['id'] . '/resolve') ?>"
                                                data-code="<?= e($row['item_code']) ?>"
                                                data-name="<?= e($row['item_name']) ?>"
                                                data-group="<?= e($row['group_name']) ?>"
                                                data-counted="<?= (int) $row['counted_qty'] ?>"
                                                <?= can_see_stock_qty() ? 'data-expected="' . (int) $row['expected_qty'] . '"' : '' ?>
                                                data-audited="<?= $row['audited_qty'] === null ? '' : (int) $row['audited_qty'] ?>"
                                                data-diff="<?= can_see_stock_qty() && $row['difference'] !== null ? (int) $row['difference'] : '' ?>">
                                            <?= icon('send') ?> <?= __('Lapor') ?>
                                        </button>
                                    <?php endif; ?>
                                    <a class="btn btn-outline btn-xs" href="<?= url('discrepancies/' . (int) $row['id']) ?>" title="<?= __('Detail selisih') ?>" aria-label="Detail <?= e($row['item_code']) ?>"><?= icon('eye') ?></a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?= View::partial('partials/pagination', ['paginator' => $discrepancies, 'query' => array_filter(['group_id' => $filters['group_id'] ?? '', 'status' => $filters['status'] ?? '', 'search' => $filters['search'] ?? ''], static fn ($v) => $v !== '' && $v !== null)]) ?>
</div>

<script src="<?= asset_v('js/table-colmgr.js') ?>" defer></script>

<!-- ====================================================================== -->
<!-- Modal inline: Cari / Selesaikan selisih (tanpa lompat halaman)         -->
<!-- ====================================================================== -->
<div class="sf-modal-backdrop" id="disc-modal" role="dialog" aria-modal="true" aria-labelledby="disc-modal-title">
    <div class="sf-modal">
        <div class="sf-modal-head">
            <h3 id="disc-modal-title"><?= __('Selisih') ?></h3>
            <button type="button" class="sf-modal-x" data-sf-close aria-label="<?= __('Tutup') ?>">&times;</button>
        </div>
        <form method="post" action="<?= url('discrepancies/0/search') ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="kind" id="disc-modal-kind" value="search">
            <div class="sf-modal-body">
                <div class="sf-ctx"><span>Item: <strong id="disc-modal-item">—</strong></span><span id="disc-modal-ctx">—</span></div>

                <div class="sf-compare" id="disc-modal-compare" hidden>
                    <?php if (can_see_stock_qty()): ?>
                    <div><div class="k"><?= __('Qty Sistem') ?></div><div class="v" id="disc-modal-expected">—</div></div>
                    <?php endif; ?>
                    <div><div class="k"><?= __('Hasil Hitung') ?></div><div class="v" id="disc-modal-counted">—</div></div>
                </div>
                <div class="sf-delta-chip" id="disc-modal-delta" hidden>—</div>

                <div class="disc-field-search">
                    <div class="form-group mb-md">
                        <label for="disc-modal-qty" class="font-semibold text-xs uppercase tracking-wider">Hasil Hitung Terbaru <span class="text-danger">*</span></label>
                        <input type="number" id="disc-modal-qty" name="counted_qty" min="0" inputmode="numeric"
                               style="font-size:1.2rem;font-weight:700;padding:.6rem .8rem;font-family:var(--font-mono);">
                    </div>
                    <div class="form-group">
                        <label for="disc-modal-note" class="font-semibold text-xs uppercase tracking-wider"><?= __('Catatan Pencarian') ?></label>
                        <textarea id="disc-modal-note" name="note" rows="2" maxlength="255" placeholder="<?= __('Contoh: barang ditemukan di rak lain...') ?>"></textarea>
                    </div>
                </div>

                <div class="disc-field-resolve" hidden>
                    <div class="form-group mb-md">
                        <label for="disc-modal-resolve-qty" class="font-semibold text-xs uppercase tracking-wider">Revisi Hasil Hitung (Qty Fisik Terbaru)</label>
                        <input type="number" id="disc-modal-resolve-qty" name="counted_qty" min="0" inputmode="numeric"
                               placeholder="<?= __('Kosongkan jika tetap') ?>"
                               style="font-size:1.2rem;font-weight:700;padding:.6rem .8rem;font-family:var(--font-mono);">
                        <p class="text-xs muted mt-1">Shortcut revisi: bila diisi, otomatis memperbarui hasil hitung fisik tanpa perlu recount ulang.</p>
                    </div>
                    <div class="form-group">
                        <label for="disc-modal-resolution" class="font-semibold text-xs uppercase tracking-wider">Catatan Penyelesaian <span class="text-danger">*</span></label>
                        <textarea id="disc-modal-resolution" name="resolution_note" rows="3" maxlength="255" placeholder="<?= __('Contoh: selisih disetujui, stok buku disesuaikan...') ?>"></textarea>
                    </div>
                </div>
            </div>
            <div class="sf-modal-foot">
                <button type="button" class="btn btn-outline btn-sm" data-sf-close><?= __('Batal') ?></button>
                <button type="submit" class="btn btn-brand btn-sm" id="disc-modal-submit"><?= icon('check') ?> Simpan</button>
            </div>
        </form>
    </div>
</div>

<script>
/* Guard: hanya kirim field yang relevan supaya validasi server tidak tertolak.
   (search butuh counted_qty; resolve butuh resolution_note + opsional counted_qty) */
(function () {
    var m = document.getElementById('disc-modal');
    if (!m) return;
    var qtySearch = document.getElementById('disc-modal-qty');
    var qtyResolve = document.getElementById('disc-modal-resolve-qty');
    var res = document.getElementById('disc-modal-resolution');
    m.querySelector('form').addEventListener('submit', function () {
        var kind = document.getElementById('disc-modal-kind').value;
        if (kind === 'resolve') {
            if (qtySearch) qtySearch.disabled = true;
            if (qtyResolve && qtyResolve.value.trim() === '') qtyResolve.disabled = true;
        } else {
            if (qtyResolve) qtyResolve.disabled = true;
            if (res) res.disabled = true;
        }
    });
})();
</script>
<?php
$cfLabels = ['item' => __('Item'), 'group' => __('Group'), 'status' => __('Status'), 'expected' => __('Sistem'), 'counted' => __('Hitung'), 'audited' => __('Audit'), 'difference' => __('Selisih'), 'reported' => __('Dilaporkan')];
if (! can_see_stock_qty()) { unset($cfLabels['expected'], $cfLabels['difference']); }
$cfDisplay = ['open' => 'Terbuka', 'searching' => 'Dicari', 'resolved' => 'Selesai'];
$cfForm = 'dis-filter';
$cfDistinct = url('discrepancies/distinct');
require __DIR__ . '/../partials/colfilter.php';
?>
