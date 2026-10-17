<?php
/**
 * @var array $items
 * @var array $filters
 * @var array $groups
 * @var array $locations
 * @var array $summary
 */
use App\Core\View;

$__xfQs = [];
foreach ((array) ($filters['col'] ?? []) as $__k => $__vals) { foreach ((array) $__vals as $__v) { $__xfQs[] = 'col[' . rawurlencode($__k) . '][]=' . rawurlencode($__v); } }
foreach ((array) ($filters['adv'] ?? []) as $__i => $__c) { foreach (['col','op','val'] as $__f) { $__xfQs[] = 'adv[' . $__i . '][' . $__f . ']=' . rawurlencode((string) $__c[$__f]); } }
if (! empty($filters['search'])) { $__xfQs[] = 'search=' . rawurlencode($filters['search']); }
$exportQs = $__xfQs ? '?' . implode('&', $__xfQs) : '';
$activeCount = count($filters['col'] ?? []) + count($filters['adv'] ?? []) + (empty($filters['search']) ? 0 : 1);

$countBadge = static fn (string $status): string => match ($status) {
    'pending' => 'badge-danger',
    'counted' => 'badge-success',
    'posted'  => 'badge-info',
    default   => '',
};
$countLabel = static fn (string $status): string => match ($status) {
    'pending' => __('status.not_counted'),
    'counted' => 'Sudah dihitung',
    'posted'  => 'Terposting',
    default   => $status,
};
$auditBadge = static fn (string $status): string => match ($status) {
    'pending'     => 'badge-danger',
    'audited'     => 'badge-success',
    'discrepancy' => 'badge-danger',
    'resolved'    => 'badge-brand',
    'recount'     => 'badge-warning',
    default       => '',
};
$auditLabel = static fn (string $status): string => match ($status) {
    'pending'     => __('status.not_audited'),
    'audited'     => __('status.audited'),
    'discrepancy' => 'Selisih',
    'resolved'    => 'Selesai',
    'recount'     => 'Cek Ulang',
    default       => $status,
};

$__keepKeys = ['group_id', 'location_id', 'count_status', 'audit_status', 'search', 'locked', 'posted', 'col', 'adv', 'sort', 'dir'];
$cleanQuery = [];
foreach ($__keepKeys as $__k) {
    if (isset($_GET[$__k]) && $_GET[$__k] !== '' && $_GET[$__k] !== []) {
        $cleanQuery[$__k] = $_GET[$__k];
    }
}
?>
<?= page_hero() ?>
<div class="page-head has-hero">
    <div>
        <h1><?= __('Observer Counting') ?></h1>
        <p><?= __('Hitung seluruh data di lokasi, kirim ke audit, lalu Admin STO memverifikasi hasilnya.') ?></p>
    </div>
    <div class="spacer"></div>
    <div class="btn-group">
        <?php if (! empty($isReadOnly)): ?>
            <span class="badge badge-warning" style="font-size:.875rem;padding:.45rem .85rem;display:inline-flex;align-items:center;gap:.4rem;">
                <?= icon('lock') ?> <?= __('Mode Read-Only') ?> &middot; <?= e($currentPeriod['audit_month'] ?? '') ?>
            </span>
        <?php else: ?>
            <?php if (can('import_data') && can_action('import', 'add')): ?>
                <a class="btn btn-outline" href="<?= url('import') ?>"><?= icon('upload') ?> Import</a>
            <?php endif; ?>
            <?php if (can('manage_data') && can_action('items', 'add')): ?>
                <a class="btn" href="<?= url('items/create') ?>"><?= icon('plus') ?> <?= __('act.add') ?> Data</a>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<?= \App\Core\View::partial('partials/process-trail', ['trail' => $trail ?? [], 'trailGroupId' => $trailGroupId ?? null]) ?>
<!-- Ringkasan cepat -->
<div class="grid grid-4 grid-tablet-4">
    <div class="stat is-brand" style="--i:0">
        <div class="stat-icon"><?= icon('package') ?></div>
        <span class="stat-label"><?= __('Total') ?></span>
        <div class="stat-value"><?= (int) $summary['total'] ?></div>
        <div class="stat-meta"><?= __('item sesuai filter') ?></div>
    </div>
    <div class="stat is-success" style="--i:1">
        <div class="stat-icon"><?= icon('check') ?></div>
        <span class="stat-label"><?= __('status.counted') ?></span>
        <div class="stat-value"><?= (int) $summary['counted'] ?></div>
        <div class="stat-meta"><?= percent((int) $summary['counted'], (int) $summary['total']) ?>%</div>
    </div>
    <div class="stat is-info" style="--i:2">
        <div class="stat-icon"><?= icon('clipboard-check') ?></div>
        <span class="stat-label"><?= __('status.audited') ?></span>
        <div class="stat-value"><?= (int) $summary['audited'] ?></div>
        <div class="stat-meta"><?= percent((int) $summary['audited'], (int) $summary['total']) ?>%</div>
    </div>
    <div class="stat is-danger" style="--i:3">
        <div class="stat-icon"><?= icon('alert-circle') ?></div>
        <span class="stat-label"><?= __('status.not_counted') ?></span>
        <div class="stat-value"><?= (int) $summary['not_counted'] ?></div>
        <div class="stat-meta"><?= (int) $summary['locked'] ?> terkunci</div>
    </div>
