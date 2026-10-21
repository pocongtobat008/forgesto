# PASS 19 — Popup "Pilih Nilai/Group" tertutup Drawer Pencarian Lanjutan

**Laporan:** [Pocong-Tobat] — "pada modul pencarian lanjutan saat klik pilih nilai, popup list
dropdown berada di belakang pop up pencarian sehingga tidak bisa dipilih."

## Akar masalah
| Elemen | z-index | Posisi di DOM |
|---|---|---|
| `.adv-backdrop` (scrim drawer) | **1190** `!important` | `body` |
| `.adv-drawer` (panel pencarian lanjutan) | **1200** `!important` | `body` |
| `.adv-combo-backdrop` (scrim popup nilai) | **520** `!important` | `body` |
| `.adv-combo-pop` (popup Pilih Nilai/Group) | **530** `!important` | `body` |

Drawer dinaikkan ke 1200 pada hotfix v1.4.1d (agar tombol X tidak terpotong topbar),
tetapi popup pilihan nilai masih memakai angka lama 520/530. Karena keduanya **bersaudara
di `<body>`**, popup dirender **di bawah** drawer + scrim-nya → tampak gelap, tidak bisa diklik.

## Perbaikan
1. **CSS** `sf-ui.css`: `.adv-combo-backdrop` 520 → **1440**, `.adv-combo-pop` 530 → **1450**.
2. **JS** `partials/advanced-search.php` → `liftComboAboveOverlays()` dipanggil setiap popup dibuka:
   membaca z-index tertinggi dari semua overlay `position:fixed/absolute` yang sedang tampil di
   `body` (drawer 1200, qe-backdrop 1300, notif 1400, rd-drawer 1500, …) lalu menaruh
   scrim = max+10 dan popup = max+20 via `style.setProperty(..., 'important')`.
   Jadi popup **selalu** di atas overlay apa pun, sekarang maupun nanti.
3. **Esc**: sebelumnya Esc tidak menutup popup (kondisi `comboPop.hidden` membuat handler
   drawer mengabaikan). Sekarang Esc menutup popup dulu, baru drawer.

## Verifikasi (memakai CSS asli dari server, 1280×813)
| Skenario | `elementFromPoint` di area tumpang-tindih | Hasil |
|---|---|---|
| z-index lama 520/530 | `adv-drawer-body` | ❌ popup tertutup drawer (bug tereproduksi) |
| CSS baru saja (1440/1450) | `adv-pop-meta` (anak popup) | ✅ |
| CSS + lift dinamis (drawer 1200) | `adv-pop-meta`, z = 1210/1220 | ✅ |
| CSS + lift, ada overlay z=1500 | `adv-pop-meta`, z = 1510/1520 | ✅ |
| Klik checkbox nyata lewat koordinat | `INPUT` → `checked = true` | ✅ |

Berkas: `public/assets/css/sf-ui.css`, `app/Views/partials/advanced-search.php`.
Berlaku untuk semua halaman yang memakai partial ini (items, groups, devices, locations,
users, discrepancies, master_data, activity).
