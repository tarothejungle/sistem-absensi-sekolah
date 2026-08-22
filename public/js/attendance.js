document.addEventListener('DOMContentLoaded', async function () {
    const videos = document.querySelectorAll('.attendance-video');
    const forms = document.querySelectorAll('.absensi-form');
    const options = window.attendanceFaceOptions || {};
    const maxAttempts = Number(options.maxAttempts || 3);
    const attempts = new Map();
    const countableFailures = new Set([
        'face_mismatch', 'face_not_found', 'multiple_faces', 'face_too_small',
        'face_partial', 'face_pose_invalid', 'face_blurry', 'face_too_dark',
        'face_overexposed', 'face_low_quality', 'face_occluded',
        'face_visibility_low', 'invalid_image', 'invalid_embedding', 'model_mismatch',
    ]);
    const SPINNER = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> ';
    const readiness = document.getElementById('biometric-readiness');
    const serviceBadge = document.getElementById('biometric-service-badge');
    const readinessMessage = document.getElementById('biometric-readiness-message');
    const modelVersion = document.getElementById('biometric-model-version');
    const retryButton = document.getElementById('biometric-status-retry');
    let biometricReady = false;
    let cameraReady = videos.length === 0;
    let cameraStream = null;

    function notify(message, title, type) {
        if (typeof window.showAppToast === 'function') {
            window.showAppToast(type || 'danger', message, title || 'Absensi');
        } else {
            alert(message);
        }
    }

    function setReadinessState(payload) {
        if (!readiness) return;
        const service = payload?.service || {};
        const enrollment = payload?.enrollment || {};
        biometricReady = payload?.ready === true && service.ready === true;
        readiness.classList.toggle('is-ready', biometricReady);
        readiness.classList.toggle('is-error', !biometricReady && service.state !== 'starting');

        if (serviceBadge) {
            const labels = {
                ready: 'FastAPI + face match siap',
                starting: 'Model memuat',
                unavailable: 'FastAPI offline',
                misconfigured: 'Konfigurasi belum siap',
                model_mismatch: 'Model tidak cocok',
                quality_unavailable: 'Quality gate belum siap',
            };
            serviceBadge.textContent = labels[service.state] || 'Status tidak diketahui';
            serviceBadge.className = 'badge ' + (biometricReady ? 'bg-success' : service.state === 'starting' ? 'bg-warning text-dark' : 'bg-danger');
        }
        if (readinessMessage) readinessMessage.textContent = payload?.message || service.message || 'Status biometrik belum tersedia.';
        if (modelVersion) {
            const version = service.model_version || 'belum terbaca';
            modelVersion.textContent = enrollment.compatible
                ? 'Template kompatibel. Model aktif: ' + version + '. Face match siap untuk absensi.'
                : 'Model aktif: ' + version + '. Enrollment dengan liveness diperlukan sebelum absensi.';
        }
        syncButtons();
    }

    function syncButtons() {
        forms.forEach(function (form) {
            const button = form.querySelector('button[type="submit"]');
            if (!button || button.dataset.processing === 'true') return;
            button.disabled = form.dataset.actionEnabled !== '1' || !biometricReady || !cameraReady || !navigator.onLine;
        });
    }

    async function checkBiometricStatus() {
        if (!options.statusUrl) return;
        if (retryButton) { retryButton.disabled = true; retryButton.setAttribute('aria-busy', 'true'); }
        try {
            const response = await fetch(options.statusUrl, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' });
            setReadinessState(await response.json());
        } catch (error) {
            setReadinessState({ ready: false, message: 'Status FastAPI tidak dapat dimuat. Coba cek ulang.', service: { ready: false, state: 'unavailable', message: error.message }, enrollment: { compatible: false } });
        } finally {
            if (retryButton) { retryButton.disabled = false; retryButton.removeAttribute('aria-busy'); }
        }
    }

    async function startCamera() {
        if (videos.length === 0) { syncButtons(); return; }
        try {
            cameraStream = await window.BiometricLiveness.startCamera(videos[0]);
            videos.forEach(function (video) { window.BiometricLiveness.attachStream(video, cameraStream); });
            cameraReady = true;
            syncButtons();
        } catch (error) {
            notify(error.message || 'Kamera tidak dapat diakses.', 'Kamera Bermasalah');
        }
    }

    function isPermissionDenied(error) { return !!error && (error.code === 1 || error.name === 'PermissionDeniedError' || error.name === 'NotAllowedError'); }
    function requestPosition(options) { return new Promise(function (resolve, reject) { navigator.geolocation.getCurrentPosition(function (position) { resolve({ lat: position.coords.latitude, lng: position.coords.longitude, accuracy: position.coords.accuracy }); }, reject, options); }); }
    function withWatchdog(promise, milliseconds) { let timer = null; const watchdog = new Promise(function (_, reject) { timer = window.setTimeout(function () { const error = new Error('watchdog-timeout'); error.code = 3; reject(error); }, milliseconds); }); return Promise.race([promise, watchdog]).finally(function () { window.clearTimeout(timer); }); }

    async function getLocation() {
        if (!navigator.geolocation) throw new Error('Perangkat/browser tidak mendukung GPS. Absensi membutuhkan lokasi.');
        try { return await withWatchdog(requestPosition({ enableHighAccuracy: true, timeout: 15000, maximumAge: 0 }), 18000); } catch (error) {
            if (isPermissionDenied(error)) throw new Error('Izin lokasi ditolak. Aktifkan izin lokasi untuk situs ini lalu coba lagi.');
            try { return await withWatchdog(requestPosition({ enableHighAccuracy: false, timeout: 12000, maximumAge: 30000 }), 15000); } catch (fallbackError) {
                if (isPermissionDenied(fallbackError)) throw new Error('Izin lokasi ditolak. Aktifkan izin lokasi untuk situs ini lalu coba lagi.');
                throw new Error('Lokasi GPS tidak terbaca. Pastikan Location aktif dan berada di area terbuka.');
            }
        }
    }

    function showOverlay(sessionId, className) { const overlay = document.getElementById('overlay-' + sessionId); if (overlay) { overlay.style.display = 'flex'; overlay.className = 'face-scan-overlay ' + (className || ''); } }
    function hideOverlay(sessionId) { const overlay = document.getElementById('overlay-' + sessionId); if (overlay) { overlay.style.display = 'none'; overlay.className = 'face-scan-overlay'; } }
    function setOverlayStatus(sessionId, text) { const label = document.getElementById('overlay-' + sessionId)?.querySelector('.scan-status-label'); if (label) label.textContent = text; }
    function setStep(sessionId, step, state) { const card = document.getElementById('video-' + sessionId)?.closest('.card'); card?.querySelectorAll('[data-biometric-step]').forEach(function (item) { item.classList.toggle('is-active', item.dataset.biometricStep === step && state === 'active'); item.classList.toggle('is-complete', item.dataset.biometricStep === step && state === 'complete'); item.classList.toggle('is-error', item.dataset.biometricStep === step && state === 'error'); }); }

    async function submitAttendance(form, sessionId, location, biometricPayload) {
        const data = new FormData(form);
        data.set('latitude', location.lat); data.set('longitude', location.lng); data.set('accuracy', location.accuracy ?? '');
        if (biometricPayload.faceImage) data.set('face_image', biometricPayload.faceImage);
        if (biometricPayload.challengeId) data.set('liveness_challenge_id', biometricPayload.challengeId);
        (biometricPayload.frames || []).forEach(function (frame) { data.append('liveness_frames[]', frame); });
        const response = await fetch(form.action, { method: 'POST', body: data, credentials: 'same-origin', headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
        const payload = await response.json().catch(function () { return {}; });
        if (!response.ok || payload.ok !== true) { const error = new Error(payload.message || 'Verifikasi biometrik gagal.'); error.code = payload.code || 'attendance_failed'; error.stage = payload.stage || 'verify'; error.retryable = payload.retryable === true; throw error; }
        setStep(sessionId, 'verify', 'complete'); setStep(sessionId, 'save', 'complete');
        return payload;
    }

    forms.forEach(function (form) {
        form.addEventListener('submit', async function (event) {
            event.preventDefault();
            const button = form.querySelector('button[type="submit"]');
            const sessionId = form.querySelector('[name="attendance_session_id"]')?.value;
            const attemptKey = form.action + ':' + sessionId;
            const currentAttempts = attempts.get(attemptKey) || 0;
            if (!sessionId || button?.dataset.processing === 'true' || currentAttempts >= maxAttempts) return;
            if (!biometricReady) { await checkBiometricStatus(); if (!biometricReady) { notify('Verifikasi biometrik belum siap.', 'Biometrik Belum Siap'); return; } }
            const originalText = button.innerHTML;
            button.dataset.processing = 'true'; button.disabled = true; button.setAttribute('aria-busy', 'true');
            try {
                setStep(sessionId, 'gps', 'active'); button.innerHTML = SPINNER + 'Mengambil lokasi...'; showOverlay(sessionId); setOverlayStatus(sessionId, 'Mengambil lokasi GPS...');
                const location = await getLocation(); setStep(sessionId, 'gps', 'complete');
                let biometricPayload;
                if (options.livenessRequired) {
                    setStep(sessionId, 'liveness', 'active'); button.innerHTML = SPINNER + 'Memeriksa liveness...'; showOverlay(sessionId, 'scanning');
                    const liveness = await window.BiometricLiveness.run({
                        video: document.getElementById('video-' + sessionId),
                        canvas: document.getElementById('canvas-' + sessionId),
                        purpose: form.dataset.action,
                        sessionId: sessionId,
                        onStatus: function (message) { setOverlayStatus(sessionId, message); },
                    });
                    setStep(sessionId, 'liveness', 'complete');
                    biometricPayload = { challengeId: liveness.challengeId, frames: liveness.frames };
                } else {
                    biometricPayload = { faceImage: window.BiometricLiveness.captureFrame(document.getElementById('video-' + sessionId), document.getElementById('canvas-' + sessionId)) };
                }
                setStep(sessionId, 'verify', 'active'); button.innerHTML = SPINNER + 'Memverifikasi wajah...'; setOverlayStatus(sessionId, 'Mencocokkan wajah terdaftar...');
                await submitAttendance(form, sessionId, location, biometricPayload); showOverlay(sessionId, 'success'); setOverlayStatus(sessionId, 'Biometrik berhasil. Absensi tersimpan.'); notify('Wajah terverifikasi dan absensi telah disimpan.', 'Biometrik Terverifikasi', 'success'); window.setTimeout(function () { window.location.reload(); }, 900);
            } catch (error) {
                const countAttempt = countableFailures.has(error.code); const failedAttempts = countAttempt ? currentAttempts + 1 : currentAttempts; if (countAttempt) attempts.set(attemptKey, failedAttempts);
                setStep(sessionId, error.stage === 'gps' ? 'gps' : 'verify', 'error'); showOverlay(sessionId, 'error'); setOverlayStatus(sessionId, error.message || 'Face match gagal.'); notify(error.message || 'Face match gagal.', 'Verifikasi Biometrik Gagal');
                if (failedAttempts >= maxAttempts) { button.disabled = true; notify('Batas percobaan verifikasi wajah tercapai. Hubungi admin untuk bantuan.', 'Batas Percobaan Tercapai'); } else { button.disabled = false; }
            } finally { delete button.dataset.processing; button.removeAttribute('aria-busy'); if (!button.disabled) button.innerHTML = originalText; window.setTimeout(function () { hideOverlay(sessionId); }, 1800); }
        });
    });

    if (retryButton) retryButton.addEventListener('click', checkBiometricStatus);
    window.addEventListener('online', syncButtons);
    window.addEventListener('offline', syncButtons);
    await Promise.all([checkBiometricStatus(), startCamera()]);
    syncButtons();
    window.addEventListener('beforeunload', function () { window.BiometricLiveness.stopCamera(cameraStream); });
});
