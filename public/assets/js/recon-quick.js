/* ==========================================================================
   Mode Cepat v3 — input hasil hitung massal (siap data ribuan)

   Perubahan dari v2:
   1. Data diambil dari SERVER (LIMIT/OFFSET), bukan dibaca dari <tr> tabel.
      Jadi berapa pun jumlah barang, yang dirender maksimal 80 baris.
   2. Panel tetap dibuka KOSONG dan daftar editan di-reset setiap kali dibuka.
   3. Angka yang diketik disimpan di Map (bukan di DOM), sehingga baris boleh
      hilang dari layar tanpa kehilangan data.
   4. Tombol "Hanya yang diedit (N)" menampilkan persis baris yang sedang
      diubah beserta jumlahnya.
   ========================================================================== */
(function () {
    'use strict';

    var ready = function (fn) {
        if (document.readyState !== 'loading') { fn(); }
        else { document.addEventListener('DOMContentLoaded', fn); }
    };

    ready(function () {
        var panel = document.getElementById('qe-panel');
        var form = document.getElementById('qe-form');
        if (!panel || !form) { return; }

        /* Panel WAJIB pindah ke <body>. .app-shell punya `position:relative;
           z-index:1` sehingga membuat stacking context sendiri: z-index 1300
           panel hanya berlaku DI DALAMNYA, sementara .topbar (fixed, z-index
           1000) berada di root dan tetap menimpa header panel — inilah yang
           membuat kotak cari tampak "tertutup header". Di body, 1300 > 1000. */
        if (panel.parentNode && panel.parentNode !== document.body) {
            document.body.appendChild(panel);
        }

        var $ = function (id) { return document.getElementById(id); };
        var listEl = $('qe-list'), searchEl = $('qe-search'), gbtn = $('qe-gbtn'), gmenu = $('qe-gmenu'),
            gcount = $('qe-gcount'), onlyBtn = $('qe-only'), onlyN = $('qe-only-n'),
            infoEl = $('qe-filterinfo'), countEl = $('qe-count'), statEl = $('qe-stat'),
            saveBtn = $('qe-save'), saveLabel = $('qe-save-label'), openBtn = $('qe-open'),
            gearBtn = $('qe-gear'), uipop = $('qe-uipop'), uiMeta = $('qe-ui-meta'), uiGroup = $('qe-ui-group'),
            sheet = panel.querySelector('.qe-sheet');

        var tokenEl = form.querySelector('input[name="_token"]');
        var TOKEN = tokenEl ? tokenEl.value : '';
        var ACTION = form.getAttribute('action') || '';
        var BASE = ACTION.replace(/\/quick-count\/?$/, '');
        var UI_KEY = 'sf_qe_ui_v1';
        var PAGE = 80;

        var S = {
            edited: new Map(),   // id -> { rec, val }
            rows: [],            // item yang sedang dirender
            total: 0,
            q: '',
            gids: new Set(),
            groups: null,
            only: false,         // mode "hanya yang diedit"
            loading: false,
            seq: 0,
            timer: null,
            ui: { density: 'cozy', meta: true, group: true }
        };

        /* ---------------------------------------------------------------- util */
        function esc(s) {
            return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
                return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
            });
        }
        function num(n) { return (Number(n) || 0).toLocaleString('id-ID'); }
        function rupiah(n) {
            n = Math.abs(Number(n) || 0);
            if (n >= 1e9) { return 'Rp ' + (n / 1e9).toFixed(1).replace('.', ',') + ' M'; }
            if (n >= 1e6) { return 'Rp ' + (n / 1e6).toFixed(1).replace('.', ',') + ' jt'; }
            if (n >= 1e3) { return 'Rp ' + Math.round(n / 1e3) + ' rb'; }
            return 'Rp ' + n;
        }
        function digits(v) { return String(v == null ? '' : v).replace(/[^\d]/g, ''); }
        function toast(msg, type) {
            var el = document.createElement('div');
            el.className = 'qe-toast' + (type ? ' is-' + type : '');
            el.textContent = msg;
            panel.appendChild(el);
            setTimeout(function () { el.classList.add('is-out'); }, 2400);
            setTimeout(function () { if (el.parentNode) { el.parentNode.removeChild(el); } }, 2900);
        }

        /* ------------------------------------------------------------ preferensi */
        function loadUI() {
            try {
                var raw = localStorage.getItem(UI_KEY);
                if (raw) {
                    var o = JSON.parse(raw);
                    S.ui.density = o.density === 'compact' ? 'compact' : 'cozy';
                    S.ui.meta = o.meta !== false;
                    S.ui.group = o.group !== false;
                }
            } catch (e) {}
        }
        function saveUI() {
            try { localStorage.setItem(UI_KEY, JSON.stringify(S.ui)); } catch (e) {}
        }
        function applyUI() {
            if (!sheet) { return; }
            sheet.classList.toggle('qe-compact', S.ui.density === 'compact');
            sheet.classList.toggle('qe-nometa', !S.ui.meta);
            sheet.classList.toggle('qe-nogroup', !S.ui.group);
            Array.prototype.forEach.call(uipop ? uipop.querySelectorAll('[data-density]') : [], function (b) {
                b.classList.toggle('is-on', b.getAttribute('data-density') === S.ui.density);
            });
            if (uiMeta) { uiMeta.checked = S.ui.meta; }
            if (uiGroup) { uiGroup.checked = S.ui.group; }
        }

        /* ---------------------------------------------------------------- group */
        function loadGroups(cb) {
            if (S.groups) { cb(); return; }
            fetch(BASE + '/quick-groups', { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then(function (r) { return r.json(); })
                .then(function (d) {
                    S.groups = (d && d.groups) || [];
                    cb();
                })
                .catch(function () { S.groups = []; cb(); });
        }
        function renderGroupMenu() {
            var html = '';
            (S.groups || []).forEach(function (g) {
                html += '<label class="qe-gopt"><input type="checkbox" value="' + g.id + '"' +
                    (S.gids.has(g.id) ? ' checked' : '') + '><span>' + esc(g.name) + '</span><b>' + num(g.n) + '</b></label>';
            });
            html += '<div class="qe-gmenu-foot">' +
                '<button type="button" data-gall="1">Pilih semua</button>' +
                '<button type="button" data-gnone="1">Kosongkan</button></div>';
            gmenu.innerHTML = html;
        }
        function syncGroupLabel() {
            var n = S.gids.size;
            gcount.textContent = n === 0 ? 'Semua group' : (n + ' group dipilih');
            gbtn.classList.toggle('is-on', n > 0);
        }

        /* ------------------------------------------------------------- render UI */
        var SVG = {
            search: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="7.25"></circle><path d="M20.5 20.5 16.4 16.4"></path></svg>',
            folder: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3.25 8.25A2.25 2.25 0 0 1 5.5 6h3.2c.6 0 1.16.28 1.52.75l.66.9c.36.47.92.75 1.52.75h6.1a2.25 2.25 0 0 1 2.25 2.25v6.1a2.25 2.25 0 0 1-2.25 2.25H5.5a2.25 2.25 0 0 1-2.25-2.25z"></path></svg>',
            edit: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 20h4.2L19 9.2a2.1 2.1 0 0 0 0-3l-1.2-1.2a2.1 2.1 0 0 0-3 0L4 15.8z"></path><path d="M13.6 6.4 17.6 10.4"></path></svg>'
        };

        function renderEmpty() {
            var q = (S.q || '').trim();
            var chips = (S.groups || []).slice(0, 8).map(function (g) {
                return '<button type="button" class="qe-chip" data-gid="' + g.id + '">' +
                    '<span class="qe-chip-ico">' + SVG.folder + '</span>' +
                    '<span class="qe-chip-t">' + esc(g.name) + '</span>' +
                    '<b class="qe-chip-n">' + num(g.n) + '</b>' +
                    '</button>';
            }).join('');
            listEl.innerHTML =
                '<div class="qe-empty">' +
                  '<button type="button" class="qe-empty-ico" data-qe-focus-search aria-label="Fokus ke kotak cari">' + SVG.search + '</button>' +
                  '<h4>' + (q ? 'Tidak ada hasil untuk &ldquo;' + esc(q) + '&rdquo;' : 'Belum ada item yang ditampilkan') + '</h4>' +
                  '<p>Ketik di kotak cari &mdash; pencarian mencakup <b>kode, nama, group, lokasi, dan kategori</b> sekaligus. Atau mulai dari group di bawah.</p>' +
                  (chips ? '<div class="qe-chips">' + chips + '</div>' : '') +
                '</div>';
        }

        function rowHtml(rec) {
            var e = S.edited.get(rec.id);
            var val = e ? e.val : String(rec.counted);
            var stock = Number(rec.stock) || 0;
            var diff = (val === '' ? stock : (Number(val) || 0)) - stock;
            var cls = 'qe-row' + (e ? ' is-dirty' : '');
            var dirCls = diff > 0 ? 'is-plus' : (diff < 0 ? 'is-minus' : 'is-match');
            return '<div class="' + cls + '" data-id="' + rec.id + '">' +
                '<div class="qe-main">' +
                    '<span class="qe-code">' + esc(rec.code) + '</span>' +
                    '<span class="qe-name" title="' + esc(rec.name) + '">' + esc(rec.name) + '</span>' +
                    '<span class="qe-gname" title="' + esc(rec.group) + '">' + esc(rec.group) + '</span>' +
                '</div>' +
                '<div class="qe-meta">' +
                    '<span class="qe-stock">Stok <b>' + num(stock) + '</b></span>' +
                    '<span class="qe-delta ' + dirCls + '">' + (e ? (diff > 0 ? '+' : '') + num(diff) : '&mdash;') + '</span>' +
                    (e ? '<span class="qe-flag">diubah</span>' : '') +
                '</div>' +
                '<div class="qe-field">' +
                    '<input type="text" inputmode="numeric" autocomplete="off" class="qe-input" ' +
                        'value="' + esc(val) + '" data-id="' + rec.id + '" aria-label="Hasil hitung ' + esc(rec.code) + '">' +
                    '<span class="qe-unit">' + esc(rec.unit || 'pcs') + '</span>' +
                '</div>' +
            '</div>';
        }

        function render() {
            var rows = S.rows;
            if (!rows.length) {
                renderEmpty();
                listEl.hidden = false;
            } else {
                listEl.hidden = false;
                var html = rows.map(rowHtml).join('');
                if (!S.only && S.rows.length < S.total) {
                    html += '<button type="button" class="qe-more" id="qe-more">Muat ' + Math.min(PAGE, S.total - S.rows.length) +
                        ' lagi &middot; ' + num(S.rows.length) + ' dari ' + num(S.total) + '</button>';
                }
                listEl.innerHTML = html;
                var more = document.getElementById('qe-more');
                if (more) {
                    more.addEventListener('click', function () { fetchList(false); });
                }
            }
            updateFoot();
        }

        function updateFoot() {
            var n = S.edited.size;
            countEl.textContent = S.only
                ? (n + ' item sedang diedit')
                : (num(S.rows.length) + ' tampil' + (S.total > S.rows.length ? ' / ' + num(S.total) : ''));
            if (onlyN) { onlyN.textContent = String(n); }
            if (onlyBtn) {
                onlyBtn.classList.toggle('is-on', S.only);
                onlyBtn.setAttribute('aria-pressed', S.only ? 'true' : 'false');
            }
            saveBtn.disabled = n === 0;
            saveLabel.textContent = n === 0 ? 'Simpan' : ('Simpan ' + n + ' item');
            statEl.textContent = n === 0 ? '' : n + ' item siap disimpan';
        }

        /* -------------------------------------------------------------- ambil data */
        function fetchList(reset) {
            if (S.only) { renderEdited(); return; }
            var q = S.q.trim();
            var gids = Array.from(S.gids).join(',');
            if (q === '' && gids === '') {
                S.rows = []; S.total = 0; S.seq++;
                render();
                infoEl.textContent = '';
                return;
            }
            if (reset) { S.rows = []; S.total = 0; }
            var offset = reset ? 0 : S.rows.length;
            var seq = ++S.seq;
            S.loading = true;
            if (infoEl) { infoEl.textContent = 'mencari…'; }

            fetch(BASE + '/quick-items?q=' + encodeURIComponent(q) + '&gids=' + encodeURIComponent(gids) +
                  '&limit=' + PAGE + '&offset=' + offset,
                  { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then(function (r) { return r.json(); })
                .then(function (d) {
                    if (seq !== S.seq) { return; }   // jawaban lama, abaikan
                    S.loading = false;
                    var items = (d && d.items) || [];
                    S.total = (d && d.total) || items.length;
                    S.rows = reset ? items : S.rows.concat(items);
                    if (infoEl) {
                        infoEl.textContent = S.total + ' cocok · ' + ((d && d.ms) || 0) + ' ms';
                    }
                    render();
                })
                .catch(function (err) {
                    if (seq !== S.seq) { return; }
                    S.loading = false;
                    if (infoEl) { infoEl.textContent = 'gagal memuat'; }
                    if (window.console && console.warn) { console.warn('[mode-cepat] pencarian gagal:', err); }
                    toast('Gagal mengambil data. Periksa koneksi lalu coba lagi.', 'err');
                    if (!S.rows.length) {
                        listEl.innerHTML = '<div class="qe-empty">' +
                            '<span class="qe-empty-ico">' + SVG.search + '</span>' +
                            '<h4>Pencarian gagal dimuat</h4>' +
                            '<p>Koneksi ke server terputus. <button type="button" class="btn btn-sm" data-qe-retry>Coba lagi</button></p></div>';
                    }
                });
        }

        function renderEdited() {
            var arr = Array.from(S.edited.values()).map(function (e) { return e.rec; });
            arr.sort(function (a, b) { return String(a.code).localeCompare(String(b.code)); });
            S.rows = arr;
            S.total = arr.length;
            if (!arr.length) {
                listEl.innerHTML = '<div class="qe-empty">' +
                    '<span class="qe-empty-ico">' + SVG.edit + '</span>' +
                    '<h4>Belum ada yang diedit</h4>' +
                    '<p>Ketik angka di daftar; baris yang berubah akan muncul di sini.</p></div>';
                updateFoot();
                return;
            }
            render();
        }

        /* ------------------------------------------------------------ interaksi */
        function onInput(ev) {
            var input = ev.target.closest ? ev.target.closest('.qe-input') : null;
            if (!input) { return; }
            var id = Number(input.getAttribute('data-id'));
            var row = input.closest('.qe-row');
            var rec = S.rows.filter(function (r) { return r.id === id; })[0];
            if (!rec) { return; }
            var val = digits(input.value);
            if (input.value !== val) { input.value = val; }

            if (val === '' || Number(val) === Number(rec.counted)) {
                S.edited.delete(id);
            } else {
                S.edited.set(id, { rec: rec, val: val });
            }
            var dirty = S.edited.has(id);
            if (row) {
                row.classList.toggle('is-dirty', dirty);
                var delta = row.querySelector('.qe-delta');
                if (delta) {
                    if (!dirty) { delta.textContent = '—'; delta.className = 'qe-delta is-match'; }
                    else {
                        var d = Number(val) - Number(rec.stock || 0);
                        delta.textContent = (d > 0 ? '+' : '') + num(d);
                        delta.className = 'qe-delta ' + (d > 0 ? 'is-plus' : (d < 0 ? 'is-minus' : 'is-match'));
                    }
                }
            }
            updateFoot();
        }

        function focusAt(idx) {
            var inputs = listEl.querySelectorAll('.qe-input');
            if (!inputs.length) { return; }
            var i = Math.max(0, Math.min(inputs.length - 1, idx));
            inputs[i].focus();
            inputs[i].select();
            var r = inputs[i].closest('.qe-row');
            Array.prototype.forEach.call(listEl.querySelectorAll('.qe-row.is-focus'), function (x) { x.classList.remove('is-focus'); });
            if (r) { r.classList.add('is-focus'); }
        }

        function onKey(ev) {
            var input = ev.target.closest ? ev.target.closest('.qe-input') : null;
            if (!input) { return; }
            var inputs = Array.prototype.slice.call(listEl.querySelectorAll('.qe-input'));
            var idx = inputs.indexOf(input);
            if (ev.key === 'Enter' && !ev.ctrlKey && !ev.metaKey) {
                ev.preventDefault();
                if (idx === inputs.length - 1 && S.rows.length < S.total && !S.only) { fetchList(false); return; }
                focusAt(idx + 1);
            } else if (ev.key === 'ArrowDown') {
                ev.preventDefault(); focusAt(idx + 1);
            } else if (ev.key === 'ArrowUp') {
                ev.preventDefault(); focusAt(idx - 1);
            } else if (ev.key === 'Enter' && (ev.ctrlKey || ev.metaKey)) {
                ev.preventDefault(); doSave();
            }
        }

        /* ------------------------------------------------------------------ simpan */
        function doSave() {
            if (!S.edited.size) { toast('Belum ada angka yang berubah.', 'warn'); return; }
            var body = new URLSearchParams();
            body.append('_token', TOKEN);
            body.append('reason', 'Input cepat (mode lapangan)');
            var ids = [];
            S.edited.forEach(function (e, id) {
                ids.push(id);
                body.append('qty[' + id + ']', e.val);
            });
            ids.forEach(function (id) { body.append('ids[]', id); });

            saveBtn.disabled = true;
            saveLabel.textContent = 'Menyimpan…';

            fetch(ACTION, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
                body: body.toString()
            })
            .then(function (r) { return r.json().catch(function () { return { ok: false, error: 'Respon tidak dikenali (HTTP ' + r.status + ')' }; }); })
            .then(function (d) {
                if (!d || !d.ok) { toast((d && d.error) || 'Gagal menyimpan.', 'err'); updateFoot(); return; }
                var saved = d.updated || 0;
                S.edited.clear();
                toast(saved + ' item tersimpan' + (d.unchanged ? ' · ' + d.unchanged + ' tidak berubah' : ''), 'ok');
                if (S.only) { renderEdited(); } else { fetchList(true); }
                document.dispatchEvent(new CustomEvent('recon:updated'));
            })
            .catch(function () { toast('Koneksi gagal saat menyimpan.', 'err'); })
            .then(function () { updateFoot(); });
        }

        /* -------------------------------------------------------------------- buka */
        function open() {
            S.edited.clear();      // editan lama tidak dibawa ke sesi berikutnya
            S.rows = []; S.total = 0; S.q = ''; S.gids.clear(); S.only = false;
            searchEl.value = '';
            syncClearBtn();
            renderGroupMenu();
            syncGroupLabel();
            panel.hidden = false;
            document.body.classList.add('qe-lock');
            applyUI();
            loadGroups(function () { renderGroupMenu(); render(); });
            render();
            clearInterval(S.watch);
            S.watch = setInterval(watchQuery, 250);
            setTimeout(function () {
                try { searchEl.focus({ preventScroll: true }); } catch (e) { searchEl.focus(); }
            }, 60);
        }
        function close() {
            panel.hidden = true;
            clearInterval(S.watch);
            clearTimeout(S.timer);
            document.body.classList.remove('qe-lock');
            gmenu.hidden = true;
            if (uipop) { uipop.hidden = true; }
            S.edited.clear();
            S.rows = []; S.total = 0;
            render();
            /* fokus kembali ke tombol pemicu tanpa menggeser halaman */
            if (openBtn && openBtn.focus) {
                try { openBtn.focus({ preventScroll: true }); } catch (e) { openBtn.focus(); }
            }
        }

        /* ----------------------------------------------------------------- events */
        if (openBtn) { openBtn.addEventListener('click', open); }
        $('qe-close') && $('qe-close').addEventListener('click', close);
        $('qe-cancel') && $('qe-cancel').addEventListener('click', close);

        /* ------------------------------------------ pencarian (banyak jalur) */
        function setQuery(v, immediate) {
            S.q = (v == null ? '' : String(v));
            clearTimeout(S.timer);
            if (immediate) { fetchList(true); return; }
            S.timer = setTimeout(function () { fetchList(true); }, 220);
        }
        function syncQuery(force) {
            if (panel.hidden || !searchEl) { return; }
            var v = searchEl.value;
            if (!force && v === S.q) { return; }
            if (S.only) { S.only = false; applyUI(); }   /* mengetik = keluar dari mode "hanya diedit" */
            setQuery(v);
        }
        /* 1) jalur normal: setiap ketikan */
        panel.addEventListener('input', function (ev) {
            if (ev.target && ev.target.id === 'qe-search') { syncQuery(true); }
        }, true);
        /* 2) tombol "x" bawaan <input type="search"> */
        panel.addEventListener('search', function (ev) {
            if (ev.target && ev.target.id === 'qe-search') { syncQuery(true); }
        }, true);
        /* 3) Enter / tombol "Cari" di keyboard ponsel */
        searchEl.addEventListener('keydown', function (ev) {
            if (ev.key === 'Enter' || ev.keyCode === 13) {
                ev.preventDefault();
                syncQuery(true);
                setTimeout(function () { focusAt(0); }, 300);
            }
        });
        searchEl.addEventListener('keyup', function (ev) {
            if (ev.key === 'Enter' || ev.keyCode === 13) { syncQuery(true); }
        });
        /* 4) jaring pengaman: kalau ada event yang tidak sampai, nilai input dicek tiap 250 ms */
        function watchQuery() {
            if (!panel || panel.hidden || !searchEl) { return; }
            if (searchEl.value !== S.q) { syncQuery(true); }
        }
        /* 5) jalur tambahan: tempel, potong, IME, dan perubahan dari autofill */
        ['keyup', 'paste', 'cut', 'change', 'compositionend', 'blur'].forEach(function (evt) {
            panel.addEventListener(evt, function (ev) {
                if (ev.target && ev.target.id === 'qe-search') {
                    setTimeout(function () { if (searchEl.value !== S.q) { syncQuery(true); } }, 0);
                }
            }, true);
        });

        /* klik di mana pun pada kotak cari → fokus ke input */
        var findWrap = document.getElementById('qe-find');
        var findClear = document.getElementById('qe-find-clear');

        function syncClearBtn() {
            if (!findClear || !searchEl) { return; }
            findClear.hidden = !searchEl.value;
        }

        if (findWrap) {
            findWrap.addEventListener('mousedown', function (ev) {
                if (ev.target !== searchEl && !(findClear && findClear.contains(ev.target))) {
                    ev.preventDefault();
                    try { searchEl.focus({ preventScroll: true }); } catch (e) { searchEl.focus(); }
                }
            });
            findWrap.addEventListener('click', function (ev) {
                if (findClear && findClear.contains(ev.target)) { return; }
                try { searchEl.focus({ preventScroll: true }); } catch (e) { searchEl.focus(); }
            });
        }
        if (findClear) {
            findClear.addEventListener('click', function (ev) {
                ev.preventDefault();
                ev.stopPropagation();
                if (!searchEl) { return; }
                searchEl.value = '';
                syncClearBtn();
                syncQuery(true);
                try { searchEl.focus({ preventScroll: true }); } catch (e) { searchEl.focus(); }
            });
        }
        if (searchEl) {
            ['input', 'change', 'search'].forEach(function (evt) {
                searchEl.addEventListener(evt, syncClearBtn);
            });
        }

        listEl.addEventListener('click', function (ev) {
            if (ev.target.closest('[data-qe-retry]')) { ev.preventDefault(); fetchList(true); return; }
            if (ev.target.closest('[data-qe-focus-search]')) {
                ev.preventDefault();
                try { searchEl.focus({ preventScroll: true }); } catch (e) { searchEl.focus(); }
            }
        });

        gbtn.addEventListener('click', function (ev) {
            ev.stopPropagation();
            gmenu.hidden = !gmenu.hidden;
            gbtn.setAttribute('aria-expanded', gmenu.hidden ? 'false' : 'true');
        });
        gmenu.addEventListener('change', function (ev) {
            var cb = ev.target;
            if (!cb || cb.type !== 'checkbox') { return; }
            var id = Number(cb.value);
            if (cb.checked) { S.gids.add(id); } else { S.gids.delete(id); }
            syncGroupLabel();
            fetchList(true);
        });
        gmenu.addEventListener('click', function (ev) {
            var all = ev.target.closest('[data-gall]'), none = ev.target.closest('[data-gnone]');
            if (all) {
                (S.groups || []).forEach(function (g) { S.gids.add(g.id); });
                renderGroupMenu(); syncGroupLabel(); fetchList(true);
            } else if (none) {
                S.gids.clear(); renderGroupMenu(); syncGroupLabel(); fetchList(true);
            }
        });

        if (onlyBtn) {
            onlyBtn.addEventListener('click', function () {
                S.only = !S.only;
                if (S.only) { renderEdited(); }
                else { fetchList(true); }
                updateFoot();
            });
        }

        listEl.addEventListener('input', onInput);
        listEl.addEventListener('keydown', onKey);
        listEl.addEventListener('click', function (ev) {
            var chip = ev.target.closest('.qe-chip');
            if (chip) {
                S.gids.clear();
                S.gids.add(Number(chip.getAttribute('data-gid')));
                renderGroupMenu(); syncGroupLabel(); fetchList(true);
            }
        });

        form.addEventListener('submit', function (ev) { ev.preventDefault(); doSave(); });

        document.addEventListener('keydown', function (ev) {
            if (ev.key === 'Escape' && !panel.hidden) { close(); }
            if (ev.altKey && (ev.key === 'q' || ev.key === 'Q')) { ev.preventDefault(); panel.hidden ? open() : close(); }
        });
        document.addEventListener('click', function (ev) {
            if (gmenu && !gmenu.hidden && !gmenu.contains(ev.target) && !gbtn.contains(ev.target)) { gmenu.hidden = true; }
            if (uipop && !uipop.hidden && !uipop.contains(ev.target) && !gearBtn.contains(ev.target)) { uipop.hidden = true; }
        });

        /* pengaturan tampilan */
        if (gearBtn && uipop) {
            gearBtn.addEventListener('click', function (ev) {
                ev.stopPropagation();
                uipop.hidden = !uipop.hidden;
                gearBtn.setAttribute('aria-expanded', uipop.hidden ? 'false' : 'true');
            });
            uipop.addEventListener('click', function (ev) {
                var b = ev.target.closest('[data-density]');
                if (b) { S.ui.density = b.getAttribute('data-density'); saveUI(); applyUI(); }
            });
            if (uiMeta) {
                uiMeta.addEventListener('change', function () { S.ui.meta = uiMeta.checked; saveUI(); applyUI(); });
            }
            if (uiGroup) {
                uiGroup.addEventListener('change', function () { S.ui.group = uiGroup.checked; saveUI(); applyUI(); });
            }
        }

        loadUI();
        applyUI();
        render();
        if (location.hash === '#mode-cepat') { open(); }
    });
})();
