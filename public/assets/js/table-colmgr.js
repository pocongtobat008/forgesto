/* table-colmgr.js v5 (Universal Column Manager, Live Resize, & Drag-Drop).
 * 1. Icon Sesuaikan Kolom: Pop-up rapi, scrollable, bebas bug, auto-save real-time, SVG icons (zero emoji).
 * 2. Manual Adjust Lebar Kolom: Handle resize (.col-resize) mudah digeser untuk SEMUA kolom (termasuk kolom belakang / aksi), dengan prioritas styling tinggi (!important) dan live resizing feedback.
 * 3. Excel-style Filter Icon: Posisi ikon di pojok kanan sel header.
 * 4. Persistensi Penuh: Simpan order, hidden, dan width per tabel di localStorage.
 */
(function () {
  'use strict';

  function init(tbl) {
    if (!tbl || tbl.__colmgr) return;
    tbl.__colmgr = true;

    var KEY = tbl.getAttribute('data-colmgr-key') || ('colmgr-' + (tbl.id || 'tbl'));
    var PREFIX = tbl.getAttribute('data-colmgr-prefix') || (tbl.id || 'tbl').replace(/-table$/, '');
    var MIN_WIDTH = 45;

    var headRow = tbl.querySelector('thead tr');
    if (!headRow) return;

    var saved = { hidden: {}, width: {}, order: [] };
    try {
      var parsed = JSON.parse(localStorage.getItem(KEY) || '{}');
      saved = Object.assign(saved, parsed);
      if (!Array.isArray(saved.order)) saved.order = [];
      if (!saved.hidden || typeof saved.hidden !== 'object') saved.hidden = {};
      if (!saved.width || typeof saved.width !== 'object') saved.width = {};
    } catch (e) {
      saved = { hidden: {}, width: {}, order: [] };
    }
    var cfg = JSON.parse(JSON.stringify(saved));

    // ===== PRESET & MODE (Default / Custom) =====
    var presetRaw = (tbl.getAttribute('data-colmgr-preset') || '').trim();
    var PRESET = presetRaw ? presetRaw.split(',').map(function (x) { return x.trim(); }).filter(Boolean) : [];
    var hasPreset = PRESET.length > 0;
    var MODE = (saved.mode === 'custom' || saved.mode === 'default')
      ? saved.mode
      : (hasPreset ? 'default' : 'custom');

    function allThs() { return [].slice.call(headRow.children); }
    function allKeys() { return allThs().map(getThKey).filter(Boolean); }

    function labelFor(key) {
      var found = '';
      allThs().forEach(function (th) {
        if (getThKey(th) !== key) return;
        var el = th.querySelector('.xf-label');
        var txt = (el ? el.textContent : th.textContent).replace(/\s+/g, ' ').trim();
        found = txt || key;
      });
      return found;
    }

    /** Terapkan template standar: hanya kolom preset yang tampil, urutan sesuai preset. */
    function applyPreset() {
      if (!hasPreset) return;
      var keys = allKeys();
      var fixedKeys = allThs().filter(isFixed).map(getThKey).filter(Boolean);

      /* Kolom non-fixed diurutkan sesuai preset; kolom fixed (checkbox / Aksi)
         tetap di posisi aslinya di DOM. Dulu semua fixed ditaruh di depan,
         sehingga moveColumn menggeser kolom lain dan urutan jadi tertukar
         (mis. Selisih tampil sebelum Hitung). */
      var rest = [];
      PRESET.forEach(function (k) {
        if (keys.indexOf(k) !== -1 && fixedKeys.indexOf(k) === -1 && rest.indexOf(k) === -1) rest.push(k);
      });
      keys.forEach(function (k) {
        if (fixedKeys.indexOf(k) === -1 && rest.indexOf(k) === -1) rest.push(k);
      });

      /* Iterasi per <th> (bukan per key) supaya kolom tanpa data-col —
         mis. checkbox "pilih semua" — tetap di slotnya dan indeks tidak
         bergeser satu. */
      var order = [];
      var ri = 0;
      allThs().forEach(function (th) {
        var k = getThKey(th);
        if (!k) { order.push(''); return; }
        if (isFixed(th)) { order.push(k); return; }
        order.push(rest[ri++]);
      });

      saved.order = order;
      saved.hidden = {};
      keys.forEach(function (k) {
        if (fixedKeys.indexOf(k) !== -1) return;
        if (PRESET.indexOf(k) === -1) saved.hidden[k] = true;
      });
      cfg = JSON.parse(JSON.stringify(saved));
      persist();
      applySavedOrder(order);
      applyAll();
    }

    function setMode(mode) {
      if (mode === MODE) return;
      if (mode === 'default') {
        if (MODE === 'custom') {
          saved.custom = { hidden: JSON.parse(JSON.stringify(saved.hidden || {})), order: (saved.order || []).slice() };
        }
        MODE = 'default';
        saved.mode = 'default';
        applyPreset();
        notify('Tampilan kolom: Default', 'info');
      } else {
        MODE = 'custom';
        saved.mode = 'custom';
        if (saved.custom) {
          saved.hidden = saved.custom.hidden || {};
          saved.order = saved.custom.order || [];
          persist();
          applySavedOrder(saved.order);
          applyAll();
        } else {
          persist();
        }
        notify('Tampilan kolom: Custom', 'info');
      }
      buildPanelItems();
    }

    function persist() {
      try {
        localStorage.setItem(KEY, JSON.stringify(saved));
      } catch (e) {}
    }

    function getThKey(th) {
      if (!th) return '';
      var col = th.getAttribute('data-col') || th.getAttribute('id');
      if (!col) {
        var txt = th.textContent.replace(/\s+/g, ' ').trim().toLowerCase();
        if (txt === 'aksi' || txt === 'action' || txt === 'actions') {
          col = 'actions';
        } else if (txt && txt !== '#') {
          col = 'col-' + txt.replace(/[^a-z0-9]/g, '-');
        }
        if (col) th.setAttribute('data-col', col);
      }
      return col || '';
    }

    function isFixed(th) {
      if (!th) return true;
      if (th.classList.contains('th-select-all') || th.classList.contains('recon-check-col') ||
          th.classList.contains('memo-pick') || th.querySelector('input[type="checkbox"]')) {
        return true;
      }
      var k = (th.getAttribute('data-col') || '').toLowerCase();
      if (k === 'select' || k === 'check' || k === 'cb') return true;
      var txt = th.textContent.trim().toLowerCase();
      if (txt === 'aksi' || txt === 'action' || txt === 'actions' || txt === '#') return true;
      if (k === 'no' && th.textContent.trim() === '#') return true;
      if (k === 'aksi' || k === 'actions' || th.classList.contains('cell-action') || (th.classList.contains('t-right') && txt === 'aksi')) return true;
      return false;
    }

    // Catat urutan asli DOM untuk fitur Reset
    var initialDomOrder = [].slice.call(headRow.children).map(getThKey);

    // Pindahkan kolom dari fromIdx ke toIdx pada header dan semua baris data
    function moveColumn(fromIdx, toIdx) {
      var ths = [].slice.call(headRow.children);
      var fromTh = ths[fromIdx], toTh = ths[toIdx];
      if (!fromTh || !toTh || fromTh === toTh) return;

      if (fromIdx < toIdx) {
        headRow.insertBefore(fromTh, toTh.nextSibling);
      } else {
        headRow.insertBefore(fromTh, toTh);
      }

      tbl.querySelectorAll('tbody tr, tfoot tr').forEach(function (row) {
        var cells = [].slice.call(row.children);
        if (cells.length <= 1 && cells[0] && cells[0].hasAttribute('colspan')) return;
        var fromCell = cells[fromIdx], toCell = cells[toIdx];
        if (fromCell && toCell) {
          if (fromIdx < toIdx) {
            row.insertBefore(fromCell, toCell.nextSibling);
          } else {
            row.insertBefore(fromCell, toCell);
          }
        }
      });
    }

    // Terapkan urutan tersimpan
    function applySavedOrder(targetOrder) {
      if (!targetOrder || !targetOrder.length) return;
      var curKeys = [].slice.call(headRow.children).map(getThKey);
      var validKeys = targetOrder.filter(function (k) { return curKeys.indexOf(k) !== -1; });
      curKeys.forEach(function (k) { if (validKeys.indexOf(k) === -1) validKeys.push(k); });

      validKeys.forEach(function (key, desiredIdx) {
        var ths = [].slice.call(headRow.children);
        var curIdx = -1;
        for (var i = 0; i < ths.length; i++) {
          if (getThKey(ths[i]) === key) { curIdx = i; break; }
        }
        if (curIdx !== -1 && curIdx !== desiredIdx && desiredIdx < ths.length) {
          if (!isFixed(ths[curIdx]) && !isFixed(ths[desiredIdx])) {
            moveColumn(curIdx, desiredIdx);
          }
        }
      });
    }

    // Terapkan sembunyikan / tampilkan kolom
    function applyHidden() {
      [].slice.call(headRow.children).forEach(function (th) {
        var k = getThKey(th);
        if (!k) return;
        var off = !!saved.hidden[k];
        th.classList.toggle('is-col-hidden', off);
        var idx = [].indexOf.call(headRow.children, th) + 1;
        tbl.querySelectorAll('tbody tr > *:nth-child(' + idx + '), tfoot tr > *:nth-child(' + idx + ')').forEach(function (c) {
          if (c.hasAttribute('colspan')) return;
          c.classList.toggle('is-col-hidden', off);
        });
      });
    }

    // Terapkan lebar kolom kustom (menggunakan !important agar tidak terhambat oleh stylesheet lama)
    function applyWidth() {
      [].slice.call(headRow.children).forEach(function (th) {
        var k = getThKey(th);
        var w = saved.width[k];
        if (!k) return;
        var idx = [].indexOf.call(headRow.children, th) + 1;
        if (w) {
          th.style.setProperty('width', w + 'px', 'important');
          th.style.setProperty('min-width', w + 'px', 'important');
          th.style.setProperty('max-width', w + 'px', 'important');
        } else {
          th.style.removeProperty('width');
          th.style.removeProperty('min-width');
          th.style.removeProperty('max-width');
        }
        tbl.querySelectorAll('tbody tr > *:nth-child(' + idx + '), tfoot tr > *:nth-child(' + idx + ')').forEach(function (c) {
          if (c.hasAttribute('colspan')) return;
          if (w) {
            c.style.setProperty('width', w + 'px', 'important');
            c.style.setProperty('min-width', w + 'px', 'important');
            c.style.setProperty('max-width', w + 'px', 'important');
          } else {
            c.style.removeProperty('width');
            c.style.removeProperty('min-width');
            c.style.removeProperty('max-width');
          }
        });
      });
    }

    function applyAll() {
      applyHidden();
      applyWidth();
    }

    // Setup Toolbar & Panel
    var bar = document.getElementById(PREFIX + '-colmgr') || document.getElementById('col-mgr');
    var btn = document.getElementById(PREFIX + '-colmgr-btn') || document.getElementById('col-mgr-btn');
    var panel = document.getElementById(PREFIX + '-colmgr-panel') || document.getElementById('col-mgr-panel');

    // Auto-create toolbar jika belum ada di template HTML
    if (!bar) {
      var toolbarParent = tbl.closest('.card') ? tbl.closest('.card').querySelector('.table-toolbar, .filter-header, .card-header') : null;
      if (!toolbarParent && tbl.parentNode) toolbarParent = tbl.parentNode;

      bar = document.createElement('div');
      bar.className = 'col-mgr';
      bar.id = PREFIX + '-colmgr';
      bar.innerHTML = '<button type="button" class="btn btn-ghost btn-icon-only" id="' + PREFIX + '-colmgr-btn" aria-expanded="false" aria-label="Sesuaikan kolom" title="Sesuaikan kolom">' +
                      '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polygon points="12 2 2 7 12 12 22 7 12 2"></polygon><polyline points="2 17 12 22 22 17"></polyline><polyline points="2 12 12 17 22 12"></polyline></svg>' +
                      '</button>' +
                      '<div class="col-mgr-panel" id="' + PREFIX + '-colmgr-panel" hidden></div>';

      if (toolbarParent && toolbarParent.classList && toolbarParent.classList.contains('table-toolbar')) {
        toolbarParent.appendChild(bar);
      } else if (toolbarParent && toolbarParent !== tbl.parentNode) {
        toolbarParent.appendChild(bar);
      } else if (tbl.parentNode) {
        tbl.parentNode.insertBefore(bar, tbl);
      }

      btn = document.getElementById(PREFIX + '-colmgr-btn');
      panel = document.getElementById(PREFIX + '-colmgr-panel');
    }

    function notify(msg, type) {
      if (typeof window.showToast === 'function') {
        window.showToast(msg, type || 'info');
      } else if (typeof window.sfToast === 'function') {
        window.sfToast(msg, type || 'info');
      }
    }

    function doReset() {
      MODE = hasPreset ? 'default' : 'custom';
      cfg = { hidden: {}, width: {}, order: [], mode: MODE };
      saved = { hidden: {}, width: {}, order: [], mode: MODE };
      persist();

      if (!hasPreset) applySavedOrder(initialDomOrder);

      [].slice.call(headRow.children).forEach(function (th) {
        th.classList.remove('is-col-hidden');
        th.style.removeProperty('width');
        th.style.removeProperty('min-width');
        th.style.removeProperty('max-width');
        var idx = [].indexOf.call(headRow.children, th) + 1;
        tbl.querySelectorAll('tr > *:nth-child(' + idx + ')').forEach(function (c) {
          c.classList.remove('is-col-hidden');
          c.style.removeProperty('width');
          c.style.removeProperty('min-width');
          c.style.removeProperty('max-width');
        });
      });

      if (hasPreset) applyPreset();
      buildPanelItems();
      notify(hasPreset ? 'Kolom dikembalikan ke template standar' : 'Pengaturan kolom direset ke default', 'info');
    }

    // Bangun item kontrol kolom di dalam panel
    function buildPanelItems() {
      if (!panel) return;
      panel.innerHTML = '';

      // 1. Header Pop-up
      var head = document.createElement('div');
      head.className = 'col-mgr-head';
      head.innerHTML = '<span>Sesuaikan Kolom</span>';
      var closeBtn = document.createElement('button');
      closeBtn.type = 'button';
      closeBtn.className = 'col-mgr-close';
      closeBtn.innerHTML = '&times;';
      closeBtn.title = 'Tutup';
      closeBtn.setAttribute('aria-label', 'Tutup');
      closeBtn.addEventListener('click', function () {
        if (typeof panel.__close === 'function') { panel.__close(); return; }
        panel.hidden = true;
        if (btn) btn.setAttribute('aria-expanded', 'false');
      });
      head.appendChild(closeBtn);
      panel.appendChild(head);

      // 1b. Mode Default / Custom + hint
      if (hasPreset) {
        var modeWrap = document.createElement('div');
        modeWrap.className = 'col-mgr-mode';
        modeWrap.setAttribute('role', 'group');
        modeWrap.setAttribute('aria-label', 'Tampilan kolom');
        [['default', 'Default'], ['custom', 'Custom']].forEach(function (pair) {
          var mb = document.createElement('button');
          mb.type = 'button';
          mb.className = 'col-mgr-mode-btn' + (MODE === pair[0] ? ' is-active' : '');
          mb.setAttribute('data-mode', pair[0]);
          mb.setAttribute('aria-pressed', MODE === pair[0] ? 'true' : 'false');
          mb.textContent = pair[1];
          mb.addEventListener('click', function (e) { e.stopPropagation(); setMode(pair[0]); });
          modeWrap.appendChild(mb);
        });
        panel.appendChild(modeWrap);

        var hint = document.createElement('div');
        hint.className = 'col-mgr-hint';
        hint.textContent = MODE === 'default'
          ? 'Tampilan standar: ' + PRESET.map(labelFor).join(', ') + '.'
          : 'Pilih kolom yang tampil dan atur urutannya sendiri.';
        panel.appendChild(hint);
      }

      // 2. Scrollable List
      var list = document.createElement('div');
      list.className = 'col-mgr-list';
      panel.appendChild(list);

      var movableThs = [].slice.call(headRow.children).filter(function (th) {
        return !isFixed(th) && getThKey(th);
      });

      movableThs.forEach(function (th, mIdx) {
        var k = getThKey(th);
        var labelText = (th.querySelector('.xf-label') ? th.querySelector('.xf-label').textContent : th.textContent).replace(/\s+/g, ' ').trim();

        var lab = document.createElement('label');
        lab.className = 'col-mgr-item';

        var cb = document.createElement('input');
        cb.type = 'checkbox';
        cb.checked = !saved.hidden[k];
        cb.setAttribute('data-col', k);
        // REAL-TIME AUTO PERSISTENCE
        cb.addEventListener('change', function () {
          saved.hidden[k] = !cb.checked;
          cfg.hidden[k] = saved.hidden[k];
          persist();
          applyHidden();
        });

        var txt = document.createElement('span');
        txt.className = 'col-mgr-item-label';
        txt.textContent = labelText;

        // Tombol geser dengan SVG Icons (zero emoji)
        var mvWrap = document.createElement('span');
        mvWrap.className = 'col-mv';

        var bLeft = document.createElement('button');
        bLeft.type = 'button';
        bLeft.className = 'col-mv-btn';
        bLeft.innerHTML = '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="15 18 9 12 15 6"/></svg>';
        bLeft.title = 'Geser ' + labelText + ' ke kiri';
        bLeft.setAttribute('aria-label', 'Geser ' + labelText + ' ke kiri');
        bLeft.disabled = mIdx <= 0;

        var bRight = document.createElement('button');
        bRight.type = 'button';
        bRight.className = 'col-mv-btn';
        bRight.innerHTML = '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="9 18 15 12 9 6"/></svg>';
        bRight.title = 'Geser ' + labelText + ' ke kanan';
        bRight.setAttribute('aria-label', 'Geser ' + labelText + ' ke kanan');
        bRight.disabled = mIdx >= movableThs.length - 1;

        bLeft.addEventListener('click', function (e) {
          e.preventDefault(); e.stopPropagation();
          var curIdx = [].indexOf.call(headRow.children, th);
          var prevTh = movableThs[mIdx - 1];
          if (prevTh) {
            var targetIdx = [].indexOf.call(headRow.children, prevTh);
            moveColumn(curIdx, targetIdx);
            saved.order = [].slice.call(headRow.children).map(getThKey);
            cfg.order = saved.order;
            persist();
            buildPanelItems();
          }
        });

        bRight.addEventListener('click', function (e) {
          e.preventDefault(); e.stopPropagation();
          var curIdx = [].indexOf.call(headRow.children, th);
          var nextTh = movableThs[mIdx + 1];
          if (nextTh) {
            var targetIdx = [].indexOf.call(headRow.children, nextTh);
            moveColumn(curIdx, targetIdx);
            saved.order = [].slice.call(headRow.children).map(getThKey);
            cfg.order = saved.order;
            persist();
            buildPanelItems();
          }
        });

        mvWrap.appendChild(bLeft);
        mvWrap.appendChild(bRight);

        lab.appendChild(cb);
        lab.appendChild(txt);
        lab.appendChild(mvWrap);
        if (hasPreset && MODE === 'default') {
          lab.classList.add('is-locked');
          cb.disabled = true;
          bLeft.disabled = true;
          bRight.disabled = true;
        }
        list.appendChild(lab);
      });

      // 3. Footer Pop-up: Reset & Selesai
      var foot = document.createElement('div');
      foot.className = 'col-mgr-foot';

      var bReset = document.createElement('button');
      bReset.type = 'button';
      bReset.className = 'btn btn-outline btn-xs';
      bReset.textContent = 'Reset';
      bReset.addEventListener('click', doReset);

      var bDone = document.createElement('button');
      bDone.type = 'button';
      bDone.className = 'btn btn-brand btn-xs';
      bDone.textContent = 'Selesai';
      bDone.addEventListener('click', function () {
        if (typeof panel.__close === 'function') { panel.__close(); return; }
        panel.hidden = true;
        if (btn) btn.setAttribute('aria-expanded', 'false');
      });

      foot.appendChild(bReset);
      foot.appendChild(bDone);
      panel.appendChild(foot);
    }

    // Setup Resizer & Drag-Drop Headers
    var draggedTh = null;

    function setupHeaders() {
      [].slice.call(headRow.children).forEach(function (th) {
        var k = getThKey(th);
        var isFixedCol = isFixed(th);

        // 1. Drag & Drop Reordering (HTML5)
        if (!isFixedCol) {
          th.setAttribute('draggable', 'true');
          th.classList.add('col-draggable');
          if (!th.title) th.title = 'Tarik untuk ubah urutan';

          th.addEventListener('dragstart', function (e) {
            if (e.target && e.target.closest && (e.target.closest('.col-resize') || e.target.closest('.xf-btn') || e.target.closest('input') || e.target.closest('button'))) {
              e.preventDefault();
              return false;
            }
            draggedTh = th;
            th.classList.add('is-col-dragging');
            try {
              e.dataTransfer.effectAllowed = 'move';
              e.dataTransfer.setData('text/plain', getThKey(th));
            } catch (_) {}
          });

          th.addEventListener('dragover', function (e) {
            if (!draggedTh || draggedTh === th || isFixedCol) return;
            e.preventDefault();
            e.dataTransfer.dropEffect = 'move';
            th.classList.add('is-col-drop');
          });

          th.addEventListener('dragleave', function () {
            th.classList.remove('is-col-drop');
          });

          th.addEventListener('dragend', function () {
            if (draggedTh) draggedTh.classList.remove('is-col-dragging');
            draggedTh = null;
            headRow.querySelectorAll('.is-col-drop').forEach(function (el) { el.classList.remove('is-col-drop'); });
          });

          th.addEventListener('drop', function (e) {
            e.preventDefault();
            th.classList.remove('is-col-drop');
            if (!draggedTh || draggedTh === th || isFixedCol) return;
            var fromIdx = [].indexOf.call(headRow.children, draggedTh);
            var toIdx = [].indexOf.call(headRow.children, th);
            if (fromIdx === -1 || toIdx === -1 || fromIdx === toIdx) return;

            moveColumn(fromIdx, toIdx);
            saved.order = [].slice.call(headRow.children).map(getThKey);
            cfg.order = saved.order;
            persist();
            buildPanelItems();
            notify('Urutan kolom tersimpan', 'success');
          });
        }

        // 2. Manual Adjust Lebar Kolom (Resize Handle)
        // Pasang handle untuk semua kolom yang memiliki key (termasuk kolom aksi dan kolom data)
        if (k && !th.querySelector('.col-resize')) {
          var labelText = th.textContent.trim();
          var h = document.createElement('span');
          h.className = 'col-resize';
          h.setAttribute('role', 'separator');
          h.setAttribute('aria-orientation', 'vertical');
          h.setAttribute('aria-label', 'Ubah lebar kolom ' + labelText);
          h.setAttribute('draggable', 'false');
          h.title = 'Tarik garis ini untuk mengatur lebar kolom. Klik ganda untuk reset lebar otomatis.';
          th.appendChild(h);

          function setW(w) {
            var finalW = Math.max(MIN_WIDTH, Math.round(w));
            saved.width[k] = finalW;
            cfg.width[k] = finalW;
            applyWidth();
          }

          h.addEventListener('pointerdown', function (e) {
            if (e.button !== undefined && e.button !== 0) return;
            e.preventDefault();
            e.stopPropagation();

            var startX = e.clientX;
            var startW = th.getBoundingClientRect().width;
            h.classList.add('is-drag');
            document.body.classList.add('sf-colresizing');

            function onMove(ev) {
              var delta = ev.clientX - startX;
              setW(startW + delta);
            }

            function onUp(ev) {
              window.removeEventListener('pointermove', onMove);
              window.removeEventListener('pointerup', onUp);
              window.removeEventListener('pointercancel', onUp);
              h.classList.remove('is-drag');
              document.body.classList.remove('sf-colresizing');

              // PERSIST LEBAR KOLOM KE LOCALSTORAGE
              persist();
              notify('Lebar kolom ' + labelText + ' tersimpan', 'info');
            }

            window.addEventListener('pointermove', onMove);
            window.addEventListener('pointerup', onUp);
            window.addEventListener('pointercancel', onUp);
          });

          // Klik ganda: reset lebar kolom ke otomatis
          h.addEventListener('dblclick', function (e) {
            e.preventDefault();
            e.stopPropagation();
            delete saved.width[k];
            delete cfg.width[k];
            persist();
            applyWidth();
            notify('Lebar kolom ' + labelText + ' direset ke otomatis', 'info');
          });

          h.addEventListener('click', function (e) {
            e.stopPropagation();
          });
        }
      });
    }

    // ===== Toggle Panel: portal ke <body> + posisi terukur (anti-terpotong) =====
    if (btn && panel) {
      // Pindahkan panel ke body agar tidak terpotong ancestor ber-overflow:hidden (.card)
      if (panel.parentNode !== document.body) document.body.appendChild(panel);
      panel.classList.add('col-mgr-panel--portal');

      function place() {
        var r = btn.getBoundingClientRect();
        var vw = document.documentElement.clientWidth || window.innerWidth;
        var vh = window.innerHeight || document.documentElement.clientHeight;
        var w = Math.min(300, Math.max(240, vw - 24));
        var gap = 8;
        var s = panel.style;

        s.setProperty('position', 'fixed', 'important');
        s.setProperty('width', w + 'px', 'important');
        s.setProperty('right', 'auto', 'important');
        s.setProperty('bottom', 'auto', 'important');
        s.setProperty('z-index', '1400', 'important');
        s.setProperty('display', 'flex', 'important');
        s.setProperty('flex-direction', 'column', 'important');
        s.setProperty('overflow', 'hidden', 'important');

        var left = r.right - w;
        if (left < 8) left = 8;
        if (left + w > vw - 8) left = Math.max(8, vw - 8 - w);
        s.setProperty('left', Math.round(left) + 'px', 'important');

        // Panel dibuat cukup tinggi (daftar kolom lega) tapi SELALU utuh
        // di dalam viewport: tidak terpotong atas, bawah, kiri, maupun kanan.
        var maxH = Math.min(520, vh - 40);
        var top = r.bottom + gap;
        if (top + maxH > vh - 20) top = vh - 20 - maxH; // geser naik bila bawah tidak cukup
        if (top < 10) top = 10;
        maxH = Math.min(maxH, vh - top - 20);
        if (maxH < 180) maxH = Math.max(160, vh - 30);
        s.setProperty('top', Math.round(top) + 'px', 'important');
        s.setProperty('max-height', Math.round(maxH) + 'px', 'important');

        var list = panel.querySelector('.col-mgr-list');
        if (list) {
          list.style.setProperty('flex', '1 1 auto', 'important');
          list.style.setProperty('min-height', '0', 'important');
          list.style.setProperty('overflow-y', 'auto', 'important');
        }
        var foot = panel.querySelector('.col-mgr-foot');
        if (foot) foot.style.setProperty('flex', '0 0 auto', 'important');
      }

      function openPanel() {
        panel.hidden = false;
        panel.style.setProperty('display', 'flex', 'important');
        btn.setAttribute('aria-expanded', 'true');
        place();
      }

      function closePanel() {
        panel.hidden = true;
        panel.style.setProperty('display', 'none', 'important');
        btn.setAttribute('aria-expanded', 'false');
      }

      closePanel();

      btn.addEventListener('click', function (e) {
        e.stopPropagation();
        if (panel.hidden) openPanel(); else closePanel();
      });

      panel.addEventListener('click', function (e) { e.stopPropagation(); });

      document.addEventListener('click', function (e) {
        if (!panel.hidden && !panel.contains(e.target) && !btn.contains(e.target)) closePanel();
      });

      document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && !panel.hidden) { closePanel(); btn.focus(); }
      });

      window.addEventListener('resize', function () { if (!panel.hidden) place(); });
      window.addEventListener('scroll', function () { if (!panel.hidden) place(); }, true);
      if (window.visualViewport) {
        window.visualViewport.addEventListener('resize', function () { if (!panel.hidden) place(); });
        window.visualViewport.addEventListener('scroll', function () { if (!panel.hidden) place(); });
      }
      panel.__place = place;
      panel.__close = closePanel;
    }

    // Inisialisasi awal: template standar (default) atau konfigurasi custom tersimpan
    if (hasPreset && MODE === 'default') {
      saved.mode = 'default';
      applyPreset();
    } else if (saved.order && saved.order.length > 0) {
      applySavedOrder(saved.order);
    }

    setupHeaders();
    buildPanelItems();
    applyAll();
  }

  // Scan dan inisialisasi semua tabel di halaman
  function scanAndInit() {
    document.querySelectorAll('table[data-colmgr-key], table.data[id], table.data').forEach(function (tbl) {
      if (tbl.closest('.modal') || tbl.closest('.sf-pop') || tbl.classList.contains('ba-table') || tbl.classList.contains('ba-meta')) return;
      init(tbl);
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', scanAndInit);
  } else {
    scanAndInit();
  }
})();
