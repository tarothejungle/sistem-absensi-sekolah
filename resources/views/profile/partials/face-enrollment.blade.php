@php
    $teacher = $user->teacher;
    $enrollment = $teacher?->faceEnrollment;
    $canEnroll = !$enrollment;
@endphp

<div class="card ui-form-card mt-4">
    <div class="card-body">
        <div class="ui-form-section-head mb-3">
            <span class="ui-form-section-icon"><i class="bi bi-person-bounding-box"></i></span>
            <div>
                <h5 class="ui-form-section-title">Wajah Absensi</h5>
                <p class="ui-form-section-subtitle">Selesaikan active liveness dengan gerakan kepala sebelum template InsightFace 512 dimensi dikunci sebagai rujukan absensi.</p>
            </div>
        </div>

        @if($enrollment)
            <div class="alert {{ $enrollment->isCompatible() ? 'alert-success' : 'alert-warning' }}">
                <div class="fw-bold mb-1">
                    <i class="bi {{ $enrollment->isCompatible() ? 'bi-check-circle' : 'bi-exclamation-triangle' }} me-1"></i>
                    {{ $enrollment->isCompatible() ? 'Wajah sudah terdaftar dan dikunci.' : 'Template wajah terkunci tetapi perlu didaftarkan ulang.' }}
                </div>
                <div class="small">
                    Template dikunci sejak {{ $enrollment->enrolled_at?->format('d/m/Y H:i') }}.
                    Hubungi super admin untuk reset apabila template perlu diganti.
                </div>
            </div>
        @else
            <div class="alert alert-info">
                <i class="bi bi-info-circle me-1"></i>
                Wajah belum terdaftar. Selesaikan challenge liveness untuk mendaftarkan template wajah.
            </div>
        @endif

        @if($canEnroll)
            <form method="POST" action="{{ route('profile.face-enrollment.store') }}" id="face-enrollment-form" data-status-url="{{ route('biometric.status') }}">
                @csrf
                <input type="hidden" name="liveness_challenge_id" id="liveness-challenge-id">

                <div class="row g-3 align-items-stretch">
                    <div class="col-lg-6">
                        <div class="face-enrollment-camera">
                            <video id="face-enrollment-video" autoplay playsinline muted></video>
                            <div id="face-enrollment-overlay" class="face-enrollment-overlay" aria-live="polite">
                                <div class="enrollment-scan-frame" aria-hidden="true">
                                    <span class="enrollment-corner top-left"></span>
                                    <span class="enrollment-corner top-right"></span>
                                    <span class="enrollment-corner bottom-left"></span>
                                    <span class="enrollment-corner bottom-right"></span>
                                </div>
                                <div class="enrollment-scan-line" aria-hidden="true"></div>
                                <div id="face-enrollment-countdown" class="enrollment-countdown" aria-hidden="true"></div>
                                <div id="face-enrollment-overlay-status" class="enrollment-overlay-status">Bersiap memindai wajah...</div>
                            </div>
                        </div>
                        <canvas id="face-enrollment-canvas" class="d-none"></canvas>
                    </div>

                    <div class="col-lg-6">
                        <div class="face-enrollment-preview-empty" aria-live="polite">
                            <i class="bi bi-shield-check"></i>
                            <strong>Active liveness wajib</strong>
                            <span>Ikuti empat instruksi: lurus, menoleh, menoleh ke sisi lain, lalu lurus kembali.</span>
                        </div>
                    </div>
                </div>

                <div id="face-enrollment-status" class="alert alert-secondary mt-3 mb-3" role="status">Memeriksa FastAPI dan model InsightFace...</div>

                <div class="d-flex flex-wrap gap-2">
                    <button type="button" id="face-enrollment-capture" class="btn btn-primary" disabled>
                        <i class="bi bi-person-bounding-box me-1"></i> Mulai Liveness
                    </button>
                </div>
                <small class="text-muted d-block mt-2">Frame challenge dipakai sekali untuk verifikasi dan tidak disimpan. Setelah template dibuat, pendaftaran ulang memerlukan reset super admin.</small>
            </form>
        @else
            <div class="alert alert-danger mb-0">
                <i class="bi bi-lock me-1"></i>
                Template wajah sudah dikunci. Hubungi super admin untuk reset jika perlu mendaftarkan ulang.
            </div>
        @endif
    </div>
</div>

