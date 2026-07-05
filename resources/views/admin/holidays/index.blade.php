@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="ui-page-hero">
        <div>
            <h3>Setting Hari Libur</h3>
            <p>Atur tanggal libur agar sistem tidak membuat status alfa untuk jadwal reguler.</p>
        </div>
    </div>

    @if($errors->any())
        <div class="ui-error-summary">
            <strong><i class="bi bi-exclamation-circle"></i> Data belum bisa disimpan</strong>
            <ul class="mb-0">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form action="{{ route('admin.holidays.store') }}" method="POST" class="card ui-form-card mb-4">
        @csrf
        <div class="card-body">
            <div class="ui-form-section">
                <div class="ui-form-section-head">
                    <span class="ui-form-section-icon"><i class="bi bi-calendar-plus"></i></span>
                    <div>
                        <h5 class="ui-form-section-title">Tambah Hari Libur</h5>
                        <p class="ui-form-section-subtitle">Tanggal aktif akan dikecualikan dari kewajiban absen reguler.</p>
                    </div>
                </div>

                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Tanggal Libur</label>
                        <input type="date" name="tanggal" class="form-control" value="{{ old('tanggal') }}" required>
                    </div>
                    <div class="col-md-5">
                        <label class="form-label">Nama Libur</label>
                        <input type="text" name="nama_libur" class="form-control" value="{{ old('nama_libur') }}" placeholder="Contoh: Libur semester" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label d-block">Status</label>
                        <input type="hidden" name="status" value="nonaktif">
                        <label class="form-switch-card">
                            <input type="checkbox" name="status" value="aktif" @checked(old('status', 'aktif') === 'aktif')>
                            <span class="form-switch-visual"></span>
                            <span>Aktif</span>
                        </label>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Keterangan</label>
                        <textarea name="keterangan" class="form-control" rows="3" placeholder="Catatan tambahan bila diperlukan">{{ old('keterangan') }}</textarea>
                    </div>
                </div>
            </div>

            <div class="ui-form-actions">
                <button type="submit" class="btn-add-primary">
                    <i class="bi bi-save"></i>
                    <span>Simpan Hari Libur</span>
                </button>
            </div>
        </div>
    </form>

    <div class="card">
        <div class="card-body">
            <div class="ui-section-title">
                <i class="bi bi-calendar-x"></i>
                <span>Daftar Hari Libur</span>
            </div>

            <div class="table-responsive-mobile">
                <table class="table table-bordered table-striped align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Tanggal</th>
                            <th>Nama Libur</th>
                            <th>Status</th>
                            <th>Keterangan</th>
                            <th width="170">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($holidays as $holiday)
                            <tr>
                                <td>
                                    <span class="holiday-date-badge">
                                        <i class="bi bi-calendar-event"></i>
                                        {{ $holiday->tanggal->translatedFormat('d F Y') }}
                                    </span>
                                </td>
                                <td class="fw-bold">{{ $holiday->nama_libur }}</td>
                                <td>
                                    <form action="{{ route('admin.holidays.toggle', $holiday) }}" method="POST" class="m-0" data-no-loading="true">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="status-switch {{ $holiday->status === 'aktif' ? 'is-active' : '' }}" aria-label="Ubah status hari libur">
                                            <span></span>
                                        </button>
                                    </form>
                                    <small class="d-block mt-1 {{ $holiday->status === 'aktif' ? 'text-success' : 'text-muted' }}">
                                        {{ $holiday->status === 'aktif' ? 'Aktif' : 'Nonaktif' }}
                                    </small>
                                </td>
                                <td>{{ $holiday->keterangan ?: '-' }}</td>
                                <td>
                                    <div class="ui-inline-actions">
                                        <button type="button" class="btn btn-warning btn-sm" data-bs-toggle="modal" data-bs-target="#holidayEditModal{{ $holiday->id }}">
                                            Edit
                                        </button>

                                        <form
                                            action="{{ route('admin.holidays.destroy', $holiday) }}"
                                            method="POST"
                                            class="d-inline"
                                            data-confirm-action="true"
                                            data-confirm-type="danger"
                                            data-confirm-icon="bi-calendar-x"
                                            data-confirm-title="Hapus hari libur?"
                                            data-confirm-message="Hari libur {{ $holiday->nama_libur }} pada {{ $holiday->tanggal->format('d/m/Y') }} akan dihapus dari sistem."
                                            data-confirm-submit="Hapus"
                                        >
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-danger btn-sm">Hapus</button>
                                        </form>
                                    </div>

                                    <div class="modal fade" id="holidayEditModal{{ $holiday->id }}" tabindex="-1" aria-hidden="true">
                                        <div class="modal-dialog modal-dialog-centered modal-lg">
                                            <div class="modal-content schedule-setting-modal">
                                                <form action="{{ route('admin.holidays.update', $holiday) }}" method="POST">
                                                    @csrf
                                                    @method('PUT')
                                                    <div class="modal-header">
                                                        <div>
                                                            <h5 class="modal-title">Edit Hari Libur</h5>
                                                            <small class="text-muted">{{ $holiday->tanggal->format('d/m/Y') }}</small>
                                                        </div>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <div class="row g-3">
                                                            <div class="col-md-4">
                                                                <label class="form-label">Tanggal Libur</label>
                                                                <input type="date" name="tanggal" class="form-control" value="{{ $holiday->tanggal->format('Y-m-d') }}" required>
                                                            </div>
                                                            <div class="col-md-5">
                                                                <label class="form-label">Nama Libur</label>
                                                                <input type="text" name="nama_libur" class="form-control" value="{{ $holiday->nama_libur }}" required>
                                                            </div>
                                                            <div class="col-md-3">
                                                                <label class="form-label d-block">Status</label>
                                                                <input type="hidden" name="status" value="nonaktif">
                                                                <label class="form-switch-card">
                                                                    <input type="checkbox" name="status" value="aktif" @checked($holiday->status === 'aktif')>
                                                                    <span class="form-switch-visual"></span>
                                                                    <span>Aktif</span>
                                                                </label>
                                                            </div>
                                                            <div class="col-12">
                                                                <label class="form-label">Keterangan</label>
                                                                <textarea name="keterangan" class="form-control" rows="3">{{ $holiday->keterangan }}</textarea>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                                                        <button type="submit" class="btn btn-primary">
                                                            <i class="bi bi-save"></i> Simpan Perubahan
                                                        </button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center text-muted">Belum ada hari libur yang diatur.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-3">
                {{ $holidays->links('pagination::bootstrap-5') }}
            </div>
        </div>
    </div>
</div>
@endsection
