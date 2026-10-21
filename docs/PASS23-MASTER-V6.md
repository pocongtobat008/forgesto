# PASS 23 — Template Master v6 (tanpa sheet group, PIC di sheet karyawan)

Tanggal: 21 Okt 2026 · File: `public/templates/stockforge-master-v6.xlsx`
(v3/v4/v5 disimpan sebagai history)

## 1. Perubahan sesuai permintaan

### a. Sheet `group` dihapus
Template sekarang **3 sheet**: `karyawan` (9 kolom), `master` (21 kolom), `petunjuk`.
Semua informasi group sudah ada di dua sheet data:
- `master` → `group_code` + `group_name` + `warehouse_code`
- `karyawan` → `group_code` (+ `pic_employee_id`)

Uploader sekarang **menurunkan group dari data itu**:
`ensureGroupsFromSheets()` mengumpulkan `group_code` dari sheet master & karyawan,
lalu:
- group belum ada → **dibuat** (kode, nama dari `group_name`, gudang dari `warehouse_code`),
- group sudah ada & namanya masih sama dengan kodenya → nama dirapikan dari `group_name`,
- nama yang sudah dikurasi manusia **tidak** ditimpa.
Urutan tetap: gudang → group (otomatis) → karyawan → master → petugas/lokasi/tablet/item.

### b. PIC pindah ke sheet karyawan
Kolom baru `pic_employee_id` (opsional) di sheet karyawan, cukup diisi **satu baris
per group**. `applyGroupPicFromKaryawan()` menyimpan PIC + mengambil nama dari tabel
`employees`. Kalau kolom tidak ada / kosong, PIC group tidak diubah.

### c. Tombol unduh template diperbaiki
Tombol besar "Unduh Template Excel (.xlsx)" di halaman Import masih menunjuk
**v3** (`templates/stockforge-master-v3.xlsx`). Sekarang v6, begitu juga tautan
di teks bantuan. `ImportController::templateMaster()` dan `templateXlsx()`
sama-sama memakai v6.

## 2. Hasil uji

| Uji | Hasil |
|---|---|
| File kiriman Bapak (3 sheet, 4.869 baris master, 100 karyawan) | dry-run & commit: **0 gagal**, 19,5 detik, idempotent (semua update) |
| Group baru otomatis (`TESTAUTO1`, `TESTAUTO2`) | dibuat 2 group lengkap dengan gudang; PIC `TESTAUTO1 = Ues Kurni` dari kolom `pic_employee_id`; `TESTAUTO2` tanpa PIC (kosong) |
| Unduhan publik | `https://sto.izal.my.id/templates/stockforge-master-v6.xlsx` → HTTP 200, 495.527 byte, 3 sheet, karyawan 9 kolom |
| Halaman Import | 2 tautan template semuanya v6, teks "Berisi 3 sheet" |

Data uji (`TESTAUTO1/2`, item, lokasi) sudah dihapus lagi; DB kembali 54 group /
4.775 item / 92 lokasi.

## 3. File yang diubah
- `app/Services/MasterWorkbookImportService.php`
  (`ensureGroupsFromSheets()`, `applyGroupPicFromKaryawan()`, guard sheet group opsional)
- `app/Controllers/ImportController.php` (template v6, 2 endpoint)
- `app/Views/import/index.php` (tombol + tautan + teks 3 sheet)
- `public/templates/stockforge-master-v6.xlsx` (baru)

## 4. Catatan
- File v5 (dengan sheet `group`) **masih bisa** diupload: bila sheet `group` ada,
  sheet itu yang dipakai dan auto-derive dilewati (backward compatible).
- PIC tetap informatif (tidak mengubah hak akses). Bila PIC harus otomatis jadi
  admin group, perlu aturan terpisah di `user_groups`.
