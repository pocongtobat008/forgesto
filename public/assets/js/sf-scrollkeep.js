/* sf-scrollkeep.js — jaga posisi scroll saat popup/modal dibuka-tutup.
   Latar masalah: <body>{overflow:hidden} memaksa browser me-reset scrollTop ke 0,
   jadi setelah popup ditutup halaman melompat ke header. Kunci utama sekarang
   dipindah ke <html> lewat CSS (html:has(body.*-lock)), berkas ini hanya jaring
   pengaman untuk modul yang masih menyetel overflow di body secara inline. */
(function () {
    'use strict';
    var LOCKS = ['no-scroll', 'qe-lock', 'prg-lock', 'modal-open', 'sf-modal-open',
                 'col-mgr-open', 'sf-lock', 'drawer-open', 'is-locked'];
    var y = window.pageYOffset || document.documentElement.scrollTop || 0;
    var wasLocked = false;

    function isLocked() {
        if (document.documentElement.classList.contains('rd-lock')) { return true; }
        var b = document.body;
        if (!b) { return false; }
        for (var i = 0; i < LOCKS.length; i++) {
            if (b.classList.contains(LOCKS[i])) { return true; }
        }
        return b.style.overflow === 'hidden';
    }

    function scrollTopNow() {
        return window.pageYOffset || document.documentElement.scrollTop || (document.body ? document.body.scrollTop : 0) || 0;
    }

    function remember() {
        if (!isLocked()) { y = scrollTopNow(); }
    }

    function restore(target) {
        if (!target) { return; }
        var go = function () {
            window.scrollTo(0, target);
            if (document.body) { document.body.scrollTop = target; }
        };
        if (window.requestAnimationFrame) { window.requestAnimationFrame(go); } else { go(); }
        setTimeout(function () {
            if (Math.abs(scrollTopNow() - target) > 4) { go(); }
        }, 70);
    }

    function check() {
        var l = isLocked();
        if (!l && wasLocked) { restore(y); }
        wasLocked = l;
    }

    window.addEventListener('scroll', remember, { passive: true });
    document.addEventListener('scroll', remember, true);
    window.addEventListener('pageshow', remember);
    window.addEventListener('resize', remember);

    if (window.MutationObserver) {
        var mo = new MutationObserver(function () { setTimeout(check, 0); });
        mo.observe(document.documentElement, { attributes: true, attributeFilter: ['class', 'style'] });
        if (document.body) {
            mo.observe(document.body, { attributes: true, attributeFilter: ['class', 'style'] });
        }
    }
    setInterval(check, 400);
    remember();
})();
