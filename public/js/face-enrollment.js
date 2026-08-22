(function () {
    'use strict';

    function init() {
        const form = document.getElementById('face-enrollment-form');
        if (!form) return;

        const video = document.getElementById('face-enrollment-video');
        const canvas = document.getElementById('face-enrollment-canvas');
        const captureButton = document.getElementById('face-enrollment-capture');
        const status = document.getElementById('face-enrollment-status');
        const overlay = document.getElementById('face-enrollment-overlay');
        const overlayStatus = document.getElementById('face-enrollment-overlay-status');
        const challengeInput = document.getElementById('liveness-challenge-id');
        const statusUrl = form.dataset.statusUrl;
        const spinner = '<span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span>';
        let serviceReady = false;
        let processing = false;
        let stream = null;

        function setStatus(message, type) {
            status.textContent = message;
            status.className = 'alert mt-3 mb-3 alert-' + (type || 'secondary');
        }

        function showOverlay(message, state) {
            overlay.className = 'face-enrollment-overlay is-visible' + (state ? ' is-' + state : '');
            overlayStatus.textContent = message;
        }

        function hideOverlay() {
            overlay.className = 'face-enrollment-overlay';
        }

        function updateButton() {
            captureButton.disabled = processing || !serviceReady || !stream;
        }

        async function checkService() {
            setStatus('Memeriksa FastAPI, InsightFace, dan active liveness...', 'secondary');
            try {
                const response = await fetch(statusUrl, {
                    credentials: 'same-origin',
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                });
                const payload = await response.json().catch(function () { return {}; });
                const service = payload.service || {};
                serviceReady = response.ok
                    && service.ready === true
                    && service.liveness_ready === true
                    && service.liveness_method === 'active_head_pose_v1';
                if (!serviceReady) {
                    const message = service.ready === true && service.liveness_ready !== true
                        ? 'FastAPI siap untuk face match, tetapi active liveness enrollment belum siap.'
                        : payload.message || service.message || 'Layanan liveness belum siap. Coba lagi setelah FastAPI siap.';
                    setStatus(message, 'danger');
                    updateButton();
                    return false;
                }
                setStatus('FastAPI, InsightFace, dan active liveness siap. Ikuti instruksi gerakan kepala untuk melanjutkan.', 'success');
                updateButton();
                return true;
            } catch (error) {
                serviceReady = false;
                setStatus('FastAPI tidak dapat dihubungi. Pastikan container face-api berjalan lalu coba cek ulang.', 'danger');
                updateButton();
                return false;
            }
        }

        async function startCamera() {
            try {
                stream = await window.BiometricLiveness.startCamera(video);
                updateButton();
            } catch (error) {
                setStatus(error.message || 'Kamera tidak dapat diakses. Izinkan kamera untuk melanjutkan.', 'danger');
            }
        }

        async function submitEnrollment(liveness) {
            const data = new FormData(form);
            data.set('liveness_challenge_id', liveness.challengeId);
            liveness.frames.forEach(function (frame, index) {
                data.append('liveness_frames[' + index + ']', frame);
            });
            const response = await fetch(form.action, {
                method: 'POST',
                body: data,
                credentials: 'same-origin',
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            });
            const payload = await response.json().catch(function () { return {}; });
            if (!response.ok || payload.ok !== true) {
                throw new Error(payload.message || 'Enrollment liveness gagal diproses.');
            }
            return payload;
        }

        async function runEnrollment() {
            if (processing) return;
            if (!serviceReady && !await checkService()) return;
            if (!stream) {
                await startCamera();
                if (!stream) return;
            }

            processing = true;
            updateButton();
            captureButton.setAttribute('aria-busy', 'true');
            challengeInput.value = '';
            try {
                captureButton.innerHTML = spinner + ' Menjalankan liveness...';
                showOverlay('Menyiapkan challenge liveness...', 'scanning');
                const liveness = await window.BiometricLiveness.run({
                    purpose: 'enrollment',
                    video: video,
                    canvas: canvas,
                    onStatus: function (message) {
                        showOverlay(message, 'scanning');
                    },
                });
                challengeInput.value = liveness.challengeId;
                captureButton.innerHTML = spinner + ' Membuat template...';
                showOverlay('FastAPI memvalidasi liveness dan membuat template wajah...', 'scanning');
                const payload = await submitEnrollment(liveness);
                liveness.frames.length = 0;
                showOverlay('Liveness berhasil. Template wajah telah dikunci.', 'success');
                setStatus(payload.message || 'Wajah berhasil didaftarkan dan siap dipakai untuk absensi.', 'success');
                window.setTimeout(function () { window.location.reload(); }, 900);
            } catch (error) {
                showOverlay(error.message || 'Liveness gagal.', 'error');
                setStatus(error.message || 'Enrollment liveness gagal. Mulai ulang challenge untuk mencoba lagi.', 'danger');
                challengeInput.value = '';
                processing = false;
                captureButton.removeAttribute('aria-busy');
                captureButton.innerHTML = '<i class="bi bi-person-bounding-box me-1"></i> Mulai Liveness';
                updateButton();
                window.setTimeout(hideOverlay, 1800);
            }
        }

        captureButton.addEventListener('click', runEnrollment);
        form.addEventListener('submit', function (event) { event.preventDefault(); runEnrollment(); });
        window.addEventListener('beforeunload', function () { window.BiometricLiveness.stopCamera(stream); });

        Promise.all([checkService(), startCamera()]).then(updateButton);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
}());
