# Sistem Absensi Sekolah

Aplikasi web Laravel untuk mengelola kehadiran guru dan tenaga kependidikan, izin/cuti, jadwal piket, laporan, serta penggajian dalam satu dashboard operasional sekolah.

## Status Implementasi

Implementasi aktual: Laravel 12, multi-sesi, FastAPI/InsightFace, role 5 tingkat, geofence + akurasi GPS, izin reguler/sementara, multi-infal, sinkronisasi status otomatis, laporan Excel/PDF, payroll, dan API AIO. Detail di bawah merefleksikan kode dan konfigurasi terbaru di working tree.

## Fitur Utama

- **Autentikasi dan hak akses**: login `NIP` + password, validasi `status = aktif`, reset password via email, Turnstile (jika dikonfigurasi), throttle login `5/menit`, idle timeout 10 menit, dan logout otomatis untuk akun nonaktif.
- **Absensi multi-sesi**: check-in/check-out per sesi yang ditugaskan ke guru, validasi window (mendukung lintas tengah malam), jadwal kerja mingguan, lock transaksi + cek duplikasi, dan constraint `teacher_id + tanggal + attendance_session_id`.
- **Verifikasi wajah 1:1 (FastAPI)**: capture di browser, embedding InsightFace `buffalo_l` 512 dimensi via `POST /api/v1/faces/enroll|verify`, threshold `0.45`, foto audit disimpan di disk privat `storage/app/attendance_faces`.
- **Enrollment wajah**: self-enrollment oleh pemilik akun melalui **Profil > Wajah Absensi** (maks 3 kandidat, pilih 1). Template terkunci setelah sukses; pendaftaran ulang butuh **Reset Data Wajah** oleh `super_admin` di **Data Guru > Edit**.
- **GPS dan geofence**: validasi `latitude/longitude/accuracy`, hitung jarak dengan `GeoFenceService`, bandingkan dengan `radius_meter` lokasi aktif, dan tolak akurasi buruk sebelum cek radius.
- **Izin dan cuti**: `sakit/izin/cuti/tugas_luar` + izin sementara (1 hari + jam mulai/selesai), validasi jam terhadap sesi aktif, cegah overlap izin sementara, dan grace sampai `jam_selesai + 30 menit` (dibatasi akhir sesi).
- **Alur infal**: pengajuan izin penuh dapat memilih beberapa guru pengganti aktif; approval utama oleh `kepala_sekolah`/`super_admin`; infal `approve/reject` sendiri; status agregat `disetujui` bila minimal 1 setuju, `ditolak` bila semua menolak.
- **Status kehadiran otomatis**: `hadir/terlambat/hadir_tidak_lengkap/izin/sakit/cuti/tugas_luar/alfa` via `DailyAttendanceStatusService`; mempertimbangkan akun aktif, jadwal hari, hari libur, piket, dan izin penuh.
- **Dashboard dan notifikasi**: ringkasan harian, daftar belum absen, dan notifikasi izin/infal + pengingat check-in/out; `notifications/read` via `POST` milik pemilik saja.
- **Laporan**: rekap absensi (filter tanggal/status, Excel/PDF) untuk `kepala_sekolah`/`super_admin`; laporan infal untuk `bendahara`/`kepala_sekolah`/`super_admin`.
- **Penggajian**: generate per bulan/tahun untuk semua personel absensi aktif, regenerasi periode sebagai `draft`, hitung `gaji_bersih = pokok - potongan + tambahan_infal`, potongan `Rp30.000` bila pokok `>=1.000.000` else `Rp20.000`, pembagian potongan ke infal disetujui, rekap + slip PDF.
- **Administrasi sekolah**: kelola pengguna, data guru/karyawan, lokasi sekolah, sesi absensi, hari libur, dan jadwal piket (khusus `super_admin`).
- **Operasional dan keamanan**: security headers, CORS terbatas, lampiran privat `no-store`, `SESSION_ENCRYPT`/`SESSION_SECURE_COOKIE`/`FORCE_HTTPS`, `TRUSTED_PROXIES` tanpa wildcard, dan pembersihan terjadwal.

## Hak Akses

| Role | Absensi | Ajukan izin | Approve izin | Respons infal | Payroll | Laporan absensi | Laporan infal | Admin sekolah |
|---|:---:|:---:|:---:|:---:|:---:|:---:|:---:|:---:|
| `guru` | Ya | Ya | - | Ya | - | - | - | - |
| `tenaga_kependidikan` | Ya | Ya | - | Ya | - | - | - | - |
| `bendahara` | Ya | Ya | - | Ya | Ya | - | Ya | - |
| `kepala_sekolah` | Ya | - | Ya | - | - | Ya | Ya | - |
| `super_admin` | - | - | Ya | - | Ya | Ya | Ya | Ya |

