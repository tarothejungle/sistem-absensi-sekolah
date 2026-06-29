@extends('layouts.app')

@section('content')
@php
    $dayLabels = [
        'senin' => 'Senin',
        'selasa' => 'Selasa',
        'rabu' => 'Rabu',
        'kamis' => 'Kamis',
        'jumat' => 'Jumat',
        'sabtu' => 'Sabtu',
        'minggu' => 'Minggu',
    ];
@endphp

<style>
    .table-footer-row {
        display: flex !important;
        align-items: center !important;
        justify-content: space-between !important;
        gap: 16px !important;
        padding-top: 16px !important;
        margin-top: 16px !important;
        border-top: 1px solid rgba(15, 23, 42, 0.08) !important;
        flex-wrap: wrap !important;
    }

    .per-page-form-custom {
        display: inline-flex !important;
        align-items: center !important;
        gap: 8px !important;
        width: auto !important;
        margin: 0 !important;
        flex-wrap: nowrap !important;
    }

    .per-page-label-custom {
        font-size: 14px !important;
        color: #475569 !important;
        font-weight: 700 !important;
        white-space: nowrap !important;
    }

    .per-page-select-custom {
        width: 74px !important;
        min-width: 74px !important;
        max-width: 74px !important;
        height: 40px !important;
        border-radius: 14px !important;
        border: 1px solid #d7e3f5 !important;
        background: #fff !important;
        padding: 6px 12px !important;
        font-weight: 800 !important;
        color: #0f172a !important;
        outline: none !important;
    }

    .pagination-wrapper {
        display: flex !important;
        justify-content: flex-end !important;
        align-items: center !important;
        flex: 1 !important;
    }

    .pagination-wrapper nav {
        display: flex !important;
        justify-content: flex-end !important;
        width: 100% !important;
    }

    .pagination-wrapper nav > div:first-child,
    .pagination-wrapper nav p,
    .pagination-wrapper .small,
    .pagination-wrapper .text-muted {
        display: none !important;
    }

    .pagination-wrapper .pagination {
        margin-bottom: 0 !important;
    }

    @media (max-width: 768px) {
        .table-footer-row {
            flex-direction: column !important;
            align-items: flex-start !important;
        }

        .pagination-wrapper {
            width: 100% !important;
            justify-content: flex-start !important;
        }

        .pagination-wrapper nav {
            justify-content: flex-start !important;
        }

        .per-page-form-custom {
            flex-wrap: wrap !important;
        }
    }
</style>

<div class="container-fluid">
    <div class="ui-page-hero">
        <h3>Data Guru</h3>
        <p>Kelola data guru, sesi absensi, dan hari absensi sesuai jadwal mengajar.</p>
    </div>

    <div class="ui-toolbar">
        <div class="ui-actions">
            <a href="{{ route('admin.teachers.create') }}" class="btn btn-primary">
                <i class="bi bi-plus-circle-fill me-1"></i> Tambah Guru
            </a>

            <a href="{{ route('admin.teachers.export.excel', request()->query()) }}" class="btn btn-success">
                <i class="bi bi-file-earmark-excel-fill me-1"></i> Export Excel
            </a>

            <a href="{{ route('admin.teachers.export.pdf', request()->query()) }}" class="btn btn-danger" target="_blank">
                <i class="bi bi-file-earmark-pdf-fill me-1"></i> Export PDF
            </a>
        </div>

        <form action="{{ route('admin.teachers') }}" method="GET" class="d-flex gap-2" style="max-width: 330px; width:100%;">
            <input type="text" name="keyword" class="form-control" placeholder="Cari nama guru..." value="{{ request('keyword') }}">
            <button type="submit" class="btn btn-primary"><i class="bi bi-search"></i></button>
            @if(request('keyword'))
                <a href="{{ route('admin.teachers') }}" class="btn btn-outline-secondary"><i class="bi bi-x-lg"></i></a>
            @endif
        </form>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="table-responsive-mobile">
                <table class="table table-bordered table-striped align-middle">
                    <thead>
                        <tr>
                            <th>Username</th>
                            <th>Nama</th>
                            <th>Jabatan</th>
                            <th>Mapel</th>
                            <th>Sesi Absensi</th>
                            <th>Hari Absensi</th>
                            <th width="140">Aksi</th>
                        </tr>
                    </thead>

                    <tbody>
                        @forelse($teachers as $teacher)
                            <tr>
                                <td>{{ $teacher->user->nip ?? '-' }}</td>
                                <td class="fw-semibold">{{ $teacher->nama_lengkap ?? '-' }}</td>
                                <td>{{ $teacher->jabatan ?? '-' }}</td>
                                <td>{{ $teacher->mata_pelajaran ?? '-' }}</td>
                                <td>
                                    @if($teacher->attendanceSessions && $teacher->attendanceSessions->count() > 0)
                                        @foreach($teacher->attendanceSessions as $session)
                                            <div class="mb-1">
                                                <span class="badge bg-primary">{{ $session->nama_sesi }}</span>
                                                <small class="text-muted d-block">
                                                    {{ substr($session->jam_masuk, 0, 5) }} - {{ substr($session->jam_pulang, 0, 5) }}
                                                </small>
                                            </div>
                                        @endforeach
                                    @else
                                        <span class="badge bg-secondary">Belum diatur</span>
                                    @endif
                                </td>
                                <td>
                                    @php
                                        $activeDays = $teacher->schedules
                                            ? $teacher->schedules->where('status', 'aktif')->pluck('hari')->toArray()
                                            : [];
                                    @endphp

                                    @forelse($activeDays as $day)
                                        <span class="badge bg-light text-dark border mb-1">{{ $dayLabels[$day] ?? ucfirst($day) }}</span>
                                    @empty
                                        <span class="badge bg-secondary">Belum diatur</span>
                                    @endforelse
                                </td>
                                <td>
                                    <div class="d-flex gap-1 flex-wrap">
                                        <a href="{{ route('admin.teachers.edit', $teacher) }}" class="btn btn-warning btn-sm">Edit</a>

                                        <form action="{{ route('admin.teachers.delete', $teacher) }}" method="POST" onsubmit="return confirm('Yakin ingin menghapus data guru ini?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-danger btn-sm">Hapus</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7">
                                    <div class="ui-empty-state">
                                        <i class="bi bi-person-x"></i>
                                        <div class="fw-bold">Belum ada data guru.</div>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-3">
                {{ $teachers->links('pagination::bootstrap-5') }}
            </div>
            </div>
         </div>
    </div>
</div>
@endsection
