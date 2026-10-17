/* ==========================================================================
   sf-tables-expand.js — EXPAND / COLLAPSE + DETAIL untuk SEMUA tabel
   --------------------------------------------------------------------------
   Menambahkan perilaku yang sama seperti tabel Data STO ke setiap tabel data
   di seluruh menu:
     • tombol chevron di awal sel → buka/tutup panel detail inline
     • klik baris → drawer detail dari kanan
   Tidak perlu mengubah view apa pun: label diambil otomatis dari <thead>,
   nilai diambil dari sel baris. Tabel yang sudah punya implementasi sendiri
   (atribut data-rowdetail, mis. Data STO) dilewati.
   ========================================================================== */
(function () {
    'use strict';

    var MARK = 'data-xrd';           /* penanda tabel sudah diproses */
    var ROWMARK = 'data-xrd-row';    /* penanda baris sudah dipasangi chevron */

    function ready(fn) {
        if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', fn);
        else fn();
    }

    var BLN = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];

    function prettyVal(s) {
        var t = String(s == null ? '' : s);
        var m = t.match(/^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2})/);
        if (!m) return t;
        return parseInt(m[3], 10) + ' ' + (BLN[parseInt(m[2], 10) - 1] || m[2]) + ' ' + m[1] + ' ' + m[4] + ':' + m[5];
    }

    function esc(s) {
        return prettyVal(s)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    }

    function clean(s) { return String(s || '').replace(/\s+/g, ' ').trim(); }

    /* ---------------------------------------------------------------- data */
    function headLabels(table) {
        var thead = table.tHead;
        if (!thead || !thead.rows.length) return [];
        var tr = thead.rows[thead.rows.length - 1];
        var out = [];
        for (var i = 0; i < tr.cells.length; i++) {
            var th = tr.cells[i];
            var lab = th.getAttribute('data-label') || th.innerText || th.textContent || '';
            out.push(clean(lab).replace(/\s*[▾▼▲]\s*$/, ''));
        }
        return out;
    }

    function skipCell(td) {
        if (!td) return true;
        if (td.hasAttribute('data-xskip')) return true;
        if (td.classList.contains('col-actions') || td.classList.contains('cell-actions')) return true;
        var onlyCheckbox = td.querySelector('input[type=checkbox],input[type=radio]') &&
            clean(td.innerText).length < 2;
        return !!onlyCheckbox;
    }

    function rowFields(table, row) {
        var H = headLabels(table), out = [];
        var start = 0;
        var hc = contentCell(row);
        if (hc) start = hc.cellIndex;          /* lewati kolom # / pilih */
        for (var i = 0; i < row.cells.length; i++) {
            var td = row.cells[i];
            if (i < start) continue;
            if (skipCell(td)) continue;
            var txt = clean(td.innerText);
            if (!txt) continue;
            if (/^(—|-|–)$/.test(txt)) continue;
            /* kolom aksi: header AKSI / Action / Opsi, atau sel khusus aksi */
            var hlab = clean(H[i] || '');
            if (/^(aksi|action|aksi\s*&\s*status|opsi|tools?|operasi)$/i.test(hlab)) continue;
            if (td.classList.contains('col-actions') || td.classList.contains('cell-actions')) continue;
            /* kolom aksi: isinya hanya ikon/tombol */
            if (td.querySelector('button,a.btn,.row-actions,.btn-group') && txt.length < 3) continue;
            out.push({ k: (H[i] || ('Kolom ' + (i + 1))).toUpperCase(), v: txt });
        }
        return out;
    }

    function titleOf(fields, row) {
        var prefer = /NAMA|ITEM|AKTIVITAS|KETERANGAN|DESKRIPSI|USER|GROUP|PETUGAS|NAMA FILE|PERIODE|DOKUMEN/i;
        var best = null;
        for (var i = 0; i < fields.length; i++) {
            if (prefer.test(fields[i].k) && fields[i].v.length > 2) { best = fields[i].v; break; }
        }
        if (!best) {
            for (var j = 0; j < Math.min(3, fields.length); j++) {
                if (fields[j].v.length > (best ? best.length : 0)) best = fields[j].v;
            }
        }
        return best || clean(row.innerText).slice(0, 80) || 'Detail baris';
    }

    function codeOf(fields) {
        var i, k, v;
        for (i = 0; i < fields.length; i++) {
            k = clean(fields[i].k); v = clean(fields[i].v);
            if (!v || v.length > 28) continue;
            if (!/^(kode|code|sku|nik|nim|nomor|no\.?|id)(\s|$)/i.test(k)) continue;
            return v;
        }
        for (i = 0; i < fields.length; i++) {
            v = clean(fields[i].v);
            if (/^[A-Z0-9][A-Z0-9\-_.\/]{2,27}$/.test(v) && !/^[\d.,]+$/.test(v)) return v;
        }
        return '';
    }

    /* -------------------------------------------------------------- drawer */
    var drawer = null, backdrop = null, lastFocus = null, activeRow = null;

    function buildDrawer() {
        if (drawer) return;
        backdrop = document.createElement('div');
        backdrop.className = 'rd-backdrop';

        drawer = document.createElement('aside');
        drawer.className = 'rd-drawer';
        drawer.setAttribute('role', 'dialog');
        drawer.setAttribute('aria-modal', 'true');
        drawer.setAttribute('aria-label', 'Detail baris');
        drawer.setAttribute('tabindex', '-1');
        drawer.innerHTML =
            '<header class="rd-head">' +
                '<div class="rd-head-main">' +
                    '<div class="rd-eyebrow" data-xrd-eyebrow>Detail Baris</div>' +
                    '<h2 class="rd-title" data-xrd-title>—</h2>' +
                    '<div class="rd-head-badges" data-xrd-badges></div>' +
                '</div>' +
                '<button type="button" class="rd-x" data-xrd-close aria-label="Tutup detail">' +
                    '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg>' +
                '</button>' +
            '</header>' +
            '<div class="rd-body" data-xrd-body></div>' +
            '<footer class="rd-foot"><button type="button" class="rd-btn rd-btn-ghost" data-xrd-close>Tutup</button>' +
                '<a class="rd-btn rd-btn-primary" data-xrd-page href="#" style="display:none">Buka Halaman</a>' +
            '</footer>';

        document.body.appendChild(backdrop);
        document.body.appendChild(drawer);

        drawer.addEventListener('click', function (e) {
            if (e.target.closest('[data-xrd-close]')) closeDrawer();
        });
        backdrop.addEventListener('click', closeDrawer);
    }


    /* --------------------------------------------------- badge / tone (konsisten) */
    function toneOf(text) {
        var s = clean(text).toLowerCase();
        if (!s || s === '-' || s === '—') return 'is-grey';
        if (/belum|pending|menunggu|draft|baru|kosong|n\/a/.test(s)) return 'is-grey';
        if (/nonaktif|inactive|tidak aktif|diblokir|disabled/.test(s)) return 'is-red';
        if (/recount|hitung ulang|selisih|tidak cocok|kurang|lebih|gagal|ditolak|batal|error|terbuka|open/.test(s)) return 'is-red';
        if (/proses|berjalan|diaudit|audit|submit|terposting|posted|sebagian|partial/.test(s)) return 'is-blue';
        if (/perlu|warning|perhatian/.test(s)) return 'is-amber';
        if (/selesai|sesuai|cocok|ok|valid|terverifikasi|terkunci|done|lengkap|sukses|aktif|active|ya$/.test(s)) return 'is-green';
        return 'is-grey';
    }

    /* tone sadar-angka: progres 100% hijau, selisih 0 hijau, selisih ≠ 0 merah */
    function toneFor(k, v) {
        var s = clean(v), key = String(k || '');
        if (/^[+\-]?[\d.,]+\s*%?$/.test(s)) {
            var n = parseFloat(s.replace(/[^\d+\-]/g, ''));
            if (isNaN(n)) return 'is-grey';
            if (/%/.test(s) || /progres|progress|target/i.test(key)) return n >= 100 ? 'is-green' : (n > 0 ? 'is-amber' : 'is-grey');
            if (/selisih|diff/i.test(key)) return n === 0 ? 'is-green' : 'is-red';
            return 'is-grey';
        }
        return toneOf(s);
    }

    function isStatusLabel(k) {
        return /status|keadaan|kondisi|progres|progress|hasil|audit|hitung|selisih|terkunci/i.test(String(k || ''));
    }

    function isPillable(k, v) {
        if (!isStatusLabel(k) || !clean(v)) return false;
        if (clean(v).length > 22 || /^rp/i.test(clean(v))) return false;   /* teks panjang / nominal -> teks biasa */
        if (/^[\d.,%+\-\s]+$/.test(clean(v)) && !/progres|progress|selisih/i.test(String(k || ''))) return false;
        return true;
    }

    function pill(k, v) {
        return '<span class="rd-pill ' + toneFor(k, v) + '">' + esc(v) + '</span>';
    }

    /* daftar kolom status yang layak jadi badge (maks 2) */
    function statusFields(fields, title) {
        var seen = {}, out = [];
        for (var i = 0; i < fields.length && out.length < 2; i++) {
            var f = fields[i], v = clean(f.v);
            if (!isStatusLabel(f.k)) continue;
            if (!v || v === '-' || v === '—') continue;
            if (v === clean(title)) continue;
            if (/^rp/i.test(v) || v.length > 22) continue;
            if (/^[\d.,%+\-\s]+$/.test(v) && !/progres|progress|selisih/i.test(f.k)) continue;
            if (seen[v.toLowerCase()]) continue;
            seen[v.toLowerCase()] = 1; out.push(f);
        }
        return out;
    }

    function statusBadges(list) {
        return list.map(function (f) { return pill(f.k, f.v); }).join('');
    }

    /* kartu ringkas di atas popup: 3 angka/status paling penting */
    function pickHero(fields) {
        var pats = [/selisih/i, /total|jumlah/i, /progres|progress|target/i, /status/i, /nilai|harga|rupiah|rp/i, /stok|qty|fisik|hitung/i];
        var used = [], out = [];
        var ok = function (i) {
            var f = fields[i];
            if (!f || used.indexOf(i) > -1) return false;
            if (!clean(f.v) || clean(f.v).length > 24) return false;
            if (/^kode|^code|^id$|^sku/i.test(clean(f.k))) return false;
            return true;
        };
        for (var p = 0; p < pats.length && out.length < 3; p++) {
            for (var i = 0; i < fields.length && out.length < 3; i++) {
                if (!ok(i) || !pats[p].test(fields[i].k)) continue;
                used.push(i); out.push(fields[i]); break;
            }
        }
        for (var j = 0; j < fields.length && out.length < 3; j++) {
            if (!ok(j)) continue;
            used.push(j); out.push(fields[j]);
        }
        return out;
    }

    function heroHtml(list) {
        if (!list.length) return '';
        var cells = list.map(function (f) {
            var cls = isStatusLabel(f.k) ? ' ' + toneFor(f.k, f.v) : '';
            return '<div class="rd-hero-cell' + cls + '">' +
                '<span class="rd-hero-k">' + esc(f.k) + '</span>' +
                '<span class="rd-hero-v' + (String(f.v).length > 6 ? ' is-sm' : '') + '">' + esc(f.v) + '</span></div>';
        }).join('');
        return '<div class="rd-hero">' + cells + '</div>';
    }

    function sectionHtml(title, fields) {
        fields = fields.filter(function (f) { return clean(f.v) !== ''; });
        if (!fields.length) return '';
        var cells = fields.map(function (f) {
            var mono = /KODE|CODE|ID|NOMOR|SKU|NILAI|HARGA|TOTAL|QTY|JUMLAH|RP/i.test(f.k);
            var bar = /PROGRES|PROGRESS|CAPAIAN|%/i.test(f.k) ? progressCell(f) : null;
            var val = bar !== null ? bar : (isPillable(f.k, f.v) ? pill(f.k, f.v) : esc(f.v));
            return '<div class="rd-cell">' +
                '<div class="rd-k">' + esc(f.k) + '</div>' +
                '<div class="rd-v' + (mono ? ' is-mono' : '') + '">' + val + '</div>' +
            '</div>';
        }).join('');
        return '<section class="rd-sec"><h3 class="rd-sec-t">' + esc(title) + '</h3>' +
            '<div class="rd-grid">' + cells + '</div></section>';
    }


    /* ------------------------------------------------------- rich renderer */
    var ICON = {
        box: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21 8l-9-5-9 5 9 5 9-5z"/><path d="M3 8v8l9 5 9-5V8"/><path d="M12 13v8"/></svg>',
        check: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg>',
        clip: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="8" y="3" width="8" height="4" rx="1.2"/><path d="M16 5h2a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2h2"/><path d="M9 14l2 2 4-4"/></svg>',
        clock: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>',
        layers: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3l9 5-9 5-9-5 9-5z"/><path d="M3 13l9 5 9-5"/></svg>',
        chart: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/></svg>',
        money: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="2.5" y="6" width="19" height="12" rx="2.5"/><circle cx="12" cy="12" r="2.6"/><path d="M6 12h.01M18 12h.01"/></svg>',
        users: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="8" r="3.2"/><path d="M2.5 20a6.5 6.5 0 0 1 13 0"/><path d="M17 11a3 3 0 1 0 0-6"/><path d="M18 20a5.6 5.6 0 0 0-2-4.3"/></svg>'
    };

    function iconFor(k) {
        if (/SELISIH|DIFF/i.test(k)) return ICON.clip;
        if (/DIAUDIT|AUDIT/i.test(k)) return ICON.check;
        if (/TARGET|SISA|ANTRE|MENUNGGU|PENDING/i.test(k)) return ICON.clock;
        if (/PROGRES|PROGRESS|PERSEN|%/i.test(k)) return ICON.chart;
        if (/NILAI|HARGA|RP|RUPIAH/i.test(k)) return ICON.money;
        if (/PETUGAS|USER|AUDITOR|OBSERVER|OLEH/i.test(k)) return ICON.users;
        if (/TOTAL|JUMLAH|ITEM|STOK|QTY|LAYER/i.test(k)) return ICON.layers;
        return ICON.box;
    }

    function toneOf(k) {
        if (/SELISIH|DIFF/i.test(k)) return 'is-rose';
        if (/DIAUDIT|AUDIT|SELESAI|TERPOSTING|POSTED|TERCAPAI/i.test(k)) return 'is-mint';
        if (/SISA|ANTRE|MENUNGGU|PENDING|BELUM/i.test(k)) return 'is-rose';
        if (/TARGET|MINIMAL/i.test(k)) return 'is-blue';
        if (/PROGRES|PROGRESS|PERSEN|%/i.test(k)) return 'is-blue';
        if (/NILAI|HARGA|RP/i.test(k)) return 'is-blue';
        return 'is-violet';
    }

    function chipOf(k) {
        if (/TERPOSTING|POSTED/i.test(k)) return 'sudah diposting';
        if (/DIAUDIT|AUDIT/i.test(k)) return 'sudah diaudit';
        if (/TARGET|MINIMAL/i.test(k)) return 'target minimal';
        if (/SISA/i.test(k)) return 'belum selesai';
        if (/PROGRES|PERSEN|%/i.test(k)) return 'capaian';
        if (/SELISIH|DIFF/i.test(k)) return 'perlu tindakan';
        if (/NILAI|HARGA|RP/i.test(k)) return 'nilai rupiah';
        if (/BELUM|PENDING|MENUNGGU/i.test(k)) return 'menunggu';
        if (/SISTEM/i.test(k)) return 'stok tercatat';
        if (/HITUNG/i.test(k)) return 'hasil hitung';
        if (/^ITEM$|TOTAL|JUMLAH/i.test(k)) return 'total item';
        if (/^RAK$/i.test(k)) return 'rak penyimpanan';
        if (/^GROUP/i.test(k)) return 'jumlah group';
        if (/^DEVICE/i.test(k)) return 'perangkat';
        return 'ringkasan';
    }

    function numLike(v) {
        v = clean(v);
        return v.length > 0 && v.length <= 24 && /\d/.test(v) &&
            /^[+\-]?\s*[\d.,]+\s*(%|[a-zA-Z]{1,8})?$/.test(v);
    }

    function metricHtml(f) {
        return '<div class="rd-mc ' + toneOf(f.k) + '">' +
            '<div class="rd-mc-top"><span class="rd-mc-ico">' + iconFor(f.k) + '</span>' +
            '<span class="rd-mc-k">' + esc(f.k) + '</span></div>' +
            '<div class="rd-mc-v">' + esc(f.v) + '</div>' +
            '<div class="rd-mc-bot"><span class="rd-mc-chip">' + esc(chipOf(f.k)) + '</span></div>' +
        '</div>';
    }

    function progressCell(f) {
        var m = clean(f.v).match(/(\d+(?:[.,]\d+)?)\s*%/);
        if (!m) return null;
        var pct = Math.max(0, Math.min(100, parseFloat(m[1].replace(',', '.'))));
        var ok = pct >= 100;
        return '<div class="coverage ' + (ok ? 'is-ok' : 'is-warn') + '">' +
            '<div class="bar ' + (ok ? 'is-success' : 'is-warning') + ' cov-bar"><span style="width:' + pct + '%"></span></div>' +
            '<span class="cov-pct">' + esc(f.v) + '</span></div>';
    }

    function openDrawer(row) {
        var table = row.closest('table');
        if (!table) return;
        buildDrawer();
        activeRow = row;

        /* data bisa datang dari atribut data-xrd-json (kaya seperti Data STO)
           atau hasil ekstraksi sel tabel (fallback generik). */
        var meta = { title: '', code: '', url: '', note: '' };
        var fields = null;
        var rawJson = row.getAttribute('data-xrd-json');
        if (rawJson) {
            try {
                var j = JSON.parse(rawJson);
                if (j && j.fields && j.fields.length) {
                    fields = j.fields.map(function (f) { return { k: clean(f.k), v: clean(f.v) }; });
                    meta.title = clean(j.title || '');
                    meta.code = clean(j.code || '');
                    meta.url = j.url || '';
                    meta.note = j.note || '';
                }
            } catch (e) { fields = null; }
        }
        if (!fields) fields = rowFields(table, row);
        fields = fields.filter(function (f) { return !/^(aksi|action)$/i.test(clean(f.k)); });

        var title = meta.title || titleOf(fields, row);
        var code = meta.code || codeOf(fields);
        var menu = (document.querySelector('.page-title, .page-header h1, h1') || {}).innerText || 'Detail';

        var st = statusFields(fields, title);

        /* hero: 4 angka terpenting (seperti kartu metrik Data STO) */
        var hero = [];
        for (var i = 0; i < fields.length && hero.length < 4; i++) {
            var f = fields[i];
            if (st.indexOf(f) > -1) continue;
            if (clean(f.v) === clean(title)) continue;
            if (/^(KODE|CODE|ID|NOMOR|NO\.?|SKU|NIK)(\s|$)/i.test(clean(f.k))) continue;
            if (!numLike(f.v)) continue;
            hero.push(f);
        }

        var rest = fields.filter(function (f) {
            return hero.indexOf(f) === -1 && clean(f.v) !== clean(title);
        });
        var timePat = /WAKTU|TANGGAL|DIBUAT|DIUBAH|DIPERBARUI|DIINPUT|DIPOSTING|DILAPORKAN|OLEH|PETUGAS|AUDITOR|OBSERVER|USER|TERAKHIR/i;
        var timeFields = [], numFields = [], idFields = [];
        rest.forEach(function (f) {
            if (timePat.test(clean(f.k))) timeFields.push(f);
            else if (numLike(f.v)) numFields.push(f);
            else idFields.push(f);
        });

        drawer.querySelector('[data-xrd-eyebrow]').textContent = clean(menu).slice(0, 60) || 'Detail Baris';
        drawer.querySelector('[data-xrd-title]').textContent = title;
        drawer.querySelector('[data-xrd-badges]').innerHTML =
            (code ? '<span class="rd-code">' + esc(code) + '</span>' : '') + statusBadges(st);

        var body = '';
        if (hero.length) body += '<div class="rd-hero is-stack">' + hero.map(metricHtml).join('') + '</div>';
        body += sectionHtml('Ringkasan', idFields);
        body += sectionHtml('Angka & Progres', numFields);
        body += sectionHtml('Petugas & Waktu', timeFields);
        if (meta.note) {
            body += '<section class="rd-sec"><h3 class="rd-sec-t">Catatan</h3>' +
                '<div class="rd-grid"><div class="rd-cell is-wide"><div class="rd-v">' + esc(meta.note) + '</div></div></div></section>';
        }
        if (!body) {
            body = '<div class="rd-sec"><div class="rd-grid"><div class="rd-cell is-wide">' +
                '<div class="rd-v is-empty">Tidak ada detail tambahan untuk baris ini.</div></div></div></div>';
        }
        drawer.querySelector('[data-xrd-body]').innerHTML = body;

        /* footer: tombol buka halaman (seperti Data STO) */
        var pageBtn = drawer.querySelector('[data-xrd-page]');
        var href = meta.url;
        if (!href) {
            var a = row.querySelector('.cell-title a[href], a[href]:not([href="#"])');
            if (a) href = a.getAttribute('href');
        }
        if (pageBtn) {
            if (href && href !== '#') { pageBtn.href = href; pageBtn.style.display = ''; }
            else { pageBtn.style.display = 'none'; }
        }

        lastFocus = document.activeElement;
        document.documentElement.classList.add('rd-lock');
        row.classList.add('is-active-row');
        void drawer.offsetHeight;
        drawer.classList.add('is-open');
        backdrop.classList.add('is-open');
        try { drawer.focus({ preventScroll: true }); } catch (e) {}
    }

    function closeDrawer() {
        if (!drawer) return;
        drawer.classList.remove('is-open');
        backdrop.classList.remove('is-open');
        document.documentElement.classList.remove('rd-lock');
        if (activeRow) { activeRow.classList.remove('is-active-row'); activeRow = null; }
        if (lastFocus && lastFocus.focus) { try { lastFocus.focus({ preventScroll: true }); } catch (e) {} }
    }

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && drawer && drawer.classList.contains('is-open')) {
            e.preventDefault();
            closeDrawer();
        }
    });

    /* ------------------------------------------------- inline expand panel */
    function colspanOf(table, row) {
        var thead = table.tHead;
        if (thead && thead.rows.length) {
            var n = 0;
            for (var i = 0; i < thead.rows[thead.rows.length - 1].cells.length; i++) {
                var th = thead.rows[thead.rows.length - 1].cells[i];
                n += (parseInt(th.getAttribute('colspan'), 10) || 1);
            }
            return n || row.cells.length;
        }
        return row.cells.length;
    }

    function panelHtml(fields, title, code) {
        var pf = statusFields(fields, title);
        var pv = pf.map(function (f) { return clean(f.v).toLowerCase(); });
        var gridFields = fields.filter(function (f) {
            return !(isStatusLabel(f.k) && pv.indexOf(clean(f.v).toLowerCase()) > -1);
        });
        var grid = gridFields.map(function (f) {
            var mono = /KODE|CODE|ID|NOMOR|SKU|NILAI|HARGA|TOTAL|QTY|JUMLAH/i.test(f.k);
            var val = isPillable(f.k, f.v) ? pill(f.k, f.v) : esc(f.v);
            return '<div class="rd-xcell"><span>' + esc(f.k) + '</span>' +
                '<strong' + (mono ? ' class="is-mono"' : '') + '>' + val + '</strong></div>';
        }).join('');
        return '<div class="rd-xpanel">' +
            '<div class="rd-xhead">' +
                (code ? '<span class="rd-xcode">' + esc(code) + '</span>' : '') +
                '<strong>' + esc(title) + '</strong>' +
                statusBadges(pf) +
                '<div class="rd-xspacer"></div>' +
                '<button type="button" class="rd-xbtn" data-xrd-open>Detail lengkap</button>' +
            '</div>' +
            (grid ? '<div class="rd-xgrid">' + grid + '</div>' : '') +
        '</div>';
    }

    function toggleRow(table, row, btn) {
        var next = row.nextElementSibling;
        if (next && next.classList.contains('rd-xrow')) {
            next.classList.add('is-closing');
            row.classList.remove('is-expanded');
            btn.setAttribute('aria-expanded', 'false');
            window.setTimeout(function () { if (next.parentNode) next.parentNode.removeChild(next); }, 160);
            return;
        }
        var fields = rowFields(table, row);
        var tr = document.createElement('tr');
        tr.className = 'rd-xrow';
        var td = document.createElement('td');
        td.colSpan = colspanOf(table, row);
        td.className = 'rd-xcellwrap';
        td.innerHTML = panelHtml(fields, titleOf(fields, row), codeOf(fields));

        var wrap = table.closest('.table-wrap') || table.parentElement;
        var panel = td.querySelector('.rd-xpanel');
        if (wrap && wrap.clientWidth) panel.style.maxWidth = Math.max(280, wrap.clientWidth - 22) + 'px';

        tr.appendChild(td);
        row.parentNode.insertBefore(tr, row.nextSibling);
        row.classList.add('is-expanded');
        btn.setAttribute('aria-expanded', 'true');
        void tr.offsetHeight;
        tr.classList.add('is-in');
        window.setTimeout(function () { if (tr.parentNode) tr.classList.add('is-in'); }, 40);

        var openBtn = td.querySelector('[data-xrd-open]');
        if (openBtn) openBtn.addEventListener('click', function (e) {
            e.preventDefault(); e.stopPropagation(); openDrawer(row);
        });
    }

    /* ---------------------------------------------------------------- wire */
    function rowSkip(table, row) {
        if (row.classList.contains('rd-xrow')) return true;
        if (row.hasAttribute('data-rd')) return true;              /* sudah punya detail sendiri */
        if (!row.cells || !row.cells.length) return true;
        if (row.querySelector('td[colspan]')) return true;          /* baris empty-state */
        if (row.closest('tfoot')) return true;
        var cls = (row.className || '').toLowerCase();
        if (/(^|\s)(total|subtotal|grand-total|footer|empty|loading|no-data)(\s|$)/.test(cls)) return true;
        /* baris rekap: teks sel pertama diawali TOTAL / JUMLAH */
        var firstTxt = clean(row.cells[0] ? row.cells[0].innerText : '').toLowerCase();
        if (/^(total|jumlah|grand total|subtotal)\b/.test(firstTxt)) return true;
        return false;
    }

    /* Kolom konten pertama = kolom yang sama untuk SEMUA baris & SEMUA menu:
       lewati kolom pilih (checkbox) dan kolom aksi, lalu ambil kolom pertama. */
    function contentCell(row) {
        for (var i = 0; i < row.cells.length; i++) {
            var td = row.cells[i];
            if (!td) continue;
            if (td.hasAttribute('data-xskip')) continue;
            if (td.classList.contains('col-actions') || td.classList.contains('cell-actions')) continue;
            if (td.querySelector('input[type=checkbox],input[type=radio]') && clean(td.innerText).length < 2) continue;
            if (/^(select|pilih|#)$/i.test(td.getAttribute('data-label') || '')) continue;
            if (td.classList.contains('memo-pick') || td.classList.contains('col-num')) continue;
            /* kolom nomor urut (isinya hanya angka) bukan kolom konten */
            var t = clean(td.innerText);
            if (/^\d{1,5}$/.test(t) && !td.querySelector('a,button,.badge,.cell-title,strong')) continue;
            return td;
        }
        return null;
    }

    /* Bungkus isi sel supaya chevron SELALU di kiri, isi sel bergeser rapi 8px.
       Struktur dalam sel (nama + sub-teks, badge, dsb.) tidak berubah. */
    function makeHost(td) {
        if (td.classList.contains('rd-host')) return td;
        var main = document.createElement('span');
        main.className = 'rd-hostmain';
        while (td.firstChild) main.appendChild(td.firstChild);
        td.appendChild(main);
        td.classList.add('rd-host');
        return td;
    }

    function wireRow(table, row) {
        if (row.hasAttribute(ROWMARK)) return;
        if (rowSkip(table, row)) return;
        var host = contentCell(row);
        if (!host) return;
        row.setAttribute(ROWMARK, '1');
        makeHost(host);

        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'rd-toggle';
        btn.setAttribute('aria-expanded', 'false');
        btn.setAttribute('aria-label', 'Buka detail baris');
        btn.title = 'Expand / collapse detail baris';
        btn.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="m9 6 6 6-6 6"/></svg>';
        btn.addEventListener('click', function (e) {
            e.preventDefault(); e.stopPropagation();
            toggleRow(table, row, btn);
        });

        /* posisi tetap: elemen pertama sel konten, sejajar vertikal dengan isi */
        host.insertBefore(btn, host.firstChild);
    }

    function wireTable(table) {
        if (!table || table.hasAttribute('data-rowdetail')) return;
        var tbody = table.tBodies && table.tBodies[0];
        if (!tbody) return;
        if (!table.hasAttribute(MARK)) table.setAttribute(MARK, '1');

        for (var i = 0; i < tbody.rows.length; i++) wireRow(table, tbody.rows[i]);

        if (!table.__xrdObserved) {
            table.__xrdObserved = true;
            var obs = new MutationObserver(function (muts) {
                var need = false;
                for (var m = 0; m < muts.length; m++) {
                    if (muts[m].addedNodes && muts[m].addedNodes.length) { need = true; break; }
                }
                if (!need) return;
                window.clearTimeout(table.__xrdT);
                table.__xrdT = window.setTimeout(function () {
                    var tb = table.tBodies[0];
                    if (!tb) return;
                    for (var r = 0; r < tb.rows.length; r++) wireRow(table, tb.rows[r]);
                }, 60);
            });
            obs.observe(tbody, { childList: true });
        }

        /* klik baris → drawer (kecuali baris punya aksi sendiri) */
        if (!table.__xrdClick) {
            table.__xrdClick = true;
            table.addEventListener('click', function (e) {
                var tr = e.target.closest('tbody tr');
                if (!tr || !tr.hasAttribute(ROWMARK)) return;
                if (e.target.closest('a,button,input,select,textarea,label,form,.rd-toggle,.rd-xbtn,.row-actions,.btn-group')) return;
                var sel = window.getSelection && String(window.getSelection());
                if (sel && sel.length > 2) return;
                openDrawer(tr);
            });
        }
    }

    function scan(root) {
        var tables = (root || document).querySelectorAll('table.data, table.sf-summary-table, table.prg-d-table, table[data-table-expand]');
        for (var i = 0; i < tables.length; i++) wireTable(tables[i]);
    }

    ready(function () {
        scan(document);
        /* halaman yang merender tabel belakangan (ajax/filter) */
        var mo = new MutationObserver(function () {
            window.clearTimeout(window.__xrdScanT);
            window.__xrdScanT = window.setTimeout(function () { scan(document); }, 120);
        });
        mo.observe(document.body, { childList: true, subtree: true });
    });
})();
