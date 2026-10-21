# PASS 18 — Kotak Cari Mode Cepat (fix "tertutup header" + desain baru)

**Keluhan:** "Cek ini tombol cari apakah tertutup header, dan buat tombol search profesional dan modern tidak aneh."

## 1. AKAR MASALAH: panel memang tertutup topbar (bukan cuma terlihat aneh)

Diagnosa terukur (CDP, /reconciliation):

| Elemen | Nilai |
|---|---|
| `.qe-panel` (backdrop) | `z-index: 1300`, **tetapi** berada di dalam `.app-shell` |
| `.app-shell` | `position: relative; z-index: 1` → **membuat stacking context** |
| `.topbar` | `position: fixed; z-index: 1000` di root |

Karena `.app-shell` punya `z-index: 1`, angka 1300 milik panel hanya berlaku **di dalam**
stacking context `.app-shell`; di root, `.app-shell` (1) kalah dari `.topbar` (1000).
`document.elementFromPoint(700,30)` mengembalikan `KBD` (chip Ctrl+K milik topbar) →
**header panel benar-benar tertimpa topbar 60px**, dan kotak cari (top: 58px) mepet tepat
di bawahnya sehingga tampak "nyangkut/tertutup".

**Fix:** pindahkan panel ke `document.body` saat init (recon-quick.js). Di body,
`z-index: 1300 > 1000` → panel menutup topbar dengan benar.
```js
if (panel.parentNode && panel.parentNode !== document.body) { document.body.appendChild(panel); }
```
Verifikasi: `elementFromPoint` di area judul → `DIV.qe-head` (bukan `KBD`), tombol X →
SVG tombol tutup, kotak cari → `INPUT.qe-search`. Scroll halaman tetap dipertahankan
(900px) saat buka/tutup, `qe-lock` tetap dilepas setelah tutup.

> **Pelajaran umum:** overlay apa pun (drawer/modal/popover) yang ditulis di dalam
> `<main class="content">` akan TERKUNCI di bawah `.topbar` selama `.app-shell`
> punya `z-index`. Solusi baku: append ke `body` (seperti yang sudah dilakukan untuk
> drawer rekonsiliasi & FAB view-mode).

## 2. DESAIN BARU KOTAK CARI (sebelum → sesudah)

Sebelum: `<input class="qe-search">` polos, ikon di kiri, padding 10px, tanpa tombol bersihkan,
tanpa penanda fokus pada wrapper, placeholder panjang satu baris.

Sesudah — komponen "search field" utuh:

- **Wrapper `.qe-find`** (46px mobile / 44px desktop): ikon kaca pembesar 18px (stroke 2)
  sebagai child, input transparan di tengah, tombol bersihkan bulat 24px di kanan.
- **Padding & radius**: 12px kiri, 44px radius (kapsul), background `--surface-2`
  (#f8fafc di terang), border 1px `--border`.
- **Fokus**: seluruh kotak menyala — border `--accent-primary` + ring
  `0 0 0 3px rgba(37,99,235,.16)`, background naik ke `--surface`.
- **Hover**: border menggelap ke `--text-muted`.
- **Tema gelap**: latar `rgba(255,255,255,.045)`, border `rgba(255,255,255,.12)`
  (token `--border` terlalu dekat dengan `--surface-2` sehingga kotak nyaris tanpa tepi),
  fokus `rgba(255,255,255,.07)` + ring biru.
- **Teks**: 15px/16px, `-webkit-appearance: none`, `::-webkit-search-cancel-button` dimatikan
  (pakai tombol sendiri yang konsisten), `spellcheck=false`, `enterkeyhint="next"`,
  `aria-label` deskriptif.
- **Hint keyboard** di bawah kotak: keycap `Enter` "item berikutnya" · `Ctrl`+`Enter` "simpan"
  + baris kedua "tempel satu kolom angka dari Excel juga bisa" (10.5px, muted).
- **Tombol bersihkan** hanya muncul saat ada isi (`.has-val`), bulat, hover → `--text-muted`.

### Bug halus yang ikut diperbaiki
1. **Garis biru dobel di dalam kotak** — aturan global `input:focus{border-color:brand;
   box-shadow:0 0 0 3px}` ikut mengenai `<input>` di dalam wrapper, jadi ada border biru
   kedua di dalam ring wrapper. Fix: `.qe-search{border:0!important;box-shadow:none!important;
   background:transparent!important;outline:none!important}` (semua state).
2. **Tombol X jadi kotak 28×24** — lapisan global `button{border-radius:8px;width:28px}`.
   Fix: `border-radius:999px;width/height:24px` + `!important`.
3. **Ikon berkedip saat resize** — SVG ikon diberi `position:absolute` di dalam wrapper
   fleksibel; diganti child statis `flex:0 0 18px` sehingga tidak pernah bertumpuk teks.
4. **Label "Semua group" terpotong di 390px** — padding tombol 7px 10px, `max-width:52%`,
   `text-overflow:ellipsis`.

## 3. Verifikasi
- 390px: header 0–53, kotak cari 12..378 (lebar 366, tinggi 46), tanpa overflow horizontal.
- 1280px: panel 520..1280, judul terlihat penuh, kotak cari 44px, hint 2 baris rapi.
- Tema gelap: latar `rgba(255,255,255,.043)`, border `rgba(255,255,255,.12)`.
- Fungsi: cari "spare" → 3 cocok; tombol X mengosongkan; filter group & "hanya diedit"
  tetap jalan; buka ulang → fokus otomatis ke kotak cari; tutup → lock dilepas, scroll utuh.

## 4. File
- `app/Views/reconciliation/index.php` — markup `.qe-find` + hint + `aria-label`.
- `public/assets/css/app.css` — blok `.qe-find*` (termasuk override tema gelap).
- `public/assets/js/recon-quick.js` — panel dipindah ke `document.body`; toggle `.has-val`.

## 5. Polish pass (setelah review visual)
- Padding kanan kotak cari 10px → tombol X tidak lagi menempel tepi.
- Ritme vertikal: kotak cari → hint 8px → baris filter 10px.
- Hint duplikat di footer DIHAPUS (sekarang hanya di bawah kotak cari yang selalu terlihat
  karena head/tools sticky, hanya daftar yang scroll).
- Tombol **Simpan** nonaktif: dulu `opacity:.4` global → teks putih di biru pucat
  (kontras ~2:1). Sekarang tint brand muda `#e3ecfc` + teks `#4a6a9e` → **kontras 4.59:1**
  (lolos AA) dan tetap terbaca sebagai tombol nonaktif. Varian tema gelap ikut dibuat.
