LALANGS CSS CONTROL — 0.1.0
==========================
Requires WordPress 6.2+ and PHP 7.4+.
License: GPL-2.0-or-later.

TUJUAN
Kawalan Critical CSS manual dan pemuatan stylesheet secara async mengikut
halaman. Tiada API, langganan atau kuota QUIC.cloud. Plugin ini TIDAK menjana
Critical CSS, membuang unused CSS, atau mengurangkan saiz fail CSS sendiri.
Semua stylesheet yang dipilih masih dimuat turun, cuma tidak lagi menghalang
render awal apabila Critical CSS yang mencukupi tersedia.

PEMASANGAN
1. WordPress > Plugins > Add New Plugin > Upload Plugin.
2. Pilih lalangs-css-control-0.1.0.zip, Install Now, kemudian Activate.
3. Buka Settings > Lalangs CSS Control.
4. Plugin bermula OFF untuk semua halaman dan tidak mengubah CSS sehingga
   terdapat Critical CSS dan peraturan diaktifkan, atau admin membuka Preview.

ALIRAN KERJA HOMEPAGE
1. Kekalkan LiteSpeed page cache. Sebelum menguji plugin ini, matikan Load CSS
   Asynchronously, UCSS Inline dan CSS Combine dalam LiteSpeed; matikan fungsi
   CSS async/Critical CSS daripada plugin lain juga. Catat tetapan asal.
2. Pilih Homepage, klik Inspect CSS Halaman untuk mendapatkan handle sebenar.
   Panel Inspect memerlukan administrator yang log masuk dan nonce sah.
3. Semak allowlist. Nama contoh mengikut audit Lalangs; semak semula selepas
   menukar theme atau template. Handle lain tidak diubah.
4. Masukkan Critical CSS tanpa <style> dan tanpa @import. Sertakan CSS header,
   hero, menu dan susun atur atas fold untuk desktop DAN mobile. Fon perlu
   mempunyai fallback yang sesuai. Maksimum 150 KB.
5. Biarkan checkbox aktif OFF dan Simpan Tetapan.
6. Klik Preview Tetapan Tersimpan. Preview memproses CSS walaupun checkbox OFF,
   untuk administrator sahaja, dengan arahan tidak cache.
7. Semak desktop dan mobile: header, hero, menu, booking popup dan kalendar.
   Semak dengan rangkaian perlahan supaya kehilangan Critical CSS jelas.
8. Selepas hasil memuaskan, tandakan Aktifkan untuk pengunjung, Simpan Tetapan,
   kemudian Purge cache homepage dalam LiteSpeed. Semak semula sebagai pelawat
   yang log keluar dan jalankan PageSpeed semula.

PERLINDUNGAN DAN SKOP
- Setiap halaman mempunyai peraturan sendiri; homepage memakai peraturan home.
- Versi ini menyokong homepage dan post type WordPress 'page'. Halaman single
  accommodation, product, blog post, archive dan search tidak disasarkan.
- Critical CSS kosong menyebabkan tiada perubahan, termasuk dalam Preview.
- Hanya stylesheet WordPress yang terdapat dalam allowlist dan dihoskan pada
  domain WordPress/site yang sama diproses. CSS yang ditulis terus dalam HTML
  tidak diproses. Google Fonts di domain luar tidak diproses.
- media asal dipulihkan selepas stylesheet siap; noscript menyediakan fallback
  apabila JavaScript dimatikan. CSS print, data-no-async dan tag yang mempunyai
  onload/onerror sendiri dilangkau.
- JavaScript, jQuery dan font tidak diubah. Tiada dequeue/deregister asset.
- CSS MotoPress, datepick dan Select2 tidak dimasukkan dalam allowlist default;
  jangan tambah tanpa menguji borang tempahan.
- Elementor editor, Customizer, feed, REST dan WordPress preview dilangkau.
- Simpan tetapan memerlukan capability manage_options dan nonce WordPress.
- Inspector menyenaraikan stylesheet berdaftar; ia bukan laporan CSS coverage.

ROLLBACK
OFF-kan peraturan halaman, Simpan Tetapan dan Purge cache halaman tersebut.
Atau nyahaktifkan plugin dan Purge cache. Tetapan disimpan untuk kegunaan semula.
Cache lama boleh mengandungi HTML async sehingga ia dipurge.

BATASAN
Inline onload digunakan untuk memulihkan media. Polisi CSP yang menghalang
inline event handlers memerlukan pelaksanaan lain; jangan aktifkan sebelum
Preview berjaya. Plugin optimasi lain mungkin menulis semula link stylesheet,
maka kombinasi plugin perlu diperiksa pada HTML sebenar selepas cache dipurge.
Plugin tidak menjamin skor atau penurunan LCP tertentu. Critical CSS yang tidak
cukup boleh menghasilkan layout berkelip atau salah semasa CSS penuh dimuat.

STATUS PENGESAHAN
Kod telah disemak dan struktur ZIP diperiksa. Persekitaran pembinaan tidak
mempunyai PHP atau executable Chromium, jadi PHP lint, ujian runtime WordPress
dan ujian browser belum dilaksanakan. Fail ini ialah versi awal untuk diuji
melalui Preview sebelum digunakan kepada pengunjung.
