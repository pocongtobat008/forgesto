<?php
/**
 * Menu Progress — ringkasan progres penghitungan & audit per group.
 * Detail per group tampil pada drawer yang meluncur dari kanan.
 *
 * @var array<int, array<string, mixed>> $rows
 * @var array<string, int>               $totals
 * @var array<string, mixed>|null        $period
 */

$months = ['01' => 'Jan', '02' => 'Feb', '03' => 'Mar', '04' => 'Apr', '05' => 'Mei', '06' => 'Jun',
           '07' => 'Jul', '08' => 'Agu', '09' => 'Sep', '10' => 'Okt', '11' => 'Nov', '12' => 'Des'];

$periodLabel = __('Semua periode');
if (is_array($period) && ! empty($period['audit_month'])) {
    $parts = explode('-', (string) $period['audit_month']);
    $mm = $parts[1] ?? '';
    $yy = $parts[0] ?? '';
    if ($mm !== '' && $yy !== '') {
        $periodLabel = ($months[$mm] ?? $mm) . " '" . substr($yy, -2);
    }
}

$initials = static function (string $name): string {
    $name = trim($name);
    if ($name === '') {
        return '?';
    }
    $words = preg_split('/\s+/', $name) ?: [];
    $out = '';
    foreach ($words as $w) {
        if ($w === '' || ! preg_match('/[A-Za-z0-9]/', $w)) {
            continue;
        }
        $out .= strtoupper(substr($w, 0, 1));
        if (strlen($out) >= 2) {
            break;
        }
    }
    return $out !== '' ? $out : strtoupper(substr($name, 0, 2));
};

$stateLabel = [
    'done'    => __('Tuntas'),
    'running' => __('Berjalan'),
    'idle'    => __('Belum mulai'),
    'empty'   => __('Kosong'),
];

$tone = static function (int $pct, int $total): string {
    if ($total === 0) {
        return 'is-idle';
    }
    if ($pct >= 100) {
        return 'is-success';
    }
    if ($pct >= 50) {
        return 'is-brand';
    }
    return $pct > 0 ? 'is-warning' : 'is-idle';
};
?>

<?= page_hero() ?>
<div class="page-head has-hero">
    <div>
        <h1><?= __('Progress') ?></h1>
        <p><?= __('Progres penghitungan & audit tiap group. Klik salah satu group untuk melihat rinciannya.') ?></p>
    </div>
    <div class="spacer"></div>
    <span class="sf-pill prg-period"><?= icon('archive') ?> <?= e($periodLabel) ?></span>
</div>

