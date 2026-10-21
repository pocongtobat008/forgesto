/**
 * Muat ulang blok Informasi tanpa logout / reload halaman.
 *
 * - Tombol `[data-info-refresh]` tanpa `data-card`  -> halaman /informasi:
 *   ambil ulang HTML halaman, tukar isi #info-page-zone + angka ringkasan.
 * - Tombol `[data-info-refresh][data-card="#id"]`   -> kartu "Informasi Penting"
 *   di dashboard: tukar elemen kartu tersebut saja.
 * - Auto refresh tiap 60 detik selama tab terlihat (khusus halaman /informasi).
 */
(function () {
    'use strict';
    if (window.__sfInfoRefresh) { return; }
    window.__sfInfoRefresh = true;

    function stamp() {
        var el = document.getElementById('info-updated');
        if (!el) { return; }
        var opts = { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: false };
        if (el.dataset.tz) { opts.timeZone = el.dataset.tz; }
        var now;
        try {
            now = new Date().toLocaleTimeString('en-GB', opts);
        } catch (e) {
            now = new Date().toLocaleTimeString('en-GB', { hour12: false });
        }
        el.textContent = (el.dataset.label || 'Diperbarui') + ' ' + now;
    }

    function spin(btn, on) {
        if (!btn) { return; }
        btn.disabled = on;
        btn.classList.toggle('is-spinning', on);
    }

    function toast(msg, type) {
        if (typeof window.sfToast === 'function') { window.sfToast(msg, type || 'danger'); return; }
        if (typeof window.toast === 'function') { window.toast(msg, type || 'danger'); }
    }

    async function grab(url) {
        var res = await fetch(url, {
            cache: 'no-store',
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'text/html' }
        });
        if (!res.ok) { throw new Error('HTTP ' + res.status); }
        return new DOMParser().parseFromString(await res.text(), 'text/html');
    }

    async function refreshPage(btn) {
        spin(btn, true);
        try {
            var doc = await grab(location.pathname + '?_=' + Date.now());
            var zone = document.getElementById('info-page-zone');
            var fresh = doc.getElementById('info-page-zone');
            if (zone && fresh) { zone.innerHTML = fresh.innerHTML; }
            ['total', 'active', 'notified'].forEach(function (k) {
                var a = doc.getElementById('stat-' + k);
                var b = document.getElementById('stat-' + k);
                if (a && b) { b.textContent = a.textContent.trim(); }
            });
            stamp();
            if (window.SFTablesExpand && typeof window.SFTablesExpand.refresh === 'function') {
                window.SFTablesExpand.refresh();
            }
        } catch (e) {
            toast('Gagal memuat ulang: ' + e.message);
        } finally {
            spin(btn, false);
        }
    }

    async function refreshCard(btn, selector) {
        spin(btn, true);
        try {
            var doc = await grab(location.pathname + '?_=' + Date.now());
            var fresh = doc.querySelector(selector);
            var now = document.querySelector(selector);
            if (fresh && now) { now.replaceWith(fresh); }
            stamp();
        } catch (e) {
            toast('Gagal memuat ulang: ' + e.message);
        } finally {
            spin(btn, false);
        }
    }

    document.addEventListener('click', function (ev) {
        var btn = ev.target.closest ? ev.target.closest('[data-info-refresh]') : null;
        if (!btn) { return; }
        ev.preventDefault();
        var card = btn.getAttribute('data-card');
        if (card) { refreshCard(btn, card); } else { refreshPage(btn); }
    });

    setInterval(function () {
        if (document.visibilityState !== 'visible') { return; }
        if (!document.getElementById('info-page-zone')) { return; }
        refreshPage(null);
    }, 60000);
})();
