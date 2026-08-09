# Sistem Absensi Sekolah

Aplikasi web berbasis Laravel untuk mengelola kehadiran guru dan tenaga kependidikan, izin/cuti, jadwal piket, laporan, serta penggajian dalam satu dashboard operasional sekolah.

Versi ini menggunakan **face recognition 1:1** via FastAPI/InsightFace untuk pencocokan wajah terdaftar, membutuhkan akses Docker untuk menjalankan service Python.

---

## Fitur Utama

### Autentikasi & Otorisasi

- Login via `NIP` + password (bukan email)
- 5 role: **Super Admin**, **Guru**, **Bendahara**, **Kepala Sekolah**, **Tenaga Kependidikan**
- Cloudflare Turnstile CAPTCHA pada halaman login
- Kebijakan password kuat (8+ karakter, campuran huruf besar/kecil, angka, simbol) via `PasswordPolicy`
- Throttle login 5 percobaan/menit
- Idle timeout 10 menit, session lifetime 15 menit
- Validasi `status = aktif` setiap request (`EnsureUserIsActive`)
- Logout otomatis untuk akun nonaktif
- Reset password via email
- Logging aktivitas login (IP, user agent, timestamp)

### Absensi

- Check-in / check-out per sesi absensi yang ditugaskan ke guru
- Validasi lokasi GPS (geofence) menggunakan `GeoFenceService` dengan rumus Haversine
- Konfigurasi radius dan akurasi GPS per lokasi sekolah
- Multiple sesi absensi (pagi, siang, dll.) dengan pivot `attendance_session_teacher` (`is_primary`)
- Batas waktu check-in/check-out per sesi (mendukung lintas tengah malam)
- Lock transaksi + constraint `teacher_id + tanggal + attendance_session_id` cegah duplikasi
- Status otomatis: Hadir, Terlambat, Hadir Tidak Lengkap, Alfa
- Retensi foto otomatis (dihapus scheduler setelah X hari)

### Verifikasi Wajah 1:1 (FastAPI)

- Capture di browser, embedding InsightFace `buffalo_l` 512 dimensi via `POST /api/v1/faces/enroll|verify`
- Threshold kecocokan `0.45` (distance = 1 - cosine_similarity)
- Foto audit disimpan di disk privat `storage/app/attendance_faces`
- Maks 3 percobaan per check-in/out, 1 frame per percobaan
- API key auth via header `X-API-Key`, constant-time comparison

### Enrollment Wajah

- Self-enrollment oleh pemilik akun melalui **Profil > Wajah Absensi** (maks 3 kandidat, pilih 1)
- Laravel validasi Data URL + MIME aktual (`getimagesizefromstring`) max 2 MiB
- FastAPI wajib 1 wajah min 5% frame, kembalikan embedding 512
- Template terkunci setelah sukses; pendaftaran ulang butuh **Reset Data Wajah** oleh `super_admin` di **Data Guru > Edit**

### Izin, Cuti & Guru Pengganti (Infal)

- 5 jenis pengajuan: Sakit, Izin, Cuti, Tugas Luar, Izin Sementara
- Izin sementara: 1 hari + jam mulai/selesai, validasi jam terhadap sesi aktif, cegah overlap
- Grace period sampai `jam_selesai + 30 menit` (dibatasi akhir sesi)
- Alur approval: Pengaju → Kepala Sekolah/Super Admin
- Sistem guru pengganti (infal) dengan multi-teacher nomination dan approval terpisah
- Status agregat: `disetujui` bila minimal 1 setuju, `ditolak` bila semua menolak
- Lampiran file (JPG, PNG, PDF, maks 2MB) disimpan di disk privat
- Notifikasi in-app untuk setiap perubahan status

### Jadwal Kerja & Piket

- Jadwal kerja per guru berdasarkan hari dan sesi (`WorkSchedule`)
- Jadwal piket (duty schedule) dengan relasi many-to-many ke teacher
- Hari libur nasional/sekolah (dikelola admin via `HolidaySetting`)

### Penggajian

- Generate per bulan/tahun untuk semua personel absensi aktif
- Gaji bersih = pokok - potongan + tambahan_infal
- Potongan: Rp30.000 bila pokok >= Rp1.000.000, else Rp20.000
- Pembagian potongan ke infal yang disetujui
- Regenerasi periode sebagai `draft`
- Cetak slip gaji (PDF) dan rekap penggajian bulanan (PDF landscape)

### Laporan & Export

