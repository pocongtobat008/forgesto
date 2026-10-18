<?php
/**
 * Partial: popup filter kolom generik (multi-nilai, tanpa Enter).
 * Vars: $cfForm (id form GET), $cfDistinct (URL endpoint distinct), $cfLabels (key=>label),
 *       $cfDisplay (value=>label tampilan, opsional).
 */
$cfDisplay = $cfDisplay ?? [];
?>
<div class="xf-pop" id="xf-pop" role="dialog" aria-modal="false" hidden>
    <div class="xf-pop-head"><strong id="xf-title"><?= __('Filter') ?></strong></div>
    <input type="search" id="xf-q" class="xf-q" placeholder="<?= __('Cari di daftar...') ?>" autocomplete="off" aria-label="<?= __('Cari nilai di daftar filter') ?>">
    <div class="xf-list" id="xf-list" role="listbox" aria-multiselectable="true"><div class="xf-loading"><?= __('Memuat...') ?></div></div>
    <div class="xf-foot">
        <button type="button" class="btn btn-ghost btn-sm" id="xf-clear"><?= __('Hapus filter') ?></button>
        <span class="spacer"></span>
        <button type="button" class="btn btn-outline btn-sm" id="xf-cancel"><?= __('Batal') ?></button>
        <button type="button" class="btn btn-brand btn-sm" id="xf-apply"><?= __('Terapkan') ?></button>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
