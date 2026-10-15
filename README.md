# StockForge

**StockForge** adalah aplikasi manajemen *inventory counting* & *audit* untuk STO,
dibangun dengan **PHP native** (tanpa framework) dan **MySQL/MariaDB**.

Aplikasi ini mengelola data per group: observer menghitung data di lokasi,
hasilnya dikirim (posting) ke audit untuk dicek minimal **10% per group**,
lalu Admin STO memverifikasi hasilnya. Bila ada selisih, item otomatis
dibuka (unlock) agar observer dapat mencari fisiknya.

---

## Fitur

### Dashboard
- Kartu ringkasan per group: **100% dihitung**, **belum dihitung sama sekali**,
  **100% diaudit**, **belum diaudit sama sekali**.
- Ringkasan item: total, sudah dihitung, sudah diaudit, selisih terbuka, item terkunci.
- Grafik progres (donut + bar per group) yang dapat **difilter per group atau semua group**.
- Aktivitas terbaru dan riwayat import.

### Leveling / Role
| Role | Tugas |
|------|-------|
| `poweradmin` | Mengelola semua user & role akses, group, STO, lokasi, dan device. |
| `adminsto` | Admin pengelola semua data STO, seluruh group, data yang masuk, selisih, dan unlock. |
| `audit` | Pengecekan minimal 10% data per group yang didelegasikan. |
| `observer` | Input & menghitung semua data di lokasi, mencari selisih. |

Setiap user hanya dapat melihat **group yang didelegasikan** (poweradmin & adminsto melihat semua).

### Data & Alur
- Master data: **Group, User, Lokasi, Device (tablet lokasi)**.
- **Data STO** (items): hitung → posting (terkunci) → audit → selesai / selisih.
- **Audit**: antrean audit, target cakupan minimal 10% per group, riwayat audit.
- **Selisih**: ditandai otomatis oleh audit (atau manual oleh Admin STO),
  observer mencari dan memperbarui hasilnya, Admin STO menyelesaikan.
- **Import** data dari **Excel (.xlsx)** atau **CSV** (menambah data baru & memperbarui yang lama).
- **Notifikasi** per-user, per-role, atau per-group + badge jumlah belum dibaca.
- **Aktivitas** (activity log) untuk audit trail seluruh aksi.
- UI/UX modern ala startup, **full responsive** (mobile, tablet, desktop),
  lengkap dengan **dark mode**.

### Aturan penguncian (locking)
1. Observer menghitung data → status `counted`.
2. Observer **posting** ke audit → status `posted` dan data **terkunci**.
3. Untuk mengubah data terkunci, Admin STO melakukan **unlock**.
4. Bila audit menemukan selisih → data **otomatis terbuka** (auto-unlock) agar observer dapat mencari.

> ⚠️ Form login **sengaja belum dibuat** (fase pengujian). Sebagai gantinya
> tersedia **pengalihan user** di menu *Profil & Akses* untuk menguji tiap role.
> Set konfigurasi `app.auth.login_enabled` / `app.auth.dev_switcher` bila ingin menyesuaikan.

---

## Kebutuhan

- PHP **8.0+** dengan ekstensi: `pdo_mysql`, `mbstring`, `zip`, `simplexml`
  (`zip` & `simplexml` dibutuhkan untuk import `.xlsx`; CSV tidak memerlukannya)
- MySQL **5.7+ / 8.x** atau MariaDB **10.4+**
- Apache/Nginx (XAMPP, Laragon, dsb.) atau PHP built-in server

---

## Instalasi

### 1. Siapkan database

```bash
mysql -u root -p < database/schema.sql
mysql -u root -p stockforge < database/seed.sql   # opsional: data contoh
```

`database/schema.sql` membuat database `stockforge` dan seluruh tabel.
`database/seed.sql` mengisi data demo (4 group dengan skenario berbeda).

### 2. Konfigurasi koneksi

