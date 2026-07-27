# Panduan Face Recognition FastAPI Lokal

## Batas fitur saat ini

Fitur memakai face match 1:1 melalui FastAPI yang berjalan di Docker. Laravel mengirim foto kamera dan template akun yang sedang login ke service internal, lalu hanya menerima keputusan dan distance dari server.

- Tidak memakai liveness detection.
- Foto/layar replay dan manipulasi browser masih mungkin dilakukan.
- Model wajah diunduh ke volume Docker saat container pertama kali dijalankan.
- HTTPS, kamera, dan GPS wajib aktif.
- Maksimal tiga percobaan pengambilan foto per tombol check-in atau check-out. Penolakan akhir tetap dilakukan server.

Descriptor dan match score dari browser tidak dipercaya. Seluruh keputusan face match dibuat oleh FastAPI.

## Setup lokal

1. Pastikan Docker Desktop menggunakan Linux containers.
2. Isi API key acak yang sama pada `.env` Laravel:

```dotenv
FACE_SERVICE_URL=http://127.0.0.1:8001
FACE_SERVICE_API_KEY=ganti-dengan-random-secret
```

3. Jalankan migrasi dan bersihkan cache Laravel:

```bash
php artisan migrate
php artisan optimize:clear
```

4. Build dan jalankan FastAPI:

```bash
docker compose up --build -d
```

5. Tunggu model selesai diunduh, lalu periksa status:

```text
docker compose ps
docker compose logs -f face-api
```

## Daftarkan wajah guru/karyawan

1. Login sebagai `super_admin`.
2. Buka menu **Data Guru**.
3. Pilih **Edit** pada guru/karyawan.
4. Buka bagian **Daftarkan Wajah untuk Absensi**.
5. Izinkan akses kamera browser.
6. Pastikan hanya satu orang terlihat, wajah menghadap kamera, cukup terang, dan tidak terlalu jauh.
7. Klik **Ambil Foto dan Daftarkan Wajah**.
8. Tunggu pesan sukses.

Pendaftaran ulang mengganti template lama. Gunakan ulang bila tampilan wajah berubah jauh, kamera pertama buruk, atau face match sering gagal.

## Cara absensi guru/karyawan

1. Login memakai akun sendiri.
2. Buka menu **Absensi**.
3. Izinkan kamera dan lokasi/GPS.
4. Hadapkan wajah ke kamera. Pastikan hanya satu wajah terlihat.
5. Tekan **Check-in** atau **Check-out**.
6. Sistem mengambil lokasi dan foto, FastAPI memindai wajah, lalu Laravel menyimpan absensi bila wajah cocok.

Jika gagal, periksa pencahayaan, dekatkan wajah, bersihkan lensa, lalu ulangi. Sistem memberi maksimal tiga percobaan.

## Pesan masalah umum

| Pesan | Tindakan |
|---|---|
| Wajah belum terdaftar | Minta super admin mendaftarkan wajah lewat Data Guru > Edit. |
| Wajah tidak terdeteksi | Hadap kamera, tambah cahaya, lepaskan penutup wajah, dekatkan wajah. |
| Lebih dari satu wajah | Pastikan orang lain tidak masuk frame kamera. |
| Wajah terlalu jauh | Dekatkan wajah ke kamera. |
| Wajah tidak cocok | Ambil ulang dengan pencahayaan baik. Bila tetap gagal, admin daftarkan ulang wajah. |
| Layanan verifikasi wajah tidak tersedia | Pastikan Docker Desktop dan container `face-api` berjalan. |
| Kamera tidak bisa diakses | Izinkan kamera di browser; situs harus HTTPS. |
| Lokasi GPS tidak terbaca | Aktifkan Location/GPS dan gunakan area terbuka. |

## Admin audit

- Foto check-in/check-out tetap tersimpan private seperti sebelum fitur ini.
- Match score tersimpan di `attendances.check_in_face_match_score` dan `attendances.check_out_face_match_score`.
- Template wajah tersimpan di `teacher_face_enrollments`.
- Jangan ekspor atau bagikan descriptor wajah.

## Catatan enrollment lama

Template lama dari `face-api.js` tidak kompatibel dengan embedding InsightFace 512 dimensi. Semua guru/karyawan harus didaftarkan ulang melalui formulir admin. Tambahkan liveness detection pada fase berikutnya bila perlindungan terhadap foto atau layar replay diperlukan.
