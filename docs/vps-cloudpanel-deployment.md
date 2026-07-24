# Deployment VPS CloudPanel

Panduan ini khusus untuk deployment berikut:

- Domain utama: `https://www.absensi-mu.my.id`
- Ubuntu 26.04 LTS x86_64
- CloudPanel, Nginx, PHP 8.3, dan MySQL 8
- FastAPI/InsightFace berjalan di Docker pada `127.0.0.1:8001`
- Zona waktu: `Asia/Jakarta`

Ganti setiap placeholder `<SITE_USER>`, `<DB_NAME>`, `<DB_USER>`, dan nilai rahasia dengan nilai milik server. Jangan menaruh password atau API key di Git.

## 1. Siapkan release GitHub

Sebelum mengunggah source terbaru:

1. Ubah repository `tarothejungle/sistem-absensi-sekolah` menjadi **Private** melalui GitHub `Settings > General > Change repository visibility`.
2. Pastikan `.env`, database, foto, dan credential tidak ikut dalam perubahan Git.
3. Jalankan test dan build di komputer lokal.
4. Commit seluruh source terbaru, push ke `main`, lalu buat tag release, misalnya `v1.0.0`.

VPS sebaiknya memakai tag release, bukan perubahan acak dari branch yang belum diuji.

## 2. Periksa server

Jalankan sebagai `root`:

```bash
timedatectl set-timezone Asia/Jakarta
uname -m
free -h
swapon --show
df -h
php8.3 -v
mysql --version
```

Hasil arsitektur harus `x86_64`. Bila `swapon --show` kosong, tambahkan swap 2 GB sebagai pengaman RAM:

```bash
fallocate -l 2G /swapfile
chmod 600 /swapfile
mkswap /swapfile
swapon /swapfile
printf '/swapfile none swap sw 0 0\n' >> /etc/fstab
```

Swap hanya pengaman. Bila server terus menggunakan swap saat jam absensi, upgrade VPS.

## 3. Install Docker

Gunakan panduan resmi Docker untuk Ubuntu: <https://docs.docker.com/engine/install/ubuntu/>. Setelah instalasi:

```bash
systemctl enable --now docker
docker --version
docker compose version
```

Tidak perlu membuka port `8001` pada firewall. Compose hanya mempublikasikannya ke loopback server.

## 4. Siapkan akses repository private

Masuk sebagai site user CloudPanel, bukan `root`:

```bash
su - <SITE_USER>
ssh-keygen -t ed25519 -C "deploy@absensi-mu.my.id"
cat ~/.ssh/id_ed25519.pub
```

Tambahkan public key tersebut di GitHub melalui repository `Settings > Deploy keys > Add deploy key`. Jangan centang **Allow write access**.

Uji koneksi:

```bash
ssh -T git@github.com
```

## 5. Pasang source aplikasi

Path standar CloudPanel adalah `/home/<SITE_USER>/htdocs/www.absensi-mu.my.id`. Pastikan direktori saat ini hanya placeholder CloudPanel sebelum memindahkannya.

```bash
cd /home/<SITE_USER>/htdocs
mv www.absensi-mu.my.id www.absensi-mu.my.id.placeholder
git clone git@github.com:tarothejungle/sistem-absensi-sekolah.git www.absensi-mu.my.id
cd www.absensi-mu.my.id
git checkout v1.0.0
```

Jika tag release yang dibuat bukan `v1.0.0`, gunakan nama tag yang sebenarnya.

## 6. Buat database

Melalui CloudPanel, buat database dan user database khusus aplikasi. Jangan gunakan user MySQL `root` pada `.env`.

Catat secara lokal:

- Nama database
- Username database
- Password database acak

Jangan mengirim credential tersebut melalui chat atau menyimpannya di repository.

## 7. Konfigurasi `.env`

Sebagai site user:

```bash
cd /home/<SITE_USER>/htdocs/www.absensi-mu.my.id
cp .env.example .env
chmod 600 .env
php8.3 artisan key:generate
openssl rand -hex 32
```

Salin hasil `openssl rand -hex 32` sebagai `FACE_SERVICE_API_KEY`, lalu edit `.env`:

```dotenv
APP_NAME="Sistem Absensi Sekolah"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://www.absensi-mu.my.id
LOG_LEVEL=warning

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=<DB_NAME>
DB_USERNAME=<DB_USER>
DB_PASSWORD=<PASSWORD_DATABASE>

CACHE_DRIVER=file
QUEUE_CONNECTION=sync
SESSION_DRIVER=file
SESSION_SECURE_COOKIE=true
SESSION_ENCRYPT=true
SESSION_DOMAIN=www.absensi-mu.my.id
FORCE_HTTPS=true
CORS_ALLOWED_ORIGINS=https://www.absensi-mu.my.id
SECURITY_CSP_ENABLED=true

FACE_SERVICE_URL=http://127.0.0.1:8001
FACE_SERVICE_API_KEY=<HASIL_OPENSSL_RAND>
FACE_SERVICE_CONNECT_TIMEOUT=3
FACE_SERVICE_TIMEOUT=45
ATTENDANCE_FACE_PHOTO_RETENTION_DAYS=30

MAIL_MAILER=log
```

Gunakan SMTP sebenarnya bila aplikasi perlu mengirim email.

## 8. Install dependency dan build frontend

Jalankan sebagai site user:

```bash
cd /home/<SITE_USER>/htdocs/www.absensi-mu.my.id
php8.3 /usr/local/bin/composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
npm ci
npm run build
rm -rf node_modules
chmod -R ug+rwX storage bootstrap/cache
```

Jika `npm` belum tersedia, instal Node.js LTS untuk site user melalui fitur Node.js CloudPanel atau `nvm`, lalu ulangi `npm ci` dan `npm run build`.

