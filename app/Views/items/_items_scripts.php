<?php
// Shared JavaScript logic for all item views
?>
<script>
(function() {
  function init() {
(function () {
  var f = document.getElementById('items-filter');
  var pop = document.getElementById('xf-pop');
  if (!f || !pop) return;
  var LABELS = <?= json_encode(['code'=>__('Kode'),'name'=>__('Nama'),'group'=>__('Group'),'location'=>__('Lokasi'),'device'=>__('Device'),'expected'=>__('Sistem'),'counted'=>__('Hitung'),'unit'=>__('Satuan'),'category'=>__('Kategori'),'price'=>__('Harga'),'count'=>__('Status Hitung'),'audit'=>__('Status Audit')], JSON_UNESCAPED_UNICODE) ?>;
  var NUMK = { expected:1, counted:1, price:1 };
  var sortKey = <?= json_encode($sortKey ?? null) ?>, sortDir = <?= json_encode($sortDir ?? null) ?>;
  var DISPLAY = <?= json_encode(['counted'=>__('Sudah dihitung'),'pending'=>__('Belum dihitung'),'posted'=>__('Terposting'),'audited'=>__('Sudah diaudit'),'discrepancy'=>__('Selisih'),'resolved'=>__('Selesai')], JSON_UNESCAPED_UNICODE) ?>;
  var current = null, picked = [], cache = {};
  var list = document.getElementById('xf-list'), q = document.getElementById('xf-q');
  var title = document.getElementById('xf-title');
  var base = <?= json_encode(url('items/distinct'), JSON_UNESCAPED_SLASHES) ?>;

  function currentFilters(key) {
    var out = [];
    f.querySelectorAll('input.col-hidden').forEach(function (i) {
      var m = i.name.match(/^col\[([a-z]+)\]/);
      if (m && m[1] === key) out.push(i.value);
    });
    return out;
  }
  function filterQueryExcept(key) {
    var fd = new FormData(f), p = new URLSearchParams();
    fd.forEach(function (v, k) { if (!/^col\[/.test(k) || k.indexOf('col[' + key + ']') !== 0) p.append(k, v); });
    return p.toString();
  }
  function openPop(btn) {
    current = btn.getAttribute('data-xf');
    picked = currentFilters(current);
    title.textContent = 'Filter: ' + LABELS[current];
    markSortState();
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
  function loadValues() {
    var qs = filterQueryExcept(current);
    var key = current;
    var url = base + '?key=' + encodeURIComponent(key) + (qs ? '&' + qs : '');
    fetch(url, { credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (d) {
      if (key !== current) return;
      cache[key] = d.values || [];
      render();
    }).catch(function () { list.innerHTML = '<div class="xf-loading">' + <?= json_encode(__('Gagal memuat daftar.'), JSON_UNESCAPED_UNICODE) ?> + '</div>'; });
  }
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
      html += '<label class="xf-opt' + (on ? ' is-on' : '') + '"><input type="checkbox" value="' + escAttr(v) + '"' + (on ? ' checked' : '') + '><span>' + escHtml(label) + '</span></label>';
    });
    list.innerHTML = shown ? html : '<div class="xf-loading">' + <?= json_encode(__('Tidak ada yang cocok.'), JSON_UNESCAPED_UNICODE) ?> + '</div>';
  }
  function escHtml(s) { return String(s).replace(/[&<>"']/g, function (c) { return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]; }); }
  function escAttr(s) { return escHtml(s); }
  list.addEventListener('change', function (e) {
    var v = e.target.value;
    if (e.target.checked) { if (picked.indexOf(v) === -1) picked.push(v); }
    else picked = picked.filter(function (x) { return x !== v; });
    e.target.closest('.xf-opt').classList.toggle('is-on', e.target.checked);
  });
  q.addEventListener('input', render);
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
  function applySort(dir) {
    var old = f.querySelectorAll('input.sort-hidden');
    old.forEach(function (i) { i.remove(); });
    var k = document.createElement('input'); k.type = 'hidden'; k.className = 'sort-hidden'; k.name = 'sort'; k.value = current;
    var d = document.createElement('input'); d.type = 'hidden'; d.className = 'sort-hidden'; d.name = 'dir'; d.value = dir;
    f.appendChild(k); f.appendChild(d);
    f.requestSubmit ? f.requestSubmit() : f.submit();
  }
  var xfSortAsc = document.getElementById('xf-sort-asc');
  var xfSortDesc = document.getElementById('xf-sort-desc');
  if (xfSortAsc) xfSortAsc.addEventListener('click', function () { applySort('asc'); });
  if (xfSortDesc) xfSortDesc.addEventListener('click', function () { applySort('desc'); });
  function markSortState() {
    document.querySelectorAll('.xf-sort-btn').forEach(function (b) {
      var on = current && sortKey === current && sortDir && b.dataset.dir === (sortDir === 'DESC' ? 'desc' : 'asc');
      b.classList.toggle('is-on', !!on);
    });
  }
  document.querySelectorAll('.xf-btn').forEach(function (b) {
    b.addEventListener('click', function (e) {
      e.stopPropagation();
      if (!pop.hidden && current === b.getAttribute('data-xf')) { close(); return; }
      openPop(b);
    });
  });
  var xfApply = document.getElementById('xf-apply');
  var xfCancel = document.getElementById('xf-cancel');
  if (xfApply) xfApply.addEventListener('click', apply);
  if (xfCancel) xfCancel.addEventListener('click', close);
  document.addEventListener('click', function (e) { if (!pop.hidden && !pop.contains(e.target)) close(); });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape') close(); });

  var s = document.getElementById('search'), t = null;
  if (s) {
    s.addEventListener('input', function () { clearTimeout(t); t = setTimeout(function () { f.requestSubmit ? f.requestSubmit() : f.submit(); }, 350); });
    s.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); clearTimeout(t); f.requestSubmit ? f.requestSubmit() : f.submit(); } });
  }

  var adv = document.getElementById('adv-panel'), advBtn = document.getElementById('adv-toggle');
  var advRows = document.getElementById('adv-rows');
  var COLDEF = <?= json_encode([['code',__('Kode')],['name',__('Nama')],['category',__('Kategori')],['group',__('Group')],['location',__('Lokasi')],['device',__('Device')],['unit',__('Satuan')],['price',__('Harga Satuan')],['expected',__('Sistem')],['counted',__('Hitung')],['count',__('Status Hitung')],['audit',__('Status Audit')]], JSON_UNESCAPED_UNICODE) ?>;
  var OPS = <?= json_encode([['in',__('salah satu dari')],['contains',__('mengandung')],['equals',__('sama dengan')],['starts',__('diawali')],['gt',__('lebih dari')],['lt',__('kurang dari')],['empty',__('kosong')]], JSON_UNESCAPED_UNICODE) ?>;
  var backdrop = document.getElementById('adv-backdrop');
  document.body.appendChild(backdrop); document.body.appendChild(adv);
  function openAdv() { adv.hidden = false; backdrop.hidden = false; advBtn.setAttribute('aria-expanded','true'); if (advRows.children.length === 0) addRow(); var first = advRows.querySelector('.adv-col'); if (first) first.focus(); }
  function closeAdv() { adv.hidden = true; backdrop.hidden = true; advBtn.setAttribute('aria-expanded','false'); advBtn.focus(); }
  var existing = <?= json_encode(array_values($filters['adv'])) ?>;
  function rowHtml(i, c) {
    c = c || {};
    var colOpts = COLDEF.map(function (d) { return '<option value="' + d[0] + '"' + (c.col === d[0] ? ' selected' : '') + '>' + d[1] + '</option>'; }).join('');
    var opOpts = OPS.map(function (o) { return '<option value="' + o[0] + '"' + (c.op === o[0] ? ' selected' : '') + '>' + o[1] + '</option>'; }).join('');
    return '<div class="adv-row" data-i="' + i + '">' +
      '<select class="adv-col" aria-label="Kolom">' + colOpts + '</select>' +
      '<select class="adv-op" aria-label="Kondisi">' + opOpts + '</select>' +
      '<input class="adv-val" type="text" placeholder="Nilai" value="' + escAttr(c.val || '') + '" aria-label="Nilai">' +
      '<button type="button" class="btn btn-outline btn-sm adv-pick" aria-label="Pilih nilai">Pilih nilai</button>' +
      '<button type="button" class="btn btn-ghost btn-sm adv-del" aria-label="Hapus kondisi">×</button></div>';
  }
  function addRow(c) { advRows.insertAdjacentHTML('beforeend', rowHtml(advRows.children.length, c)); }
  existing.forEach(function (c) { addRow(c); });
  
  if (advBtn) {
    advBtn.addEventListener('click', function () { if (adv.hidden) openAdv(); else closeAdv(); });
  }
  backdrop.addEventListener('click', closeAdv);
  adv.querySelector('.adv-close').addEventListener('click', closeAdv);
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !adv.hidden) closeAdv(); });
  document.getElementById('adv-add').addEventListener('click', function () {
    if (advRows.children.length >= 12) return;
    addRow();
  });
  advRows.addEventListener('click', function (e) {
    if (e.target.classList.contains('adv-del')) e.target.closest('.adv-row').remove();
  });
  f.addEventListener('submit', function () {
    f.querySelectorAll('input[name^="adv["]').forEach(function (i) { i.remove(); });
    var n = 0;
    advRows.querySelectorAll('.adv-row').forEach(function (r) {
      var col = r.querySelector('.adv-col').value, op = r.querySelector('.adv-op').value, val = r.querySelector('.adv-val').value.trim();
      if (op !== 'empty' && val === '') return;
      [['col', col], ['op', op], ['val', val]].forEach(function (kv) {
        var h = document.createElement('input'); h.type = 'hidden'; h.name = 'adv[' + n + '][' + kv[0] + ']'; h.value = kv[1]; f.appendChild(h);
      });
      n++;
    });
  });
})();
  }
  
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
</script>

<script>
(function () {
    var all = document.getElementById('memo-all');
    var cnt = document.getElementById('memo-count');
    function boxes() { return Array.prototype.slice.call(document.querySelectorAll('.memo-check')); }
    function visible(cb) { var r = cb.closest('tr'); return r && r.style.display !== 'none' && !r.hidden; }
    function picked() { return boxes().filter(function (c) { return c.checked; }).map(function (c) { return c.value; }); }
    function refresh() {
        var ids = picked();
        cnt.textContent = ids.length + ' item dipilih';
        if (all) {
            var vis = boxes().filter(visible);
            all.checked = vis.length > 0 && vis.every(function (c) { return c.checked; });
        }
    }
    if (all) all.addEventListener('change', function () {
        boxes().filter(visible).forEach(function (c) { c.checked = all.checked; });
        refresh();
    });
    document.addEventListener('change', function (e) {
        if (e.target.classList && e.target.classList.contains('memo-check')) refresh();
    });
    refresh();
})();
</script>