- Rekap kehadiran dengan filter tanggal, status, dan nama (Excel/PDF)
- Rekap guru pengganti (infal) untuk bendahara/kepala_sekolah/super_admin
- Chart distribusi status kehadiran (Chart.js)
- Dashboard ringkasan harian + daftar belum absen
- Export data akun guru (Excel, PDF, QR Code)

### Pengaturan Admin

- Kelola data guru/karyawan (CRUD + bulk delete)
- Kelola akun Super Admin
- Setting lokasi sekolah (GPS + radius + peta Leaflet)
- Konfigurasi sesi absensi
- Kelola hari libur dan jadwal piket

### Keamanan Operasional

- Security headers middleware (`SecurityHeaders`) dengan CSP nonce
- CORS terbatas (`CORS_ALLOWED_ORIGINS`)
- Lampiran privat `Cache-Control: private, no-store`
- `SESSION_ENCRYPT`, `SESSION_SECURE_COOKIE`, `FORCE_HTTPS`
- `TRUSTED_PROXIES` tanpa wildcard
- `AppServiceProvider` fail-fast bila kombinasi produksi tidak aman
- Pembersihan terjadwal (foto, login log, token reset)

---

## Tech Stack

| Layer | Teknologi |
|---|---|
| Framework | Laravel 12 |
| PHP | 8.2+ (produksi: 8.3) |
| Database | MySQL / MariaDB |
| Frontend | Blade, Bootstrap 5 (CDN), Tailwind CSS 4 |
| JavaScript | Vite 8, Chart.js, Axios |
| PDF | barryvdh/laravel-dompdf 3.1 |
| Excel | maatwebsite/excel 3.1 (PHPSpreadsheet) |
| Auth | Laravel Sanctum 4.0 |
| CAPTCHA | Cloudflare Turnstile |
| Peta | Leaflet.js (OpenStreetMap) |
| Face Recognition | FastAPI 0.116.1, InsightFace 0.7.3, ONNX Runtime 1.22.1, OpenCV headless |
| Container | Docker Engine + Compose v2 |

---

## Arsitektur

```
app/
├── Console/
│   └── Commands/          # 4 commands: CreateSuperAdmin, PruneAttendancePhotos,
│                          #   PruneLoginActivities, SyncAttendanceStatus
├── Exceptions/            # FaceVerificationException, Handler
├── Exports/               # AttendanceReportExport, InfalReportExport, TeacherAccountsExport
├── Http/
│   ├── Controllers/       # 16 controllers (web + API)
│   │   └── Api/           # AioAttendanceReportController
│   └── Middleware/        # 14 middleware (Role, Security, Session, CORS, dll)
├── Models/                # 16 Eloquent models
├── Providers/             # AppServiceProvider, Auth, Broadcast, Event, Route
├── Services/              # 8 service classes (business logic)
│   ├── AttendanceService          (516 baris)
│   ├── DailyAttendanceStatusService (485 baris)
│   ├── FaceVerificationService    (294 baris)
│   ├── AttendanceWindowService
│   ├── GeoFenceService
│   ├── TemporaryLeaveAttendancePolicy
│   ├── AppNotificationService
│   └── UserProfileCompletenessService
└── Support/               # ConfigSanitizer, PasswordPolicy, ExcelCell, InfalReportRow

face-service/              # FastAPI Python (Dockerized)
├── app/
│   ├── main.py            # FastAPI app: /health, /api/v1/faces/enroll, /api/v1/faces/verify
│   ├── face_engine.py     # InsightFace buffalo_l wrapper
│   ├── config.py          # Pydantic settings
│   └── schemas.py         # Request/response schemas
├── tests/test_api.py      # pytest suite
├── Dockerfile
└── requirements.txt

resources/
├── css/                   # Tailwind + custom CSS (absensi, login)
├── js/                    # app.js, attendance.js, face-enrollment.js
└── views/                 # Blade templates (11 modul)
    ├── admin/             # teachers, users, location, sessions, holidays, duty-schedules
    ├── attendance/        # index.blade.php
    ├── auth/              # login, forgot-password, reset-password
    ├── dashboard/         # index + partials
    ├── errors/            # 419
    ├── infal_report/      # index + pdf
    ├── layouts/           # app.blade.php
    ├── leave/             # index, create, edit
    ├── payroll/           # index, show, print, slip, settings
    ├── profile/           # edit, password, partials
    └── report/            # index, export_excel, export_pdf, preview_pdf

routes/
├── web.php                # ~190 routes
├── api.php                # AIO integration endpoint + scheduler
├── channels.php
└── console.php

tests/
├── Feature/               # 23 test files
└── Unit/                  # 3 test files (SecurityHeaders, ConfigSanitizer, Example)

docs/
├── SECURITY-HARDENING.md
├── face-recognition-guide.md
├── fastapi-face-recognition-design.md
├── vps-cloudpanel-deployment.md
├── plan.md
└── hasil-eksekusi.md
```