Sesuaikan di `config/database.php`, atau gunakan environment variable:

```bash
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=stockforge
DB_USERNAME=root
DB_PASSWORD=
```

### 3a. Jalankan dengan PHP built-in server (paling cepat)

```bash
php -S localhost:8000 -t public public/router.php
```

Buka <http://localhost:8000>.

### 3b. Jalankan dengan Apache (XAMPP / Laragon)

Salin folder project ke `htdocs`, misalnya `C:/xampp/htdocs/sistock`.

**Cara paling mudah** — buka `http://localhost/sistock`.
Sudah berfungsi tanpa konfigurasi tambahan: file `index.php` + `.htaccess` di
root project meneruskan semua request ke `public/index.php` dan menyajikan
aset dari `public/assets`.

**Cara alternatif (paling aman untuk produksi)** — buka `http://localhost/sistock/public`.
Ini adalah document root yang sesungguhnya; `config/`, `database/`, dan
`app/` tidak pernah bisa diakses browser.

Bila ingin bersih tanpa `/public` (atau nama domain sendiri), buat VirtualHost:

```
<VirtualHost *:80>
    ServerName sistock.local
    DocumentRoot "C:/xampp/htdocs/sistock/public"
    <Directory "C:/xampp/htdocs/sistock/public">
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

Jangan lupa tambahkan `127.0.0.1 sistock.local` ke file `hosts`, lalu buka
`http://sistock.local`. Pada setup ini file `index.php` dan `.htaccess` di root
project boleh dihapus.

#### Jika masih error

| Gejala | Penyebab & solusi |
|--------|-------------------|
| Halaman daftar file / "Index of /sistock" | Tidak ada `index.php` di root. Pastikan file `index.php` dan `.htaccess` hasil setup ini ikut tersalin. |
| **404 Not Found** di semua halaman | `mod_rewrite` belum aktif. Di XAMPP: buka `httpd.conf`, hilangkan `#` pada `LoadModule rewrite_module modules/mod_rewrite.so`, lalu restart Apache. |
| **403 Forbidden** | `AllowOverride` masih `None`. Set `AllowOverride All` pada `<Directory "C:/xampp/htdocs">`, restart Apache. |
| Tampilan polos tanpa CSS | `RewriteRule ^assets/(.*)$ public/assets/$1` tidak jalan (mod_rewrite). Sementara bisa diakses lewat `.../public/assets/css/app.css`. |
| Rewrite tidak bisa dipakai (hosting ketat) | Buka `http://localhost/sistock/public/index.php?r=dashboard`. Aplikasi memang mendukung fallback `?r=`. |
| `Tidak dapat terhubung ke database` | Sesuaikan `config/database.php` dan pastikan MySQL di XAMPP sudah **Start**. |

> **Penting:** jangan pernah mengarahkan DocumentRoot ke root project tanpa
> `.htaccess` yang membatasi akses, karena `config/database.php` berisi kredensial.

---

## Akun & Role (data seed)

Login dimatikan; user diambil dari sesi. User default adalah `poweradmin`.
Gunakan menu **Profil & Akses** untuk berpindah user/role saat menguji.

| Username | Role | Group |
|----------|------|-------|
| `poweradmin` | Power Admin | semua |
| `adminsto` | Admin STO | semua |
| `audit.ab` | Audit | GRP-A, GRP-B |
| `audit.cd` | Audit | GRP-C, GRP-D |
| `obs.alpha` | Observer | GRP-A |
| `obs.bravo` | Observer | GRP-B |
| `obs.charlie` | Observer | GRP-C |
| `obs.delta` | Observer | GRP-D |

---

## Hak akses (dapat diubah di `config/app.php`)

