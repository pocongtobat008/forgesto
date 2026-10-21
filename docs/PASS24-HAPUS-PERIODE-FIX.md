# PASS 24 — Hapus periode: tombol tidak berfungsi + tampilan mepet

Tanggal: 21 Okt 2026

## 1. Akar masalah "tidak bisa dihapus"

Bukan di tombolnya, tapi di **langkah 2 (cadangan)**:

```
storage/backups  ->  drwxr-xr-x  root root
```

PHP-FPM berjalan sebagai `www-data`. Folder cadangan dimiliki `root` dengan mode 755,
jadi `file_put_contents()` gagal → `PeriodDeleteService::backup()` melempar
"Cadangan gagal ditulis. Hapus dibatalkan." → `execute()` batal → tidak ada yang terhapus.

Galatnya juga **tidak terlihat**: `deleteExecute()` me-redirect ke `/tahun-buku` (bukan ke
halaman konfirmasi), jadi pesan error lewat begitu saja dan pengguna hanya melihat
"tidak terjadi apa-apa".

Perbaikan:
- `chown -R www-data:www-data storage/backups` + `chmod 775` (diuji tulis sebagai www-data).
- `deleteExecute()` sekarang menangkap `\Throwable` dan redirect **kembali ke
  `/tahun-buku/hapus/{id}`** sehingga pesan galat tampil tepat di halaman konfirmasi.

## 2. Tampilan dirapikan (tidak mepet lagi)

`app/Views/book/_delete_confirm.php` ditulis ulang:

- lebar konten dibatasi 960px, kartu padding 1.35rem/1.5rem, jarak antar kartu 1.1rem;
- header dengan tombol "Kembali ke Tahun Buku";
- banner merah "Tindakan permanen";
- tiap langkah punya nomor bulat + judul + penjelasan;
- Langkah 1: 4 kartu metrik (Periode+status, Snapshot, Group terdampak, Nilai selisih) +
  catatan periode;
- Langkah 3: input besar monospace, petunjuk hidup (abu-abu → merah "belum cocok" →
  hijau "kode cocok"), tombol hapus **disabled** sampai kode persis sama.

Atribut `pattern` HTML5 dihapus — itu salah satu penyebab submit ditolak browser tanpa
pesan yang jelas. Validasi sekarang pakai JS + cek ulang di server.

## 3. Uji

| Uji | Hasil |
|---|---|
| Tulis file di `storage/backups` sebagai `www-data` | berhasil |
| POST hapus periode uji `2099-01` (kode benar) | 302 → periode terhapus, cadangan `hapus_periode_8_*.json` dibuat |
| POST hapus `2099-02` kode salah (`2099-2`) | 302 balik ke halaman konfirmasi, pesan "Konfirmasi tidak cocok…" tampil |
| POST hapus `2099-02` kode benar | terhapus, cadangan dibuat |
| JS tombol | awal disabled → salah tetap disabled → benar aktif + hint hijau |
| Visual 1280px | kartu lega, langkah 1–3 rapi, tombol merah penuh saat kode cocok |

Data uji (`2099-01`, `2099-02`) sudah dihapus. Periode asli tidak disentuh:
`2025-08`, `2025-09`, `2025-10` (36 snapshot), `2026-10` (aktif).

## 4. Upload data tahun sebelumnya

Import master (`/import`, template v6) menerima `period_month`. Kalau periodenya belum ada,
**periode dibuat otomatis**. Diuji: file dengan `period_month=2025-07` → laporan
`periode: created 1`.

Jadi alurnya: hapus periode lama yang isinya data simulasi → upload workbook data
tahun lalu (kolom `period_month` diisi `2025-10`) → periode terbentuk lagi dengan data asli.

## 5. File yang diubah
- `app/Views/book/_delete_confirm.php` (tulis ulang)
- `app/Controllers/BookYearController.php` (`deleteExecute`: tangkap Throwable, redirect ke halaman konfirmasi)
- permission `storage/backups`
