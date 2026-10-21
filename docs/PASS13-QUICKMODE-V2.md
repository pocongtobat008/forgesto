# Mode Cepat v2 — kosong dulu, filter group multi-pilih, pengaturan tampilan

Tanggal: 18 Okt 2026 · Halaman: `/reconciliation` (tombol **Mode Cepat**, `#qe-open`)

## 1. Panel dibuka kosong
Sebelumnya semua baris langsung dirender (3 item, semua group). Sekarang:

- Saat dibuka: `0 item tampil`, daftar kosong + empty state
  ("Belum ada item yang ditampilkan" + chip semua group).
- Baris hanya muncul setelah pengguna **mencari** atau **memilih group**.
- Fokus otomatis ke kotak cari, bukan ke input angka pertama.
- Pengecualian: baris yang **sedang diubah** (dirty) tetap tampil walau
  filter kosong / tidak cocok — supaya angka yang sudah diketik tidak hilang.

## 2. Filter group multi-pilih + pencarian seluruh isi tabel
**Tombol group** (`#qe-gbtn`, ikon layers) membuka dropdown berisi checkbox
per group + jumlah item + tombol *Pilih semua* / *Kosongkan*. Label berubah
menjadi "2 group dipilih" dan tombol menyala biru saat filter aktif.

**Pencarian** sekarang mencakup **seluruh isi baris** (semua `<td>`), bukan
hanya kode+nama: group, lokasi, kategori, satuan, observer, auditor,
diajukan/verifikasi, catatan, harga, stok, hasil hitung, selisih, status.
Multi-kata = AND (`rak oli` mencari baris yang memuat keduanya).

**Aturan tampil** (`applyFilter()`):
| kondisi | yang tampil |
|---|---|
| belum cari & belum pilih group | hanya baris yang sedang diubah |
| ada pencarian / group | baris yang cocok **+** baris yang sedang diubah |
| baris diubah tapi tidak cocok filter | tetap tampil, border putus-putus + tag "diubah" |

Baris yang baru disimpan tetap terlihat 15 detik (`rec.recent`) walau tidak
cocok filter, lalu ikut hilang.

## 3. Pengaturan tampilan + tombol Simpan tidak pernah tertutup
**Tombol gear** (`#qe-gear`) → popover *Tampilan daftar*:
- Kepadatan: **Nyaman** / **Rapat** (padding baris + tinggi input).
- Tampilkan **stok & selisih** (on/off).
- Tampilkan **nama group** (on/off).
Preferensi disimpan di `localStorage` → `sf_qe_ui_v1`.

**Akar masalah tombol Simpan tertutup**: panel (`#qe-panel`) berada di dalam
`.content` yang punya stacking context sendiri, sehingga `z-index:1300` hanya
berlaku lokal dan **dock tampilan `.sf-vm` (z 940, fixed di body)** menang —
`elementFromPoint` di tengah tombol Simpan mengembalikan
`BUTTON.sf-vm__collapse`, bukan tombolnya.

Perbaikan:
1. Panel dipindah ke `<body>` saat dibuka (`document.body.appendChild(panel)`).
2. CSS global di `sf-viewmode.css`: dock disembunyikan saat popup terbuka —
   `html.rd-lock .sf-vm`, `body.qe-lock .sf-vm`,
   `body:has(.qe-backdrop:not([hidden])) .sf-vm`,
   `body:has(.rd-drawer.is-open)) .sf-vm`, `.wh-modal`, `.sf-modal`.
3. Animasi masuk panel diganti **fade** (tanpa `translateX(28px)`). Sebelumnya
   kalau animasi tidak sempat jalan, panel tertinggal bergeser 28px dan tombol
   gear/Simpan keluar layar di mobile 390px.

## Verifikasi live (headless Chrome 1280×813 & 390×844)
| uji | hasil |
|---|---|
| buka panel | 0 baris tampil, empty state tampil |
| cari `filter` | C-003, C-001, C-002 |
| cari `rak` (kolom lokasi) | 3 baris (bukti cari seluruh kolom) |
| pilih 1 group | 3 baris, label "1 group dipilih" |
| ubah angka lalu cari `zzz` | baris dirty tetap tampil (border putus-putus) |
| gear → Rapat + sembunyikan meta | kelas `qe-compact`/`qe-nometa` aktif, tersimpan di localStorage |
| tombol Simpan | `elementFromPoint` = elemen di dalam tombol (tidak tertutup) |
| dock `.sf-vm` | `none` saat popup/drawer terbuka, `flex` saat normal |
| mobile 390 | sheet 0–390, gear 348–380, menu group & popover di dalam layar, tanpa overflow-x |
| keyboard | Enter C-003→C-001, ArrowDown→C-002, Ctrl+Enter tanpa perubahan = tidak menyimpan |
| endpoint `reconciliation/quick-count` | `{"ok":true,"updated":0,"unchanged":1}` (tanpa mengubah data) |
| 5 halaman lain | tanpa error, dock normal |

## Berkas
- `public/assets/js/recon-quick.js` — ditulis ulang (v2).
- `app/Views/reconciliation/index.php` — markup panel: head + toolbar + list + foot.
- `public/assets/css/app.css` — blok "MODE CEPAT v2".
- `public/assets/css/sf-viewmode.css` — aturan sembunyikan dock saat popup.