| Ability | Role |
|---------|------|
| `manage_users`, `manage_roles` | poweradmin |
| `manage_groups` | poweradmin |
| `manage_master` (lokasi & device) | poweradmin |
| `manage_data` (tambah/ubah/hapus data) | poweradmin, adminsto |
| `count_data`, `post_data` | poweradmin, adminsto, observer |
| `audit_data` | poweradmin, audit |
| `manage_discrepancy`, `unlock_data` | poweradmin, adminsto |
| `search_discrepancy` | observer |
| `import_data` | poweradmin, adminsto |
| `view_all_groups`, `view_activity` | poweradmin, adminsto |

---

## Format Import (.xlsx / .csv)

Baris pertama = header. Nama kolom fleksibel (mis. `kode` = `code`).

| Kolom | Wajib | Keterangan |
|-------|-------|------------|
| `group_code` | – | Kode group. Boleh dikosongkan bila group dipilih di form import. |
| `code` | ✔ | Kode unik barang **per group**. |
| `name` | ✔ | Nama barang/data. |
| `category` | – | Kategori. |
| `unit` | – | Satuan. |
| `expected_qty` | – | Jumlah menurut sistem. |
| `counted_qty` | – | Hasil hitung fisik (opsional, langsung tercatat). |
| `location_code` | – | Kode lokasi (boleh nama lokasi). |
| `device_code` | – | Kode atau serial number device. |
| `condition_note` | – | Catatan kondisi. |

Unduh contoh template dari menu **Import Data → Unduh Template CSV**.

Item yang sudah **terkunci** tidak akan diubah oleh import (dilaporkan sebagai gagal),
sehingga perlu di-unlock terlebih dahulu.

---

## Struktur Project

```
SiStock/
├── app/
│   ├── bootstrap.php              # Autoloader, config, session
│   ├── Controllers/               # Controller per modul
│   ├── Core/                      # Framework mini (Router, Database, Model, Auth, ...)
│   ├── Helpers/                   # functions.php, icons.php
│   ├── Models/                    # Query & logic per tabel
│   ├── Services/                  # SpreadsheetReader, ItemImportService
│   └── Views/                     # Tampilan (layout, partials, per modul)
├── config/
│   ├── app.php                    # Identitas, role, abilities, upload
│   ├── database.php               # Koneksi MySQL
│   └── routes.php                 # Definisi route
├── database/
│   ├── schema.sql                 # Struktur tabel
│   └── seed.sql                   # Data contoh
├── public/                        # ← DocumentRoot
│   ├── index.php                  # Front controller
│   ├── router.php                 # Router untuk PHP built-in server
│   ├── .htaccess / web.config     # Rewrite rules
│   └── assets/{css,js}
├── storage/                       # Folder runtime (import, log)
└── README.md
```

### Konvensi penamaan
- **Controller**: `XxxController@action` (lihat `config/routes.php`).
- **Model**: satu file per tabel, query kompleks berada di model (bukan di view).
- **View**: satu folder per modul, layout tunggal `layouts/app.php`,
  partial reusable di `Views/partials/`.

---

## Alur Kerja Singkat

1. **Power Admin** membuat Group, User (+ delegasi group), Lokasi, dan Device.
2. **Power Admin / Admin STO** mengisi data master (manual atau import Excel).
3. **Observer** menghitung data di lokasi → simpan hasil hitung → **Posting** ke audit.
4. **Audit** mengecek minimal 10% data per group.
   - Sesuai → status `audited`.
   - Selisih → otomatis dibuka & dicatat sebagai selisih; observer dinotifikasi.
5. **Observer** mencari fisik pada data selisih → memperbarui hasil temuan.
6. **Admin STO** menyelesaikan selisih, memantau dashboard, dan melakukan unlock bila diperlukan.

---

## Catatan keamanan

- Semua output di-escape (`e()`), seluruh query memakai **prepared statement**.
- Form state-changing menggunakan **CSRF token**.
- Untuk produksi: set `app.debug` menjadi `false` di `config/app.php`.
- Aktifkan kembali login (form + middleware) sebelum dipakai produksi.