Guru terhubung ke banyak sesi via pivot `attendance_session_teacher` (`is_primary` menandai sesi utama untuk API AIO).

## Arsitektur

- **Laravel (PHP 8.2+)**: auth, role, jadwal, GPS, approval, payroll, laporan, storage privat.
- **Browser**: akses kamera + GPS, pilih kandidat foto, kirim 1 frame ke Laravel (tidak ada deteksi wajah di browser; semua deteksi/match di server).
- **FastAPI (`face-api`)**: `GET /health`, `POST /api/v1/faces/enroll`, `POST /api/v1/faces/verify`; validasi `X-API-Key`, batas body ~6 MB (header `Content-Length`), image 32–4.000.000 char / binary max 2 MiB, tepat 1 wajah min 5% frame, embedding 512.
- **MySQL/MariaDB**: source of truth migrasi; SQLite tidak valid untuk suite ini.
- **Docker Compose**: service `face-api` build dari `face-service/`, bind `127.0.0.1:8001 -> 8000`, volume `face-models`, `no-new-privileges`, restart `unless-stopped`.

## Teknologi

- Laravel 12, PHP 8.2+ (produksi panduan ini memakai PHP 8.3), MySQL/MariaDB
- Bootstrap 5 (CDN), Vite 8, Tailwind CSS 4
- FastAPI 0.116.1, InsightFace 0.7.3, ONNX Runtime 1.22.1, OpenCV headless, `buffalo_l` (`insightface-buffalo_l-v1`)
- Laravel DomPDF 3.1, Laravel Excel 3.1 (PHPSpreadsheet)
- Docker Engine + Compose v2

## Prasyarat

- PHP 8.2+ dan Composer
- MySQL/MariaDB
- Node.js `^20.19.0 || >=22.12.0` dan npm (Vite 8 + Laravel Vite Plugin)
- Docker Desktop/Engine dengan Linux containers dan `docker compose version` (Compose v2)
- Browser dengan izin kamera + geolokasi; HTTPS wajib di produksi/non-loopback

## Instalasi Lokal

1. Pasang dependency:

```bash
composer install
npm ci
```

2. Buat konfigurasi:

```bash
cp .env.example .env
php artisan key:generate
```

Isi `DB_*` dan buat `FACE_SERVICE_API_KEY` acak; nilai sama dipakai Laravel dan container FastAPI.

```bash
openssl rand -hex 32
```

3. Siapkan database dan akun pertama:

```bash
php artisan migrate
php artisan admin:create-super
```

`admin:create-super` interaktif (NIP, nama, email, password) dan membuat row `Teacher`. Tidak ada akun bawaan bila langkah ini tidak dijalankan.

> Jangan jalankan `php artisan db:seed` di produksi. Seeder membuat akun demo `ADM001` / `KS001` / `GURU001` dengan password `password` untuk pengembangan saja (`database/seeders/DatabaseSeeder.php:16-69`).

4. Build aset dan jalankan service:

```bash
npm run build
docker compose up --build -d
php artisan serve
```

Untuk pengembangan frontend: `npm run dev`.

5. Periksa readiness:

```bash
docker compose ps
docker compose logs -f face-api
curl http://127.0.0.1:8001/health
```

Sehat: `{"status":"ok","model_loaded":true,"model_version":"insightface-buffalo_l-v1"}`. Model pertama diunduh ke volume `face-models`.

## Konfigurasi Penting (`.env`)

| Key | Contoh | Keterangan |
|---|---|---|
| `APP_ENV` / `APP_DEBUG` / `APP_URL` | `production` / `false` / `https://example.sch.id` | Produksi wajib `APP_DEBUG=false` |
| `SESSION_IDLE_TIMEOUT` | `10` | Menit idle sebelum logout (`EnsureSessionIsActive`) |
| `SESSION_LIFETIME` | `15` | Lifetime cookie sesi |
| `SESSION_SECURE_COOKIE` | `true` | Produksi HTTPS |
| `SESSION_ENCRYPT` | `true` | Enkripsi file sesi |
| `SESSION_DOMAIN` | `example.sch.id` | Domain polos tanpa skema |
| `FORCE_HTTPS` | `true` | Paksa URL `https` |
| `TRUSTED_PROXIES` | `10.0.0.0/8,172.16.0.0/12` | CIDR proxy; jangan `*` |
| `AIO_INTEGRATION_API_KEY` | `random-secret` | Kosong = API AIO nonaktif (503) |
| `FACE_SERVICE_URL` | `http://127.0.0.1:8001` | URL FastAPI dari host Laravel |
| `FACE_SERVICE_API_KEY` | `random-secret` | Header `X-API-Key` ke FastAPI |
| `FACE_SERVICE_CONNECT_TIMEOUT` | `3` | Detik |
| `FACE_SERVICE_TIMEOUT` | `45` | Detik |
| `ATTENDANCE_MAX_GPS_ACCURACY_METERS` | `100` | Default akurasi GPS (meter) |
| `ATTENDANCE_PHOTO_RETENTION_DAYS` | `30` | Retensi foto audit (hari) |

