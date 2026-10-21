
## 3. Verifikasi ulang dengan rantai CSS asli (19 berkas, urutan layout)
Dibuat harness yang memuat tokens → app → sf-ui → flat-material → sf-glass → forge-gradient →
hero-menu → sf-progress → items-mobile → sf-viewmode → money-fit → palette-scan → sf-usermenu →
sf-rowdetail → sf-tables → a11y-fixes → a11y-pass2 → topbar-brand → topbar-quicksearch.
Hasil ukur di 440px: kartu **118px**, jarak head→nilai **6px**, select **34px**, `flex` kotak nilai `0 0 auto`.

Catatan cache: `asset_v()` memakai `filemtime`, jadi URL CSS berubah tiap deploy. Cloudflare
meng-cache aset `max-age=14400`, sehingga **tab yang sudah terbuka sebelum deploy harus di-reload**
(hard refresh) agar CSS baru terpakai. Panel kini punya penanda `data-adv-build="PASS20"`.
