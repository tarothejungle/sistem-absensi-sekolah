# Catatan Keamanan & Checklist Sebelum Hosting

Dokumen ini merangkum hasil audit keamanan penuh, perbaikan yang sudah
diterapkan di kode, dan langkah wajib yang harus Anda lakukan **di server
produksi** sebelum aplikasi go-live.

Ringkasan hasil audit: **tidak ditemukan celah eksploitatif pada logika
aplikasi** (tidak ada SQL injection, XSS, IDOR, broken access control, atau
mass-assignment escalation). Semua temuan bersifat konfigurasi / kesiapan
produksi + satu paparan data lama. Semua sudah ditangani atau
didokumentasikan di bawah.

---

## 1. Sudah diperbaiki di kode (tidak perlu tindakan lagi)

| Area | Perbaikan |
|------|-----------|
| **Security headers** | Middleware `App\Http\Middleware\SecurityHeaders` menambahkan `Content-Security-Policy`, `X-Frame-Options: DENY`, `X-Content-Type-Options: nosniff`, `Referrer-Policy`, `Permissions-Policy`, dan `Strict-Transport-Security` (khusus HTTPS). Terdaftar di global stack `app/Http/Kernel.php`. |
| **Lampiran izin/cuti bocor** | 12 file lampiran (dokumen pribadi/medis) dipindah dari `public/leave_attachments/` ke `storage/app/leave_attachments/` (privat). Hanya bisa diakses lewat route `leave.attachment.show` yang memeriksa kepemilikan/role. Ditambah rule `Deny` di `public/.htaccess`. |
| **CORS wildcard** | `config/cors.php` tidak lagi `*`. Origin dibatasi ke `APP_URL` / `CORS_ALLOWED_ORIGINS`, method & header dibatasi. |
| **Mass-assignment (defense-in-depth)** | `role` & `status` dihapus dari `$fillable` model `User`. Keduanya hanya di-set eksplisit via `forceFill()` di endpoint admin. Mencegah eskalasi hak akses bila kelak ada `->update($request->all())`. |
| **Enkripsi sesi** | `config/session.php` `encrypt` kini mengikuti env `SESSION_ENCRYPT`. |

> **PENTING – konvensi baru:** setiap kali membuat/ubah `User`, set `role` dan
> `status` **eksplisit** (`$user->role = ...; $user->save();` atau `forceFill`).
> Jangan pernah menaruh lampiran izin/cuti di folder `public/`.

---

## 2. WAJIB dilakukan di server produksi (`.env`)

Salin nilai berikut ke `.env` produksi (bukan `.env.example`):

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://domain-anda.sch.id

# Session aman (aktifkan setelah pakai HTTPS)
SESSION_SECURE_COOKIE=true
SESSION_ENCRYPT=true
SESSION_DOMAIN=domain-anda.sch.id

# Paksa HTTPS
FORCE_HTTPS=true

# CORS ke domain sendiri
CORS_ALLOWED_ORIGINS=https://domain-anda.sch.id

# Log lebih senyap di produksi
LOG_LEVEL=warning
```

Lalu jalankan:

```bash
php artisan key:generate      # APP_KEY unik untuk produksi (jangan pakai key dev)
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### 2a. Rotasi kredensial (PENTING)
- **Password SMTP** (`MAIL_PASSWORD`) saat ini tersimpan plaintext di `.env`.
  Ganti/rotasi password akun email di panel provider sebelum repo/backup
  dibagikan ke pihak lain. Simpan `.env` hanya di server, chmod `640`.
- **Password database**: `DB_PASSWORD` masih kosong. Buat user MySQL khusus
  dengan password kuat & hak akses terbatas (jangan pakai `root`).

---

## 3. Konfigurasi web server

- **HTTPS** aktif (Let's Encrypt / SSL provider). Redirect semua HTTP → HTTPS.
- **Document root** harus mengarah ke folder `public/`, **bukan** root proyek.
  Ini mencegah akses langsung ke `.env`, `storage/`, `vendor/`, dll.
- Jika memakai **Nginx** (bukan Apache), aturan `public/.htaccess` tidak
  berlaku. Replikasikan di server block:
  ```nginx
  location ~ /\.(?!well-known) { deny all; }
  location ~* \.(env|log)$ { deny all; }
  location ^~ /leave_attachments/ { deny all; }
  ```
- Set izin file: folder `storage/` dan `bootstrap/cache/` writable oleh user
  web server; sisanya read-only.
- Jika di belakang reverse proxy/Cloudflare, isi `TrustProxies::$proxies`
  dengan IP/CIDR proxy Anda (jangan `'*'`).

---

## 4. Tips tambahan (opsional, meningkatkan keamanan lebih jauh)

1. **Backup terjadwal** database + folder `storage/app` (berisi foto absensi &
   lampiran). Simpan terenkripsi di lokasi terpisah.
2. **Kebijakan password**: pertimbangkan menambah aturan kompleksitas (mis.
   `Password::min(8)->letters()->numbers()`) pada reset & ganti password.
3. **Perpendek masa token reset** password (`config/auth.php` → `expire`) dari
   60 → 15–30 menit.
4. **Face verification** sudah memakai InsightFace melalui FastAPI internal.
   Liveness detection belum tersedia, sehingga foto atau replay layar masih
   perlu dipertimbangkan sebagai risiko dan diaudit dari foto absensi.
5. **Rate limit tambahan**: login & reset password sudah `throttle:5,1`.
   Pertimbangkan captcha bila terjadi brute force masif.
6. **Update dependency** rutin: `composer update` + pantau `composer audit`.
7. **Nonaktifkan** `APP_DEBUG` selamanya di produksi (sudah `false`). Jangan
   pernah menyalakannya untuk debugging di server publik.
8. **Monitoring**: pantau `storage/logs/laravel.log` untuk error 500 & percobaan
   akses 403/419 yang tidak wajar.

---

## 5. Verifikasi cepat pasca-deploy

```bash
# Header keamanan muncul?
curl -sI https://domain-anda.sch.id/login | grep -iE 'content-security|x-frame|strict-transport'

# Lampiran tidak bisa diakses langsung (harus 403/404)?
curl -sI https://domain-anda.sch.id/leave_attachments/apapun.jpg

# Debug mati (halaman error tidak menampilkan stack trace)?
```

Jalankan seluruh test otomatis (`php artisan test`) sebelum setiap release.
