// Progress bar dan bulk delete untuk Master Import
(function() {
    const progressBar = document.getElementById('master-import-progress');
    const progressText = document.getElementById('master-import-progress-text');
    const progressContainer = document.getElementById('master-import-progress-container');
    const masterForm = document.getElementById('master-import-form');
    const bulkDeleteBtn = document.getElementById('bulk-delete-master-btn');
    
    if (!masterForm) return;
    
    // Progress tracking via SSE
    masterForm.addEventListener('submit', function(e) {
        const mode = document.querySelector('input[name="mode"]:checked')?.value;
        if (mode !== 'commit') return; // Dry-run tidak perlu progress
        
        progressContainer.style.display = 'block';
        progressBar.style.width = '0%';
        progressText.textContent = 'Memulai import...';
        
        const eventSource = new EventSource('/import/progress-master');
        
        eventSource.onmessage = function(e) {
            const data = JSON.parse(e.data);
            
            if (data.done) {
                eventSource.close();
                progressBar.style.width = '100%';
                progressText.textContent = data.success ? 'Import selesai!' : 'Import gagal.';
                progressBar.classList.remove('bg-blue-500');
                progressBar.classList.add(data.success ? 'bg-green-500' : 'bg-red-500');
                return;
            }
            
            progressBar.style.width = data.percent + '%';
            progressText.textContent = `Processing ${data.sheet}: ${data.current}/${data.total} (${data.percent}%)`;
        };
        
        eventSource.onerror = function() {
            eventSource.close();
            progressText.textContent = 'Koneksi terputus. Refresh untuk melihat hasil.';
        };
    });
    
    // Bulk delete dengan 3x verifikasi
    if (bulkDeleteBtn) {
        bulkDeleteBtn.addEventListener('click', function() {
            showBulkDeleteModal();
        });
    }
    
    function showBulkDeleteModal() {
        const backdrop = document.createElement('div');
        backdrop.className = 'sf-delete-modal-backdrop';
        backdrop.style.cssText = `
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.6);
            backdrop-filter: blur(8px);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            padding: 1rem;
        `;
        
        const modal = document.createElement('div');
        modal.className = 'sf-delete-modal';
        modal.style.cssText = `
            background: white;
            border-radius: 12px;
            max-width: 500px;
            width: 100%;
            padding: 2rem;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        `;
        
        modal.innerHTML = `
            <div style="display: flex; align-items: flex-start; gap: 1rem; margin-bottom: 1.5rem;">
                <div style="width: 48px; height: 48px; border-radius: 50%; background: #fee; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                    <svg width="24" height="24" fill="#dc2626" viewBox="0 0 24 24">
                        <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/>
                    </svg>
                </div>
                <div style="flex: 1;">
                    <h3 style="font-size: 1.25rem; font-weight: 700; color: #111; margin: 0 0 0.5rem 0;">
                        Hapus Semua Data Master
                    </h3>
                    <p style="color: #666; font-size: 0.95rem; line-height: 1.5; margin: 0;">
                        Tindakan ini akan menghapus <strong>semua group, items, locations, devices, audits, dan discrepancies</strong> yang Anda miliki akses. <strong>Tidak bisa dibatalkan!</strong>
                    </p>
                </div>
            </div>
            
            <div style="background: #fef3c7; border-left: 4px solid #f59e0b; padding: 1rem; border-radius: 6px; margin-bottom: 1.5rem;">
                <p style="font-size: 0.9rem; color: #92400e; margin: 0; font-weight: 500;">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-3px;margin-right:6px;" aria-hidden="true"><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>Ketik 3 kata berikut dengan HURUF BESAR untuk konfirmasi:
                </p>
            </div>
            
            <form id="bulk-delete-form" style="display: flex; flex-direction: column; gap: 1rem;">
                <div>
                    <label style="display: block; font-size: 0.875rem; font-weight: 600; color: #374151; margin-bottom: 0.5rem;">
                        1. Ketik: HAPUS
                    </label>
                    <input type="text" name="confirm1" required 
                           style="width: 100%; padding: 0.75rem; border: 2px solid #d1d5db; border-radius: 8px; font-size: 1rem; font-weight: 600; letter-spacing: 0.05em;"
                           placeholder="HAPUS" autocomplete="off">
                </div>
                
                <div>
                    <label style="display: block; font-size: 0.875rem; font-weight: 600; color: #374151; margin-bottom: 0.5rem;">
                        2. Ketik: SEMUA
                    </label>
                    <input type="text" name="confirm2" required 
                           style="width: 100%; padding: 0.75rem; border: 2px solid #d1d5db; border-radius: 8px; font-size: 1rem; font-weight: 600; letter-spacing: 0.05em;"
                           placeholder="SEMUA" autocomplete="off">
                </div>
                
                <div>
                    <label style="display: block; font-size: 0.875rem; font-weight: 600; color: #374151; margin-bottom: 0.5rem;">
                        3. Ketik: DATA
                    </label>
                    <input type="text" name="confirm3" required 
                           style="width: 100%; padding: 0.75rem; border: 2px solid #d1d5db; border-radius: 8px; font-size: 1rem; font-weight: 600; letter-spacing: 0.05em;"
                           placeholder="DATA" autocomplete="off">
                </div>
                
                <div style="display: flex; gap: 0.75rem; margin-top: 1rem;">
                    <button type="button" class="cancel-btn" 
                            style="flex: 1; padding: 0.75rem 1.5rem; border: 2px solid #d1d5db; background: white; color: #374151; border-radius: 8px; font-weight: 600; cursor: pointer; transition: all 0.2s;">
                        Batal
                    </button>
                    <button type="submit" 
                            style="flex: 1; padding: 0.75rem 1.5rem; background: #dc2626; color: white; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; transition: all 0.2s;">
                        Hapus Sekarang
                    </button>
                </div>
            </form>
        `;
        
        backdrop.appendChild(modal);
        document.body.appendChild(backdrop);
        
        // Auto-focus first input
        setTimeout(() => modal.querySelector('input[name="confirm1"]').focus(), 100);
        
        // Close handlers
        backdrop.addEventListener('click', function(e) {
            if (e.target === backdrop) closeModal();
        });
        
        modal.querySelector('.cancel-btn').addEventListener('click', closeModal);
        
        function closeModal() {
            backdrop.remove();
        }
        
        // Form submit
        const form = modal.querySelector('#bulk-delete-form');
        form.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const formData = new FormData(form);
            formData.append('_token', document.querySelector('input[name="_token"]').value);
            
            const submitBtn = form.querySelector('button[type="submit"]');
            submitBtn.disabled = true;
            submitBtn.textContent = 'Menghapus...';
            
            try {
                const response = await fetch('/import/bulk-delete-master', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    closeModal();
                    showToast('success', result.message + ` (Items: ${result.deleted.items}, Groups: ${result.deleted.groups})`);
                    setTimeout(() => location.reload(), 2000);
                } else {
                    showToast('error', result.message);
                    submitBtn.disabled = false;
                    submitBtn.textContent = 'Hapus Sekarang';
                }
            } catch (err) {
                showToast('error', 'Gagal menghapus data: ' + err.message);
                submitBtn.disabled = false;
                submitBtn.textContent = 'Hapus Sekarang';
            }
        });
    }
    
    function showToast(type, message) {
        if (typeof window.sfToast === 'function') {
            window.sfToast(message, type);
        } else {
            alert(message);
        }
    }
})();
