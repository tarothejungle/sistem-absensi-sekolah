(function (global) {
    'use strict';

    const MODEL_URL = global.livenessOptions?.modelUrl || '/models';
    const detectorOptions = { inputSize: 320, scoreThreshold: 0.55 };
    let modelsPromise = null;

    function csrfToken() {
        return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
            || document.querySelector('input[name="_token"]')?.value
            || '';
    }

    function setStatus(callback, message) {
        if (typeof callback === 'function') {
            callback(message);
        }
    }

    async function loadModels() {
        if (modelsPromise) {
            return modelsPromise;
        }
        if (!global.faceapi?.nets?.tinyFaceDetector || !global.faceapi?.nets?.faceLandmark68Net) {
            throw new Error('Model deteksi wajah browser belum tersedia.');
        }
        modelsPromise = Promise.all([
            global.faceapi.nets.tinyFaceDetector.loadFromUri(MODEL_URL),
            global.faceapi.nets.faceLandmark68Net.loadFromUri(MODEL_URL),
        ]);
        return modelsPromise;
    }

    async function startCamera(video) {
        if (!video) {
            throw new Error('Elemen kamera tidak tersedia.');
        }
        if (!navigator.mediaDevices?.getUserMedia) {
            throw new Error('Browser tidak mendukung akses kamera. Gunakan HTTPS atau localhost.');
        }
        if (!video.srcObject) {
            video.srcObject = await navigator.mediaDevices.getUserMedia({
                video: { facingMode: 'user', width: { ideal: 640 }, height: { ideal: 480 } },
                audio: false,
            });
        }
        video.muted = true;
        video.playsInline = true;
        await video.play();
        return video.srcObject;
    }

    function attachStream(video, stream) {
        if (!video) return;
        video.srcObject = stream;
        video.muted = true;
        video.playsInline = true;
        video.play().catch(function () {});
    }

    function stopCamera(stream) {
        if (stream?.getTracks) {
            stream.getTracks().forEach(function (track) { track.stop(); });
        }
    }

    function yawFromLandmarks(landmarks) {
        const points = landmarks.positions;
        const leftEye = points.slice(36, 42);
        const rightEye = points.slice(42, 48);
        const eyeCenter = function (eye) {
            return eye.reduce(function (acc, point) {
                return { x: acc.x + point.x / eye.length, y: acc.y + point.y / eye.length };
            }, { x: 0, y: 0 });
        };
        const left = eyeCenter(leftEye);
        const right = eyeCenter(rightEye);
        const nose = points[30];
        const eyeDistance = Math.max(1, Math.abs(right.x - left.x));
        return (nose.x - ((left.x + right.x) / 2)) / eyeDistance;
    }

    function poseMatches(step, yaw) {
        if (step === 'center') return Math.abs(yaw) <= 0.11;
        // The landmark ratio is the inverse of OpenCV solvePnP yaw for the
        // same unmirrored frame. Keep browser guidance aligned with FastAPI.
        if (step === 'turn_left') return yaw >= 0.16;
        if (step === 'turn_right') return yaw <= -0.16;
        return false;
    }

    async function detectPose(video) {
        const result = await global.faceapi
            .detectSingleFace(video, new global.faceapi.TinyFaceDetectorOptions(detectorOptions))
            .withFaceLandmarks();
        if (!result) return null;
        return yawFromLandmarks(result.landmarks);
    }

    function captureFrame(video, canvas) {
        if (!video.videoWidth || !video.videoHeight) {
            throw new Error('Kamera belum siap. Tunggu sampai gambar kamera muncul.');
        }
        const maxWidth = 640;
        const scale = Math.min(1, maxWidth / video.videoWidth);
        canvas.width = Math.round(video.videoWidth * scale);
        canvas.height = Math.round(video.videoHeight * scale);
        const context = canvas.getContext('2d', { alpha: false });
        context.drawImage(video, 0, 0, canvas.width, canvas.height);
        return canvas.toDataURL('image/jpeg', 0.78);
    }

    function wait(milliseconds) {
        return new Promise(function (resolve) { window.setTimeout(resolve, milliseconds); });
    }

    async function waitForPose(video, step, onStatus, isFinalCenter) {
        const deadline = Date.now() + 15000;
        let stable = 0;
        let lastMessage = '';
        while (Date.now() < deadline) {
            const yaw = await detectPose(video).catch(function () { return null; });
            if (yaw === null) {
                stable = 0;
                lastMessage = 'Pastikan tepat satu wajah terlihat jelas.';
            } else if (poseMatches(step, yaw)) {
                stable += 1;
                lastMessage = step === 'center'
                    ? (isFinalCenter ? 'Tahan posisi lurus seperti langkah pertama...' : 'Tahan wajah lurus...')
                    : step === 'turn_left' ? 'Tahan posisi menoleh kiri...' : 'Tahan posisi menoleh kanan...';
            } else {
                stable = 0;
                lastMessage = step === 'center'
                    ? (isFinalCenter ? 'Kembalikan wajah lurus ke kamera.' : 'Hadapkan wajah lurus ke kamera.')
                    : step === 'turn_left' ? 'Ikuti instruksi: menoleh ke kiri.' : 'Ikuti instruksi: menoleh ke kanan.';
            }
            setStatus(onStatus, lastMessage);
            if (stable >= 3) return;
            await wait(180);
        }
        throw new Error('Gerakan liveness belum terbaca. Pastikan pencahayaan cukup lalu coba lagi.');
    }

    async function requestChallenge(purpose, sessionId) {
        const payload = { purpose: purpose };
        if (sessionId) payload.attendance_session_id = Number(sessionId);
        const response = await fetch(global.livenessOptions.challengeUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify(payload),
        });
        const result = await response.json().catch(function () { return {}; });
        if (!response.ok || !result.challenge_id || !Array.isArray(result.steps)) {
            throw new Error(result.message || 'Challenge liveness tidak dapat dibuat.');
        }
        return result;
    }

    async function run(options) {
        const video = options.video;
        const canvas = options.canvas || document.createElement('canvas');
        const frames = [];
        await loadModels();
        await startCamera(video);
        const challenge = await requestChallenge(options.purpose, options.sessionId);

        for (let index = 0; index < challenge.steps.length; index += 1) {
            const step = challenge.steps[index];
            const isFinalCenter = step === 'center' && index === challenge.steps.length - 1;
            const label = step === 'center'
                ? (isFinalCenter ? 'kembali lurus ke kamera' : 'lurus ke kamera')
                : step === 'turn_left' ? 'menoleh ke kiri' : 'menoleh ke kanan';
            setStatus(options.onStatus, 'Langkah ' + (index + 1) + '/' + challenge.steps.length + ': ' + label + '.');
            if (typeof options.onStep === 'function') options.onStep(index, step, 'active');
            await waitForPose(video, step, options.onStatus, isFinalCenter);
            frames.push(captureFrame(video, canvas));
            if (typeof options.onStep === 'function') options.onStep(index, step, 'complete');
            await wait(350);
        }

        return { challengeId: challenge.challenge_id, frames: frames };
    }

    global.BiometricLiveness = {
        loadModels: loadModels,
        startCamera: startCamera,
        attachStream: attachStream,
        stopCamera: stopCamera,
        captureFrame: captureFrame,
        run: run,
    };
}(window));
