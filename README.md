# Sistem Absensi Sekolah

Aplikasi Laravel untuk mengelola absensi guru dan tenaga kependidikan, izin/cuti dan guru pengganti (infal), jadwal kerja, laporan, notifikasi, serta penggajian sekolah.

## Fitur Utama

- Absensi multi-sesi dengan jadwal mingguan, window check-in/check-out termasuk lintas tengah malam, GPS geofence, batas akurasi lokasi, dan sinkronisasi status otomatis.
- Verifikasi wajah 1:1 melalui FastAPI dan InsightFace. Produksi mewajibkan active liveness empat langkah untuk enrollment serta check-in/check-out.
- Template wajah terenkripsi, challenge terikat akun/sesi/purpose, single-use, dan foto audit disimpan pada storage private dengan retensi terjadwal.
- Pengajuan sakit, izin, cuti, tugas luar, dan izin sementara beserta approval dan beberapa guru pengganti.
- Penggajian bulanan dengan potongan ketidakhadiran, tambahan infal, slip PDF, rekap PDF, finalisasi terkunci, reopen khusus super admin, dan audit log.
- Laporan absensi dan infal dalam Excel/PDF.
- PWA installable, offline fallback, notifikasi in-app, dan Web Push opt-in.
- Manajemen guru/PTK, admin, sesi, lokasi, jadwal kerja, jadwal piket, dan hari libur.
- Reset password melalui email, proteksi login Cloudflare Turnstile, session idle timeout, CSP, dan role-based access control.
- Lima role: Super Admin, Guru, Bendahara, Kepala Sekolah, dan Tenaga Kependidikan.

## Tech Stack

| Layer | Teknologi |
|---|---|
| Backend | Laravel 12, PHP 8.2+, Sanctum 4 |
| Database | MySQL / MariaDB |
| Frontend | Blade, Bootstrap 5, Tailwind CSS 4, Vite 8, Service Worker |
| PDF & Excel | DomPDF 3.1, Maatwebsite Excel 3.1 |
| Web Push | Minishlink Web Push 9, VAPID |
| Face Recognition | FastAPI, InsightFace `buffalo_l`, ONNX Runtime, OpenCV |
| Testing | PHPUnit 11, Pytest |
| Container | Docker Compose |

## Persyaratan

- PHP 8.2 atau lebih baru beserta ekstensi Laravel dan GD.
- Composer, Node.js/npm, MySQL atau MariaDB.
- Docker Compose untuk face service.
- HTTPS untuk kamera, geolocation, secure session, Turnstile, dan Web Push di production.

## Instalasi Lokal

```bash
git clone https://github.com/tarothejungle/sistem-absensi-sekolah.git
cd sistem-absensi-sekolah
composer install
npm ci

cp .env.example .env
php artisan key:generate
```

Isi minimal `DB_*` dan `FACE_SERVICE_API_KEY` pada `.env`. API key harus sama dengan key container FastAPI.

```bash
npm run build
docker compose up --build -d
php artisan migrate
php artisan admin:create-super
php artisan serve
```

Untuk pengembangan frontend, gunakan `npm run dev`.

## Konfigurasi

### Production Wajib

Gunakan `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://...`, `FORCE_HTTPS=true`, `SESSION_SECURE_COOKIE=true`, `SESSION_ENCRYPT=true`, `FACE_LIVENESS_REQUIRED=true`, serta Turnstile aktif dengan site key, secret key, dan hostname yang benar. Aplikasi gagal start bila kontrol production penting tidak aktif.

Isi `TRUSTED_PROXIES` hanya dengan CIDR reverse proxy yang benar. Jangan memakai `*`.

### Identitas Sekolah

Gunakan `SCHOOL_NAME`, `SCHOOL_CITY`, dan `SCHOOL_HEAD_TITLE` untuk metadata laporan dan integrasi.

### Email

Konfigurasikan `MAIL_*` agar fitur lupa/reset password dapat mengirim email.

### PWA dan Web Push

PWA aktif melalui `PWA_ENABLED=true`. Untuk Web Push:

```bash
php artisan pwa:generate-vapid
```

Masukkan hasilnya ke `PUSH_VAPID_PUBLIC_KEY`, `PUSH_VAPID_PRIVATE_KEY`, dan `PUSH_VAPID_SUBJECT`, lalu aktifkan `PUSH_ENABLED=true`. Gunakan queue database dan queue worker pada production. `PUSH_ALLOWED_HOSTS` membatasi provider Web Push yang boleh dihubungi server.

### Integrasi AIO

Endpoint laporan integrasi tersedia pada:

```text
GET /api/v1/integrations/aio/attendance-report
```

Gunakan `AIO_INTEGRATION_API_KEY`. `AIO_INTEGRATION_PREVIOUS_API_KEY` hanya dipakai sementara saat rotasi. Production mewajibkan HTTPS secara default.

## Face Service

Verifikasi kesehatan service:

```bash
curl http://127.0.0.1:8001/health
```

Respons sehat memuat `status`, `model_loaded`, `model_version`, `liveness_ready`, `liveness_method`, `quality_ready`, dan `quality_method`.

Threshold default container:

- Face match attendance: `0.40`.
- Liveness same-face center: `0.45`.
- Liveness same-face turn: `0.55`.

Face service hanya perlu diekspos pada loopback/private network dan selalu dilindungi `FACE_SERVICE_API_KEY`.

## Scheduler dan Queue

Jalankan scheduler tiap menit:

```cron
* * * * * cd /path/to/project && php artisan schedule:run >> /dev/null 2>&1
```

| Command | Frekuensi | Fungsi |
|---|---|---|
| `attendance:sync-status` | Tiap 15 menit + 23:55 | Sinkron status alfa, izin, dan hadir |
| `notifications:send-attendance-reminders` | Tiap 5 menit | Kirim pengingat absensi Web Push |
| `attendance:prune-photos` | 02:15 | Hapus foto audit kedaluwarsa |
| `biometrics:prune-liveness-challenges` | Tiap 5 menit | Kedaluwarsa dan retensi metadata challenge |
| `login-activities:prune` | Tiap jam | Hapus log login lama |
| `auth:clear-resets` | 02:30 | Bersihkan token reset |

Production dengan Web Push memerlukan worker:

```bash
php artisan queue:work --tries=3
```

## Testing

Buat database MySQL testing terpisah sesuai `phpunit.xml`, lalu jalankan:

```bash
php artisan test
python -m pytest face-service/tests
composer audit --locked
npm audit --omit=dev
npm run build
```

Jangan arahkan test ke database development atau production.

## Lisensi

[GNU General Public License v3.0-or-later](LICENSE).

## Dukungan

Bug report dan saran dapat dibuat melalui [GitHub Issues](https://github.com/tarothejungle/sistem-absensi-sekolah/issues).
