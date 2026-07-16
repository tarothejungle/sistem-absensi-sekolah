@php
    $guruBelumAbsen = $guruBelumAbsen ?? collect();
    $missingAttendanceStatusMap = [
        'alfa' => ['label' => 'Alfa / Tidak Hadir', 'class' => 'bg-danger'],
    ];
@endphp

<div class="card mt-4">
    <div class="card-body">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
            <div class="ui-section-title mb-0">
                <i class="bi bi-person-exclamation"></i>
                <span>Guru Belum Absen Hari Ini</span>
            </div>
            <span class="badge {{ $guruBelumAbsen->isNotEmpty() ? 'bg-danger' : 'bg-success' }}">
                {{ $guruBelumAbsen->count() }} guru
            </span>
        </div>

        @if($guruBelumAbsen->isNotEmpty())
            <div class="table-responsive-mobile">
                <table class="table table-bordered align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Nama</th>
                            <th>Username</th>
                            <th>Sesi Absensi</th>
                            <th>Keterangan</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($guruBelumAbsen as $teacher)
                            <tr>
                                <td>{{ $teacher->nama_lengkap ?? '-' }}</td>
                                <td>{{ $teacher->user->nip ?? '-' }}</td>
                                <td>
                                    @if($teacher->attendanceSessions && $teacher->attendanceSessions->count() > 0)
                                        <div class="d-flex flex-wrap gap-2">
                                            @foreach($teacher->attendanceSessions as $session)
                                                <span class="badge bg-primary">
                                                    {{ $session->nama_sesi }}
                                                    @if($session->jam_masuk && $session->jam_pulang)
                                                        <span class="ms-1 fw-normal">
                                                            {{ substr($session->jam_masuk, 0, 5) }} - {{ substr($session->jam_pulang, 0, 5) }}
                                                        </span>
                                                    @endif
                                                </span>
                                            @endforeach
                                        </div>
                                    @else
                                        <span class="text-muted">Sesi belum diatur</span>
                                    @endif
                                </td>
                                <td>
                                    @php
                                        $statusInfo = $missingAttendanceStatusMap[$teacher->missing_attendance_status] ?? [
                                            'label' => 'Belum absen masuk',
                                            'class' => 'bg-danger',
                                        ];
                                    @endphp
                                    <span class="badge {{ $statusInfo['class'] }}">{{ $statusInfo['label'] }}</span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="ui-empty-state">
                <i class="bi bi-check2-circle"></i>
                <div class="fw-bold">Semua guru wajib absen sudah punya catatan hari ini.</div>
            </div>
        @endif
    </div>
</div>