### Model & Relasi Utama

| Model | Deskripsi | Relasi |
|---|---|---|
| `User` | Akun login (NIP, role, status) | hasOne Teacher |
| `Teacher` | Data guru/karyawan | belongsTo User, hasMany Attendance, belongsToMany AttendanceSession |
| `Attendance` | Record kehadiran | belongsTo Teacher, belongsTo AttendanceSession |
| `AttendanceSession` | Sesi absensi (pagi/siang) | belongsToMany Teacher (pivot `attendance_session_teacher`) |
| `LeaveRequest` | Pengajuan izin/cuti | belongsTo Teacher, belongsToMany Teacher (infal) |
| `TeacherFaceEnrollment` | Embedding wajah 512-dim | belongsTo Teacher |
| `WorkSchedule` | Jadwal kerja | belongsTo Teacher, belongsTo AttendanceSession |
| `DutySchedule` | Jadwal piket | belongsToMany Teacher |
| `HolidaySetting` | Hari libur | — |
| `SchoolLocation` | Lokasi sekolah (GPS + radius) | — |
| `PayrollPeriod` | Periode penggajian | hasMany PayrollItem |
| `PayrollItem` | Detail gaji per guru | belongsTo Teacher, belongsTo PayrollPeriod, hasMany PayrollItemDetail |
| `PayrollItemDetail` | Line-item payroll | belongsTo PayrollItem |
| `TeacherSalary` | Gaji pokok per guru | belongsTo Teacher |
| `LoginActivity` | Log aktivitas login | belongsTo User |
| `AppNotification` | Notifikasi in-app | belongsTo User |

---

## Role & Hak Akses

| Menu / Fitur | Super Admin | Guru | Bendahara | Kepala Sekolah | Tenaga Kependidikan |
|---|:---:|:---:|:---:|:---:|:---:|
| Dashboard | ✓ | ✓ | ✓ | ✓ | ✓ |
| Absensi | — | ✓ | ✓ | — | ✓ |
| Izin & Cuti | — | ✓ | ✓ | ✓ | ✓ |
| Approve Izin | ✓ | — | — | ✓ | — |
| Respons Infal | — | ✓ | ✓ | — | ✓ |
| Laporan Absensi | ✓ | — | — | ✓ | — |
| Laporan Infal | ✓ | — | ✓ | ✓ | — |
| Penggajian | ✓ | — | ✓ | — | — |
| Data Guru | ✓ | — | — | — | — |
| Data Admin | ✓ | — | — | — | — |
| Lokasi Sekolah | ✓ | — | — | — | — |
| Sesi Absensi | ✓ | — | — | — | — |
| Hari Libur | ✓ | — | — | — | — |
| Jadwal Kerja | ✓ | — | — | — | — |
| Jadwal Piket | ✓ | — | — | — | — |
| Profil | ✓ | ✓ | ✓ | ✓ | ✓ |

---

## Instalasi

### Prasyarat

- PHP 8.2+ dengan extension: ctype, curl, dom, fileinfo, filter, mbstring, openssl, pdo_mysql, session, tokenizer, xml
- Composer 2
- MySQL / MariaDB
- Node.js `^20.19.0 || >=22.12.0` dan npm (Vite 8 + Laravel Vite Plugin)
- Docker Desktop/Engine dengan Linux containers dan `docker compose version` (Compose v2)
- Browser dengan izin kamera + geolokasi; HTTPS wajib di produksi/non-loopback

### Langkah

```bash
# 1. Clone repository
git clone <GIT_URL>
cd sistem-absensi-sekolah

# 2. Install dependency PHP
composer install

# 3. Install dependency JS
npm ci

# 4. Copy environment file
cp .env.example .env

# 5. Generate application key
php artisan key:generate

# 6. Generate FACE_SERVICE_API_KEY (nilai sama dipakai Laravel dan container FastAPI)
openssl rand -hex 32

# 7. Konfigurasi database di .env (DB_DATABASE, DB_USERNAME, DB_PASSWORD)

# 8. Jalankan migrasi
php artisan migrate

# 9. Buat akun super admin pertama (interaktif: NIP, nama, email, password)
php artisan admin:create-super

# 10. Build aset frontend
npm run build

# 11. Jalankan service
docker compose up --build -d
php artisan serve
```

> Jangan jalankan `php artisan db:seed` di produksi. Seeder membuat akun demo `ADM001` / `KS001` / `GURU001` dengan password `password` untuk pengembangan saja.

