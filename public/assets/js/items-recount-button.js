/**
 * Tombol Recount untuk adminsto/poweradmin
 * Unlock items yang sudah posting agar bisa dihitung ulang
 */

document.addEventListener('DOMContentLoaded', function() {
    // Check user role dari meta tag atau global var
    const userRole = document.querySelector('meta[name="user-role"]')?.content || 
                     window.USER_ROLE || 
                     'unknown';
    
    // Hanya tampilkan untuk adminsto/poweradmin
    if (!['adminsto', 'poweradmin'].includes(userRole)) {
        return;
    }
    
    const toolbar = document.querySelector('.table-toolbar');
    if (!toolbar) return;
    
    // Buat tombol Recount
    const recountBtn = document.createElement('button');
    recountBtn.type = 'button';
    recountBtn.className = 'btn btn-warning btn-icon-only';
    recountBtn.innerHTML = '<svg width="16" height="16" fill="currentColor"><use xlink:href="#icon-unlock"/></svg>';
    recountBtn.id = 'btn-recount';
    recountBtn.title = 'Unlock item yang sudah posting untuk recount (adminsto only)';
    
    // Insert setelah tombol posting
    const postingBtn = document.getElementById('btn-posting');
    if (postingBtn) {
        postingBtn.parentNode.insertBefore(recountBtn, postingBtn.nextSibling);
    } else {
        toolbar.appendChild(recountBtn);
    }
    
    recountBtn.addEventListener('click', showRecountModal);
    
    function showRecountModal() {
        const checkedItems = Array.from(document.querySelectorAll('.memo-check:checked'));
        const allPostedItems = Array.from(document.querySelectorAll('#items-table tbody tr[data-item-id]'))
            .filter(row => {
                const st = row.getAttribute('data-count-status');
                if (st) return ['posted','submitted','audited','reconciled'].includes(st);
                const badge = row.querySelector('.badge');
                return badge && /Terposting|Dikirim|Diaudit|Selesai/.test(badge.textContent);
            });
        
        if (allPostedItems.length === 0 && checkedItems.length === 0) {
            showToast('Tidak ada item yang sudah diposting', 'info');
            return;
        }
        
        const modal = document.createElement('div');
        modal.className = 'sf-modal';
        modal.innerHTML = `
            <div class="sf-modal-backdrop"></div>
            <div class="sf-modal-content" style="max-width:500px;">
                <div class="sf-modal-header">
                    <h3>Cari Ulang (Recount)</h3>
                    <button type="button" class="sf-modal-close" aria-label="Tutup">&times;</button>
                </div>
                <div class="sf-modal-body">
                    <div style="padding:1rem;background:#fef3c7;border:1px solid #fbbf24;border-radius:6px;margin-bottom:1.5rem;">
                        <strong>Perhatian:</strong><br>
                        Item yang dipilih akan dibuka kuncinya agar bisa dihitung &amp; diposting ulang oleh observer. Status kembali ke "Sudah dihitung".
                    </div>
                    <form id="recount-form">
                        <label style="display:block;padding:0.75rem;border:2px solid #ddd;border-radius:6px;cursor:pointer;margin-bottom:0.75rem;">
                            <input type="radio" name="recount_mode" value="all" checked style="margin-right:0.5rem;">
                            <strong>Semua yang terposting</strong>
                            <div style="font-size:0.9rem;color:#666;margin-left:1.5rem;">${allPostedItems.length} item</div>
                        </label>
                        <label style="display:block;padding:0.75rem;border:2px solid #ddd;border-radius:6px;cursor:pointer;${checkedItems.length === 0 ? 'opacity:0.5;pointer-events:none;' : ''}">
                            <input type="radio" name="recount_mode" value="selected" ${checkedItems.length === 0 ? 'disabled' : ''} style="margin-right:0.5rem;">
                            <strong>Hanya yang dipilih</strong>
                            <div style="font-size:0.9rem;color:#666;margin-left:1.5rem;">${checkedItems.length} item dipilih</div>
                        </label>
                        <div style="margin-top:1.5rem;display:flex;gap:0.5rem;justify-content:flex-end;">
                            <button type="button" class="btn btn-ghost sf-modal-cancel">Batal</button>
                            <button type="submit" class="btn btn-warning">Unlock Sekarang</button>
                        </div>
                    </form>
                </div>
            </div>
        `;
        
        document.body.appendChild(modal);
        
        // Close handlers
        modal.querySelector('.sf-modal-close').addEventListener('click', () => modal.remove());
        modal.querySelector('.sf-modal-cancel').addEventListener('click', () => modal.remove());
        modal.querySelector('.sf-modal-backdrop').addEventListener('click', () => modal.remove());
        
        // Form submit
        modal.querySelector('#recount-form').addEventListener('submit', function(e) {
            e.preventDefault();
            const mode = this.recount_mode.value;
            
            let itemIds = [];
            if (mode === 'all') {
                itemIds = allPostedItems.map(row => row.getAttribute('data-item-id'));
            } else {
                itemIds = checkedItems.map(cb => cb.value);
            }
            
            if (itemIds.length === 0) {
                showToast('Tidak ada item yang akan di-unlock', 'error');
                return;
            }
            
            // Submit recount
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
            fetch('/items/recount', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrfToken
                },
                body: JSON.stringify({ item_ids: itemIds })
            })
            .then(res => res.ok ? res.json() : Promise.reject(res))
            .then(data => {
                showToast(`Berhasil unlock ${data.count || itemIds.length} item`, 'success');
                modal.remove();
                setTimeout(() => window.location.reload(), 1000);
            })
            .catch(err => {
                showToast('Gagal unlock data', 'error');
                console.error(err);
            });
        });
    }
    
    function showToast(message, type = 'info') {
        const toast = document.createElement('div');
        toast.className = `sf-toast sf-toast-${type}`;
        toast.textContent = message;
        toast.style.cssText = 'position:fixed;top:20px;right:20px;padding:1rem 1.5rem;background:#fff;border-radius:6px;box-shadow:0 4px 12px rgba(0,0,0,0.15);z-index:10000;';
        
        if (type === 'success') toast.style.borderLeft = '4px solid #10b981';
        if (type === 'error') toast.style.borderLeft = '4px solid #ef4444';
        if (type === 'info') toast.style.borderLeft = '4px solid #3b82f6';
        if (type === 'warning') toast.style.borderLeft = '4px solid #fbbf24';
        
        document.body.appendChild(toast);
        setTimeout(() => toast.remove(), 3000);
    }
});
