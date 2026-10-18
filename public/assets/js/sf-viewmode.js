/* ==========================================================================
   SF VIEW MODE — kontrol "Otomatis / Kartu / Tabel" untuk semua tabel .data
   • Menyimpan pilihan di localStorage (berlaku lintas halaman & lintas tab)
   • Memberi peran pada tiap sel (id / judul / meta / status / label-nilai /
     aksi) supaya CSS kartu tahu harus meletakkannya di mana
   • Tabel yang dirender belakangan (AJAX) ikut diproses lewat MutationObserver
   ========================================================================== */
(function () {
  'use strict';

  var KEY = 'sf:viewmode';
  var MODES = ['auto', 'cards', 'table'];
  var root = document.documentElement;

  var ICON = {
    auto: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="2.5" y="4" width="19" height="12.5" rx="2"/><path d="M8 20.5h8M12 16.5v4"/></svg>',
    cards: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="3" width="7.5" height="7.5" rx="1.8"/><rect x="13.5" y="3" width="7.5" height="7.5" rx="1.8"/><rect x="3" y="13.5" width="7.5" height="7.5" rx="1.8"/><rect x="13.5" y="13.5" width="7.5" height="7.5" rx="1.8"/></svg>',
    table: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="2.5" y="4" width="19" height="16" rx="2"/><path d="M2.5 9.5h19M2.5 15h19M9 9.5V20"/></svg>'
  };

  var LABEL = { auto: 'Otomatis', cards: 'Kartu', table: 'Tabel' };

  /* ---------------------------------------------------------------- state */
  function readMode() {
    try {
      var q = new URLSearchParams(location.search).get('view');
      if (MODES.indexOf(q) > -1) return q;
      var v = localStorage.getItem(KEY);
      return MODES.indexOf(v) > -1 ? v : 'auto';
    } catch (e) { return 'auto'; }
  }
  function writeMode(mode) {
    try { localStorage.setItem(KEY, mode); } catch (e) {}
  }
  function applyMode(mode) {
    if (mode === 'auto') root.removeAttribute('data-view');
    else root.setAttribute('data-view', mode);
    root.setAttribute('data-sf-view', mode);
    /* tabel lebar (mis. #items-table min-width:1250px) harus dilepas saat mode kartu,
       kalau tidak grid kartu melebar keluar layar */
    var tabs = document.querySelectorAll('table.data');
    for (var k = 0; k < tabs.length; k++) {
      if (mode === 'cards') tabs[k].style.setProperty('min-width', '0', 'important');
      else tabs[k].style.removeProperty('min-width');
    }
    /* mode tabel di layar sempit: beri scroll horizontal sendiri di wadahnya */
    var wraps = document.querySelectorAll('.table-wrap');
    for (var w = 0; w < wraps.length; w++) {
      if (mode === 'table' && window.innerWidth <= 767) {
        wraps[w].style.setProperty('overflow-x', 'auto', 'important');
        wraps[w].style.setProperty('-webkit-overflow-scrolling', 'touch');
      } else {
        wraps[w].style.removeProperty('overflow-x');
        wraps[w].style.removeProperty('-webkit-overflow-scrolling');
      }
    }
    var btns = document.querySelectorAll('.sf-vm__btn');
    for (var i = 0; i < btns.length; i++) {
      btns[i].setAttribute('aria-pressed', String(btns[i].getAttribute('data-mode') === mode));
    }
  }

  /* ----------------------------------------------------------- role rules */
  var RE_ID = /^(kode|code|id|username|nrp|nip|nik|sku|barcode|serial|no|no\.)$/i;
  var RE_TITLE = /(nama|name|judul|title|item|barang|produk|file|aksi|activity|group|grup|device|perangkat|lokasi|location|gudang|warehouse)/i;
  var RE_META = /^(kategori|category|tipe|type|satuan|unit|role|kota|city|merek|brand|divisi|departemen)$/i;
  var RE_HIDE = /^(#|no\.?|no urut|urut)$/i;
  var META_ORDER = { 'kategori': 5, 'category': 5, 'tipe': 6, 'type': 6, 'role': 6, 'kota': 6, 'city': 6, 'satuan': 7, 'unit': 7 };

  function norm(s) { return String(s == null ? '' : s).replace(/\s+/g, ' ').trim(); }

  function roleOf(td, label, state) {
    var txt = norm(td.textContent);
    var hasCb = td.querySelector('input[type="checkbox"]');
    if (hasCb && !txt && !td.querySelector('a.btn,button,.btn')) return 'sel';
    if (td.querySelector('a.btn,button,.btn')) return 'act';
    if (RE_HIDE.test(label)) return 'hide';
    if (td.querySelector('.badge,.chip,.pill,.tag') && state.status < 2) { state.status++; return 'status'; }
    if (RE_ID.test(label) && !state.id) { state.id = 1; return 'id'; }
    if (RE_TITLE.test(label) && !state.title) { state.title = 1; return 'title'; }
    if (RE_META.test(label)) return 'meta';
    return 'kv';
  }

  function decorate(table) {
    if (!table.tHead || !table.tHead.rows.length || !table.tBodies.length) return;
    var ths = table.tHead.rows[0].cells;
    var labels = [];
    for (var i = 0; i < ths.length; i++) {
      var th = ths[i];
      labels.push(norm(th.getAttribute('data-label') || th.getAttribute('data-col') || th.textContent));
    }
    var rows = table.tBodies[0].rows;
    for (var r = 0; r < rows.length; r++) {
      var tr = rows[r];
      if (tr.cells.length === 1 && tr.cells[0].hasAttribute('colspan')) continue;
      var state = { status: 0, id: 0, title: 0 };
      for (var c = 0; c < tr.cells.length; c++) {
        var td = tr.cells[c];
        var label = norm(td.getAttribute('data-label') || labels[c] || '');
        if (!td.getAttribute('data-label') && label) td.setAttribute('data-label', label);

        var role = roleOf(td, label, state);
        td.setAttribute('data-sf-role', role);
        if (role === 'meta') td.style.setProperty('--sf-ord', String(META_ORDER[label.toLowerCase()] || 5));

        var txt = norm(td.textContent);
        var blank = !txt || txt === '—' || txt === '-' || txt === '–';
        if (blank && role !== 'act' && role !== 'sel') td.setAttribute('data-empty', '1');
        else td.removeAttribute('data-empty');

        /* bungkus isi teks supaya bisa ditata (label/nilai, judul, chip) */
        if (role !== 'act' && role !== 'sel' && role !== 'hide' &&
            !td.querySelector('input,select,textarea,button,a,form,label,.sf-vm-val')) {
          var span = document.createElement('span');
          span.className = 'sf-vm-val';
          while (td.firstChild) span.appendChild(td.firstChild);
          td.appendChild(span);
        }
      }

      /* Buang meta yang sudah tercetak di subjudul sel judul (hindari dobel) */
      var titleCell = tr.querySelector('td[data-sf-role="title"]');
      var sub = titleCell ? titleCell.querySelector('.cell-sub') : null;
      var subTxt = sub ? norm(sub.textContent).toLowerCase() : '';
      if (subTxt) {
        var metas = tr.querySelectorAll('td[data-sf-role="meta"]');
        for (var m = 0; m < metas.length; m++) {
          var mv = norm(metas[m].textContent).toLowerCase();
          if (mv && subTxt.indexOf(mv) !== -1) metas[m].setAttribute('data-empty', '1');
        }
      }
    }
    table.setAttribute('data-sf-vm', 'on');
  }

  function scan() {
    var tables = document.querySelectorAll('table.data:not([data-sf-vm])');
    for (var i = 0; i < tables.length; i++) {
      var t = tables[i];
      if (t.closest('.modal, dialog, .offcanvas, .col-mgr-panel, [data-sf-vm="off"]')) {
        t.setAttribute('data-sf-vm', 'off');
        continue;
      }
      decorate(t);
    }

    /* pill hanya berguna kalau halaman punya tabel */
    if (ctrl) {
      var any = document.querySelector('table.data[data-sf-vm="on"]');
      ctrl.style.display = any ? '' : 'none';
      var sp = document.getElementById('sf-vm-spacer');
      if (sp) sp.style.display = any ? '' : 'none';
    }
  }

  /* ------------------------------------------------------------- the pill */
  var ctrl = null;
  var handleEl = null;

  function buildControl() {
    ctrl = document.createElement('div');
    ctrl.className = 'sf-vm';
    ctrl.setAttribute('role', 'region');
    ctrl.setAttribute('aria-label', 'Tampilan tabel');
    ctrl.id = 'sf-vm';

    var lbl = document.createElement('span');
    lbl.className = 'sf-vm__lbl';
    lbl.textContent = 'Tampilan';
    ctrl.appendChild(lbl);

    MODES.forEach(function (m) {
      var b = document.createElement('button');
      b.type = 'button';
      b.className = 'sf-vm__btn';
      b.setAttribute('data-mode', m);
      b.setAttribute('title', 'Tampilan ' + LABEL[m]);
      b.setAttribute('aria-label', 'Tampilan ' + LABEL[m]);
      b.innerHTML = ICON[m] + '<span>' + LABEL[m] + '</span>';
      b.addEventListener('click', function () {
        var mode = b.getAttribute('data-mode');
        writeMode(mode);
        applyMode(mode);
        place();
      });
      ctrl.appendChild(b);
    });

    /* ruang ekstra di dasar halaman supaya kartu terakhir tidak tertutup pill */
    if (!document.getElementById('sf-vm-spacer')) {
      var sp = document.createElement('div');
      sp.id = 'sf-vm-spacer';
      sp.className = 'sf-vm__spacer';
      sp.setAttribute('aria-hidden', 'true');
      document.body.appendChild(sp);
    }

    /* --------------------------------------------------------- sembunyikan
       Tombol chevron menyingkirkan pil sepenuhnya; yang tersisa hanya tombol
       bulat kecil di sudut untuk memunculkannya kembali. Pilihan disimpan
       supaya konsisten antar halaman dan saat reload. */
    var collapseBtn = document.createElement('button');
    collapseBtn.type = 'button';
    collapseBtn.className = 'sf-vm__collapse';
    collapseBtn.title = 'Sembunyikan';
    collapseBtn.setAttribute('aria-label', 'Sembunyikan pemilih tampilan');
    collapseBtn.innerHTML = '<svg viewBox="0 0 16 16" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3.5 6.5 8 11l4.5-4.5"/></svg>';
    ctrl.appendChild(collapseBtn);

    var handle = document.createElement('button');
    handle.type = 'button';
    handle.className = 'sf-vm-handle';
    handle.hidden = true;
    handle.title = 'Tampilkan pemilih tampilan';
    handle.setAttribute('aria-label', 'Tampilkan pemilih tampilan');
    handle.setAttribute('aria-expanded', 'true');
    handle.setAttribute('aria-controls', 'sf-vm');
    handle.innerHTML = '<svg viewBox="0 0 16 16" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" aria-hidden="true"><path d="M2.5 5h7M12.5 5h1M2.5 11h1M6.5 11h7"/><circle cx="11" cy="5" r="1.6"/><circle cx="5" cy="11" r="1.6"/></svg>';

    handleEl = handle;

    function setCollapsed(v, moveFocus) {
      ctrl.classList.toggle('is-collapsed', v);
      if (v) { ctrl.setAttribute('inert', ''); ctrl.classList.remove('is-tucked'); }
      else { ctrl.removeAttribute('inert'); ctrl.classList.remove('is-tucked'); }
      handle.hidden = !v;
      handle.setAttribute('aria-expanded', v ? 'false' : 'true');
      try { localStorage.setItem('sf-vm-collapsed', v ? '1' : '0'); } catch (e) {}
      if (moveFocus) {
        if (v) handle.focus();
        else (ctrl.querySelector('.sf-vm__btn[aria-pressed="true"]') ||
              ctrl.querySelector('.sf-vm__btn') || ctrl).focus();
      }
    }

    collapseBtn.addEventListener('click', function () { setCollapsed(true, true); });
    handle.addEventListener('click', function () { setCollapsed(false, true); });

    document.body.appendChild(ctrl);
    document.body.appendChild(handle);
    document.body.classList.add('sf-vm-ready');

    try { if (localStorage.getItem('sf-vm-collapsed') === '1') setCollapsed(true, false); } catch (e) {}
  }

  function place() {
    if (!ctrl) return;
    var off = 16;
    var bars = document.querySelectorAll('.bottom-action-bar');
    for (var i = 0; i < bars.length; i++) {
      var bar = bars[i];
      var cs = getComputedStyle(bar);
      if (cs.display === 'none' || cs.position !== 'fixed') continue;
      var r = bar.getBoundingClientRect();
      if (r.height > 0 && r.top < window.innerHeight) {
        off = Math.round(window.innerHeight - r.top) + 10;
        break;
      }
    }
    ctrl.style.bottom = off + 'px';
    /* tombol tampilkan ikut naik kalau ada bottom action bar di mobile */
    if (handleEl) handleEl.style.bottom = 'calc(' + off + 'px + env(safe-area-inset-bottom, 0px))';
  }

  /* ==========================================================================
     TATA LETAK — baris kartu tanpa slot kosong
     Masalah: grid 3/4/5 kolom yang jumlah kartunya tidak habis dibagi akan
     menyisakan lubang di kanan (3 kartu di grid 4 kolom, 5 kartu di grid 3
     kolom, dst). Solusi: baris kartu jadi flex-wrap dengan lebar dasar 1/c;
     sisa di baris terakhir ikut melebar mengisi ruang, jadi tidak ada lubang.
     ========================================================================== */
  var FIT_SEL = '.memo-stats, .memo-sample, .grid-2, .grid-3, .grid-4, .grid-5';
  var fitRO = null;

  function isCardRow(el) {
    if (el.getAttribute('data-sf-fit') === 'off') return false;
    if (el.matches('.memo-stats, .memo-sample')) return true;
    var kids = el.children;
    if (kids.length < 2) return false;
    for (var i = 0; i < kids.length; i++) {
      var k = kids[i];
      var cls = (k.className || '').toString();
      if (!/(^|\s)stat(\s|$)/.test(cls)) return false;
      if (k.style && (k.style.gridColumn || k.style.gridRow)) return false;
    }
    return true;
  }

  function fitRow(el) {
    var kids = el.children;
    var n = kids.length;
    if (!n) return;
    var W = el.clientWidth;
    if (!W) return;
    /* tidak ada perubahan ukuran / jumlah kartu -> tidak perlu dihitung ulang */
    if (el.__fitW === W && el.__fitN === n && el.getAttribute('data-sf-fit') === 'on') return;

    var cs = getComputedStyle(el);
    var gap = parseFloat(cs.columnGap) || parseFloat(cs.gap) || 14;
    var min = parseFloat(cs.getPropertyValue('--fit-min')) || (el.matches('.memo-sample') ? 320 : 215);
    var tol = 0.95;
    var c = 0, i, best = -1;

    /* pilih jumlah kolom: utamakan yang habis dibagi, lalu yang sisanya >= 2
       (supaya tidak ada satu kartu yang melebar sendirian di baris terakhir) */
    for (i = Math.min(n, 6); i >= 2; i--) {
      if ((W - gap * (i - 1)) / i < min * tol) continue;
      var rest = n % i;
      var score = rest === 0 ? 100 + i : (rest >= 2 ? 50 + i : 10 + i);
      if (score > best) { best = score; c = i; }
    }
    if (!c) c = 1;

    el.setAttribute('data-sf-fit', 'on');
    /* pakai !important: sebagian halaman memaksa display:grid !important */
    el.style.setProperty('display', 'flex', 'important');
    el.style.setProperty('flex-wrap', 'wrap', 'important');
    el.style.setProperty('align-items', 'stretch', 'important');
    el.style.setProperty('grid-template-columns', 'none', 'important');

    /* -0.6px: hindari pembulatan sub-piksel yang bikin kartu ke-4 turun baris */
    var basis = c > 1
      ? 'calc(' + (100 / c).toFixed(4) + '% - ' + ((gap * (c - 1)) / c + 0.6).toFixed(2) + 'px)'
      : '100%';

    for (i = 0; i < n; i++) {
      var k = kids[i];
      k.style.setProperty('flex', '1 1 ' + basis, 'important');
      k.style.setProperty('min-width', '0', 'important');
      k.style.setProperty('max-width', '100%', 'important');
      k.style.setProperty('width', 'auto', 'important');
    }

    el.__fitW = W;
    el.__fitN = n;

    if (!fitRO && window.ResizeObserver) fitRO = new ResizeObserver(function () { scheduleFit(); });
    if (fitRO && !el.__fitObs) { fitRO.observe(el); el.__fitObs = true; }
  }

  function fitAll() {
    var rows = document.querySelectorAll(FIT_SEL);
    for (var i = 0; i < rows.length; i++) {
      if (isCardRow(rows[i])) fitRow(rows[i]);
    }
  }

  var fitTimer = null;
  function scheduleFit() {
    clearTimeout(fitTimer);
    fitTimer = setTimeout(fitAll, 90);
  }

  /* lebar kontainer sering berubah setelah font/gambar/sidebar selesai dimuat */
  function watchFit() {
    fitAll();
    setTimeout(fitAll, 250);
    setTimeout(fitAll, 900);
    setTimeout(fitAll, 2000);
  }

  /* ------------------------------------------------------- auto-hide saat gulir
     Pola yang sama dengan toolbar browser mobile:
       • menggulir ke bawah  -> pil menyingkir (tidak menutupi grafik/kartu)
       • menggulir ke atas   -> pil muncul lagi
       • dekat atas halaman  -> selalu tampil
       • dekat dasar halaman -> selalu tampil (di sana ada ruang spacer)
       • fokus keyboard masuk ke pil -> tampil
     Tidak ada timer "muncul sendiri": kalau pengguna berhenti membaca di tengah
     halaman, pil tetap tersembunyi supaya konten di sudut kanan-bawah bersih.
     Cukup geser ke atas sedikit untuk memunculkannya kembali. */
  var HIDE_AFTER = 120;   /* px gulir minimum sebelum boleh menyingkir */
  var DELTA = 8;          /* px untuk menentukan arah gulir */
  var EDGE = 60;          /* jarak dari dasar halaman yang dianggap "di dasar" */

  function initAutoHide() {
    if (!ctrl || ctrl.__autoHide) return;
    ctrl.__autoHide = true;

    var tick = 0;   /* id timer throttle */

    function show() { if (!ctrl.classList.contains('is-collapsed')) ctrl.classList.remove('is-tucked'); }
    function hide() { if (!ctrl.classList.contains('is-collapsed')) ctrl.classList.add('is-tucked'); }

    /* Posisi terakhir disimpan di elemen yang menggulir (html di desktop,
       body di layout mobile). Disemai saat init supaya lompatan gulir pertama
       -- PageDown, anchor, atau restore posisi -- tetap terbaca sebagai arah. */
    function seed(el) { if (el) el.__sfVmY = el.scrollTop || 0; }
    seed(document.scrollingElement || document.documentElement);
    seed(document.body);

    /* throttle pakai setTimeout, bukan requestAnimationFrame: rAF berhenti
       total saat tab tidak terlihat dan itu bisa mengunci antrean selamanya */
    function onScroll(e) {
      var t = (e && e.target && e.target.nodeType === 1)
        ? e.target
        : (document.scrollingElement || document.documentElement);
      if (tick) return;
      tick = setTimeout(function () {
        tick = 0;
        var y = t.scrollTop || 0;
        var prev = (typeof t.__sfVmY === 'number') ? t.__sfVmY : y;
        var d = y - prev;
        var max = (t.scrollHeight || 0) - (t.clientHeight || 0);
        t.__sfVmY = y;
        if (y <= HIDE_AFTER) show();
        else if (max > 0 && y >= max - EDGE) show();
        else if (d > DELTA) hide();
        else if (d < -DELTA) show();
      }, 60);
    }

    document.addEventListener('scroll', onScroll, { passive: true, capture: true });
    window.addEventListener('scroll', onScroll, { passive: true });
    window.addEventListener('resize', show, { passive: true });

    /* keyboard: begitu fokus masuk, pil wajib kelihatan */
    ctrl.addEventListener('focusin', show);
    ctrl.addEventListener('keydown', function (e) { if (e.key === 'Escape') show(); });
  }

  /* --------------------------------------------------------------- events */
  var t = null;
  function schedule() {
    clearTimeout(t);
    t = setTimeout(function () { scan(); place(); fitAll(); }, 180);
  }

  function boot() {
    applyMode(readMode());
    scan();
    fitAll();
    buildControl();
    applyMode(readMode());
    place();
    initAutoHide();
    window.addEventListener('resize', function () { schedule(); scheduleFit(); }, { passive: true });
    window.addEventListener('scroll', function () { place(); }, { passive: true });
    window.addEventListener('storage', function (e) {
      if (e.key === KEY) applyMode(readMode());
    });
    if (window.MutationObserver) {
      new MutationObserver(function (muts) {
        for (var i = 0; i < muts.length; i++) {
          if (muts[i].addedNodes && muts[i].addedNodes.length) { schedule(); return; }
        }
      }).observe(document.body, { childList: true, subtree: true });
    }
    /* tabel yang di-render setelah load (mis. halaman progress) */
    document.addEventListener('sf:table-rendered', schedule);
    /* ukur ulang baris kartu setelah semua aset selesai dimuat */
    if (document.readyState === 'complete') watchFit();
    else window.addEventListener('load', watchFit);
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot);
  else boot();
})();
