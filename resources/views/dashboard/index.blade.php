@extends('layouts.app')

@section('content')
@php
    $statusBadgeMap = [
        'hadir' => ['label' => 'Hadir', 'class' => 'bg-success'],
        'terlambat' => ['label' => 'Terlambat', 'class' => 'bg-warning text-dark'],
        'izin' => ['label' => 'Izin', 'class' => 'bg-info text-dark'],
        'sakit' => ['label' => 'Sakit', 'class' => 'bg-primary'],
        'cuti' => ['label' => 'Cuti', 'class' => 'bg-secondary'],
        'tugas_luar' => ['label' => 'Tugas Luar', 'class' => 'bg-dark'],
        'alfa' => ['label' => 'Alfa / Tidak Hadir', 'class' => 'bg-danger'],
        'hadir_tidak_lengkap' => ['label' => 'Hadir Tidak Lengkap', 'class' => 'bg-secondary'],
    ];
@endphp
<div class="container-fluid">

    @if(auth()->user()->hasAttendanceAccess())
        @php
            $user = auth()->user();
            $namaGuru = $teacher->nama_lengkap ?? $user->name ?? $user->username;
            $profilePhoto = $user->profile_photo ? route('profile.photo.show', $user) : null;
            $initials = collect(explode(' ', $namaGuru))
                ->filter()
                ->take(2)
                ->map(fn($word) => strtoupper(substr($word, 0, 1)))
                ->implode('');
        @endphp

        <div class="dashboard-hero dashboard-welcome-card">
            <div class="dashboard-hero-main">
                <div class="dashboard-hero-icon">
                    <i class="bi bi-building-check"></i>
                </div>
                <div>
                    <h2>Dashboard</h2>
                    <p>Ringkasan profil dan aktivitas absensi akun Anda.</p>
                    <div class="dashboard-meta-pills">
                        <span><i class="bi bi-calendar3"></i> {{ now()->translatedFormat('d F Y') }}</span>
                        <span><i class="bi bi-clock"></i> {{ now()->format('H:i') }} WIB</span>
                    </div>
                </div>
            </div>
            <div class="dashboard-hero-summary">
                <div class="dashboard-summary-title">Aktivitas Hari Ini</div>
                <div class="dashboard-summary-row">
                    <span>Profil</span>
                    @if($profileCompleteness['is_complete'] ?? false)
                        <strong class="text-success">Lengkap</strong>
                    @else
                        <strong class="text-warning">Belum Lengkap</strong>
                    @endif
                </div>
                @if(auth()->user()->role === 'kepala_sekolah')
                    <div class="dashboard-summary-row">
                        <span>Belum Absen</span>
                        <strong>{{ $belumAbsenHariIni ?? 0 }}</strong>
                    </div>
                @endif
            </div>
        </div>

        @if(!($profileCompleteness['is_complete'] ?? true) && !empty($profileCompleteness['missing']))
            @php
                $userMissing = collect($profileCompleteness['missing'])
                    ->where('editable_by_user', true)
                    ->pluck('label')
                    ->all();
                $adminMissing = collect($profileCompleteness['missing'])
                    ->where('editable_by_user', false)
                    ->pluck('label')
                    ->all();
            @endphp
            <div class="dashboard-profile-alert d-flex align-items-start gap-3 mt-3 mb-3" role="alert">
                <i class="bi bi-exclamation-triangle-fill fs-4 dashboard-profile-alert-icon"></i>
                <div class="flex-grow-1">
                    <div class="fw-bold mb-1">Profil Anda belum lengkap</div>
                    @if($userMissing)
                        <div>Data yang dapat Anda lengkapi: <strong>{{ implode(', ', $userMissing) }}.</strong></div>
                        <div class="small mt-1">Gunakan tombol <strong>Ubah Profil</strong> pada kartu profil.</div>
                    @endif
                    @if($adminMissing)
                        <div class="small {{ $userMissing ? 'mt-2 pt-2 border-top' : 'mt-1' }}">
                            Data yang perlu diatur admin: {{ implode(', ', $adminMissing) }}.
                        </div>
                    @endif
                </div>
            </div>
        @endif

        <div class="row g-4">
            <div class="col-lg-4">
                <div class="card h-100">
                    <div class="profile-modern">
                        @if($profilePhoto)
                            <img src="{{ $profilePhoto }}" alt="Foto Profil" class="profile-avatar-modern">
                        @else
                            <div class="profile-avatar-modern">{{ $initials ?: 'G' }}</div>
                        @endif

                        <h4 class="fw-bold mb-1">{{ $teacher->nama_lengkap ?? '-' }}</h4>
                        <div class="text-muted mb-1">{{ $teacher->jabatan ?? '-' }}</div>
                        <div class="small text-secondary mb-4">{{ $user->instansi_mengajar ?? 'MI Lantaburo' }}</div>

                        <div class="d-grid gap-2">
                            <a href="{{ route('profile.edit') }}" class="btn btn-primary">
                                <i class="bi bi-pencil-square"></i> Ubah Profil
                            </a>

                            @if(auth()->user()->profile_photo)
                                <form
                                    action="{{ route('profile.photo.delete') }}"
                                    method="POST"
                                    data-confirm-action="true"
                                    data-confirm-type="danger"
                                    data-confirm-icon="bi-image"
                                    data-confirm-title="Hapus foto profil?"
                                    data-confirm-message="Foto profil akun Anda akan dihapus dan avatar inisial akan digunakan kembali."
                                    data-confirm-submit="Hapus Foto"
                                >
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-danger w-100">
                                        <i class="bi bi-trash"></i> Hapus Foto
                                    </button>
                                </form>
                            @else
                                <button type="button" class="btn btn-outline-secondary w-100" disabled>
                                    <i class="bi bi-image"></i> Belum Ada Foto
                                </button>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-8">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="ui-section-title">
                            <i class="bi bi-person-lines-fill"></i>
                            <span>Data Diri</span>
                        </div>

                        <div class="info-list-modern">
                            <div class="info-row"><div class="info-label">Nama Lengkap</div><div class="info-value">{{ $teacher->nama_lengkap ?? '-' }}</div></div>
                            <div class="info-row"><div class="info-label">Username</div><div class="info-value">{{ $user->username ?? '-' }}</div></div>
                            <div class="info-row"><div class="info-label">Tempat, Tanggal Lahir</div><div class="info-value">
                                @if($user->tempat_lahir || $user->tanggal_lahir)
                                    {{ $user->tempat_lahir ?? '-' }}
                                    @if($user->tanggal_lahir), {{ \Carbon\Carbon::parse($user->tanggal_lahir)->translatedFormat('d F Y') }} @endif
                                @else
                                    -
                                @endif
                            </div></div>
                            <div class="info-row"><div class="info-label">Pendidikan Terakhir</div><div class="info-value">{{ $user->pendidikan_terakhir ?? '-' }}</div></div>
                            <div class="info-row"><div class="info-label">Jenis Kelamin</div><div class="info-value">{{ $teacher->jenis_kelamin ?? '-' }}</div></div>
                            <div class="info-row"><div class="info-label">No HP</div><div class="info-value">{{ $teacher->no_hp ?? '-' }}</div></div>
                            <div class="info-row"><div class="info-label">Email</div><div class="info-value">{{ $user->email ?? '-' }}</div></div>
                            <div class="info-row"><div class="info-label">Mata Pelajaran</div><div class="info-value">{{ $teacher->mata_pelajaran ?? '-' }}</div></div>
                            <div class="info-row">
                                <div class="info-label">Sesi Absensi</div>
                                <div class="info-value">
                                    @if($teacher && $teacher->attendanceSessions && $teacher->attendanceSessions->count() > 0)
                                        <div class="d-flex flex-column gap-3">
                                            @foreach($teacher->attendanceSessions as $session)
                                                @php
                                                    $window = $sessionWindows[$session->id] ?? null;
                                                @endphp
                                                <div class="profile-session-panel">
                                                    <div class="fw-bold mb-2" style="color: var(--ref-blue-strong);">
                                                        <i class="bi bi-clock-history me-1"></i> {{ $session->nama_sesi }}
                                                    </div>
                                                    <div class="d-grid gap-1 small">
                                                        <div><span class="text-muted">Jadwal kerja:</span> <span class="fw-semibold">{{ substr($session->jam_masuk, 0, 5) }} - {{ substr($session->jam_pulang, 0, 5) }}</span></div>
                                                        <div><span class="text-muted">Check-in tersedia:</span> <span class="fw-semibold">{{ substr($session->batas_check_in_mulai, 0, 5) }} - {{ substr($session->batas_check_in_selesai, 0, 5) }}</span></div>
                                                        @if($window)
                                                            <div><span class="text-muted">Batas hadir tepat waktu:</span> <span class="fw-semibold text-success">{{ $window['batas_tepat_waktu']->format('H:i') }}</span> <span class="text-muted">({{ $window['toleransi_menit'] }} menit)</span></div>
                                                        @endif
                                                        <div><span class="text-muted">Check-out tersedia:</span> <span class="fw-semibold">{{ substr($session->batas_check_out_mulai, 0, 5) }} - {{ substr($session->batas_check_out_selesai, 0, 5) }}</span></div>
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    @else
                                        -
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        @if(auth()->user()->role === 'kepala_sekolah')
            @include('dashboard.partials.missing-attendance-teachers', [
                'guruBelumAbsen' => $guruBelumAbsenHariIni ?? collect(),
            ])

            <div class="card mt-4">
                <div class="card-body">
                    <div class="ui-section-title">
                        <i class="bi bi-calendar-check"></i>
                        <span>Rekap Kehadiran Hari Ini</span>
                    </div>

                    <div class="table-responsive-mobile">
                        <table class="table table-bordered table-striped align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Nama</th>
                                    <th>Username</th>
                                    <th>Masuk</th>
                                    <th>Pulang</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($rekapHariIni ?? [] as $attendance)
                                    <tr>
                                        <td>{{ $attendance->teacher->nama_lengkap ?? '-' }}</td>
                                        <td>{{ $attendance->teacher->user->username ?? '-' }}</td>
                                        <td>{{ $attendance->check_in_time ? \Carbon\Carbon::parse($attendance->check_in_time)->format('H:i') : '-' }}</td>
                                        <td>{{ $attendance->check_out_time ? \Carbon\Carbon::parse($attendance->check_out_time)->format('H:i') : '-' }}</td>
                                        <td>
                                            @php
                                                $statusInfo = $statusBadgeMap[$attendance->status_kehadiran] ?? [
                                                    'label' => ucfirst(str_replace('_', ' ', $attendance->status_kehadiran ?? '-')),
                                                    'class' => 'bg-secondary',
                                                ];
                                            @endphp
                                            <span class="badge {{ $statusInfo['class'] }}">
                                                {{ $statusInfo['label'] }}
                                            </span>
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="5" class="text-center text-muted">Belum ada data absensi hari ini.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @endif

    @else
        <div class="dashboard-hero dashboard-welcome-card">
            <div class="dashboard-hero-main">
                <div class="dashboard-hero-icon">
                    <i class="bi bi-building-check"></i>
                </div>
                <div>
                    <h2>Dashboard</h2>
                    <p>Monitoring ringkas kehadiran guru dan aktivitas sistem hari ini.</p>
                    <div class="dashboard-meta-pills">
                        <span><i class="bi bi-calendar3"></i> {{ now()->translatedFormat('d F Y') }}</span>
                        <span><i class="bi bi-clock"></i> {{ now()->format('H:i') }} WIB</span>
                    </div>
                </div>
            </div>
            <div class="dashboard-hero-summary">
                <div class="dashboard-summary-title">Ringkasan Hari Ini</div>
                <div class="dashboard-summary-row">
                    <span>Total Guru</span>
                    <strong>{{ $totalGuru ?? 0 }}</strong>
                </div>
                <div class="dashboard-summary-row">
                    <span>Hadir Lengkap</span>
                    <strong>{{ $hadirHariIni ?? 0 }}</strong>
                </div>
                <div class="dashboard-summary-row">
                    <span>Belum Absen</span>
                    <strong>{{ $belumAbsenHariIni ?? 0 }}</strong>
                </div>
            </div>
        </div>

        <div class="row g-4 mb-4">
            <div class="col-md-6 col-xl"><div class="metric-card metric-blue"><div class="metric-icon"><i class="bi bi-people-fill"></i></div><div class="metric-label">Total Guru</div><div class="metric-value">{{ $totalGuru ?? 0 }}</div></div></div>
            <div class="col-md-6 col-xl"><div class="metric-card metric-green"><div class="metric-icon"><i class="bi bi-check-circle-fill"></i></div><div class="metric-label">Hadir Lengkap</div><div class="metric-value">{{ $hadirHariIni ?? 0 }}</div></div></div>
            <div class="col-md-6 col-xl"><div class="metric-card metric-yellow"><div class="metric-icon"><i class="bi bi-clock-history"></i></div><div class="metric-label">Terlambat</div><div class="metric-value">{{ $terlambatHariIni ?? 0 }}</div></div></div>
            <div class="col-md-6 col-xl"><div class="metric-card metric-cyan"><div class="metric-icon"><i class="bi bi-hourglass-split"></i></div><div class="metric-label">Tidak Lengkap</div><div class="metric-value">{{ $tidakLengkapHariIni ?? 0 }}</div></div></div>
            <div class="col-md-6 col-xl"><div class="metric-card metric-red"><div class="metric-icon"><i class="bi bi-exclamation-triangle-fill"></i></div><div class="metric-label">Belum Absen</div><div class="metric-value">{{ $belumAbsenHariIni ?? 0 }}</div></div></div>
        </div>

        @include('dashboard.partials.missing-attendance-teachers', [
            'guruBelumAbsen' => $guruBelumAbsenHariIni ?? collect(),
        ])

        <div class="card mt-4">
            <div class="card-body">
                <div class="ui-section-title">
                    <i class="bi bi-clipboard2-data"></i>
                    <span>Rekap Kehadiran Hari Ini</span>
                </div>

                @if(isset($rekapHariIni) && $rekapHariIni->count())
                    <div class="table-responsive-mobile">
                        <table class="table table-bordered align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Nama</th><th>Username</th><th>Masuk</th><th>Pulang</th><th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($rekapHariIni as $item)
                                    <tr>
                                        <td>{{ $item->teacher->nama_lengkap ?? '-' }}</td>
                                        <td>{{ $item->teacher->user->username ?? '-' }}</td>
                                        <td>{{ $item->check_in_time ? \Carbon\Carbon::parse($item->check_in_time)->format('H:i') : '-' }}</td>
                                        <td>{{ $item->check_out_time ? \Carbon\Carbon::parse($item->check_out_time)->format('H:i') : '-' }}</td>
                                        <td>
                                            @php
                                                $statusInfo = $statusBadgeMap[$item->status_kehadiran] ?? [
                                                    'label' => ucfirst(str_replace('_', ' ', $item->status_kehadiran ?? '-')),
                                                    'class' => 'bg-secondary',
                                                ];
                                            @endphp
                                            <span class="badge {{ $statusInfo['class'] }}">
                                                {{ $statusInfo['label'] }}
                                            </span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="ui-empty-state">
                        <i class="bi bi-calendar-x"></i>
                        <div class="fw-bold">Belum ada data absensi hari ini.</div>
                    </div>
                @endif
            </div>
        </div>
    @endif

    @if(auth()->user()->role === 'super_admin')
        <div class="card mt-4">
            <div class="card-body">
                <div class="ui-section-title">
                    <i class="bi bi-activity"></i>
                    <span>Aktivitas Login User</span>
                </div>

                <div class="table-responsive-mobile">
                    <table class="table table-bordered table-striped align-middle mb-0">
                        <thead>
                            <tr><th>User</th><th>Level</th><th>Aktivitas</th><th>Waktu</th></tr>
                        </thead>
                        <tbody>
                            @forelse(($loginActivities ?? collect()) as $activity)
                                <tr>
                                    <td>{{ $activity->name ?? '-' }}</td>
                                    <td>
                                        @php
                                            $activityRoleClass = match ($activity->role) {
                                                'super_admin' => 'bg-primary',
                                                'kepala_sekolah' => 'bg-primary',
                                                'guru', 'bendahara' => 'bg-secondary',
                                                'tenaga_kependidikan' => 'bg-secondary',
                                                default => 'bg-secondary',
                                            };
                                        @endphp
                                        <span class="badge {{ $activityRoleClass }}">
                                            {{ \App\Models\Teacher::dataGuruRoleLabel($activity->role) }}
                                        </span>
                                    </td>
                                    <td>{{ $activity->activity ?? 'login kedalam aplikasi' }}</td>
                                    <td><strong>{{ $activity->created_at->diffForHumans() }}</strong><br><small class="text-muted">{{ $activity->created_at->format('d/m/Y H:i') }}</small></td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-center text-muted">Belum ada aktivitas login.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif
</div>
@endsection