@push('styles')
<style>
.face-enrollment-camera,
.face-enrollment-preview-empty {
    width: 100%;
    min-height: 280px;
    border-radius: 0.85rem;
}

.face-enrollment-camera {
    position: relative;
    overflow: hidden;
    border: 1px solid var(--bs-border-color);
    background: #0b1220;
}

.face-enrollment-camera video {
    display: block;
    width: 100%;
    height: 280px;
    object-fit: cover;
}

.face-enrollment-camera video { transform: scaleX(-1); }

.face-enrollment-preview-empty {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 0.75rem;
    border: 1px dashed var(--bs-border-color);
    background: var(--bs-tertiary-bg);
    color: var(--bs-secondary-color);
    text-align: center;
}

.face-enrollment-preview-empty i { font-size: 2rem; }

.face-enrollment-overlay {
    position: absolute;
    inset: 0;
    z-index: 2;
    display: none;
    align-items: center;
    justify-content: center;
    background: rgba(4, 10, 20, 0.48);
}

.face-enrollment-overlay.is-visible { display: flex; }
.enrollment-scan-frame { position: absolute; inset: 14% 20%; }
.enrollment-corner { position: absolute; width: 42px; height: 42px; border: 3px solid #60a5fa; filter: drop-shadow(0 0 5px rgba(96, 165, 250, 0.75)); }
.enrollment-corner.top-left { top: 0; left: 0; border-right: 0; border-bottom: 0; border-radius: 8px 0 0; }
.enrollment-corner.top-right { top: 0; right: 0; border-left: 0; border-bottom: 0; border-radius: 0 8px 0 0; }
.enrollment-corner.bottom-left { bottom: 0; left: 0; border-right: 0; border-top: 0; border-radius: 0 0 0 8px; }
.enrollment-corner.bottom-right { right: 0; bottom: 0; border-left: 0; border-top: 0; border-radius: 0 0 8px; }
.enrollment-scan-line { position: absolute; top: 20%; left: 20%; display: none; width: 60%; height: 2px; background: linear-gradient(90deg, transparent, #60a5fa 18%, #fff 50%, #60a5fa 82%, transparent); box-shadow: 0 0 14px rgba(96, 165, 250, 0.95); }
.face-enrollment-overlay.is-scanning .enrollment-scan-line { display: block; animation: enrollmentScan 1.8s ease-in-out infinite; }
.enrollment-countdown { color: #fff; font-size: clamp(4rem, 12vw, 6rem); font-weight: 800; line-height: 1; text-shadow: 0 0 24px rgba(96, 165, 250, 0.9); }
.enrollment-countdown.is-pulsing { animation: enrollmentCountdown 900ms ease-out; }
.enrollment-overlay-status { position: absolute; right: 16px; bottom: 16px; left: 16px; padding: 0.55rem 0.9rem; border: 1px solid rgba(255, 255, 255, 0.16); border-radius: 999px; background: rgba(4, 10, 20, 0.82); color: #fff; font-size: 0.85rem; font-weight: 600; text-align: center; }
.face-enrollment-overlay.is-error { background: rgba(127, 29, 29, 0.48); }
.face-enrollment-overlay.is-error .enrollment-corner { border-color: #f87171; }

@keyframes enrollmentScan { 0%, 100% { top: 20%; opacity: 0.4; } 50% { top: 78%; opacity: 1; } }
@keyframes enrollmentCountdown { 0% { opacity: 0; transform: scale(0.55); } 55% { opacity: 1; transform: scale(1.18); } 100% { opacity: 1; transform: scale(1); } }

@media (prefers-reduced-motion: reduce) {
    .face-enrollment-overlay.is-scanning .enrollment-scan-line,
    .enrollment-countdown.is-pulsing { animation: none; }
}

</style>
@endpush

@push('scripts')
<script nonce='{{ request()->attributes->get('csp_nonce') }}'>
    window.livenessOptions = {
        challengeUrl: @json(route('biometric.liveness.challenges.store')),
        modelUrl: @json(asset('models')),
    };
</script>
<script nonce='{{ request()->attributes->get('csp_nonce') }}' src="{{ asset('js/face-api.min.js') }}?v=0.20.0-full1"></script>
<script nonce='{{ request()->attributes->get('csp_nonce') }}' src="{{ asset('js/liveness.js') }}?v=1.1.0"></script>
<script nonce='{{ request()->attributes->get('csp_nonce') }}' src="{{ asset('js/face-enrollment.js') }}?v=6.0.0"></script>
@endpush
