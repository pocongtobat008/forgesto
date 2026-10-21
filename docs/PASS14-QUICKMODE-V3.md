# Mode Cepat v3 — siap data ribuan, tampil hanya yang diedit

Tanggal: 18 Okt 2026 · Halaman: `/reconciliation` → tombol **Mode Cepat** (`#qe-open`)

## Masalah yang diselesaikan
Data barang akan ribuan. Versi v2 membaca **seluruh baris `<tr>`** tabel lalu
membuat satu elemen per baris — makin banyak barang, makin berat saat dibuka.
Selain itu tidak ada cara cepat melihat "berapa yang sudah saya edit".

## Yang berubah

### 1. Data diambil dari server, bukan dari tabel
Endpoint baru (JSON, LIMIT/OFFSET di SQL):

| Endpoint | Guna |
|---|---|
| `GET /reconciliation/quick-items?q=&gids=1,2&limit=80&offset=0` | daftar ringkas (maks 80/permintaan) |
| `GET /reconciliation/quick-groups` | daftar group + jumlah item |

- Panel **tidak pernah** merender lebih dari 80 baris sekaligus; kalau hasil
  lebih banyak muncul tombol **"Muat 80 lagi · 80 dari 1.240"**.
- Total hasil dihitung `COUNT(*)` terpisah, jadi angka "x dari y" akurat tanpa
  menarik semua baris.
- Waktu query ditampilkan di toolbar: `1.240 cocok · 12 ms` — bisa dipantau
  sendiri saat data bertambah.
- Kolom yang diambil cuma 12 (tanpa subquery observer/auditor seperti
  `listDetailed()`), jadi query tetap ringan.

### 2. Angka yang diketik disimpan di Map, bukan di DOM
Baris boleh hilang dari layar (ganti filter, muat ulang daftar) tanpa
kehilangan angka yang sudah diketik. Simpan mengirim **semua** id yang diedit,
bukan hanya yang terlihat.

### 3. Tombol "Hanya yang diedit (N)"
Tombol di toolbar (`#qe-only`) menampilkan **persis baris yang sedang diubah**
beserta jumlahnya — tanpa memanggil server, jadi instan. Cocok untuk mengecek
"tadi saya sudah mengedit berapa item" sebelum menyimpan.

### 4. Daftar editan di-reset setiap panel dibuka
`open()` mengosongkan Map editan, kotak cari, pilihan group, dan mode
hanya-diedit. Jadi sesi berikutnya mulai bersih (sesuai permintaan: "saat mode
cepat selanjutnya akan hilang lagi").

## Verifikasi live
| Uji | Hasil |
|---|---|
| Buka panel | 0 baris, empty state, badge 0 |
| Cari `oli` | 1 baris, `1 cocok · 14 ms` |
| Pilih semua group | 3 baris, `3 cocok · 5 ms` |
| Edit 2 baris | badge `2`, `2 item siap disimpan` |
| Klik "Hanya yang diedit" | 2 baris (C-001 +10, C-003 +24), label `2 item sedang diedit` |
| Simpan | toast `2 item tersimpan`, badge 0, tombol Simpan nonaktif |
| Kembalikan nilai asli lalu simpan | nilai balik 9 & 19, badge 0 (data tidak berubah) |
| Tutup → buka lagi | 0 baris, badge 0, kotak cari kosong, group reset |
| Tombol Simpan saat ada editan | `elementFromPoint` = elemen dalam tombol (tidak tertutup) |
| Mobile 390px | group + tombol edit + gear satu baris (47px), tanpa overflow-x |
| Offset/LIMIT | `limit=1&offset=2` → C-003 (benar) |
| Halaman lain | tanpa error |

## Berkas
- `app/Models/Reconciliation.php` — `quickList()`, `quickTotal()`, `quickWhere()`
- `app/Controllers/ReconciliationController.php` — `quickItems()`, `quickGroups()`
- `config/routes.php` — 2 route GET baru
- `app/Views/reconciliation/index.php` — tombol `#qe-only` + teks subjudul
- `public/assets/js/recon-quick.js` — ditulis ulang (v3)
- `public/assets/css/app.css` — blok "MODE CEPAT v3"