Untuk pengembangan frontend: `npm run dev`.

Verifikasi readiness:

```bash
docker compose ps
docker compose logs -f face-api
curl http://127.0.0.1:8001/health
```

Sehat: `{"status":"ok","model_loaded":true,"model_version":"insightface-buffalo_l-v1"}`

### Konfigurasi `.env` Penting

| Key | Contoh | Keterangan |
|---|---|---|
| `APP_ENV` / `APP_DEBUG` / `APP_URL` | `production` / `false` / `https://example.sch.id` | Produksi wajib `APP_DEBUG=false` |
| `SESSION_IDLE_TIMEOUT` | `10` | Menit idle sebelum logout |
| `SESSION_LIFETIME` | `15` | Lifetime cookie sesi |
| `SESSION_SECURE_COOKIE` | `true` | Produksi HTTPS |
| `SESSION_ENCRYPT` | `true` | Enkripsi file sesi |
| `SESSION_DOMAIN` | `example.sch.id` | Domain polos tanpa skema |
| `FORCE_HTTPS` | `true` | Paksa URL `https` |
| `TRUSTED_PROXIES` | `10.0.0.0/8,172.16.0.0/12` | CIDR proxy; jangan `*` |
| `SCHOOL_NAME` / `SCHOOL_CITY` | `MI Lantaburo` / `Tangerang` | Identitas sekolah untuk laporan |
| `AIO_INTEGRATION_API_KEY` | `random-secret` | Kosong = API AIO nonaktif (503) |
| `FACE_SERVICE_URL` | `http://127.0.0.1:8001` | URL FastAPI dari host Laravel |
| `FACE_SERVICE_API_KEY` | `random-secret` | Header `X-API-Key` ke FastAPI |
| `FACE_SERVICE_CONNECT_TIMEOUT` | `3` | Detik |
| `FACE_SERVICE_TIMEOUT` | `45` | Detik |
| `ATTENDANCE_MAX_GPS_ACCURACY_METERS` | `100` | Default akurasi GPS (meter) |
| `ATTENDANCE_PHOTO_RETENTION_DAYS` | `30` | Retensi foto audit (hari) |
| `SECURITY_CSP_ENABLED` | `true` | Aktifkan CSP header |
| `CORS_ALLOWED_ORIGINS` | `https://example.sch.id` | Default: `APP_URL` |

---

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

### Batasan Biometrik

Belum ada liveness detection. Foto cetak/layar replay dapat lolos. Foto audit dan `distance` tersimpan untuk audit manual; jangan jadikan face match satu-satunya kontrol untuk akses berisiko tinggi.

---

## Scheduler / Cron Job

### Shared Hosting (tanpa SSH)

Gunakan layanan cron eksternal (cron-job.org, EasyCron, dll) atau endpoint HTTP scheduler:

```bash
# Jalankan via cron eksternal setiap menit
php artisan schedule:run
```

### VPS / Dedicated Server

```cron
* * * * * cd /path/to/project && php artisan schedule:run >> /dev/null 2>&1
```

### Jadwal Aplikasi

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

---

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

---

## Pengujian

### Laravel

Suite memakai `DatabaseTransactions` dan butuh MySQL `absensi_guru_testing` yang sudah termigrasi. Kredensial `DB_HOST/PORT/USERNAME/PASSWORD` diwarisi dari `.env` (hanya `DB_CONNECTION`/`DB_DATABASE` di `phpunit.xml`).

```bash
mysql -u <USER> -p -e "CREATE DATABASE absensi_guru_testing CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
APP_ENV=testing DB_DATABASE=absensi_guru_testing php artisan migrate:fresh
php artisan test
# atau tersegmentasi
php artisan test --testsuite=Unit
php artisan test --testsuite=Feature
php artisan test --filter=test_check_in_success_stores_attendance
```

26 test files: 23 Feature + 3 Unit mencakup attendance, leave, payroll, face enrollment, security, session, dashboard, reports.

### FastAPI

Image produksi tidak memuat dev deps. Jalankan dari venv Python terpisah (Python 3.11 sesuai `face-service/Dockerfile`):

```bash
cd face-service
python -m pip install -r requirements-dev.txt
python -m pytest tests
```

### Build Frontend

```bash
npm ci
npm run build
```

---

## Deployment

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

# 2. Dependency & build
composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
npm ci
npm run build
rm -rf node_modules
chmod -R ug+rwX storage bootstrap/cache

# 3. Migrasi (jangan db:seed di produksi)
php artisan migrate --force
php artisan admin:create-super
php artisan optimize

