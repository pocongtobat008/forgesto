/* =====================================================================
   StockForge · UI behaviours  (vanilla JS, zero dependencies)
   Hallmark · Workbench Shell
   Sidebar accordion + collapse · command palette (⌘K) · topbar dropdown
   Searchable select · barcode scanner · keyboard-wedge capture
   ===================================================================== */
(function () {
    'use strict';

    var root = document.documentElement;
    var IS_MAC = /Mac|iPhone|iPad/.test(navigator.platform || '');

    function $(sel, ctx) { return (ctx || document).querySelector(sel); }
    function $$(sel, ctx) { return Array.prototype.slice.call((ctx || document).querySelectorAll(sel)); }
    function store(key, val) {
        try {
            if (val === undefined) return localStorage.getItem(key);
            if (val === null) { localStorage.removeItem(key); return null; }
            localStorage.setItem(key, val);
        } catch (e) {}
        return null;
    }

    /* ============================================================ sidebar & appshell */
    var sidebar  = $('[data-sidebar]');
    var backdrop = $('[data-sidebar-backdrop]');
    var collapseBtn = $('[data-sidebar-collapse]');
    var expandBtn   = $('[data-sidebar-expand]');

    function isDesktop() {
        return window.innerWidth >= 1024;
    }

    function closeMobileDrawer() {
        if (sidebar) sidebar.classList.remove('is-open');
        if (backdrop) backdrop.classList.remove('is-open');
        document.body.classList.remove('no-scroll');
    }

    function openMobileDrawer() {
        if (sidebar) sidebar.classList.add('is-open');
        if (backdrop) backdrop.classList.add('is-open');
        document.body.classList.add('no-scroll');
    }

    function setDesktopCollapsed(collapsed) {
        root.classList.toggle('sidebar-collapsed', collapsed);
        store('stockforge-sidebar', collapsed ? 'collapsed' : 'expanded');
        if (collapseBtn) collapseBtn.setAttribute('aria-label', collapsed ? 'Bentangkan sidebar' : 'Ciutkan sidebar');
    }

    // Toggle via Topbar Hamburger / [data-sidebar-toggle]:
    // On Desktop -> collapses/expands navbar rail (persistent).
    // On Mobile/Tablet -> opens/closes off-canvas drawer (temporary).
    $$('[data-sidebar-toggle]').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            if (isDesktop()) {
                setDesktopCollapsed(!root.classList.contains('sidebar-collapsed'));
            } else {
                if (!sidebar) return;
                sidebar.classList.contains('is-open') ? closeMobileDrawer() : openMobileDrawer();
            }
        });
    });

    // Close button inside mobile drawer
    $$('[data-sidebar-close]').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            closeMobileDrawer();
        });
    });

    // Backdrop click closes mobile drawer
    if (backdrop) backdrop.addEventListener('click', closeMobileDrawer);

    // Clicking any nav link inside drawer closes the drawer on mobile
    if (sidebar) {
        sidebar.addEventListener('click', function (e) {
            if (!isDesktop() && e.target.closest('.nav-item')) {
                closeMobileDrawer();
            }
        });
    }

    // Escape key closes mobile drawer
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && sidebar && sidebar.classList.contains('is-open')) {
            closeMobileDrawer();
        }
    });

    // Sidebar collapse button inside sidebar brand
    if (collapseBtn) {
        collapseBtn.addEventListener('click', function (e) {
            e.preventDefault();
            if (isDesktop()) {
                setDesktopCollapsed(!root.classList.contains('sidebar-collapsed'));
            } else {
                closeMobileDrawer();
            }
        });
    }
    if (expandBtn) expandBtn.addEventListener('click', function () { setDesktopCollapsed(false); });

    // Responsive listener for viewport change
    window.addEventListener('resize', function () {
        if (isDesktop()) {
            closeMobileDrawer();
        }
    });

    /* ---- accordion groups ---- */
    var groups = $$('.sidebar-group');
    groups.forEach(function (grp, idx) {
        var toggle = $('.sidebar-group-toggle', grp);
        if (!toggle) return;
        var hasActive = !!$('.nav-item.is-active', grp);
        var saved = store('sf-sb-grp-' + idx);
        if (hasActive || saved === 'open') grp.classList.add('is-open');
        else if (saved === 'closed') grp.classList.remove('is-open');

        toggle.setAttribute('aria-expanded', grp.classList.contains('is-open') ? 'true' : 'false');
        toggle.addEventListener('click', function () {
            if (root.classList.contains('sidebar-collapsed') && isDesktop()) {
                setDesktopCollapsed(false);
            }
            var open = grp.classList.toggle('is-open');
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            store('sf-sb-grp-' + idx, open ? 'open' : 'closed');
        });
    });

    /* ============================================================== theme */
    var themeToggles = $$('[data-theme-toggle]');

    function applyTheme(theme) {
        root.setAttribute('data-theme', theme);
        // Kanonik: 'sf-theme' (dibaca layout saat boot + theme-toggle.js).
        // Kunci lama 'stockforge-theme' dibersihkan agar tidak ada dua sumber kebenaran.
        try {
            localStorage.setItem('sf-theme', theme);
            localStorage.removeItem('stockforge-theme');
        } catch (e) { store('stockforge-theme', theme); }
        themeToggles.forEach(function (btn) {
            btn.setAttribute('aria-label', theme === 'dark' ? 'Mode terang' : 'Mode gelap');
            var sun = $('.icon-sun', btn), moon = $('.icon-moon', btn);
            if (sun && moon) {
                sun.style.display = theme === 'dark' ? '' : 'none';
                moon.style.display = theme === 'dark' ? 'none' : '';
            }
        });
    }

    // Migrasi kunci tema lama -> kanonik (sekali jalan, level modul)
    try {
        if (!localStorage.getItem('sf-theme')) {
            var __legacy = localStorage.getItem('stockforge-theme');
            if (__legacy === 'dark' || __legacy === 'light') {
                localStorage.setItem('sf-theme', __legacy);
                root.setAttribute('data-theme', __legacy);
                localStorage.removeItem('stockforge-theme');
            }
        }
    } catch (e) {}
    themeToggles.forEach(function (btn) {
        btn.addEventListener('click', function () {
            applyTheme(root.getAttribute('data-theme') === 'dark' ? 'light' : 'dark');
        });
    });

    /* =========================================================== dropdown */
    $$('[data-dropdown]').forEach(function (dd) {
        var btn = $('[data-dropdown-toggle]', dd);
        if (!btn) return;
        btn.addEventListener('click', function (e) {
            e.stopPropagation();
            var wasOpen = dd.classList.contains('is-open');
            $$('[data-dropdown]').forEach(function (o) { o.classList.remove('is-open'); });
            if (!wasOpen) dd.classList.add('is-open');
        });
    });
    document.addEventListener('click', function () {
        $$('[data-dropdown]').forEach(function (o) { o.classList.remove('is-open'); });
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') $$('[data-dropdown]').forEach(function (o) { o.classList.remove('is-open'); });
    });

    /* ====================================================== command palette */
    function buildPaletteItems() {
        var items = [];

        // 1. PRIMARY ACTION AT VERY TOP: Scan Barcode / QR (Ctrl+B)
        items.push({
            group: 'Aksi Cepat',
            title: 'Scan Barcode / QR',
            sub: 'Buka scanner kamera langsung untuk audit fisik',
            action: 'scan',
            icon: 'scan',
            shortcut: (typeof IS_MAC !== 'undefined' && IS_MAC) ? '⌘B' : 'Ctrl B'
        });

        // 2. Secondary quick actions
        items.push({
            group: 'Aksi Cepat',
            title: 'Ganti tema terang / gelap',
            sub: 'Terang ⇄ Gelap',
            action: 'theme',
            icon: 'sun'
        });
        var searchInput = $('#search');
        if (searchInput) {
            items.push({
                group: 'Aksi Cepat',
                title: 'Cari data STO',
                sub: 'Fokus kolom pencarian tabel',
                action: 'focus-search',
                icon: 'filter'
            });
        }

        // 3. Navigation links straight off the sidebar (permission-accurate)
        $$('.sidebar .nav-item[href]').forEach(function (a) {
            var label = ($('.nav-label', a) || {}).textContent || a.getAttribute('data-tip') || '';
            label = label.trim();
            if (!label) return;
            var iconSvg = $('svg', a);
            items.push({
                group: 'Navigasi',
                title: label,
                sub: a.getAttribute('href'),
                href: a.getAttribute('href'),
                iconHtml: iconSvg ? iconSvg.outerHTML : null
            });
        });

        return items;
    }

    var PALETTE_ICONS = {
        sun: '<circle cx="12" cy="12" r="4"/><line x1="12" y1="2" x2="12" y2="4"/><line x1="12" y1="20" x2="12" y2="22"/><line x1="4.2" y1="4.2" x2="5.6" y2="5.6"/><line x1="18.4" y1="18.4" x2="19.8" y2="19.8"/><line x1="2" y1="12" x2="4" y2="12"/><line x1="20" y1="12" x2="22" y2="12"/><line x1="4.2" y1="19.8" x2="5.6" y2="18.4"/><line x1="18.4" y1="5.6" x2="19.8" y2="4.2"/>',
        search: '<circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>',
        filter: '<polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/>',
        scan: '<path d="M3 7V5a2 2 0 0 1 2-2h2M17 3h2a2 2 0 0 1 2 2v2M21 17v2a2 2 0 0 1-2 2h-2M7 21H5a2 2 0 0 1-2-2v-2"/><line x1="10" y1="12" x2="14" y2="12"/>'
    };
    function iconSvg(name) {
        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' + (PALETTE_ICONS[name] || PALETTE_ICONS.search) + '</svg>';
    }

    var paletteBackdrop = $('[data-palette]');
    var paletteInput    = $('[data-palette-input]');
    var paletteList     = $('[data-palette-list]');
    var paletteItems    = [];
    var paletteFiltered = [];
    var paletteActive   = 0;

    function openPalette() {
        if (!paletteBackdrop) return;
        paletteItems = buildPaletteItems();
        paletteBackdrop.classList.add('is-open');
        document.body.classList.add('no-scroll');
        if (paletteInput) { paletteInput.value = ''; }
        renderPalette('');
        setTimeout(function () { if (paletteInput) paletteInput.focus(); }, 30);
    }
    function closePalette() {
        if (!paletteBackdrop) return;
        paletteBackdrop.classList.remove('is-open');
        document.body.classList.remove('no-scroll');
    }

    function renderPalette(q) {
        if (!paletteList) return;
        q = (q || '').toLowerCase().trim();
        paletteFiltered = paletteItems.filter(function (it) {
            if (!q) return true;
            return (it.title + ' ' + (it.sub || '')).toLowerCase().indexOf(q) !== -1;
        });
        paletteActive = 0;

        if (!paletteFiltered.length) {
            paletteList.innerHTML = '<div class="palette-empty">Tidak ada hasil untuk “' + q.replace(/[<>&]/g, '') + '”.</div>';
            return;
        }

        var html = '';
        var lastGroup = null;
        paletteFiltered.forEach(function (it, i) {
            if (it.group !== lastGroup) {
                html += '<div class="palette-group-label">' + it.group + '</div>';
                lastGroup = it.group;
            }
            var ic = it.iconHtml || iconSvg(it.icon);
            html += '<div class="palette-item' + (i === 0 ? ' is-active' : '') + '" data-index="' + i + '" role="option">' +
                        '<span class="pi-icon">' + ic + '</span>' +
                        '<span class="pi-body"><span class="pi-title">' + it.title + '</span>' +
                        (it.sub ? '<span class="pi-sub">' + it.sub + '</span>' : '') + '</span>' +
                        (it.shortcut ? '<kbd class="pi-kbd">' + it.shortcut + '</kbd>' : '') +
                    '</div>';
        });
        paletteList.innerHTML = html;

        $$('.palette-item', paletteList).forEach(function (el) {
            el.addEventListener('mouseenter', function () { setPaletteActive(parseInt(el.getAttribute('data-index'), 10)); });
            el.addEventListener('click', function () { runPaletteItem(parseInt(el.getAttribute('data-index'), 10)); });
        });
    }

    function setPaletteActive(idx) {
        paletteActive = idx;
        $$('.palette-item', paletteList).forEach(function (el, i) {
            el.classList.toggle('is-active', i === idx);
        });
        var active = $('.palette-item.is-active', paletteList);
        if (active && active.scrollIntoView) active.scrollIntoView({ block: 'nearest' });
    }

    function runPaletteItem(idx) {
        var it = paletteFiltered[idx];
        if (!it) return;
        if (it.action === 'theme') { applyTheme(root.getAttribute('data-theme') === 'dark' ? 'light' : 'dark'); closePalette(); return; }
        if (it.action === 'scan') {
            closePalette();
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
        if (it.action === 'focus-search') { closePalette(); var si = $('#search'); if (si) { si.focus(); si.select && si.select(); } return; }
        if (it.href) window.location.href = it.href;
    }

    if (paletteBackdrop) {
        if (paletteInput) paletteInput.addEventListener('input', function () { renderPalette(paletteInput.value); });
        paletteBackdrop.addEventListener('click', function (e) { if (e.target === paletteBackdrop) closePalette(); });

        $$('[data-palette-open]').forEach(function (b) {
            b.addEventListener('click', function (e) { e.preventDefault(); openPalette(); });
        });

        if (paletteInput) paletteInput.addEventListener('keydown', function (e) {
            if (e.key === 'ArrowDown') { e.preventDefault(); if (paletteActive < paletteFiltered.length - 1) setPaletteActive(paletteActive + 1); }
            else if (e.key === 'ArrowUp') { e.preventDefault(); if (paletteActive > 0) setPaletteActive(paletteActive - 1); }
            else if (e.key === 'Enter') { e.preventDefault(); runPaletteItem(paletteActive); }
        });
    }

    /* label ⌘K / Ctrl K */
    $$('[data-palette-hint]').forEach(function (el) { el.textContent = IS_MAC ? '⌘K' : 'Ctrl K'; });

    document.addEventListener('keydown', function (e) {
        var typing = /^(INPUT|TEXTAREA|SELECT)$/.test((document.activeElement || {}).tagName || '') || (document.activeElement && document.activeElement.isContentEditable);
        if ((e.key === 'k' || e.key === 'K') && (e.metaKey || e.ctrlKey)) {
            e.preventDefault();
            paletteBackdrop && paletteBackdrop.classList.contains('is-open') ? closePalette() : openPalette();
            return;
        }
        if (e.key === '/' && !typing && paletteBackdrop) { e.preventDefault(); openPalette(); return; }
        if (e.key === 'Escape') { closePalette(); if (typeof closeMobileDrawer === 'function') closeMobileDrawer(); }
    });

    /* ==================================================== searchable select */
    $$('[data-search-select]').forEach(function (wrap) {
        var input  = $('input:not([type="hidden"])', wrap);
        var list   = $('.ss-list', wrap);
        var hidden = $('input[type="hidden"]', wrap);
        if (!input || !list) return;
        var items = $$('.ss-item', list);

        function filter(q) {
            q = (q || '').toLowerCase();
            var visible = 0;
            items.forEach(function (it) {
                var hit = it.textContent.toLowerCase().indexOf(q) !== -1;
                it.style.display = hit ? '' : 'none';
                if (hit) visible++;
            });
            var empty = $('.ss-empty', list);
            if (empty) empty.style.display = visible ? 'none' : '';
        }

        input.addEventListener('focus', function () { wrap.classList.add('is-open'); input.value = ''; filter(''); });
        input.addEventListener('input', function () { filter(input.value); });
        document.addEventListener('click', function (e) { if (!wrap.contains(e.target)) wrap.classList.remove('is-open'); });
        items.forEach(function (it) {
            it.addEventListener('click', function () {
                items.forEach(function (x) { x.classList.remove('is-selected'); });
                it.classList.add('is-selected');
                input.value = it.getAttribute('data-label') || it.textContent.trim();
                if (hidden) hidden.value = it.getAttribute('data-value') || '';
                wrap.classList.remove('is-open');
                var form = wrap.closest('form');
                if (form && wrap.hasAttribute('data-auto-submit')) form.submit();
            });
        });
    });

    /* =========================================================== flash timer */
    $$('[data-auto-dismiss]').forEach(function (alert) {
        setTimeout(function () {
            alert.style.transition = 'opacity .4s ease, transform .4s ease';
            alert.style.opacity = '0';
            alert.style.transform = 'translateY(-6px)';
            setTimeout(function () { alert.remove(); }, 420);
        }, 6000);
    });

    /* ======================================================= confirm & submit */
    $$('form[data-confirm]').forEach(function (form) {
        form.addEventListener('submit', function (e) {
            if (!window.confirm(form.getAttribute('data-confirm') || 'Yakin ingin melanjutkan?')) e.preventDefault();
        });
    });
    $$('[data-auto-submit]').forEach(function (el) {
        if (el.tagName === 'FORM') return;
        el.addEventListener('change', function () { var f = el.closest('form'); if (f) f.submit(); });
    });
    /* live (debounced) server-side search so typing filters across all pages */
    $$('[data-filter-live]').forEach(function (input) {
        var form = input.closest('form');
        if (!form) return;
        var t;
        input.addEventListener('input', function () {
            clearTimeout(t);
            t = setTimeout(function () { form.submit(); }, 550);
        });
        input.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') { clearTimeout(t); form.submit(); }
        });
    });
    $$('[data-copy]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var value = btn.getAttribute('data-copy');
            if (!value || !navigator.clipboard) return;
            navigator.clipboard.writeText(value).then(function () {
                var original = btn.innerHTML;
                btn.innerHTML = 'Tersalin!';
                setTimeout(function () { btn.innerHTML = original; }, 1500);
            });
        });
    });
    $$('[data-nav-select]').forEach(function (select) {
        select.addEventListener('change', function () {
            var base = select.getAttribute('data-nav-select');
            var url = new URL(base, window.location.origin);
            url.searchParams.set(select.name, select.value);
            window.location.href = url.toString();
        });
    });

    /* ============================================================ barcode */
    var scanBtns = $$('[data-scan-open]');
    var scanModal = $('[data-scan-modal]');
    var scanner = null;
    var libLoading = false;

    function scanSearchInput() { return $('#search'); }

    function closeScanner() {
        if (scanModal) scanModal.classList.remove('is-open');
        document.body.classList.remove('no-scroll');
        if (scanner) {
            var s = scanner; scanner = null;
            try { s.stop().then(function () { s.clear(); }).catch(function () {}); } catch (e) {}
        }
    }
    function showScanError(msg) {
        var vp = $('#scanner-viewport');
        if (vp) vp.innerHTML = '<div class="scanner-error">' + msg + '</div>';
    }
    function startScanner() {
        var vp = $('#scanner-viewport');
        if (!vp) return;
        vp.innerHTML = '<div class="scanner-frame"></div>';
        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            showScanError('Kamera tidak tersedia di perangkat/browser ini. Gunakan pencarian manual.');
            return;
        }
        try {
            scanner = new Html5Qrcode('scanner-viewport');
            scanner.start(
                { facingMode: 'environment' },
                { fps: 10, qrbox: { width: 240, height: 240 } },
                function (decoded) {
                    if (window.stoHandleBarcodeScan) {
                        closeScanner();
                        window.stoHandleBarcodeScan(decoded);
                        return;
                    }
                    var si = scanSearchInput();
                    if (si) { si.value = decoded; var f = si.closest('form'); closeScanner(); if (f) f.submit(); }
                    else closeScanner();
                },
                function () {}
            ).catch(function () {
                showScanError('Tidak dapat mengakses kamera. Pastikan izin kamera diberikan, atau gunakan pencarian manual.');
            });
        } catch (e) {
            showScanError('Scanner gagal dimulai. Gunakan pencarian manual.');
        }
    }
    function loadScannerLib(cb) {
        if (typeof Html5Qrcode !== 'undefined') { cb(); return; }
        if (libLoading) return;
        libLoading = true;
        var s = document.createElement('script');
        s.src = 'https://unpkg.com/html5-qrcode';
        s.onload = function () { libLoading = false; cb(); };
        s.onerror = function () { libLoading = false; showScanError('Gagal memuat library scanner. Periksa koneksi internet.'); };
        document.head.appendChild(s);
    }
    if (scanBtns.length > 0 && scanModal) {
        scanBtns.forEach(function(btn) {
            btn.addEventListener('click', function () {
                scanModal.classList.add('is-open');
                document.body.classList.add('no-scroll');
                var vp = $('#scanner-viewport');
                if (vp) vp.innerHTML = '<div class="scanner-hint">Memuat scanner…</div>';
                loadScannerLib(startScanner);
            });
        });
        $$('[data-scan-close]').forEach(function (btn) { btn.addEventListener('click', closeScanner); });
        scanModal.addEventListener('click', function (e) { if (e.target === scanModal) closeScanner(); });
    }

    /* ---- keyboard-wedge capture: USB/HID barcode gun types fast + Enter ---- */
    var wedgeBuf = '', wedgeAt = 0;
    document.addEventListener('keydown', function (e) {
        // ignore when a modal/palette owns the keyboard
        if (paletteBackdrop && paletteBackdrop.classList.contains('is-open')) return;
        if (scanModal && scanModal.classList.contains('is-open')) return;

        var tag = (document.activeElement || {}).tagName || '';
        var editing = /^(INPUT|TEXTAREA|SELECT)$/.test(tag) || (document.activeElement && document.activeElement.isContentEditable);
        if (editing) return;

        var now = Date.now();
        if (now - wedgeAt > 70) wedgeBuf = '';   // gap too long → new sequence
        wedgeAt = now;

        if (e.key === 'Enter') {
            if (wedgeBuf.length >= 6) {
                var si = scanSearchInput();
                if (si) { si.value = wedgeBuf; var f = si.closest('form'); if (f) f.submit(); }
            }
            wedgeBuf = '';
            return;
        }
        if (e.key.length === 1) wedgeBuf += e.key;
    });

    /* ============================================ chart bootstrap (dashboard) */
    function chartColors() {
        var cs = getComputedStyle(root);
        function v(name, fb) { var x = cs.getPropertyValue(name).trim(); return x || fb; }
        var dark = root.getAttribute('data-theme') === 'dark';
        return {
            text: v('--color-ink-2', dark ? '#9aa3bd' : '#666c85'),
            grid: v('--color-line', dark ? '#262f42' : '#e4e6f0'),
            brand: v('--color-accent', '#6366f1'),
            success: v('--color-success', '#10b981'),
            info: v('--color-info', '#0ea5e9'),
            warning: v('--color-warning', '#f59e0b'),
            danger: v('--color-danger', '#ef4444'),
            track: v('--color-paper-3', dark ? '#222a3b' : '#eceef7')
        };
    }
    function initDashboardCharts() {
        if (typeof Chart === 'undefined') return;
        var c = chartColors();
        Chart.defaults.font.family = getComputedStyle(root).getPropertyValue('--font-sans') || "'Google Sans Flex', system-ui, sans-serif";
        Chart.defaults.color = c.text;

        var doughnutEl = $('#chart-doughnut');
        if (doughnutEl && window.SF_DASH) {
            var d = window.SF_DASH;
            new Chart(doughnutEl, {
                type: 'doughnut',
                data: {
                    labels: ['Sudah dihitung', 'Belum dihitung', 'Sudah diaudit'],
                    datasets: [{
                        data: [d.counted, d.notCounted, d.audited],
                        backgroundColor: [c.success, c.track, c.info],
                        borderWidth: 0, hoverOffset: 6
                    }]
                },
                options: {
                    cutout: '72%',
                    animation: { animateRotate: true, duration: 900 },
                    plugins: {
                        legend: { position: 'bottom', labels: { boxWidth: 10, boxHeight: 10, borderRadius: 3, useBorderRadius: true, padding: 14 } },
                        tooltip: { callbacks: { label: function (ctx) { return ' ' + ctx.label + ': ' + ctx.parsed; } } }
                    }
                }
            });
            var pctEl = $('#chart-doughnut-pct');
            if (pctEl) pctEl.textContent = d.countPercent + '%';
        }

        var barEl = $('#chart-groups');
        if (barEl && window.SF_DASH && window.SF_DASH.groups) {
            var groups = window.SF_DASH.groups.slice(0, 25);
            new Chart(barEl, {
                type: 'bar',
                data: {
                    labels: groups.map(function (g) { return g.name; }),
                    datasets: [
                        { label: 'Dihitung %', data: groups.map(function (g) { return g.count_percent; }), backgroundColor: c.success, borderRadius: 4, barPercentage: .7, categoryPercentage: .62 },
                        { label: 'Diaudit %', data: groups.map(function (g) { return g.audit_percent; }), backgroundColor: c.info, borderRadius: 4, barPercentage: .7, categoryPercentage: .62 }
                    ]
                },
                options: {
                    indexAxis: 'y',
                    animation: { duration: 700 },
                    maintainAspectRatio: false,
                    scales: {
                        x: { min: 0, max: 100, grid: { color: c.grid }, ticks: { callback: function (v) { return v + '%'; } } },
                        y: { grid: { display: false } }
                    },
                    plugins: {
                        legend: { position: 'top', align: 'end', labels: { boxWidth: 10, boxHeight: 10, useBorderRadius: true, borderRadius: 3 } },
                        tooltip: { callbacks: { label: function (ctx) { return ' ' + ctx.dataset.label + ': ' + ctx.parsed.x + '%'; } } }
                    }
                }
            });
        }
    }
    if ($('#chart-doughnut') || $('#chart-groups')) {
        if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initDashboardCharts);
        else initDashboardCharts();
    }

    /* ================================================= submit loading state */
    /* Prevents double-submit on every form: on submit the clicked submit button
       enters an inline spinner state (.is-loading) and gets disabled, and the
       form is latched so a second submit cannot fire. Mutations that opt out via
       [data-confirm] are handled: the confirm dialog runs first, and the state
       only latches once the user accepts. */
    document.addEventListener('submit', function (e) {
        var form = e.target;
        if (!form || form.tagName !== 'FORM') return;
        if (e.defaultPrevented) return;                 // confirm was declined
        if (form.getAttribute('data-confirm') && e.submitter === null) {
            // confirmed via native dialog path — still fine, fall through
        }

        var btn = e.submitter
            || form.querySelector('[type="submit"]')
            || form.querySelector('button:not([type])')
            || form.querySelector('button');
        var label = ((btn && (btn.getAttribute('data-loading-label') || btn.textContent)) || 'Memproses').trim();
        if (label.length > 22) label = 'Memproses';

        // Block the whole form from being submitted again (disable other submit controls)
        $$('[type="submit"]', form).forEach(function (b) { b.disabled = true; });

        if (btn && btn.tagName === 'BUTTON') {
            if (!btn.getAttribute('data-loading-label')) btn.setAttribute('data-loading-label', label);
            btn.classList.add('is-loading');
            btn.setAttribute('aria-busy', 'true');
            btn.disabled = true;
        }

        // Global latch: block Enter-key re-submits + double clicks during navigation
        form.classList.add('is-submitting');
        $$('button, [type="submit"], a.btn', form).forEach(function (b) {
            if (b.tagName === 'A') { b.setAttribute('aria-disabled', 'true'); b.style.pointerEvents = 'none'; }
        });

        // Safety: if the browser restores the page from cache on "back", reset the state
        setTimeout(function () { form.classList.remove('is-submitting'); }, 15000);
    });

    // Reset loading state when the page is restored from bfcache (back button)
    window.addEventListener('pageshow', function (e) {
        if (!e.persisted) return;
        $$('form.is-submitting').forEach(function (f) { f.classList.remove('is-submitting'); });
        $$('.btn.is-loading').forEach(function (b) {
            b.classList.remove('is-loading');
            b.removeAttribute('aria-busy');
            b.disabled = false;
        });
        $$('button[disabled][data-loading-label], [type="submit"][disabled][data-loading-label]').forEach(function (b) {
            b.disabled = false;
        });
    });

    /* dialogs that submit via JS (e.g. reconciliation row modal) already manage
       their own button label; make their submit buttons spin too by tagging them */
})();


