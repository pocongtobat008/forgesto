/* =====================================================================
   StockForge · sf-mat.js — Analisis Materialitas: aksi "Kirim ke Petugas"
   dan "Print ke PDF" untuk item yang SEDANG TAMPIL (sesuai group + ambang
   materialitas yang dipilih). Berdiri sendiri, progressive-enhancement:
   tidak ada elemen → no-op total. Bergantung pada window.sfRowIsVisible
   (arbiter visibilitas di sf-ui.js) tetapi tetap fallback ke style.display.
   ===================================================================== */
(function () {
    'use strict';

    var doc = document;
    var $  = function (s, c) { return (c || doc).querySelector(s); };
    var $$ = function (s, c) { return Array.prototype.slice.call((c || doc).querySelectorAll(s)); };

    var panel = doc.getElementById('sf-materiality');
    var table = doc.getElementById('recon-table');
    if (!panel || !table) return;

    var allRows   = $$('tr.recon-row', table);
    if (!allRows.length) return;

    var noteEl    = doc.getElementById('sf-mat-scope-note');
    var groupSel  = doc.getElementById('sf-mat-group');
    var readoutEl = doc.getElementById('sf-mat-readout');
    var sendBtn   = doc.getElementById('sf-mat-send');
    var printBtn  = doc.getElementById('sf-mat-print');
    var sendForm  = doc.getElementById('sf-mat-send-form');
    var idsSel    = doc.getElementById('sf-mat-ids');

    function visible(row) {
        return window.sfRowIsVisible ? window.sfRowIsVisible(row) : (row.style.display !== 'none');
    }

    // Baris yang lolos filter MATERIALITAS (ambang + group), terlepas dari
    // filter lain (instant / live-search / tahapan).
    function inMatScope(row) {
        var m = row._sfVis || {};
        return !m.materiality && !m.group;
    }

    // Baris yang benar-benar tampil di tabel = lolos SEMUA filter.
    function shownRows() {
        return allRows.filter(function (r) { return inMatScope(r) && visible(r); });
    }

    function rowDiff(row) {
        var d = parseFloat(row.getAttribute('data-diff'));
        return isNaN(d) ? 0 : d;
    }
    function rpx(n) { return 'Rp ' + Math.round(n).toLocaleString('id-ID'); }

    // ID rekonsiliasi sebuah baris: ambil dari checkbox baris (paling andal),
    // fallback ke data-id pada <tr>, lalu ke href tombol detail /reconciliation/{id}.
    function rowId(row) {
        var cb = row.querySelector('.recon-row-check');
        if (cb && cb.value) return String(cb.value);
        var di = row.getAttribute('data-id');
        if (di) return String(di);
        var a = row.querySelector('a[href*="/reconciliation/"]');
        if (a) { var m = (a.getAttribute('href') || '').match(/\/reconciliation\/(\d+)/); if (m) return m[1]; }
        return '';
    }

    function groupLabel() {
        if (!groupSel || !groupSel.value) return 'Semua group';
        var o = groupSel.options[groupSel.selectedIndex];
        return o ? o.textContent.trim() : 'Semua group';
    }
    function thresholdLabel() {
        return readoutEl ? readoutEl.textContent.trim() : '';
    }

    function scopeModel() {
        var shown = shownRows();
        var scope = allRows.filter(inMatScope);
        var shownSum = shown.reduce(function (a, r) { return a + Math.abs(rowDiff(r)); }, 0);
        var scopeSum = scope.reduce(function (a, r) { return a + Math.abs(rowDiff(r)); }, 0);
        var pctVal = scopeSum ? Math.round(shownSum / scopeSum * 100) : 0;
        return { shown: shown, scope: scope, shownSum: shownSum, pctVal: pctVal };
    }

    var noteTimer = null;
    function updateNote() {
        if (noteTimer) clearTimeout(noteTimer);
        noteTimer = setTimeout(function () {
            noteTimer = null;
            if (!noteEl) return;
            var m = scopeModel();
            if (m.scope.length === 0) {
                noteEl.innerHTML = '<span class="sf-mat-scope-empty">Tidak ada item dalam cakupan materialitas ini.</span>';
                if (sendBtn) sendBtn.disabled = true;
                return;
            }
            noteEl.innerHTML = '<span class="sf-mat-scope-lbl">Cakupan aksi:</span> ' +
                '<strong>' + m.shown.length + '</strong> dari ' + m.scope.length + ' item lolos ambang ' +
                '<strong>' + thresholdLabel() + '</strong> &middot; group <strong>' + groupLabel() + '</strong>' +
                ' &middot; selisih <strong class="mono">' + rpx(m.shownSum) + '</strong> (' + m.pctVal + '%)' +
                (sendBtn ? '' : '');
            if (sendBtn) sendBtn.disabled = m.shown.length === 0;
        }, 60);
    }

    /* ---------------- Kirim ke Petugas (bulk) ---------------- */
    var origSubmit = sendForm ? sendForm.getAttribute('data-confirm') : '';
    function doSend(e) {
        if (!sendForm || !idsSel) return;
        var m = scopeModel();
        if (m.shown.length === 0) {
            if (e) e.preventDefault();
            window.sfToast && window.sfToast('Tidak ada item yang tampil untuk dikirim.', 'warning');
            return;
        }
        if (origSubmit && !window.confirm(origSubmit + '\n\n' + m.shown.length + ' item akan dikirim ke petugas.')) {
            if (e) e.preventDefault();
            return;
        }
        idsSel.innerHTML = '';
        m.shown.forEach(function (r) {
            var id = rowId(r);
            if (!id) return;
            var opt = doc.createElement('option');
            opt.value = id;
            opt.selected = true;
            idsSel.appendChild(opt);
        });
        if (e) return; // biarkan form submit natural
        sendForm.submit();
    }
    if (sendBtn) {
        sendBtn.addEventListener('click', function (ev) {
            ev.preventDefault();
            if (!sendForm) return;
            // stop existing submit handlers from double-confirming; we handle it.
            doSend(null);
        });
    }

    /* ---------------- Print / simpan PDF ---------------- */
    function cellText(tr, idx) {
        var tds = tr.children;
        if (!tds || idx >= tds.length) return '';
        return (tds[idx].innerText || tds[idx].textContent || '').replace(/\s+/g, ' ').trim();
    }

    function buildPrintable() {
        var m = scopeModel();
        var headThs = $$('thead th', table).map(function (th) { return (th.innerText || th.textContent || '').trim(); });
        var hasCheck = headThs.length && headThs[0] === '';
        var cols = headThs.slice(hasCheck ? 1 : 0);

        function cellsFor(tr) {
            var tds = Array.prototype.slice.call(tr.children);
            if (hasCheck) tds = tds.slice(1);
            return tds.slice(0, cols.length).map(function (td) { return (td.innerText || td.textContent || '').replace(/\s+/g,' ').trim(); });
        }

        var rowsHtml = m.shown.map(function (r, i) {
            var cls = rowDiff(r) < 0 ? 'neg' : (rowDiff(r) > 0 ? 'pos' : '');
            return '<tr class="' + cls + '"><td class="num">' + (i + 1) + '</td>' +
                cellsFor(r).map(function (c) { return '<td>' + esc(c) + '</td>'; }).join('') + '</tr>';
        }).join('');

        return '<!DOCTYPE html><html lang="id"><head><meta charset="utf-8">' +
            '<title>Analisis Materialitas: ' + esc(groupLabel()) + '</title>' +
            '<style>' + printCss() + '</style></head><body>' +
            '<header class="doc-head">' +
              '<div class="brand">StockForge</div>' +
              '<h1>Analisis Materialitas: Rekonsiliasi Nilai Stok</h1>' +
              '<div class="meta">' +
                '<span><b>Group:</b> ' + esc(groupLabel()) + '</span>' +
                '<span><b>Ambang materialitas:</b> ' + esc(thresholdLabel()) + '</span>' +
                '<span><b>Item tampil:</b> ' + m.shown.length + ' dari ' + m.scope.length + '</span>' +
                '<span><b>Total selisih tampil:</b> ' + esc(rpx(m.shownSum)) + ' (' + m.pctVal + '% dari cakupan)</span>' +
                '<span><b>Dicetak:</b> ' + esc(new Date().toLocaleString('id-ID')) + '</span>' +
              '</div>' +
            '</header>' +
            '<table><thead><tr><th class="num">#</th>' +
              cols.map(function (c) { return '<th>' + esc(c) + '</th>'; }).join('') +
            '</tr></thead><tbody>' + rowsHtml + '</tbody></table>' +
            '<footer class="doc-foot">Dibuat otomatis oleh StockForge. Daftar mengikuti filter Analisis Materialitas yang aktif.</footer>' +
            '</body></html>';
    }

    function esc(s) {
        return String(s == null ? '' : s).replace(/[&<>"]/g, function (c) {
            return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' })[c];
        });
    }

    function printCss() {
        return '@page{size:A4 landscape;margin:12mm}' +
            '*{box-sizing:border-box}' +
            'body{font-family:"Google Sans Flex","Segoe UI",Arial,sans-serif;color:#111;margin:0;font-size:11px}' +
            '.doc-head{border-bottom:2px solid #f0b429;padding-bottom:8px;margin-bottom:12px}' +
            '.doc-head .brand{font-weight:800;letter-spacing:.08em;text-transform:uppercase;color:#b8860b;font-size:11px}' +
            '.doc-head h1{font-size:17px;margin:2px 0 6px}' +
            '.doc-head .meta{display:flex;flex-wrap:wrap;gap:4px 18px;color:#444;font-size:10.5px}' +
            '.doc-head .meta b{color:#111}' +
            'table{width:100%;border-collapse:collapse}' +
            'th,td{border:1px solid #d0d0d0;padding:4px 6px;text-align:left;vertical-align:top}' +
            'thead th{background:#faf3e0;font-size:10px;text-transform:uppercase;letter-spacing:.03em}' +
            'td.num,th.num{width:26px;text-align:center;color:#666}' +
            'tbody tr:nth-child(even){background:#fafafa}' +
            'tr.neg td:nth-last-child(3){color:#c0392b;font-weight:600}' +
            'tr.pos td:nth-last-child(3){color:#1e8449;font-weight:600}' +
            '.doc-foot{margin-top:12px;color:#777;font-size:9.5px;border-top:1px solid #e0e0e0;padding-top:6px}' +
            '@media print{thead{display:table-header-group}}';
    }

    function doPrint() {
        var m = scopeModel();
        if (m.shown.length === 0) {
            window.sfToast && window.sfToast('Tidak ada item yang tampil untuk dicetak.', 'warning');
            return;
        }
        var html = buildPrintable();
        var w = window.open('', '_blank', 'width=1100,height=780');
        if (!w) {
            window.sfToast && window.sfToast('Popup diblokir. Izinkan popup untuk mencetak ke PDF.', 'error');
            return;
        }
        w.document.open();
        w.document.write(html);
        w.document.close();
        var go = function () { try { w.focus(); w.print(); } catch (e) {} };
        if (w.document.readyState === 'complete') setTimeout(go, 250);
        else w.onload = function () { setTimeout(go, 250); };
    }

    if (printBtn) printBtn.addEventListener('click', function (ev) { ev.preventDefault(); doPrint(); });

    /* ---------------- Recompute note on every visibility/filter change ---- */
    doc.addEventListener('sf:rowvis', updateNote);
    if (groupSel) groupSel.addEventListener('change', updateNote);
    var slider = doc.getElementById('sf-mat-slider');
    if (slider) slider.addEventListener('input', updateNote);
    if (readoutEl) {
        // readout is rewritten by sf-ui's recompute; watch it for threshold moves.
        try {
            var obs = new MutationObserver(function () { updateNote(); });
            obs.observe(readoutEl, { childList: true, characterData: true, subtree: true });
        } catch (e) {}
    }
    $$('[data-mat-preset]').forEach(function (b) { b.addEventListener('click', updateNote); });
    var ls = doc.getElementById('live-search-input');
    if (ls) ls.addEventListener('input', updateNote);

    if (doc.readyState === 'loading') doc.addEventListener('DOMContentLoaded', updateNote);
    else updateNote();
    // after sf-ui boot finishes composing filters, refresh once more.
    setTimeout(updateNote, 400);
})();