document.addEventListener('DOMContentLoaded', async function () {
    const videos = document.querySelectorAll('.attendance-video');
    const forms = document.querySelectorAll('.absensi-form');
    const maxAttempts = Number(window.attendanceFaceOptions?.maxAttempts ?? 3);
    const attempts = new Map();
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

        return typeof err === 'string' ? err : (err.message || 'Terjadi kesalahan. Silakan coba lagi.');
    }

    async function startCamera() {
        try {
            if (!navigator.mediaDevices?.getUserMedia) {
                notify('Browser tidak mendukung akses kamera. Pastikan membuka aplikasi lewat HTTPS.', 'Kamera tidak didukung');
                return;
            }

            if (videos.length === 0) {
                return;
            }

            const stream = await navigator.mediaDevices.getUserMedia({
                video: { facingMode: 'user', width: { ideal: 640 }, height: { ideal: 480 } },
                audio: false,
            });

            videos.forEach(function (video) {
                video.srcObject = stream;
                video.muted = true;
                video.playsInline = true;
                video.onloadedmetadata = function () {
                    video.play().catch(function (error) {
                        console.error('Video play error:', error);
                    });
                };
            });
        } catch (error) {
            let message = 'Kamera tidak bisa diakses.';

            if (error.name === 'NotAllowedError' || error.name === 'PermissionDeniedError') {
                message = 'Akses kamera ditolak. Aktifkan izin kamera untuk situs ini di pengaturan browser.';
            } else if (error.name === 'NotFoundError' || error.name === 'DevicesNotFoundError') {
                message = 'Kamera tidak ditemukan di perangkat ini.';
            } else if (error.name === 'NotReadableError' || error.name === 'TrackStartError') {
                message = 'Kamera sedang digunakan aplikasi/tab lain atau terkunci oleh sistem.';
            }

            notify(message + ' (Kode: ' + (error.name || '-') + ')', 'Kamera Bermasalah');
        }
    }

    function isPermissionDenied(error) {
        return !!error && (error.code === 1 || error.name === 'PermissionDeniedError' || error.name === 'NotAllowedError');
    }

    function requestPosition(options) {
        return new Promise(function (resolve, reject) {
            navigator.geolocation.getCurrentPosition(function (position) {
                resolve({ lat: position.coords.latitude, lng: position.coords.longitude, accuracy: position.coords.accuracy });
            }, reject, options);
        });
    }

    function withWatchdog(promise, milliseconds) {
        let timer = null;
        const watchdog = new Promise(function (_, reject) {
            timer = window.setTimeout(function () {
                const error = new Error('watchdog-timeout');
                error.code = 3;
                reject(error);
            }, milliseconds);
        });

        return Promise.race([promise, watchdog]).finally(function () {
            window.clearTimeout(timer);
        });
    }

    async function getLocation() {
        if (!navigator.geolocation) {
            throw new Error('Perangkat/browser tidak mendukung GPS. Absensi membutuhkan lokasi.');
        }

        try {
            return await withWatchdog(requestPosition({ enableHighAccuracy: true, timeout: 15000, maximumAge: 0 }), 18000);
        } catch (error) {
            if (isPermissionDenied(error)) {
                throw new Error('Izin lokasi ditolak. Aktifkan izin lokasi untuk situs ini di pengaturan browser, lalu coba lagi.');
            }

            try {
                return await withWatchdog(requestPosition({ enableHighAccuracy: false, timeout: 12000, maximumAge: 30000 }), 15000);
            } catch (fallbackError) {
                if (isPermissionDenied(fallbackError)) {
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

        canvas.width = video.videoWidth;
        canvas.height = video.videoHeight;
        const context = canvas.getContext('2d');
        context.save();
        context.translate(canvas.width, 0);
        context.scale(-1, 1);
        context.drawImage(video, 0, 0, canvas.width, canvas.height);
        context.restore();

        return { canvas: canvas, image: canvas.toDataURL('image/jpeg', 0.85) };
    }

    function showOverlay(sessionId, className) {
        const overlay = document.getElementById('overlay-' + sessionId);
        if (overlay) {
            overlay.style.display = 'flex';
            overlay.className = 'face-scan-overlay ' + (className || '');
        }
    }

    function hideOverlay(sessionId) {
        const overlay = document.getElementById('overlay-' + sessionId);
        if (overlay) {
            overlay.style.display = 'none';
            overlay.className = 'face-scan-overlay';
        }
    }

    function setOverlayStatus(sessionId, text) {
        const overlay = document.getElementById('overlay-' + sessionId);
        if (overlay) {
            const label = overlay.querySelector('.scan-status-label');
            if (label) {
                label.textContent = text;
            }
        }
    }

    function setOverlayCountdown(sessionId, number) {
        const overlay = document.getElementById('overlay-' + sessionId);
        if (overlay) {
            const countdown = overlay.querySelector('.scan-countdown');
            if (countdown) {
                countdown.textContent = number;
                countdown.style.animation = 'none';
                void countdown.offsetHeight;
                countdown.style.animation = '';
            }
        }
    }

    function countdown(sessionId, seconds) {
        return new Promise(function (resolve) {
            let remaining = seconds;

            function tick() {
                if (remaining <= 0) {
                    resolve();
                    return;
                }

                showOverlay(sessionId, 'countdown');
                setOverlayCountdown(sessionId, remaining);
                remaining--;

                setTimeout(tick, 1000);
            }

            tick();
        });
    }

    forms.forEach(function (form) {
        form.addEventListener('submit', async function (event) {
            event.preventDefault();
            const button = form.querySelector('button[type="submit"]');
            const attemptKey = form.action + ':' + form.querySelector('[name="attendance_session_id"]')?.value;
            const currentAttempts = attempts.get(attemptKey) ?? 0;

            if (button?.dataset.processing === 'true' || currentAttempts >= maxAttempts) {
                return;
            }

            const originalText = button?.innerHTML ?? '';
            const lockButton = function () {
                if (button) {
                    button.dataset.processing = 'true';
                    button.disabled = true;
                    button.setAttribute('aria-busy', 'true');
                }
            };
            const releaseButton = function () {
                if (button) {
                    delete button.dataset.processing;
                    button.disabled = false;
                    button.removeAttribute('aria-busy');
                    button.innerHTML = originalText;
                }
            };
            const setButtonText = function (text) {
                if (button) {
                    button.innerHTML = text;
                }
            };

            let sessionId = null;

            try {
                sessionId = form.querySelector('[name="attendance_session_id"]')?.value;
                if (!sessionId) {
                    throw new Error('Sesi absensi tidak ditemukan.');
                }

                lockButton();
                setButtonText(SPINNER + 'Mengambil lokasi...');
                showOverlay(sessionId);
                setOverlayStatus(sessionId, '📍 Mengambil lokasi GPS...');
                const location = await getLocation();

                setButtonText(SPINNER + 'Bersiap...');
                setOverlayStatus(sessionId, 'Bersiap memindai wajah...');
                await countdown(sessionId, 3);

                setButtonText(SPINNER + 'Mengambil foto...');
                showOverlay(sessionId, 'scanning');
                setOverlayStatus(sessionId, 'Mengambil foto wajah - tetap menghadap kamera');
                const capture = captureFace(sessionId);

                showOverlay(sessionId, 'success');
                setOverlayStatus(sessionId, 'Foto berhasil diambil');
                await new Promise(function (resolve) { setTimeout(resolve, 400); });

                form.querySelector('[name="latitude"]').value = location.lat;
                form.querySelector('[name="longitude"]').value = location.lng;
                form.querySelector('[name="accuracy"]').value = location.accuracy ?? '';
                form.querySelector('[name="face_image"]').value = capture.image;

                setButtonText(SPINNER + 'Menyimpan...');
                setOverlayStatus(sessionId, 'Menyimpan data absensi...');
                form.submit();
            } catch (error) {
                const failedAttempts = currentAttempts + 1;
                attempts.set(attemptKey, failedAttempts);
                releaseButton();

                if (sessionId) {
                    showOverlay(sessionId, 'error');
                    setOverlayStatus(sessionId, '✗ Verifikasi gagal');
                    setTimeout(function () { hideOverlay(sessionId); }, 1500);
                }

                if (failedAttempts >= maxAttempts) {
                    if (button) {
                        button.disabled = true;
                    }
                    notify('Verifikasi wajah gagal ' + maxAttempts + ' kali. Hubungi admin untuk bantuan.', 'Batas Percobaan Tercapai');
                    return;
                }

                notify(errorMessage(error) + ' Sisa percobaan: ' + (maxAttempts - failedAttempts) + '.', 'Verifikasi Wajah Gagal');
            }
        });
    });

    await startCamera();
});
