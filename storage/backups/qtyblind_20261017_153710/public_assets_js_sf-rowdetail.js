/* ==========================================================================
   sf-rowdetail.js — Row expand/collapse + Detail drawer (kanan → kiri)
   Dipakai oleh tabel yang punya atribut  data-rowdetail  pada <table>,
   dan  data-rd='{json}'  pada setiap <tr>.
   ========================================================================== */
(function () {
    'use strict';

    var TABLES = document.querySelectorAll('table[data-rowdetail]');
    if (!TABLES.length) return;

    /* ---------------- util ---------------- */
    function esc(s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    }
    function num(n) {
        if (n === null || n === undefined || n === '') return '—';
        var v = Number(n);
        if (!isFinite(v)) return esc(n);
        return v.toLocaleString('id-ID');
    }
    function money(n) {
        if (n === null || n === undefined || n === '') return '—';
        var v = Number(n);
        if (!isFinite(v)) return esc(n);
        return 'Rp ' + v.toLocaleString('id-ID');
    }
    function dt(s) {
        if (!s) return '—';
        var d = new Date(String(s).replace(' ', 'T'));
        if (isNaN(d.getTime())) return esc(s);
        var opt = { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' };
        try { return d.toLocaleString('id-ID', opt); } catch (e) { return esc(s); }
    }
    var COUNT = {
        pending: ['Belum dihitung', 'is-red'],
        counted: ['Sudah dihitung', 'is-green'],
        posted: ['Terposting', 'is-green']
    };
    var AUDIT = {
        pending: ['Belum diaudit', 'is-red'],
        audited: ['Sesuai', 'is-green'],
        discrepancy: ['Selisih', 'is-red'],
        resolved: ['Selesai', 'is-brand'],
        recount: ['Hitung ulang', 'is-amber']
    };
    function pill(map, key) {
        var m = map[key] || [key || '—', 'is-grey'];
        return '<span class="rd-pill ' + m[1] + '"><i class="rd-dot"></i>' + esc(m[0]) + '</span>';
    }
    function payload(row) {
        try { return JSON.parse(row.getAttribute('data-rd') || '{}'); } catch (e) { return {}; }
    }

    /* ---------------- drawer ---------------- */
    var drawer = null, backdrop = null, lastFocus = null, activeRow = null;

    function buildDrawer() {
        if (drawer) return;
        backdrop = document.createElement('div');
        backdrop.className = 'rd-backdrop';
        backdrop.setAttribute('data-rd-close', '');

        drawer = document.createElement('aside');
        drawer.className = 'rd-drawer';
        drawer.setAttribute('role', 'dialog');
        drawer.setAttribute('aria-modal', 'true');
        drawer.setAttribute('aria-label', 'Detail item');
        drawer.setAttribute('tabindex', '-1');
        drawer.innerHTML =
            '<header class="rd-head">' +
                '<div class="rd-head-main">' +
                    '<div class="rd-eyebrow">Detail Item</div>' +
                    '<h2 class="rd-title" data-rd-title>—</h2>' +
                    '<div class="rd-head-badges" data-rd-badges></div>' +
                '</div>' +
                '<button type="button" class="rd-x" data-rd-close aria-label="Tutup detail">' +
                    '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg>' +
                '</button>' +
            '</header>' +
            '<div class="rd-body" data-rd-body></div>' +
            '<footer class="rd-foot">' +
                '<a class="rd-btn rd-btn-ghost" data-rd-link href="#">Tutup</a>' +
                '<a class="rd-btn rd-btn-primary" data-rd-page href="#">Buka Halaman Item</a>' +
            '</footer>';

        document.body.appendChild(backdrop);
        document.body.appendChild(drawer);

        drawer.addEventListener('click', function (e) {
            if (e.target.closest('[data-rd-close]')) closeDrawer();
        });
        backdrop.addEventListener('click', closeDrawer);
    }

    function section(title, rows) {
        var html = rows.map(function (r) {
            var txt = r.raw ? '' : String(r.v == null ? '' : r.v).trim();
            var empty = !r.raw && (txt === '' || txt === '—');
            return '<div class="rd-cell' + (r.wide ? ' is-wide' : '') + '">' +
                '<div class="rd-k">' + esc(r.k) + '</div>' +
                '<div class="rd-v' + (r.mono ? ' is-mono' : '') + (empty ? ' is-empty' : '') + '">' +
                    (r.raw ? r.v : esc(r.v)) + '</div>' +
            '</div>';
        }).join('');
        return '<section class="rd-sec"><h3 class="rd-sec-t">' + esc(title) + '</h3>' +
            '<div class="rd-grid">' + html + '</div></section>';
    }

    function render(d) {
        var title = drawer.querySelector('[data-rd-title]');
        var badges = drawer.querySelector('[data-rd-badges]');
        var body = drawer.querySelector('[data-rd-body]');

        title.textContent = d.name || '(tanpa nama)';
        badges.innerHTML =
            '<span class="rd-code">' + esc(d.code || '—') + '</span>' +
            pill(COUNT, d.count_status) + pill(AUDIT, d.audit_status) +
            (d.locked ? '<span class="rd-pill is-lock">Terkunci</span>' : '');

        /* hero: angka utama */
        var diff = (d.expected !== undefined && d.expected !== null && d.counted !== null && d.counted !== undefined)
            ? Number(d.counted) - Number(d.expected) : null;
        var ICON_BOX = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21 8l-9-5-9 5 9 5 9-5z"/><path d="M3 8v8l9 5 9-5V8"/><path d="M12 13v8"/></svg>';
        var ICON_CHECK = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg>';
        var ICON_CLIP = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="8" y="3" width="8" height="4" rx="1.2"/><path d="M16 5h2a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2h2"/><path d="M9 14l2 2 4-4"/></svg>';
        var SPARK = '<svg class="rd-mc-spark" viewBox="0 0 64 22" fill="none" aria-hidden="true"><path d="M1 16L9 12L17 14L25 8L33 11L41 6L49 9L57 4L63 6" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>';

        function metric(o) {
            return '<div class="rd-mc ' + o.tone + '">' +
                '<div class="rd-mc-top">' +
                    '<span class="rd-mc-ico">' + o.icon + '</span>' +
                    '<span class="rd-mc-k">' + esc(o.k) + '</span>' +
                '</div>' +
                '<div class="rd-mc-v' + (o.empty ? ' is-empty' : '') + '">' +
                    (o.empty ? '<span class="rd-mc-none" aria-label="belum ada nilai"></span>' : o.v + (o.unit ? '<em>' + esc(o.unit) + '</em>' : '')) +
                '</div>' +
                '<div class="rd-mc-bot">' +
                    '<span class="rd-mc-chip">' + esc(o.chip) + '</span>' +
                    (o.empty ? '' : SPARK) +
                '</div>' +
            '</div>';
        }

        var hasCount = d.counted !== null && d.counted !== undefined;
        var hero = '<div class="rd-hero is-stack">' +
            metric({
                tone: 'is-violet', icon: ICON_BOX, k: 'Qty Sistem',
                v: (d.expected === undefined || d.expected === null) ? '—' : num(d.expected),
                unit: d.unit || '', chip: 'stok tercatat'
            }) +
            metric({
                tone: 'is-mint', icon: ICON_CHECK, k: 'Hitung Fisik',
                v: hasCount ? num(d.counted) : '—',
                empty: !hasCount,
                unit: hasCount ? (d.unit || '') : '',
                chip: hasCount ? 'sudah dihitung' : 'belum dihitung'
            }) +
            metric({
                tone: (diff !== null && diff !== 0) ? 'is-rose' : 'is-blue', icon: ICON_CLIP, k: 'Selisih',
                v: diff === null ? '—' : (diff > 0 ? '+' : '') + num(diff),
                empty: diff === null,
                unit: diff === null ? '' : (d.unit || ''),
                chip: diff === null ? 'menunggu hitung' : (diff === 0 ? 'sesuai' : 'ada selisih')
            }) +
        '</div>';

        var diffVal = (d.expected !== undefined && d.expected !== null && d.counted !== null && d.counted !== undefined)
            ? (Number(d.counted) - Number(d.expected)) : null;
        function keep(list) {
            return list.filter(function (r) { return !r.opt || (r.v && String(r.v) !== '—'); });
        }

        var ident = section('Ringkasan', keep([
            { k: 'Kategori', v: d.category || '—' },
            { k: 'Group', v: d.group ? (d.group_code ? d.group + ' · ' + d.group_code : d.group) : '—' },
            { k: 'Lokasi', v: d.location || '—', opt: true },
            { k: 'Satuan', v: d.unit || '—' },
            { k: 'Harga Satuan', v: money(d.price) },
            { k: 'Device', v: d.device || '—', opt: true }
        ]));

        var detailRows = [
            { k: 'Qty Sistem', v: d.expected === undefined || d.expected === null ? '—' : num(d.expected) },
            { k: 'Observer', v: d.observers || '—', opt: true },
            { k: 'Auditor', v: d.auditors || '—', opt: true },
            { k: 'Dihitung oleh', v: d.counted_by || '—', opt: true },
            { k: 'Waktu hitung', v: dt(d.counted_at), opt: true },
            { k: 'Diposting oleh', v: d.posted_by || '—', opt: true },
            { k: 'Waktu posting', v: dt(d.posted_at), opt: true },
            { k: 'Terkunci', v: d.locked ? 'Ya — butuh izin unlock' : 'Tidak' },
            { k: 'Catatan', v: d.note || '—', wide: true, opt: true }
        ];
        var detail = section('Detail Lengkap', keep(detailRows));

        body.innerHTML = hero + ident + detail;
        body.scrollTop = 0;

        var page = drawer.querySelector('[data-rd-page]');
        page.href = d.url || '#';
        drawer.querySelector('[data-rd-link]').onclick = function (e) { e.preventDefault(); closeDrawer(); };
    }

    function openDrawer(row) {
        buildDrawer();
        activeRow = row;
        render(payload(row));
        lastFocus = document.activeElement;
        document.documentElement.classList.add('rd-lock');
        row.classList.add('is-active-row');

        /* paksa layout keadaan "tertutup" dulu agar transisi slide tetap jalan,
           lalu buka secara sinkron: requestAnimationFrame bisa ditahan browser
           saat tab background / bfcache sehingga drawer gagal muncul. */
        void drawer.offsetHeight;
        drawer.classList.add('is-open');
        backdrop.classList.add('is-open');
        setTimeout(function () {
            if (!drawer.classList.contains('is-open')) drawer.classList.add('is-open');
            if (!backdrop.classList.contains('is-open')) backdrop.classList.add('is-open');
        }, 40);
        try { drawer.focus({ preventScroll: true }); } catch (e) {}
        syncMore();
    }

    /* indikator "masih ada isi di bawah" */
    function syncMore() {
        var body = drawer && drawer.querySelector('[data-rd-body]');
        if (!body) return;
        var more = body.scrollHeight - body.scrollTop - body.clientHeight > 8;
        drawer.classList.toggle('has-more', more);
    }
    document.addEventListener('scroll', function (e) {
        if (drawer && e.target && e.target.hasAttribute && e.target.hasAttribute('data-rd-body')) syncMore();
    }, true);
    window.addEventListener('resize', function () { if (drawer && drawer.classList.contains('is-open')) syncMore(); });

    function closeDrawer() {
        if (!drawer) return;
        drawer.classList.remove('is-open');
        backdrop.classList.remove('is-open');
        document.documentElement.classList.remove('rd-lock');
        drawer.classList.remove('has-more');
        if (activeRow) { activeRow.classList.remove('is-active-row'); activeRow = null; }
        if (lastFocus && lastFocus.focus) { try { lastFocus.focus({ preventScroll: true }); } catch (e) {} }
    }

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && drawer && drawer.classList.contains('is-open')) {
            e.preventDefault();
            closeDrawer();
        }
    });

    /* ---------------- expand / collapse ---------------- */
    function colspan(row) {
        var table = row.closest('table');
        var head = table && table.tHead && table.tHead.rows[0];
        return head ? head.cells.length : row.cells.length;
    }

    function expandPanel(d, row) {
        var rows = [
            ['Kode', d.code || '—', 1],
            ['Kategori', d.category || '—', 0],
            ['Group', d.group || '—', 0],
            ['Lokasi', d.location || '—', 0],
            ['Device', d.device || '—', 0],
            ['Satuan', d.unit || '—', 0],
            ['Harga', money(d.price), 0],
            ['Hitung fisik', num(d.counted), 0],
            ['Qty sistem', d.expected === undefined || d.expected === null ? '—' : num(d.expected), 0],
            ['Dihitung oleh', d.counted_by || '—', 0],
            ['Waktu hitung', dt(d.counted_at), 0]
        ];
        var grid = rows.map(function (r) {
            return '<div class="rd-xcell"><span>' + esc(r[0]) + '</span><strong' + (r[2] ? ' class="is-mono"' : '') + '>' + esc(r[1]) + '</strong></div>';
        }).join('');

        return '<div class="rd-xpanel">' +
            '<div class="rd-xhead">' +
                '<div class="rd-xtop">' +
                    '<span class="rd-xcode">' + esc(d.code || '') + '</span>' +
                    '<strong>' + esc(d.name || '') + '</strong>' +
                    '<div class="rd-xspacer"></div>' +
                    '<button type="button" class="rd-xbtn" data-rd-open>Detail lengkap</button>' +
                '</div>' +
                '<div class="rd-xstat">' + pill(COUNT, d.count_status) + pill(AUDIT, d.audit_status) + '</div>' +
            '</div>' +
            '<div class="rd-xgrid">' + grid + '</div>' +
            '<div class="rd-xoff">' +
                '<span class="rd-xoff-i is-obs"><i>OBS</i><b>' + esc(d.observers || '—') + '</b></span>' +
                '<span class="rd-xoff-i is-aud"><i>AUD</i><b>' + esc(d.auditors || '—') + '</b></span>' +
            '</div>' +
        '</div>';
    }

    function toggleRow(row, btn) {
        var next = row.nextElementSibling;
        if (next && next.classList.contains('rd-xrow')) {
            next.classList.add('is-closing');
            row.classList.remove('is-expanded');
            btn.setAttribute('aria-expanded', 'false');
            setTimeout(function () { if (next.parentNode) next.parentNode.removeChild(next); }, 160);
            return;
        }
        var tr = document.createElement('tr');
        tr.className = 'rd-xrow';
        var td = document.createElement('td');
        td.colSpan = colspan(row);
        td.className = 'rd-xcellwrap';
        td.innerHTML = expandPanel(payload(row), row);
        // batasi lebar panel ke lebar area tabel yang terlihat
        var tbl = row.closest('table');
        var wrap = tbl ? (tbl.closest('.table-wrap') || tbl.parentElement) : null;
        if (wrap && wrap.clientWidth) {
            td.querySelector('.rd-xpanel').style.maxWidth = Math.max(280, wrap.clientWidth - 22) + 'px';
        }
        tr.appendChild(td);
        row.parentNode.insertBefore(tr, row.nextSibling);
        row.classList.add('is-expanded');
        btn.setAttribute('aria-expanded', 'true');
        void tr.offsetHeight;
        tr.classList.add('is-in');
        setTimeout(function () { if (tr.parentNode) tr.classList.add('is-in'); }, 40);
        td.querySelector('[data-rd-open]').addEventListener('click', function () { openDrawer(row); });
    }

    /* ---------------- wiring ---------------- */
    TABLES.forEach(function (table) {
        var tbody = table.tBodies[0];
        if (!tbody) return;

        Array.prototype.forEach.call(tbody.rows, function (row) {
            if (!row.hasAttribute('data-rd')) return;
            var host = row.querySelector('.code-cell') || row.querySelector('.cell-title') || row.cells[2];
            if (!host || host.querySelector('.rd-toggle')) return;
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'rd-toggle';
            btn.setAttribute('aria-expanded', 'false');
            btn.setAttribute('aria-label', 'Buka detail baris');
            btn.title = 'Expand / collapse detail baris';
            btn.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="m9 6 6 6-6 6"/></svg>';
            host.insertBefore(btn, host.firstChild);
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();
                /* mobile/tablet: detail selalu slide dari kanan (drawer),
                   desktop: expand inline */
                if (window.matchMedia('(max-width: 900px)').matches) {
                    openDrawer(row);
                    return;
                }
                toggleRow(row, btn);
            });
        });

        table.addEventListener('click', function (e) {
            var tr = e.target.closest('tbody tr');
            if (!tr || !tr.hasAttribute('data-rd')) return;
            if (tr.classList.contains('rd-xrow')) return;
            if (e.target.closest('a,button,input,select,textarea,label,.memo-pick,.rd-toggle,.btn,.cell-action,form')) return;
            var sel = window.getSelection && String(window.getSelection());
            if (sel && sel.length > 2) return;
            openDrawer(tr);
        });
    });
})();