(function () {
  var f = document.getElementById(<?= json_encode($cfForm) ?>);
  var pop = document.getElementById('xf-pop');
  if (!f || !pop) return;
  var LABELS = <?= json_encode($cfLabels, JSON_UNESCAPED_UNICODE) ?>;
  var DISPLAY = <?= json_encode($cfDisplay, JSON_UNESCAPED_UNICODE) ?>;
  var base = <?= json_encode($cfDistinct, JSON_UNESCAPED_SLASHES) ?>;
  var current = null, picked = [], cache = {};
  var list = document.getElementById('xf-list'), q = document.getElementById('xf-q');
  var title = document.getElementById('xf-title');

  function currentFilters(key) {
    var out = [];
    f.querySelectorAll('input.col-hidden').forEach(function (i) {
      var m = i.name.match(/^col\[([a-z_]+)\]/);
      if (m && m[1] === key) out.push(i.value);
    });
    return out;
  }
  function filterQueryExcept(key) {
    var fd = new FormData(f), p = new URLSearchParams();
    fd.forEach(function (v, k) { if (k.indexOf('col[' + key + ']') !== 0 && !/^col\[/.test(k)) p.append(k, v); else if (!/^col\[/.test(k)) p.append(k, v); });
    // kolom lain tetap ikut (faceted), kolom ini dikecualikan
    var fd2 = new FormData(f), p2 = new URLSearchParams();
    fd2.forEach(function (v, k) { if (k.indexOf('col[' + key + ']') !== 0) p2.append(k, v); });
    return p2.toString();
  }
  function escHtml(s) { return String(s).replace(/[&<>"']/g, function (c) { return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]; }); }

  function render() {
    var term = q.value.trim().toLowerCase();
    var vals = cache[current] || [];
    if (vals.length === 0) { list.innerHTML = '<div class="xf-loading">' + <?= json_encode(__('Tidak ada nilai.'), JSON_UNESCAPED_UNICODE) ?> + '</div>'; return; }
    var html = '', shown = 0;
    vals.forEach(function (v) {
      if (term && v.toLowerCase().indexOf(term) === -1) return;
      shown++;
      var on = picked.indexOf(v) !== -1;
      var label = DISPLAY[v] || v;
      html += '<label class="xf-opt' + (on ? ' is-on' : '') + '"><input type="checkbox" value="' + escHtml(v) + '"' + (on ? ' checked' : '') + '><span>' + escHtml(label) + '</span></label>';
    });
    list.innerHTML = shown ? html : '<div class="xf-loading">' + <?= json_encode(__('Tidak ada yang cocok.'), JSON_UNESCAPED_UNICODE) ?> + '</div>';
    reposition();
    setTimeout(reposition, 0);
  }
  function loadValues() {
    var qs = filterQueryExcept(current);
    var key = current;
    fetch(base + '?key=' + encodeURIComponent(key) + (qs ? '&' + qs : ''), { credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (d) { if (key !== current) return; cache[key] = d.values || []; render(); })
      .catch(function () { list.innerHTML = '<div class="xf-loading">' + <?= json_encode(__('Gagal memuat daftar.'), JSON_UNESCAPED_UNICODE) ?> + '</div>'; });
  }
  function openPop(btn) {
    current = btn.getAttribute('data-xf');
    picked = currentFilters(current);
    title.textContent = 'Filter: ' + (LABELS[current] || current);
    q.value = '';
    list.innerHTML = '<div class="xf-loading">' + <?= json_encode(__('Memuat...'), JSON_UNESCAPED_UNICODE) ?> + '</div>';
    pop.hidden = false;
    placePop(btn);
    loadValues();
    q.focus();
    loadValues();
    q.focus();
  }
  function close() { pop.hidden = true; current = null; }

  // ---- Posisi popup filter: fixed + selalu menempel pada tombol ----
  var popAnchor = null;
  function placePop(btn) {
    popAnchor = btn;
    if (pop.parentElement !== document.body) { document.body.appendChild(pop); }
    var r = btn.getBoundingClientRect();
    var vw = document.documentElement.clientWidth || window.innerWidth;
    var vh = window.innerHeight || document.documentElement.clientHeight;
    var gap = 6, pad = 8;
    var w = pop.offsetWidth || 280;
    var left = r.left;
    if (left + w > vw - pad) { left = vw - pad - w; }
    if (left < pad) { left = pad; }

    // Ukur tinggi NATURAL tanpa batas. Dulu pop.offsetHeight dibaca saat
    // maxHeight lama masih terpasang, jadi tinggi terkunci pada tinggi
    // keadaan "Memuat..." dan daftar nilai terhimpit jadi 1 baris.
    var prevList = list.style.maxHeight;
    pop.style.maxHeight = 'none';
    pop.style.overflowY = 'hidden';
    list.style.maxHeight = 'none';
    var natural = pop.offsetHeight;
    var listNatural = list.offsetHeight;
    list.style.maxHeight = prevList || '';

    var chrome = Math.max(0, natural - listNatural);          // head + cari + urut + footer
    var want = Math.min(Math.max(listNatural, 132), 300);      // 3 s.d. 7 baris

    var below = (vh - pad) - (r.bottom + gap);
    var above = (r.top - gap) - pad;
    var openUp = (below < chrome + want) && (above > below);
    var room = (openUp ? above : below) - chrome;
    if (room < 132 && ((openUp ? below : above) - chrome) > room) {
      openUp = !openUp;
      room = (openUp ? above : below) - chrome;
    }
    var listH = Math.min(want, Math.max(120, room));
    list.style.maxHeight = Math.round(listH) + 'px';

    var total = chrome + listH;
    var top = openUp ? (r.top - gap - total) : (r.bottom + gap);
    if (top + total > vh - pad) { top = Math.max(pad, vh - pad - total); }
    if (top < pad) { top = pad; }
    // jaring pengaman: viewport sangat pendek → popup sendiri yang scroll
    if (total > vh - pad * 2) {
      pop.style.maxHeight = Math.round(vh - pad * 2) + 'px';
      pop.style.overflowY = 'auto';
    }
    pop.style.left = Math.round(left) + 'px';
    pop.style.top = Math.round(top) + 'px';
  }
  function reposition() {
    if (pop.hidden || !popAnchor) { return; }
    var r = popAnchor.getBoundingClientRect();
    var vh = window.innerHeight || document.documentElement.clientHeight;
    // Tombol sudah keluar layar -> tutup saja, jangan menggantung di tengah.
    if (r.bottom < 4 || r.top > vh - 4) { close(); return; }
    placePop(popAnchor);
  }
  window.addEventListener('scroll', reposition, { passive: true });
  window.addEventListener('resize', reposition);
  function apply() {
    f.querySelectorAll('input.col-hidden').forEach(function (i) { if (i.name.indexOf('col[' + current + ']') === 0) i.remove(); });
    picked.forEach(function (v) {
      var h = document.createElement('input');
      h.type = 'hidden'; h.className = 'col-hidden'; h.name = 'col[' + current + '][]'; h.value = v;
      f.appendChild(h);
    });
    f.requestSubmit ? f.requestSubmit() : f.submit();
  }
  list.addEventListener('change', function (e) {
    var v = e.target.value;
    if (e.target.checked) { if (picked.indexOf(v) === -1) picked.push(v); }
    else picked = picked.filter(function (x) { return x !== v; });
    e.target.closest('.xf-opt').classList.toggle('is-on', e.target.checked);
  });
  q.addEventListener('input', render);
  document.querySelectorAll('.xf-btn').forEach(function (b) {
    b.addEventListener('click', function (e) {
      e.stopPropagation();
      if (!pop.hidden && current === b.getAttribute('data-xf')) { close(); return; }
      openPop(b);
    });
  });
  document.getElementById('xf-apply').addEventListener('click', apply);
  document.getElementById('xf-cancel').addEventListener('click', close);
  document.getElementById('xf-clear').addEventListener('click', function () { picked = []; render(); });
  document.addEventListener('click', function (e) { if (!pop.hidden && !pop.contains(e.target)) close(); });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape') close(); });
})();
});
</script>