<section class="prg-hero">
    <div class="prg-hero-main">
        <span class="prg-hero-glow" aria-hidden="true"></span>
        <svg class="prg-hero-deco" viewBox="0 0 200 200" fill="none" aria-hidden="true">
            <circle cx="100" cy="100" r="96" stroke="rgba(255,255,255,.5)" stroke-width="1.2"/>
            <circle cx="100" cy="100" r="72" stroke="rgba(255,255,255,.4)" stroke-width="1.2"/>
            <circle cx="100" cy="100" r="48" stroke="rgba(255,255,255,.3)" stroke-width="1.2"/>
        </svg>
        <div class="prg-hero-body">
            <span class="prg-hero-kicker"><?= __('Progres periode ini') ?></span>
            <div class="prg-hero-num">
                <b><?= (int) round((float) $totals['count_pct']) ?></b><i>%</i>
            </div>
            <p class="prg-hero-sub">
                <?= sprintf(__('%d dari %d item sudah dihitung'), (int) $totals['counted'], (int) $totals['items']) ?>
            </p>

            <div class="prg-hero-bars">
                <div class="prg-hero-bar">
                    <span class="prg-hero-bar-label"><?= __('Hitung') ?></span>
                    <span class="prg-hero-track"><span class="prg-hero-fill" style="width:<?= max(0, min(100, (int) round((float) $totals['count_pct']))) ?>%"></span></span>
                    <b><?= (int) round((float) $totals['count_pct']) ?>%</b>
                </div>
                <div class="prg-hero-bar is-audit">
                    <span class="prg-hero-bar-label"><?= __('Audit') ?></span>
                    <span class="prg-hero-track"><span class="prg-hero-fill" style="width:<?= max(0, min(100, (int) round((float) $totals['audit_pct']))) ?>%"></span></span>
                    <b><?= (int) round((float) $totals['audit_pct']) ?>%</b>
                </div>
            </div>
        </div>
    </div>

    <div class="prg-kpis">
        <div class="prg-kpi">
            <span><?= __('Total item') ?></span>
            <b><?= number_format((int) $totals['items'], 0, ',', '.') ?></b>
            <i><?= sprintf(__('%d group'), (int) $totals['groups']) ?></i>
        </div>
        <div class="prg-kpi is-success">
            <span><?= __('Group tuntas') ?></span>
            <b><?= (int) $totals['done'] ?></b>
            <i><?= __('100% dihitung') ?></i>
        </div>
        <div class="prg-kpi is-warning">
            <span><?= __('Sedang berjalan') ?></span>
            <b><?= (int) $totals['running'] ?></b>
            <i><?= __('Sudah ada progres') ?></i>
        </div>
        <div class="prg-kpi is-idle">
            <span><?= __('Belum mulai') ?></span>
            <b><?= (int) ($totals['groups'] - $totals['done'] - $totals['running']) ?></b>
            <i><?= __('0% dihitung') ?></i>
        </div>
    </div>
</section>

<div class="prg-toolbar">
    <div class="prg-search">
        <?= icon('search') ?>
        <input type="search" id="prg-search" placeholder="<?= __('Cari nama atau kode group...') ?>" autocomplete="off" aria-label="<?= __('Cari group') ?>">
    </div>
    <div class="prg-chips" role="group" aria-label="<?= __('Filter status group') ?>">
        <button type="button" class="prg-chip is-active" data-state="all"><?= __('Semua') ?> <span><?= (int) $totals['groups'] ?></span></button>
        <button type="button" class="prg-chip" data-state="running"><?= __('Berjalan') ?> <span><?= (int) $totals['running'] ?></span></button>
        <button type="button" class="prg-chip" data-state="done"><?= __('Tuntas') ?> <span><?= (int) $totals['done'] ?></span></button>
        <button type="button" class="prg-chip" data-state="idle"><?= __('Belum mulai') ?> <span><?= (int) ($totals['groups'] - $totals['done'] - $totals['running']) ?></span></button>
    </div>
    <div class="prg-sort">
        <label for="prg-sort" class="text-xs muted"><?= __('Urutkan') ?></label>
        <select id="prg-sort" class="form-select form-select-xs">
            <option value="name"><?= __('Nama (A-Z)') ?></option>
            <option value="progress_desc"><?= __('Progres tertinggi') ?></option>
            <option value="progress_asc"><?= __('Progres terendah') ?></option>
        </select>
    </div>
</div>

<?php if ($rows === []): ?>
    <div class="card">
        <div class="empty-state">
            <div class="empty-icon"><?= icon('layers') ?></div>
            <h3><?= __('Belum ada group') ?></h3>
            <p><?= __('Tambahkan group terlebih dahulu melalui menu Group.') ?></p>
        </div>
    </div>