## 9. Jalankan database dan buat admin

Jangan menjalankan `php artisan db:seed` karena seeder pengembangan berisi akun contoh.

```bash
php8.3 artisan migrate --force
php8.3 artisan admin:create-super
php8.3 artisan optimize
```

Command admin akan meminta NIP, nama, email, dan password kuat secara interaktif. Password tidak ditulis ke shell history.

Data sesi absensi, lokasi sekolah, jadwal, dan akun guru dibuat melalui menu admin setelah login.

## 10. Jalankan face recognition

Jalankan Compose sebagai `root` dari direktori project agar dapat membaca `.env` yang sama:

```bash
cd /home/<SITE_USER>/htdocs/www.absensi-mu.my.id
docker compose build face-api
docker compose up -d face-api
docker compose ps
docker compose logs -f --tail=100 face-api
```

Startup pertama lebih lama karena model `buffalo_l` diunduh ke volume Docker. Keluar dari tampilan log dengan `Ctrl+C`; container tetap berjalan.

Periksa health:

```bash
curl --fail http://127.0.0.1:8001/health
```

Respons harus memiliki `"status":"ok"` dan `"model_loaded":true`.

Pastikan service tidak dapat dijangkau dari internet:

```bash
ss -lntp | grep 8001
```

Alamat listen harus `127.0.0.1:8001`, bukan `0.0.0.0:8001`.

## 11. Konfigurasi CloudPanel

Pada site `www.absensi-mu.my.id`:

1. Gunakan PHP 8.3 dan template Laravel.
2. Pastikan document root berakhir di `/public`.
3. Aktifkan sertifikat SSL untuk `www.absensi-mu.my.id` dan `absensi-mu.my.id`.
4. Redirect permanen `absensi-mu.my.id` ke `https://www.absensi-mu.my.id`.
5. Aktifkan redirect HTTP ke HTTPS.
6. Jangan aktifkan Varnish/full-page cache untuk halaman pengguna yang login.

## 12. Aktifkan scheduler

Tambahkan Cron Job di CloudPanel untuk site user:

```cron
* * * * * cd /home/<SITE_USER>/htdocs/www.absensi-mu.my.id && php8.3 artisan schedule:run >> /dev/null 2>&1
```

Periksa jadwal:

```bash
php8.3 artisan schedule:list
```

Command `attendance:prune-face-photos` berjalan setiap hari pukul 02:15 WIB dan menghapus foto yang lebih lama dari 30 hari tanpa menghapus record absensi.

Command `login-activities:prune` berjalan setiap menit dan menghapus aktivitas login yang telah berumur minimal 24 jam.

## 13. Tes sebelum digunakan

```bash
php8.3 artisan about --only=environment
php8.3 artisan migrate:status
curl --fail https://www.absensi-mu.my.id
curl --fail http://127.0.0.1:8001/health
```

Kemudian lakukan dari browser/ponsel:

1. Login super admin.
2. Isi lokasi sekolah dan radius yang benar.
3. Buat sesi dan akun guru.
4. Login dengan akun guru/pegawai lalu daftarkan wajah melalui menu profil.
5. Uji check-in dan check-out menggunakan HTTPS, kamera, dan GPS.
6. Uji wajah salah, lokasi di luar radius, dan kondisi FastAPI dimatikan.
7. Uji beberapa perangkat hampir bersamaan sambil memantau `docker stats` dan `free -h`.

## 14. Deployment update berikutnya

Buat tag baru setelah perubahan lulus test, misalnya `v1.0.1`. Di VPS, sebagai site user:

```bash
cd /home/<SITE_USER>/htdocs/www.absensi-mu.my.id
git fetch --tags origin
git checkout v1.0.1
php8.3 /usr/local/bin/composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
npm ci
npm run build
rm -rf node_modules
php8.3 artisan migrate --force
php8.3 artisan optimize
```

Jika `face-service/`, `compose.yaml`, atau requirements berubah, sebagai `root` jalankan:

```bash
cd /home/<SITE_USER>/htdocs/www.absensi-mu.my.id
docker compose up -d --build face-api
curl --fail http://127.0.0.1:8001/health
```

## 15. Backup dan rollback

Aktifkan backup CloudPanel harian. Backup minimal harus mencakup database dan `/home/<SITE_USER>/htdocs/www.absensi-mu.my.id/storage/app`. Backup lokal tidak melindungi dari VPS atau disk yang hilang; tambahkan remote backup CloudPanel ke Cloudflare R2/Backblaze B2 bila memungkinkan.

Sebelum update, buat dump database. Simpan di direktori private yang tidak berada di web root:

```bash
mkdir -p /home/<SITE_USER>/backups
mysqldump --single-transaction -u <DB_USER> -p <DB_NAME> | gzip > /home/<SITE_USER>/backups/before-update.sql.gz
chmod 600 /home/<SITE_USER>/backups/before-update.sql.gz
```

Rollback source ke tag sebelumnya:

```bash
cd /home/<SITE_USER>/htdocs/www.absensi-mu.my.id
git checkout v1.0.0
php8.3 /usr/local/bin/composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
npm ci
npm run build
rm -rf node_modules
php8.3 artisan optimize
```

Jika update memuat migration yang tidak kompatibel, pulihkan database dari backup yang dibuat sebelum update. Jangan menjalankan `migrate:rollback` secara otomatis tanpa meninjau migration dan data produksi.

## 16. Pemeriksaan rutin

```bash
free -h
df -h
docker compose ps
docker stats --no-stream
docker compose logs --tail=100 face-api
php8.3 artisan schedule:list
```

Upgrade minimal ke 4 vCPU/8 GB bila verifikasi sering timeout, container restart karena kehabisan RAM, atau swap terus digunakan pada jam absensi.
