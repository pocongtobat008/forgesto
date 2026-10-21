# PASS 22 — Master Template v5 (PIC per Group, device per orang, periode toleran)

Tanggal: 20 Okt 2026 · File template: `public/templates/stockforge-master-v5.xlsx`
(v4 tetap disimpan sebagai history)

## 1. Yang berubah

### a. Sheet baru: `group` (group + PIC)
- Kolom: `group_code`, `name`, `gudang_code`, `pic_employee_id`, `pic_name`, `description`.
- Diproses **sebelum** `karyawan` dan `master`, jadi satu file bisa membuat group
  sekaligus mengisi lokasi/item (tidak perlu upload group terpisah lagi).
- Template sudah berisi 50 group dari data nyata; PIC tinggal diisi.
- `pic_name` kosong + `pic_employee_id` terisi → nama diambil otomatis dari `employees`.

### b. Kolom PIC di database
```sql
ALTER TABLE `groups`
  ADD COLUMN pic_employee_id VARCHAR(80)  NULL AFTER description,
  ADD COLUMN pic_name        VARCHAR(150) NULL AFTER pic_employee_id;
```
Dipakai di: `Group::$fillable`, `Group::COL_MAP` (`pic`), form create/edit group,
daftar group (subteks di bawah nama), dan halaman detail group.

### c. Device per orang (bukan 1 device per group)
- `buildGroupStaffMap()` sekarang menyimpan **daftar** device per group
  (`devices[] = {code, name, employee}`), bukan hanya device pertama.
- `rowTablet()` menerima `employee_id` → mengisi `devices.user_id` (pemilik tablet).
- Lokasi device: `location_code` baris master; bila kosong → lokasi pertama group.
- Hasil uji data nyata: 100 baris karyawan → 15 device unik (tablet dipakai
  beberapa orang), semuanya punya `user_id`.

### d. Periode toleran (fix bug "harus format YYYY-MM-DD")
Data nyata berisi `period_start = 2025-10-01 00:00:00` dan `period_end = 2025-09-31`
(tanggal tidak ada). Sekarang:
- `normaliseDate()` menerima DateTime/teks, serial Excel, `d/m/Y`, dan memperbaiki
  tanggal mustahil (2025-09-31 → 2025-09-30).
- Bila `period_start`/`period_end` kosong **atau** tidak sesuai `period_month`,
  dipakai batas bulan dari `period_month` (tanggal 1 s/d akhir bulan).
- `audit_month` tetap wajib; bila kosong diturunkan dari `period_start`.

### e. Fix `toInt()` di `ItemImportService`
`8308.256` dulu menjadi **8.308.256** (titik dibuang, 1000×) dan memicu
`SQLSTATE[22003] expected_qty out of range`. Sekarang:
- mendukung format `1.234,5` dan `1,234.5`,
- dibulatkan ke bilangan bulat terdekat (`8308.256` → `8308`),
- dijepit ke rentang kolom INT.

### f. Urutan & laporan import
- Urutan: **gudang → group → karyawan → master → petugas/lokasi/tablet/item**.
- `preCreateWarehouses()` membuat gudang dari kolom `warehouse_code` sheet master
  lebih dulu, supaya sheet `group` langsung bisa menempel ke gudangnya.
- Counter laporan tidak lagi di-reset oleh `importMaster()` (group/gudang yang
  diproses lebih awal tetap terlihat), struktur laporan selalu lengkap
  (`created/updated/failed/errors`), dan section `master` disembunyikan bila kosong.

## 2. Hasil uji file nyata (4.869 baris master, 100 karyawan, 50 group)

Dry-run dan commit, ±20 detik:

| Sheet | created | updated | failed |
|-------|--------:|--------:|-------:|
| karyawan | 11 | 89 | 0 |
| group | 50 | 4.869 | 0 |
| periode | 0 | 1 | 0 |
| gudang | 0 | 4.869 | 0 |
| petugas | 0 | 100 | 0 |
| lokasi | 85 | 4.784 | 0 |
| tablet | 15 | 85 | 0 |
| item | 4.736 | 133 | 0 |

Verifikasi DB setelah commit: 54 group, 4.775 item, 92 lokasi, 29 device
(semua ber-`user_id`), 25 user, 113 keanggotaan group. PIC 3 group contoh tersimpan
(`CBL → Ues Kurni`, dst). `expected_qty` contoh: 8.308 (bukan 8.308.256).

## 3. File yang diubah
- `app/Services/MasterWorkbookImportService.php` (urutan, PIC, multi-device, periode, laporan)
- `app/Services/ItemImportService.php` (`toInt`)
- `app/Models/Group.php` (fillable + COL_MAP `pic`)
- `app/Controllers/GroupController.php` (validasi + simpan PIC, auto-nama dari karyawan)
- `app/Views/groups/form.php`, `index.php`, `show.php` (tampilan PIC)
- `app/Controllers/ImportController.php`, `app/Views/import/index.php` (template v5)
- `public/templates/stockforge-master-v5.xlsx` (baru)

## 4. Catatan
- Import ulang file yang sama bersifat idempotent (update, bukan duplikat).
- Group tanpa PIC tetap boleh; PIC hanya informatif (tidak mengubah hak akses).
- Bila PIC ingin dijadikan admin group otomatis, perlu aturan terpisah
  (mis. PIC otomatis masuk `user_groups` dengan role `adminsto`).
