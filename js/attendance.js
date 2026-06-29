document.addEventListener('DOMContentLoaded', async function () {
    const videos = document.querySelectorAll('.attendance-video');
    const forms = document.querySelectorAll('.absensi-form');

    async function startCamera() {
        try {
            if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                alert('Browser tidak mendukung akses kamera. Pastikan akses melalui HTTPS.');
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
                message = 'Akses kamera ditolak. Cek permission kamera di browser.';
            } else if (e.name === 'NotFoundError' || e.name === 'DevicesNotFoundError') {
                message = 'Kamera tidak ditemukan di perangkat ini.';
            } else if (e.name === 'NotReadableError' || e.name === 'TrackStartError') {
                message = 'Kamera sedang digunakan aplikasi/tab lain atau terkunci oleh sistem.';
            } else if (e.name === 'SecurityError') {
                message = 'Akses kamera diblokir oleh keamanan browser/server.';
            }

            alert(message + '\n\nKode error: ' + (e.name || '-') + '\nPesan: ' + (e.message || '-'));
        }
    }

    function getLocation() {
        return new Promise((resolve, reject) => {
            if (!navigator.geolocation) {
                reject('Browser tidak mendukung GPS.');
                return;
            }

            navigator.geolocation.getCurrentPosition(
                pos => {
                    resolve({
                        lat: pos.coords.latitude,
                        lng: pos.coords.longitude,
                        accuracy: pos.coords.accuracy
                    });
                },
                err => {
                    reject('GPS gagal dibaca: ' + err.message);
                },
                {
                    enableHighAccuracy: true,
                    timeout: 20000,
                    maximumAge: 0
                }
            );
        });
    }

    function captureFace(sessionId) {
        const video = document.getElementById('video-' + sessionId);
        const canvas = document.getElementById('canvas-' + sessionId);

        if (!video || !canvas) {
            throw 'Kamera untuk sesi ini tidak ditemukan.';
        }

        if (!video.videoWidth || !video.videoHeight) {
            throw 'Kamera belum siap. Tunggu beberapa detik lalu coba lagi.';
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

            if (button && button.disabled) {
                return;
            }

            try {
                const sessionInput = form.querySelector('[name="attendance_session_id"]');
                const sessionId = sessionInput ? sessionInput.value : null;

                if (!sessionId) {
                    alert('Sesi absensi tidak ditemukan.');
                    return;
                }

                if (button) {
                    button.disabled = true;
                    button.dataset.originalText = button.innerHTML;
                    button.innerHTML = 'Memproses...';
                }

                const loc = await getLocation();
                const faceImage = captureFace(sessionId);

                form.querySelector('[name="latitude"]').value = loc.lat;
                form.querySelector('[name="longitude"]').value = loc.lng;

                if (form.querySelector('[name="accuracy"]')) {
                    form.querySelector('[name="accuracy"]').value = loc.accuracy;
                }

                form.querySelector('[name="face_image"]').value = faceImage;
                form.submit();
            } catch (err) {
                alert(err);

                if (button) {
                    button.disabled = false;
                    button.innerHTML = button.dataset.originalText || 'Submit';
                }
            }
        });
    });

    await startCamera();
});