<?php else: ?>
    <div class="prg-grid" id="prg-grid">
        <?php foreach ($rows as $r):
            $pct    = (int) round((float) $r['count_pct']);
            $apct   = (int) round((float) $r['audit_pct']);
            $total  = (int) $r['total'];
            $counted = (int) $r['counted'];
            $audited = (int) $r['audited'];
            $state  = (string) $r['state'];
            $toneCls = $tone($pct, $total);

            // Petugas group: tampilkan nama pertama + jumlah sisanya.
            $officerFirst = static function (string $list): array {
                $list = trim($list);
                if ($list === '') {
                    return ['', 0];
                }
                $parts = array_values(array_filter(array_map('trim', explode('|', $list)), static fn ($x) => $x !== ''));
                return [$parts[0] ?? '', max(0, count($parts) - 1)];
            };
            $obsRaw = (string) ($r['observers'] ?? '');
            $audRaw = (string) ($r['auditors'] ?? '');
            [$obsName, $obsMore] = $officerFirst($obsRaw);
            [$audName, $audMore] = $officerFirst($audRaw);
        ?>
        <button type="button"
                class="prg-card is-<?= e($state) ?>"
                data-group="<?= (int) $r['id'] ?>"
                data-name="<?= e(mb_strtolower((string) $r['name'])) ?>"
                data-code="<?= e(mb_strtolower((string) $r['code'])) ?>"
                data-state="<?= e($state) ?>"
                data-pct="<?= $pct ?>"
                aria-haspopup="dialog">
            <span class="prg-card-top">
                <span class="prg-ava <?= $toneCls ?>"><?= e($initials((string) $r['name'])) ?></span>
                <span class="prg-card-id">
                    <span class="prg-card-name" title="<?= e((string) $r['name']) ?>"><?= e((string) $r['name']) ?></span>
                    <span class="prg-card-code"><?= e((string) $r['code']) ?></span>
                </span>
                <span class="prg-card-pct"><?= $pct ?><i>%</i></span>
            </span>

            <span class="sfp-track prg-track<?= $total === 0 ? ' is-empty' : '' ?>">
                <span class="sfp-fill <?= $toneCls ?>" style="width:<?= max(0, min(100, $pct)) ?>%"></span>
                <?php if ($apct > 0): ?><span class="sfp-mark" style="left:<?= max(2, min(98, $apct)) ?>%"></span><?php endif; ?>
            </span>

            <span class="prg-card-officers">
                <span class="prg-role is-obs<?= $obsName === '' ? ' is-none' : '' ?>" title="<?= e(__('Observer') . ': ' . ($obsRaw !== '' ? $obsRaw : __('belum ditugaskan'))) ?>">
                    <i>OBS</i><b><?= e($obsName !== '' ? $obsName : '—') ?></b><?php if ($obsMore > 0): ?><em>+<?= $obsMore ?></em><?php endif; ?>
                </span>
                <span class="prg-role is-aud<?= $audName === '' ? ' is-none' : '' ?>" title="<?= e(__('Audit') . ': ' . ($audRaw !== '' ? $audRaw : __('belum ditugaskan'))) ?>">
                    <i>AUD</i><b><?= e($audName !== '' ? $audName : '—') ?></b><?php if ($audMore > 0): ?><em>+<?= $audMore ?></em><?php endif; ?>
                </span>
            </span>

            <span class="prg-card-foot">
                <span class="prg-card-count"><b><?= $counted ?></b>/<?= $total ?> <?= __('item') ?></span>
                <span class="prg-card-dot"></span>
                <span class="prg-card-audit"><?= $audited ?> <?= __('diaudit') ?></span>
                <span class="prg-badge is-<?= e($state) ?>"><?= e($stateLabel[$state] ?? $state) ?></span>
            </span>
        </button>
        <?php endforeach; ?>
    </div>

    <p class="prg-empty" id="prg-empty" hidden><?= icon('search') ?> <?= __('Tidak ada group yang cocok dengan filter.') ?></p>
<?php endif; ?>

<?php
// Hero + KPI + kartu sudah menampilkan ringkasan yang sama,
// jadi blok duplikat di partial tidak perlu dirender ulang.
$prgHideDuplicates = true;
require __DIR__ . '/../partials/progress_overview.php'; ?>

