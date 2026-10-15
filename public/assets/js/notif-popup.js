/* ==========================================================================
   StockForge STO — Header Notification Popup (v1.0)
   Loaded globally from layouts/app.php. Zero dependencies.
   ========================================================================== */
(function () {
    'use strict';

    var doc = document;
    if (!doc) return;

    var wrap    = doc.querySelector('[data-notif]');
    if (!wrap) return;

    var toggle  = wrap.querySelector('[data-notif-toggle]');
    var panel   = wrap.querySelector('[data-notif-panel]');
    var badge   = wrap.querySelector('[data-notif-badge]');
    var countEl = wrap.querySelector('[data-notif-count]');
    var listEl  = wrap.querySelector('[data-notif-list]');
    var closeEl = wrap.querySelector('[data-notif-close]');
    var readAll = wrap.querySelector('[data-notif-readall]');

    if (!toggle || !panel || !listEl) return;

    var base   = (doc.querySelector('meta[name="sf-base"]') || {}).content || '';
    var csrf   = (doc.querySelector('meta[name="csrf-token"]') || {}).content || '';
    var FEED   = base + '/notifications/feed';
    var READALL= base + '/notifications/read-all';

    var loaded = false;
    var busy   = false;

    function esc(s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    }

    function iconSvg(name) {
        var paths = {
            info:     '<circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/>',
            check:    '<path d="M20 6 9 17l-5-5"/>',
            alert:    '<path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3"/><path d="M12 9v4"/><path d="M12 17h.01"/>',
            bell:     '<path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/>',
            x:        '<path d="M18 6 6 18"/><path d="m6 6 12 12"/>'
        };
        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' + (paths[name] || paths.info) + '</svg>';
    }

    function setBadge(n) {
        n = parseInt(n, 10) || 0;
        if (badge) {
            if (n > 0) {
                badge.hidden = false;
                badge.textContent = n > 99 ? '99+' : String(n);
            } else {
                badge.hidden = true;
                badge.textContent = '0';
            }
        }
        if (countEl) {
            countEl.textContent = n > 0 ? String(n) : '';
            if (n <= 0) countEl.setAttribute('data-zero', '1');
            else countEl.removeAttribute('data-zero');
        }
        if (readAll) readAll.disabled = n <= 0;
    }

    function render(items, unread) {
        if (!items || !items.length) {
            listEl.innerHTML =
                '<div class="notif-empty">' + iconSvg('bell') +
                '<div>Tidak ada notifikasi</div>' +
                '<span style="font-size:.74rem;opacity:.75;">Kabar proses posting, audit, dan selisih akan muncul di sini.</span>' +
                '</div>';
            setBadge(unread);
            return;
        }

        var html = '';
        for (var i = 0; i < items.length; i++) {
            var it = items[i];
            var cls = 'notif-item' + (it.is_read ? '' : ' is-unread');
            var inner =
                '<span class="notif-item-ico t-' + esc(it.type) + '">' + iconSvg(it.icon) + '</span>' +
                '<span class="notif-item-body">' +
                    '<span class="notif-item-title">' + esc(it.title) + '</span>' +
                    (it.message ? '<span class="notif-item-msg">' + esc(it.message) + '</span>' : '') +
                    '<span class="notif-item-meta">' + esc(it.time) +
                        (it.group ? ' &middot; Group ' + esc(it.group) : '') +
                    '</span>' +
                '</span>' +
                (it.is_read ? '' : '<span class="notif-item-dot" aria-hidden="true"></span>');

            if (it.link) {
                html += '<a class="' + cls + '" href="' + esc(it.link) + '" data-notif-id="' + it.id + '">' + inner + '</a>';
            } else {
                html += '<div class="' + cls + '" data-notif-id="' + it.id + '">' + inner + '</div>';
            }
        }
        listEl.innerHTML = html;
        setBadge(unread);
    }

    function load(force) {
        if (busy) return;
        if (loaded && !force) return;
        busy = true;

        if (!loaded) {
            listEl.innerHTML = '<div class="notif-loading"><span class="notif-spinner"></span>Memuat notifikasi...</div>';
        }

        fetch(FEED, {
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
            credentials: 'same-origin'
        })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                busy = false;
                if (d && d.ok) {
                    loaded = true;
                    render(d.items, d.unread);
                } else {
                    listEl.innerHTML = '<div class="notif-empty">Gagal memuat notifikasi.</div>';
                }
            })
            .catch(function () {
                busy = false;
                listEl.innerHTML = '<div class="notif-empty">Tidak dapat menghubungi server.</div>';
            });
    }

    function open() {
        panel.hidden = false;
        toggle.setAttribute('aria-expanded', 'true');
        load(false);
    }

    function close() {
        panel.hidden = true;
        toggle.setAttribute('aria-expanded', 'false');
    }

    function isOpen() { return !panel.hidden; }

    /* ---- events ---- */
    toggle.addEventListener('click', function (e) {
        e.preventDefault();
        e.stopPropagation();
        isOpen() ? close() : open();
    });

    if (closeEl) closeEl.addEventListener('click', function (e) { e.preventDefault(); close(); });

    doc.addEventListener('click', function (e) {
        if (!isOpen()) return;
        if (!wrap.contains(e.target)) close();
    });

    doc.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && isOpen()) close();
    });

    /* Clicking a notification marks it read, then follows the link. */
    listEl.addEventListener('click', function (e) {
        var item = e.target.closest ? e.target.closest('[data-notif-id]') : null;
        if (!item) return;
        var id = item.getAttribute('data-notif-id');
        if (!id) return;

        if (item.classList.contains('is-unread')) {
            item.classList.remove('is-unread');
            var d = item.querySelector('.notif-item-dot');
            if (d) d.remove();
            setBadge(Math.max(0, (parseInt(badge ? badge.textContent : '0', 10) || 0) - 1));

            try {
                fetch(base + '/notifications/' + id + '/read', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-Token': csrf
                    },
                    credentials: 'same-origin',
                    body: 'csrf_token=' + encodeURIComponent(csrf) + '&_token=' + encodeURIComponent(csrf)
                }).catch(function () {});
            } catch (err) {}
        }
    });

    if (readAll) {
        readAll.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            readAll.disabled = true;

            fetch(READALL, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-Token': csrf
                },
                credentials: 'same-origin',
                body: 'csrf_token=' + encodeURIComponent(csrf) + '&_token=' + encodeURIComponent(csrf)
            })
                .then(function (r) { return r.json(); })
                .then(function (d) {
                    if (d && d.ok) {
                        var unreads = listEl.querySelectorAll('.notif-item.is-unread');
                        for (var i = 0; i < unreads.length; i++) {
                            unreads[i].classList.remove('is-unread');
                            var dot = unreads[i].querySelector('.notif-item-dot');
                            if (dot) dot.remove();
                        }
                        setBadge(0);
                        if (window.sfToast) window.sfToast('Semua notifikasi ditandai sudah dibaca.', 'success');
                    } else {
                        readAll.disabled = false;
                    }
                })
                .catch(function () { readAll.disabled = false; });
        });
    }

    /* ---- background poll every 60s for the badge ---- */
    setInterval(function () {
        if (doc.hidden) return;
        fetch(FEED + '?limit=1', {
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
            credentials: 'same-origin'
        })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (d && d.ok) {
                    setBadge(d.unread);
                    if (isOpen()) load(true);
                }
            })
            .catch(function () {});
    }, 60000);

    /* Boot: sync badge with the server-rendered value. */
    setBadge(badge && !badge.hidden ? badge.textContent : 0);
})();
