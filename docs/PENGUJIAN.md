# Hasil pengujian implementasi

Tanggal pemeriksaan: **19 November 2026**. Pengujian memakai data simulasi yang dibuat dari `01_schema.sql` dan `02_seed.sql`; tidak memakai database atau akun asli pengguna.

## Lingkungan dan hasil aktual

| Pemeriksaan | Hasil |
| --- | --- |
| PHP native | PHP 8.4.24 CLI development server |
| Database yang benar-benar dieksekusi | MariaDB 11.8.6, InnoDB |
| Impor SQL skema + seed | Berhasil, semua tabel dan data demo tersedia |
| Integrasi HTTP | **117 dari 117 pemeriksaan lulus** |
| Kontrak nama method Modul 2 | **47 dari 47 lulus**, termasuk sifat private kedua validator |
| Pemeriksaan sintaks | **63 file PHP tanpa kesalahan sintaks**; konfigurasi lokal pengujian tidak ikut paket |
| Log PHP pada eksekusi integrasi terakhir | Tidak ada warning, notice, atau exception |
| Konfigurasi Compose | YAML berhasil diparse; service web, db dan phpMyAdmin tersedia dalam konfigurasi |
| Visual desktop | Login, pencarian lowongan, formulir pendaftaran, formulir pembuatan, timeline dan verifikasi admin diperiksa di browser |
| Visual layar kecil | Lebar frame 390 px (area konten 375 px dengan scrollbar); halaman pencarian tidak memiliki overflow horizontal halaman |

Pemeriksaan integrasi menggunakan HTTP sungguhan melalui server PHP dan database, bukan model in-memory. PDF nyata dikirim dengan multipart/form-data. Cookie sesi dipisahkan untuk mahasiswa, mahasiswa kedua, tiga rekruter, dan admin. Pemeriksaan visual halaman setelah login menggunakan salinan HTML hasil respons dari sesi akun demo yang sama; aksi dan perubahan database diperiksa oleh suite HTTP. Tidak ada screenshot atau salinan HTML pengujian dalam paket akhir.

Koreksi yang ditemukan saat pengujian: penempatan berkas sesi dibuat eksplisit agar portabel; renderer diperbaiki agar data formulir benar-benar terisi dari database/sesi; alur Kembali Mengedit diperiksa agar isian tidak hilang. Setelah koreksi, semua 117 pemeriksaan lulus. Perapian format PHP kemudian diverifikasi mempertahankan token non-whitespace yang sama dan seluruh file kembali lulus lint.

## Cakupan alur

- Autentikasi tiga peran, penolakan registrasi, CSRF, login salah/injeksi, kepemilikan lamaran/dokumen dan pembatasan route.
- Pencarian dan kombinasi filter; detail, draf, batas visibilitas perusahaan, validasi syarat dan pendaftaran ganda.
- Penolakan file non-PDF/PDF palsu/>5 MB; upload valid; draf menjadi lamaran dan muncul pada rekruter.
- Penilaian administrasi, pembukaan chat, pesan dua arah dengan escaping, perubahan tahap, penilaian tahap lama, serta status final.
- Jadwal, kalender mahasiswa, notifikasi, pengingat jatuh tempo, ubah/hapus pengingat dan pembatasan pemilik.
- Buat/edit/tutup/hapus lowongan, validasi kolom, periksa kelengkapan, batal/kembali mengedit dan penolakan publikasi perusahaan belum terverifikasi.
- Pemeriksaan dokumen, kesimpulan verifikasi, persetujuan/penolakan, penghapusan perusahaan dan pencabutan sesi rekruter.
- Rekap/CSV sesuai peran, profil mahasiswa dan unggah ulang legalitas yang memicu notifikasi admin.

## Menjalankan ulang

Gunakan **database uji baru** dengan kedua SQL, karena suite mengubah data demo. Jangan menjalankan pada database berisi akun/lamaran nyata. Suite menguji kondisi data awal; pengulangan memerlukan salinan data awal lagi.

```sh
python tests/integration.py http://127.0.0.1:8082/
php tests/contracts.php
```

Suite Python memakai standard library. JSON hasil run ditulis ke `tests/result.json`. Daftar hasil run yang menyertai paket tersedia di `docs/hasil_uji_http.json`.

## Batas verifikasi

