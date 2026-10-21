# Pass 15 — Dock "Penyesuaian tampilan" selalu kalah + ikon SVG (tanpa emoji)

Tanggal: 18 Okt 2026 · Halaman: `/reconciliation` (panel Mode Cepat), global.

## 1. Tombol penyesuaian tampilan tidak boleh menutupi pop up
**Masalah:** FAB bulat `.sf-vm-handle` (z-index 44) adalah **anak langsung `<body>`**,
sedangkan aturan lama hanya menyembunyikan `.sf-vm` (dock). Akibatnya FAB tetap
tampil dan menutupi footer pop up — persis menutupi tombol **Simpan**.

**Perbaikan (dua lapis):**
1. `z-index` FAB diturunkan ke **30** — di bawah semua backdrop/modal
   (100 / 940 / 1040 / 1300), jadi secara struktur selalu kalah.
2. Guard overlay `sf-viewmode.js` + CSS `body.sf-overlay-open`:
   setiap kali ada overlay terbuka, `.sf-vm` **dan** `.sf-vm-handle`
   disembunyikan (`opacity:0; visibility:hidden; pointer-events:none`) dan
   `aria-hidden="true"` + `tabindex="-1"` dipasang.

**Overlay yang dideteksi:** `.qe-backdrop:not([hidden])`, `.rd-drawer.is-open`,
`.wh-modal.is-open`, `.sf-modal.is-open`, `dialog[open]`, `.modal.show`,
`.modal.in`, `[data-sf-overlay]`, `.sf-sheet.is-open`, `.sf-drawer.is-open`,
`.sheet.is-open`, `.popover.is-open`, plus `body.qe-lock`, `body.modal-open`,
`html.rd-lock`.

**Pemicu sinkron:** MutationObserver (childList + class/hidden/style/open),
klik (30 ms & 260 ms), Escape, pageshow, dan timer 350 ms selama overlay
dianggap terbuka — tidak bergantung `requestAnimationFrame` yang bisa berhenti
di tab background.

## 2. Emoji → SVG
| Sebelum | Sesudah |
|---|---|
| `🔍` (empty state) | SVG magnifier, tile 52px radius 16px |
| `✍` (empty "hanya diedit") | SVG pencil |
| chip group tanpa ikon | SVG folder + nama + jumlah |

Semua ikon memakai `stroke="currentColor"`, `aria-hidden="true"`, ukuran 15–24px,
jadi ikut warna tema (light/dark) dan tidak pernah tampil sebagai emoji.

## 3. Pencarian tetap menyeluruh
Tidak diubah: `/reconciliation/quick-items?q=` mencari di **kode, nama, group,
lokasi, dan kategori** (LIKE, case-insensitive). Diuji live:

| q | hasil |
|---|---|
| `c-002` | 1 cocok · C-002 |
| `spare` (nama group) | 3 cocok |
| `rak` (lokasi) | 3 cocok |
| `oli` (nama) | 1 cocok |
| `pcs` (satuan, tidak termasuk kolom cari) | 0 cocok |

## 4. Verifikasi live
| Uji | Hasil |
|---|---|
| Panel tertutup | FAB `visibility:visible`, `z-index:30` |
| Panel terbuka | FAB `hidden`, `opacity:0`, `aria-hidden:true`, body `sf-overlay-open` |
| Tutup via "Tutup" | FAB kembali terlihat |
| Tutup via X | FAB kembali terlihat |
| Overlay `.rd-drawer.is-open` (probe) | FAB tersembunyi, lalu kembali setelah overlay hilang |
| Emoji scan seluruh halaman | `[]` (tidak ada emoji tersisa) |
| Empty state | 1 chip: ikon folder SVG + "Gudang Spare Part" + jumlah |
| Mobile 390px | chip 1 kolom 334px, tanpa overflow-x, FAB hidden |
| Dark mode | chip bg `rgb(22,32,47)`, border `rgb(36,48,74)`, teks `rgb(193,194,197)` |

## Berkas
- `public/assets/css/sf-viewmode.css` — blok "Dock Penyesuaian tampilan"
- `public/assets/js/sf-viewmode.js` — IIFE "Overlay guard"
- `public/assets/js/recon-quick.js` — `SVG` map + `renderEmpty()` + `renderEdited()`
- `public/assets/css/app.css` — `.qe-empty-ico`, `.qe-chips`, `.qe-chip*`
