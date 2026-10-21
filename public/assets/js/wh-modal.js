/* ==========================================================================
   StockForge — Warehouse Picker Modal (mobile)
   Opens a bottom-sheet picker to switch the active warehouse.
   Progressive enhancement: the desktop <select> keeps working untouched.
   ========================================================================== */
(function () {
    'use strict';

    var opened = false;
    var lastFocus = null;

    function backdrop() { return document.querySelector('[data-wh-modal-backdrop]'); }
    function dialog()   { return document.querySelector('[data-wh-modal-dialog]'); }
    function form()     { return document.querySelector('[data-wh-modal-form]'); }
    function input()    { return document.querySelector('[data-wh-modal-input]'); }

    function open(trigger) {
        var bd = backdrop(), dg = dialog();
        if (!bd || !dg) return;
        lastFocus = trigger || document.activeElement;
        bd.hidden = false;
        dg.hidden = false;
        opened = true;
        document.documentElement.style.overflow = 'hidden';
        var first = dg.querySelector('.wh-opt.is-active') || dg.querySelector('.wh-opt');
        if (first) { try { first.focus({ preventScroll: true }); } catch (e) { first.focus(); } }
    }

    function close() {
        var bd = backdrop(), dg = dialog();
        if (!bd || !dg) return;
        bd.hidden = true;
        dg.hidden = true;
        opened = false;
        document.documentElement.style.overflow = '';
        if (lastFocus && typeof lastFocus.focus === 'function') {
            try { lastFocus.focus({ preventScroll: true }); } catch (e) { /* noop */ }
        }
    }

    // --- Open triggers -------------------------------------------------------
    document.addEventListener('click', function (ev) {
        var t = ev.target.closest('[data-wh-modal-open]');
        if (t) { ev.preventDefault(); open(t); return; }

        // Close affordances
        if (ev.target.closest('[data-wh-modal-close]')) { ev.preventDefault(); close(); return; }
        var bd = ev.target.closest('[data-wh-modal-backdrop]');
        if (bd && opened) { ev.preventDefault(); close(); return; }
    });

    // --- Option pick ---------------------------------------------------------
    document.addEventListener('click', function (ev) {
        var opt = ev.target.closest('.wh-opt');
        if (!opt) return;
        var dg = dialog();
        if (!dg || dg.hidden) return;
        ev.preventDefault();

        var val = opt.getAttribute('data-wh-value');
        var inp = input();
        var frm = form();
        if (!frm || !inp) return;

        // If it's already the active one, just dismiss.
        if (opt.classList.contains('is-active')) { close(); return; }

        inp.value = val;

        // Optimistic visual feedback
        dg.querySelectorAll('.wh-opt').forEach(function (o) {
            o.classList.remove('is-active');
            o.setAttribute('aria-selected', 'false');
        });
        opt.classList.add('is-active');
        opt.setAttribute('aria-selected', 'true');

        frm.submit();
    });

    // --- Keyboard ------------------------------------------------------------
    document.addEventListener('keydown', function (ev) {
        if (!opened) return;
        if (ev.key === 'Escape' || ev.key === 'Esc') { ev.preventDefault(); close(); }
    });

    // Safety: if the viewport grows while the sheet is open, close it.
    var mq = window.matchMedia('(min-width: 768px)');
    function onGrow(e) { if (e.matches && opened) close(); }
    if (typeof mq.addEventListener === 'function') mq.addEventListener('change', onGrow);
    else if (typeof mq.addListener === 'function') mq.addListener(onGrow);

    window.SFWhModal = { open: open, close: close };
})();
