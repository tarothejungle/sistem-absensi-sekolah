# Sistem Absensi Sekolah

Aplikasi web berbasis Laravel untuk mengelola kehadiran guru dan tenaga kependidikan. Mencakup absensi multi-sesi dengan verifikasi wajah dan GPS, pengajuan izin/cuti dengan sistem guru pengganti (infal), jadwal kerja, laporan, hingga penggajian.

---

## Fitur Utama

- **Absensi multi-sesi** — check-in/check-out per sesi yang ditugaskan, validasi window waktu (lintas tengah malam), jadwal kerja mingguan, dan status otomatis (Hadir, Terlambat, Hadir Tidak Lengkap, Alfa).
- **Verifikasi wajah 1:1** — capture dari webcam, embedding InsightFace `buffalo_l` via FastAPI, threshold `0.45`, foto audit tersimpan sebagai bukti.
- **GPS geofence** — validasi lokasi dengan rumus Haversine, konfigurasi radius dan akurasi per lokasi sekolah.
- Izin/cuti (sakit, izin, cuti, tugas luar, izin sementara) dengan alur approval dan sistem guru pengganti (infal).
- Penggajian bulanan dengan potongan otomatis dan slip gaji PDF.
- Laporan rekap kehadiran dan infal, export Excel/PDF.
- Dashboard ringkasan harian, notifikasi in-app, dan manajemen data sekolah (guru, sesi, lokasi, hari libur, jadwal piket).
- 5 role: Super Admin, Guru, Bendahara, Kepala Sekolah, Tenaga Kependidikan.

---

## Tech Stack

| Layer | Teknologi |
|---|---|
| Framework | Laravel 12, PHP 8.2+ |
| Database | MySQL / MariaDB |
| Frontend | Blade, Bootstrap 5, Tailwind CSS 4, Vite 8 |
| PDF & Excel | DomPDF 3.1, Maatwebsite Excel 3.1 |
| Face Recognition | FastAPI, InsightFace `buffalo_l`, ONNX Runtime |
| Container | Docker Compose |

---

## Instalasi

```bash
# 1. Clone & install dependency
git clone <GIT_URL> && cd sistem-absensi-sekolah
composer install && npm ci

# 2. Konfigurasi
cp .env.example .env
php artisan key:generate
# Isi DB_DATABASE, DB_USERNAME, DB_PASSWORD, dan FACE_SERVICE_API_KEY

# 3. Migrasi & buat akun admin
php artisan migrate
php artisan admin:create-super

# 4. Build & jalankan
npm run build
docker compose up --build -d
php artisan serve
```

Untuk pengembangan frontend: `npm run dev`.

Verifikasi service wajah:

```bash
curl http://127.0.0.1:8001/health
# {"status":"ok","model_loaded":true,"model_version":"insightface-buffalo_l-v1"}
```

---

## Scheduler

Aktifkan scheduler Laravel (cron tiap menit):

```cron
* * * * * cd /path/to/project && php artisan schedule:run >> /dev/null 2>&1
```

| Command | Frekuensi | Fungsi |
|---|---|---|
| `attendance:sync-status` | Tiap 15 menit + 23:55 | Sinkron status alfa/izin/hadir |
| `attendance:prune-photos` | 02:15 | Hapus foto kedaluwarsa |
| `login-activities:prune` | Tiap jam | Hapus log login >24 jam |
| `auth:clear-resets` | 02:30 | Bersihkan token reset |

---

## Lisensi

[GNU General Public License v3.0-or-later](LICENSE).

## Dukungan

Untuk pertanyaan, bug report, atau saran, silakan buka [Issues](https://github.com/tarothejungle/sistem-absensi-sekolah/issues).
