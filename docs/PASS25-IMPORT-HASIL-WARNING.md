# PASS 25 — Halaman hasil import master: warning PHP (Undefined array key 'master')

Tanggal: 21 Okt 2026

## Gejala

Setelah upload file master, halaman "Hasil Import Master" menampilkan:

```
Warning: Undefined array key "master" in .../app/Views/import/master.php on line 30
Warning: Trying to access array offset on value of type null in .../master.php on line 33
Warning: Trying to access array offset on value of type null in .../master.php on line 34
Warning: Trying to access array offset on value of type null in .../master.php on line 35
```

Tabel hanya menampilkan satu baris "karyawan" dengan angka 0.

## Akar masalah

`MasterWorkbookImportService::run()` mengembalikan dua daftar yang bisa tidak sinkron:

- `sheets` = irisan `self::SHEETS` dengan sheet yang ada di file (masih memuat `master`);
- `report` = hasil per sheet — dan **`report['master']` sengaja di-unset** kalau
  created/updated/failed semuanya 0 ("hanya berisi error baris, sembunyikan bila kosong").

View melakukan `foreach ($out['sheets'] as $s) { $r = $out['report'][$s]; }` →
kunci `master` sudah tidak ada → `$r = null` → warning di tiga baris berikutnya.

Jadi pemicunya: **sheet `master` ada di file tapi tidak menghasilkan baris apa pun**
(biasanya hanya berisi header). Bukan data rusak — hanya laporan yang tidak konsisten.

## Perbaikan

1. `app/Services/MasterWorkbookImportService.php`
   - `sheets` sekarang diturunkan dari kunci `report` yang benar-benar ada, urut
     `periode, karyawan, master, gudang, group, petugas, lokasi, tablet, item`.
   - `periode` hanya ditampilkan bila benar-benar membuat/memperbarui periode.
   - Tidak ada lagi kunci "hantu" di `sheets`.

2. `app/Views/import/master.php` (ditulis ulang)
   - defensif: `$r = $out['report'][$s] ?? null` → baris dilewati bila tidak ada;
   - label lengkap (`Periode`, `Karyawan`, dst. — sebelumnya mentah "periode"/"karyawan");
   - baris **Total** di footer tabel;
   - angka gagal diberi warna merah;
   - empty-state: "Tidak ada baris data yang terbaca dari file ini… nama sheet harus…";
   - kartu CTA dry-run dipisah rapi.

## Uji

Render view dengan `error_reporting(E_ALL)` + handler yang mengubah warning jadi exception:

| Kasus | Hasil |
|---|---|
| `sheets=['karyawan','master']`, `report` tanpa `master` (kasus asli) | OK, tanpa warning |
| laporan normal 6 sheet + 1 error | OK, 8 `<tr>` |
| `report` kosong | OK, empty-state tampil |
| tanpa kunci `sheets` | OK, empty-state tampil |

End-to-end lewat browser (login admin, upload file):

| File | Hasil |
|---|---|
| sheet `master` kosong + `gudang` 1 baris | halaman normal, Gudang 0/0/1, pesan error baris tampil |
| `karyawan` 2 baris + `master` 2 baris (dry-run) | Periode 0/1, Karyawan 2/0, Group 0/2, Petugas 0/2, Lokasi 1/1, Item 2/0, **Total 5/6/0**, 0 warning |

Dry-run tidak menulis apa pun (diverifikasi: tidak ada item/lokasi demo di DB setelah uji).

## Catatan

Kalau setelah perbaikan ini baris **Master** tidak muncul di tabel, artinya sheet `master`
di file benar-benar tidak berisi baris data (hanya header). Isi minimal satu baris dengan
`period_month` + `group_code` + `item_code`, lalu jalankan dry-run lagi.

## File yang diubah
- `app/Views/import/master.php` (tulis ulang)
- `app/Services/MasterWorkbookImportService.php` (konsistensi `sheets` vs `report`)