</div>

<div class="card mt-2">
    <?php if (empty($isReadOnly)): ?>
    <div class="scan-hint">
        <span class="hint">
            <?= icon('barcode') ?> <?= __('Bisa pakai kamera HP atau scanner USB/gun: arahkan ke kode lalu Enter.') ?>
            Tekan <span class="kbd"><?= __('Ctrl B') ?></span>
        </span>
    </div>
    <?php endif; ?>
    <div class="items-loader" id="items-loader" role="status" aria-live="polite" aria-hidden="true">
        <div class="items-loader-box"><span class="sf-spin" aria-hidden="true"></span><span><?= __('Memuat data...') ?></span></div>
    </div>
    <script>
    (function () {
      var ov = document.getElementById('items-loader');
      if (!ov) return;
      function show() { ov.classList.add('is-on'); ov.setAttribute('aria-hidden', 'false'); }
      function hide() { ov.classList.remove('is-on'); ov.setAttribute('aria-hidden', 'true'); }
      var f = document.getElementById('items-filter');
      if (f) f.addEventListener('submit', show);
      document.addEventListener('click', function (ev) {
        var a = ev.target.closest && ev.target.closest('a[href*="items"][href*="page="]');
        if (a && !ev.defaultPrevented) show();
      });
      var proto = HTMLFormElement.prototype.submit;
      HTMLFormElement.prototype.submit = function () { if (this.id === 'items-filter') show(); return proto.apply(this, arguments); };
      var rs = HTMLFormElement.prototype.requestSubmit;
      if (rs) HTMLFormElement.prototype.requestSubmit = function () { if (this.id === 'items-filter') show(); return rs.apply(this, arguments); };
      window.addEventListener('pageshow', hide);
    })();
    </script>
    <form method="get" action="<?= url('items') ?>" class="filters items-filters" id="items-filter" style="border:none;box-shadow:none;">
        <div class="filter-bar">
            <div class="form-group is-search">
                <label for="search"><?= __('label.quick_search') ?></label>
                <input type="search" id="search" name="search" value="<?= e($filters['search'] ?? '') ?>"
                       placeholder="<?= __('Kode, nama, kategori, group, lokasi...') ?>" autocomplete="off" enterkeyhint="search">
            </div>
            <div class="form-group is-barcode">
                <span class="form-label-lite"><?= __('Barcode') ?></span>
                <button type="button" class="btn btn-brand" data-scan-open aria-label="<?= __('Scan barcode atau QR') ?>">
                    <?= icon('scan') ?> Scan barcode
                </button>
            </div>
            <!-- DUPLICATE ADV BUTTON REMOVED -->
        </div>

        <?php
        $asForm     = 'items-filter';
        $asCols     = [
            'code'      => __('Kode'),
            'name'      => __('Nama'),
            'category'  => __('Kategori'),
            'group'     => __('Group'),
            'location'  => __('Lokasi'),
            'device'    => __('Device'),
            'unit'      => __('Satuan'),
            'price'     => __('Harga Satuan'),
            'counted'   => __('Hitung'),
            'count'     => __('Status Hitung'),
            'audit'     => __('Status Audit'),
        ];
        if (can_see_stock_qty()) {
            $asCols['expected'] = __('Sistem');
        }
        $asCurrent  = $filters['adv'] ?? [];
        $asNumeric  = ['price', 'expected', 'counted'];
        $asToggleId = 'adv-toggle';
        $asDistinct = url('items/distinct');
        require __DIR__ . '/../partials/advanced-search.php';
        ?>

        <?php foreach ($filters['col'] as $ck => $cvals): ?>
            <?php foreach ((array) $cvals as $cv): ?>
                <input type="hidden" name="col[<?= e($ck) ?>][]" value="<?= e($cv) ?>" class="col-hidden">
            <?php endforeach; ?>
        <?php endforeach; ?>
        <?php foreach ($filters['adv'] as $i => $cond): ?>
            <input type="hidden" name="adv[<?= (int) $i ?>][col]" value="<?= e($cond['col']) ?>">
            <input type="hidden" name="adv[<?= (int) $i ?>][op]" value="<?= e($cond['op']) ?>">
            <input type="hidden" name="adv[<?= (int) $i ?>][val]" value="<?= e($cond['val']) ?>">
        <?php endforeach; ?>
        <noscript><button type="submit" class="btn btn-outline"><?= __('Terapkan') ?></button></noscript>
    </form>

    <?php
    // Hitung tab sekarang dulu sebelum tampilkan tombol posting
    $__cs = (array) ($filters['count_status'] ?? []);
    sort($__cs);
    // Param eksplisit ?tab=all dipakai agar tab "Semua" tetap bisa aktif
    // ketika tidak ada filter count_status (kalau tidak, tab Semua tak pernah is-active).
    $__explicitTab = (string) ($_GET['tab'] ?? '');
    $__tab = 'pending';
    if ($__cs === []) {
        $__tab = ($__explicitTab === 'all') ? 'all' : 'pending';   // default = belum dihitung
    } elseif ($__cs === ['pending']) {
        $__tab = 'pending';
    } elseif ($__cs === ['counted']) {
        $__tab = 'counted';
    } elseif (in_array('posted', $__cs, true) || in_array('submitted', $__cs, true)) {
        $__tab = 'posted';
    } elseif (in_array('recount', $__cs, true)) {
        $__tab = 'recount';
    } else {
        $__tab = 'all';
    }
    ?>
    <?php if (can_action('items', 'post') && can('post_data') && count((array) ($filters['group_id'] ?? [])) === 1 && $__tab === 'counted'): ?>
        <div class="card-body" style="padding:.85rem 1.1rem;border-bottom:1px solid var(--border);">
            <form method="post" action="<?= url('items/bulk-post') ?>" class="flex items-center gap wrap"
                  data-confirm="<?= e(__('Posting semua data yang sudah dihitung pada group ini ke audit?')) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="group_id" value="<?= (int) ((array) $filters['group_id'])[0] ?>">
                <span class="text-sm muted">Selesaikan penghitungan group ini lalu kirim sekaligus ke audit.</span>
                <button type="submit" class="btn btn-sm"><?= icon('send') ?> Posting semua yang sudah dihitung</button>
            </form>
        </div>
    <?php endif; ?>
    <div class="sf-tabtoolbar"><?php
    // --- Tab kelompok penghitungan (server-side, mempertahankan filter lain) ---
    // Logic tab sudah dihitung di atas untuk conditional tombol posting
    $__baseQ = $_GET;
    unset($__baseQ['count_status'], $__baseQ['page'], $__baseQ['tab']);
    $__tabUrl = static function (array $cs, string $tabKey = '') use ($__baseQ): string {
        $q = $__baseQ;
        if ($cs !== []) { $q['count_status'] = $cs; }
        if ($tabKey === 'all') { $q['tab'] = 'all'; }
        return url('items') . ($q !== [] ? '?' . http_build_query($q) : '');
    };
    $__tabs = [
        ['pending', __('status.not_counted'),  ['pending'],                     (int) $tabCounts['not_counted']],
        ['counted', __('status.counted'),      ['counted'],                     (int) $tabCounts['counted']],
        ['recount', __('status.recount'),      ['recount'],                     (int) $tabCounts['recount']],
        ['posted',  __('status.posted'),       ['posted', 'submitted'],        (int) $tabCounts['posted']],
        ['all',     __('status.all'),          [],                              (int) $tabCounts['total']],
    ];
    ?>
    <div class="sf-tabs items-tabs" role="tablist" aria-label="<?= __('Kelompok penghitungan') ?>">
        <?php foreach ($__tabs as [$__k, $__lbl, $__csVals, $__n]): ?>
            <a class="tab-pill<?= $__tab === $__k ? ' is-active' : '' ?>" role="tab"
               aria-selected="<?= $__tab === $__k ? 'true' : 'false' ?>"
               href="<?= e($__tabUrl($__csVals, $__k)) ?>" data-no-progress>
                <?= e($__lbl) ?> <span class="tab-count"><?= $__n ?></span>
            </a>
        <?php endforeach; ?>
    </div>

    <!-- Toolbar: aksi tabel (filter, ekspor, kolom, pengaturan scanner) — sebaris dgn tab, rata kanan -->
    <div class="table-toolbar items-toolbar-right">
        <span class="memo-count-badge" id="memo-count" aria-live="polite"></span>
        <button type="button" class="btn btn-info btn-icon-only" id="adv-toggle" aria-label="<?= __('adv.open') ?>" title="<?= __('adv.label') ?>"><?= icon('filter') ?></button>
        <?php $__cc = 0; foreach (($filters["col"] ?? []) as $__v) { if ($__v) $__cc++; } $__hs = !empty($filters["sort"]); $__adv = !empty($filters["adv"]) || !empty($_GET["adv"]); ?>
        <?php if ($__cc || $__hs || $__adv): ?>
        <a class="btn btn-ghost btn-icon-only col-clear-all" href="<?= url("items") ?>" aria-label="<?= __('Hapus semua filter dan urutan') ?>" title="<?= __('Hapus semua filter dan urutan') ?>"><?= icon('x') ?></a>
        <?php endif; ?>
        <?php if (empty($isReadOnly) && can_action('items', 'post') && can('post_data')): ?>
            <?php
            $__isAdmin = in_array(\App\Core\Auth::role(), ['poweradmin', 'adminsto'], true);
            $__isPic = $__isAdmin || ($activeGroup !== null && in_array((int) $activeGroup, array_map('intval', $picGroups), true));
            if (! $__isAdmin && ! can_action('items', 'pic_post')) {
                $__isPic = false;
            }
            $__canDirectPost = $__isAdmin
                ? ((int) $tabCounts['counted'] > 0)
                : ($__isPic && (int) $groupPending === 0 && (int) $tabCounts['counted'] > 0);
            ?>
            <button type="button" class="btn <?= $__canDirectPost ? 'btn-success' : 'btn-outline' ?> btn-icon-only btn-posting-toolbar" id="btn-posting" aria-label="<?= __('Posting data yang sudah dihitung ke audit') ?>" title="<?= $__canDirectPost ? __('Posting data yang sudah dihitung ke audit') : ((int) $groupPending > 0 ? sprintf(__('Masih ada %d item belum dihitung'), (int) $groupPending) : __('Posting data ke audit')) ?>"><?= icon('upload') ?></button>
        <?php endif; ?>
        <a class="btn btn-brand btn-icon-only" id="btn-export-toolbar" href="<?= url('items/export.xlsx') ?><?= $exportQs ?>" aria-label="<?= __('Unduh Excel hasil filter') ?>" title="<?= __('Unduh Excel hasil filter') ?>"><?= icon('download') ?></a>
        <div class="col-mgr" id="items-colmgr">
            <button type="button" class="btn btn-ghost btn-icon-only" id="items-colmgr-btn" aria-expanded="false" aria-label="<?= __('Sesuaikan kolom') ?>" title="<?= __('Sesuaikan kolom') ?>">
                <?= icon('layers') ?>
            </button>
            <div class="col-mgr-panel" id="items-colmgr-panel" hidden></div>
        </div>
        <button type="button" class="btn btn-icon-only" id="scanner-settings" aria-label="<?= __('Pengaturan Scanner') ?>" title="<?= __('Pengaturan Scanner') ?>">
            <?= icon('settings') ?>
        </button>
    </div>
    </div><!-- /.sf-tabtoolbar -->
    <div class="table-wrap">
        <table class="data" id="items-table" data-rowdetail data-colmgr-key="items-v1" data-colmgr-prefix="items">
            <thead>
                <tr>
                    <th data-col="select" style="width:44px" class="t-center th-select-all" title="<?= __('Pilih semua item tampil') ?>">
                        <?php if (empty($isReadOnly)): ?>
                        <input type="checkbox" id="memo-all" class="memo-all-table" aria-label="<?= __('Pilih semua item tampil') ?>">
                        <?php endif; ?>
                    </th>
                    <th data-col="actions" style="width:140px" class="t-center"><?= __('Aksi') ?></th>
                    <?php
                    // REORDERED: Aksi + Status Hitung di kiri, HAPUS Sistem & Selisih (petugas tidak boleh lihat qty sistem)
                    $XF_COLS = [
                      ['count','Status Hitung',' t-center'],
                      ['code','Kode',''], ['name','Nama',''], ['group','Group',''], ['location','Lokasi',''],
                      ['device','Device',' col-hide-tablet'], 
                      ['counted','Hitung',' col-hide-tablet t-center'], ['unit','Satuan',' col-hide-tablet t-center'],
                      ['category','Kategori',' col-hide-tablet'], ['price','Harga',' col-hide-tablet t-right'],
                      ['audit','Status Audit',' t-center'],
                    ];
                    if (function_exists('is_col_hidden')) {
                        $XF_COLS = array_values(array_filter($XF_COLS, static fn ($c) => ! is_col_hidden('items', $c[0])));
                    }
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
                        <td colspan="13">
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
                        $isLocked = (int) ($item['is_locked'] ?? 0) === 1 || ! empty($isReadOnly);
                        $canCount = empty($isReadOnly) && can('count_data') && (! $isLocked || can('unlock_data'));
                        $__rd = [
                            'id'           => (int) $item['id'],
                            'code'         => (string) $item['code'],
                            'name'         => (string) $item['name'],
                            'category'     => (string) ($item['category'] ?? ''),
                            'unit'         => (string) ($item['unit'] ?? ''),
                            'group'        => (string) ($item['group_name'] ?? ''),
                            'group_code'   => (string) ($item['group_code'] ?? ''),
                            'location'     => (string) ($item['location_name'] ?? ''),
                            'device'       => (string) ($item['device_name'] ?? ''),
                            'price'        => $item['unit_price'] === null ? null : (float) $item['unit_price'],
                            'counted'      => $item['counted_qty'] === null ? null : (int) $item['counted_qty'],
                            'count_status' => (string) $item['count_status'],
                            'audit_status' => (string) $item['audit_status'],
                            'locked'       => (bool) $isLocked,
                            'counted_by'   => (string) ($item['counted_by_name'] ?? ''),
                            'counted_at'   => (string) ($item['counted_at'] ?? ''),
                            'posted_by'    => (string) ($item['posted_by_name'] ?? ''),
                            'posted_at'    => (string) ($item['posted_at'] ?? ''),
                            'note'         => (string) ($item['condition_note'] ?? ''),
                            'observers'    => trim((string) ($item['observers'] ?? '')),
                            'auditors'     => trim((string) ($item['auditors'] ?? '')),
                            'url'          => url('items/' . (int) $item['id']),
                        ];
                        if (function_exists('can_see_stock_qty') && can_see_stock_qty()) {
                            $__rd['expected'] = $item['expected_qty'] === null ? null : (int) $item['expected_qty'];
                        }
                        ?>
                        <tr data-rd="<?= e(json_encode($__rd, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP)) ?>"
                            data-item-id="<?= (int) $item['id'] ?>"
                            data-item-code="<?= e($item['code']) ?>"
                            data-item-name="<?= e($item['name']) ?>"
                            data-group-id="<?= (int) ($item['group_id'] ?? 0) ?>"
                            data-item-group="<?= e($item['group_name'] ?: '-') ?>"
                            data-item-location="<?= e($item['location_name'] ?: '-') ?>"
                            data-item-device="<?= e($item['device_name'] ?: '-') ?>"
                            data-item-unit="<?= e($item['unit'] ?: 'UNIT') ?>"
                            data-item-category="<?= e($item['category'] ?: '-') ?>"
                            data-counted-qty="<?= $item['counted_qty'] === null ? '' : (int) $item['counted_qty'] ?>"
                            data-count-status="<?= e($item['count_status']) ?>"
                            data-audit-status="<?= e($item['audit_status']) ?>"
                            data-is-locked="<?= $isLocked ? '1' : '0' ?>">
                            <td class="memo-pick"><?php if (empty($isReadOnly)): ?><input type="checkbox" class="memo-check" value="<?= (int) ($item['item_id'] ?? $item['id']) ?>" aria-label="Pilih item <?= e($item['code']) ?>"><?php else: ?><span class="muted" style="font-size:.8rem;">-</span><?php endif; ?></td>
                            <td data-label="<?= __('Aksi') ?>" class="t-center cell-action">
                                <?php if ($canCount): ?>
                                    <form method="post" action="<?= url('items/' . (int) $item['id'] . '/count') ?>" class="flex gap-sm items-center form-count-action" style="justify-content:center;">
                                        <?= csrf_field() ?>
                                        <input type="number" name="counted_qty" min="0" value="<?= $item['counted_qty'] === null ? '' : (int) $item['counted_qty'] ?>" placeholder="0"
                                               class="input-counted-qty"
                                               style="width:84px;padding:.3rem .5rem;font-size:.8rem;" aria-label="<?= __('Jumlah hitung') ?>" required>
                                        <button type="button" class="btn btn-outline btn-xs btn-count-action" aria-label="<?= __('Simpan hitung') ?>" title="<?= __('Konfirmasi simpan hitungan') ?>"><?= icon('check') ?></button>
                                    </form>
                                <?php else: ?>
                                    <a class="btn btn-outline btn-xs" href="<?= url('items/' . (int) $item['id']) ?>"><?= icon('eye') ?> Detail</a>
                                <?php endif; ?>
                            </td>
                            <?php if (! is_col_hidden('items', 'count')): ?>
                            <td data-label="<?= __('Status Hitung') ?>" class="t-center">
                                <span class="badge <?= $countBadge((string) $item['count_status']) ?>"><?= e($countLabel((string) $item['count_status'])) ?></span>
                            </td>
                            <?php endif; ?>
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
                            <?php if (! is_col_hidden('items', 'group')): ?>
                            <td data-label="<?= __('Group') ?>"><?= e($item['group_name']) ?></td>
                            <?php endif; ?>
                            <?php if (! is_col_hidden('items', 'location')): ?>
                            <td data-label="<?= __('Lokasi') ?>"><?= e($item['location_name'] ?: '-') ?></td>
                            <?php endif; ?>
                            <?php if (! is_col_hidden('items', 'device')): ?>
                            <td data-label="<?= __('Device') ?>" class="col-hide-tablet"><?= e($item['device_name'] ?: '-') ?></td>
                            <?php endif; ?>

                            <?php if (! is_col_hidden('items', 'counted')): ?>
                            <td data-label="<?= __('Hitung') ?>" class="col-hide-tablet t-center mono tabular"><?= $item['counted_qty'] === null ? '—' : (int) $item['counted_qty'] ?></td>
                            <?php endif; ?>
                            <?php if (! is_col_hidden('items', 'unit')): ?>
                            <td data-label="<?= __('Satuan') ?>" class="col-hide-tablet t-center"><?= e($item['unit'] ?: '-') ?></td>
                            <?php endif; ?>
                            <?php if (! is_col_hidden('items', 'category')): ?>
                            <td data-label="<?= __('Kategori') ?>" class="col-hide-tablet"><?= e($item['category'] ?: '-') ?></td>
                            <?php endif; ?>
                            <?php if (! is_col_hidden('items', 'price')): ?>
                            <td data-label="<?= __('Harga') ?>" class="col-hide-tablet t-right mono tabular"><?= $item['unit_price'] === null ? '-' : 'Rp ' . number_format((float) $item['unit_price'], 0, ',', '.') ?></td>
                            <?php endif; ?>
                            <?php if (! is_col_hidden('items', 'audit')): ?>
                            <td data-label="<?= __('Status Audit') ?>" class="t-center">
                                <span class="badge <?= $auditBadge((string) $item['audit_status']) ?>"><?= e($auditLabel((string) $item['audit_status'])) ?></span>
                            </td>
                            <?php endif; ?>

                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table></div>


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

