<?php
/**
 * Partial: Advanced Search drawer (reusable, universal untuk semua tabel).
 * Mendukung:
 *  - Modern Searchable Combobox & Dropdown dengan input pencarian real-time (tanpa scroll panjang).
 *  - Mode multi-nilai (in: salah satu dari) via modal pilihan multi-checkbox dengan pencarian.
 *  - Mode ketik bebas / manual via tombol toggle pensil atau fallback custom input.
 *  - Tampilan responsif modern OSS untuk PC, Tablet, HP (Portrait & Landscape).
 *  - Mengikuti standar anti-slop & UI UX Max Pro (vektor SVG, zero emoji).
 *
 * Vars:
 *  - $asForm         : id form GET yang di-submit (wajib)
 *  - $asCols         : array key => label kolom (wajib)
 *  - $asCurrent      : array kondisi tersimpan [ ['col'=>..,'op'=>..,'val'=>..], ... ]
 *  - $asNumeric      : list key kolom numerik (opsional)
 *  - $asMax          : batas kondisi (default 12)
 *  - $asToggleId     : id tombol toggle di toolbar (opsional)
 *  - $asRenderToggle : boolean (opsional, default false)
 *  - $asDistinct     : URL endpoint distinct (opsional)
 */
$asCurrent      = $asCurrent ?? [];
$asNumeric      = $asNumeric ?? [];
$asMax          = $asMax ?? 12;
$asToggleId     = $asToggleId ?? ($asForm . '-adv-btn');
$asRenderToggle = $asRenderToggle ?? false;

$currentPath    = trim(\App\Core\Request::path(), '/');
$asDistinct     = $asDistinct ?? url(($currentPath ?: 'items') . '/distinct');

$panelId    = $asForm . '-adv-panel';
$backdropId = $asForm . '-adv-backdrop';
$rowsId     = $asForm . '-adv-rows';
$addId      = $asForm . '-adv-add';
$clearId    = $asForm . '-adv-clear';

$AS_OPS = [
    'equals'   => 'sama dengan',
    'contains' => 'mengandung',
    'in'       => 'salah satu dari',
    'starts'   => 'diawali',
    'gt'       => 'lebih dari',
    'lt'       => 'kurang dari',
    'empty'    => 'kosong',
];
$__asActive = count($asCurrent);
?>
<div class="adv-backdrop" id="<?= e($backdropId) ?>" hidden></div>
<aside class="adv-drawer" id="<?= e($panelId) ?>" role="dialog" aria-modal="true" aria-labelledby="<?= e($panelId) ?>-title" hidden>
    <div class="adv-drawer-head">
        <div>
            <strong id="<?= e($panelId) ?>-title"><?= e(__('label.advanced_search')) ?></strong>
            <div class="muted text-sm">Pilih kolom &amp; nilai dari drop down searchable atau ketik bebas. Gabungan dengan AND.</div>
        </div>
        <button type="button" class="btn btn-ghost btn-sm adv-close" aria-label="<?= e(__('act.close')) ?>">&times;</button>
    </div>
    <div class="adv-drawer-body">
        <div id="<?= e($rowsId) ?>"></div>
        <button type="button" class="btn btn-outline btn-sm" id="<?= e($addId) ?>">+ <?= e(__('label.add_condition')) ?></button>
    </div>
    <div class="adv-drawer-foot">
        <a class="btn btn-ghost btn-sm" href="<?= e(url($currentPath)) ?>" id="<?= e($clearId) ?>"><?= e(__('Bersihkan')) ?></a>
        <button type="submit" class="btn btn-brand" form="<?= e($asForm) ?>"><?= e(__('act.search')) ?></button>
    </div>
</aside>

<?php if ($asRenderToggle): ?>
<button type="button" class="btn btn-info btn-icon-only" id="<?= e($asToggleId) ?>" aria-label="<?= e(__('Buka pencarian lanjutan')) ?>" title="<?= e(__('Pencarian lanjutan')) ?>">
    <?= icon('filter') ?>
</button>
<?php endif; ?>

