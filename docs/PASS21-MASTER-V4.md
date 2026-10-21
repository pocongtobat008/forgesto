# PASS21 — Master Template v4 (device pindah ke karyawan, petugas mapping group)

Tanggal: 20 Okt 2026 · File: `public/templates/stockforge-master-v4.xlsx`

## Revisi yang diminta
1. `device_code` & `device_name` **dipindah dari sheet `master` ke sheet `karyawan`**.
2. Kolom `auditor_employee_id` & `observer_employee_id` **dihapus** dari `master` —
   petugas dipetakan otomatis berdasarkan `group_code`.

## Struktur file v4
| Sheet | Kolom | Isi |
|---|---|---|
| `karyawan` | 8 | employee_id, name, role, **device_code**, **device_name**, group_code, password, is_active |
| `master` | **21** (v3: 25) | periode, gudang, group, lokasi, item — tanpa petugas & device |
| `petunjuk` | 2 | panduan lengkap + ringkasan perubahan v4 |

Kolom yang dihapus dari `master`:
`auditor_employee_id`, `observer_employee_id`, `device_code`, `device_name`.

## Aturan mapping (baru)
- **Auditor** = karyawan `role=audit` pada `group_code` yang sama.
- **Observer** = karyawan `role=observer` (role kosong = observer) pada group yang sama.
- **Device** = `device_code`/`device_name` pertama (baris teratas) untuk group tersebut.
- Sheet `karyawan` harus ada **di file yang sama** dengan `master`.
- **Group tidak dibuat otomatis.** Bila `group_code` belum ada di database, baris master
  GAGAL dengan pesan jelas: `group 'XXX' tidak ada di database. Buat group dengan kode
  tersebut di menu Group ...`. Gudang pada baris itu tetap diproses; lokasi/tablet/item
  dilewati supaya tidak muncul error berlapis.
- Daftar error per sheet dibatasi 100 baris pertama (jumlah `failed` tetap dihitung penuh).

## Perubahan kode
`app/Services/MasterWorkbookImportService.php`
- Properti baru: `$groupStaff`, `$petugasDone`, `$tabletDone`, `$groupEnsureTried`.
- `buildGroupStaffMap()` — baca sheet `karyawan` → peta `group_code => {device, auditors, observers}`.
- `staffForGroup()` — lookup peta (fallback ke kode mentah bila group belum resolve).
- `ensureGroupFromMaster()` — buat group yang belum ada, sekali per kode.
- `importMaster()`:
  - petugas: kolom lama (bila masih ada) → fallback ke peta group; upsert di-dedup per
    `role|employee|group` supaya tidak 4.869× query.
  - tablet: kolom lama → fallback device group; upsert di-dedup per `group|device`.
  - `item.device_code` ikut device hasil mapping.
- **Backward compatible**: file v3 (masih memakai kolom petugas/device) tetap jalan.

`app/Controllers/ImportController.php` + `app/Views/import/index.php`
- Link & unduhan template → `stockforge-master-v4.xlsx`, teks hint diperbarui.

## Hasil uji (dry-run, data dev)
| File | Hasil |
|---|---|
| 8 baris GRP-A/GRP-C | petugas 4 (audit+observer per group), tablet 2 (DEV-A1, DEV-C1), item 8, **0 gagal** |
| 500 baris GRP-A/GRP-C | 0 gagal, ±2 detik, petugas tetap 4 (dedup bekerja) |
| v4 penuh, group belum ada (4.869 baris) | master 4.869 gagal dengan pesan jelas, lokasi/item 0 (tidak ada error berlapis), gudang tetap 4.869 |
| v3 lama (regresi) | tetap terimport, petugas & tablet dari kolom lama |

## Temuan data contoh (perlu keputusan user)
1. `group_code` di sheet `master` contoh (`CBL`, `Group Balancer Shaft Finish`, …) **tidak ada**
   di database dev (hanya `GRP-A..GRP-D`) → sesuai keputusan, baris itu GAGAL (bukan dibuat
   otomatis). Buat group dulu / import sheet `group` lebih dulu, baru import master.
2. `period_start`/`period_end` v3 berisi tanggal tidak valid (`2025-09-31`, start 2025-10-01
   untuk `period_month` 2025-09) → sudah dinormalkan di v4 menjadi teks `2025-09-01` / `2025-09-30`.
3. Beberapa baris contoh bergeser terhadap header (mis. `item_business_unit` berisi `200`) dan
   1 baris `expected_qty` melebihi kapasitas kolom. Data contoh, bukan bug import.