# 4. FastAPI
docker compose build face-api
docker compose up -d face-api
curl --fail http://127.0.0.1:8001/health

# 5. Scheduler (cron tiap menit)
php artisan schedule:list

# 6. Verifikasi
php artisan about --only=environment
php artisan migrate:status
curl --fail https://<DOMAIN>
```

| Skenario | Metode |
|---|---|
| Shared hosting + terminal | `php artisan` langsung + cron |
| Shared hosting tanpa terminal | Scheduler via HTTP endpoint + cron eksternal |
| VPS Linux | Nginx/Apache + Laravel queue + cron |
| Windows / Laragon | `php artisan serve` + Task Scheduler |

CloudPanel/Nginx: document root ke `public/`, aktifkan SSL + redirect HTTP→HTTPS, jangan cache halaman login.

Panduan deployment lengkap: `docs/vps-cloudpanel-deployment.md`.

---

## Keamanan Produksi (Checklist)

Wajib di `.env` produksi: `APP_DEBUG=false`, `APP_URL=https`, `SESSION_SECURE_COOKIE=true`, `SESSION_ENCRYPT=true`, `FORCE_HTTPS=true`, `SECURITY_CSP_ENABLED=true`, `TRUSTED_PROXIES` berisi CIDR spesifik. `AppServiceProvider` fail-fast bila kombinasi produksi tidak aman.

Operasional: document root `public/`, `storage/` + `bootstrap/cache/` writable, `chmod 600 .env`, rotasi `APP_KEY` + `MAIL_PASSWORD` + `DB_PASSWORD`, backup terenkripsi `storage/app` (berisi foto/lampiran privat), pantau `composer audit`/`npm audit`.

Lampiran/foto disajikan via route berotorisasi dengan `Cache-Control: private, no-store`. FastAPI hanya di `127.0.0.1`, `X-API-Key` constant-time, OpenAPI nonaktif default (`ENABLE_DOCS=false`).

Detail hardening: `docs/SECURITY-HARDENING.md`.

---

## Struktur Database

41 migrasi mencakup:

| Tabel | Keterangan |
|---|---|
| `users` | Akun login (NIP, nama, email, role enum, status, password) |
| `teachers` | Data guru/karyawan (nama, jenis kelamin, HP, jabatan, mata pelajaran) |
| `school_locations` | Lokasi sekolah (latitude, longitude, radius, max_gps_accuracy) |
| `attendance_sessions` | Sesi absensi (nama, jam masuk/pulang, toleransi, window check-in/out) |
| `attendance_session_teacher` | Pivot guru ↔ sesi (`is_primary`) |
| `attendances` | Record kehadiran (check-in/out, GPS, foto, match score, status, verifikasi) |
| `teacher_face_enrollments` | Embedding wajah 512-dim (JSON, model_version, template lock) |
| `leave_requests` | Pengajuan izin/cuti (tipe, temporary, tanggal, alasan, lampiran, approval) |
| `leave_request_infal` | Pivot infal (guru pengganti + individual status) |
| `work_schedules` | Jadwal kerja per guru per hari |
| `duty_schedules` | Jadwal piket |
| `holiday_settings` | Hari libur nasional/sekolah |
| `payroll_periods` | Periode penggajian (bulan/tahun, status draft/published) |
| `payroll_items` | Per-teacher payroll (gaji pokok, potongan, infal bonus, gaji bersih) |
| `payroll_item_details` | Line-item breakdown payroll |
| `teacher_salaries` | Gaji pokok per guru |
| `login_activities` | Log aktivitas login |
| `app_notifications` | Notifikasi in-app |
| `password_reset_tokens` | Token reset password |
| `personal_access_tokens` | Sanctum tokens |

---

## Backup dan Data Sensitif

Backup harian: database + `storage/app`. Jangan simpan dump di web root; simpan terenkripsi di lokasi terpisah. Arsip `*.zip`/`*.sql` di-ignore Git; jangan buat arsip mentah working tree. Template biometrik disimpan sebagai JSON cast di `teacher_face_enrollments`; lindungi via akses DB + enkripsi backup/host.

---

## Lisensi

Dirilis di bawah [GNU General Public License v3.0-or-later](LICENSE) (`composer.json`: GPL-3.0-or-later). Karya turunan tetap dirilis di bawah lisensi yang sama.

---

## Dukungan & Kontribusi

Repository ini dipublikasikan sebagai referensi. Untuk pertanyaan, bug report, atau saran, silakan buka [Issues](https://github.com/tarothejungle/sistem-absensi-sekolah/issues).
