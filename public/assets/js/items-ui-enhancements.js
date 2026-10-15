/**
 * UI Enhancements untuk Data STO
 * - Konfirmasi popup untuk tombol reset (clear filter & reset kolom)
 * - Checkbox "Pilih Semua" functionality
 */

(function() {
    'use strict';
    
    // === 1. KONFIRMASI POPUP UNTUK CLEAR FILTER ===
    const clearAllBtn = document.getElementById('col-clear-all-confirm');
    if (clearAllBtn) {
        clearAllBtn.addEventListener('click', function(e) {
            e.preventDefault();
            
            if (confirm('Hapus semua filter dan urutan?\n\nSemua filter kolom, pencarian lanjutan, dan pengurutan akan dihapus.')) {
                window.location.href = window.location.pathname;
            }
        });
    }
    
    // === 2. KONFIRMASI POPUP UNTUK RESET KOLOM ===
    const resetColBtn = document.getElementById('items-reset-confirm');
    if (resetColBtn) {
        resetColBtn.addEventListener('click', function(e) {
            e.preventDefault();
            
            if (confirm('Reset kolom ke pengaturan default?\n\nUrutan dan visibilitas kolom akan dikembalikan ke default.')) {
                // Clear localStorage untuk kolom
                localStorage.removeItem('items-v1-columns');
                localStorage.removeItem('items-v1-widths');
                localStorage.removeItem('sto-column-order');
                
                // Show toast
                if (typeof window.sfToast === 'function') {
                    window.sfToast('Kolom direset ke default', 'success');
                }
                
                // Reload halaman
                setTimeout(function() {
                    window.location.reload();
                }, 500);
            }
        });
    }
    
    // === 3. CHECKBOX "PILIH SEMUA" ===
    const selectAllCheckbox = document.getElementById('table-select-all');
    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener('change', function() {
            const isChecked = this.checked;
            const table = document.getElementById('items-table');
            if (!table) return;
            
            // Cari semua checkbox di tbody yang visible (tidak tersembunyi oleh filter)
            const rows = table.querySelectorAll('tbody tr');
            let checkedCount = 0;
            
            rows.forEach(function(row) {
                // Skip jika row disembunyikan oleh filter (sf-ui.js)
                if (window.sfRowIsVisible && !window.sfRowIsVisible(row)) {
                    return;
                }
                
                const checkbox = row.querySelector('input[type="checkbox"][name="item_ids[]"]');
                if (checkbox && !checkbox.disabled) {
                    checkbox.checked = isChecked;
                    if (isChecked) checkedCount++;
                }
            });
            
            // Update indeterminate state
            selectAllCheckbox.indeterminate = false;
            
            // Show toast
            if (isChecked && checkedCount > 0 && typeof window.sfToast === 'function') {
                window.sfToast(checkedCount + ' item dipilih', 'info');
            }
            
            // Trigger custom event untuk komponen lain (misalnya bulk action toolbar)
            document.dispatchEvent(new CustomEvent('items:selection-changed', {
                detail: { count: checkedCount, all: isChecked }
            }));
        });
        
        // Update indeterminate state saat checkbox individual diubah
        const table = document.getElementById('items-table');
        if (table) {
            table.addEventListener('change', function(e) {
                if (e.target.type === 'checkbox' && e.target.name === 'item_ids[]') {
                    updateSelectAllState();
                }
            });
        }
    }
    
    function updateSelectAllState() {
        if (!selectAllCheckbox) return;
        
        const table = document.getElementById('items-table');
        if (!table) return;
        
        const rows = table.querySelectorAll('tbody tr');
        let totalVisible = 0;
        let totalChecked = 0;
        
        rows.forEach(function(row) {
            // Skip jika row disembunyikan oleh filter
            if (window.sfRowIsVisible && !window.sfRowIsVisible(row)) {
                return;
            }
            
            const checkbox = row.querySelector('input[type="checkbox"][name="item_ids[]"]');
            if (checkbox && !checkbox.disabled) {
                totalVisible++;
                if (checkbox.checked) totalChecked++;
            }
        });
        
        if (totalVisible === 0) {
            selectAllCheckbox.checked = false;
            selectAllCheckbox.indeterminate = false;
        } else if (totalChecked === 0) {
            selectAllCheckbox.checked = false;
            selectAllCheckbox.indeterminate = false;
        } else if (totalChecked === totalVisible) {
            selectAllCheckbox.checked = true;
            selectAllCheckbox.indeterminate = false;
        } else {
            selectAllCheckbox.checked = false;
            selectAllCheckbox.indeterminate = true;
        }
    }
    
    // Update state on page load
    if (selectAllCheckbox) {
        setTimeout(updateSelectAllState, 100);
    }
    
    // === 4. INTEGRASI DENGAN SF-UI ROW VISIBILITY ===
    // Ketika filter berubah (sf-ui.js), update select-all state
    document.addEventListener('sf:rowvis', function() {
        if (selectAllCheckbox) {
            updateSelectAllState();
        }
    });
    
    console.log('[items-ui-enhancements] Loaded: confirm dialogs + select-all checkbox');
})();
