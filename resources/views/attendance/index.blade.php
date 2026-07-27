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
                    <p class="mb-3">Daftarkan wajah melalui profil sebelum melakukan absensi.</p>
                    <a href="{{ route('profile.edit') }}" class="btn btn-primary btn-sm">Buka Pendaftaran Wajah</a>
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
                            </div>
                            @if(!$today || !$today->check_in_time)
                                <span class="badge bg-secondary">Belum check-in</span>
                            @elseif($today->check_in_time && !$today->check_out_time)
                                <span class="badge bg-warning text-dark">Proses</span>
                            @else
                                <span class="badge bg-success">Selesai</span>
                            @endif
                        </div>

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

                        <form method="POST" action="{{ route('attendance.checkin') }}" class="mt-3 absensi-form" data-no-loading="true">
                            @csrf
                            <input type="hidden" name="attendance_session_id" value="{{ $session->id }}">
                            <input type="hidden" name="latitude">
                            <input type="hidden" name="longitude">
                            <input type="hidden" name="accuracy">
                            <input type="hidden" name="face_image">
                            <button type="submit" class="btn btn-success w-100" {{ $session->canCheckIn ? '' : 'disabled' }}>
                                <i class="bi bi-box-arrow-in-right me-1"></i> Check-in {{ $session->nama_sesi }}
                            </button>
                        </form>

                        <form method="POST" action="{{ route('attendance.checkout') }}" class="mt-2 absensi-form" data-no-loading="true">
                            @csrf
                            <input type="hidden" name="attendance_session_id" value="{{ $session->id }}">
                            <input type="hidden" name="latitude">
                            <input type="hidden" name="longitude">
                            <input type="hidden" name="accuracy">
                            <input type="hidden" name="face_image">
                            <button type="submit" class="btn btn-danger w-100" {{ $session->canCheckOut ? '' : 'disabled' }}>
                                <i class="bi bi-box-arrow-right me-1"></i> Check-out {{ $session->nama_sesi }}
                            </button>
                        </form>

                        <small class="text-muted d-block mt-2">Pastikan GPS dan kamera browser diizinkan.</small>
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
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="text-center text-muted">Belum ada riwayat absensi.</td>
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
    <script>
        window.attendanceFaceOptions = { maxAttempts: 3 };
    </script>
    <script src="{{ asset('js/attendance.js') }}?v=6.0.0"></script>
@endpush
