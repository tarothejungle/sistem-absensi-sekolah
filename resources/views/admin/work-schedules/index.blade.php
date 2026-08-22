@extends('layouts.app')

@section('content')
@php
    $days = [
        'senin' => 'Senin', 'selasa' => 'Selasa', 'rabu' => 'Rabu', 'kamis' => 'Kamis',
        'jumat' => 'Jumat', 'sabtu' => 'Sabtu', 'minggu' => 'Minggu',
    ];
    $selected = $selectedTeacher?->schedules
        ?->where('status', 'aktif')
        ->groupBy('hari')
        ->map(fn ($rows) => $rows->pluck('attendance_session_id')->map(fn ($id) => (int) $id)->all()) ?? collect();
@endphp
<div class="container-fluid">
    <div class="ui-page-hero">
        <div>
            <h3>Setting Jadwal Kerja</h3>
            <p>Petakan hari kerja dan sesi wajib setiap guru/karyawan. Jam mengikuti Setting Sesi.</p>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger">
            <strong>Jadwal belum bisa disimpan</strong>
            <ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('admin.work-schedules.index') }}" class="row g-3 align-items-end">
                <div class="col-md-9">
                    <label class="form-label">Guru/Karyawan</label>
                    <select name="teacher_id" class="form-select" required>
                        @foreach($teachers as $teacher)
                            <option value="{{ $teacher->id }}" @selected($selectedTeacher?->id === $teacher->id)>
                                {{ $teacher->nama_lengkap }} - {{ \App\Models\Teacher::dataGuruRoleLabel($teacher->user?->role) }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3"><button class="btn btn-primary w-100">Tampilkan Jadwal</button></div>
            </form>
        </div>
    </div>

    @if($selectedTeacher)
        <form method="POST" action="{{ route('admin.work-schedules.update', $selectedTeacher) }}" class="card">
            @csrf @method('PUT')
            <div class="card-body">
                <h5 class="mb-3"><i class="bi bi-calendar-week"></i> Jadwal {{ $selectedTeacher->nama_lengkap }}</h5>
                @if($sessions->isEmpty())
                    <div class="alert alert-warning mb-0">Belum ada sesi aktif. Buat sesi lebih dulu pada Setting Sesi dan Jam Absensi.</div>
                @else
                    <div class="table-responsive">
                        <table class="table table-bordered align-middle">
                            <thead><tr><th>Hari</th>@foreach($sessions as $session)<th>{{ $session->nama_sesi }}<small class="d-block fw-normal">{{ substr($session->jam_masuk, 0, 5) }}-{{ substr($session->jam_pulang, 0, 5) }}</small></th>@endforeach</tr></thead>
                            <tbody>
                            @foreach($days as $day => $label)
                                <tr>
                                    <th>{{ $label }}</th>
                                    @foreach($sessions as $session)
                                        <td class="text-center">
                                            <input class="form-check-input" type="checkbox" name="assignments[{{ $day }}][]" value="{{ $session->id }}"
                                                @checked(in_array($session->id, old("assignments.$day", $selected->get($day, []))))>
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="ui-form-actions">
                        <button type="submit" class="btn-add-primary">
                            <i class="bi bi-save"></i>
                            <span>Simpan Jadwal Kerja</span>
                        </button>
                    </div>
                @endif
            </div>
        </form>
    @else
        <div class="alert alert-warning">Belum ada guru/karyawan aktif.</div>
    @endif

    <div class="card mt-4">
        <div class="card-body">
            <h5 class="mb-3"><i class="bi bi-people"></i> Ringkasan Jadwal</h5>
            <div class="table-responsive"><table class="table table-striped align-middle mb-0">
                <thead><tr><th>Nama</th><th>Role</th><th>Assignment Aktif</th><th>Status</th><th>Aksi</th></tr></thead>
                <tbody>@foreach($teachers as $teacher)<tr>
                    <td>{{ $teacher->nama_lengkap }}</td><td>{{ \App\Models\Teacher::dataGuruRoleLabel($teacher->user?->role) }}</td>
                    <td>{{ $teacher->schedules->where('status', 'aktif')->whereNotNull('attendance_session_id')->count() }}</td>
                    <td>@if($teacher->schedules->where('status', 'aktif')->whereNotNull('attendance_session_id')->isEmpty())<span class="badge bg-warning text-dark">Belum diatur</span>@else<span class="badge bg-success">Terjadwal</span>@endif</td>
                    <td><a class="btn btn-sm btn-outline-primary" href="{{ route('admin.work-schedules.index', ['teacher_id' => $teacher->id]) }}">Atur</a></td>
                </tr>@endforeach</tbody>
            </table></div>
        </div>
    </div>
</div>
@endsection