Env salah `ATTENDANCE_FACE_PHOTO_RETENTION_DAYS` tidak dibaca; nama benar `ATTENDANCE_PHOTO_RETENTION_DAYS` (`config/attendance.php:6`, `.env.example:69`).

## Verifikasi Wajah

### Enrollment

1. Login sebagai `guru`/`tenaga_kependidikan`/`bendahara`/`kepala_sekolah` yang memiliki profil `Teacher`.
2. Buka **Profil > Wajah Absensi**, izinkan kamera, tunggu status FastAPI siap.
3. Ambil maks 3 kandidat, pilih terbaik, klik **Daftarkan Wajah Terpilih**.
4. Laravel validasi Data URL + MIME aktual (`getimagesizefromstring`) max 2 MiB, lalu `POST /api/v1/faces/enroll` ke FastAPI.
5. FastAPI wajib 1 wajah min 5% frame, lalu kembalikan embedding 512; Laravel simpan `teacher_face_enrollments` dan kunci template.

Admin: **Data Guru > Edit** hanya melihat status dan **Reset Data Wajah**; setelah reset pemilik akun daftar ulang via Profil.

### Absensi

1. Buka **Absensi**, izinkan kamera + GPS.
2. Tekan **Check-in**/**Check-out** (maks 3 percobaan, 1 frame per percobaan).
3. Laravel validasi role aktif, sesi aktif yang ditugaskan, window, GPS, duplikasi, enrollment.
4. `POST /api/v1/faces/verify` dengan `image + embedding 512 + model_version`; FastAPI hitung `distance = 1 - cosine_similarity`, `matched = distance <= 0.45`.
5. Laravel simpan absensi + foto privat + `distance` hanya bila `matched: true`; bila gagal hapus foto yang baru dibuat.

Foto `GET` hanya untuk pemilik, `kepala_sekolah`, `super_admin` dengan header `private, no-store`.

## Batasan Biometrik

Belum ada liveness detection. Foto cetak/layar replay dapat lolos. Foto audit dan `distance` tersimpan untuk audit manual; jangan jadikan face match satu-satunya kontrol untuk akses berisiko tinggi.

## Integrasi API AIO

```
GET /api/v1/integrations/aio/attendance-report?month=8&year=2026
Header: X-API-Key: <AIO_INTEGRATION_API_KEY>
Throttle: 60/menit
```

- `AIO_INTEGRATION_API_KEY` kosong → `503`.
- Key salah → `401` (compare constant-time).
- Query `month`/`year` opsional, default bulan berjalan.
- Respons: kalender bulanan, hari libur, semua PTK aktif, sesi utama (`is_primary`), absensi per hari, tahun ajaran, metadata + warning PTK tanpa sesi utama.
- Pagination tidak ada; konsumsi dengan rentang bulanan.

## Penjadwalan

Aktifkan scheduler Laravel:

```cron
* * * * * cd /path/to/project && php artisan schedule:run >> /dev/null 2>&1
```

| Jadwal | Command | Fungsi |
|---|---|---|
| Tiap 15 menit | `attendance:sync-status` | Sinkron alfa/izin/hadir tidak lengkap |
| `23:55` harian | `attendance:sync-status` | Sapu akhir hari |
| `02:15` harian | `attendance:prune-photos` | Hapus file foto kedaluwarsa, kosongkan kolom path (record tetap) |
| Tiap jam | `login-activities:prune` | Hapus aktivitas login `>=24 jam` |
| `02:30` harian | `auth:clear-resets` | Bersihkan token reset |

Verifikasi:

```bash
php artisan schedule:list
```

Retensi foto membaca `ATTENDANCE_PHOTO_RETENTION_DAYS` (default 30). Command benar `attendance:prune-photos`, bukan `attendance:prune-face-photos`.

## Pengujian

### Laravel

Suite memakai `DatabaseTransactions` dan butuh MySQL `absensi_guru_testing` yang sudah termigrasi. Kredensial `DB_HOST/PORT/USERNAME/PASSWORD` diwarisi dari `.env` (hanya `DB_CONNECTION`/`DB_DATABASE` di `phpunit.xml`).

Setup DB testing disposable (jangan arahkan ke DB produksi):

```bash
mysql -u <USER_TEST> -p -e "CREATE DATABASE absensi_guru_testing CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
APP_ENV=testing DB_DATABASE=absensi_guru_testing php artisan migrate:fresh
php artisan test
# atau tersegmentasi
php artisan test --testsuite=Unit
php artisan test --testsuite=Feature
php artisan test --filter=test_check_in_success_stores_attendance
```

### FastAPI

Image produksi tidak memuat dev deps. Jalankan dari venv Python terpisah (disarankan Python 3.11 sesuai `face-service/Dockerfile`):

```bash
cd face-service
python -m pip install -r requirements-dev.txt
python -m pytest tests
```

`httpx` + `pytest` sudah termasuk; walau test memakai `FakeEngine`, import tetap butuh deps service.

### Build Frontend

```bash
npm ci
npm run build
```

`package.json` hanya `dev`/`build`; `npm run build` adalah cek kompilasi Vite, bukan test runner. `npm ci` lebih reproduktif daripada `npm install`.

## Deployment (Generik)

Ganti placeholder `<DOMAIN>`, `<SITE_USER>`, `<DB_NAME>`, `<DB_USER>`, `<TAG>` dengan nilai server. Jangan commit kredensial.

```bash
# 1. Release dari tag
git clone <GIT_URL> /home/<SITE_USER>/htdocs/<DOMAIN>
cd /home/<SITE_USER>/htdocs/<DOMAIN>
git checkout <TAG>
cp .env.example .env
chmod 600 .env
php artisan key:generate
openssl rand -hex 32  # isi FACE_SERVICE_API_KEY

# 2. Env produksi (contoh)
# APP_ENV=production, APP_DEBUG=false, APP_URL=https://<DOMAIN>
# SESSION_SECURE_COOKIE=true, SESSION_ENCRYPT=true, FORCE_HTTPS=true
# TRUSTED_PROXIES=<CIDR_PROXY>, CORS_ALLOWED_ORIGINS=https://<DOMAIN>
# DB_*, FACE_SERVICE_URL=http://127.0.0.1:8001, ATTENDANCE_PHOTO_RETENTION_DAYS=30

# 3. Dependency & build
composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
npm ci
npm run build
rm -rf node_modules
chmod -R ug+rwX storage bootstrap/cache

# 4. Migrasi (jangan db:seed di produksi)
php artisan migrate --force
php artisan admin:create-super
php artisan optimize

# 5. FastAPI
docker compose build face-api
docker compose up -d face-api
curl --fail http://127.0.0.1:8001/health
ss -lntp | grep 8001  # harus 127.0.0.1:8001

# 6. Scheduler
# Cron tiap menit: php artisan schedule:run
php artisan schedule:list

# 7. Verifikasi
php artisan about --only=environment
php artisan migrate:status
curl --fail https://<DOMAIN>
```

CloudPanel/Nginx: document root ke `public/`, aktifkan SSL + redirect HTTP→HTTPS, jangan cache halaman login, replikasi `public/.htaccess` untuk Nginx bila perlu (`deny` untuk `leave_attachments`/`attendance_faces` legacy).

## Keamanan Produksi (Checklist)

Wajib di `.env` produksi: `APP_DEBUG=false`, `APP_URL=https`, `SESSION_SECURE_COOKIE=true`, `SESSION_ENCRYPT=true`, `FORCE_HTTPS=true`, `SECURITY_CSP_ENABLED=true`, `TRUSTED_PROXIES` berisi CIDR spesifik. `AppServiceProvider` fail-fast bila kombinasi produksi tidak aman.

Operasional: document root `public/`, `storage/` + `bootstrap/cache/` writable, `chmod 600 .env`, rotasi `APP_KEY` + `MAIL_PASSWORD` + `DB_PASSWORD` (jangan pakai `root` tanpa password), backup terenkripsi `storage/app` (berisi foto/lampiran privat), pantau `composer audit`/`npm audit`.

Lampiran/foto disajikan via route berotorisasi dengan `Cache-Control: private, no-store`. FastAPI hanya di `127.0.0.1`, `X-API-Key` constant-time, OpenAPI nonaktif default (`ENABLE_DOCS=false`).

## Backup dan Data Sensitif

Backup harian: database + `storage/app`. Jangan simpan dump di web root; simpan terenkripsi di lokasi terpisah. Arsip `*.zip`/`*.sql` di-ignore Git; jangan buat arsip mentah working tree. Template biometrik disimpan sebagai JSON cast di `teacher_face_enrollments`; lindungi via akses DB + enkripsi backup/host.

## Lisensi

Dirilis di bawah [GNU General Public License v3.0-or-later](LICENSE) (`composer.json: GPL-3.0-or-later`). Karya turunan tetap dirilis di bawah lisensi yang sama.
