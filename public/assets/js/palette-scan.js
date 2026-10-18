/* ==========================================================================
   PALETTE SCAN — tombol Scan Barcode di pencarian cepat (Ctrl/Cmd+K).

   Tujuan: dari halaman mana pun, user bisa langsung scan barcode lalu
   mengisi qty hasil hitung tanpa harus membuka menu Data STO dulu.

   Alur:
     1. Tombol "Scan" di baris pencarian palette → buka modal scan.
     2. Scanner gun / ketik manual → Enter → GET /items/lookup?code=…
     3. Item ketemu → tampil ringkasan + stepper qty + catatan → Simpan.
     4. POST /items/{id}/count → toast, baris tabel di-flash (bila ada),
        modal balik ke mode scan untuk barang berikutnya (multi-scan).

   Tidak bergantung pada items-barcode-posting.js (yang hanya dimuat di
   halaman /items), jadi tetap berfungsi di dashboard, audit, dsb.
   ========================================================================== */
(function () {
    'use strict';

    var backdrop = document.querySelector('[data-palette]');
    if (!backdrop) { return; }

    var inputWrap = backdrop.querySelector('.palette-input-wrap');
    var palInput  = backdrop.querySelector('[data-palette-input]');
    var LOOKUP    = '/items/lookup';

    function csrf() {
        var m = document.querySelector('meta[name="csrf-token"]');
        return m ? m.content : '';
    }

    function toast(msg, type) {
        if (typeof window.sfToast === 'function') { window.sfToast(msg, type || 'info'); }
    }

    /* sessionStorage bisa diblokir (mode privat) — jangan sampai melempar error */
    function sget(k) { try { return sessionStorage.getItem(k); } catch (e) { return null; } }
    function sset(k, v) { try { sessionStorage.setItem(k, v); } catch (e) {} }
    function sdel(k) { try { sessionStorage.removeItem(k); } catch (e) {} }

    var ICON_SCAN = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
        + '<path d="M3 7V5a2 2 0 0 1 2-2h2"/><path d="M17 3h2a2 2 0 0 1 2 2v2"/>'
        + '<path d="M21 17v2a2 2 0 0 1-2 2h-2"/><path d="M7 21H5a2 2 0 0 1-2-2v-2"/>'
        + '<path d="M7 8v8"/><path d="M10.5 8v8"/><path d="M14 8v8"/><path d="M17.5 8v8"/></svg>';

    var ICON_CAM = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
        + '<path d="M14.5 4h-5L8 6H5a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2h-3l-1.5-2Z"/>'
        + '<circle cx="12" cy="13" r="3.2"/></svg>';


    /* ------------------------------------------------------------------
       1. Tombol Scan di baris pencarian palette
       ------------------------------------------------------------------ */
    if (inputWrap && !inputWrap.querySelector('[data-palette-scan]')) {
        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'palette-scan-btn';
        btn.setAttribute('data-palette-scan', '');
        btn.title = 'Scan barcode → input qty hitung';
        btn.setAttribute('aria-label', 'Scan barcode dan input qty hitung');
        btn.innerHTML = ICON_SCAN + '<span>Scan</span>';

        var escKbd = inputWrap.querySelector('kbd');
        inputWrap.insertBefore(btn, escKbd || null);

        btn.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            openScan('');
        });
    }

    /* ------------------------------------------------------------------
       2. Modal scan & hitung (dibangun sekali, dipakai ulang)
       ------------------------------------------------------------------ */
    var wrap = document.createElement('div');
    wrap.className = 'pscan';
    wrap.hidden = true;
    wrap.innerHTML = ''
        + '<div class="pscan-card" role="dialog" aria-modal="true" aria-labelledby="pscan-title">'
        +   '<div class="pscan-head">'
        +     '<span class="pscan-badge">' + ICON_SCAN + '</span>'
        +     '<div class="pscan-headtext">'
        +       '<h2 class="pscan-title" id="pscan-title">Scan &amp; Hitung</h2>'
        +       '<p class="pscan-sub">Tembak barcode, isi qty, simpan — tanpa pindah menu.</p>'
        +     '</div>'
        +     '<button type="button" class="pscan-x" data-pscan-close aria-label="Tutup">&times;</button>'
        +   '</div>'
        +   '<div class="pscan-body">'
        +     '<div class="pscan-scanrow">'
        +       '<input type="text" class="pscan-code" data-pscan-code autocomplete="off" spellcheck="false"'
        +              ' placeholder="Scan / ketik kode barang…" aria-label="Kode barcode barang">'
        +       '<button type="button" class="btn btn-outline pscan-cam-btn" data-pscan-cam'
        +              ' aria-label="Scan pakai kamera" aria-pressed="false">'
        +         ICON_CAM + '<span class="pscan-cam-label">Kamera</span>'
        +       '</button>'
        +       '<button type="button" class="btn btn-brand pscan-go" data-pscan-go>Cari</button>'
        +     '</div>'
        +     '<div class="pscan-cam" data-pscan-cam-panel hidden>'
        +       '<div class="pscan-cam-vp" id="pscan-cam-vp" data-pscan-cam-vp></div>'
        +       '<div class="pscan-cam-bar">'
        +         '<span class="pscan-cam-hint" data-pscan-cam-hint>Arahkan kamera ke barcode…</span>'
        +         '<button type="button" class="pscan-cam-stop" data-pscan-cam-stop>Hentikan</button>'
        +       '</div>'
        +     '</div>'
        +     '<p class="pscan-hint">Scanner gun langsung mengisi kolom ini. Atau tekan <b>Kamera</b> untuk scan pakai kamera HP.</p>'
        +     '<div class="pscan-msg" data-pscan-msg role="status" aria-live="polite"></div>'
        +     '<div class="pscan-item" data-pscan-item hidden>'
        +       '<div class="pscan-item-head">'
        +         '<span class="pscan-code-tag" data-pscan-code-tag></span>'
        +         '<span class="pscan-name" data-pscan-name></span>'
        +       '</div>'
        +       '<dl class="pscan-meta">'
        +         '<div><dt>Group</dt><dd data-pscan-group>—</dd></div>'
        +         '<div><dt>Lokasi</dt><dd data-pscan-loc>—</dd></div>'
        +         '<div><dt>Satuan</dt><dd data-pscan-unit>—</dd></div>'
        +         '<div data-pscan-stockwrap hidden><dt>Stok sistem</dt><dd data-pscan-stock>—</dd></div>'
        +       '</dl>'
        +       '<div class="pscan-qtyrow">'
        +         '<label for="pscan-qty">Qty hitung</label>'
        +         '<div class="pscan-stepper">'
        +           '<button type="button" data-pscan-minus aria-label="Kurangi qty">&minus;</button>'
        +           '<input id="pscan-qty" type="number" min="0" step="1" inputmode="numeric" data-pscan-qty value="0">'
        +           '<button type="button" data-pscan-plus aria-label="Tambah qty">+</button>'
        +         '</div>'
        +       '</div>'
        +       '<p class="pscan-diff" data-pscan-diff aria-live="polite"></p>'
        +       '<input type="text" class="pscan-note" data-pscan-note maxlength="255"'
        +              ' placeholder="Catatan kondisi (opsional)" aria-label="Catatan kondisi">'
        +       '<div class="pscan-actions">'
        +         '<button type="button" class="btn btn-outline" data-pscan-close>Batal</button>'
        +         '<button type="button" class="btn btn-brand" data-pscan-save>Simpan hitungan</button>'
        +       '</div>'
        +     '</div>'
        +     '<div class="pscan-last" data-pscan-last hidden>'
        +       '<span class="pscan-last-dot" aria-hidden="true"></span>'
        +       '<span data-pscan-last-text></span>'
        +       '<button type="button" class="pscan-reload" data-pscan-reload hidden>Muat ulang tabel</button>'
        +     '</div>'
        +   '</div>'
        + '</div>';

    document.body.appendChild(wrap);

    var els = {
        card:    wrap.querySelector('.pscan-card'),
        code:    wrap.querySelector('[data-pscan-code]'),
        go:      wrap.querySelector('[data-pscan-go]'),
        camBtn:  wrap.querySelector('[data-pscan-cam]'),
        camPanel: wrap.querySelector('[data-pscan-cam-panel]'),
        camVp:   wrap.querySelector('[data-pscan-cam-vp]'),
        camHint: wrap.querySelector('[data-pscan-cam-hint]'),
        camStop: wrap.querySelector('[data-pscan-cam-stop]'),
        msg:     wrap.querySelector('[data-pscan-msg]'),
        item:    wrap.querySelector('[data-pscan-item]'),
        codeTag: wrap.querySelector('[data-pscan-code-tag]'),
        name:    wrap.querySelector('[data-pscan-name]'),
        group:   wrap.querySelector('[data-pscan-group]'),
        loc:     wrap.querySelector('[data-pscan-loc]'),
        unit:    wrap.querySelector('[data-pscan-unit]'),
        stockWrap: wrap.querySelector('[data-pscan-stockwrap]'),
        stock:   wrap.querySelector('[data-pscan-stock]'),
        qty:     wrap.querySelector('[data-pscan-qty]'),
        diff:    wrap.querySelector('[data-pscan-diff]'),
        minus:   wrap.querySelector('[data-pscan-minus]'),
        plus:    wrap.querySelector('[data-pscan-plus]'),
        note:    wrap.querySelector('[data-pscan-note]'),
        save:    wrap.querySelector('[data-pscan-save]'),
        last:    wrap.querySelector('[data-pscan-last]'),
        lastText: wrap.querySelector('[data-pscan-last-text]'),
        reload:  wrap.querySelector('[data-pscan-reload]')
    };

    var state = { item: null, canCount: true, busy: false };

    /* ------------------------------------------------------------------
       3. Util
       ------------------------------------------------------------------ */
    function msg(text, type) {
        els.msg.textContent = text || '';
        els.msg.className = 'pscan-msg' + (text ? ' is-' + (type || 'info') : '');
    }

    /* Baris info selisih qty hitung vs stok sistem (tidak memblokir simpan) */
    function paintDiff() {
        if (!els.diff) { return; }
        var it = state.item;
        if (!it || !it.can_see_qty || it.expected_qty === null || it.expected_qty === undefined) {
            els.diff.textContent = '';
            els.diff.className = 'pscan-diff';
            return;
        }
        var raw = els.qty.value === '' ? NaN : parseInt(els.qty.value, 10);
        if (isNaN(raw)) {
            els.diff.textContent = '';
            els.diff.className = 'pscan-diff';
            return;
        }
        var sys = Number(it.expected_qty) || 0;
        var d = raw - sys;
        if (d === 0) {
            els.diff.textContent = 'Sesuai stok sistem (' + sys + ').';
            els.diff.className = 'pscan-diff is-same';
        } else {
            els.diff.textContent = 'Selisih ' + (d > 0 ? '+' : '') + d + ' dari stok sistem ' + sys + '.';
            els.diff.className = 'pscan-diff is-diff';
        }
    }

    /* Tombol simpan hanya aktif kalau qty valid & item boleh dihitung */
    function syncSave() {
        var n = els.qty.value === '' ? NaN : parseInt(els.qty.value, 10);
        var ok = !isNaN(n) && n >= 0 && !!state.item && state.canCount
            && !(state.item && state.item.is_locked);
        els.save.disabled = !ok;
    }

    function closePalette() {
        // Biarkan spotlight.js yang menutup (state internalnya ikut rapi).
        if (backdrop.classList.contains('is-open')) {
            document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }));
            if (backdrop.classList.contains('is-open')) {
                backdrop.classList.remove('is-open');
                document.documentElement.style.overflow = '';
            }
        }
    }

    function flashRow(id) {
        var row = document.querySelector('#items-table tbody tr[data-item-id="' + id + '"]');
        if (!row) { return false; }
        row.classList.remove('highlight-flash-success');
        void row.offsetWidth;
        row.classList.add('highlight-flash-success');
        if (row.scrollIntoView) { row.scrollIntoView({ block: 'center', behavior: 'smooth' }); }
        return true;
    }

    function resetForm() {
        state.item = null;
        els.item.hidden = true;
        els.code.value = '';
        els.qty.value = '';
        els.note.value = '';
        els.save.disabled = true;
        els.save.textContent = 'Simpan hitungan';
        if (els.diff) { els.diff.textContent = ''; els.diff.className = 'pscan-diff'; }
        els.stockWrap.hidden = true;
    }

    function showLast(text, withReload) {
        els.last.hidden = false;
        els.lastText.textContent = text;
        els.reload.hidden = !withReload;
    }

    /* ------------------------------------------------------------------
       4. Buka / tutup
       ------------------------------------------------------------------ */
    function openScan(code) {
        closePalette();
        camStop();
        wrap.hidden = false;
        document.documentElement.style.overflow = 'hidden';
        resetForm();
        msg('', '');

        if (code) {
            els.code.value = code;
            lookup();
        } else {
            setTimeout(function () { els.code.focus(); }, 40);
            /* Di HP/tablet, langsung nyalakan kamera (sekali tap = scan).
               Kalau user menekan "Hentikan", jangan auto-nyala lagi. */
            setTimeout(function () {
                var coarse = (window.matchMedia && window.matchMedia('(pointer: coarse)').matches)
                    || (navigator.maxTouchPoints || 0) > 0
                    || /Android|iPhone|iPad|iPod|Mobile|Tablet/i.test(navigator.userAgent || '');
                if (!coarse || wrap.hidden) { return; }
                if (sget('pscanCamAuto') === 'off') { return; }
                camStart();
            }, 420);
        }
    }

    function closeScan() {
        camStop();
        wrap.hidden = true;
        document.documentElement.style.overflow = '';
        resetForm();
        msg('', '');
        if (palInput) { setTimeout(function () { palInput.focus(); }, 30); }
    }

    /* ------------------------------------------------------------------
       5. Cari item
       ------------------------------------------------------------------ */
    function lookup() {
        var code = (els.code.value || '').trim();
        if (!code || state.busy) { return; }

        state.busy = true;
        els.go.disabled = true;
        msg('Mencari ' + code + '…', 'info');

        fetch(LOOKUP + '?code=' + encodeURIComponent(code), {
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin'
        })
        .then(function (r) {
            return r.json().catch(function () {
                return { found: false, message: 'Respons server tidak valid.' };
            });
        })
        .then(function (data) {
            state.busy = false;
            els.go.disabled = false;

            if (!data || !data.found) {
                els.item.hidden = true;
                msg((data && data.message) || 'Barang tidak ditemukan.', 'error');
                els.code.select();
                return;
            }

            state.item = data.item;
            state.canCount = data.can_count !== false;
            renderItem(data.item, data.can_count);
        })
        .catch(function () {
            state.busy = false;
            els.go.disabled = false;
            msg('Gagal menghubungi server. Periksa koneksi lalu coba lagi.', 'error');
        });
    }

    function renderItem(it, canCount) {
        els.codeTag.textContent = it.code || '';
        els.name.textContent = it.name || '';
        els.group.textContent = it.group || '—';
        els.loc.textContent = it.location || '—';
        els.unit.textContent = it.unit || 'UNIT';

        if (it.can_see_qty && it.expected_qty !== null && it.expected_qty !== undefined) {
            els.stockWrap.hidden = false;
            els.stock.textContent = it.expected_qty + ' ' + (it.unit || '');
        } else {
            els.stockWrap.hidden = true;
        }

        els.qty.value = (it.counted_qty === null || it.counted_qty === undefined) ? '' : String(it.counted_qty);

        els.item.hidden = false;
        msg('', '');

        if (it.is_locked) {
            msg('Barang ini terkunci (sudah diposting). Ajukan unlock untuk mengubah.', 'error');
            els.save.disabled = true;
        } else if (!canCount) {
            msg('Role Anda tidak punya izin menghitung data.', 'error');
            els.save.disabled = true;
        } else {
            els.save.disabled = false;
            setTimeout(function () { els.qty.focus(); els.qty.select(); }, 40);
        }

        syncSave();
        paintDiff();
    }

    /* ------------------------------------------------------------------
       6. Simpan hasil hitung
       ------------------------------------------------------------------ */
    function save() {
        if (!state.item || state.busy || els.save.disabled) { return; }

        var v = parseInt(els.qty.value, 10);
        if (isNaN(v) || v < 0) {
            msg('Qty harus angka bulat minimal 0.', 'error');
            els.qty.focus();
            return;
        }

        state.busy = true;
        els.save.disabled = true;
        els.save.innerHTML = '<span class="pscan-spin"></span> Menyimpan…';

        var fd = new FormData();
        fd.append('_token', csrf());
        fd.append('counted_qty', String(v));
        var note = (els.note.value || '').trim();
        if (note) { fd.append('condition_note', note); }

        fetch('/items/' + state.item.id + '/count', {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: fd,
            credentials: 'same-origin'
        })
        .then(function (r) {
            return r.json().catch(function () {
                return { success: false, message: 'Gagal menyimpan (' + r.status + ').' };
            }).then(function (j) {
                if (!r.ok || !j.success) { throw j; }
                return j;
            });
        })
        .then(function () {
            state.busy = false;
            var code = state.item.code;
            var unit = state.item.unit || 'UNIT';

            toast('Tersimpan: ' + code + ' = ' + v + ' ' + unit, 'success');
            var hasRow = flashRow(state.item.id);

            showLast(code + ' → ' + v + ' ' + unit + ' tersimpan.', hasRow);

            resetForm();
            els.code.focus();
        })
        .catch(function (err) {
            state.busy = false;
            els.save.disabled = false;
            els.save.textContent = 'Simpan hitungan';
            msg((err && err.message) || 'Gagal menyimpan hasil hitung.', 'error');
        });
    }

    /* ------------------------------------------------------------------
       6b. Scan pakai kamera (html5-qrcode, dimuat saat dibutuhkan)
       ------------------------------------------------------------------ */
    var cam = { inst: null, starting: false, last: '', lastAt: 0 };

    function camSetHint(t) { if (els.camHint) { els.camHint.textContent = t; } }

    /* Library kamera dimuat lazy + fallback CDN kedua */
    function camLib(ok, fail) {
        if (typeof window.Html5Qrcode !== 'undefined') { ok(); return; }
        var urls = [
            'https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js',
            'https://cdn.jsdelivr.net/npm/html5-qrcode@2.3.8/html5-qrcode.min.js'
        ];
        (function next(i) {
            if (i >= urls.length) { fail(); return; }
            var s = document.createElement('script');
            s.src = urls[i];
            s.onload = function () { ok(); };
            s.onerror = function () { if (s.parentNode) { s.parentNode.removeChild(s); } next(i + 1); };
            document.head.appendChild(s);
        })(0);
    }

    function camStop() {
        var inst = cam.inst;
        cam.inst = null;
        cam.starting = false;
        if (inst) {
            try {
                inst.stop().then(function () { try { inst.clear(); } catch (e) {} }).catch(function () {});
            } catch (e) {}
        }
        if (els.camBtn) {
            els.camBtn.setAttribute('aria-pressed', 'false');
            els.camBtn.classList.remove('is-on');
        }
        if (els.camPanel) { els.camPanel.hidden = true; }
        if (els.camVp) { els.camVp.innerHTML = ''; }
    }

    /* Satu barcode terbaca → isi kolom, cari item, kamera berhenti */
    function camOnCode(text) {
        var code = String(text || '').trim();
        if (!code) { return; }
        var now = Date.now();
        if (code === cam.last && now - cam.lastAt < 1500) { return; }
        cam.last = code;
        cam.lastAt = now;

        camStop();
        els.code.value = code;
        if (navigator.vibrate) { try { navigator.vibrate(35); } catch (e) {} }
        lookup();
    }

    function camStart() {
        if (cam.inst || cam.starting) { return; }
        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            msg('Kamera tidak tersedia di browser ini. Pakai scanner gun atau ketik kode manual.', 'error');
            return;
        }
        if (!window.isSecureContext) {
            msg('Kamera hanya bisa dipakai lewat HTTPS. Pakai scanner gun atau ketik manual.', 'error');
            return;
        }

        els.camPanel.hidden = false;
        els.camVp.innerHTML = '';
        camSetHint('Menyiapkan kamera…');
        cam.starting = true;
        els.camBtn.setAttribute('aria-pressed', 'true');
        els.camBtn.classList.add('is-on');

        camLib(function () {
            try {
                var inst = new window.Html5Qrcode('pscan-cam-vp', { verbose: false });
                cam.inst = inst;
                inst.start(
                    { facingMode: 'environment' },
                    {
                        fps: 10,
                        qrbox: function (vw, vh) {
                            var m = Math.min(vw, vh);
                            var s = Math.max(140, Math.floor(m * 0.72));
                            return { width: s, height: s };
                        },
                        aspectRatio: 1.0
                    },
                    camOnCode,
                    function () {}
                ).then(function () {
                    cam.starting = false;
                    camSetHint('Arahkan kamera ke barcode…');
                }).catch(function () {
                    cam.starting = false;
                    cam.inst = null;
                    camStop();
                    msg('Tidak bisa membuka kamera. Beri izin kamera di browser, lalu coba lagi — atau pakai scanner gun / ketik manual.', 'error');
                });
            } catch (e) {
                cam.starting = false;
                cam.inst = null;
                camStop();
                msg('Scanner kamera gagal dimulai. Pakai scanner gun atau ketik manual.', 'error');
            }
        }, function () {
            cam.starting = false;
            camStop();
            msg('Library scanner gagal dimuat (butuh internet). Pakai scanner gun atau ketik manual.', 'error');
        });
    }

    /* ------------------------------------------------------------------
       7. Event
       ------------------------------------------------------------------ */
    if (els.camBtn) {
        els.camBtn.addEventListener('click', function () {
            try {
                if (cam.inst || cam.starting) {
                    sset('pscanCamAuto', 'off');
                    camStop();
                } else {
                    sdel('pscanCamAuto');
                    camStart();
                }
            } catch (e) {
                camStop();
                msg('Kamera tidak bisa dimulai. Pakai scanner gun atau ketik manual.', 'error');
            }
        });
    }
    if (els.camStop) {
        els.camStop.addEventListener('click', function () {
            sset('pscanCamAuto', 'off');
            camStop();
        });
    }

    els.go.addEventListener('click', lookup);

    els.code.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') { e.preventDefault(); lookup(); }
        else if (e.key === 'Escape') { e.preventDefault(); closeScan(); }
    });

    els.qty.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') { e.preventDefault(); save(); }
        else if (e.key === 'Escape') { e.preventDefault(); closeScan(); }
    });

    els.qty.addEventListener('input', function () {
        syncSave();
        paintDiff();
    });

    els.minus.addEventListener('click', function () {
        var v = parseInt(els.qty.value, 10);
        if (isNaN(v)) { v = 0; }
        els.qty.value = Math.max(0, v - 1);
        els.qty.focus();
        syncSave();
        paintDiff();
    });

    els.plus.addEventListener('click', function () {
        var v = parseInt(els.qty.value, 10);
        if (isNaN(v) || v < 0) { v = 0; }
        els.qty.value = v + 1;
        els.qty.focus();
        syncSave();
        paintDiff();
    });

    els.save.addEventListener('click', save);

    els.reload.addEventListener('click', function () { window.location.reload(); });

    wrap.querySelectorAll('[data-pscan-close]').forEach(function (b) {
        b.addEventListener('click', closeScan);
    });

    wrap.addEventListener('click', function (e) {
        if (e.target === wrap) { closeScan(); }
    });

    document.addEventListener('keydown', function (e) {
        if (wrap.hidden) { return; }
        if (e.key === 'Escape') { e.preventDefault(); closeScan(); return; }
        if (e.key === 'Tab') {
            // focus trap sederhana
            var f = els.card.querySelectorAll('button, input, [href]');
            var list = Array.prototype.filter.call(f, function (el) { return !el.disabled && el.offsetParent !== null; });
            if (!list.length) { return; }
            var first = list[0], last = list[list.length - 1];
            if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
            else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
        }
    });

    /* Palette: Enter pada kode yang tidak menghasilkan apa pun → buka scan */
    if (palInput) {
        palInput.addEventListener('keydown', function (e) {
            if (e.key !== 'Enter') { return; }
            var q = (palInput.value || '').trim();
            if (q.length < 3 || /\s/.test(q)) { return; }

            var listEl = backdrop.querySelector('[data-palette-list]');
            if (!listEl) { return; }

            var empty = listEl.querySelector('.palette-empty') || !listEl.querySelector('.palette-item');

            // Item hasil pencarian: sub-nya "KODE · Group". Kalau kode persis
            // sama dengan yang diketik, langsung buka scan (bukan pindah halaman).
            var exact = false;
            listEl.querySelectorAll('.palette-item .pi-sub').forEach(function (sub) {
                var code = (sub.textContent || '').split('\u00b7')[0].trim();
                if (code && code.toLowerCase() === q.toLowerCase()) { exact = true; }
            });

            if (!empty && !exact) { return; }

            e.preventDefault();
            e.stopPropagation();
            openScan(q);
        }, true);
    }

    /* Ekspos untuk pemanggil lain (mis. tombol eksternal) */
    window.sfPaletteScan = openScan;
})();
