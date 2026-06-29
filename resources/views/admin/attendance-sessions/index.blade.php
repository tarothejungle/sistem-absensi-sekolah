@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="ui-page-hero">
        <div>
            <h3>Manajemen Sesi Absensi</h3>
            <p>Kelola sesi pagi, siang, dan waktu check-in/check-out.</p>
        </div>

    </div>

    <div class="ui-page-action-row">
        <a href="{{ route('admin.attendance-sessions.create') }}" class="btn-add-primary">
            <i class="bi bi-plus-circle-fill"></i>
            <span>Tambah Sesi</span>
        </a>
    </div>

    @if(session('success'))
        <div class="alert alert-success">
            {{ session('success') }}
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger">
            {{ session('error') }}
        </div>
    @endif

    <div class="card">
        <div class="card-body">
            <div class="table-responsive-mobile">
            <table class="table table-bordered table-striped">
                <thead>
                    <tr>
                        <th>Nama Sesi</th>
                        <th>Jam Masuk</th>
                        <th>Jam Pulang</th>
                        <th>Toleransi</th>
                        <th>Batas Check-in</th>
                        <th>Batas Check-out</th>
                        <th>Status</th>
                        <th width="150">Aksi</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse($sessions as $session)
                        <tr>
                            <td>{{ $session->nama_sesi }}</td>
                            <td>{{ substr($session->jam_masuk, 0, 5) }}</td>
                            <td>{{ substr($session->jam_pulang, 0, 5) }}</td>
                            <td>{{ $session->toleransi_terlambat }} menit</td>
                            <td>
                                {{ substr($session->batas_check_in_mulai, 0, 5) }}
                                -
                                {{ substr($session->batas_check_in_selesai, 0, 5) }}
                            </td>
                            <td>
                                {{ substr($session->batas_check_out_mulai, 0, 5) }}
                                -
                                {{ substr($session->batas_check_out_selesai, 0, 5) }}
                            </td>
                            <td>
                                @if($session->status === 'aktif')
                                    <span class="badge bg-success">Aktif</span>
                                @else
                                    <span class="badge bg-secondary">Nonaktif</span>
                                @endif
                            </td>
                            <td>
                                <a href="{{ route('admin.attendance-sessions.edit', $session) }}" class="btn btn-warning btn-sm">
                                    Edit
                                </a>

                                <form action="{{ route('admin.attendance-sessions.destroy', $session) }}" method="POST" style="display:inline;">
                                    @csrf
                                    @method('DELETE')

                                    <button 
                                        type="submit" 
                                        class="btn btn-danger btn-sm"
                                        onclick="return confirm('Yakin ingin menghapus sesi ini?')"
                                    >
                                        Hapus
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center">
                                Belum ada data sesi absensi.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        </div>
    </div>
</div>
@endsection