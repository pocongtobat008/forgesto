<?php
/**
 * Halaman kelola Marking Memo: ringkasan cetak, contoh memo, dan daftar item 50 per halaman.
 * @var array $stats     ['total','printed','unique','never']
 * @var array $paginator ['items','total','page','per_page','last_page','from','to']
 * @var array $sample    memo contoh (maks 4)
 * @var ?string $sampleUrl
 */
$stats     = $stats ?? ['total' => 0, 'printed' => 0, 'unique' => 0, 'never' => 0];
$paginator = $paginator ?? ['items' => [], 'total' => 0, 'page' => 1, 'per_page' => 50, 'last_page' => 1, 'from' => 0, 'to' => 0];
$rows      = $paginator['items'];
$sample    = $sample ?? [];
$sampleUrl = $sampleUrl ?? null;
$q         = $q ?? '';
$filter    = $filter ?? '';
$warehouseLabel = $warehouseLabel ?? '';
$__base = $_GET; unset($__base['page'], $__base['printed']);
$__tabLink = static function (string $f) use ($__base): string {
    $qq = $__base;
    if ($f !== '') { $qq['printed'] = $f; }
    return url('items/memo') . ($qq !== [] ? '?' . http_build_query($qq) : '');
};
?>
<?= \App\Core\View::partial('items/_memo_styles') ?>

<?= page_hero() ?>
<div class="page-head has-hero">
    <div>
        <h1><?= __('Marking Memo') ?></h1>
        <p><?= __('Cetak memo QR per item. Daftar tampil 50 item per halaman.') ?></p>
    </div>
    <div class="spacer"></div>
</div>

<?php
$__tabs = [
    ['',    'Semua item',        (int) $stats['total']],
    ['yes', 'Sudah dicetak',     (int) $stats['unique']],
    ['no',  'Belum dicetak',     (int) $stats['never']],
];
?>
<div class="sf-tabs memo-tabs" role="tablist" aria-label="<?= __('Kelompok memo') ?>">
    <?php foreach ($__tabs as [$__f, $__lbl, $__n]): ?>
        <a class="tab-pill<?= $filter === $__f ? ' is-active' : '' ?>" role="tab"
           aria-selected="<?= $filter === $__f ? 'true' : 'false' ?>" href="<?= e($__tabLink($__f)) ?>">
            <?= e($__lbl) ?> <span class="tab-count"><?= $__n ?></span>
        </a>
    <?php endforeach; ?>
</div>

<div class="grid grid-4 grid-tablet-4 memo-stats">
    <div class="stat is-brand" style="--i:0">
        <div class="stat-icon"><?= icon('file-text') ?></div>
        <div class="stat-label"><?= __('Memo tercetak') ?></div>
        <div class="stat-value"><?= (int) $stats['printed'] ?></div>
        <div class="stat-meta"><?= __('lembar memo yang diunduh PDF') ?></div>
    </div>
    <div class="stat is-info" style="--i:1">
        <div class="stat-icon"><?= icon('check') ?></div>
        <div class="stat-label"><?= __('Item sudah pernah dicetak') ?></div>
        <div class="stat-value"><?= (int) $stats['unique'] ?></div>
        <div class="stat-meta">dari <?= (int) $stats['total'] ?> item</div>
    </div>
    <div class="stat is-warning" style="--i:2">
        <div class="stat-icon"><?= icon('x') ?></div>
        <div class="stat-label"><?= __('Belum pernah dicetak') ?></div>
        <div class="stat-value"><?= (int) $stats['never'] ?></div>
        <div class="stat-meta"><?= __('item tanpa memo tercetak') ?></div>
    </div>
</div>

