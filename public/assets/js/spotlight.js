/* ==========================================================================
   SPOTLIGHT — pencarian cepat (Ctrl/Cmd+K atau klik kolom cari di topbar)
   Mencari: halaman (dari sidebar), group, dan item (via /api/spotlight).
   ========================================================================== */
(function () {
    'use strict';

    var backdrop = document.querySelector('[data-palette]');
    if (!backdrop) { return; }

    var input   = backdrop.querySelector('[data-palette-input]');
    var listEl  = backdrop.querySelector('[data-palette-list]');
    var trigger = document.querySelector('.topbar-search');
    var ENDPOINT = '/api/spotlight';

    var state = { items: [], active: -1, timer: null, seq: 0, open: false };

    var ICON = {
        page: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7" rx="1.6"/><rect x="14" y="3" width="7" height="7" rx="1.6"/><rect x="3" y="14" width="7" height="7" rx="1.6"/><rect x="14" y="14" width="7" height="7" rx="1.6"/></svg>',
        group: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M3 7h18"/><path d="M8 7V4.8A1.8 1.8 0 0 1 9.8 3h4.4A1.8 1.8 0 0 1 16 4.8V7"/><rect x="3" y="7" width="18" height="13" rx="2.2"/><path d="M9.5 12h5"/></svg>',
        item: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M21 16V8.5a2 2 0 0 0-1.1-1.8l-7-3.4a2 2 0 0 0-1.8 0l-7 3.4A2 2 0 0 0 3 8.5V16a2 2 0 0 0 1.1 1.8l7 3.4a2 2 0 0 0 1.8 0l7-3.4A2 2 0 0 0 21 16Z"/><path d="M3.4 7.9 12 12l8.6-4.1"/><path d="M12 12v9"/></svg>'
    };

    function esc(s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    /* ---------- halaman dari sidebar ---------- */
    var pageCache = null;

    function pages() {
        if (pageCache) { return pageCache; }

        var seen = {};
        var out  = [];

        document.querySelectorAll('.sidebar a[href]').forEach(function (a) {
            var href = a.getAttribute('href') || '';
            if (!href || href === '#' || href.indexOf('javascript:') === 0) { return; }
            if (seen[href]) { return; }

            var label = (a.querySelector('.sidebar-label') || a).textContent.replace(/\s+/g, ' ').trim();
            if (!label || label.length > 42) { return; }

            seen[href] = true;
            out.push({ kind: 'page', title: label, sub: 'Halaman', href: href, icon: ICON.page });
        });

        pageCache = out;

        return out;
    }

    /* ---------- render ---------- */
    function render(sections) {
        var html  = '';
        var index = 0;

        state.items = [];

        sections.forEach(function (section) {
            if (!section.items.length) { return; }

            html += '<div class="palette-group-label">' + esc(section.label) + '</div>';

            section.items.forEach(function (it) {
                html += '<a class="palette-item" href="' + esc(it.href) + '" data-idx="' + index + '">'
                    + '<span class="pi-icon">' + (it.icon || ICON.page) + '</span>'
                    + '<span class="pi-body">'
                    + '<span class="pi-title">' + esc(it.title) + '</span>'
                    + (it.sub ? '<span class="pi-sub">' + esc(it.sub) + '</span>' : '')
                    + '</span>'
                    + '<kbd>↵</kbd>'
                    + '</a>';

                state.items.push(it);
                index++;
            });
        });

        if (!index) {
            html = '<div class="palette-empty">Tidak ada hasil untuk “' + esc(input.value.trim()) + '”.</div>';
        }

        listEl.innerHTML = html;
        state.active = index ? 0 : -1;
        paint();
    }

    function paint() {
        listEl.querySelectorAll('.palette-item').forEach(function (el) {
            el.classList.toggle('is-active', Number(el.getAttribute('data-idx')) === state.active);
        });

        var cur = listEl.querySelector('.palette-item.is-active');
        if (cur && cur.scrollIntoView) {
            cur.scrollIntoView({ block: 'nearest' });
        }
    }

    function move(delta) {
        var total = state.items.length;
        if (!total) { return; }
        state.active = (state.active + delta + total) % total;
        paint();
    }

    function openActive() {
        if (state.active < 0 || !state.items[state.active]) { return; }
        window.location.href = state.items[state.active].href;
    }

    /* ---------- data ---------- */
    function matchPages(term) {
        if (!term) { return pages().slice(0, 6); }

        return pages().filter(function (p) {
            return p.title.toLowerCase().indexOf(term) !== -1;
        }).slice(0, 6);
    }

    function remote(term) {
        var seq = ++state.seq;

        fetch(ENDPOINT + '?q=' + encodeURIComponent(term), {
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin'
        }).then(function (r) {
            return r.ok ? r.json() : { groups: [], items: [] };
        }).then(function (data) {
            if (seq !== state.seq || !state.open) { return; }

            var groups = (data.groups || []).map(function (g) {
                return {
                    kind: 'group', icon: ICON.group,
                    title: g.name,
                    sub: 'Group · ' + g.code,
                    href: '/groups/' + g.id
                };
            });

            var items = (data.items || []).map(function (it) {
                return {
                    kind: 'item', icon: ICON.item,
                    title: it.name,
                    sub: it.code + ' · ' + it.group,
                    href: '/items?code[]=' + encodeURIComponent(it.code)
                };
            });

            render([
                { label: 'Halaman', items: matchPages(term) },
                { label: 'Group', items: groups },
                { label: 'Item', items: items }
            ]);
        }).catch(function () { /* diamkan: jaringan gagal, biarkan hasil lokal */ });
    }

    /* ---------- buka / tutup ---------- */
    function open() {
        state.open = true;
        backdrop.classList.add('is-open');
        document.documentElement.style.overflow = 'hidden';
        input.value = '';
        state.seq++;
        render([{ label: 'Halaman', items: pages().slice(0, 7) }]);

        setTimeout(function () { input.focus(); }, 40);
    }

    function close() {
        state.open = false;
        backdrop.classList.remove('is-open');
        document.documentElement.style.overflow = '';
        state.seq++;
    }

    /* ---------- event ---------- */
    if (trigger) {
        trigger.addEventListener('click', function (e) {
            e.preventDefault();
            open();
        });
    }

    document.addEventListener('keydown', function (e) {
        var k = e.key || '';

        if ((e.ctrlKey || e.metaKey) && (k === 'k' || k === 'K')) {
            e.preventDefault();
            state.open ? close() : open();
            return;
        }

        if (!state.open) { return; }

        if (k === 'Escape') { e.preventDefault(); close(); return; }
        if (k === 'ArrowDown') { e.preventDefault(); move(1); return; }
        if (k === 'ArrowUp') { e.preventDefault(); move(-1); return; }
        if (k === 'Enter') {
            e.preventDefault();
            openActive();
        }
    });

    input.addEventListener('input', function () {
        var term = input.value.trim().toLowerCase();

        state.seq++;
        render([{ label: 'Halaman', items: matchPages(term) }]);

        if (state.timer) { clearTimeout(state.timer); }
        if (term.length < 2) { return; }

        state.timer = setTimeout(function () { remote(term); }, 170);
    });

    backdrop.addEventListener('click', function (e) {
        if (e.target === backdrop) { close(); }
    });

    listEl.addEventListener('mousemove', function (e) {
        var el = e.target.closest('.palette-item');
        if (!el) { return; }
        var idx = Number(el.getAttribute('data-idx'));
        if (idx !== state.active) { state.active = idx; paint(); }
    });


    /* ---------- searchable select: pulihkan label saat batal memilih ---------- */
    document.querySelectorAll('[data-search-select]').forEach(function (wrap) {
        var field = wrap.querySelector('input[type="text"]');
        if (!field) { return; }

        function restore() {
            if (field.value.trim() !== '') { return; }
            var sel = wrap.querySelector('.ss-item.is-selected');
            field.value = sel ? (sel.getAttribute('data-label') || sel.textContent.trim()) : '';
        }

        field.addEventListener('blur', function () { setTimeout(restore, 120); });
        field.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') { restore(); field.blur(); }
        });
    });

    /* Enter di kotak "Cari nama atau kode group…" (kartu Ringkasan) → buka group pertama */
    var groupSearch = document.getElementById('sf-summary-search');
    if (groupSearch) {
        groupSearch.addEventListener('keydown', function (e) {
            if (e.key !== 'Enter') { return; }
            e.preventDefault();

            var first = document.querySelector('#sf-summary-tbody .sf-summary-row:not([style*="display: none"]) .sf-group-name');
            if (first && first.getAttribute('href')) { window.location.href = first.getAttribute('href'); }
        });
    }
})();
