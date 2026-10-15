(function(){
  var tbl=document.getElementById('recon-table'); if(!tbl) return;
  var KEY='sf-recon-cols-v2';
  var MIN=40;
  var saved={hidden:{},width:{}};
  try{saved=Object.assign(saved,JSON.parse(localStorage.getItem(KEY)||'{}'));}catch(e){}
  var cfg=JSON.parse(JSON.stringify(saved)); // draft aktif (dipakai tabel)
  function persist(){try{localStorage.setItem(KEY,JSON.stringify(saved));}catch(e){}}
  var ths=[].slice.call(tbl.querySelectorAll('thead th[data-col]'));
  function colIndex(th){return [].indexOf.call(th.parentNode.children,th)+1;}
  function cellsOf(th){return tbl.querySelectorAll('tr > *:nth-child('+colIndex(th)+')');}
  function applyHidden(){
    ths.forEach(function(th){
      var off=!!cfg.hidden[th.getAttribute('data-col')];
      cellsOf(th).forEach(function(c){c.style.display=off?'none':'';});
    });
  }
  function applyWidth(){
    ths.forEach(function(th){
      var k=th.getAttribute('data-col'), w=cfg.width[k];
      cellsOf(th).forEach(function(c){
        if(!w){c.style.minWidth='';c.style.maxWidth='';c.style.width='';return;}
        c.style.minWidth=w+'px';c.style.maxWidth=w+'px';c.style.width=w+'px';
      });
    });
  }
  function apply(){applyHidden();applyWidth();}

  // Toolbar statis (di luar .table-wrap) -- dibuat dari elemen yang sudah ada
  var btn=document.getElementById('col-mgr-btn'), panel=document.getElementById('col-mgr-panel'), rb=document.getElementById('col-mgr-reset');
  var bar=document.getElementById('col-mgr');
  var wrap=tbl.parentNode;
  if(bar && wrap && bar.parentNode===wrap){ wrap.parentNode.insertBefore(bar, wrap); }
  /* col-mgr-bar dihapus: tata letak toolbar dikelola COLMGR-PRO */

  // Status ringkas + tombol Simpan/Batal di dalam panel
  var status=document.createElement('span'); status.className='col-mgr-status'; status.setAttribute('aria-live','polite');
  if(bar && rb) bar.insertBefore(status, rb);
  var footer=document.createElement('div'); footer.className='col-mgr-foot';
  var bCancel=document.createElement('button'); bCancel.type='button'; bCancel.className='btn btn-ghost btn-sm'; bCancel.textContent='Batal';
  var bSave=document.createElement('button'); bSave.type='button'; bSave.className='btn btn-brand btn-sm'; bSave.textContent='Simpan';
  footer.appendChild(bCancel); footer.appendChild(bSave);
  if(panel) panel.appendChild(footer);

  var inputs={};
  function isDirty(){return JSON.stringify(cfg)!==JSON.stringify(saved);}
  function refreshUI(){
    if(rb) rb.disabled = !Object.keys(saved.hidden).length && !Object.keys(saved.width).length;
    bSave.disabled = !isDirty(); bCancel.disabled = !isDirty();
    status.textContent = isDirty() ? 'Belum disimpan' : '';
    status.classList.toggle('is-dirty', isDirty());
  }

  ths.forEach(function(th){
    var k=th.getAttribute('data-col');
    if(panel && k!=='aksi'){
      var lab=document.createElement('label'); lab.className='col-mgr-item';
      var cb=document.createElement('input'); cb.type='checkbox'; cb.checked=!cfg.hidden[k]; cb.setAttribute('data-col',k);
      cb.addEventListener('change',function(){cfg.hidden[k]=!cb.checked; applyHidden(); refreshUI();});
      inputs[k]=cb;
      lab.appendChild(cb); lab.appendChild(document.createTextNode(' '+th.textContent.trim()));
      panel.insertBefore(lab, footer);
    }

    var label=th.textContent.trim();
    var h=document.createElement('span'); h.className='col-resize';
    h.setAttribute('role','separator'); h.setAttribute('aria-orientation','vertical');
    h.setAttribute('aria-label','Ubah lebar kolom '+label); h.setAttribute('tabindex','0');
    h.title='Geser untuk ubah lebar. Panah kiri/kanan = 8px (Shift = 1px). Klik ganda = lebar otomatis.';
    th.appendChild(h);

    function setW(w){cfg.width[k]=Math.max(MIN,Math.round(w)); applyWidth(); refreshUI();}

    h.addEventListener('pointerdown',function(e){
      if(e.button!==undefined && e.button!==0) return;
      e.preventDefault(); e.stopPropagation();
      var startX=e.clientX, startW=th.getBoundingClientRect().width;
      h.classList.add('is-drag'); document.body.classList.add('sf-colresizing');
      try{h.setPointerCapture(e.pointerId);}catch(_){}
      function mv(ev){setW(startW+(ev.clientX-startX));}
      function up(ev){
        h.removeEventListener('pointermove',mv); h.removeEventListener('pointerup',up); h.removeEventListener('pointercancel',up);
        h.classList.remove('is-drag'); document.body.classList.remove('sf-colresizing');
        try{h.releasePointerCapture(ev.pointerId);}catch(_){}
      }
      h.addEventListener('pointermove',mv); h.addEventListener('pointerup',up); h.addEventListener('pointercancel',up);
    });
    h.addEventListener('keydown',function(e){
      if(e.key==='ArrowLeft'||e.key==='ArrowRight'){
        e.preventDefault();
        var step=e.shiftKey?1:8, cur=cfg.width[k]||th.getBoundingClientRect().width;
        setW(cur+(e.key==='ArrowRight'?step:-step));
      }
    });
    h.addEventListener('dblclick',function(e){e.preventDefault(); e.stopPropagation(); delete cfg.width[k]; applyWidth(); refreshUI();});
    h.addEventListener('click',function(e){e.stopPropagation();});
  });

  function syncInputs(){Object.keys(inputs).forEach(function(k){inputs[k].checked=!cfg.hidden[k];});}

  bSave.addEventListener('click',function(){
    saved=JSON.parse(JSON.stringify(cfg)); persist(); refreshUI();
    status.textContent='Tersimpan';
    setTimeout(function(){ if(!isDirty()) status.textContent=''; },1500);
    if(panel){panel.hidden=true; btn.setAttribute('aria-expanded','false');}
  });
  bCancel.addEventListener('click',function(){
    cfg=JSON.parse(JSON.stringify(saved)); apply(); syncInputs(); refreshUI();
  });

  if(btn && panel){
    btn.addEventListener('click',function(){var o=panel.hidden; panel.hidden=!o; btn.setAttribute('aria-expanded',String(o));});
    document.addEventListener('click',function(e){
      if(!panel.hidden && !panel.contains(e.target) && !btn.contains(e.target)){ panel.hidden=true; btn.setAttribute('aria-expanded','false'); }
    });
    document.addEventListener('keydown',function(e){ if(e.key==='Escape' && !panel.hidden){ panel.hidden=true; btn.setAttribute('aria-expanded','false'); btn.focus(); } });
  }
  if(rb) rb.addEventListener('click',function(){
    if(rb.dataset.armed!=='1'){
      rb.dataset.armed='1'; rb.textContent='Yakin?'; rb.classList.add('is-armed');
      clearTimeout(rb._t); rb._t=setTimeout(disarm,3000); return;
    }
    disarm();
    cfg={hidden:{},width:{}}; saved={hidden:{},width:{}}; persist();
    tbl.querySelectorAll('tr > *').forEach(function(c){c.style.display='';c.style.width='';c.style.minWidth='';c.style.maxWidth='';});
    syncInputs(); refreshUI();
  });

  function disarm(){ if(!rb) return; rb.dataset.armed=''; rb.textContent='Reset'; rb.classList.remove('is-armed'); }
  apply(); refreshUI();
})();
