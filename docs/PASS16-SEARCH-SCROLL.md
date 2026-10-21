# PASS 16 — Cari selalu "tidak berfungsi" + tutup panel balik ke header

Tanggal: 2026-10-17 · Rute: `/reconciliation` (Mode Cepat / quick-entry)

## A. Fungsi cari dibuat "mustahil gagal"

Keluhan berulang: mengetik di kotak cari tidak memuat apa pun. Kotak cari kini dibuat tahan
terhadap 3 skenario kegagalan yang mungkin terjadi di perangkat user:

1. **Kotak cari dulu hanya `<input>` ber-border tipis** — mudah dianggap label, dan klik pada
   area sekitarnya tidak memfokuskan apa pun. Sekarang `.qe-find` (pembungkus) bisa diklik:
   klik di mana saja pada kotak -> input fokus. Placeholder diperjelas:
   "Cari kode / nama / grup...".
2. **Listener input dulu satu jalur** (`searchEl.addEventListener`). Sekarang **3 jalur**:
   `input` di elemen search, `input` delegasi di panel (menangkap event walau elemen search
   diganti), dan `change`/`search` sebagai cadangan (autofill / tombol clear bawaan).
3. **Watchdog 2 detik**: kalau kolom search berisi teks tapi daftar masih kosong, panel memaksa
   memuat ulang sendiri (anti kondisi "listener tidak terpasang / fetch mati").
4. **Empty state informatif**: menampilkan kata kunci yang sedang dicari + tombol *Fokus kotak
   pencarian*; kalau fetch gagal -> kartu "Pencarian gagal dimuat" + tombol **Coba lagi**
   (plus `console.warn('[mode-cepat] pencarian gagal')` untuk diagnosa).
5. Setiap buka panel: `searchEl.value` dikosongkan, fokus + `select()` -> langsung bisa ketik.

## B. Tutup overlay tidak lagi melempar ke atas (akar masalah sebenarnya)

**Akar masalah**: `html { overflow: hidden }` saat overlay terbuka **me-reset `scrollY` ke 0**
(overflow pada `<html>` mematikan scroll container viewport). Saat ditutup, halaman tetap di 0 ->
user melihat "header" lagi. Ini terjadi di SEMUA overlay yang mengunci scroll lewat `body`
(quick-entry, row detail, progress drawer, drawer mobile).

Perbaikan (dicek satu per satu):

* **Mode Cepat**: `body.qe-lock{overflow:hidden}` -> **`html:has(body.qe-lock){overflow:hidden}`**.
  `:has()` tidak mengubah `body`, jadi tidak ada reflow/scroll-reset.
* **Row detail**: `body{overflow:hidden}` dihapus -> `html.rd-lock{overflow:hidden}` (class sudah
  dipasang JS-nya; sebelumnya tidak ada CSS-nya).
* **Progress drawer**: `html:has(body.prg-lock)`.
* **Drawer mobile**: `html:has(body.no-scroll)` +
  `@media(max-width:1023.98px){body.no-scroll{overflow:hidden}}`
  (di mobile `<body>` memang scroller; lock di body di sana TIDAK me-reset `scrollTop`).
* **`wh-modal`** (dropdown gudang): `document.body.style.overflow` dihapus, cukup `html`.
* **Jaring pengaman `sf-scrollkeep.js`** (baru, global di footer): mencatat posisi scroll sebelum
  overlay dibuka dan memulihkannya bila berubah > 4px; mendukung **dua mode scroller**
  (`window` di desktop, `document.body` di mobile). Menangkap kasus lock yang belum diketahui.

## Bukti uji (Chrome headless)

| Skenario | Sebelum | Sesudah |
|---|---|---|
| Desktop /reconciliation: buka di scrollY 900 -> cari "spare" -> tutup | 0 (header) | **900** |
| Mobile 390 (body scroller): buka di scrollTop 700 -> cari -> tutup | 0 | **700** |
| Drawer mobile: buka/tutup di 600 | 0 | **600** |
| `wh-modal` gudang: buka/tutup di 600 | 0 | **600** |
| Cari "spare" (desktop & mobile) | 0 hasil / kosong | **3 cocok, 3 baris** |
| Cari "c-00" | - | **3 cocok** (C-001..C-003) |
| Ketik angka di baris -> footer | - | "Simpan 1 item", Hanya-diedit 1 |
| Tombol FAB saat panel terbuka | - | hidden (guard PASS15 tetap) |

## File disentuh

* `public/assets/js/recon-quick.js` (`.qe-find` click-to-focus, 3 jalur listener, watchdog,
  empty state + retry, console.warn)
* `public/assets/css/app.css` (`html:has(body.qe-lock)`, `html:has(body.no-scroll)`,
  media mobile lock, style `.qe-find`)
* `public/assets/css/sf-progress.css` (`html:has(body.prg-lock)` + mobile)
* `public/assets/css/sf-rowdetail.css` (`html.rd-lock` + mobile)
* `public/assets/js/sf-scrollkeep.js` (baru — guard scroll global)
* `public/partials/footer.php` (load `sf-scrollkeep.js`)

Catatan operasional: setelah deploy, lakukan **hard refresh** sekali (Ctrl+F5) agar CSS/JS lama
tidak tersangkut; `asset_v()` sudah menambahkan `?v=filemtime` sehingga refresh biasa pun cukup.