<section class="card">
    <div class="card-header">
        <h3><?= __('Contoh marking memo') ?></h3>
        <div class="spacer"></div>
        <?php if ($sampleUrl): ?>
            <a class="btn btn-outline btn-sm" href="<?= e($sampleUrl) ?>" target="_blank" rel="noopener"><?= icon('printer') ?> Cetak contoh</a>
        <?php endif; ?>
    </div>
    <div class="by-body" style="padding:1rem;">
        <?php if ($sample === []): ?>
            <div class="empty-state">
                <div class="empty-icon"><?= icon('file-text') ?></div>
                <h3><?= __('Belum ada item untuk dijadikan contoh') ?></h3>
                <p>Tambahkan atau import item terlebih dahulu agar contoh memo tampil di sini.</p>
            </div>
        <?php else: ?>
            <div class="memo-sample">
                <?php foreach ($sample as $m): ?>
                    <article class="memo">
                        <header class="memo-head">
                            <span class="memo-kicker"><?= __('Marking memo') ?></span>
                            <span class="memo-meta">
                                <span class="memo-period"><?= e($periodLabel ?? '') ?></span>
                                <span class="memo-wh"><?= e($m['warehouse'] ?? '') ?></span>
                            </span>
                            <span class="memo-code"><?= e($m['code']) ?></span>
                        </header>
                        <div class="memo-body">
                            <div class="memo-info">
                                <div class="memo-row-name"><span class="memo-k"><?= __('Nama barang') ?></span><span class="memo-v"><?= e($m['name']) ?></span></div>
                                <div class="memo-row-loc"><span class="memo-k"><?= __('Lokasi') ?></span><span class="memo-v"><?= e($m['location'] !== '' ? $m['location'] : 'Belum diatur') ?></span></div>
                                <div class="memo-row-qty"><span class="memo-k">Total QTY</span><span class="memo-qty memo-qty-blank"><b>&nbsp;</b></span></div>
                                <?php if (($m['group'] ?? '') !== ''): ?><div class="memo-row-remark"><span class="memo-k">Remark</span><span class="memo-v"><?= e($m['group']) ?></span></div><?php endif; ?>
                            </div>
                            <div class="memo-qr">
                                <?= $m['qr'] ?>
                                <small><?= e($m['code']) ?></small>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<section class="card">
    <div class="card-header">
        <h3><?= __('Pilih item untuk dicetak') ?></h3>
        <div class="spacer"></div>
        <span class="muted text-sm">Halaman <?= (int) $paginator['page'] ?> dari <?= (int) $paginator['last_page'] ?> · <?= (int) $paginator['total'] ?> item</span>
    </div>

    <form class="memo-search" method="get" action="<?= url('items/memo') ?>">
        <?php if ($filter !== ''): ?><input type="hidden" name="printed" value="<?= e($filter) ?>"><?php endif; ?>
        <div class="memo-search-field">
            <?= icon('search') ?>
            <input type="search" name="q" value="<?= e($q) ?>" autocomplete="off"
                   placeholder="<?= __('Cari kode, nama barang, group, atau lokasi...') ?>" aria-label="<?= __('Cari item memo') ?>">
        </div>
        <?php if ($q !== ''): ?>
            <a class="btn btn-ghost btn-sm" href="<?= e($__tabLink($filter)) ?>"><?= icon('x') ?> Hapus</a>
        <?php endif; ?>
    </form>

    <div class="memo-toolbar">
        <label>
            <input type="checkbox" id="memo-all" aria-label="<?= __('Pilih semua item di halaman ini') ?>"> Pilih semua di halaman ini
        </label>
        <span class="text-sm muted" id="memo-count"><?= __('0 item dipilih') ?></span>
        <span class="spacer"></span>
        <button type="button" class="btn btn-outline" id="memo-preview-sel" disabled><?= icon('printer') ?> Preview terpilih</button>
        <button type="button" class="btn btn-brand" id="memo-pdf" disabled><?= icon('download') ?> Unduh PDF terpilih</button>
        <a class="btn btn-ghost" href="<?= e(url('items/memo/preview?all=1')) ?>" target="_blank" rel="noopener"><?= icon('eye') ?> Semua</a>
        <a class="btn btn-ghost" href="<?= e(url('items/memo.pdf?all=1&dl=1')) ?>"><?= icon('download') ?> PDF semua</a>
    </div>

    <div class="table-wrap">
        <table class="data">
            <thead>
                <tr>
                    <th style="width:44px"><span class="sr-only"><?= __('Pilih') ?></span></th>
                    <th><?= __('Kode') ?></th>
                    <th><?= __('Nama barang') ?></th>
                    <th class="col-hide-tablet"><?= __('Group') ?></th>
                    <th class="col-hide-tablet"><?= __('Lokasi') ?></th>
                    <th class="t-right"><?= __('total.qty') ?></th>
                    <th><?= __('Dicetak') ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if ($rows === []): ?>
                    <tr><td colspan="7">
                        <div class="empty-state">
                            <div class="empty-icon"><?= icon('search') ?></div>
                            <h3><?= __('Tidak ada item cocok') ?></h3>
                            <p><?= $q !== '' ? 'Tidak ada item yang cocok dengan pencarian “' . e($q) . '”.' : 'Belum ada item pada kelompok ini.' ?></p>
                        </div>
                    </td></tr>
                <?php endif; ?>
                <?php foreach ($rows as $r): ?>
                    <tr>
                        <td><input type="checkbox" class="memo-check" value="<?= (int) $r['id'] ?>" aria-label="Pilih item <?= e($r['code']) ?>"></td>
                        <td><span class="mono"><?= e($r['code']) ?></span></td>
                        <td><?= e($r['name']) ?></td>
                        <td class="col-hide-tablet"><?= e($r['group_name']) ?></td>
                        <td class="col-hide-tablet"><?= e($r['location_name'] ?? '-') ?></td>
                        <td class="t-right"><?= can_see_stock_qty() ? (int) $r['expected_qty'] : '<span class="faint">***</span>' ?></td>
                        <td>
                            <?php if ((int) $r['print_count'] > 0): ?>
                                <span class="badge badge-success"><?= (int) $r['print_count'] ?>x</span>
                                <span class="text-sm muted"><?= e(date('d M Y H:i', strtotime((string) $r['last_printed']))) ?></span>
                            <?php else: ?>
                                <span class="badge"><?= __('Belum') ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?= \App\Core\View::partial('partials/pagination', ['paginator' => $paginator, 'query' => []]) ?>
