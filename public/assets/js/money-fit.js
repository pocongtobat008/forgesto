/* ==========================================================================
   money-fit.js — pastikan angka & nominal (Rp ...) TIDAK PERNAH terpotong.
   Cara kerja: kalau teks lebih lebar dari kartu, font-size diturunkan
   bertahap sampai muat (min 10px). Kalau masih tidak muat, teks dibungkus
   (white-space: normal) — data tetap utuh, tidak ada elipsis.
   ========================================================================== */
(function () {
    'use strict';

    var SELECTOR = [
        '.stat .stat-value',
        '.stat-value',
        '.kpi-value',
        '.kpi-amt',
        '.cc-value',
        '.stat-card .value',
        '.group-card-amt',
        '.sf-hero-stat-value',
        '.sf-amt',
        '.sf-money',
        '.money',
        '.amt'
    ].join(',');

    var MIN_PX = 10;
    var STEP = 0.5;

    function textWidth(el) {
        var r = document.createRange();
        r.selectNodeContents(el);
        var w = r.getBoundingClientRect().width;
        return w;
    }

    function availWidth(el) {
        var cs = window.getComputedStyle(el);
        var padL = parseFloat(cs.paddingLeft) || 0;
        var padR = parseFloat(cs.paddingRight) || 0;
        var box = el.clientWidth - padL - padR;
        if (box > 0) {
            return box;
        }
        var p = el.parentElement;
        if (p) {
            var pcs = window.getComputedStyle(p);
            var b = p.clientWidth
                - (parseFloat(pcs.paddingLeft) || 0)
                - (parseFloat(pcs.paddingRight) || 0);
            if (b > 0) {
                return b;
            }
        }
        return 0;
    }

    function fit(el) {
        if (!el || el.nodeType !== 1) {
            return;
        }
        var txt = (el.textContent || '').trim();
        if (txt === '') {
            return;
        }

        // Ukuran font "seharusnya" menurut CSS (media query aware): reset dulu.
        el.style.removeProperty('font-size');
        el.style.removeProperty('letter-spacing');
        el.style.removeProperty('white-space');
        if (el.dataset.sfWrap === '1') {
            delete el.dataset.sfWrap;
        }
        // Selalu ukur dalam SATU baris: kalau teks sedang terbungkus,
        // Range hanya mengukur baris terpanjang sehingga loop tidak jalan.
        el.style.setProperty('white-space', 'nowrap', 'important');

        var cs = window.getComputedStyle(el);
        var base = parseFloat(cs.fontSize) || 16;

        var avail = availWidth(el);
        if (avail <= 0) {
            return;
        }

        // Judul lengkap untuk hover (angka panjang tetap bisa dibaca utuh).
        if (!el.getAttribute('title')) {
            el.setAttribute('title', txt);
        }

        var size = base;
        var guard = 0;
        while (textWidth(el) > avail - 1 && size > MIN_PX && guard < 200) {
            size -= STEP;
            el.style.setProperty('font-size', size + 'px', 'important');
            guard++;
        }

        if (textWidth(el) > avail - 1) {
            // Sudah minimum & masih lebih lebar → bungkus, jangan potong.
            el.style.setProperty('font-size', MIN_PX + 'px', 'important');
            el.style.setProperty('white-space', 'normal', 'important');
            el.dataset.sfWrap = '1';
        }
    }

    function runAll(root) {
        var scope = root && root.querySelectorAll ? root : document;
        var nodes = scope.querySelectorAll(SELECTOR);
        for (var i = 0; i < nodes.length; i++) {
            fit(nodes[i]);
        }
    }

    var pending = false;
    function schedule(root) {
        if (pending) {
            return;
        }
        pending = true;
        window.requestAnimationFrame(function () {
            pending = false;
            runAll(root);
        });
    }

    function boot() {
        runAll(document);

        // Re-fit saat ukuran jendela berubah.
        var t = null;
        window.addEventListener('resize', function () {
            if (t) {
                window.clearTimeout(t);
            }
            t = window.setTimeout(function () {
                runAll(document);
            }, 120);
        });

        // Re-fit saat konten berubah (tabel/angka di-refresh via fetch).
        if (window.MutationObserver) {
            var mo = new MutationObserver(function (muts) {
                for (var i = 0; i < muts.length; i++) {
                    if (muts[i].type === 'characterData' || muts[i].addedNodes.length) {
                        schedule(document);
                        return;
                    }
                }
            });
            mo.observe(document.body, {
                childList: true,
                subtree: true,
                characterData: true
            });
        }

        // Re-fit setelah font selesai dimuat (Google Sans Flex).
        if (document.fonts && document.fonts.ready) {
            document.fonts.ready.then(function () {
                runAll(document);
            });
        }

        // Beberapa pass awal untuk layout yang bergeser (animasi kartu).
        window.setTimeout(function () { runAll(document); }, 250);
        window.setTimeout(function () { runAll(document); }, 900);
        // Setelah sf-viewmode.js menata ulang baris kartu (flex) & animasi masuk.
        window.setTimeout(function () { runAll(document); }, 1400);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }

    window.sfFitMoney = runAll;
})();
