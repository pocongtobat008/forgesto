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
    var r = btn.getBoundingClientRect();
    pop.hidden = false;
    var left = Math.min(r.left + window.scrollX, window.scrollX + document.documentElement.clientWidth - 300);
    pop.style.left = Math.max(8, left) + 'px';
    pop.style.top = (r.bottom + window.scrollY + 6) + 'px';
    loadValues();
    q.focus();
  }
  function close() { pop.hidden = true; current = null; }
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