<!-- Drawer detail group -->
<div class="prg-backdrop" id="prg-backdrop" hidden></div>
<aside class="prg-drawer" id="prg-drawer" role="dialog" aria-modal="true" aria-labelledby="prg-d-title" hidden>
    <header class="prg-drawer-head">
        <div class="prg-drawer-title">
            <span class="prg-drawer-kicker"><?= __('Detail group') ?></span>
            <h2 id="prg-d-title">—</h2>
            <span class="prg-drawer-code" id="prg-d-code"></span>
        </div>
        <button type="button" class="icon-btn prg-drawer-close" id="prg-close" aria-label="<?= __('Tutup') ?>"><?= icon('x') ?></button>
    </header>
    <div class="prg-drawer-body" id="prg-d-body">
        <div class="prg-d-loading"><span class="prg-spinner" aria-hidden="true"></span> <?= __('Memuat detail group...') ?></div>
    </div>
    <footer class="prg-drawer-foot" id="prg-d-foot" hidden></footer>
</aside>

<script>
(function () {
    'use strict';

    var grid     = document.getElementById('prg-grid');
    var drawer   = document.getElementById('prg-drawer');
    var backdrop = document.getElementById('prg-backdrop');
    var body     = document.getElementById('prg-d-body');
    var foot     = document.getElementById('prg-d-foot');
    var titleEl  = document.getElementById('prg-d-title');
    var codeEl   = document.getElementById('prg-d-code');
    var closeBtn = document.getElementById('prg-close');
    var emptyMsg = document.getElementById('prg-empty');

    /* PASS21: keluarkan drawer dari .content/.app-shell.
       .app-shell punya z-index:1 (sf-glass.css) dan .content punya animasi opacity,
       keduanya membentuk stacking context -> drawer (z-index 1001) terkurung:
       header tertutup topbar dan footer tertimpa pil "Tampilan" (.sf-vm, z-index 940). */
    if (drawer.parentNode !== document.body) document.body.appendChild(drawer);
    if (backdrop.parentNode !== document.body) document.body.appendChild(backdrop);

    var endpoint = <?= json_encode(url('progress/group'), JSON_UNESCAPED_SLASHES) ?>;
    var lastFocus = null;
    var activeGroup = null;

    function esc(s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }
    function clamp(n) { return Math.max(0, Math.min(100, Number(n) || 0)); }
    function num(n) { return Number(n || 0).toLocaleString('id-ID'); }
    function rupiah(n) {
        var v = Number(n || 0);
        var sign = v > 0 ? '+' : (v < 0 ? '-' : '');
        return sign + 'Rp ' + Math.abs(v).toLocaleString('id-ID', { maximumFractionDigits: 0 });
    }
    function toneOf(pct) {
        if (pct >= 100) return 'is-success';
        if (pct >= 50) return 'is-brand';
        return pct > 0 ? 'is-warning' : 'is-idle';
    }

    /* ---------------- filter & sort kartu ---------------- */
    var search = document.getElementById('prg-search');
    var chips  = Array.prototype.slice.call(document.querySelectorAll('.prg-chip'));
    var sortEl = document.getElementById('prg-sort');
    var state  = 'all';

    function applyFilter() {
        if (!grid) return;
        var term = (search && search.value || '').trim().toLowerCase();
        var cards = Array.prototype.slice.call(grid.querySelectorAll('.prg-card'));
        var shown = 0;

        cards.forEach(function (c) {
            var okState = state === 'all' || c.getAttribute('data-state') === state;
            var hay = c.getAttribute('data-name') + ' ' + c.getAttribute('data-code');
            var okTerm = term === '' || hay.indexOf(term) !== -1;
            var show = okState && okTerm;
            c.hidden = !show;
            if (show) shown++;
        });

        if (emptyMsg) emptyMsg.hidden = shown !== 0;
    }

    function applySort() {
        if (!grid || !sortEl) return;
        var mode = sortEl.value;
        var cards = Array.prototype.slice.call(grid.querySelectorAll('.prg-card'));
        cards.sort(function (a, b) {
            if (mode === 'progress_desc' || mode === 'progress_asc') {
                var pa = Number(a.getAttribute('data-pct') || 0);
                var pb = Number(b.getAttribute('data-pct') || 0);
                if (pa !== pb) return mode === 'progress_desc' ? pb - pa : pa - pb;
            }
            return (a.getAttribute('data-name') || '').localeCompare(b.getAttribute('data-name') || '', 'id');
        });
        cards.forEach(function (c) { grid.appendChild(c); });
    }

    if (search) search.addEventListener('input', applyFilter);
    chips.forEach(function (chip) {
        chip.addEventListener('click', function () {
            chips.forEach(function (c) { c.classList.remove('is-active'); });
            chip.classList.add('is-active');
            state = chip.getAttribute('data-state');
            applyFilter();
        });
    });
    if (sortEl) sortEl.addEventListener('change', applySort);

    /* ---------------- drawer ---------------- */
    function openDrawer(card) {
        activeGroup = card.getAttribute('data-group');
        lastFocus = card;

        titleEl.textContent = card.querySelector('.prg-card-name').textContent;
        codeEl.textContent = card.querySelector('.prg-card-code').textContent;

        body.innerHTML = '<div class="prg-d-loading"><span class="prg-spinner" aria-hidden="true"></span> ' +
            <?= json_encode(__('Memuat detail group...'), JSON_UNESCAPED_UNICODE) ?> + '</div>';
        foot.hidden = true;
        foot.innerHTML = '';

        drawer.hidden = false;
        backdrop.hidden = false;
        document.body.classList.add('prg-lock');
        // Paksa reflow supaya transisi translateX benar-benar berjalan
        // (requestAnimationFrame bisa tertunda saat tab tidak aktif).
        void drawer.offsetWidth;
        drawer.classList.add('is-open');
        backdrop.classList.add('is-open');
        drawer.setAttribute('aria-hidden', 'false');
        closeBtn.focus();

        fetch(endpoint + '/' + encodeURIComponent(activeGroup), {
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json' }
        }).then(function (r) {
            return r.json().then(function (d) { return { ok: r.ok, data: d }; });
        }).then(function (res) {
            if (activeGroup !== card.getAttribute('data-group')) return;
            if (!res.ok || !res.data || !res.data.ok) {
                body.innerHTML = '<div class="prg-d-error">' +
                    esc((res.data && res.data.message) || <?= json_encode(__('Gagal memuat detail.'), JSON_UNESCAPED_UNICODE) ?>) +
                    '</div>';
                return;
            }
            renderDetail(res.data);
        }).catch(function () {
            body.innerHTML = '<div class="prg-d-error">' +
                <?= json_encode(__('Gagal memuat detail. Periksa koneksi Anda.'), JSON_UNESCAPED_UNICODE) ?> + '</div>';
        });
    }

    function renderDetail(d) {
        var s = d.stats, L = d.labels, g = d.group;
        var cp = Math.round(clamp(s.countPct)), ap = Math.round(clamp(s.auditPct));
        var html = '';

        html += '<div class="prg-d-hero">';
        html += '  <div class="prg-d-hero-main">';
        html += '    <span class="prg-d-hero-kicker">' + esc(L.hitung) + '</span>';
        html += '    <div class="prg-d-big">' + cp + '<i>%</i></div>';
        html += '    <div class="prg-d-hero-sub">' + num(s.counted) + ' / ' + num(s.total) + ' ' + esc(L.counted).toLowerCase() + '</div>';
        html += '  </div>';
        html += '  <div class="prg-d-hero-side">';
        html += '    <div class="prg-d-side-item"><b>' + ap + '%</b><span>' + esc(L.audit) + '</span></div>';
        html += '    <div class="prg-d-side-item"><b>' + num(s.audited) + '</b><span>' + esc(L.audited) + '</span></div>';
        html += '  </div>';
        html += '</div>';

        html += '<div class="prg-d-bars">';
        html += '  <div class="prg-d-bar"><span class="prg-d-bar-label">' + esc(L.hitung) + '</span>';
        html += '    <span class="prg-d-track"><span class="prg-d-fill ' + toneOf(cp) + '" style="width:' + cp + '%"></span></span>';
        html += '    <b>' + cp + '%</b></div>';
        html += '  <div class="prg-d-bar"><span class="prg-d-bar-label">' + esc(L.audit) + '</span>';
        html += '    <span class="prg-d-track"><span class="prg-d-fill is-audit" style="width:' + ap + '%"></span></span>';
        html += '    <b>' + ap + '%</b></div>';
        html += '</div>';

        if (s.total === 0) {
            html += '<p class="prg-d-note">' + esc(L.empty) + '</p>';
        }

        /* petugas group: observer & auditor */
        html += '<div class="prg-d-officers">';
        html += '  <div class="prg-d-officer is-obs"><i>OBS</i><b>' + esc(g.observers || L.none) + '</b></div>';
        html += '  <div class="prg-d-officer is-aud"><i>AUD</i><b>' + esc(g.auditors || L.none) + '</b></div>';
        html += '</div>';

        html += '<div class="prg-d-stats">';
        var tiles = [
            [L.total, num(s.total), ''],
            [L.counted, num(s.counted), 'is-brand'],
            [L.pending, num(s.pending), 'is-idle'],
            [L.audited, num(s.audited), 'is-success'],
            [L.notAudited, num(s.notAudited), 'is-warning'],
            [L.discrepancy, num(s.discrepancy), s.discrepancy > 0 ? 'is-danger' : '']
        ];
        tiles.forEach(function (t) {
            html += '<div class="prg-d-stat ' + t[2] + '"><span>' + esc(t[0]) + '</span><b>' + t[1] + '</b></div>';
        });
        html += '</div>';

        /* Qty stock & selisih: hanya admin/poweradmin. Role lain melihat nilai tersamar. */
        var canSeeDiff = !!(d.diff && d.diff.visible);
        html += '<div class="prg-d-money">';
        html += '  <div class="prg-d-money-item"><span>' + esc(L.diffQty) + '</span><b class="' +
                (canSeeDiff ? (d.diff.qty < 0 ? 'is-neg' : (d.diff.qty > 0 ? 'is-pos' : '')) : 'is-muted') + '">' +
                (canSeeDiff ? ((d.diff.qty > 0 ? '+' : '') + num(d.diff.qty)) : '***') + '</b></div>';
        html += '  <div class="prg-d-money-item"><span>' + esc(L.diffAmt) + '</span><b class="' +
                (canSeeDiff ? (d.diff.amount < 0 ? 'is-neg' : (d.diff.amount > 0 ? 'is-pos' : '')) : 'is-muted') + '">' +
                (canSeeDiff ? rupiah(d.diff.amount) : '***') + '</b></div>';
        html += '</div>';

        /* status hitung */
        html += '<section class="prg-d-sec"><h3>' + esc(L.hitung) + '</h3>';
        html += breakRow(L.counted, s.counted, s.total, 'is-brand');
        html += breakRow(L.pending, s.pending, s.total, 'is-idle');
        html += breakRow(L.posted, s.posted, s.total, 'is-success');
        if (s.recount > 0) html += breakRow(L.recount, s.recount, s.total, 'is-warning');
        html += '</section>';

        /* status audit */
        html += '<section class="prg-d-sec"><h3>' + esc(L.audit) + '</h3>';
        html += breakRow(L.audited, s.audited, s.total, 'is-success');
        html += breakRow(L.notAudited, s.notAudited, s.total, 'is-idle');
        html += breakRow(L.discrepancy, s.discrepancy, s.total, 'is-danger');
        html += breakRow(L.resolved, s.resolved, s.total, 'is-brand');
        html += '</section>';

        /* lokasi */
        html += '<section class="prg-d-sec"><h3>' + esc(L.loc) + '</h3>';
        if (!d.locations || d.locations.length === 0) {
            html += '<p class="prg-d-muted">' + esc(L.noLoc) + '</p>';
        } else {
            html += '<ul class="prg-d-locs">';
            d.locations.forEach(function (l) {
                var p = Math.round(clamp(l.pct));
                html += '<li><span class="prg-d-loc-name">' + esc(l.name) +
                        '<i>' + esc(l.code || '') + '</i></span>' +
                        '<span class="prg-d-loc-track"><span class="prg-d-loc-fill ' + toneOf(p) + '" style="width:' + p + '%"></span></span>' +
                        '<span class="prg-d-loc-val">' + num(l.counted) + '/' + num(l.total) + '</span></li>';
            });
            html += '</ul>';
        }
        html += '</section>';

        /* top selisih */
        html += '<section class="prg-d-sec"><h3>' + esc(L.top) + '</h3>';
        if (!canSeeDiff) {
            html += '<p class="prg-d-muted">' + esc(L.adminOnly) + '</p>';
        } else if (!d.top || d.top.length === 0) {
            html += '<p class="prg-d-muted">' + esc(L.noTop) + '</p>';
        } else {
            html += '<div class="prg-d-table-wrap"><table class="prg-d-table"><caption class="sr-only">' + esc(L.top) + '</caption><thead><tr>' +
                    '<th>' + esc(L.count) + '</th><th class="t-right">' + esc(L.diffQty) + '</th>' +
                    (d.diff.visible ? '<th class="t-right">' + esc(L.diffAmt) + '</th>' : '') +
                    '</tr></thead><tbody>';
            d.top.forEach(function (t) {
                html += '<tr><td><span class="prg-d-item-code">' + esc(t.code) + '</span>' +
                        '<span class="prg-d-item-name" title="' + esc(t.name) + '">' + esc(t.name) + '</span></td>' +
                        '<td class="t-right"><b class="' + (t.diff < 0 ? 'is-neg' : 'is-pos') + '">' +
                        (t.diff > 0 ? '+' : '') + num(t.diff) + '</b></td>' +
                        (d.diff.visible ? '<td class="t-right">' + rupiah(t.amount) + '</td>' : '') +
                        '</tr>';
            });
            html += '</tbody></table></div>';
        }
        html += '</section>';

        /* waktu */
        if (d.time && (d.time.first || d.time.last)) {
            html += '<div class="prg-d-times">';
            if (d.time.first) html += '<span>' + esc(L.first) + ': <b>' + esc(d.time.first) + '</b></span>';
            if (d.time.last)  html += '<span>' + esc(L.last) + ': <b>' + esc(d.time.last) + '</b></span>';
            html += '</div>';
        }

        body.innerHTML = html;

        foot.innerHTML =
            '<a class="btn btn-brand btn-sm" href="' + esc(d.links.items) + '">' + esc(L.items) + '</a>' +
            '<a class="btn btn-outline btn-sm" href="' + esc(d.links.audit) + '">' + esc(L.auditBtn) + '</a>';
        foot.hidden = false;
        body.scrollTop = 0;
    }

    function breakRow(label, value, total, cls) {
        var pct = total > 0 ? Math.round((Number(value) / Number(total)) * 100) : 0;
        return '<div class="prg-d-break"><span class="prg-d-break-label">' + esc(label) + '</span>' +
               '<span class="prg-d-break-track"><span class="prg-d-break-fill ' + cls + '" style="width:' + clamp(pct) + '%"></span></span>' +
               '<b>' + num(value) + '</b></div>';
    }

    function closeDrawer() {
        if (!drawer || drawer.hidden) return;
        drawer.classList.remove('is-open');
        backdrop.classList.remove('is-open');
        document.body.classList.remove('prg-lock');
        window.setTimeout(function () {
            drawer.hidden = true;
            backdrop.hidden = true;
            body.innerHTML = '';
            foot.hidden = true;
            activeGroup = null;
            if (lastFocus && lastFocus.focus) lastFocus.focus();
        }, 300);
    }

    if (grid) {
        grid.addEventListener('click', function (e) {
            var card = e.target.closest('.prg-card');
            if (card) openDrawer(card);
        });
    }
    if (closeBtn) closeBtn.addEventListener('click', closeDrawer);
    if (backdrop) backdrop.addEventListener('click', closeDrawer);
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') closeDrawer();
    });
})();
</script>
