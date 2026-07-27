# Desain Integrasi FastAPI Face Recognition Lokal

## Tujuan

Memindahkan pembuatan embedding dan keputusan face match dari browser ke service FastAPI yang berjalan di Docker. Laravel tetap menjadi pemilik autentikasi, otorisasi, aturan absensi, GPS, penyimpanan template, dan foto audit.

## Alur

### Enrollment

1. Super admin membuka formulir guru dan mengambil satu frame kamera.
2. Browser mengirim frame ke Laravel dengan CSRF dan sesi admin.
3. Laravel memanggil `POST /api/v1/faces/enroll` menggunakan API key internal.
4. FastAPI memvalidasi gambar, memastikan tepat satu wajah, lalu menghasilkan embedding.
5. Laravel menyimpan embedding dan versi model pada `teacher_face_enrollments`.

### Verifikasi absensi

1. Browser mengambil GPS dan satu frame kamera, lalu mengirimkannya ke Laravel.
2. Laravel memvalidasi akun, jadwal, sesi, GPS, dan enrollment.
3. Laravel memanggil `POST /api/v1/faces/verify` dengan frame dan embedding terdaftar.
4. FastAPI mendeteksi wajah dan membandingkan cosine distance dengan threshold server.
5. Laravel hanya menyimpan absensi jika FastAPI mengembalikan `matched: true`.

## Kontrak API

- `GET /health` tidak membutuhkan API key dan hanya mengembalikan kesiapan model.
- `POST /api/v1/faces/enroll` menerima `{ "image": "data:image/...;base64,..." }`.
- `POST /api/v1/faces/verify` menerima `image`, `embedding`, dan `model_version`.
- Endpoint pemrosesan membutuhkan header `X-API-Key`.
- Ukuran gambar hasil decode dibatasi 2 MiB dan hanya JPEG, PNG, atau WebP.
- Model versi pertama adalah `insightface-buffalo_l-v1` dengan embedding 512 dimensi.

## Keamanan dan kegagalan

- Descriptor dan score tidak lagi dibuat atau dipercaya dari browser.
- FastAPI tidak mengakses database dan tidak menerima identitas guru.
- API key disimpan pada environment Laravel dan Docker, bukan source code.
- Port Docker di-bind ke loopback agar tidak tersedia ke jaringan lokal.
- Timeout, koneksi gagal, respons invalid, atau model belum siap menyebabkan absensi ditolak.
- Foto replay masih dapat lolos karena versi ini belum memiliki liveness detection.
- Enrollment `face-api.js` lama tidak kompatibel dan wajib diganti melalui enrollment ulang.

## Kriteria penerimaan

- Container memiliki health check dan dokumentasi OpenAPI.
- Enrollment valid menyimpan embedding 512 dimensi dari FastAPI.
- Gambar tanpa wajah atau dengan lebih dari satu wajah ditolak.
- Check-in dan check-out tidak menerima match score dari browser.
- Wajah tidak cocok dan service tidak tersedia tidak membuat absensi.
- Unit test FastAPI tidak membutuhkan model asli, dan feature test Laravel memakai HTTP fake.
