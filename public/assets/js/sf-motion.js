/* ==========================================================================
   StockForge · SF-MOTION — Aurora Glass motion engine
   --------------------------------------------------------------------------
   Tanggung jawab (kecil, tanpa dependensi):
     1. Pastikan layer aurora ada (kalau layout belum merender).
     2. Elevasi topbar saat scroll (.is-scrolled) — satu tanda, bukan animasi.
     3. Count-up angka statistik (hormat pada prefers-reduced-motion).
     4. Indeks stagger otomatis untuk .sf-reveal (maks 8 langkah = 336ms).
     5. Tandai tabel panjang agar blur per-sel dimatikan (performa mobile).
   Semua efek di-skip bila user memilih reduced motion.
   ========================================================================== */
(function () {
  'use strict';

  var root = document.documentElement;
  var reduceMQ = window.matchMedia ? window.matchMedia('(prefers-reduced-motion: reduce)') : null;
  function reduced() { return !!(reduceMQ && reduceMQ.matches); }

  /* ---------------------------------------------------------------- 1. aurora */
  function ensureAurora() {
    if (document.querySelector('.sf-aurora')) return;
    var layer = document.createElement('div');
    layer.className = 'sf-aurora';
    layer.setAttribute('aria-hidden', 'true');
    layer.innerHTML =
      '<span class="sf-aurora-blob b1"></span><span class="sf-aurora-blob b2"></span>' +
      '<span class="sf-aurora-blob b3"></span><span class="sf-aurora-blob b4"></span>';
    document.body.insertBefore(layer, document.body.firstChild);
  }

  /* ------------------------------------------------------- 2. topbar elevation */
  function topbarElevation() {
    var bar = document.querySelector('.topbar');
    if (!bar) return;
    var ticking = false;
    function apply() {
      var y = window.pageYOffset || document.documentElement.scrollTop || 0;
      bar.classList.toggle('is-scrolled', y > 8);
      ticking = false;
    }
    function onScroll() {
      if (ticking) return;
      ticking = true;
      (window.requestAnimationFrame || window.setTimeout)(apply, 16);
    }
    window.addEventListener('scroll', onScroll, { passive: true });
    apply();
  }

  /* ---------------------------------------------------------- 3. count-up */
  var NUM_RE = /^(\d[\d.,]*)(.*)$/;
  function animateNumber(el) {
    if (el.dataset.sfCounted === '1') return;
    var raw = (el.textContent || '').trim();
    var m = NUM_RE.exec(raw);
    if (!m) return;
    var digits = m[1].replace(/[.,]/g, '');
    var target = parseInt(digits, 10);
    if (!isFinite(target) || target <= 0 || digits.length > 9) { el.dataset.sfCounted = '1'; return; }
    el.dataset.sfCounted = '1';

    var suffix = m[2] || '';
    var start = null;
    var DUR = 700;

    function fmt(n) {
      try { return n.toLocaleString('id-ID'); } catch (e) { return String(n); }
    }
    function step(ts) {
      if (start === null) start = ts;
      var p = Math.min(1, (ts - start) / DUR);
      // ease-out expo: tiba cepat lalu mengendap
      var eased = p === 1 ? 1 : 1 - Math.pow(2, -10 * p);
      el.textContent = fmt(Math.round(target * eased)) + suffix;
      if (p < 1) window.requestAnimationFrame(step);
      else el.textContent = fmt(target) + suffix;
    }
    if (reduced()) { el.textContent = fmt(target) + suffix; return; }
    window.requestAnimationFrame(step);
  }

  function countUps(scope) {
    var els = (scope || document).querySelectorAll(
      '.stat-value, .sf-hero-stat-value, [data-sf-count]'
    );
    for (var i = 0; i < els.length; i++) animateNumber(els[i]);
  }

  /* ------------------------------------------------------------ 4. stagger */
  function assignStagger() {
    var groups = {};
    var items = document.querySelectorAll('.sf-reveal:not([style*="--i"])');
    for (var i = 0; i < items.length; i++) {
      var el = items[i];
      var key = el.parentNode ? (el.parentNode.getAttribute('data-sf-stagger') || 'p' + indexOfParent(el.parentNode)) : 'root';
      groups[key] = groups[key] || 0;
      var n = groups[key];
      if (n < 8) el.style.setProperty('--i', String(n));
      groups[key] = n + 1;
    }
  }
  var parents = [];
  function indexOfParent(p) {
    var idx = parents.indexOf(p);
    if (idx === -1) { parents.push(p); idx = parents.length - 1; }
    return idx;
  }

  /* ---------------------------------------- 4b. jaring aman reveal */
  // sf-ui.js hanya menandai anak dari .card/.grid/.kpi-grid/.group-grid.
  // Elemen .sf-reveal lain (mis. hero/panel mandiri) akan tertinggal di
  // opacity 0 -> pastikan apa pun yang sudah terlihat di viewport ikut tampil.
  function revealFallback() {
    var els = document.querySelectorAll('.sf-reveal:not(.is-shown)');
    var h = window.innerHeight || 800;
    for (var i = 0; i < els.length; i++) {
      var r = els[i].getBoundingClientRect();
      if (r.top < h) els[i].classList.add('is-shown');
    }
  }

  /* --------------------------------------------------- 5. tabel panjang */
  function markLongTables() {
    var wraps = document.querySelectorAll('.table-wrap');
    for (var i = 0; i < wraps.length; i++) {
      var rows = wraps[i].querySelectorAll('tbody tr');
      if (rows.length > 60) wraps[i].classList.add('sf-table-long');
    }
  }

  /* --------------------------------------------------------------- boot */
  function boot() {
    try { ensureAurora(); } catch (e) {}
    try { topbarElevation(); } catch (e) {}
    try { assignStagger(); } catch (e) {}
    try { markLongTables(); } catch (e) {}
    // count-up setelah layout stabil supaya angka tidak "melompat" saat reveal
    window.setTimeout(function () { try { countUps(document); } catch (e) {} }, 60);
    // jaring aman: beri kesempatan observer bawaan jalan dulu, lalu paksa tampil
    window.setTimeout(function () { try { revealFallback(); } catch (e) {} }, 420);
    window.setTimeout(function () { try { revealFallback(); } catch (e) {} }, 1400);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }

  // Halaman dinamis (SPA-ish partial render): panggil ulang lewat event ini.
  window.addEventListener('sf:refresh-motion', function () {
    try { countUps(document); } catch (e) {}
  });

  // Hormati perubahan preferensi motion saat aplikasi berjalan.
  if (reduceMQ && reduceMQ.addEventListener) {
    reduceMQ.addEventListener('change', function () { if (reduced()) root.classList.add('sf-reduce'); });
  }
})();
