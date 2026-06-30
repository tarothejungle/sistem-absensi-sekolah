# Sistem Absensi Guru Berbasis Web - Laravel

Source code MVP full web untuk sistem absensi guru sekolah.

## Fitur
- Login menggunakan NIP dan password
- Role: guru, bendahara, kepala_sekolah, super_admin
- Absensi check-in dan check-out real-time
- Validasi GPS geofencing sekolah
- Capture wajah via webcam sebagai bukti audit biometrik
- Pengajuan izin, sakit, cuti, tugas luar
- Approval digital kepala sekolah
- Dashboard rekap harian/bulanan
- Grafik menggunakan Chart.js CDN
- Export laporan CSV tanpa package tambahan

## Tech Stack
- Laravel 12 / Laravel 11 compatible
- PHP 8.2+
- MySQL/MariaDB
- Bootstrap 5 CDN
- Chart.js CDN

## Cara Install
1. Buat project Laravel baru:
   composer create-project laravel/laravel absensi-guru
2. Copy folder `app`, `database`, `resources`, `routes`, `public` dari source ini ke project Laravel.
3. Atur `.env` database.
4. Jalankan:
   php artisan migrate:fresh --seed
   php artisan storage:link
   php artisan serve
5. Login default:
   - Super Admin: NIP ADM001, password password
   - Kepala Sekolah: NIP KS001, password password
   - Guru: NIP GURU001, password password

## Catatan Face Recognition
Versi hemat biaya ini menggunakan webcam capture sebagai bukti wajah dan fungsi verifikasi placeholder di `app/Services/FaceVerificationService.php`. Untuk face recognition sungguhan, hubungkan service tersebut ke API Python/FastAPI + OpenCV/face_recognition, atau face-api.js dengan model lokal.