MySQL 8.0/PHP 8.3 pada Docker disediakan sebagai konfigurasi, tetapi container Docker belum dibangun/dijalankan di lingkungan ini. XAMPP, phpMyAdmin dan Docker pada komputer pengguna belum diuji langsung. Uji tidak mencakup beban produksi, stress test, audit keamanan menyeluruh, maupun benturan transaksi paralel; proteksi transaksi/constraint telah diimplementasikan, sedangkan suite menguji alur berurutan dan permintaan kedaluwarsa. Penjadwal OS untuk `bin/pengingat.php` perlu dikonfigurasi pada mesin pengguna; jalur pengingat berbasis permintaan aplikasi telah diuji.

## Daftar pemeriksaan HTTP

| No. | Pemeriksaan | Hasil |
| --- | --- | --- |
| 1 | Anonim diarahkan ke login | Lulus |
| 2 | Tidak ada registrasi | Lulus |
| 3 | POST tanpa CSRF ditolak | Lulus |
| 4 | Injeksi login tidak masuk | Lulus |
| 5 | Login mahasiswa dari database | Lulus |
| 6 | Login rekruter dari database | Lulus |
| 7 | Login admin dari database | Lulus |
| 8 | Mahasiswa halaman dashboard | Lulus |
| 9 | Mahasiswa halaman lowongan | Lulus |
| 10 | Mahasiswa halaman lamaran | Lulus |
| 11 | Mahasiswa halaman profil | Lulus |
| 12 | Mahasiswa halaman chat | Lulus |
| 13 | Mahasiswa halaman kalender | Lulus |
| 14 | Mahasiswa halaman notifikasi | Lulus |
| 15 | Rekruter halaman dashboard | Lulus |
| 16 | Rekruter halaman lowongan | Lulus |
| 17 | Rekruter halaman pendaftar | Lulus |
| 18 | Rekruter halaman profil | Lulus |
| 19 | Rekruter halaman chat | Lulus |
| 20 | Rekruter halaman kalender | Lulus |
| 21 | Rekruter halaman notifikasi | Lulus |
| 22 | Rekruter halaman rekapitulasi | Lulus |
| 23 | Admin halaman dashboard | Lulus |
| 24 | Admin halaman perusahaan | Lulus |
| 25 | Admin halaman profil | Lulus |
| 26 | Admin halaman notifikasi | Lulus |
| 27 | Admin halaman rekapitulasi | Lulus |
| 28 | Mahasiswa dilarang perusahaan | Lulus |
| 29 | Mahasiswa dilarang pendaftar | Lulus |
| 30 | Mahasiswa dilarang lowongan/form | Lulus |
| 31 | Mahasiswa dilarang rekapitulasi | Lulus |
| 32 | Mahasiswa tidak melihat lamaran orang lain | Lulus |
| 33 | Rekruter tidak melihat kandidat perusahaan lain | Lulus |
| 34 | Dokumen mahasiswa lain ditolak | Lulus |
| 35 | Dokumen kandidat perusahaan lain ditolak | Lulus |
| 36 | Dokumen pribadi diunduh sebagai PDF | Lulus |
| 37 | Pencarian tanpa peka kapital | Lulus |
| 38 | Pencarian kosong menampilkan pesan | Lulus |
| 39 | Kata kunci kosong menampilkan seluruh lowongan | Lulus |
| 40 | Semua filter bekerja bersamaan | Lulus |
| 41 | Draf perusahaan belum lolos tidak tampil | Lulus |
| 42 | Detail memuat persyaratan dan roadmap | Lulus |
| 43 | Form pendaftaran sesuai lowongan | Lulus |
| 44 | Berkas wajib divalidasi | Lulus |
| 45 | Chat bukan milik mahasiswa ditolak | Lulus |
| 46 | Chat sebelum lolos administrasi ditolak | Lulus |
| 47 | Unggahan non-PDF ditolak | Lulus |
| 48 | PDF palsu ditolak dari MIME | Lulus |
| 49 | PDF melebihi 5 MB ditolak | Lulus |
| 50 | Unggah PDF dan simpan draf | Lulus |
| 51 | Lamaran terhubung dan terkirim | Lulus |
| 52 | Lamaran memiliki identitas tersimpan | Lulus |
| 53 | Pendaftaran ganda diblokir | Lulus |
| 54 | Pendaftar muncul pada rekruter | Lulus |
| 55 | Rekruter membuka profil dan berkas | Lulus |
| 56 | Chat terkunci sebelum administrasi | Lulus |
| 57 | Lolos administrasi memindahkan tahap | Lulus |
| 58 | Chat terbuka setelah administrasi | Lulus |
| 59 | Pesan mahasiswa tersimpan aman | Lulus |
| 60 | Pesan terlihat pada rekruter | Lulus |
| 61 | Balasan rekruter tersimpan | Lulus |
| 62 | Balasan terlihat pada mahasiswa | Lulus |
| 63 | Penilaian tahap kedaluwarsa ditolak | Lulus |
| 64 | Jadwal masa lalu ditolak | Lulus |
| 65 | Jadwal disimpan rekruter | Lulus |
| 66 | Jadwal terhubung ke kalender mahasiswa | Lulus |
| 67 | Notifikasi pembaruan jadwal terkirim | Lulus |
| 68 | Tandai semua notifikasi dibaca | Lulus |
| 69 | Jumlah notifikasi belum dibaca menjadi nol | Lulus |
| 70 | Pengingat pribadi tersimpan | Lulus |
| 71 | Pengingat jatuh tempo menjadi notifikasi | Lulus |
| 72 | Pengingat pengguna lain tidak dapat diubah | Lulus |
| 73 | Pengingat dapat diubah | Lulus |
| 74 | Hapus pengingat memerlukan konfirmasi | Lulus |
| 75 | Pengingat dihapus setelah konfirmasi | Lulus |
| 76 | Lolos tes memindahkan ke wawancara | Lulus |
| 77 | Tahap terakhir menjadi Diterima | Lulus |
| 78 | Hasil akhir tersinkron mahasiswa | Lulus |
| 79 | Status final tidak ditimpa | Lulus |
| 80 | Form lowongan rekruter | Lulus |
| 81 | Validasi kolom wajib lowongan | Lulus |
| 82 | Periksa formulir tidak mempublikasikan | Lulus |
| 83 | Pembatalan menampilkan konfirmasi | Lulus |
| 84 | Kembali mengedit mempertahankan isian | Lulus |
| 85 | Konfirmasi pembatalan membuang formulir | Lulus |
| 86 | Buat lowongan dan roadmap terintegrasi | Lulus |
| 87 | Lowongan baru langsung muncul pada mahasiswa | Lulus |
| 88 | Edit lowongan perusahaan lain ditolak | Lulus |
| 89 | Edit mengisi field dari database | Lulus |
| 90 | Edit lowongan tersimpan | Lulus |
| 91 | Tutup lowongan menampilkan konfirmasi | Lulus |
| 92 | Tutup lowongan tersimpan | Lulus |
| 93 | Lowongan tutup tidak dapat dilamar | Lulus |
| 94 | Hapus lowongan hanya daftar aktif | Lulus |
| 95 | Perusahaan belum terverifikasi tidak dapat publikasi | Lulus |
| 96 | Perusahaan belum terverifikasi dapat menyimpan draf | Lulus |
| 97 | Detail perusahaan admin | Lulus |
| 98 | Persetujuan tanpa pemeriksaan ditolak | Lulus |
| 99 | Pemeriksaan dokumen perusahaan | Lulus |
| 100 | Verifikasi kelayakan perusahaan | Lulus |
| 101 | Persetujuan perusahaan tersimpan | Lulus |
| 102 | Keputusan admin diterima rekruter | Lulus |
| 103 | Rekruter disetujui dapat mempublikasikan | Lulus |
| 104 | Rekap admin memuat hasil terhubung | Lulus |
| 105 | Ekspor CSV benar | Lulus |
| 106 | CSV rekruter hanya perusahaannya | Lulus |
| 107 | Tolak perusahaan dengan alasan | Lulus |
| 108 | Hapus perusahaan menampilkan konfirmasi | Lulus |
| 109 | Penghapusan perusahaan terintegrasi | Lulus |
| 110 | Sesi rekruter perusahaan dihapus dicabut | Lulus |
| 111 | Lowongan perusahaan dihapus tidak tampil | Lulus |
| 112 | Logout harus menggunakan POST | Lulus |
| 113 | Logout mengakhiri sesi | Lulus |
| 114 | Sesi lama tidak bisa membuka halaman privat | Lulus |
| 115 | Profil mahasiswa tersimpan | Lulus |
| 116 | Unggah legalitas memicu verifikasi ulang | Lulus |
| 117 | Admin menerima notifikasi legalitas | Lulus |
