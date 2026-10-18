/**
 * Barcode Scanner + Posting Modal + Count Confirmation Dialog untuk Data STO
 * Progressive UI/UX Pro Max Edition
 * 
 * Fitur:
 * 1. Auto-detect barcode scanner input
 * 2. Pop-up modern konfirmasi simpan hitungan (Aksi Cek List)
 * 3. Stepper interaktif [-] dan [+] untuk penyesuaian QTY cepat
 * 4. Detail spesifikasi barang (Kode, Nama, Group, Lokasi, Satuan, Kategori)
 * 5. Update baris tabel real-time tanpa reload seluruh halaman
 * 6. Tombol Posting dengan modal pilihan (semua/terpilih)
 * 7. Pengaturan Barcode Scanner (modal setting)
 */

document.addEventListener("DOMContentLoaded", function () {
    // Check flash toast & row highlight
    try {
        const flashToast = sessionStorage.getItem("sf_flash_toast");
        if (flashToast) {
            sessionStorage.removeItem("sf_flash_toast");
            const parsed = JSON.parse(flashToast);
            if (parsed && parsed.message) {
                setTimeout(() => showToast(parsed.message, parsed.type || "success"), 250);
            }
        }
        const urlParams = new URLSearchParams(window.location.search);
        const highlightId = urlParams.get("highlight");
        if (highlightId) {
            const targetRow = document.querySelector("#items-table tbody tr[data-item-id=\"" + highlightId + "\"]");
            if (targetRow) {
                setTimeout(() => {
                    targetRow.scrollIntoView({ behavior: "smooth", block: "center" });
                    targetRow.classList.add("highlight-flash-success");
                }, 200);
            }
        }
    } catch (e) {
        console.error("[INIT FLASH/HIGHLIGHT ERROR]", e);
    }

    // === BARCODE SCANNER AUTO-DETECT (server-side lookup) ===
    const SCANNER_CONFIG = {
        timeout: parseInt(localStorage.getItem("sto-scanner-timeout") || "150", 10),
        minLength: parseInt(localStorage.getItem("sto-scanner-minlength") || "3", 10),
        debug: localStorage.getItem("sto-scanner-debug") === "true"
    };

    let barcodeBuffer = "";
    let barcodeTimeout = null;
    let lookupInFlight = false;

    if (SCANNER_CONFIG.debug) {
        console.log("[BARCODE CONFIG]", SCANNER_CONFIG);
    }

    // Phase CAPTURE + stopImmediatePropagation supaya tidak bertabrakan dengan
    // keyboard-wedge lain (app.js) yang men-submit form pencarian.
    document.addEventListener("keydown", function (e) {
        if (countConfirmOpen) return; // Izinkan scan di input field (timing detection cukup)

        var now = Date.now();
        if (now - lastKeyAt > 120) barcodeBuffer = "";
        lastKeyAt = now;

        if (e.key === "Enter") {
            if (barcodeBuffer.length >= SCANNER_CONFIG.minLength) {
                clearTimeout(barcodeTimeout);
                var code = barcodeBuffer.trim();
                barcodeBuffer = "";
                e.preventDefault();
                e.stopImmediatePropagation();
                lookupAndCount(code);
            }
            return;
        }

        if (e.key.length === 1) {
            barcodeBuffer += e.key;
            clearTimeout(barcodeTimeout);
            barcodeTimeout = setTimeout(function () {
                if (barcodeBuffer.length >= SCANNER_CONFIG.minLength) {
                    var code = barcodeBuffer.trim();
                    barcodeBuffer = "";
                    lookupAndCount(code);
                } else {
                    barcodeBuffer = "";
                }
            }, SCANNER_CONFIG.timeout);
        }
    }, true);

    let lastKeyAt = 0;
    let countConfirmOpen = false;

    // Expose lookup scanner globally agar bisa dipanggil dari Camera Scanner (app.js) dan Keyboard Wedge (sf-ui.js)
    window.stoHandleBarcodeScan = function(code) {
        lookupAndCount(code);
    };

    function isTypingTarget(el) {
        if (!el) return false;
        var tag = (el.tagName || "").toUpperCase();
        return tag === "INPUT" || tag === "TEXTAREA" || tag === "SELECT" || el.isContentEditable === true;
    }

    /** Cari item di server (bukan DOM) lalu buka dialog konfirmasi hitung. */
    function lookupAndCount(code) {
        code = (code || "").trim();
        if (!code || lookupInFlight) return;
        lookupInFlight = true;

        if (SCANNER_CONFIG.debug) console.log("[BARCODE] lookup:", code);
        showToast("Mencari " + code + "…", "info");

        fetch("/items/lookup?code=" + encodeURIComponent(code), {
            headers: { "X-Requested-With": "XMLHttpRequest" },
            credentials: "same-origin"
        })
        .then(function (res) { return res.json().catch(function () { return { found: false, message: "Respons tidak valid dari server." }; }); })
        .then(function (data) {
            lookupInFlight = false;
            if (!data || !data.found) {
                showNotFoundModal(code, (data && data.message) || "Barang tidak ditemukan di database.");
                return;
            }

            var item = data.item;

            // Highlight baris bila item kebetulan ada di DOM halaman aktif.
            var row = document.querySelector('#items-table tbody tr[data-item-id="' + item.id + '"]');
            if (row) {
                row.classList.remove("highlight-flash-success");
                void row.offsetWidth;
                row.classList.add("highlight-flash-success");
                if (row.scrollIntoView) row.scrollIntoView({ block: "center", behavior: "smooth" });
            }

            if (item.is_locked) {
                showToast("Barang " + item.code + " terkunci (sudah diposting). Ajukan unlock untuk mengubah.", "error");
                return;
            }
            if (data.can_count === false) {
                showToast("Role Anda tidak memiliki izin menghitung data.", "error");
                return;
            }

            showCountConfirmModal({
                itemId: String(item.id),
                code: item.code,
                name: item.name,
                group: item.group,
                location: item.location,
                device: item.device,
                unit: item.unit,
                category: item.category,
                subInventory: item.sub_inventory,
                expectedQty: item.can_see_qty ? item.expected_qty : null,
                countStatus: item.count_status,
                needsRecount: item.needs_recount,
                conditionNote: item.condition_note || "",
                qty: (item.counted_qty === null || item.counted_qty === undefined) ? "" : String(item.counted_qty)
            });
        })
        .catch(function (err) {
            lookupInFlight = false;
            console.error("[BARCODE LOOKUP ERROR]", err);
            showToast("Gagal mencari barang. Periksa koneksi lalu coba lagi.", "error");
        });
    }

    function searchItemByCode(code) {
        // Fallback: dipakai kalau ada pemanggil lama. Cari dulu di DOM,
        // kalau tidak ada langsung ke server.
        const rows = document.querySelectorAll("#items-table tbody tr[data-item-id]");
        for (const row of rows) {
            const rowCode = row.getAttribute("data-item-code") || row.querySelector(".kbd")?.textContent.trim();
            if (rowCode && rowCode.toLowerCase() === String(code).toLowerCase()) {
                row.classList.remove("highlight-flash-success");
                void row.offsetWidth;
                row.classList.add("highlight-flash-success");
                showCountConfirmModal(row);
                return;
            }
        }
        lookupAndCount(code);
    }

    // === POP-UP MODERN KONFIRMASI HITUNG BARANG (UI/UX PRO MAX) ===
    function showCountConfirmModal(rowOrData) {
        let data = {};
        if (rowOrData instanceof Element) {
            const row = rowOrData;
            const itemId = row.getAttribute("data-item-id") || "";
            const code = row.getAttribute("data-item-code") || row.querySelector(".kbd")?.textContent.trim() || "";
            const name = row.getAttribute("data-item-name") || row.querySelector(".cell-title a")?.textContent.trim() || "";
            const group = row.getAttribute("data-item-group") || row.querySelector("td[data-label=\"Group\"]")?.textContent.trim() || "-";
            const location = row.getAttribute("data-item-location") || row.querySelector("td[data-label=\"Lokasi\"]")?.textContent.trim() || "-";
            const unit = row.getAttribute("data-item-unit") || row.querySelector("td[data-label=\"Satuan\"]")?.textContent.trim() || "UNIT";
            const category = row.getAttribute("data-item-category") || row.querySelector("td[data-label=\"Kategori\"]")?.textContent.trim() || "-";
            const countStatus = row.getAttribute("data-count-status") || "pending";

            let qty = "";
            const inputEl = row.querySelector("input[name=\"counted_qty\"]");
            if (inputEl && inputEl.value !== "") {
                qty = inputEl.value;
            } else if (row.getAttribute("data-counted-qty")) {
                qty = row.getAttribute("data-counted-qty");
            } else {
                const tdH = row.querySelector("td[data-label=\"Hitung\"]");
                const t = tdH?.textContent.trim();
                if (t && t !== "—" && t !== "-") qty = t;
            }

            data = { itemId, code, name, group, location, unit, category, countStatus, qty, row };
        } else {
            data = rowOrData;
        }

        if (!data.itemId) return;

        // Tutup dialog lama bila masih terbuka
        const existing = document.getElementById("count-confirm-modal");
        if (existing) existing.remove();

        // Status badge mapping
        let statusLabel = "Belum Dihitung";
        let statusClass = "status-pending";
        if (data.countStatus === "counted") {
            statusLabel = "Sudah Dihitung";
            statusClass = "status-counted";
        } else if (data.countStatus === "recount") {
            statusLabel = "Dihitung Ulang";
            statusClass = "status-recount";
        } else if (data.countStatus === "posted") {
            statusLabel = "Terposting";
            statusClass = "status-posted";
        }

        const initialQty = (data.qty !== "" && !isNaN(parseInt(data.qty, 10))) ? parseInt(data.qty, 10) : 0;

        const modal = document.createElement("div");
        modal.className = "sf-count-dialog-backdrop";
        modal.id = "count-confirm-modal";
        modal.setAttribute("role", "dialog");
        modal.setAttribute("aria-modal", "true");
        modal.setAttribute("aria-labelledby", "sf-count-dialog-title");

        modal.innerHTML = `
            <div class="sf-count-dialog">
                <div class="sf-count-dialog-head">
                    <div class="sf-count-dialog-icon">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                            <polyline points="22 4 12 14.01 9 11.01"></polyline>
                        </svg>
                    </div>
                    <div class="sf-count-dialog-titles">
                        <h3 class="sf-count-dialog-title" id="sf-count-dialog-title">Konfirmasi Hasil Hitung</h3>
                        <p class="sf-count-dialog-sub">Periksa detail barang &amp; jumlah sebelum disimpan</p>
                    </div>
                    <button type="button" class="sf-count-dialog-close" aria-label="Tutup dialog">&times;</button>
                </div>

                <div class="sf-count-dialog-body">
                    <!-- Detail Informasi Barang -->
                    <div class="sf-count-item-card">
                        <div class="sf-count-item-top">
                            <span class="sf-chip-code">${escapeHtml(data.code || "-")}</span>
                            <span class="sf-status-pill ${statusClass}">${statusLabel}</span>
                        </div>
                        <div class="sf-count-item-name">${escapeHtml(data.name || "-")}</div>
                        <div class="sf-count-meta-grid">
                            <div class="sf-meta-chip" title="Group: ${escapeHtml(data.group || "-")}">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>
                                <span><strong>Group:</strong> ${escapeHtml((data.group || "-").replace(/^Group\s+/i, ""))}</span>
                            </div>
                            <div class="sf-meta-chip" title="Lokasi: ${escapeHtml(data.location || "-")}">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                                <span><strong>Lokasi:</strong> ${escapeHtml(data.location || "-")}</span>
                            </div>
                            <div class="sf-meta-chip" title="Satuan: ${escapeHtml(data.unit || "UNIT")}">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="7" width="20" height="14" rx="2" ry="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/></svg>
                                <span><strong>Satuan:</strong> ${escapeHtml(data.unit || "UNIT")}</span>
                            </div>
                            <div class="sf-meta-chip" title="Kategori: ${escapeHtml(data.category || "-")}">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/></svg>
                                <span><strong>Kategori:</strong> ${escapeHtml(data.category || "-")}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Highlight QTY Card with Stepper -->
                    <div class="sf-qty-hero-card">
                        <div class="sf-qty-head-label">
                            <span class="sf-qty-badge-pill">QTY FISIK</span>
                            <span>JUMLAH HASIL HITUNG</span>
                        </div>
                        <div class="sf-qty-stepper-row">
                            <button type="button" class="sf-qty-btn sf-qty-minus" aria-label="Kurangi QTY">&minus;</button>
                            <input type="number" id="sf-modal-qty-input" class="sf-qty-input" min="0" step="1" value="${initialQty}" placeholder="0" required />
                            <button type="button" class="sf-qty-btn sf-qty-plus" aria-label="Tambah QTY">&plus;</button>
                        </div>
                        <div class="sf-qty-unit-tag">${escapeHtml(data.unit || "UNIT")}</div>
                    </div>

                    <!-- Catatan Kondisi (Opsional) -->
                    <div class="sf-note-group">
                        <label class="sf-note-label" for="sf-modal-note-input">Catatan Kondisi Fisik (Opsional):</label>
                        <input type="text" id="sf-modal-note-input" class="sf-note-input" placeholder="Contoh: Segel utuh, barang baik..." />
                    </div>
                </div>

                <!-- Footer Aksi -->
                <div class="sf-count-dialog-foot">
                    <button type="button" class="sf-btn-cancel">Batal</button>
                    <button type="button" class="sf-btn-submit" id="sf-modal-submit-btn">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="20 6 9 17 4 12"></polyline>
                        </svg>
                        <span>Simpan Hitungan</span>
                    </button>
                </div>
            </div>
        `;

        document.body.appendChild(modal);
        countConfirmOpen = true;

        const qtyInput = modal.querySelector("#sf-modal-qty-input");
        const noteInput = modal.querySelector("#sf-modal-note-input");
        const submitBtn = modal.querySelector("#sf-modal-submit-btn");
        const minusBtn = modal.querySelector(".sf-qty-minus");
        const plusBtn = modal.querySelector(".sf-qty-plus");
        const closeBtn = modal.querySelector(".sf-count-dialog-close");
        const cancelBtn = modal.querySelector(".sf-btn-cancel");

        setTimeout(() => {
            qtyInput?.focus();
            qtyInput?.select();
        }, 80);

        function closeModal() {
            countConfirmOpen = false;
            modal.style.opacity = "0";
            modal.style.transition = "opacity 140ms ease-out";
            setTimeout(() => {
                if (modal.parentNode) modal.remove();
            }, 140);
        }

        closeBtn.addEventListener("click", closeModal);
        cancelBtn.addEventListener("click", closeModal);
        modal.addEventListener("click", function (e) {
            if (e.target === modal) closeModal();
        });

        minusBtn.addEventListener("click", function () {
            let v = parseInt(qtyInput.value, 10);
            if (isNaN(v) || v <= 0) v = 0;
            else v -= 1;
            qtyInput.value = v;
        });

        plusBtn.addEventListener("click", function () {
            let v = parseInt(qtyInput.value, 10);
            if (isNaN(v) || v < 0) v = 0;
            v += 1;
            qtyInput.value = v;
        });

        function doSubmit() {
            let v = parseInt(qtyInput.value, 10);
            if (isNaN(v) || v < 0) {
                showToast("Masukkan jumlah hitung yang valid (minimal 0)", "error");
                qtyInput.focus();
                return;
            }

            submitBtn.disabled = true;
            submitBtn.innerHTML = "<span class=\"sf-spinner\"></span> <span>Menyimpan...</span>";

            const csrfToken = document.querySelector("meta[name=\"csrf-token\"]")?.content ||
                              document.querySelector("input[name=\"_token\"]")?.value || "";

            const formData = new FormData();
            formData.append("_token", csrfToken);
            formData.append("counted_qty", v.toString());
            const noteVal = noteInput.value.trim();
            if (noteVal) {
                formData.append("condition_note", noteVal);
            }

            fetch("/items/" + data.itemId + "/count", {
                method: "POST",
                headers: {
                    "X-Requested-With": "XMLHttpRequest"
                },
                body: formData
            })
            .then(res => {
                if (!res.ok) {
                    return res.json().catch(() => ({ message: "Gagal menyimpan hasil hitung (" + res.status + ")" })).then(err => Promise.reject(err));
                }
                return res.json();
            })
            .then(resData => {
                closeModal();

                // Simpan flash toast untuk ditampilkan setelah refresh
                sessionStorage.setItem("sf_flash_toast", JSON.stringify({
                    message: "Hasil hitung " + (data.code || "") + " tersimpan: " + v + " " + (data.unit || "UNIT") + ".",
                    type: "success"
                }));

                // TETAP DI HALAMAN & TAB SAAT INI (TIDAK PINDAH TAB)
                window.location.reload();
            })
            .catch(err => {
                submitBtn.disabled = false;
                submitBtn.innerHTML = `
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="20 6 9 17 4 12"></polyline>
                    </svg>
                    <span>Simpan Hitungan</span>
                `;
                showToast(err.message || "Gagal menyimpan hasil hitung", "error");
                console.error("[COUNT SUBMIT ERROR]", err);
            });
        }

        submitBtn.addEventListener("click", doSubmit);

        qtyInput.addEventListener("keydown", function (e) {
            if (e.key === "Enter") {
                e.preventDefault();
                doSubmit();
            } else if (e.key === "Escape") {
                e.preventDefault();
                closeModal();
            }
        });

        noteInput.addEventListener("keydown", function (e) {
            if (e.key === "Enter") {
                e.preventDefault();
                doSubmit();
            } else if (e.key === "Escape") {
                e.preventDefault();
                closeModal();
            }
        });

        function onEsc(e) {
            if (e.key === "Escape") {
                closeModal();
                document.removeEventListener("keydown", onEsc);
            }
        }
        document.addEventListener("keydown", onEsc);
    }

    /** Show "not found" modal when barcode scan finds no item */
    function showNotFoundModal(code, message) {
        var backdrop = document.createElement("div");
        backdrop.className = "sf-modal-backdrop";
        backdrop.style.cssText = "position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.5);backdrop-filter:blur(4px);z-index:9999;display:flex;align-items:center;justify-content:center;";
        
        var box = document.createElement("div");
        box.style.cssText = "background:#fff;border-radius:12px;padding:1.5rem;max-width:400px;width:90%;box-shadow:0 20px 25px -5px rgba(0,0,0,0.1);";
        
        box.innerHTML = `
            <div style="text-align:center;">
                <div style="width:64px;height:64px;margin:0 auto 1rem;background:#fee;border-radius:50%;display:flex;align-items:center;justify-content:center;">
                    <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#dc2626" stroke-width="2">
                        <circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/>
                    </svg>
                </div>
                <h3 style="margin:0 0 0.5rem;font-size:1.25rem;font-weight:600;color:#1f2937;">Tidak Ditemukan</h3>
                <p style="margin:0 0 0.5rem;color:#6b7280;font-size:0.95rem;">${message}</p>
                <p style="margin:0 0 1.5rem;color:#374151;font-weight:500;font-size:1.1rem;font-family:monospace;background:#f3f4f6;padding:0.5rem;border-radius:6px;">${code}</p>
                <button type="button" id="notfound-ok-btn" style="background:#2563eb;color:#fff;border:none;padding:0.75rem 2rem;border-radius:8px;font-weight:500;cursor:pointer;font-size:1rem;width:100%;">
                    OK
                </button>
            </div>
        `;
        
        backdrop.appendChild(box);
        document.body.appendChild(backdrop);
        
        var okBtn = document.getElementById("notfound-ok-btn");
        var closeModal = function() {
            backdrop.remove();
        };
        
        okBtn.addEventListener("click", closeModal);
        backdrop.addEventListener("click", function(e) {
            if (e.target === backdrop) closeModal();
        });
        document.addEventListener("keydown", function onEsc(e) {
            if (e.key === "Escape") {
                closeModal();
                document.removeEventListener("keydown", onEsc);
            }
        });
        
        okBtn.focus();
    }


    // === DELEGASI KLIK AKSI CEK LIST PADA TABEL DATA STO ===
    document.addEventListener("click", function (e) {
        const btn = e.target.closest(".btn-count-action");
        if (btn) {
            e.preventDefault();
            e.stopPropagation();
            const row = btn.closest("tr[data-item-id]");
            if (row) {
                showCountConfirmModal(row);
            }
        }
    });

    document.addEventListener("submit", function (e) {
        const form = e.target.closest(".form-count-action");
        if (form) {
            e.preventDefault();
            e.stopPropagation();
            const row = form.closest("tr[data-item-id]");
            if (row) {
                showCountConfirmModal(row);
            }
        }
    });

    // === TOMBOL POSTING ===
    document.addEventListener("click", function (e) {
        const btn = e.target.closest("#btn-posting, .btn-posting-toolbar");
        if (btn) {
            e.preventDefault();
            showPostingModal();
        }
    });

    function showPostingModal() {
        const existing = document.querySelector(".sf-posting-backdrop");
        if (existing) existing.remove();

        const userRole = (document.body.dataset.userRole || "").toLowerCase();
        const isAdmin = ["poweradmin", "adminsto"].includes(userRole);
        const picGroups = (document.body.dataset.picGroups || "")
            .split(",").map(s => s.trim()).filter(Boolean);
        const activeGroup = (document.body.dataset.activeGroup || "").trim();

        const urlParams = new URLSearchParams(window.location.search);
        const currentTabKey = urlParams.get("tab") || "counted";

        // Tab counts
        const tabPendingEl = document.querySelector(".tab-pill[href*='pending'] .tab-count, a[href*='tab=pending'] .tab-count");
        const tabCountedEl = document.querySelector(".tab-pill[href*='counted'] .tab-count, a[href*='tab=counted'] .tab-count");
        const tabTotalEl   = document.querySelector(".tab-pill[href*='all'] .tab-count, a[href*='tab=all'] .tab-count");

        const countPending = tabPendingEl ? parseInt(tabPendingEl.textContent, 10) : 0;
        const countCounted = tabCountedEl ? parseInt(tabCountedEl.textContent, 10) : 0;
        const countTotal   = tabTotalEl   ? parseInt(tabTotalEl.textContent, 10) : 0;

        // Ambil baris yang sedang dicentang (checked items)
        const checkedBoxes = Array.from(document.querySelectorAll("#items-table tbody .memo-check:checked"));
        const checkedIds = checkedBoxes.map(cb => parseInt(cb.value, 10)).filter(Boolean);
        const checkedCount = checkedIds.length;

        // Ambil group ID dari checked rows untuk verifikasi keamanan observer
        const checkedRowGroups = checkedBoxes.map(cb => {
            const tr = cb.closest("tr");
            return tr ? (tr.getAttribute("data-group-id") || "") : "";
        }).filter(Boolean);

        // Ambil data semua group dari #sf-groups-data
        let allGroups = [];
        try {
            const gd = document.getElementById("sf-groups-data");
            if (gd) allGroups = JSON.parse(gd.textContent || "[]");
        } catch (_) {}

        // VALIDASI KEAMANAN OBSERVER:
        if (!isAdmin && picGroups.length === 0) {
            showToast("Anda belum ditugaskan ke group manapun sebagai observer.", "error");
            return;
        }

        // Pilihan Group untuk Dropdown
        let availableGroups = [];
        if (isAdmin) {
            availableGroups = allGroups;
        } else {
            // Observer HANYA bisa posting group penugasannya!
            availableGroups = allGroups.filter(g => picGroups.includes(String(g.id)));
            if (availableGroups.length === 0 && picGroups.length > 0) {
                availableGroups = picGroups.map(id => ({ id: parseInt(id, 10), name: "Group #" + id }));
            }
        }

        let groupSelectHtml = "";
        if (isAdmin) {
            groupSelectHtml += `<option value="0">Semua Group (Posting seluruh group)</option>`;
        }
        availableGroups.forEach(g => {
            const isSel = activeGroup ? String(activeGroup) === String(g.id) : (!isAdmin && availableGroups.length === 1);
            groupSelectHtml += `<option value="${g.id}" ${isSel ? "selected" : ""}>${escapeHtml(g.code ? `[${g.code}] ${g.name}` : g.name)}</option>`;
        });

        // Nama tab aktif
        let tabLabelName = "Belum dihitung";
        let tabItemCount = countPending;
        if (currentTabKey === "counted") {
            tabLabelName = "Sudah dihitung";
            tabItemCount = countCounted;
        } else if (currentTabKey === "all") {
            tabLabelName = "Semua";
            tabItemCount = countTotal;
        } else if (currentTabKey === "recount") {
            tabLabelName = "Cek ulang";
        }

        const hasChecked = checkedCount > 0;
        const defaultMode = hasChecked ? "selected" : (currentTabKey === "pending" ? "tab_all" : "group");

        // Periksa apakah ada item terpilih yang melanggar hak akses observer
        let observerAccessViolation = false;
        if (!isAdmin && hasChecked) {
            for (const gid of checkedRowGroups) {
                if (!picGroups.includes(String(gid))) {
                    observerAccessViolation = true;
                    break;
                }
            }
        }

        // Buat Dialog Modal Baru
        const modalWrap = document.createElement("div");
        modalWrap.className = "sf-posting-backdrop";
        modalWrap.innerHTML = `
            <div class="sf-posting-card" role="dialog" aria-modal="true" aria-labelledby="sf-post-title">
                <div class="sf-posting-head">
                    <div class="sf-posting-icon">
                        <svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                        </svg>
                    </div>
                    <div class="sf-posting-titles">
                        <h3 id="sf-post-title" class="sf-posting-title">Posting ke Audit</h3>
                        <p class="sf-posting-sub">Kirim hasil penghitungan ke tim Auditor</p>
                    </div>
                    <button type="button" class="sf-posting-close" aria-label="Tutup dialog">&times;</button>
                </div>

                <div class="sf-posting-body">
                    <!-- Section: Lingkup Group -->
                    <div>
                        <div class="sf-posting-section-title">
                            <span>Lingkup Group</span>
                            <span class="sf-posting-badge ${isAdmin ? "badge-amber" : "badge-green"}">${isAdmin ? (userRole === "poweradmin" ? "Power Admin" : "Admin STO") : "Observer"}</span>
                        </div>
                        <select id="sf-post-group-select" class="form-control" style="width:100%;padding:0.6rem 0.75rem;border-radius:10px;border:1.5px solid var(--border,#cbd5e1);font-weight:500;">
                            ${groupSelectHtml}
                        </select>
                        ${!isAdmin ? `<p style="font-size:0.75rem;color:var(--muted,#64748b);margin-top:0.35rem;">Sesuai aturan keamanan, Observer hanya dapat memposting group yang ditugaskan kepada Anda.</p>` : ""}
                    </div>

                    <!-- Section: Opsi Item yang Diposting -->
                    <div>
                        <div class="sf-posting-section-title">
                            <span>Pilih Item yang Diposting</span>
                        </div>

                        <div class="sf-posting-options">
                            <!-- Opsi 1: Item yang dipilih saja (centang) -->
                            <label class="sf-posting-opt ${hasChecked ? (defaultMode === "selected" ? "is-selected" : "") : "is-disabled"}" id="opt-wrap-selected">
                                <input type="radio" name="sf_post_mode" value="selected" ${hasChecked && defaultMode === "selected" ? "checked" : ""} ${!hasChecked ? "disabled" : ""}>
                                <div class="sf-posting-opt-content">
                                    <div class="sf-posting-opt-label">
                                        <span>Item yang dipilih saja</span>
                                        <span class="sf-posting-badge ${hasChecked ? "badge-green" : ""}">${checkedCount} dipilih</span>
                                    </div>
                                    <div class="sf-posting-opt-desc">
                                        ${hasChecked ? "Hanya baris yang dicentang di tabel yang akan diposting ke audit." : "Centang kotak checkbox di tabel terlebih dahulu untuk memilih item tertentu."}
                                    </div>
                                </div>
                            </label>

                            <!-- Opsi 2: Semua item di tab saat ini (Belum dihitung / Sudah dihitung) -->
                            <label class="sf-posting-opt ${defaultMode === "tab_all" ? "is-selected" : ""}" id="opt-wrap-tab">
                                <input type="radio" name="sf_post_mode" value="tab_all" ${defaultMode === "tab_all" ? "checked" : ""}>
                                <div class="sf-posting-opt-content">
                                    <div class="sf-posting-opt-label">
                                        <span>Semua item di tab "${escapeHtml(tabLabelName)}"</span>
                                        <span class="sf-posting-badge badge-amber">${tabItemCount} item</span>
                                    </div>
                                    <div class="sf-posting-opt-desc">
                                        ${currentTabKey === "pending"
                                            ? "Posting seluruh item yang belum dihitung pada tab ini ke audit."
                                            : `Posting seluruh item pada tab "${escapeHtml(tabLabelName)}" ke tim audit.`}
                                    </div>
                                </div>
                            </label>

                            <!-- Opsi 3: Semua item yang sudah dihitung (Siap audit) -->
                            <label class="sf-posting-opt ${defaultMode === "group" ? "is-selected" : ""}" id="opt-wrap-group">
                                <input type="radio" name="sf_post_mode" value="group" ${defaultMode === "group" ? "checked" : ""}>
                                <div class="sf-posting-opt-content">
                                    <div class="sf-posting-opt-label">
                                        <span>Semua item yang sudah dihitung</span>
                                        <span class="sf-posting-badge badge-green">${countCounted} siap audit</span>
                                    </div>
                                    <div class="sf-posting-opt-desc">
                                        Posting item yang sudah berstatus "Sudah dihitung" pada group terpilih.
                                    </div>
                                </div>
                            </label>
                        </div>
                    </div>

                    ${observerAccessViolation ? `
                        <div class="sf-alert sf-alert-danger" style="margin-top:0.25rem;font-size:0.8rem;padding:0.6rem 0.85rem;border-radius:10px;background:#fee2e2;color:#991b1b;border:1px solid #fca5a5;">
                            <strong>Peringatan Keamanan:</strong> Ada item yang dicentang berasal dari group di luar penugasan Anda. Hilangkan centang pada item tersebut untuk melanjutkan.
                        </div>
                    ` : ""}
                </div>

                <div class="sf-posting-foot">
                    <button type="button" class="sf-posting-btn-cancel">Batal</button>
                    <button type="button" class="sf-posting-btn-submit" ${observerAccessViolation ? "disabled" : ""}>
                        <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                        </svg>
                        <span>Kirim ke Audit</span>
                    </button>
                </div>
            </div>
        `;

        document.body.appendChild(modalWrap);

        // Interaksi pemilihan radio opsi
        const optLabels = modalWrap.querySelectorAll(".sf-posting-opt");
        const radios = modalWrap.querySelectorAll("input[name='sf_post_mode']");
        radios.forEach(radio => {
            radio.addEventListener("change", function () {
                optLabels.forEach(l => l.classList.remove("is-selected"));
                const parent = this.closest(".sf-posting-opt");
                if (parent) parent.classList.add("is-selected");
            });
        });

        // Close handlers
        const closeModal = () => modalWrap.remove();
        modalWrap.querySelector(".sf-posting-close").addEventListener("click", closeModal);
        modalWrap.querySelector(".sf-posting-btn-cancel").addEventListener("click", closeModal);
        modalWrap.addEventListener("click", function (e) {
            if (e.target === modalWrap) closeModal();
        });

        // Submit action
        const submitBtn = modalWrap.querySelector(".sf-posting-btn-submit");
        submitBtn.addEventListener("click", function () {
            const selectedMode = (modalWrap.querySelector("input[name='sf_post_mode']:checked") || {}).value || "group";
            const targetGroupId = parseInt(modalWrap.querySelector("#sf-post-group-select").value, 10) || 0;

            if (selectedMode === "selected" && checkedIds.length === 0) {
                showToast("Pilih minimal satu item di tabel untuk diposting.", "error");
                return;
            }

            submitBtn.disabled = true;
            submitBtn.innerHTML = `<span>Mengirim…</span>`;

            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content ||
                              document.querySelector('input[name="_token"]')?.value || "";

            const payloadData = {
                mode: selectedMode,
                period: "default"
            };

            if (selectedMode === "selected") {
                payloadData.item_ids = checkedIds;
            } else if (selectedMode === "tab_all") {
                payloadData.tab_status = currentTabKey === "pending" ? "pending" : (currentTabKey === "counted" ? "counted" : "all");
                payloadData.group_id = targetGroupId;
            } else {
                payloadData.group_id = targetGroupId;
            }

            fetch("/items/posting", {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    "X-Requested-With": "XMLHttpRequest",
                    "X-CSRF-TOKEN": csrfToken
                },
                body: JSON.stringify(payloadData)
            })
            .then(res => res.json().then(data => ({ ok: res.ok, status: res.status, data })))
            .then(({ ok, status, data }) => {
                if (!ok || !data.success) {
                    showToast(data.error || "Gagal posting data", "error");
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = `<span>Kirim ke Audit</span>`;
                    return;
                }
                if (data.blocked && data.blocked.length && (!data.posted || data.posted.length === 0)) {
                    showToast(data.blocked[0].reason || "Posting diblokir.", "error");
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = `<span>Kirim ke Audit</span>`;
                    return;
                }

                showToast(data.message || `Berhasil memposting ${data.total_items} item ke audit`, "success");
                closeModal();
                setTimeout(() => {
                    window.location.href = "/items?count_status[]=posted&tab=posted";
                }, 850);
            })
            .catch(err => {
                showToast("Gagal posting data", "error");
                console.error(err);
                submitBtn.disabled = false;
                submitBtn.innerHTML = `<span>Kirim ke Audit</span>`;
            });
        });
    }

    // === SCANNER SETTINGS BUTTON ===
    const settingsBtn = document.getElementById("scanner-settings");
    if (settingsBtn) {
        settingsBtn.addEventListener("click", showScannerSettings);
    }

    function showScannerSettings() {
        const currentTimeout = SCANNER_CONFIG.timeout;
        const currentMinLength = SCANNER_CONFIG.minLength;
        const currentDebug = SCANNER_CONFIG.debug;

        const modal = document.createElement("div");
        modal.className = "sf-settings-dialog-backdrop";
        modal.id = "scanner-settings-modal";
        modal.setAttribute("role", "dialog");
        modal.setAttribute("aria-modal", "true");
        modal.setAttribute("aria-labelledby", "sf-settings-dialog-title");

        modal.innerHTML = `
            <div class="sf-settings-dialog">
                <!-- Header Dialog -->
                <div class="sf-settings-dialog-head">
                    <div class="sf-settings-dialog-icon">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M3 7V5a2 2 0 0 1 2-2h2"></path>
                            <path d="M17 3h2a2 2 0 0 1 2 2v2"></path>
                            <path d="M21 17v2a2 2 0 0 1-2 2h-2"></path>
                            <path d="M7 21H5a2 2 0 0 1-2-2v-2"></path>
                            <line x1="7" y1="8" x2="7" y2="16"></line>
                            <line x1="12" y1="8" x2="12" y2="16"></line>
                            <line x1="17" y1="8" x2="17" y2="16"></line>
                        </svg>
                    </div>
                    <div class="sf-settings-dialog-titles">
                        <h3 class="sf-settings-dialog-title" id="sf-settings-dialog-title">Pengaturan Barcode Scanner</h3>
                        <p class="sf-settings-dialog-sub">Kalibrasi latensi &amp; sensitivitas hardware scanner</p>
                    </div>
                    <button type="button" class="sf-settings-dialog-close" aria-label="Tutup dialog">&times;</button>
                </div>

                <!-- Form Body -->
                <form id="scanner-settings-form">
                    <div class="sf-settings-dialog-body">
                        <!-- Field Timeout / Latensi -->
                        <div class="sf-setting-group">
                            <label class="sf-setting-label" for="sf-scanner-timeout">
                                <span>Sensitivitas Latensi (Timeout)</span>
                            </label>
                            <div class="sf-input-adornment-wrap">
                                <input type="number" id="sf-scanner-timeout" name="timeout" value="${currentTimeout}" min="40" max="600" step="10" class="sf-form-control-pro" required />
                                <span class="sf-input-suffix">ms</span>
                            </div>
                            <div class="sf-setting-hint">Jeda antar-karakter. Standar USB/BT: 100–150ms. Scanner cepat: 50–80ms.</div>
                        </div>

                        <!-- Field Minimal Karakter -->
                        <div class="sf-setting-group">
                            <label class="sf-setting-label" for="sf-scanner-minlength">
                                <span>Panjang Karakter Minimal</span>
                            </label>
                            <div class="sf-input-adornment-wrap">
                                <input type="number" id="sf-scanner-minlength" name="minLength" value="${currentMinLength}" min="1" max="30" class="sf-form-control-pro" required />
                                <span class="sf-input-suffix">karakter</span>
                            </div>
                            <div class="sf-setting-hint">Batas minimum panjang string untuk dianggap barcode (mencegah typo keyboard).</div>
                        </div>

                        <!-- Modern Toggle Switch Card -->
                        <label class="sf-toggle-card" for="sf-scanner-debug">
                            <div class="sf-toggle-info">
                                <div class="sf-toggle-title">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <polyline points="4 17 10 11 4 5"></polyline>
                                        <line x1="12" y1="19" x2="20" y2="19"></line>
                                    </svg>
                                    <span>Mode Debug Console</span>
                                </div>
                                <div class="sf-toggle-desc">Tampilkan buffer deteksi dan timing keystroke di console</div>
                            </div>
                            <div class="sf-switch">
                                <input type="checkbox" id="sf-scanner-debug" name="debug" ${currentDebug ? "checked" : ""}>
                                <span class="sf-slider"></span>
                            </div>
                        </label>

                        <!-- Modern Info Callout Box (Tips) -->
                        <div class="sf-info-callout">
                            <div class="sf-info-callout-icon">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <circle cx="12" cy="12" r="10"></circle>
                                    <line x1="12" y1="16" x2="12" y2="12"></line>
                                    <line x1="12" y1="8" x2="12.01" y2="8"></line>
                                </svg>
                            </div>
                            <div class="sf-info-callout-text">
                                <strong>Tips Kalibrasi Scanner:</strong>
                                <ul>
                                    <li>Jika barcode panjang sering terputus, <strong>naikkan Timeout</strong> (misal 200ms).</li>
                                    <li>Jika ketikan biasa terdeteksi sebagai barcode, <strong>naikkan Panjang Minimal</strong>.</li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <!-- Footer Dialog -->
                    <div class="sf-settings-dialog-foot">
                        <button type="button" class="sf-btn-reset-ghost" id="reset-scanner-settings">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"></path>
                                <path d="M3 3v5h5"></path>
                            </svg>
                            <span>Reset Default</span>
                        </button>
                        <div style="display:flex;gap:0.65rem;">
                            <button type="button" class="sf-btn-cancel">Batal</button>
                            <button type="submit" class="sf-btn-submit-brand">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                    <polyline points="20 6 9 17 4 12"></polyline>
                                </svg>
                                <span>Simpan Pengaturan</span>
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        `;

        document.body.appendChild(modal);

        function closeModal() {
            modal.style.opacity = "0";
            modal.style.transition = "opacity 140ms ease-out";
            setTimeout(() => {
                if (modal.parentNode) modal.remove();
            }, 140);
        }

        modal.querySelector(".sf-settings-dialog-close").addEventListener("click", closeModal);
        modal.querySelector(".sf-btn-cancel").addEventListener("click", closeModal);
        modal.addEventListener("click", function (e) {
            if (e.target === modal) closeModal();
        });

        function onEsc(e) {
            if (e.key === "Escape") {
                closeModal();
                document.removeEventListener("keydown", onEsc);
            }
        }
        document.addEventListener("keydown", onEsc);

        modal.querySelector("#reset-scanner-settings").addEventListener("click", function () {
            localStorage.removeItem("sto-scanner-timeout");
            localStorage.removeItem("sto-scanner-minlength");
            localStorage.removeItem("sto-scanner-debug");
            SCANNER_CONFIG.timeout = 150;
            SCANNER_CONFIG.minLength = 3;
            SCANNER_CONFIG.debug = false;
            closeModal();
            showToast("Pengaturan scanner direset ke default (150ms / 3 char)", "info");
        });

        modal.querySelector("#scanner-settings-form").addEventListener("submit", function (e) {
            e.preventDefault();
            const timeout = parseInt(this.timeout.value, 10) || 150;
            const minLength = parseInt(this.minLength.value, 10) || 3;
            const debug = this.debug.checked;

            localStorage.setItem("sto-scanner-timeout", timeout.toString());
            localStorage.setItem("sto-scanner-minlength", minLength.toString());
            localStorage.setItem("sto-scanner-debug", debug ? "true" : "false");

            SCANNER_CONFIG.timeout = timeout;
            SCANNER_CONFIG.minLength = minLength;
            SCANNER_CONFIG.debug = debug;

            closeModal();
            showToast("Pengaturan scanner berhasil disimpan (" + timeout + "ms / " + minLength + " char)", "success");
        });
    }

    // === HELPER FUNCTIONS ===
    function escapeHtml(text) {
        if (!text) return "";
        const div = document.createElement("div");
        div.textContent = text;
        return div.innerHTML;
    }

    function showToast(message, type = "info") {
        if (window.sfAnnounce) window.sfAnnounce(message, type);
        const existing = document.querySelectorAll(".sf-toast");
        existing.forEach(t => t.remove());

        const toast = document.createElement("div");
        toast.className = `sf-toast sf-toast-${type}`;

        let iconSvg = "";
        if (type === "success") {
            iconSvg = `<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#10b981" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0"><polyline points="20 6 9 17 4 12"></polyline></svg>`;
        } else if (type === "error") {
            iconSvg = `<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#ef4444" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>`;
        } else {
            iconSvg = `<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#3b82f6" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>`;
        }

        toast.innerHTML = `
            <div style="display:flex;align-items:center;gap:0.75rem;">
                ${iconSvg}
                <div style="font-size:0.9rem;font-weight:600;color:#0f172a;line-height:1.4;">${escapeHtml(message)}</div>
            </div>
        `;

        toast.style.cssText = "position:fixed;top:24px;right:24px;max-width:380px;padding:0.9rem 1.25rem;background:#ffffff;border-radius:14px;box-shadow:0 12px 30px -4px rgba(15,23,42,0.18),0 0 0 1px rgba(226,232,240,0.8);z-index:11000;animation:sfToastIn 0.22s cubic-bezier(0.16,1,0.3,1);";

        if (type === "success") {
            toast.style.borderLeft = "4px solid #10b981";
        } else if (type === "error") {
            toast.style.borderLeft = "4px solid #ef4444";
        } else {
            toast.style.borderLeft = "4px solid #3b82f6";
        }

        document.body.appendChild(toast);
        setTimeout(() => {
            toast.style.animation = "sfToastOut 0.2s cubic-bezier(0.16,1,0.3,1) forwards";
            setTimeout(() => toast.remove(), 220);
        }, 3200);
    }
});

// CSS untuk toast animations
const style = document.createElement("style");
style.textContent = `
    @keyframes sfToastIn {
        from { transform: translateX(100%) scale(0.95); opacity: 0; }
        to { transform: translateX(0) scale(1); opacity: 1; }
    }
    @keyframes sfToastOut {
        from { transform: translateX(0) scale(1); opacity: 1; }
        to { transform: translateX(100%) scale(0.95); opacity: 0; }
    }
`;
document.head.appendChild(style);
