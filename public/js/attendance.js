document.addEventListener('DOMContentLoaded', async function () {
    const videos = document.querySelectorAll('.attendance-video');
    const forms = document.querySelectorAll('.absensi-form');

    const SPINNER = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> ';

    function notify(message, title) {
        if (typeof window.showAppToast === 'function') {
            window.showAppToast('danger', message, title || 'Absensi Gagal');
        } else {
            alert(message);
        }
    }

    function errorMessage(err) {
        if (!err) {
            return 'Terjadi kesalahan. Silakan coba lagi.';
        }

        if (typeof err === 'string') {
            return err;
        }

        return err.message || 'Terjadi kesalahan. Silakan coba lagi.';
    }

    async function startCamera() {
        try {
            if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                notify('Browser tidak mendukung akses kamera. Pastikan membuka aplikasi lewat HTTPS.', 'Kamera tidak didukung');
                return;
            }

            if (videos.length === 0) {
                return;
            }

            const stream = await navigator.mediaDevices.getUserMedia({
                video: true,
                audio: false
            });

            videos.forEach(video => {
                video.srcObject = stream;
                video.muted = true;
                video.playsInline = true;

                video.onloadedmetadata = function () {
                    video.play().catch(error => console.error('Video play error:', error));
                };
            });
        } catch (e) {
            console.error('Camera error detail:', e);

            let message = 'Kamera tidak bisa diakses.';

            if (e.name === 'NotAllowedError' || e.name === 'PermissionDeniedError') {
                message = 'Akses kamera ditolak. Aktifkan izin kamera untuk situs ini di pengaturan browser.';
            } else if (e.name === 'NotFoundError' || e.name === 'DevicesNotFoundError') {
                message = 'Kamera tidak ditemukan di perangkat ini.';
            } else if (e.name === 'NotReadableError' || e.name === 'TrackStartError') {
                message = 'Kamera sedang digunakan aplikasi/tab lain atau terkunci oleh sistem.';
            } else if (e.name === 'SecurityError') {
                message = 'Akses kamera diblokir oleh keamanan browser/server.';
            }

            notify(message + ' (Kode: ' + (e.name || '-') + ')', 'Kamera Bermasalah');
        }
    }

    function isPermissionDenied(err) {
        return !!err && (
            err.code === 1 ||
            err.name === 'PermissionDeniedError' ||
            err.name === 'NotAllowedError'
        );
    }

    function requestPosition(options) {
        return new Promise(function (resolve, reject) {
            navigator.geolocation.getCurrentPosition(
                function (pos) {
                    resolve({
                        lat: pos.coords.latitude,
                        lng: pos.coords.longitude,
                        accuracy: pos.coords.accuracy
                    });
                },
                function (err) {
                    reject(err);
                },
                options
            );
        });
    }

    // Watchdog manual: `timeout` bawaan getCurrentPosition TIDAK berjalan selama
    // prompt izin lokasi masih tampil, sehingga tombol bisa "muter-muter"
    // selamanya. Ini menjamin promise selalu selesai dalam batas waktu.
    function withWatchdog(promise, ms) {
        let timer = null;

        const watchdog = new Promise(function (_, reject) {
            timer = window.setTimeout(function () {
                const timeoutError = new Error('watchdog-timeout');
                timeoutError.code = 3;
                reject(timeoutError);
            }, ms);
        });

        return Promise.race([promise, watchdog]).finally(function () {
            window.clearTimeout(timer);
        });
    }

    async function getLocation() {
        if (!navigator.geolocation) {
            throw new Error('Perangkat/browser tidak mendukung GPS. Absensi membutuhkan lokasi.');
        }

        // Percobaan 1: akurasi tinggi.
        try {
            return await withWatchdog(
                requestPosition({ enableHighAccuracy: true, timeout: 15000, maximumAge: 0 }),
                18000
            );
        } catch (err) {
            if (isPermissionDenied(err)) {
                throw new Error('Izin lokasi ditolak. Aktifkan izin lokasi untuk situs ini di pengaturan browser, lalu coba lagi.');
            }

            // Percobaan 2 (fallback): akurasi rendah tapi lebih cepat dapat fix.
            // Server sudah toleran akurasi hingga 100 meter.
            try {
                return await withWatchdog(
                    requestPosition({ enableHighAccuracy: false, timeout: 12000, maximumAge: 30000 }),
                    15000
                );
            } catch (err2) {
                if (isPermissionDenied(err2)) {
                    throw new Error('Izin lokasi ditolak. Aktifkan izin lokasi untuk situs ini di pengaturan browser, lalu coba lagi.');
                }

                throw new Error('Lokasi GPS tidak terbaca. Pastikan GPS/Location aktif, berada di area terbuka, lalu coba lagi.');
            }
        }
    }

    function captureFace(sessionId) {
        const video = document.getElementById('video-' + sessionId);
        const canvas = document.getElementById('canvas-' + sessionId);

        if (!video || !canvas) {
            throw new Error('Kamera untuk sesi ini tidak ditemukan.');
        }

        if (!video.videoWidth || !video.videoHeight) {
            throw new Error('Kamera belum siap. Tunggu beberapa detik hingga gambar muncul, lalu coba lagi.');
        }

        canvas.width = video.videoWidth || 640;
        canvas.height = video.videoHeight || 480;

        const context = canvas.getContext('2d');
        context.save();
        context.translate(canvas.width, 0);
        context.scale(-1, 1);
        context.drawImage(video, 0, 0, canvas.width, canvas.height);
        context.restore();

        return canvas.toDataURL('image/jpeg', 0.85);
    }

    forms.forEach(form => {
        form.addEventListener('submit', async function (e) {
            e.preventDefault();

            const button = form.querySelector('button[type="submit"]');

            // Cegah dobel-proses tanpa bergantung pada atribut `disabled`
            // (yang bisa disentuh handler lain).
            if (button && button.dataset.processing === 'true') {
                return;
            }

            const originalText = button ? button.innerHTML : '';

            function setButtonText(text) {
                if (button) {
                    button.innerHTML = text;
                }
            }

            function lockButton() {
                if (button) {
                    button.dataset.processing = 'true';
                    button.disabled = true;
                    button.setAttribute('aria-busy', 'true');
                }
            }

            function releaseButton() {
                if (button) {
                    delete button.dataset.processing;
                    button.disabled = false;
                    button.removeAttribute('aria-busy');
                    button.innerHTML = originalText;
                }
            }

            try {
                const sessionInput = form.querySelector('[name="attendance_session_id"]');
                const sessionId = sessionInput ? sessionInput.value : null;

                if (!sessionId) {
                    notify('Sesi absensi tidak ditemukan.');
                    return;
                }

                lockButton();

                setButtonText(SPINNER + 'Mengambil lokasi...');
                const loc = await getLocation();

                setButtonText(SPINNER + 'Mengambil foto...');
                const faceImage = captureFace(sessionId);

                form.querySelector('[name="latitude"]').value = loc.lat;
                form.querySelector('[name="longitude"]').value = loc.lng;

                const accuracyField = form.querySelector('[name="accuracy"]');
                if (accuracyField) {
                    accuracyField.value = (loc.accuracy !== null && loc.accuracy !== undefined) ? loc.accuracy : '';
                }

                form.querySelector('[name="face_image"]').value = faceImage;

                setButtonText(SPINNER + 'Menyimpan...');

                // Sukses: submit native (memicu navigasi). Tombol sengaja tetap
                // terkunci sampai halaman berpindah.
                form.submit();
            } catch (err) {
                // Apa pun yang gagal, tombol WAJIB pulih agar tidak nyangkut.
                releaseButton();
                notify(errorMessage(err));
            }
        });
    });

    await startCamera();
});
