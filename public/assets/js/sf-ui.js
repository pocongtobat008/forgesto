/* =====================================================================
   StockForge · sf-ui.js — progressive UI enhancement layer
   ---------------------------------------------------------------------
   Adds, without touching existing behaviour:
     1.  Instant (client-side) filter for every .filters form — no Enter,
         no "Filter" click. Selects filter the table the moment they change.
     2.  Smooth custom dropdown for native <select> (accessible, form-safe).
     3.  Motion: page enter, staggered reveals, button ripple, top progress
         bar on navigation + submit, table skeleton on load.
     4.  Inline modals: Audit check, Discrepancy search/resolve.
     5.  Barcode engine: fast capture, quick actions, rule-based routing.
     6.  Toast notifications (used by AJAX + flashes).
     7.  Materiality analyser (reconciliation): live threshold + Pareto.
   All features are guarded — a missing element is a silent no-op.
   ===================================================================== */
(function () {
    'use strict';

    var doc = document;
    var root = doc.documentElement;
    var $  = function (s, c) { return (c || doc).querySelector(s); };
    var $$ = function (s, c) { return Array.prototype.slice.call((c || doc).querySelectorAll(s)); };
    var RM = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    function on(el, ev, fn, opt) { if (el) el.addEventListener(ev, fn, opt || false); }
    function fire(el, ev) { el.dispatchEvent(new Event(ev, { bubbles: true, cancelable: true })); }

    /* =================================================================
       ROW VISIBILITY ARBITER
       Beberapa filter (materialitas, instant-filter, tahapan) bisa
       menyembunyikan baris yang sama. Menulis langsung ke style.display
       membuat filter yang jalan belakangan "menang" dan memunculkan
       kembali baris yang sudah disembunyikan filter lain. Arbiter ini
       menyimpan alasan-per-alasan di row._sfVis; baris tampil hanya bila
       SEMUA alasan memberi izin.
       ================================================================= */
    window.sfRowVisible = function (row, reason, visible) {
        if (!row) return;
        if (!row._sfVis) { try { Object.defineProperty(row, '_sfVis', { value: Object.create(null), enumerable: false }); } catch (e) { row._sfVis = Object.create(null); } }
        if (visible) { delete row._sfVis[reason]; } else { row._sfVis[reason] = 1; }
        var hide = false;
        for (var k in row._sfVis) { if (row._sfVis[k]) { hide = true; break; } }
        row.style.display = hide ? 'none' : '';
        sfVisNotify();
    };
    function rowShow(row, reason, visible) { window.sfRowVisible(row, reason, visible); }

    /* Cek apakah baris benar-benar tampil (lolos SEMUA filter client-side). */
    function sfRowIsVisible(row) { return !!row && row.style.display !== 'none'; }
    window.sfRowIsVisible = sfRowIsVisible;

    /* Beberapa pemanggil menulis display langsung (bukan lewat arbiter).
       Kirim sinyal ter-throttle supaya konsumen (mis. pilih-semua bulk)
       bisa menyelaraskan state setelah visibilitas baris berubah. */
    var sfVisTimer = null;
    function sfVisNotify() {
        if (sfVisTimer) return;
        sfVisTimer = setTimeout(function () {
            sfVisTimer = null;
            try { doc.dispatchEvent(new CustomEvent('sf:rowvis')); } catch (e) {}
        }, 0);
    }
    window.sfVisNotify = sfVisNotify;

    /* =================================================================
       0.  TOASTS
       ================================================================= */
    var toastWrap = null;

    /* Live region persisten: toast visual tidak terbaca pembaca layar bila
       elemennya baru disisipkan bersama isinya, jadi pengumuman lewat sini. */
    function liveRegion(assertive) {
        var id = assertive ? 'sf-live-assertive' : 'sf-live-polite';
        var el = doc.getElementById(id);
        if (!el) {
            el = doc.createElement('div');
            el.id = id;
            el.className = 'sr-only';
            el.setAttribute('role', assertive ? 'alert' : 'status');
            el.setAttribute('aria-live', assertive ? 'assertive' : 'polite');
            el.setAttribute('aria-atomic', 'true');
            doc.body.appendChild(el);
        }
        return el;
    }
    window.sfAnnounce = function (msg, type) {
        if (!msg) return;
        var assertive = (type === 'error' || type === 'danger' || type === 'warning');
        var el = liveRegion(assertive);
        el.textContent = '';
        setTimeout(function () { el.textContent = String(msg); }, 60);
    };

    function toastWrapEl() {
        if (toastWrap) return toastWrap;
        toastWrap = doc.createElement('div');
        toastWrap.className = 'sf-toasts';
        doc.body.appendChild(toastWrap);
        return toastWrap;
    }
    window.sfToast = function (msg, type, ms) {
        if (!msg) return;
        var wrap = toastWrapEl();
        var el = doc.createElement('div');
        el.className = 'sf-toast is-' + (type || 'info');
        el.setAttribute('aria-hidden', 'true');
        el.innerHTML = '<span class="sf-toast-dot"></span><span class="sf-toast-msg"></span>';
        el.querySelector('.sf-toast-msg').textContent = msg;
        window.sfAnnounce(msg, type);
        wrap.appendChild(el);
        requestAnimationFrame(function () { el.classList.add('is-in'); });
        setTimeout(function () {
            el.classList.remove('is-in');
            setTimeout(function () { el.remove(); }, 320);
        }, ms || 3200);
    };

    /* =================================================================
       1.  TOP PROGRESS BAR (navigation + submit feedback)
       ================================================================= */
    var bar = null;
    function barEl() {
        if (bar) return bar;
        bar = doc.createElement('div');
        bar.className = 'sf-progress';
        bar.innerHTML = '<span></span>';
        doc.body.appendChild(bar);
        return bar;
    }
    var barTimer = null, barRunning = false;
    function barStart() {
        var b = barEl();
        if (barRunning) return;
        barRunning = true;
        b.classList.add('is-on');
        b.firstChild.style.width = '8%';
        var p = 8;
        clearInterval(barTimer);
        barTimer = setInterval(function () {
            p = Math.min(p + (90 - p) * 0.12, 90);
            b.firstChild.style.width = p + '%';
        }, 220);
    }
    function barDone() {
        var b = barEl();
        if (!barRunning) return;
        clearInterval(barTimer);
        barRunning = false;
        b.firstChild.style.width = '100%';
        setTimeout(function () {
            b.classList.remove('is-on');
            setTimeout(function () { b.firstChild.style.width = '0%'; }, 220);
        }, 200);
    }
    window.addEventListener('pageshow', barDone);
    window.addEventListener('load', barDone);

    /* =================================================================
       2.  PAGE-ENTER MOTION + STAGGER REVEALS
       ================================================================= */
    function animateIn() {
        var content = $('.content');
        if (!content) return;
        if (!RM) {
            content.classList.remove('sf-enter');
            void content.offsetWidth;
            content.classList.add('sf-enter');
        }
        // stagger candidates
        var cands = $$('.kpi-grid, .grid, .group-grid, .card, .data tbody');
        var items = [];
        cands.forEach(function (grid) {
            var kids = grid.children;
            if (grid.classList.contains('data') || grid.tagName === 'TBODY') kids = grid.children;
            var n = 0;
            for (var i = 0; i < kids.length && i < 40; i++) {
                var k = kids[i];
                if (!k.classList || k.tagName === 'TR') continue;
                k.classList.add('sf-reveal');
                k.style.setProperty('--i', n++);
                items.push(k);
            }
        });
        if (RM || !('IntersectionObserver' in window)) {
            items.forEach(function (k) { k.classList.add('is-shown'); });
            return;
        }
        var io = new IntersectionObserver(function (entries) {
            entries.forEach(function (e) {
                if (e.isIntersecting) { e.target.classList.add('is-shown'); io.unobserve(e.target); }
            });
        }, { rootMargin: '0px 0px -6% 0px', threshold: 0.05 });
        items.forEach(function (k) { io.observe(k); });
    }

    /* =================================================================
       3.  BUTTON RIPPLE (pressed feedback)
       ================================================================= */
    function initRipple() {
        if (RM) return;
        doc.addEventListener('pointerdown', function (e) {
            var btn = e.target.closest && e.target.closest('.btn, .btn-icon, .recon-chip, .sf-select-trigger, .tab-pill');
            if (!btn) return;
            var r = btn.getBoundingClientRect();
            var d = Math.max(r.width, r.height);
            var s = doc.createElement('span');
            s.className = 'sf-ripple';
            s.style.width = s.style.height = d + 'px';
            s.style.left = (e.clientX - r.left - d / 2) + 'px';
            s.style.top = (e.clientY - r.top - d / 2) + 'px';
            btn.appendChild(s);
            setTimeout(function () { s.remove(); }, 560);
        });
    }

    /* =================================================================
       4.  NAVIGATION + SUBMIT PROGRESS/LOCK
       ================================================================= */
    function isModified(e) { return e.metaKey || e.ctrlKey || e.shiftKey || e.altKey || (e.button && e.button !== 0); }
    function samePage(href) {
        try { var u = new URL(href, location.href); return u.pathname === location.pathname && u.search === location.search; }
        catch (x) { return false; }
    }
    function initProgress() {
        doc.addEventListener('click', function (e) {
            var a = e.target.closest && e.target.closest('a[href]');
            if (!a || isModified(e) || a.target === '_blank' || a.hasAttribute('download')) return;
            var href = a.getAttribute('href');
            if (!href || href.charAt(0) === '#' || href.indexOf('mailto:') === 0 || href.indexOf('javascript:') === 0) return;
            if (a.getAttribute('data-no-progress') !== null) return;
            if (samePage(a.href)) return;
            barStart();
        }, true);
        // any GET form submit (filters) also shows the bar
        doc.addEventListener('submit', function (e) {
            var f = e.target;
            if (!f || f.tagName !== 'FORM') return;
            if (f.getAttribute('data-no-progress') !== null) return;
            if ((f.method || 'get').toLowerCase() === 'get') barStart();
        }, true);
    }

    /* =================================================================
       5.  SMOOTH CUSTOM SELECT (progressive enhancement)
       ================================================================= */
    function buildSelects() {
        $$('select').forEach(function (sel) {
            if (sel.dataset.sfSelect || sel.multiple || sel.size > 1) return;
            sel.dataset.sfSelect = '1';
            sel.classList.add('sf-native');

            var wrap = doc.createElement('div');
            wrap.className = 'sf-select';
            var trigger = doc.createElement('button');
            trigger.type = 'button';
            trigger.className = 'sf-select-trigger';
            trigger.setAttribute('aria-haspopup', 'listbox');
            trigger.setAttribute('aria-expanded', 'false');
            var label = doc.createElement('span');
            label.className = 'sf-select-label';
            var chev = doc.createElement('span');
            chev.className = 'sf-select-chev';
            chev.innerHTML = '<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="6 9 12 15 18 9"/></svg>';
            trigger.appendChild(label); trigger.appendChild(chev);

            var panel = doc.createElement('div');
            panel.className = 'sf-select-panel';
            panel.setAttribute('role', 'listbox');
            // Panel bisa di-scroll: beri tab stop agar bisa dijangkau keyboard (WCAG 2.1.1)
            panel.setAttribute('tabindex', '0');
            // Nama aksesibel untuk listbox: aria-label select -> <label for> -> opsi pertama
            var __selName = (sel.getAttribute('aria-label') || sel.getAttribute('data-label') || '').trim();
            if (!__selName && sel.id) {
                var __lbl = doc.querySelector('label[for="' + sel.id + '"]');
                if (__lbl) __selName = (__lbl.textContent || '').trim();
            }
            if (!__selName && sel.options && sel.options.length) {
                __selName = (sel.options[0].textContent || '').trim();
            }
            if (!__selName) __selName = 'Pilihan';
            panel.setAttribute('aria-label', __selName);
            trigger.setAttribute('aria-label', __selName);

            sel.parentNode.insertBefore(wrap, sel);
            wrap.appendChild(sel);
            wrap.appendChild(trigger);
            wrap.appendChild(panel);

            var parentCard = wrap.closest('.card');
            if (parentCard) parentCard.classList.add('has-sf-select');
            var parentBody = wrap.closest('.card-body');
            if (parentBody) parentBody.classList.add('has-sf-select');

            var opts = [];
            function syncOptions() {
                opts = $$('option', sel);
                panel.innerHTML = '';
                opts.forEach(function (o, i) {
                    var oi = doc.createElement('div');
                    oi.className = 'sf-option' + (o.selected ? ' is-selected' : '');
                    oi.setAttribute('role', 'option');
                    oi.setAttribute('data-value', o.value);
                    oi.setAttribute('data-index', i);
                    oi.textContent = o.textContent;
                    if (o.disabled) { oi.classList.add('is-disabled'); }
                    oi.setAttribute('aria-selected', o.selected ? 'true' : 'false');
                    panel.appendChild(oi);
                });
                var cur = sel.options[sel.selectedIndex];
                label.textContent = cur ? cur.textContent : '';
                trigger.classList.toggle('is-placeholder', !sel.value);
            }
            function open() {
                closeAllSelects(wrap);
                wrap.classList.add('is-open');
                trigger.setAttribute('aria-expanded', 'true');
                if (parentCard) parentCard.classList.add('has-sf-select-open');
                if (parentBody) parentBody.classList.add('has-sf-select-open');

                // Auto dropup detection
                var rect = trigger.getBoundingClientRect();
                var spaceBelow = window.innerHeight - rect.bottom;
                var panelHeight = Math.min(panel.scrollHeight || 260, 280);
                if (spaceBelow < panelHeight + 15 && rect.top > spaceBelow) {
                    wrap.classList.add('is-dropup');
                } else {
                    wrap.classList.remove('is-dropup');
                }
            }
            function close() {
                wrap.classList.remove('is-open');
                wrap.classList.remove('is-dropup');
                trigger.setAttribute('aria-expanded', 'false');
                if (parentCard) parentCard.classList.remove('has-sf-select-open');
                if (parentBody) parentBody.classList.remove('has-sf-select-open');
            }
            function choose(val) {
                if (sel.value === val) { close(); return; }
                sel.value = val;
                syncOptions();
                fire(sel, 'change');   // triggers onchange / data-auto-submit / instant filter
                if (sel.hasAttribute('data-auto-submit')) {
                    var f = sel.closest('form');
                    if (f && !f._isSubmitting) {
                        f._isSubmitting = true;
                        f.submit();
                    }
                }
                close();
                trigger.focus();
            }

            syncOptions();
            on(trigger, 'click', function (e) { e.preventDefault(); wrap.classList.contains('is-open') ? close() : open(); });
            on(panel, 'click', function (e) {
                var o = e.target.closest('.sf-option');
                if (!o || o.classList.contains('is-disabled')) return;
                choose(o.getAttribute('data-value'));
            });
            on(trigger, 'keydown', function (e) {
                var open2 = wrap.classList.contains('is-open');
                if (e.key === 'ArrowDown' || e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    if (!open2) open();
                    else {
                        var cur = panel.querySelector('.sf-option.is-selected') || panel.querySelector('.sf-option');
                        if (cur) choose(cur.getAttribute('data-value'));
                    }
                } else if (e.key === 'Escape' && open2) { e.preventDefault(); close(); }
                else if (e.key === 'ArrowUp' && open2) { e.preventDefault(); }
            });
            on(panel, 'keydown', function (e) { if (e.key === 'Escape') { close(); trigger.focus(); } });
        });
    }
    function closeAllSelects(except) {
        $$('.sf-select.is-open').forEach(function (w) {
            if (w !== except) {
                w.classList.remove('is-open');
                w.classList.remove('is-dropup');
                var t = $('.sf-select-trigger', w);
                if (t) t.setAttribute('aria-expanded', 'false');
                var c = w.closest('.card');
                if (c) c.classList.remove('has-sf-select-open');
                var b = w.closest('.card-body');
                if (b) b.classList.remove('has-sf-select-open');
            }
        });
    }
    on(doc, 'click', function (e) { if (!(e.target.closest && e.target.closest('.sf-select'))) closeAllSelects(null); });

    /* =================================================================
       6.  INSTANT FILTER (no Enter / no click on Filter)
       ================================================================= */
    function norm(s) { return (s || '').toString().toLowerCase().replace(/\s+/g, ' ').trim(); }

    function wireFilterForm(form) {
        if (form.dataset.sfInstant) return;
        form.dataset.sfInstant = '1';

        var card = form.closest('.card') || form.parentElement;
        var table = card ? $('table.data', card) : null;
        // recon page keeps its own dedicated table id
        if (!table) table = $('#recon-table');
        if (!table) return;
        var tbody = $('tbody', table);
        if (!tbody) return;

        var rows = function () { return $$('tr', tbody).filter(function (r) { return !$('td[colspan]', r); }); };
        var counter = form.querySelector('.sf-filter-count') || doc.getElementById('live-search-count') || null;
        var searchInput = form.querySelector('input[type="search"], input[name="search"]');
        // Reconciliation keeps its search box OUTSIDE the toolbar form; adopt it.
        if (!searchInput) {
            var ls = doc.getElementById('live-search-input');
            if (ls && !ls.closest('form')) searchInput = ls;
        }

        // give every row a normalised search blob (once)
        rows().forEach(function (r) {
            if (r.dataset.sfBlob) return;
            r.dataset.sfBlob = norm(r.textContent);
        });

        function controls() {
            // Hanya kontrol yang MEMANG filter client-side. Kontrol dengan
            // onchange inline (mis. sort "Selisih nilai terbesar", atau select
            // group/direction/status yang submit ke server) DIKECUALIKAN —
            // kalau tidak, label sort seperti "Selisih nilai terbesar" tidak
            // ada di blob baris → semua baris tersembunyi saat load.
            return $$('input:not([type="hidden"]), select', form).filter(function (el) {
                if (!el.name) return false;
                if (el.hasAttribute('onchange')) return false;
                return true;
            });
        }

        function apply() {
            var q = searchInput ? norm(searchInput.value) : '';
            // Selects are matched by their visible LABEL only (a numeric value
            // like a group id would false-match quantities/prices in the blob).
            var selFilters = [];
            controls().forEach(function (el) {
                if (el === searchInput || el.tagName !== 'SELECT') return;
                if (el.value === '' || el.value == null) return;
                var lab = norm(selectedLabel(el));
                if (lab) selFilters.push(lab);
            });

            var vis = 0, all = rows();
            all.forEach(function (r) {
                var blob = r.dataset.sfBlob || '';
                var ok = !q || blob.indexOf(q) !== -1;
                if (ok) {
                    for (var i = 0; i < selFilters.length; i++) {
                        if (blob.indexOf(selFilters[i]) === -1) { ok = false; break; }
                    }
                }
                rowShow(r, 'instant', ok);
                if (ok) vis++;
            });

            toggleEmpty(tbody, vis, table);
            if (counter) counter.textContent = (q || selFilters.length) ? (vis + '/' + all.length + ' cocok') : '';
        }

        function selectedLabel(el) { var o = el.options ? el.options[el.selectedIndex] : null; return o ? o.textContent : ''; }

        function toggleEmpty(tbody, vis, table) {
            /* A server-rendered empty state may already exist (items/index.php
               renders one when the dataset is empty). Reuse it — appending a
               second row produced two stacked "no data" messages and inherited
               the 44px first-column width floor (40px cell => 1-char lines). */
            var ph = tbody.querySelector('tr.sf-filter-empty');
            var seeded = tbody.querySelector('tr:not(.sf-filter-empty) > td > .empty-state');
            var host = ph || (seeded ? seeded.closest('tr') : null);
            if (vis === 0) {
                if (!host) {
                    host = doc.createElement('tr');
                    host.className = 'sf-filter-empty';
                    var cols = table.querySelectorAll('thead th').length || 6;
                    host.innerHTML = '<td colspan="' + cols + '"><div class="empty-state"><h3>Tidak ada data cocok</h3><p>Ubah kata kunci atau filter yang dipakai.</p></div></td>';
                    tbody.appendChild(host);
                } else if (seeded && !ph) {
                    /* re-target the seeded row's copy for the filter case */
                    var h3 = host.querySelector('.empty-state h3');
                    var p  = host.querySelector('.empty-state p');
                    if (h3) h3.textContent = 'Tidak ada data cocok';
                    if (p)  p.textContent  = 'Ubah kata kunci atau filter yang dipakai.';
                }
            } else if (ph) {
                ph.remove();
            } else if (seeded) {
                /* data rows are present again but a seeded empty row lingers */
                var sr = seeded.closest('tr');
                if (sr && tbody.querySelectorAll('tr').length > 1) sr.remove();
            }
        }

        var t;
        controls().forEach(function (el) {
            var ev = el.tagName === 'SELECT' ? 'change' : 'input';
            on(el, ev, function () {
                clearTimeout(t);
                if (ev === 'change') { apply(); }
                else { t = setTimeout(apply, RM ? 120 : 220); }
            });
        });
        // the adopted (out-of-form) search input still drives instant filtering;
        // Enter keeps the classic server-side search across all pages.
        if (searchInput) {
            on(searchInput, 'input', function () { clearTimeout(t); t = setTimeout(apply, RM ? 120 : 200); });
            on(searchInput, 'keydown', function (e) {
                if (e.key === 'Enter') { clearTimeout(t); barStart(); var f = searchInput.closest('form'); if (f) { if (f.requestSubmit) f.requestSubmit(); else f.submit(); } }
            });
        }
        apply();
    }
    function initInstantFilters() {
        $$('form.filters, form[data-filter-form]').forEach(function (f) { wireFilterForm(f); });
        // reconciliation toolbar + items filter forms have no .filters class
        var rf = $('.card form[method="get"][action*="reconciliation"]');
        if (rf) wireFilterForm(rf);
        var itf = $('form[method="get"][action*="/items"]');
        if (itf) wireFilterForm(itf);
        // pages with a standalone live-search input: adopt them into the
        // nearest GET form (or run the client filter standalone).
        $$('#live-search-input, [data-filter-live]').forEach(function (inp) {
            if (inp.dataset.sfWired) return;
            inp.dataset.sfWired = '1';
            // Kalau sebuah filter-form yang SUDAH di-wire mengadopsi input ini
            // (wireFilterForm menyerap #live-search-input global), maka input
            // sudah tertangani — JANGAN pasang handler standalone juga, karena
            // dia menulis style.display langsung dan menabrak arbiter.
            var f = inp.closest('form');
            var adopt = (f && f.dataset.sfInstant) ? f : $('.card form[method="get"][action*="reconciliation"]');
            if (adopt && adopt.dataset.sfInstant) return;
            if (adopt) { wireFilterForm(adopt); if (adopt.dataset.sfInstant) return; }
            // benar-benar standalone: debounce-search tabel langsung (pakai arbiter)
            (function () {
                var card = inp.closest('.card'), table = card ? $('table.data', card) : null, tb = table ? $('tbody', table) : null;
                if (!tb) return;
                var run = function () {
                    var q = norm(inp.value);
                    $$('tr', tb).filter(function (r) { return !$('td[colspan]', r); }).forEach(function (r) {
                        if (!r.dataset.sfBlob) r.dataset.sfBlob = norm(r.textContent);
                        rowShow(r, 'search', !q || r.dataset.sfBlob.indexOf(q) !== -1);
                    });
                };
                var dt; on(inp, 'input', function () { clearTimeout(dt); dt = setTimeout(run, RM ? 120 : 200); });
            })();
        });
    }

    /* =================================================================
       7.  INLINE MODALS (Audit + Discrepancy)
       ================================================================= */
    function openModal(id) {
        var m = doc.getElementById(id);
        if (!m) return;
        m.classList.add('is-open');
        doc.body.classList.add('no-scroll');
        var f = m.querySelector('input:not([type="hidden"]), textarea');
        setTimeout(function () { if (f) f.focus(); }, 60);
    }
    function closeModal(m) {
        if (!m) return;
        m.classList.remove('is-open');
        doc.body.classList.remove('no-scroll');
    }
    window.sfOpenModal = openModal;
    window.sfCloseModal = closeModal;

    function initModals() {
        // generic closers
        doc.addEventListener('click', function (e) {
            if (e.target.closest && e.target.closest('[data-sf-close]')) {
                closeModal(e.target.closest('.sf-modal-backdrop'));
                return;
            }
            if (e.target.classList && e.target.classList.contains('sf-modal-backdrop')) closeModal(e.target);
        });
        doc.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                var open = $('.sf-modal-backdrop.is-open');
                if (open) closeModal(open);
            }
        });

        /* ---- Audit queue: open modal instead of a full page ---- */
        $$('.js-audit-open').forEach(function (btn) {
            on(btn, 'click', function (e) {
                e.preventDefault();
                var m = doc.getElementById('audit-modal');
                if (!m) { location.href = btn.getAttribute('href') || '#'; return; }
                var form = m.querySelector('form');
                form.setAttribute('action', btn.getAttribute('data-action'));
                var set = function (sel, val, isText) {
                    var el = m.querySelector(sel); if (!el) return;
                    if (isText) el.textContent = val; else el.value = val;
                };
                set('#audit-modal-code', btn.getAttribute('data-code'), true);
                set('#audit-modal-name', btn.getAttribute('data-name'), true);
                set('#audit-modal-group', btn.getAttribute('data-group'), true);
                set('#audit-modal-expected', btn.getAttribute('data-expected'), true);
                set('#audit-modal-counted', btn.getAttribute('data-counted'), true);
                var ai = m.querySelector('#audit-modal-qty');
                if (ai) ai.value = btn.getAttribute('data-counted') === '—' ? '' : (btn.getAttribute('data-counted') || '');
                var note = m.querySelector('#audit-modal-note'); if (note) note.value = '';
                // live delta chip
                updateAuditDelta(m);
                openModal('audit-modal');
            });
        });
        var aq = $('.sf-modal-backdrop #audit-modal-qty');
        on(aq, 'input', function () { updateAuditDelta(aq.closest('.sf-modal-backdrop')); });
        function updateAuditDelta(m) {
            if (!m) return;
            var exp = parseInt((m.querySelector('#audit-modal-expected') || {}).textContent, 10);
            var q = parseInt((m.querySelector('#audit-modal-qty') || {}).value, 10);
            var out = m.querySelector('#audit-modal-delta');
            if (!out) return;
            if (isNaN(exp) || isNaN(q)) { out.textContent = '—'; out.className = 'sf-delta-chip'; return; }
            var d = q - exp;
            out.textContent = (d > 0 ? '+' : '') + d + ' unit (' + q + ' vs sistem ' + exp + ')';
            out.className = 'sf-delta-chip ' + (d === 0 ? 'is-zero' : (d > 0 ? 'is-pos' : 'is-neg'));
        }

        /* ---- Discrepancy: inline search / resolve ---- */
        $$('.js-disc-action').forEach(function (btn) {
            on(btn, 'click', function (e) {
                e.preventDefault();
                var m = doc.getElementById('disc-modal');
                if (!m) return;
                var kind = btn.getAttribute('data-kind');   // search | resolve
                var form = m.querySelector('form');
                form.setAttribute('action', btn.getAttribute('data-action'));
                m.querySelector('#disc-modal-title').textContent = (kind === 'resolve' ? 'Selesaikan Selisih · ' : 'Cari Selisih · ') + btn.getAttribute('data-code');
                var itemEl = m.querySelector('#disc-modal-item');
                if (itemEl) itemEl.textContent = btn.getAttribute('data-code') + ' · ' + btn.getAttribute('data-name');
                m.querySelector('#disc-modal-ctx').textContent = btn.getAttribute('data-group');
                m.querySelector('#disc-modal-kind').value = kind;
                // context: system / counted / audit / delta (blind-safe)
                var CAN_QTY = (document.body.getAttribute('data-can-see-qty') || '1') !== '0';
                var exp = btn.getAttribute('data-expected'), cnt = btn.getAttribute('data-counted'), aud = btn.getAttribute('data-audited');
                var cmp = m.querySelector('#disc-modal-compare');
                if (cmp) cmp.hidden = exp === null || !CAN_QTY;
                var setv = function (sel, val) { var e = m.querySelector(sel); if (e) e.textContent = (val === null || val === '' || val === '—') ? '—' : val; };
                setv('#disc-modal-expected', exp); setv('#disc-modal-counted', cnt);
                var dEl = m.querySelector('#disc-modal-delta');
                if (dEl) {
                    var dv = parseInt(btn.getAttribute('data-diff'), 10);
                    if (isNaN(dv) || !CAN_QTY) { dEl.hidden = true; } else {
                        dEl.hidden = false;
                        dEl.textContent = 'Selisih: ' + (dv > 0 ? '+' : '') + dv + ' unit' + (aud ? ' · hasil audit: ' + aud : '');
                        dEl.className = 'sf-delta-chip ' + (dv === 0 ? 'is-zero' : (dv > 0 ? 'is-pos' : 'is-neg'));
                    }
                }
                var fSearch = m.querySelector('.disc-field-search');
                var fResolve = m.querySelector('.disc-field-resolve');
                if (fSearch) fSearch.hidden = kind !== 'search';
                if (fResolve) fResolve.hidden = kind !== 'resolve';
                var q = m.querySelector('#disc-modal-qty');
                if (q) q.value = btn.getAttribute('data-counted') || '';
                var qRes = m.querySelector('#disc-modal-resolve-qty');
                if (qRes) qRes.value = btn.getAttribute('data-counted') || '';
                var n = m.querySelector('#disc-modal-note'); if (n) n.value = '';
                var rNote = m.querySelector('#disc-modal-resolution'); if (rNote) rNote.value = '';
                openModal('disc-modal');
            });
        });
    }

    /* =================================================================
       8.  BARCODE ENGINE  (fast capture + quick actions + rule routing)
       ================================================================= */
    function initBarcode() {
        var last = '', lastAt = 0, buf = '', at = 0;

        function searchForm() {
            var si = doc.getElementById('search');
            return si ? si.closest('form') : null;
        }
        function doSearch(code) {
            if (window.stoHandleBarcodeScan) {
                window.stoHandleBarcodeScan(code);
                return;
            }
            var si = doc.getElementById('search');
            if (!si) { location.href = sfUrl('/items?search=' + encodeURIComponent(code)); return; }
            si.value = code;
            var card = si.closest('.card');
            var tbl = card ? $('table.data', card) : null;
            var tb = tbl ? $('tbody', tbl) : null;
            if (!tbl) { var f = si.closest('form'); if (f) { barStart(); f.submit(); } return; }
            // count matches directly (do not race the debounced visible-filter)
            var rows = $$('tr', tb).filter(function (r) { return !$('td[colspan]', r); });
            var qc = (code || '').toString().toLowerCase().replace(/\s+/g, ' ').trim();
            var hits = rows.filter(function (r) {
                if (!r.dataset.sfBlob) r.dataset.sfBlob = (r.textContent || '').toLowerCase().replace(/\s+/g, ' ').trim();
                return qc && r.dataset.sfBlob.indexOf(qc) !== -1;
            });
            fire(si, 'input');            // keep the on-screen filter in sync
            openItemIfSingle(code, hits, si);
        }
        /* Rule: a barcode scan routes by what the code matches —
           · exact single item  -> open its detail page (fast path)
           · several matches    -> keep the filtered list
           · no match           -> toast + keep the search text           */
        function openItemIfSingle(code, hits, si) {
            var n = hits.length;
            if (n === 1) {
                var link = hits[0].querySelector('a[href*="/items/"]');
                if (link) { sfToast('Item ' + code + ' ditemukan, membuka detail', 'success', 1400); setTimeout(function () { location.href = link.getAttribute('href'); }, 420); return; }
            }
            if (n === 0) { sfToast('Kode “' + code + '” tidak ditemukan pada filter saat ini', 'warning'); return; }
            sfToast(n + ' item cocok dengan “' + code + '”', 'info');
        }
        window.sfHandleCode = doSearch;

        function sfUrl(p) { return (window.SF_BASE || '') + p; }

        /* ---- keyboard wedge: USB/HID scanner types fast + Enter ---- */
        on(doc, 'keydown', function (e) {
            if (sfAnyOverlayOpen()) return;
            var ae = doc.activeElement || {};
            var tag = ae.tagName || '';
            if (/^(INPUT|TEXTAREA|SELECT)$/.test(tag) || ae.isContentEditable) return;

            var now = Date.now();
            if (now - at > 90) buf = '';
            at = now;
            if (e.key === 'Enter') {
                if (buf.length >= 4) {   // a scanner burst, not a stray Enter
                    if (Date.now() - lastAt < 900 && buf === last) { buf = ''; return; }
                    last = buf; lastAt = Date.now();
                    doSearch(buf);
                }
                buf = '';
                return;
            }
            if (e.key.length === 1) buf += e.key;
        });

        /* ---- global quick-action shortcuts ---- */
        on(doc, 'keydown', function (e) {
            var ae = doc.activeElement || {};
            var typing = /^(INPUT|TEXTAREA|SELECT)$/.test(ae.tagName || '') || ae.isContentEditable;
            // Ctrl/⌘ + B  → open scanner (barcode)
            if ((e.key === 'b' || e.key === 'B') && (e.ctrlKey || e.metaKey)) {
                e.preventDefault();
                var modal = $('[data-scan-modal]');
                if (modal) {
                    var sb = $$('[data-scan-open]').find(function(b) { return b.id !== 'global-scan-trigger' && b.offsetParent !== null; });
                    if (sb) sb.click();
                    else if (typeof window.startScanner === 'function') window.startScanner();
                } else {
                    window.location.href = '/items?scan=1';
                }
                return;
            }
            // Alt + S → focus the quick search on this page
            if (e.key === 's' && e.altKey && !typing) {
                var si = doc.getElementById('search') || doc.querySelector('.topbar-search, [data-palette-open]');
                if (si) { e.preventDefault(); si.focus(); }
            }
        });

        // topbar scan button
        $$('[data-scan-open]').forEach(function (b) {
            if (b.dataset.sfWired) return; b.dataset.sfWired = '1';
        });
    }
    function sfAnyOverlayOpen() {
        return !!$('.sf-modal-backdrop.is-open, .modal-backdrop.is-open, .palette-backdrop.is-open');
    }

    /* =================================================================
       9.  MATERIALITY ANALYSER (reconciliation surprise feature)
       ================================================================= */
    function initMateriality() {
        var panel = doc.getElementById('sf-materiality');
        var table = doc.getElementById('recon-table');
        if (!panel || !table) return;
        var tbody = $('tbody', table);
        var allRows = $$('tr.recon-row', tbody);
        if (!allRows.length) return;

        var data = allRows.map(function (r) {
            return { el: r, diff: Math.abs(parseFloat(r.getAttribute('data-diff')) || 0), signed: parseFloat(r.getAttribute('data-diff')) || 0, gid: r.getAttribute('data-gid') || '' };
        });

        var totalAbs = data.reduce(function (a, d) { return a + d.diff; }, 0);
        var netSigned = data.reduce(function (a, d) { return a + d.signed; }, 0);

        var slider = doc.getElementById('sf-mat-slider');
        var groupSel = doc.getElementById('sf-mat-group');
        var readout = doc.getElementById('sf-mat-readout');
        var countEl = doc.getElementById('sf-mat-count');
        var valueEl = doc.getElementById('sf-mat-value');
        var pareto = doc.getElementById('sf-mat-pareto');
        var bar = doc.getElementById('sf-mat-bar');

        // presets
        var presets = [0, 1e6, 5e6, 1e7, 5e7];
        function rp(n) { return 'Rp ' + Math.round(n).toLocaleString('id-ID'); }

        function ns(n) { return 'Rp ' + Math.round(n).toLocaleString('id-ID'); }
        function paintSlider(el) {
            if (!el || !el.max || el.max <= 0) return;
            var pct = (parseFloat(el.value) || 0) / parseFloat(el.max) * 100;
            el.style.background = 'linear-gradient(90deg, var(--brand-500) 0%, var(--brand-600) ' + pct + '%, var(--surface-3) ' + pct + '%, var(--surface-3) 100%)';
        }
        function syncPresets(threshold) {
            $$('[data-mat-preset]').forEach(function (b) {
                b.classList.toggle('is-active', parseFloat(b.getAttribute('data-mat-preset')) === threshold);
            });
        }
        $$('[data-mat-preset]').forEach(function (b) {
            on(b, 'click', function () {
                var v = parseFloat(b.getAttribute('data-mat-preset'));
                if (slider) { slider.value = Math.min(v, parseFloat(slider.max) || v); paintSlider(slider); }
                recompute(v);
            });
        });

        // Arbiter visibilitas bersama (didefinisikan di bagian atas sf-ui.js)
        // memastikan materialitas & instant-filter tidak saling menimpa.

        function recompute(threshold) {
            var gid = groupSel ? groupSel.value : '';
            var scope = gid ? data.filter(function (d) { return String(d.gid) === String(gid); }) : data;
            var scopeAbs = scope.reduce(function (a, d) { return a + d.diff; }, 0);
            var vis = [], vSum = 0, nShown = 0;
            data.forEach(function (d) {
                // Baris disembunyikan bila (a) di luar group terpilih, atau
                // (b) nilainya di bawah ambang materialitas. Dua 'reason'
                // terpisah supaya melepas filter group tidak ikut melepas
                // ambang, dan sebaliknya.
                var inGroup = !gid || String(d.gid) === String(gid);
                rowShow(d.el, 'group', inGroup);
                rowShow(d.el, 'materiality', d.diff >= threshold);
                if (inGroup && d.diff >= threshold) { nShown++; vis.push(d); vSum += d.diff; }
            });
            // empty-state materialitas: hanya bila tidak ada baris yang lolos ambang.
            var ph = tbody.querySelector('tr.sf-filter-empty');
            if (nShown === 0) {
                if (!ph) {
                    ph = doc.createElement('tr'); ph.className = 'sf-filter-empty';
                    var cols = table.querySelectorAll('thead th').length || 8;
                    ph.innerHTML = '<td colspan="' + cols + '"><div class="empty-state"><h3>Tidak ada item di atas ambang</h3><p>' +
                        (gid ? 'Coba ganti group atau turunkan ambang materialitas.' : 'Turunkan ambang materialitas untuk melihat lebih banyak item.') +
                        '</p></div></td>';
                    tbody.appendChild(ph);
                }
            } else if (ph) { ph.remove(); }

            if (readout) readout.textContent = rp(threshold);
            syncPresets(threshold);
            // chip filter cepat tetap global (bukan per-group) → ikut ambang.
            var nMinus = 0, nPlus = 0;
            data.forEach(function (d) { if (d.diff >= threshold) { if (d.signed < 0) nMinus++; else if (d.signed > 0) nPlus++; } });
            $$('.js-mat-chip').forEach(function (c) {
                var k = c.getAttribute('data-count-key');
                c.textContent = (k === 'minus' ? nMinus : nPlus);
            });
            if (countEl) {
                var pctItems = scope.length ? Math.round(vis.length / scope.length * 100) : 0;
                var pctVal = scopeAbs ? Math.round(vSum / scopeAbs * 100) : 0;
                countEl.innerHTML = '<strong>' + vis.length + '</strong> dari ' + scope.length + ' item (' + pctItems + '%) &middot; menanggung <strong>' + pctVal + '%</strong> dari total nilai selisih' + (gid ? ' grup ini' : '');
                if (bar) bar.style.width = pctVal + '%';
            }
            if (valueEl) valueEl.textContent = rp(vSum);
            if (pareto) {
                var top = vis.slice().sort(function (a, b) { return b.diff - a.diff; }).slice(0, 5);
                pareto.innerHTML = top.map(function (d, i) {
                    var row = d.el;
                    var code = row.getAttribute('data-code') || '';
                    var name = row.getAttribute('data-name') || '';
                    return '<li class="sf-pareto-item"><span class="sf-pareto-rank">' + (i + 1) + '</span>' +
                        '<span class="sf-pareto-body"><span class="sf-pareto-name">' + code.toUpperCase() + ' &middot; ' + name + '</span>' +
                        '<span class="sf-pareto-bar"><span style="width:' + (top[0] ? Math.max(6, d.diff / top[0].diff * 100) : 0) + '%"></span></span></span>' +
                        '<span class="sf-pareto-val">' + rp(d.diff) + '</span></li>';
                }).join('');
                if (!top.length) pareto.innerHTML = '<li class="sf-pareto-empty">Tidak ada item di atas ambang.</li>';
            }
        }
        // maxSlider untuk scope terpilih (group tertentu → 0 = semua)
        function scopeMax() {
            var gid = groupSel ? groupSel.value : '';
            var sum = 0;
            data.forEach(function (d) { if (!gid || String(d.gid) === String(gid)) sum += d.diff; });
            return sum;
        }
        function applyScope() {
            // Hitung max DULU dari nilai default/scope, lalu PASTIKAN slider
            // bisa mencapai ambang filter cepat (BIG_THRESHOLD / Rp10jt).
            // Tanpa ini, saat default 10jt di-set sementara max masih 100
            // bawaan browser, nilai ter-clamp → filter mentok & readout "Rp 0".
            var desired = parseFloat(slider && slider.dataset && slider.dataset.default) || 0;
            if (desired <= 0) desired = parseFloat(slider && slider.value) || 0;
            var need = Math.max(Math.ceil(scopeMax() / 1000) * 1000, desired, 1e6);
            var maxEl = doc.getElementById('sf-mat-max');
            var bigT = maxEl ? (parseFloat(maxEl.getAttribute('data-big-threshold')) || 0) : 0;
            if (bigT > 0) need = Math.max(need, bigT);
            if (slider) {
                slider.max = need;
                slider.step = Math.max(1000, Math.round(slider.max / 200));
                if (parseFloat(slider.value) > parseFloat(slider.max)) slider.value = slider.max;
                if (maxEl) maxEl.textContent = ns(slider.max);
                paintSlider(slider);
            }
            recompute(parseFloat(slider && slider.value) || 0);
        }
        if (groupSel) on(groupSel, 'change', applyScope);
        on(slider, 'input', function () { paintSlider(slider); recompute(parseFloat(slider.value) || 0); });
        // Init: hormati ambang bawaan dari server (mis. chip Rp10jt aktif),
        // baru sesuaikan rentang slider dengan scope (semua group).
        if (slider && slider.dataset && parseFloat(slider.dataset.default) > 0) {
            slider.value = parseFloat(slider.dataset.default);
        }
        applyScope();
    }

    /* =================================================================
       10b. NOTIFIKASI SETIAP PROSES (proses / berhasil / peringatan / gagal)
       ================================================================= */
    var procTimer = null;
    function procEl() {
        var el = doc.getElementById('sf-proc');
        if (!el) {
            el = doc.createElement('div');
            el.id = 'sf-proc';
            el.className = 'sf-proc is-info';
            el.setAttribute('role', 'status');
            el.setAttribute('aria-live', 'polite');
            el.hidden = true;
            el.innerHTML = '<span class="sf-proc-dot"></span><span class="sf-proc-msg"></span>';
            doc.body.appendChild(el);
        }
        return el;
    }
    // type: process | success | warning | error
    window.sfNotify = function (msg, type, ms) {
        if (!msg) return;
        var el = procEl();
        el.className = 'sf-proc is-' + (type || 'process') + ' is-on';
        el.querySelector('.sf-proc-msg').textContent = msg;
        el.hidden = false;
        clearTimeout(procTimer);
        var sticky = (type === 'process');
        if (!sticky) {
            procTimer = setTimeout(function () {
                el.classList.remove('is-on');
                setTimeout(function () { if (!el.classList.contains('is-on')) el.hidden = true; }, 320);
            }, ms || 3400);
        }
    };
    window.sfNotifyDone = function (msg, type) {
        window.sfNotify(msg || 'Selesai.', type || 'success');
    };
    function notifyFromForm(f, e) {
        // 1. Cegah submit ganda (kecuali form yang memang punya guard sendiri)
        if (!f.dataset.sfBusy) {
            f.dataset.sfBusy = '1';
            var btns = f.querySelectorAll('button[type="submit"], button:not([type])');
            btns.forEach(function (b) {
                b.dataset.sfWas = b.disabled ? '1' : '';
                b.disabled = true;
                b.classList.add('is-loading');
                b.setAttribute('aria-busy', 'true');
            });
            setTimeout(function () { f.dataset.sfBusy = ''; }, 6000);
        }
        // 2. Notifikasi proses untuk form yang berisi field data (bukan filter GET)
        var isData = (f.method || 'get').toLowerCase() !== 'get';
        if (isData && f.getAttribute('data-sf-silent') === null) {
            var lbl = f.getAttribute('data-sf-label') || 'Menyimpan data';
            window.sfNotify(lbl + '...', 'process');
        }
    }
    function initProcessNotify() {
        // Form POST: tampilkan "proses" + cegah ganda. Form GET: tampilkan bar saja.
        doc.addEventListener('submit', function (e) {
            var f = e.target;
            if (!f || f.tagName !== 'FORM' || e.defaultPrevented) return;
            if (f.getAttribute('data-sf-nonotify') !== null) return;
            if (f.hasAttribute('data-confirm') && e.defaultPrevented) return;
            notifyFromForm(f, e);
        }, false);
        // Flash server -> toast (berhasil / gagal / peringatan)
        var flash = doc.querySelectorAll('[data-sf-flash]');
        flash.forEach(function (el) {
            var t = el.getAttribute('data-sf-flash');
            var m = (el.getAttribute('data-sf-msg') || '').trim();
            if (m) window.sfNotify(m, t || 'info', 4200);
            el.style.display = 'none';   // toast menggantikan alert inline
        });
        // Peringatan sebelum meninggalkan halaman dengan perubahan belum tersimpan
        var guard = false;
        doc.addEventListener('change', function (e) {
            var t = e.target;
            if (t && t.closest && t.closest('form[data-sf-dirty-guard]')) guard = true;
        }, true);
        window.addEventListener('beforeunload', function (e) {
            if (!guard) return;
            e.preventDefault();
            e.returnValue = '';
        });
        doc.addEventListener('submit', function () { guard = false; }, true);
    }

    /* =================================================================
       10. DASHBOARD GROUP STATUS TABS (smooth)
       ================================================================= */
    function initStatusTabs() {
        $$('.sf-tabs').forEach(function (tabs) {
            var pills = $$('.tab-pill', tabs);
            var scope = tabs.closest('.card') || tabs.closest('[data-tab-scope]') || tabs.parentElement;
            on(tabs, 'click', function (e) {
                var pill = e.target.closest('.tab-pill');
                if (!pill) return;
                var target = pill.getAttribute('data-tab');
                pills.forEach(function (p) { p.classList.toggle('is-active', p === pill); p.setAttribute('aria-selected', p === pill ? 'true' : 'false'); });
                $$('.sf-tabpanel', scope).forEach(function (pn) {
                    var show = pn.getAttribute('data-panel') === target;
                    pn.hidden = !show;
                    if (show && !RM) { pn.classList.remove('sf-enter'); void pn.offsetWidth; pn.classList.add('sf-enter'); }
                });
            });
        });
    }

    /* =================================================================
       BOOT
       ================================================================= */
    function boot() {
        if (!window.SF_BASE) {
            var b = doc.querySelector('meta[name="sf-base"]');
            window.SF_BASE = b ? b.getAttribute('content') : '';
        }
        initProgress();
        initRipple();
        buildSelects();
        initInstantFilters();
        initModals();
        initBarcode();
        initMateriality();
        initStatusTabs();
        initProcessNotify();
        animateIn();
        // let page-level scripts re-bind to controls (e.g. after <select>s were
        // wrapped into custom dropdowns that would otherwise orphan listeners)
        try { doc.dispatchEvent(new CustomEvent('sf:ready')); } catch (e) { var ev = doc.createEvent('Event'); ev.initEvent('sf:ready', true, true); doc.dispatchEvent(ev); }
        // topbar Scan button: on pages without the camera modal, route to Data STO
        // (where the scanner lives) instead of silently doing nothing.
        var hasScanModal = !!$('[data-scan-modal]');
        if (!hasScanModal) {
            $$('.topbar-scan').forEach(function (b) {
                b.addEventListener('click', function (e) {
                    e.preventDefault();
                    e.stopImmediatePropagation();
                    location.href = (window.SF_BASE || '') + '/items';
                }, true);
            });
        }
    }
    if (doc.readyState === 'loading') doc.addEventListener('DOMContentLoaded', boot);
    else boot();
})();
/* SF-EMPTYMARK v1.8.1 */
(function () {
    function mark() {
        document.querySelectorAll('table.data, table[id$="-table"]').forEach(function (t) {
            var tb = t.tBodies[0];
            if (!tb) return;
            var hasEmpty = !!tb.querySelector('.empty-state');
            var rows = tb.querySelectorAll('tr');
            var onlyEmpty = hasEmpty;
            for (var i = 0; i < rows.length; i++) {
                if (!rows[i].querySelector('.empty-state')) { onlyEmpty = false; break; }
            }
            if (onlyEmpty) { t.setAttribute('data-empty', '1'); }
            else { t.removeAttribute('data-empty'); }
        });
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', mark);
    } else { mark(); }
    document.addEventListener('sf:table-updated', mark);
    /* observe tbody mutations (filters, ajax, materiality placeholders) */
    if (window.MutationObserver) {
        var mo = new MutationObserver(function (muts) {
            for (var i = 0; i < muts.length; i++) {
                var n = muts[i].target;
                if (n && n.nodeType === 1 && (n.matches && (n.matches('tbody') || n.closest && n.closest('table')))) {
                    mark(); return;
                }
            }
        });
        document.addEventListener('DOMContentLoaded', function () {
            document.querySelectorAll('table.data, table[id$="-table"]').forEach(function (t) {
                if (t.tBodies[0]) mo.observe(t.tBodies[0], { childList: true, subtree: true });
            });
            mark();
        });
    }
    window.sfMarkEmptyTables = mark;
})();
/* /SF-EMPTYMARK */
/* SF-EMPTYMARK v1.8.0 */
(function () {
    var timer = null;
    function mark() {
        document.querySelectorAll('table.data, table[id$="-table"]').forEach(function (t) {
            var tb = t.tBodies[0];
            if (!tb || !tb.rows.length) return;
            var anyEmpty = false, visEmpty = 0, visOther = 0;
            for (var i = 0; i < tb.rows.length; i++) {
                var r = tb.rows[i];
                var hasES = !!r.querySelector('.empty-state');
                if (hasES) anyEmpty = true;
                var cs = window.getComputedStyle(r);
                var rc = r.getBoundingClientRect();
                var vis = cs.display !== 'none' && rc.width > 0 && rc.height > 0;
                if (!vis) continue;
                if (hasES) visEmpty++; else visOther++;
            }
            if (anyEmpty && visOther === 0 && visEmpty > 0) {
                t.setAttribute('data-empty', '1');
            } else {
                t.removeAttribute('data-empty');
            }
        });
    }
    function markSoon() {
        if (timer) clearTimeout(timer);
        timer = setTimeout(mark, 60);
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', mark);
    } else { mark(); }
    document.addEventListener('sf:table-updated', markSoon);
    if (window.MutationObserver) {
        var mo = new MutationObserver(function (muts) {
            for (var i = 0; i < muts.length; i++) {
                var n = muts[i].target;
                if (n && n.nodeType === 1 && n.matches &&
                    (n.matches('tbody') || (n.closest && n.closest('table')))) {
                    markSoon(); return;
                }
            }
        });
        var hook = function () {
            document.querySelectorAll('table.data, table[id$="-table"]').forEach(function (t) {
                if (t.tBodies[0]) mo.observe(t.tBodies[0], {
                    childList: true, subtree: true,
                    attributes: true, attributeFilter: ['style', 'class']
                });
            });
            mark();
        };
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', hook);
        } else { hook(); }
        /* late hook: tables rendered by ajax after load */
        setTimeout(hook, 1500);
    }
    window.sfMarkEmptyTables = mark;
})();
/* /SF-EMPTYMARK */
