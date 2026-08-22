@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="dashboard-hero d-flex justify-content-between align-items-center flex-wrap gap-3">
        <div>
            <h2>Absensi Sekolah</h2>
            <p>Lakukan check-in dan check-out sesuai sesi, jadwal mengajar, atau jadwal piket yang berlaku.</p>
        </div>

        <div class="hero-action ms-auto">
            <span class="hero-date-badge">
                <i class="bi bi-calendar-check"></i>
                {{ now()->translatedFormat('l, d F Y') }}
            </span>
        </div>
    </div>

    <div
        id='biometric-readiness'
        class='biometric-readiness mb-4'
        data-status-url='{{ route('biometric.status') }}'
        data-profile-url='{{ route('profile.edit') }}'
        data-enrollment-compatible='{{ (int) $faceEnrollmentCompatible }}'
    >
        <div class='biometric-readiness-icon' aria-hidden='true'>
            <i class='bi bi-person-bounding-box'></i>
        </div>
        <div class='flex-grow-1'>
            <div class='d-flex align-items-center flex-wrap gap-2 mb-1'>
                <strong>Verifikasi biometrik wajib</strong>
                <span id='biometric-service-badge' class='badge bg-secondary'>Memeriksa FastAPI...</span>
                <span class='badge {{ $faceEnrollmentCompatible ? 'bg-success' : 'bg-warning text-dark' }}'>
                    {{ $faceEnrollmentCompatible ? 'Wajah terdaftar' : 'Enrollment diperlukan' }}
                </span>
            </div>
            <p id='biometric-readiness-message' class='mb-0'>
                Sistem sedang memeriksa container FastAPI dan model InsightFace buffalo_l.
            </p>
            <small id='biometric-model-version' class='text-muted'>Pencocokan dilakukan server-side; foto saja tidak cukup untuk menyimpan absensi.</small>
        </div>
        <div class='d-flex flex-wrap gap-2'>
            <button type='button' id='biometric-status-retry' class='btn btn-outline-primary btn-sm'>
                <i class='bi bi-arrow-clockwise me-1'></i> Cek Ulang
            </button>
            @if(!$faceEnrollmentCompatible)
                <a href='{{ route('profile.edit') }}' class='btn btn-primary btn-sm'>Daftarkan Wajah</a>
            @endif
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-5">
            @if($scheduleMessage ?? false)
                <div class="ui-empty-state">
                    <i class="bi bi-calendar-x"></i>
                    <h5 class="fw-bold mb-2">Tidak Ada Jadwal Absensi Hari Ini</h5>
                    <p class="mb-0">{{ $scheduleMessage }}</p>
                </div>
            @elseif(!$faceEnrollment)
                <div class="ui-empty-state">
                    <i class="bi bi-person-x"></i>
                    <h5 class="fw-bold mb-2">Wajah Belum Terdaftar</h5>
                    <p class="mb-3">Daftarkan template wajah dengan active liveness melalui profil sebelum menggunakan face match untuk absensi.</p>
                    <a href="{{ route('profile.edit') }}" class="btn btn-primary btn-sm">Buka Pendaftaran Wajah</a>
                </div>
            @elseif(!$faceEnrollmentCompatible)
                <div class="ui-empty-state">
                    <i class="bi bi-exclamation-triangle"></i>
                    <h5 class="fw-bold mb-2">Template Wajah Tidak Kompatibel</h5>
                    <p class="mb-3">Template lama tidak cocok dengan model InsightFace aktif. Lakukan enrollment ulang sebelum absensi.</p>
                    <a href="{{ route('profile.edit') }}" class="btn btn-primary btn-sm">Perbarui Template Wajah</a>
                </div>
            @elseif($activeSession)
                @php
                    $session = $activeSession;
                    $today = $session->todayAttendance;
                @endphp

                <div class="card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                            <div>
                                <h5 class="fw-bold mb-1">{{ $session->nama_sesi }}</h5>
                                <div class="text-muted">
                                    Jadwal:
                                    <strong class="text-dark">
                                        {{ substr($session->jam_masuk, 0, 5) }} - {{ substr($session->jam_pulang, 0, 5) }}
                                    </strong>
                                </div>
                                <div class="text-muted">
                                    Tanggal absensi: <strong class="text-dark">{{ $session->attendanceDate->format('d/m/Y') }}</strong>
                                </div>
                                <span class="badge bg-primary mt-2">
                                    <i class="bi bi-shield-check me-1"></i> InsightFace 1:1 wajib
                                </span>
                            </div>
                            @if(!$today || !$today->check_in_time)
                                <span class="badge bg-secondary">Belum check-in</span>
                            @elseif($today->check_in_time && !$today->check_out_time)
                                <span class="badge bg-warning text-dark">Proses</span>
                            @else
                                <span class="badge bg-success">Selesai</span>
                            @endif
                        </div>

                        @if($session->temporaryLeaveGrace['is_grace_active'] ?? false)
                            <div class="alert alert-info py-2" role="status">
                                <strong>Izin sementara disetujui.</strong>
                                Check-in sesi ini mendapat grace sampai
                                <strong>{{ $session->temporaryLeaveGrace['grace_deadline']->format('H:i:s') }}</strong>.
                            </div>
                        @endif

                        <div class="position-relative mt-3">
                            <video
                                id="video-{{ $session->id }}"
                                class="w-100 border rounded camera-box attendance-video"
                                autoplay
                                playsinline
                                muted
                                style="transform: scaleX(-1); -webkit-transform: scaleX(-1); object-fit: cover; min-height: 240px; background:#111;"
                            ></video>

                            <div id="overlay-{{ $session->id }}" class="face-scan-overlay" style="display: none;">
                                <div class="scan-frame">
                                    <div class="corner-bracket top-left"></div>
                                    <div class="corner-bracket top-right"></div>
                                    <div class="corner-bracket bottom-left"></div>
                                    <div class="corner-bracket bottom-right"></div>
                                </div>
                                <div class="scan-line"></div>
                                <div class="scan-status-label"></div>
                                <div class="scan-countdown"></div>
                            </div>
                        </div>

                        <canvas id="canvas-{{ $session->id }}" class="attendance-canvas" style="display: none"></canvas>

                        <div class="biometric-steps mt-3" aria-label="Tahapan verifikasi absensi">
                            <span class="biometric-step" data-biometric-step="gps"><i class="bi bi-geo-alt"></i> GPS</span>
                            @if(config('biometrics.liveness_required'))
                                <span class="biometric-step" data-biometric-step="liveness"><i class="bi bi-person-video3"></i> Liveness</span>
                            @endif
                            <span class="biometric-step" data-biometric-step="verify"><i class="bi bi-person-check"></i> Face match</span>
                            <span class="biometric-step" data-biometric-step="save"><i class="bi bi-database-check"></i> Simpan</span>
                        </div>

                        @if($today && $today->check_in_face_photo)
                            <small class="text-muted d-block mt-2">Foto check-in sudah tersimpan.</small>
                        @endif

                        <div class="row g-3 my-2">
                            <div class="col-6">
                                <div class="ui-soft-panel">
                                    <small class="text-muted">Masuk</small>
                                    <div class="fw-bold fs-5">{{ $today && $today->check_in_time ? $today->check_in_time->format('H:i') : '-' }}</div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="ui-soft-panel">
                                    <small class="text-muted">Pulang</small>
                                    <div class="fw-bold fs-5">{{ $today && $today->check_out_time ? $today->check_out_time->format('H:i') : '-' }}</div>
                                </div>
                            </div>
                        </div>

                        <form method="POST" action="{{ route('attendance.checkin') }}" class="mt-3 absensi-form" data-no-loading="true" data-action="check_in" data-action-enabled="{{ $session->canCheckIn ? '1' : '0' }}">
                            @csrf
                            <input type="hidden" name="attendance_session_id" value="{{ $session->id }}">
                            <input type="hidden" name="latitude">
                            <input type="hidden" name="longitude">
                            <input type="hidden" name="accuracy">
                            <button type="submit" class="btn btn-success w-100" data-pwa-online-required {{ $session->canCheckIn ? '' : 'disabled' }}>
                                <i class="bi bi-box-arrow-in-right me-1"></i> Check-in {{ $session->nama_sesi }}
                            </button>
                        </form>

                        <form method="POST" action="{{ route('attendance.checkout') }}" class="mt-2 absensi-form" data-no-loading="true" data-action="check_out" data-action-enabled="{{ $session->canCheckOut ? '1' : '0' }}">
                            @csrf
                            <input type="hidden" name="attendance_session_id" value="{{ $session->id }}">
                            <input type="hidden" name="latitude">
                            <input type="hidden" name="longitude">
                            <input type="hidden" name="accuracy">
                            <button type="submit" class="btn btn-danger w-100" data-pwa-online-required {{ $session->canCheckOut ? '' : 'disabled' }}>
                                <i class="bi bi-box-arrow-right me-1"></i> Check-out {{ $session->nama_sesi }}
                            </button>
                        </form>

                        <small class="text-muted d-block mt-2">GPS, enrollment kompatibel, {{ config('biometrics.liveness_required') ? 'active liveness, dan ' : '' }}face match FastAPI harus berhasil.</small>
                    </div>
                </div>
            @else
                <div class="ui-empty-state">
                    <i class="bi bi-calendar-x"></i>
                    <h5 class="fw-bold mb-2">Belum Ada Sesi Absensi</h5>
                    <p class="mb-0">Belum ada sesi absensi aktif untuk akun ini.</p>
                </div>
            @endif
        </div>

        <div class="col-lg-7">
            <div class="card">
                <div class="card-body">
                    <h5 class="fw-bold mb-3">Riwayat Absensi</h5>
                    <div class="table-responsive-mobile">
                        <table class="table table-bordered table-striped align-middle">
                            <thead>
                                <tr>
                                    <th>Tanggal</th>
                                    <th>Sesi</th>
                                    <th>Masuk</th>
                                    <th>Pulang</th>
                                    <th>Status</th>
                                    <th>Verifikasi</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($attendances as $attendance)
                                    <tr>
                                        <td>{{ $attendance->tanggal->format('d/m/Y') }}</td>
                                        <td>{{ $attendance->attendanceSession->nama_sesi ?? '-' }}</td>
                                        <td>{{ $attendance->check_in_time?->format('H:i') ?? '-' }}</td>
                                        <td>{{ $attendance->check_out_time?->format('H:i') ?? '-' }}</td>
                                        <td>
                                            @if($attendance->status_kehadiran === 'terlambat')
                                                <span class="badge bg-warning text-dark">Terlambat {{ $attendance->keterlambatan_menit }} menit</span>
                                            @elseif($attendance->status_kehadiran === 'hadir')
                                                <span class="badge bg-success">Hadir</span>
                                            @elseif($attendance->status_kehadiran === 'hadir_tidak_lengkap')
                                                <span class="badge bg-secondary">Hadir Tidak Lengkap</span>
                                            @else
                                                <span class="badge bg-secondary">{{ ucfirst(str_replace('_', ' ', $attendance->status_kehadiran)) }}</span>
                                            @endif
                                            @if($attendance->temporaryLeaveRequest)
                                                <span class="badge bg-info text-dark">Grace izin sementara #{{ $attendance->temporaryLeaveRequest->id }}</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if($attendance->verification_method === 'face_match_fastapi')
                                                <span class="badge bg-success"><i class="bi bi-person-check me-1"></i> Face match FastAPI</span>
                                                @if($attendance->check_in_face_match_score !== null)
                                                    <small class="d-block text-muted mt-1">Distance masuk: {{ number_format((float) $attendance->check_in_face_match_score, 4) }}</small>
                                                @endif
                                                @if($attendance->check_out_face_match_score !== null)
                                                    <small class="d-block text-muted">Distance pulang: {{ number_format((float) $attendance->check_out_face_match_score, 4) }}</small>
                                                @endif
                                            @elseif($attendance->verification_method === 'face_liveness_fastapi')
                                                <span class="badge bg-success"><i class="bi bi-shield-check me-1"></i> Liveness + biometrik</span>
                                                @if($attendance->check_in_face_match_score !== null)
                                                    <small class="d-block text-muted mt-1">Distance masuk: {{ number_format((float) $attendance->check_in_face_match_score, 4) }}</small>
                                                @endif
                                                @if($attendance->check_out_face_match_score !== null)
                                                    <small class="d-block text-muted">Distance pulang: {{ number_format((float) $attendance->check_out_face_match_score, 4) }}</small>
                                                @endif
                                            @else
                                                <span class="badge bg-secondary">Data lama</span>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="text-center text-muted">Belum ada riwayat absensi.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    @if($attendances->hasPages())
                        <div class="mt-3" aria-label="Navigasi riwayat absensi">
                            {{ $attendances->links() }}
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
.biometric-readiness {
    display: flex;
    align-items: center;
    gap: 16px;
    padding: 18px;
    border: 1px solid var(--ref-line, #cbd5e1);
    border-radius: 16px;
    background: linear-gradient(135deg, var(--ref-surface, #ffffff), var(--ref-surface-soft, #eef5ff));
    color: var(--ref-text, #0f172a);
    box-shadow: var(--ref-shadow-soft, 0 10px 26px rgba(15, 23, 42, .06));
}

.biometric-readiness strong,
.biometric-readiness p {
    color: inherit;
}

.biometric-readiness.is-ready {
    border-color: #86efac;
    background: linear-gradient(135deg, #f0fdf4, #ecfeff);
}

.biometric-readiness.is-error {
    border-color: #fca5a5;
    background: linear-gradient(135deg, #fff7ed, #fef2f2);
}

.biometric-readiness-icon {
    width: 48px;
    height: 48px;
    border-radius: 14px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex: 0 0 auto;
    color: #fff;
    background: linear-gradient(135deg, #123264, var(--ref-blue-strong, #1769ff));
    font-size: 24px;
}

.biometric-steps {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 8px;
}

.biometric-step {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    padding: 8px 6px;
    border-radius: 10px;
    background: var(--ref-surface-soft, #eef2f7);
    color: var(--ref-muted, #64748b);
    border: 1px solid var(--ref-line, transparent);
    font-size: 12px;
    font-weight: 700;
}

.biometric-step.is-active { background: #dbeafe; color: #1d4ed8; }
.biometric-step.is-complete { background: #dcfce7; color: #166534; }
.biometric-step.is-error { background: #fee2e2; color: #991b1b; }

html[data-theme="dark"] .biometric-readiness {
    background: linear-gradient(135deg, var(--ref-surface, #162235), var(--ref-surface-soft, #101a29));
    border-color: var(--ref-line, #26374f);
    color: var(--ref-text, #e8eef8);
}

html[data-theme="dark"] .biometric-readiness.is-ready {
    background: linear-gradient(135deg, rgba(55, 200, 120, .14), rgba(64, 203, 228, .08));
    border-color: rgba(55, 200, 120, .48);
}

html[data-theme="dark"] .biometric-readiness.is-error {
    background: linear-gradient(135deg, rgba(255, 159, 67, .12), rgba(255, 100, 120, .10));
    border-color: rgba(255, 100, 120, .48);
}

html[data-theme="dark"] .biometric-readiness-icon {
    background: linear-gradient(135deg, #2563eb, var(--ref-blue-strong, #72a6ff));
}

html[data-theme="dark"] .biometric-readiness .badge.bg-warning {
    color: #1f2937 !important;
}

html[data-theme="dark"] .biometric-step {
    background: var(--ref-surface-soft, #101a29);
    border-color: var(--ref-line, #26374f);
    color: var(--ref-muted, #9aa8bb);
}

html[data-theme="dark"] .biometric-step.is-active {
    background: rgba(76, 141, 255, .18);
    border-color: rgba(114, 166, 255, .38);
    color: #a9c9ff;
}

html[data-theme="dark"] .biometric-step.is-complete {
    background: rgba(55, 200, 120, .16);
    border-color: rgba(55, 200, 120, .36);
    color: #8ce7b2;
}

html[data-theme="dark"] .biometric-step.is-error {
    background: rgba(255, 100, 120, .16);
    border-color: rgba(255, 100, 120, .38);
    color: #ffabb7;
}

@media (max-width: 767px) {
    .biometric-readiness { align-items: flex-start; flex-wrap: wrap; }
    .biometric-steps { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}

.face-scan-overlay {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.3);
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    border-radius: 0.375rem;
    z-index: 10;
}

.scan-frame {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    width: 70%;
    height: 70%;
    pointer-events: none;
}

.corner-bracket {
    position: absolute;
    width: 40px;
    height: 40px;
    border: 3px solid #10b981;
    border-radius: 4px;
}

.corner-bracket.top-left {
    top: 0;
    left: 0;
    border-right: none;
    border-bottom: none;
}

.corner-bracket.top-right {
    top: 0;
    right: 0;
    border-left: none;
    border-bottom: none;
}

.corner-bracket.bottom-left {
    bottom: 0;
    left: 0;
    border-right: none;
    border-top: none;
}

.corner-bracket.bottom-right {
    bottom: 0;
    right: 0;
    border-left: none;
    border-top: none;
}

.scan-line {
    position: absolute;
    width: 70%;
    height: 2px;
    background: linear-gradient(90deg, transparent, #10b981, transparent);
    box-shadow: 0 0 10px #10b981;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    animation: scanAnimation 2s ease-in-out infinite;
    display: none;
}

.face-scan-overlay.scanning .scan-line {
    display: block;
}

@keyframes scanAnimation {
    0%, 100% {
        transform: translate(-50%, calc(-50% - 100px));
        opacity: 0.3;
    }
    50% {
        transform: translate(-50%, calc(-50% + 100px));
        opacity: 1;
    }
}

.scan-status-label {
    position: absolute;
    bottom: 20px;
    left: 50%;
    transform: translateX(-50%);
    background: rgba(0, 0, 0, 0.8);
    color: white;
    padding: 8px 16px;
    border-radius: 20px;
    font-size: 14px;
    font-weight: 500;
    white-space: nowrap;
    display: none;
}

.face-scan-overlay .scan-status-label {
    display: block;
}

.scan-countdown {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    font-size: 72px;
    font-weight: bold;
    color: #10b981;
    text-shadow: 0 0 20px rgba(16, 185, 129, 0.8);
    animation: countdownPulse 1s ease-in-out;
    display: none;
}

.face-scan-overlay.countdown .scan-countdown {
    display: block;
}

@keyframes countdownPulse {
    0% {
        transform: translate(-50%, -50%) scale(0.5);
        opacity: 0;
    }
    50% {
        transform: translate(-50%, -50%) scale(1.2);
        opacity: 1;
    }
    100% {
        transform: translate(-50%, -50%) scale(1);
        opacity: 1;
    }
}

.face-scan-overlay.success {
    background: rgba(16, 185, 129, 0.4);
}

.face-scan-overlay.success .corner-bracket {
    border-color: #10b981;
}

.face-scan-overlay.error {
    background: rgba(239, 68, 68, 0.4);
}

.face-scan-overlay.error .corner-bracket {
    border-color: #ef4444;
}
</style>
@endpush

@push('scripts')
    <script nonce='{{ request()->attributes->get('csp_nonce') }}'>
        window.attendanceFaceOptions = {
            maxAttempts: 3,
            statusUrl: @json(route('biometric.status')),
            challengeUrl: @json(route('biometric.liveness.challenges.store')),
            livenessRequired: @json((bool) config('biometrics.liveness_required')),
        };
        window.livenessOptions = {
            challengeUrl: @json(route('biometric.liveness.challenges.store')),
            modelUrl: @json(asset('models')),
        };
    </script>
    <script nonce='{{ request()->attributes->get('csp_nonce') }}' src="{{ asset('js/liveness.js') }}?v=1.2.0"></script>
    <script nonce='{{ request()->attributes->get('csp_nonce') }}' src="{{ asset('js/attendance.js') }}?v=9.0.0"></script>
@endpush
