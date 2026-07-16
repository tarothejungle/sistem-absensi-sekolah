# Sistem Absensi Sekolah

Sistem Absensi Sekolah adalah aplikasi web berbasis Laravel untuk mengelola kehadiran guru/karyawan sekolah, bukti foto absensi, izin/cuti, jadwal piket, hari libur, laporan, dan penggajian dalam satu dashboard operasional.

Project ini disiapkan untuk kebutuhan internal sekolah, dengan fokus pada alur kerja harian yang mudah dipantau oleh guru/karyawan, kepala sekolah, bendahara, dan super admin.

## Ringkasan Fitur

- Login berbasis username/NIP dan password.
- Hak akses role: guru, bendahara, kepala sekolah, dan super admin.
- Dashboard ringkasan kehadiran harian.
- Check-in dan check-out berdasarkan sesi absensi.
- Validasi radius lokasi sekolah menggunakan geofence.
- Face capture sebagai bukti foto check-in dan check-out.
- Riwayat absensi per pengguna.
- Pengajuan izin, sakit, cuti, dan tugas luar.
- Approval izin/cuti oleh role yang berwenang.
- Penunjukan guru infal/pengganti untuk pengajuan tertentu.
- Laporan rekap absensi dengan filter status dan periode.
- Preview dan export laporan ke Excel/PDF.
- Laporan rekap guru infal.
- Pengaturan data pengguna dan data guru.
- Pengaturan lokasi sekolah.
- Pengaturan sesi dan jam absensi.
- Pengaturan hari libur.
- Pengaturan hari piket per tanggal dengan toggle aktif/nonaktif.
- Auto status alfa/tidak hadir setelah batas check-in berakhir.
- Pengajuan izin sementara (meninggalkan lokasi sementara) dengan rentang waktu.
- Panel "Guru Belum Absen Hari Ini" di dashboard untuk pemantauan cepat.
- Penggajian berbasis gaji pokok, potongan ketidakhadiran, potongan alfa, dan tambahan infal.
- Pengerasan keamanan: security headers (CSP, HSTS, dll.), CORS terbatas, lampiran izin/cuti privat, dan enkripsi sesi opsional.
- Tema tampilan light/dark.
- UI responsif untuk desktop dan mobile.

## Modul Utama

### Dashboard

Menampilkan ringkasan kehadiran hari ini, total guru/karyawan aktif, hadir lengkap, terlambat, tidak lengkap, dan belum absen. Perhitungan dashboard membedakan total guru aktif dari guru yang memang wajib absen pada tanggal berjalan.

### Absensi

Guru/karyawan dapat melakukan check-in dan check-out menggunakan kamera browser. Sistem menyimpan foto check-in dan check-out sebagai bukti audit, memvalidasi lokasi berdasarkan radius sekolah, serta mengikuti sesi absensi yang aktif.

### Approval Izin/Cuti

Role kepala sekolah dan super admin dapat meninjau dan memproses pengajuan izin/cuti. Role guru dan bendahara melihat menu sebagai Pengajuan Izin/Cuti.

### Setting Hari Libur

Super admin dapat menentukan tanggal libur aktif/nonaktif. Pada tanggal libur aktif, sistem tidak membuat alfa reguler kecuali guru/karyawan tersebut masuk jadwal piket aktif.

### Setting Hari Piket

Super admin dapat menentukan tanggal piket, memilih guru/karyawan yang bertugas, dan mengaktifkan atau menonaktifkan jadwal tersebut menggunakan toggle.

### Laporan Rekap Absensi

Menampilkan rekap absensi berdasarkan periode dan status, termasuk foto check-in dan check-out untuk membantu audit kehadiran.

### Penggajian

Sistem menghitung gaji berdasarkan gaji pokok, potongan izin/cuti/sakit tertentu, alfa/tidak hadir, serta tambahan untuk guru infal/pengganti. Potongan alfa dicatat sebagai potongan yang masuk ke kas sekolah.

## Teknologi

- Laravel 12
- PHP 8.2+
- MySQL/MariaDB
- Bootstrap 5
- Bootstrap Icons
- Chart.js
- Laravel DomPDF
- Laravel Excel

## Catatan Face Recognition

Versi saat ini menggunakan face capture/face verification sederhana sebagai bukti foto saat absensi. Untuk face recognition berbasis embedding wajah, integrasi yang disarankan adalah service Python lokal/self-hosted yang menerima foto dari Laravel, membuat embedding wajah, lalu mencocokkannya dengan data wajah guru/karyawan yang sudah didaftarkan.

## Instalasi Singkat

```bash
composer install
cp .env.example .env
php artisan key:generate
# sesuaikan koneksi database di .env
php artisan migrate --seed
php artisan serve
```

## Catatan Repository

Repository ini dipublikasikan sebagai referensi, namun **tidak sepenuhnya open source**. Sebagian berkas yang bersifat rahasia atau berisi konfigurasi/dokumentasi internal sengaja tidak disertakan. Untuk menjalankan di lingkungan produksi, sebagian penyesuaian perlu dieksplorasi dan dikonfigurasi sendiri.

Berkas yang **tidak** disertakan di repository publik:

- `.env`, `hosting.env`, dan berkas `*.env` lain — kredensial aplikasi, database, mail, dan layanan pihak ketiga. Gunakan `.env.example` sebagai acuan.
- `docs/SECURITY-HARDENING.md` — dokumentasi internal berisi checklist pengerasan keamanan dan langkah kesiapan produksi.
- Unggahan pengguna — foto profil (`profile_photo/`, `storage/profile_photos/`), foto bukti absensi (`storage/attendance_faces/`), dan lampiran izin/cuti (`leave_attachments/`, `storage/app/leave_attachments/`).
- Dump/berkas database (`*.sql`, `*.sqlite`, `*.db`) dan berkas runtime (`storage/logs/`, cache framework).
- Konfigurasi editor/tooling lokal (`.vscode/`, `.idea/`, dll.) serta dependency (`vendor/`, `node_modules/`, `public/build/`).