<script>
(function() {
  function init() {
(function () {
  var f = document.getElementById('items-filter');
  var pop = document.getElementById('xf-pop');
  if (!f || !pop) return;
  var LABELS = <?= json_encode(array_filter(['code'=>__('Kode'),'name'=>__('Nama'),'group'=>__('Group'),'location'=>__('Lokasi'),'device'=>__('Device'),'expected'=>__('Sistem'),'counted'=>__('Hitung'),'unit'=>__('Satuan'),'category'=>__('Kategori'),'price'=>__('Harga'),'count'=>__('Status Hitung'),'audit'=>__('Status Audit')], static fn ($k) => $k !== 'expected' || can_see_stock_qty(), ARRAY_FILTER_USE_KEY), JSON_UNESCAPED_UNICODE) ?>;
  var NUMK = { expected:1, counted:1, price:1 };
  var sortKey = <?= json_encode($sortKey ?? null) ?>, sortDir = <?= json_encode($sortDir ?? null) ?>;
  var DISPLAY = <?= json_encode(['counted'=>__('Sudah dihitung'),'pending'=>__('Belum dihitung'),'posted'=>__('Terposting'),'audited'=>__('Sudah diaudit'),'discrepancy'=>__('Selisih'),'resolved'=>__('Selesai')], JSON_UNESCAPED_UNICODE) ?>;
  var current = null, picked = [], cache = {};
  var list = document.getElementById('xf-list'), q = document.getElementById('xf-q');
  var title = document.getElementById('xf-title');
  var base = <?= json_encode(url('items/distinct'), JSON_UNESCAPED_SLASHES) ?>;
  var sbase = <?= json_encode(url('items'), JSON_UNESCAPED_SLASHES) ?>;

  // Collect current col filters from hidden inputs (so picks survive re-open)
  function currentFilters(key) {
    var out = [];
    f.querySelectorAll('input.col-hidden').forEach(function (i) {
      var m = i.name.match(/^col\[([a-z]+)\]/);
      if (m && m[1] === key) out.push(i.value);
    });
    return out;
  }
  function filterQueryExcept(key) {
    var fd = new FormData(f), p = new URLSearchParams();
    fd.forEach(function (v, k) { if (!/^col\[/.test(k) || k.indexOf('col[' + key + ']') !== 0) p.append(k, v); });
    return p.toString();
  }
  function openPop(btn) {
    current = btn.getAttribute('data-xf');
    picked = currentFilters(current);
    title.textContent = 'Filter: ' + LABELS[current];
    markSortState();
    q.value = '';
    list.innerHTML = '<div class="xf-loading">' + <?= json_encode(__('Memuat...'), JSON_UNESCAPED_UNICODE) ?> + '</div>';
    pop.hidden = false;
    placePop(btn);
    loadValues();
    q.focus();
    loadValues();
    q.focus();
  }
  function loadValues() {
    var qs = filterQueryExcept(current);
    var key = current;
    var url = base + '?key=' + encodeURIComponent(key) + (qs ? '&' + qs : '');
    fetch(url, { credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (d) {
      if (key !== current) return;
      cache[key] = d.values || [];
      render();
    }).catch(function () { list.innerHTML = '<div class="xf-loading">' + <?= json_encode(__('Gagal memuat daftar.'), JSON_UNESCAPED_UNICODE) ?> + '</div>'; });
  }
  function render() {
    var term = q.value.trim().toLowerCase();
    var vals = cache[current] || [];
    if (vals.length === 0) { list.innerHTML = '<div class="xf-loading">' + <?= json_encode(__('Tidak ada nilai.'), JSON_UNESCAPED_UNICODE) ?> + '</div>'; return; }
    var html = '', shown = 0;
    vals.forEach(function (v) {
      if (term && v.toLowerCase().indexOf(term) === -1) return;
      shown++;
      var on = picked.indexOf(v) !== -1;
      var label = DISPLAY[v] || v;
      html += '<label class="xf-opt' + (on ? ' is-on' : '') + '"><input type="checkbox" value="' + escAttr(v) + '"' + (on ? ' checked' : '') + '><span>' + escHtml(label) + '</span></label>';
    });
    list.innerHTML = shown ? html : '<div class="xf-loading">' + <?= json_encode(__('Tidak ada yang cocok.'), JSON_UNESCAPED_UNICODE) ?> + '</div>';
    reposition();
    setTimeout(reposition, 0);
  }
  function escHtml(s) { return String(s).replace(/[&<>"']/g, function (c) { return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]; }); }
  function escAttr(s) { return escHtml(s); }
  list.addEventListener('change', function (e) {
    var v = e.target.value;
    if (e.target.checked) { if (picked.indexOf(v) === -1) picked.push(v); }
    else picked = picked.filter(function (x) { return x !== v; });
    e.target.closest('.xf-opt').classList.toggle('is-on', e.target.checked);
  });
  q.addEventListener('input', render);
  function close() { pop.hidden = true; current = null; }

  // ---- Posisi popup filter: fixed + selalu menempel pada tombol ----
  var popAnchor = null;
  function placePop(btn) {
    popAnchor = btn;
    // Pindahkan ke body agar tidak terkurung ancestor ber-transform/overflow.
    if (pop.parentElement !== document.body) { document.body.appendChild(pop); }
    var r = btn.getBoundingClientRect();
    var w = pop.offsetWidth || 280;
    var h = pop.offsetHeight || 320;
    var vw = document.documentElement.clientWidth || window.innerWidth;
    var vh = window.innerHeight || document.documentElement.clientHeight;
    var gap = 6, pad = 8;
    var left = r.left;
    if (left + w > vw - pad) { left = vw - pad - w; }
    if (left < pad) { left = pad; }
    // Pilih sisi (bawah/atas) yang muat, lalu batasi tinggi popup agar
    // tidak menutupi tombol pemicu dan tidak keluar dari viewport.
    var below = (vh - pad) - (r.bottom + gap);
    var above = (r.top - gap) - pad;
    var openUp = (below < Math.min(h, 260)) && (above > below);
    var avail = Math.max(140, Math.min(h, openUp ? above : below));
    pop.style.maxHeight = Math.round(avail) + "px";
    pop.style.overflowY = "auto";
    var top = openUp ? (r.top - gap - avail) : (r.bottom + gap);
    if (top + avail > vh - pad) { top = Math.max(pad, vh - pad - avail); }
    if (top < pad) { top = pad; }
    pop.style.left = Math.round(left) + 'px';
    pop.style.top = Math.round(top) + 'px';
  }
  function reposition() {
    if (pop.hidden || !popAnchor) { return; }
    var r = popAnchor.getBoundingClientRect();
    var vh = window.innerHeight || document.documentElement.clientHeight;
    // Tombol sudah keluar layar -> tutup saja, jangan menggantung di tengah.
    if (r.bottom < 4 || r.top > vh - 4) { close(); return; }
    placePop(popAnchor);
  }
  window.addEventListener('scroll', reposition, { passive: true });
  window.addEventListener('resize', reposition);
  function apply() {
    // replace hidden col inputs for current key
    f.querySelectorAll('input.col-hidden').forEach(function (i) { if (i.name.indexOf('col[' + current + ']') === 0) i.remove(); });
    picked.forEach(function (v) {
      var h = document.createElement('input');
      h.type = 'hidden'; h.className = 'col-hidden'; h.name = 'col[' + current + '][]'; h.value = v;
      f.appendChild(h);
    });
    f.requestSubmit ? f.requestSubmit() : f.submit();
  }
  // ---- Sort dari menu filter (seperti Excel) ----
  function applySort(dir) {
    var old = f.querySelectorAll('input.sort-hidden');
    old.forEach(function (i) { i.remove(); });
    var k = document.createElement('input'); k.type = 'hidden'; k.className = 'sort-hidden'; k.name = 'sort'; k.value = current;
    var d = document.createElement('input'); d.type = 'hidden'; d.className = 'sort-hidden'; d.name = 'dir'; d.value = dir;
    f.appendChild(k); f.appendChild(d);
    f.requestSubmit ? f.requestSubmit() : f.submit();
  }
  var xfSortAsc = document.getElementById('xf-sort-asc');
  var xfSortDesc = document.getElementById('xf-sort-desc');
  if (xfSortAsc) xfSortAsc.addEventListener('click', function () { applySort('asc'); });
  if (xfSortDesc) xfSortDesc.addEventListener('click', function () { applySort('desc'); });
  function markSortState() {
    document.querySelectorAll('.xf-sort-btn').forEach(function (b) {
      var on = current && sortKey === current && sortDir && b.dataset.dir === (sortDir === 'DESC' ? 'desc' : 'asc');
      b.classList.toggle('is-on', !!on);
    });
  }
  document.querySelectorAll('.xf-btn').forEach(function (b) {
    b.addEventListener('click', function (e) {
      e.stopPropagation();
      if (!pop.hidden && current === b.getAttribute('data-xf')) { close(); return; }
      openPop(b);
    });
  });
  var xfApply = document.getElementById('xf-apply');
  var xfCancel = document.getElementById('xf-cancel');
  var xfClear = document.getElementById('xf-clear');
  if (xfApply) xfApply.addEventListener('click', apply);
  if (xfCancel) xfCancel.addEventListener('click', close);
  if (xfClear) xfClear.addEventListener('click', function () { picked = []; render(); });
  document.addEventListener('click', function (e) { if (!pop.hidden && !pop.contains(e.target)) close(); });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape') close(); });

  // Quick search: debounce, no Enter needed
  var s = document.getElementById('search'), t = null;
  if (s) {
    s.addEventListener('input', function () { clearTimeout(t); t = setTimeout(function () { f.requestSubmit ? f.requestSubmit() : f.submit(); }, 350); });
    s.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); clearTimeout(t); f.requestSubmit ? f.requestSubmit() : f.submit(); } });
  }


})();
  }
  
  // Run init when DOM is ready, handling all loading states
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
</script>

    <?= View::partial('partials/pagination', ['paginator' => $items, 'query' => $cleanQuery]) ?>
</div>

<!-- Bottom Action Bar (Mobile/Tablet) -->
<div class="bottom-action-bar">
    <button type="button" class="btn btn-primary" data-scan-open style="flex: 1;">
        <?= icon('scan') ?> Scan Barcode
    </button>
    <button type="button" class="btn btn-outline" onclick="document.querySelector('#search').focus()" style="flex: 0 0 auto;">
        <?= icon('search') ?> Cari
    </button>
</div>


<!-- Modal scanner barcode -->
<div class="modal-backdrop" data-scan-modal role="dialog" aria-modal="true" aria-label="<?= __('Scan barcode') ?>">
    <div class="modal">
        <div class="modal-header">
            <h3><?= icon('scan') ?> Scan Barcode / QR</h3>
            <div class="spacer"></div>
            <button type="button" class="icon-btn" data-scan-close aria-label="<?= __('Tutup') ?>"><?= icon('x') ?></button>
        </div>
        <div class="modal-body">
            <div id="scanner-viewport"></div>
            <p class="scanner-hint">Arahkan kamera ke barcode atau QR code. Hasil scan otomatis mengisi pencarian lalu memfilter tabel.</p>
        </div>
    </div>
</div>

<script src="<?= asset_v('js/table-colmgr.js') ?>" defer></script>
<script>
(function () {
    var MEMO_URL = '/items/memo/preview';
    var PDF_URL = '/items/memo.pdf';
    var all = document.getElementById('memo-all');
    var cnt = document.getElementById('memo-count');
    function boxes() { return Array.prototype.slice.call(document.querySelectorAll('.memo-check')); }
    function visible(cb) { var r = cb.closest('tr'); return r && r.style.display !== 'none' && !r.hidden; }
    function picked() { return boxes().filter(function (c) { return c.checked; }).map(function (c) { return c.value; }); }
    function refresh() {
        var ids = picked();
        cnt.textContent = ids.length ? (ids.length + ' dipilih') : '';
        if (all) {
            var vis = boxes().filter(visible);
            var chk = vis.filter(function (c) { return c.checked; }).length;
            all.checked = vis.length > 0 && chk === vis.length;
            all.indeterminate = chk > 0 && chk < vis.length;
        }
    }

    // Delegasi klik: pasang handler di dokumen supaya tetap hidup & responsif.
    document.addEventListener('change', function (e) {
        var t = e.target;
        if (!t) return;
        if (t.id === 'memo-all' || (t.closest && t.closest('.th-select-all'))) {
            boxes().filter(visible).forEach(function (c) { c.checked = !!t.checked; });
            refresh(); return;
        }
        if (t.classList && t.classList.contains('memo-check')) refresh();
    });
    refresh();
})();
</script>

<!-- Barcode Scanner + Posting Modal -->
<script id="sf-groups-data" type="application/json"><?= json_encode(array_values(array_map(static fn ($g) => ['id' => (int) $g['id'], 'code' => (string) $g['code'], 'name' => (string) $g['name']], $groups ?? [])), JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP) ?></script>
<script src="<?= asset_v('js/items-barcode-posting.js') ?>"></script>
<script src="<?= asset_v('js/items-dragdrop-columns.js') ?>"></script>
<script src="<?= asset_v('js/items-recount-button.js') ?>"></script>
<script src="<?= asset_v('js/sf-rowdetail.js') ?>" defer></script>
