# Fix: tutup popup detail melompat ke header (scroll lock)

Tanggal: 17 Okt 2026 · Halaman: semua tabel dengan drawer detail (`/items`, `/reconciliation`, `/audit`, dst)

## Gejala
Klik baris di tabel → popup/drawer detail terbuka. Saat ditutup (X, backdrop, Esc),
halaman kembali ke paling atas (header), bukan ke baris yang tadi dibuka.
Sama untuk tab menu dan popup filter: posisi tidak "terkunci" di tabel.

## Akar masalah
`public/assets/css/sf-rowdetail.css` mengunci scroll dengan:

```css
html.rd-lock, html.rd-lock body { overflow: hidden !important; }
```

`overflow:hidden` pada **`<body>`** (bersamaan dengan `<html>`) membuat Chrome/Edge
me-reset `scrollTop` ke 0 saat class dipasang. Class dilepas → halaman tetap di 0.
Diukur live: `scrollY 2100` → buka drawer → `scrollY 0` → tutup → tetap `0`.
Tidak ada `window.scrollTo` di log, jadi murni efek CSS.

Uji terpisah membuktikan:
| perlakuan | scrollY setelahnya |
|---|---|
| `html{overflow:hidden}` saja | 2100 (aman) |
| `html` + `body{overflow:hidden}` | 0 (reset) |

Di mobile justru `<body>` yang jadi scroller (`body{height:100%;overflow-y:auto}`),
jadi mengunci body memang perlu — tapi posisinya wajib disimpan/dipulihkan manual.

## Perbaikan
1. **CSS** (`sf-rowdetail.css`)
   - `html.rd-lock { overflow:hidden; padding-right: var(--rd-sbw,0); overscroll-behavior:none; }`
   - body **tidak** dikunci di desktop. Khusus mobile: `html.rd-lock.rd-lock-body body { overflow:hidden }`.
   - `--rd-sbw` = lebar scrollbar, supaya konten tidak bergeser saat scrollbar hilang.
2. **JS** (`sf-rowdetail.js`, `sf-tables-expand.js`) — helper baru `lockPage()` / `unlockPage()`
   menggantikan `classList.add/remove('rd-lock')` di `openDrawer()` / `closeDrawer()`:
   - simpan `window.pageYOffset` + `document.body.scrollTop` sebelum kunci;
   - deteksi body-scroller: `html.scrollHeight <= innerHeight+2 && body.scrollHeight > innerHeight+4`
     → baru tambah class `rd-lock-body`;
   - saat unlock: lepas class, pulihkan `body.scrollTop`, lalu `window.scrollTo(0, simpanan)`
     bila posisi bergeser > 1px (jaring pengaman).

## Verifikasi (headless Chrome)
| kasus | sebelum | saat buka | setelah tutup |
|---|---|---|---|
| /reconciliation (klik baris) | 2000 | 2000 | 2000 |
| /items (klik baris) | 500 | 500 | 500 |
| /audit (klik baris) | 1497 | 1497 | 1497 |
| tombol X | 1500 | 1500 | 1500 |
| klik backdrop | 1500 | 1500 | 1500 |
| Esc | 1500 | 1500 | 1500 |
| chevron inline expand | 1500 | 1500 | 1500 |
| "Detail lengkap" dari panel | 1500 | 1500 | 1500 |
| mobile 390 (body scroller) | 1100 | 1100 (body terkunci) | 1100 |
| popup filter kolom | 900 | 900 | 900 |
| tab /items, /audit | 608 / 900 | — | 537 / 900 |

12 rute utama: tanpa error, HTTP 200.

## Catatan
`asset_v()` memakai `filemtime`, jadi aset otomatis ter-cache-bust — tidak perlu ubah layout.
