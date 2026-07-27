(function () {
    'use strict';

    function init() {
        const form = document.getElementById('face-enrollment-form');

        if (!form) {
            return;
        }

        const video = document.getElementById('face-enrollment-video');
        const canvas = document.getElementById('face-enrollment-canvas');
        const imageInput = document.getElementById('face-image');
        const captureButton = document.getElementById('face-enrollment-capture');
        const retakeButton = document.getElementById('face-enrollment-retake');
        const submitButton = document.getElementById('face-enrollment-submit');
        const preview = document.getElementById('face-enrollment-preview');
        const previewEmpty = document.getElementById('face-enrollment-preview-empty');
        const status = document.getElementById('face-enrollment-status');
        const overlay = document.getElementById('face-enrollment-overlay');
        const overlayStatus = document.getElementById('face-enrollment-overlay-status');
        const countdownValue = document.getElementById('face-enrollment-countdown');
        let cameraStream = null;
        let processing = false;

        function setStatus(message, type) {
            if (status) {
                status.className = 'alert alert-' + (type || 'secondary') + ' mb-3';
                status.textContent = message;
            }
        }

        function showOverlay(state, message) {
            overlay.className = 'face-enrollment-overlay is-visible' + (state ? ' is-' + state : '');
            overlayStatus.textContent = message;
        }

        function hideOverlay() {
            overlay.className = 'face-enrollment-overlay';
            countdownValue.textContent = '';
        }

        function wait(milliseconds) {
            return new Promise(function (resolve) {
                window.setTimeout(resolve, milliseconds);
            });
        }

        async function runCountdown() {
            showOverlay('countdown', 'Posisikan wajah di tengah bingkai');

            for (let remaining = 3; remaining >= 1; remaining--) {
                countdownValue.textContent = remaining;
                countdownValue.classList.remove('is-pulsing');
                void countdownValue.offsetWidth;
                countdownValue.classList.add('is-pulsing');
                await wait(1000);
            }

            countdownValue.textContent = '';
            countdownValue.classList.remove('is-pulsing');
        }

        setStatus('Script aktif. Memeriksa dukungan kamera...', 'info');

        window.addEventListener('error', function (event) {
            setStatus('Error JS: ' + event.message + ' (' + (event.filename || '?') + ':' + event.lineno + ')', 'danger');
        });

        window.addEventListener('unhandledrejection', function (event) {
            const reason = event.reason && (event.reason.message || String(event.reason));
            setStatus('Error async: ' + reason, 'danger');
        });

        function cameraErrorMessage(error) {
            switch (error.name) {
                case 'NotAllowedError':
                case 'PermissionDeniedError':
                    return 'Akses kamera DITOLAK browser. Klik ikon di kiri address bar > Kamera > Izinkan, lalu muat ulang. Kalau sudah "Izinkan" tapi tetap gagal, cek Settings Windows > Privacy & security > Camera > "Let desktop apps access your camera" harus ON.';
                case 'NotFoundError':
                case 'DevicesNotFoundError':
                    return 'Kamera tidak ditemukan. Pastikan webcam terpasang dan aktif di Device Manager.';
                case 'NotReadableError':
                case 'TrackStartError':
                    return 'Kamera terdeteksi tapi tidak bisa dibuka. Biasanya sedang dipakai aplikasi lain (Zoom/Teams/OBS/antivirus) ATAU diblokir Windows: Settings > Privacy & security > Camera > aktifkan "Camera access" dan "Let desktop apps access your camera".';
                case 'SecurityError':
                    return 'Diblokir kebijakan keamanan browser. Gunakan http://127.0.0.1:8000 atau HTTPS.';
                case 'AbortError':
                    return 'Permintaan kamera dibatalkan sistem. Coba muat ulang halaman.';
                default:
                    return 'Kamera gagal diakses (' + error.name + '): ' + (error.message || 'penyebab tidak diketahui.');
            }
        }

        async function startCamera() {
            if (!window.isSecureContext) {
                throw new Error('Bukan secure context. Buka lewat http://127.0.0.1:8000, http://localhost, atau HTTPS.');
            }

            if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                throw new Error('Browser tidak mendukung akses kamera (mediaDevices tidak tersedia).');
            }

            setStatus('Meminta izin kamera... Klik "Izinkan" pada pop-up browser.', 'info');

            try {
                cameraStream = await navigator.mediaDevices.getUserMedia({
                    video: {
                        facingMode: 'user',
                        width: { ideal: 640 },
                        height: { ideal: 480 },
                    },
                    audio: false,
                });
            } catch (error) {
                throw new Error(cameraErrorMessage(error));
            }

            video.srcObject = cameraStream;
            video.muted = true;
            video.playsInline = true;
            await video.play();
        }

        function captureFrame() {
            if (!video.videoWidth || !video.videoHeight) {
                throw new Error('Kamera belum siap. Tunggu sampai gambar kamera muncul.');
            }

            canvas.width = video.videoWidth;
            canvas.height = video.videoHeight;
            const context = canvas.getContext('2d');
            context.save();
            context.translate(canvas.width, 0);
            context.scale(-1, 1);
            context.drawImage(video, 0, 0, canvas.width, canvas.height);
            context.restore();

            return canvas;
        }

        (async function () {
            try {
                await startCamera();
                captureButton.disabled = false;
                setStatus('Kamera siap. Pastikan hanya satu wajah terlihat sebelum mengambil foto.', 'success');
            } catch (error) {
                setStatus(error.message || 'Kamera atau model pengenal wajah gagal disiapkan.', 'danger');
            }
        })();

        captureButton.addEventListener('click', async function () {
            if (captureButton.disabled) {
                return;
            }

            if (processing) {
                return;
            }

            try {
                processing = true;
                captureButton.disabled = true;
                captureButton.setAttribute('aria-busy', 'true');
                captureButton.innerHTML = '<span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span> Bersiap memindai...';
                setStatus('Bersiap mengambil foto wajah. Tetap menghadap kamera.', 'info');

                await runCountdown();

                showOverlay('scanning', 'Memindai wajah, jangan bergerak...');
                captureButton.innerHTML = '<span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span> Mengambil foto...';
                setStatus('Mengambil preview foto wajah...', 'info');

                const frame = captureFrame();
                const image = frame.toDataURL('image/jpeg', 0.85);
                imageInput.value = image;
                preview.src = image;
                await wait(750);

                hideOverlay();
                preview.classList.remove('d-none');
                previewEmpty.classList.add('d-none');
                captureButton.classList.add('d-none');
                retakeButton.classList.remove('d-none');
                submitButton.classList.remove('d-none');
                setStatus('Periksa preview. Ulangi foto jika belum sesuai, atau simpan untuk memproses wajah.', 'success');
                processing = false;
            } catch (error) {
                processing = false;
                showOverlay('error', 'Pemindaian gagal');
                setStatus(error.message || 'Pendaftaran wajah gagal. Coba lagi.', 'danger');
                captureButton.disabled = false;
                captureButton.removeAttribute('aria-busy');
                captureButton.innerHTML = '<i class="bi bi-camera me-1"></i> Ambil Foto';
                window.setTimeout(hideOverlay, 1600);
            }
        });

        retakeButton.addEventListener('click', function () {
            imageInput.value = '';
            preview.removeAttribute('src');
            preview.classList.add('d-none');
            previewEmpty.classList.remove('d-none');
            retakeButton.classList.add('d-none');
            submitButton.classList.add('d-none');
            captureButton.classList.remove('d-none');
            captureButton.disabled = false;
            captureButton.removeAttribute('aria-busy');
            captureButton.innerHTML = '<i class="bi bi-camera me-1"></i> Ambil Foto';
            setStatus('Kamera siap. Ambil foto ulang saat posisi wajah sudah sesuai.', 'info');
        });

        form.addEventListener('submit', function (event) {
            if (!imageInput.value || processing) {
                event.preventDefault();
                return;
            }

            processing = true;
            submitButton.disabled = true;
            retakeButton.disabled = true;
            submitButton.setAttribute('aria-busy', 'true');
            submitButton.innerHTML = '<span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span> Memproses wajah...';
            setStatus('Foto dikirim ke layanan pengenal wajah...', 'info');
        });

        window.addEventListener('beforeunload', function () {
            if (cameraStream) {
                cameraStream.getTracks().forEach(function (track) {
                    track.stop();
                });
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
