/* recon-colorder.js — urutan kolom tabel rekonsiliasi (pindah kiri/kanan + drag header).
 * Menyimpan urutan di localStorage (sf-recon-order-v1), dan mengisi input #recon-export-cols
 * dengan urutan kolom yang tampil saat export. Kolom Aksi & checkbox tetap di posisi.
 * Bekerja bersama recon-colmgr.js (show/hide + lebar) tanpa menimpa state-nya.
 */
(function () {
  var tbl = document.getElementById('recon-table');
  if (!tbl) return;
  var KEY = 'sf-recon-order-v1';
  var FIXED = ['aksi'];              // tidak boleh dipindah
  var headRow = tbl.querySelector('thead tr');
  if (!headRow) return;

  function loadOrder() {
    try { return JSON.parse(localStorage.getItem(KEY) || '[]'); } catch (e) { return []; }
  }
  function saveOrder(arr) {
    try { localStorage.setItem(KEY, JSON.stringify(arr)); } catch (e) {}
  }

  // Ambil daftar kolom data-col (tanpa checkbox), urut sekarang.
  function currentKeys() {
    return [].slice.call(headRow.children)
      .map(function (th) { return th.getAttribute('data-col'); })
      .filter(Boolean);
  }

  // Terapkan urutan: pindahkan <th> dan sel <td> di setiap baris bersama-sama.
  function applyOrder(order) {
    if (!order || !order.length) return;
    var ths = [].slice.call(headRow.children);
    var byKey = {};
    ths.forEach(function (th) { var k = th.getAttribute('data-col'); if (k) byKey[k] = th; });
    // Kolom yang tidak ada di urutan tersimpan tetap di belakang sesuai urutan awal.
    var keys = order.filter(function (k) { return byKey[k]; });
    currentKeys().forEach(function (k) { if (keys.indexOf(k) < 0) keys.push(k); });
    // Checkbox select-all (kolom tanpa data-col) selalu paling kiri.
    var lead = ths.filter(function (th) { return !th.getAttribute('data-col'); });
    var rows = [].slice.call(tbl.querySelectorAll('tr'));
    rows.forEach(function (tr) {
      var cells = [].slice.call(tr.children);
      var map = {}, leadCells = [];
      cells.forEach(function (c) {
        var k = c.getAttribute('data-col');
        if (k) map[k] = c; else leadCells.push(c);
      });
      // colspan rows (pesan kosong) tidak punya data-col; biarkan.
      if (Object.keys(map).length === 0) return;
      leadCells.forEach(function (c) { tr.appendChild(c); });
      keys.forEach(function (k) { if (map[k]) tr.appendChild(map[k]); });
    });
    // Update urutan th sesuai keys (leading tetap di depan).
    lead.forEach(function (th) { headRow.appendChild(th); });
    keys.forEach(function (k) { if (byKey[k]) headRow.appendChild(byKey[k]); });
  }

  function move(key, dir) {
    var keys = currentKeys();
    var i = keys.indexOf(key);
    var j = i + dir;
    if (i < 0 || j < 0 || j >= keys.length) return;
    // Aksi selalu paling kanan: jangan geser melewatinya.
    if (FIXED.indexOf(keys[j]) >= 0) return;
    var t = keys[i]; keys[i] = keys[j]; keys[j] = t;
    saveOrder(keys);
    applyOrder(keys);
    refreshButtons();
  }

  // Panel: tambahkan tombol ◀ ▶ di setiap item kolom (di dalam .col-mgr-panel).
  var panel = document.getElementById('col-mgr-panel');
  function labelFor(k) {
    var th = headRow.querySelector('th[data-col="' + k + '"]');
    return th ? th.textContent.replace(/\s+/g, ' ').trim() : k;
  }
  function buildMoveButtons() {
    if (!panel) return;
    panel.querySelectorAll('.col-mgr-item').forEach(function (lab) {
      if (lab.querySelector('.col-mv')) return;
      var cb = lab.querySelector('input[data-col]');
      if (!cb) return;
      var k = cb.getAttribute('data-col');
      var wrap = document.createElement('span');
      wrap.className = 'col-mv';
      wrap.innerHTML = '<button type="button" class="col-mv-btn" data-dir="-1" aria-label="Geser ' + labelFor(k) + ' ke kiri"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><polyline points="15 18 9 12 15 6"/></svg></button>' +
                       '<button type="button" class="col-mv-btn" data-dir="1" aria-label="Geser ' + labelFor(k) + ' ke kanan"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><polyline points="9 18 15 12 9 6"/></svg></button>';
      wrap.setAttribute('data-key', k);
      lab.appendChild(wrap);
      wrap.addEventListener('click', function (e) {
        var b = e.target.closest('button[data-dir]');
        if (!b) return;
        e.preventDefault(); e.stopPropagation();
        move(k, parseInt(b.getAttribute('data-dir'), 10));
      });
    });
  }
  function refreshButtons() {
    if (!panel) return;
    var keys = currentKeys();
    panel.querySelectorAll('.col-mv').forEach(function (w) {
      var k = w.getAttribute('data-key');
      var i = keys.indexOf(k);
      var btns = w.querySelectorAll('button');
      btns[0].disabled = i <= 0;
      btns[1].disabled = i < 0 || i >= keys.length - 1 || keys[i + 1] === 'aksi';
    });
  }

  // Drag header: geser <th> ke posisi lain (hanya kolom data-col, bukan aksi).
  function enableDrag() {
    var dragKey = null;
    [].slice.call(headRow.querySelectorAll('th[data-col]')).forEach(function (th) {
      var k = th.getAttribute('data-col');
      if (FIXED.indexOf(k) >= 0) return;
      th.setAttribute('draggable', 'true');
      th.classList.add('recon-th-drag');
      th.title = (th.title ? th.title + ' · ' : '') + 'Seret untuk pindah kolom';
      th.addEventListener('dragstart', function (e) {
        // Jangan ikut drag saat menarik garis lebar (.col-resize).
        if (e.target && e.target.classList && e.target.classList.contains('col-resize')) { e.preventDefault(); return; }
        dragKey = k; th.classList.add('is-dragging');
        try { e.dataTransfer.setData('text/plain', k); e.dataTransfer.effectAllowed = 'move'; } catch (_) {}
      });
      th.addEventListener('dragend', function () { th.classList.remove('is-dragging'); dragKey = null; });
      th.addEventListener('dragover', function (e) { if (dragKey && dragKey !== k) { e.preventDefault(); th.classList.add('is-drop'); } });
      th.addEventListener('dragleave', function () { th.classList.remove('is-drop'); });
      th.addEventListener('drop', function (e) {
        e.preventDefault(); th.classList.remove('is-drop');
        if (!dragKey || dragKey === k) return;
        var keys = currentKeys();
        var from = keys.indexOf(dragKey), to = keys.indexOf(k);
        if (from < 0 || to < 0) return;
        keys.splice(from, 1); keys.splice(to, 0, dragKey);
        // Aksi tetap paling kanan.
        keys = keys.filter(function (x) { return x !== 'aksi'; }).concat(keys.indexOf('aksi') >= 0 ? ['aksi'] : []);
        saveOrder(keys); applyOrder(keys); refreshButtons();
      });
    });
  }

  // Sebelum export: isi cols dengan urutan + kolom tampil (display != none).
  var expForm = document.querySelector('form[action*="export-sap"]');
  if (expForm) {
    expForm.addEventListener('submit', function () {
      var inp = document.getElementById('recon-export-cols');
      if (!inp) return;
      var visible = currentKeys().filter(function (k) {
        var th = headRow.querySelector('th[data-col="' + k + '"]');
        return th && th.style.display !== 'none';
      });
      inp.value = visible.join(',');
    });
  }

  // Init
  var saved = loadOrder();
  applyOrder(saved);
  buildMoveButtons();
  refreshButtons();
  enableDrag();
  if (panel) {
    // Panel dibuat ulang oleh colmgr saat init; tambahkan tombol setelah itu.
    setTimeout(function () { buildMoveButtons(); refreshButtons(); }, 0);
  }
})();