/* ---------------------------------------------------------------------------
   Ingat posisi scroll antar-refresh DI HALAMAN YANG SAMA.
   Masalah: tab / chip filter di atas tabel me-reload halaman, browser selalu
   kembali ke paling atas, jadi user harus scroll ribuan px lagi ke tabel.
   Solusi: simpan posisi sebelum navigasi ke URL dengan path sama, lalu
   pulihkan setelah halaman baru selesai render.
--------------------------------------------------------------------------- */
(function () {
    var KEY = 'sto:scroll:' + location.pathname;

    function savePos() {
        try { sessionStorage.setItem(KEY, String(Math.round(window.pageYOffset || window.scrollY || 0))); } catch (e) {}
    }

    /* klik link yang pindah ke URL sama (beda query) = tab/chip filter */
    document.addEventListener('click', function (ev) {
        if (ev.defaultPrevented || ev.button || ev.metaKey || ev.ctrlKey || ev.shiftKey || ev.altKey) return;
        var a = ev.target && ev.target.closest ? ev.target.closest('a[href]') : null;
        if (!a || a.target || a.hasAttribute('download')) return;
        var href = a.getAttribute('href') || '';
        if (!href || href.charAt(0) === '#') return;
        var u;
        try { u = new URL(a.href, location.href); } catch (e) { return; }
        if (u.pathname === location.pathname && u.search !== location.search) savePos();
    }, true);

    /* submit form di halaman ini (mis. simpan hitung) juga diingat */
    document.addEventListener('submit', function () { savePos(); }, true);

    var saved;
    try { saved = sessionStorage.getItem(KEY); } catch (e) { saved = null; }
    if (saved === null) return;
    try { sessionStorage.removeItem(KEY); } catch (e) {}
    var y = parseInt(saved, 10);
    if (!(y > 120)) return;

    function apply() {
        try { window.scrollTo(0, y); } catch (e) {}
    }
    apply();
    if (document.readyState !== 'complete') window.addEventListener('load', apply);
    if (window.requestAnimationFrame) window.requestAnimationFrame(apply);
    setTimeout(apply, 120);
    setTimeout(apply, 400);
})();
