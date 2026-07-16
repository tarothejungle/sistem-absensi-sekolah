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
            <i class="bi bi-plus-lg"></i>
            <span>Tambah Sesi</span>
        </a>

        <form
            id="sessionBulkDeleteForm"
            action="{{ route('admin.attendance-sessions.bulk-destroy') }}"
            method="POST"
            class="bulk-action-form"
            data-confirm-action="true"
            data-confirm-type="danger"
            data-confirm-icon="bi-clock-history"
            data-confirm-title="Hapus sesi terpilih?"
            data-confirm-message="Semua sesi absensi yang dicentang akan dihapus jika belum dipakai oleh data guru."
            data-confirm-submit="Hapus Terpilih"
        >
            @csrf
            @method('DELETE')
            <button type="submit" class="btn btn-danger" data-bulk-delete-button form="sessionBulkDeleteForm" disabled>
                <i class="bi bi-trash3"></i>
                <span>Hapus Terpilih</span>
            </button>
        </form>
    </div>

    <div class="card">
        <div class="card-body">
            <div data-bulk-selection-form data-bulk-delete-target="sessionBulkDeleteForm">
                <div class="table-responsive-mobile">
                <table class="table table-bordered table-striped">
                    <thead>
                        <tr>
                            <th width="44">
                                <input type="checkbox" class="form-check-input" data-check-all aria-label="Pilih semua sesi absensi">
                            </th>
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
                                <td>
                                    <input type="checkbox" name="session_ids[]" value="{{ $session->id }}" class="form-check-input" data-row-check>
                                </td>
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
                                    <form action="{{ route('admin.attendance-sessions.toggle', $session) }}" method="POST" class="m-0" data-no-loading="true">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="status-switch {{ $session->status === 'aktif' ? 'is-active' : '' }}" aria-label="Ubah status sesi absensi">
                                            <span></span>
                                        </button>
                                    </form>
                                    <small class="d-block mt-1 {{ $session->status === 'aktif' ? 'text-success' : 'text-muted' }}">
                                        {{ $session->status === 'aktif' ? 'Aktif' : 'Nonaktif' }}
                                    </small>
                                </td>
                                <td>
                                    <a href="{{ route('admin.attendance-sessions.edit', $session) }}" class="btn btn-warning btn-sm">
                                        Edit
                                    </a>
 
                                    <form
                                        action="{{ route('admin.attendance-sessions.destroy', $session) }}"
                                        method="POST"
                                        class="d-inline"
                                        data-confirm-action="true"
                                        data-confirm-type="danger"
                                        data-confirm-icon="bi-clock-history"
                                        data-confirm-title="Hapus sesi absensi?"
                                        data-confirm-message="Sesi {{ $session->nama_sesi }} akan dihapus jika belum digunakan oleh data guru."
                                        data-confirm-submit="Hapus Sesi"
                                    >
                                        @csrf
                                        @method('DELETE')
 
                                        <button type="submit" class="btn btn-danger btn-sm">
                                            Hapus
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="text-center">
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
</div>
@endsection