<script>
(function () {
  function initAdv() {
    var PANEL = document.getElementById(<?= json_encode($panelId) ?>);
    var BACK  = document.getElementById(<?= json_encode($backdropId) ?>);
    var FORM  = document.getElementById(<?= json_encode($asForm) ?>);
    if (!PANEL || !BACK || !FORM) return;

    var COLS = <?= json_encode($asCols, JSON_UNESCAPED_UNICODE) ?>;
    var NUM  = <?= json_encode(array_values($asNumeric)) ?>;
    var MAX  = <?= (int) $asMax ?>;
    var EXIST = <?= json_encode(array_values($asCurrent), JSON_UNESCAPED_UNICODE) ?>;
    var OPS = <?= json_encode($AS_OPS, JSON_UNESCAPED_UNICODE) ?>;
    var DISTINCT_URL = <?= json_encode($asDistinct, JSON_UNESCAPED_SLASHES) ?>;

    var rows = document.getElementById(<?= json_encode($rowsId) ?>);
    var addBtn = document.getElementById(<?= json_encode($addId) ?>);
    var clearBtn = document.getElementById(<?= json_encode($clearId) ?>);

    // SVG Icon constants (Phosphor / Feather style vector icons)
    var SVG_SEARCH = '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>';
    var SVG_CHEVRON_DOWN = '<svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="6 9 12 15 18 9"/></svg>';
    var SVG_EDIT = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.12 2.12 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>';
    var SVG_LIST = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>';
    var SVG_CHECK = '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="20 6 9 17 4 12"/></svg>';

    // Portal ke document.body agar position:fixed selalu bebas dari clipping container
    if (BACK.parentNode !== document.body) document.body.appendChild(BACK);
    if (PANEL.parentNode !== document.body) document.body.appendChild(PANEL);

    function esc(s) {
      return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
        return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c];
      });
    }

    // Cache nilai distinct per kolom
    var distinctCache = {};

    function fetchDistinct(col, cb) {
      if (!col) return cb([]);
      if (distinctCache[col]) return cb(distinctCache[col]);

      var url = DISTINCT_URL + (DISTINCT_URL.indexOf('?') === -1 ? '?' : '&') + 'key=' + encodeURIComponent(col);
      fetch(url, { credentials: 'same-origin' })
        .then(function (res) { return res.json(); })
        .then(function (data) {
          var vals = (data && data.values) ? data.values : [];
          distinctCache[col] = vals;
          cb(vals);
        })
        .catch(function () {
          cb([]);
        });
    }

    // Modal / Popover Universal Searchable Combobox & Multi-Select
    var comboBackdrop = document.getElementById('adv-combo-backdrop-shared');
    if (!comboBackdrop) {
      comboBackdrop = document.createElement('div');
      comboBackdrop.id = 'adv-combo-backdrop-shared';
      comboBackdrop.className = 'adv-combo-backdrop';
      comboBackdrop.hidden = true;
      document.body.appendChild(comboBackdrop);
    }

    var comboPop = document.getElementById('adv-combo-pop-shared');
    if (!comboPop) {
      comboPop = document.createElement('div');
      comboPop.id = 'adv-combo-pop-shared';
      comboPop.className = 'adv-combo-pop';
      comboPop.setAttribute('role', 'dialog');
      comboPop.setAttribute('aria-modal', 'true');
      comboPop.hidden = true;
      comboPop.innerHTML =
        '<div class="adv-pop-header">' +
        '  <div class="adv-pop-title-wrap">' +
        '    <strong class="adv-pop-title">Pilih Nilai</strong>' +
        '    <span class="adv-pop-badge">Pilihan</span>' +
        '  </div>' +
        '  <button type="button" class="adv-pop-close" aria-label="Tutup">&times;</button>' +
        '</div>' +
        '<div class="adv-search-wrap">' +
        '  <span class="adv-search-icon">' + SVG_SEARCH + '</span>' +
        '  <input type="search" class="adv-search-input" placeholder="Cari nilai... (ketik untuk filter)" aria-label="Cari nilai" autocomplete="off">' +
        '  <button type="button" class="adv-search-clear" title="Hapus teks pencarian">&times;</button>' +
        '</div>' +
        '<div class="adv-pop-meta">' +
        '  <span class="adv-meta-count">Memuat...</span>' +
        '  <div class="adv-multi-actions" style="display:none;">' +
        '    <button type="button" class="adv-btn-select-all">Pilih Semua</button>' +
        '    <span class="adv-meta-sep">•</span>' +
        '    <button type="button" class="adv-btn-clear-all">Kosongkan</button>' +
        '  </div>' +
        '</div>' +
        '<div class="adv-pop-list" role="listbox"></div>' +
        '<div class="adv-pop-footer">' +
        '  <button type="button" class="btn btn-ghost btn-sm adv-pop-btn-cancel">Tutup</button>' +
        '  <button type="button" class="btn btn-brand btn-sm adv-pop-btn-apply" style="display:none;">Terapkan</button>' +
        '</div>';
      document.body.appendChild(comboPop);
    }

    var activeComboRow = null;
    var currentMultiSet = [];

    function selectedOfRow(r) {
      var v = (r.querySelector('.adv-val').value || '').trim();
      return v ? v.split('\x1f') : [];
    }

    function refreshTriggerLabel(r) {
      var trigger = r.querySelector('.adv-val-trigger');
      if (!trigger) return;
      var textEl = trigger.querySelector('.adv-val-text');
      var op = r.querySelector('.adv-op').value;
      var sel = selectedOfRow(r);

      if (op === 'in') {
        if (sel.length > 0) {
          trigger.classList.add('has-value');
          textEl.innerHTML = '<span class="adv-badge-check">' + SVG_CHECK + '</span> <strong>' + sel.length + '</strong> dipilih: ' + esc(sel.slice(0, 2).join(', ')) + (sel.length > 2 ? '...' : '');
        } else {
          trigger.classList.remove('has-value');
          textEl.textContent = 'Pilih nilai...';
        }
      } else {
        if (sel.length > 0 && sel[0]) {
          trigger.classList.add('has-value');
          textEl.innerHTML = '<strong>' + esc(sel[0]) + '</strong>';
        } else {
          trigger.classList.remove('has-value');
          var col = r.querySelector('.adv-col').value;
          var cached = distinctCache[col];
          if (cached && cached.length) {
            textEl.textContent = 'Pilih nilai (' + cached.length + ' pilihan)...';
          } else {
            textEl.textContent = 'Pilih nilai...';
          }
        }
      }
    }

    function renderComboOptions() {
      if (!activeComboRow) return;
      var col = activeComboRow.querySelector('.adv-col').value;
      var op = activeComboRow.querySelector('.adv-op').value;
      var isMulti = (op === 'in');
      var vals = distinctCache[col] || [];
      var q = comboPop.querySelector('.adv-search-input').value.trim();
      var qLower = q.toLowerCase();
      var listEl = comboPop.querySelector('.adv-pop-list');
      var countEl = comboPop.querySelector('.adv-meta-count');
      var clearSearchBtn = comboPop.querySelector('.adv-search-clear');

      if (clearSearchBtn) clearSearchBtn.style.display = q ? 'inline-flex' : 'none';

      var filtered = vals.filter(function (v) {
        return !q || String(v).toLowerCase().indexOf(qLower) !== -1;
      });

      countEl.textContent = q ? (filtered.length + ' cocok dari ' + vals.length) : (vals.length + ' pilihan tersedia');

      if (!filtered.length) {
        var emptyHtml = '<div class="adv-empty-state">';
        if (q) {
          emptyHtml += '<p class="adv-empty-text">Tidak ada pilihan yang cocok dengan "<strong>' + esc(q) + '</strong>".</p>';
          emptyHtml += '<button type="button" class="btn btn-outline btn-sm adv-use-custom-btn">Gunakan "' + esc(q) + '" sebagai filter</button>';
        } else {
          emptyHtml += '<p class="adv-empty-text">Belum ada data distinct untuk kolom ini.</p>';
        }
        emptyHtml += '</div>';
        listEl.innerHTML = emptyHtml;
        return;
      }

      function highlight(text, query) {
        if (!query) return esc(text);
        var idx = String(text).toLowerCase().indexOf(query.toLowerCase());
        if (idx === -1) return esc(text);
        var before = text.substring(0, idx);
        var match = text.substring(idx, idx + query.length);
        var after = text.substring(idx + query.length);
        return esc(before) + '<mark class="adv-hl">' + esc(match) + '</mark>' + esc(after);
      }

      if (isMulti) {
        listEl.innerHTML = filtered.map(function (v) {
          var on = currentMultiSet.indexOf(v) !== -1;
          return '<label class="adv-opt-item is-multi' + (on ? ' is-checked' : '') + '">' +
                 '  <input type="checkbox" class="adv-opt-cb" value="' + esc(v) + '"' + (on ? ' checked' : '') + '>' +
                 '  <span class="adv-opt-label">' + highlight(v, q) + '</span>' +
                 '</label>';
        }).join('');
      } else {
        var curVal = (activeComboRow.querySelector('.adv-val').value || '').trim();
        listEl.innerHTML = filtered.map(function (v) {
          var isSel = (v === curVal);
          return '<div class="adv-opt-item is-single' + (isSel ? ' is-selected' : '') + '" data-val="' + esc(v) + '" role="option">' +
                 '  <span class="adv-opt-label">' + highlight(v, q) + '</span>' +
                 (isSel ? '<span class="adv-opt-check">' + SVG_CHECK + '</span>' : '') +
                 '</div>';
        }).join('');
      }
    }

    function openComboPop(r) {
      activeComboRow = r;
      var col = r.querySelector('.adv-col').value;
      var op = r.querySelector('.adv-op').value;
      var isMulti = (op === 'in');
      var colLabel = COLS[col] || col;

      comboPop.querySelector('.adv-pop-title').textContent = 'Pilih ' + colLabel;
      var badge = comboPop.querySelector('.adv-pop-badge');
      var multiActions = comboPop.querySelector('.adv-multi-actions');
      var applyBtn = comboPop.querySelector('.adv-pop-btn-apply');
      var cancelBtn = comboPop.querySelector('.adv-pop-btn-cancel');

      if (isMulti) {
        badge.textContent = 'Pilih Banyak (OR)';
        badge.className = 'adv-pop-badge is-multi-badge';
        multiActions.style.display = 'inline-flex';
        applyBtn.style.display = 'inline-flex';
        cancelBtn.textContent = 'Batal';
        currentMultiSet = selectedOfRow(r);
        applyBtn.textContent = currentMultiSet.length ? ('Terapkan (' + currentMultiSet.length + ')') : 'Terapkan';
      } else {
        badge.textContent = 'Pilih 1 Nilai';
        badge.className = 'adv-pop-badge is-single-badge';
        multiActions.style.display = 'none';
        applyBtn.style.display = 'none';
        var hasVal = !!r.querySelector('.adv-val').value.trim();
        cancelBtn.textContent = hasVal ? 'Kosongkan Nilai' : 'Tutup';
        currentMultiSet = [];
      }

      var searchInp = comboPop.querySelector('.adv-search-input');
      searchInp.value = '';
      comboPop.querySelector('.adv-pop-list').innerHTML = '<div class="adv-empty-state"><p class="adv-empty-text">Memuat pilihan...</p></div>';

      comboBackdrop.hidden = false;
      comboPop.hidden = false;

      fetchDistinct(col, function (vals) {
        renderComboOptions();
        searchInp.focus();
        refreshTriggerLabel(r);
      });
    }

    function closeComboPop() {
      comboPop.hidden = true;
      comboBackdrop.hidden = true;
      activeComboRow = null;
    }

    comboBackdrop.addEventListener('click', closeComboPop);
    comboPop.querySelector('.adv-pop-close').addEventListener('click', closeComboPop);

    var searchInput = comboPop.querySelector('.adv-search-input');
    searchInput.addEventListener('input', renderComboOptions);

    var clearSearchBtn = comboPop.querySelector('.adv-search-clear');
    clearSearchBtn.addEventListener('click', function () {
      searchInput.value = '';
      searchInput.focus();
      renderComboOptions();
    });

    // Multi-actions: Select All / Clear All
    comboPop.querySelector('.adv-btn-select-all').addEventListener('click', function () {
      if (!activeComboRow) return;
      var col = activeComboRow.querySelector('.adv-col').value;
      var vals = distinctCache[col] || [];
      currentMultiSet = vals.slice(0);
      var applyBtn = comboPop.querySelector('.adv-pop-btn-apply');
      applyBtn.textContent = 'Terapkan (' + currentMultiSet.length + ')';
      renderComboOptions();
    });

    comboPop.querySelector('.adv-btn-clear-all').addEventListener('click', function () {
      currentMultiSet = [];
      var applyBtn = comboPop.querySelector('.adv-pop-btn-apply');
      applyBtn.textContent = 'Terapkan';
      renderComboOptions();
    });

    // Apply button for multi-select
    comboPop.querySelector('.adv-pop-btn-apply').addEventListener('click', function () {
      if (!activeComboRow) return;
      activeComboRow.querySelector('.adv-val').value = currentMultiSet.join('\x1f');
      refreshTriggerLabel(activeComboRow);
      closeComboPop();
    });

    // Cancel / Clear button
    comboPop.querySelector('.adv-pop-btn-cancel').addEventListener('click', function () {
      if (!activeComboRow) return;
      var op = activeComboRow.querySelector('.adv-op').value;
      if (op !== 'in') {
        var curVal = activeComboRow.querySelector('.adv-val').value;
        if (curVal) {
          activeComboRow.querySelector('.adv-val').value = '';
          refreshTriggerLabel(activeComboRow);
        }
      }
      closeComboPop();
    });

    // Option item click delegation
    comboPop.querySelector('.adv-pop-list').addEventListener('click', function (e) {
      if (!activeComboRow) return;

      // Click "Gunakan teks custom"
      var useBtn = e.target.closest('.adv-use-custom-btn');
      if (useBtn) {
        var customVal = searchInput.value.trim();
        if (customVal) {
          activeComboRow.querySelector('.adv-val').value = customVal;
          refreshTriggerLabel(activeComboRow);
          closeComboPop();
        }
        return;
      }

      var op = activeComboRow.querySelector('.adv-op').value;
      var isMulti = (op === 'in');

      if (isMulti) {
        var cbLabel = e.target.closest('.adv-opt-item.is-multi');
        if (cbLabel) {
          var cb = cbLabel.querySelector('input.adv-opt-cb');
          if (e.target !== cb) cb.checked = !cb.checked;
          var val = cb.value;
          var idx = currentMultiSet.indexOf(val);
          if (cb.checked && idx === -1) {
            currentMultiSet.push(val);
          } else if (!cb.checked && idx !== -1) {
            currentMultiSet.splice(idx, 1);
          }
          cbLabel.classList.toggle('is-checked', cb.checked);
          var applyBtn = comboPop.querySelector('.adv-pop-btn-apply');
          applyBtn.textContent = currentMultiSet.length ? ('Terapkan (' + currentMultiSet.length + ')') : 'Terapkan';
        }
      } else {
        var singleItem = e.target.closest('.adv-opt-item.is-single');
        if (singleItem) {
          var chosenVal = singleItem.getAttribute('data-val');
          activeComboRow.querySelector('.adv-val').value = chosenVal;
          refreshTriggerLabel(activeComboRow);
          closeComboPop();
        }
      }
    });

    // Keyboard navigation inside popover
    comboPop.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') {
        closeComboPop();
      } else if (e.key === 'Enter') {
        var list = comboPop.querySelector('.adv-pop-list');
        var firstSingle = list.querySelector('.adv-opt-item.is-single');
        var useBtn = list.querySelector('.adv-use-custom-btn');
        if (firstSingle) {
          firstSingle.click();
        } else if (useBtn) {
          useBtn.click();
        }
      }
    });

    function colOptions(sel) {
      var h = '';
      for (var k in COLS) h += '<option value="' + esc(k) + '"' + (k === sel ? ' selected' : '') + '>' + esc(COLS[k]) + '</option>';
      return h;
    }

    function opOptions(sel) {
      var h = '';
      for (var k in OPS) h += '<option value="' + esc(k) + '"' + (k === sel ? ' selected' : '') + '>' + esc(OPS[k]) + '</option>';
      return h;
    }

    // Perbarui kontrol nilai sesuai kolom dan operator
    function updateRowControls(r, preservedVal) {
      var col = r.querySelector('.adv-col').value;
      var op = r.querySelector('.adv-op').value;
      var valBox = r.querySelector('.adv-val-box');
      var valInput = r.querySelector('.adv-val');
      var triggerBtn = r.querySelector('.adv-val-trigger');
      var modeBtn = r.querySelector('.adv-val-mode');

      if (op === 'empty') {
        valBox.style.setProperty('display', 'none', 'important');
        valInput.value = '';
        return;
      }
      valBox.style.setProperty('display', 'flex', 'important');

      if (preservedVal !== undefined) valInput.value = preservedVal;

      // Kolom numerik: tampilkan input teks langsung
      if (NUM.indexOf(col) !== -1) {
        triggerBtn.style.setProperty('display', 'none', 'important');
        valInput.style.setProperty('display', 'inline-block', 'important');
        modeBtn.style.setProperty('display', 'none', 'important');
        valInput.placeholder = 'Nilai angka...';
        return;
      }

      // Kolom kategori/teks: gunakan searchable combobox trigger secara default
      modeBtn.style.setProperty('display', 'inline-flex', 'important');
      triggerBtn.style.setProperty('display', 'flex', 'important');
      valInput.style.setProperty('display', 'none', 'important');
      modeBtn.querySelector('.adv-mode-icon').innerHTML = SVG_EDIT;
      modeBtn.title = 'Beralih ke Ketik Manual';

      // Prefetch distinct
      fetchDistinct(col, function () {
        refreshTriggerLabel(r);
      });
      refreshTriggerLabel(r);
    }

    function addRow(c) {
      c = c || {};
      if (rows.children.length >= MAX) return;
      var row = document.createElement('div');
      row.className = 'adv-row';
      var opVal = c.op || 'equals';

      row.innerHTML =
        '<div class="adv-row-head">' +
        '  <div class="adv-field-col">' +
        '    <label class="adv-field-label">Kolom</label>' +
        '    <select class="adv-col" data-sf-select="skip" aria-label="Kolom">' + colOptions(c.col) + '</select>' +
        '  </div>' +
        '  <div class="adv-field-op">' +
        '    <label class="adv-field-label">Operator</label>' +
        '    <select class="adv-op" data-sf-select="skip" aria-label="Operator">' + opOptions(opVal) + '</select>' +
        '  </div>' +
        '  <button type="button" class="adv-del" aria-label="Hapus kondisi" title="Hapus kondisi">&times;</button>' +
        '</div>' +
        '<div class="adv-val-box adv-val-container">' +
        '  <div class="adv-val-inner">' +
        '    <label class="adv-field-label adv-val-label">Nilai Pencarian</label>' +
        '    <div class="adv-val-controls">' +
        '      <button type="button" class="adv-val-trigger" aria-label="Pilih nilai" aria-haspopup="listbox">' +
        '        <span class="adv-val-icon">' + SVG_SEARCH + '</span>' +
        '        <span class="adv-val-text">Pilih nilai...</span>' +
        '        <span class="adv-val-chev">' + SVG_CHEVRON_DOWN + '</span>' +
        '      </button>' +
        '      <input class="adv-val" type="text" value="' + esc(c.val || '') + '" placeholder="Ketik nilai..." aria-label="Nilai" autocomplete="off" style="display:none;">' +
        '    </div>' +
        '  </div>' +
        '  <button type="button" class="adv-val-mode" title="Beralih ke Ketik Manual" aria-label="Ganti mode input">' +
        '    <span class="adv-mode-icon">' + SVG_EDIT + '</span>' +
        '  </button>' +
        '</div>';

      rows.appendChild(row);
      updateRowControls(row, c.val);
      syncEmpty();
    }

    function syncEmpty() {
      if (rows.children.length === 0) addRow();
      if (addBtn) addBtn.disabled = rows.children.length >= MAX;
    }

    function rowsData() {
      var out = [];
      Array.prototype.forEach.call(rows.children, function (r) {
        var col = r.querySelector('.adv-col').value;
        var op = r.querySelector('.adv-op').value;
        var val = (r.querySelector('.adv-val').value || '').trim();
        if (!col || !op) return;
        if (op !== 'empty' && val === '') return;
        out.push({ col: col, op: op, val: val });
      });
      return out;
    }

    function writeHidden() {
      FORM.querySelectorAll('input.as-hidden, input[name^="adv["]').forEach(function (i) { i.remove(); });
      rowsData().forEach(function (c, i) {
        ['col', 'op', 'val'].forEach(function (f) {
          var h = document.createElement('input');
          h.type = 'hidden'; h.className = 'as-hidden';
          h.name = 'adv[' + i + '][' + f + ']';
          h.value = c[f];
          FORM.appendChild(h);
        });
      });
    }

    function open() {
      PANEL.hidden = false;
      BACK.hidden = false;
      getTogBtns().forEach(function (b) { b.setAttribute('aria-expanded', 'true'); });
      var first = rows.querySelector('.adv-col');
      if (first) first.focus();
    }

    function close() {
      PANEL.hidden = true;
      BACK.hidden = true;
      getTogBtns().forEach(function (b) { b.setAttribute('aria-expanded', 'false'); });
      closeComboPop();
    }

    function getTogBtns() {
      var sel = '#' + <?= json_encode($asToggleId) ?> + ', #' + <?= json_encode($asForm) ?> + '-adv-btn, #' + <?= json_encode($asForm) ?> + '-adv-toggle, [data-adv-toggle="' + <?= json_encode($asForm) ?> + '"], [data-adv-toggle]';
      return Array.prototype.slice.call(document.querySelectorAll(sel));
    }

    getTogBtns().forEach(function (b) {
      b.addEventListener('click', function (e) {
        e.preventDefault();
        PANEL.hidden ? open() : close();
      });
    });

    rows.addEventListener('click', function (e) {
      if (e.target.classList.contains('adv-del')) {
        e.target.closest('.adv-row').remove();
        syncEmpty();
        return;
      }

      var triggerBtn = e.target.closest('.adv-val-trigger');
      if (triggerBtn) {
        e.stopPropagation();
        var r = triggerBtn.closest('.adv-row');
        openComboPop(r);
        return;
      }

      var modeBtn = e.target.closest('.adv-val-mode');
      if (modeBtn) {
        var rMode = modeBtn.closest('.adv-row');
        var trig = rMode.querySelector('.adv-val-trigger');
        var inp = rMode.querySelector('.adv-val');
        var iconSpan = modeBtn.querySelector('.adv-mode-icon');

        if (trig.style.display !== 'none') {
          // Switch to manual input
          trig.style.setProperty('display', 'none', 'important');
          inp.style.setProperty('display', 'inline-block', 'important');
          iconSpan.innerHTML = SVG_LIST;
          modeBtn.title = 'Beralih ke Pilihan Daftar';
          inp.focus();
        } else {
          // Switch to combobox trigger
          inp.style.setProperty('display', 'none', 'important');
          trig.style.setProperty('display', 'flex', 'important');
          iconSpan.innerHTML = SVG_EDIT;
          modeBtn.title = 'Beralih ke Ketik Manual';
          refreshTriggerLabel(rMode);
        }
      }
    });

    rows.addEventListener('change', function (e) {
      var r = e.target.closest('.adv-row');
      if (!r) return;

      if (e.target.classList.contains('adv-col')) {
        r.querySelector('.adv-val').value = '';
        updateRowControls(r, '');
      } else if (e.target.classList.contains('adv-op')) {
        updateRowControls(r, r.querySelector('.adv-val').value);
      }
    });

    if (addBtn) addBtn.addEventListener('click', function () { addRow(); });
    var closeBtn = PANEL.querySelector('.adv-close');
    if (closeBtn) closeBtn.addEventListener('click', close);
    BACK.addEventListener('click', close);
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && !PANEL.hidden && comboPop.hidden) close();
    });
    FORM.addEventListener('submit', writeHidden, true);

    if (EXIST.length) {
      EXIST.forEach(function (c) { addRow(c); });
    } else {
      syncEmpty();
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initAdv);
  } else {
    initAdv();
  }
})();
</script>
