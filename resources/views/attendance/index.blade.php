@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="dashboard-hero d-flex justify-content-between align-items-center flex-wrap gap-3">
        <div>
            <h2>Absensi Guru</h2>
            <p>Lakukan check-in dan check-out sesuai sesi serta jadwal hari mengajar.</p>
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

                        <video
                            id="video-{{ $session->id }}"
                            class="w-100 border rounded camera-box attendance-video mt-3"
                            autoplay
                            playsinline
                            muted
                            style="transform: scaleX(-1); -webkit-transform: scaleX(-1); object-fit: cover; min-height: 240px; background:#111;"
                        ></video>

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

                        <form method="POST" action="{{ route('attendance.checkin') }}" class="mt-3 absensi-form">
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

                        <form method="POST" action="{{ route('attendance.checkout') }}" class="mt-2 absensi-form">
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

                    @include('partials.per-page-selector', ['paginator' => $attendances])
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
    <script src="{{ asset('js/attendance.js') }}?v=4.0.5"></script>
@endpush