</section>

<script>
(function () {
    var PREVIEW_URL = <?= json_encode(url('items/memo/preview')) ?>;
    var PDF_URL = <?= json_encode(url('items/memo.pdf')) ?>;
    var all = document.getElementById('memo-all');
    var prevBtn = document.getElementById('memo-preview-sel');
    var pdfBtn = document.getElementById('memo-pdf');
    var cnt = document.getElementById('memo-count');
    if (!prevBtn || !pdfBtn) return;
    function boxes() { return Array.prototype.slice.call(document.querySelectorAll('.memo-check')); }
    function picked() { return boxes().filter(function (c) { return c.checked; }).map(function (c) { return c.value; }); }
    function refresh() {
        var ids = picked();
        cnt.textContent = ids.length + ' item dipilih';
        prevBtn.disabled = ids.length === 0;
        pdfBtn.disabled = ids.length === 0;
        if (all) all.checked = boxes().length > 0 && boxes().every(function (c) { return c.checked; });
    }
    if (all) all.addEventListener('change', function () {
        boxes().forEach(function (c) { c.checked = all.checked; });
        refresh();
    });
    document.addEventListener('change', function (e) {
        if (e.target.classList && e.target.classList.contains('memo-check')) refresh();
    });
    prevBtn.addEventListener('click', function () {
        var ids = picked();
        if (ids.length) window.open(PREVIEW_URL + '?ids=' + ids.join(','), '_blank', 'noopener');
    });
    pdfBtn.addEventListener('click', function () {
        var ids = picked();
        if (ids.length) window.location.href = PDF_URL + '?ids=' + ids.join(',') + '&dl=1';
    });
    refresh();
})();
</script>
