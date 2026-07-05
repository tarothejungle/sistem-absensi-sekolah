@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="ui-page-hero">
        <div>
            <h3>Setting Hari Piket</h3>
            <p>Tentukan tanggal piket dan guru/karyawan yang tetap wajib melakukan absensi.</p>
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

    <form action="{{ route('admin.duty-schedules.store') }}" method="POST" class="card ui-form-card mb-4">
        @csrf
        <div class="card-body">
            <div class="ui-form-section">
                <div class="ui-form-section-head">
                    <span class="ui-form-section-icon"><i class="bi bi-person-check"></i></span>
                    <div>
                        <h5 class="ui-form-section-title">Tambah Jadwal Piket</h5>
                        <p class="ui-form-section-subtitle">Jika aktif, petugas piket pada tanggal ini tetap masuk daftar wajib absen.</p>
                    </div>
                </div>

                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Tanggal Piket</label>
                        <input type="date" name="tanggal" class="form-control" value="{{ old('tanggal') }}" required>
                    </div>
                    <div class="col-md-5">
                        <label class="form-label">Nama Jadwal</label>
                        <input type="text" name="nama_piket" class="form-control" value="{{ old('nama_piket') }}" placeholder="Contoh: Piket PPDB">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label d-block">Status Piket</label>
                        <input type="hidden" name="status" value="nonaktif">
                        <label class="form-switch-card">
                            <input type="checkbox" name="status" value="aktif" @checked(old('status', 'aktif') === 'aktif')>
                            <span class="form-switch-visual"></span>
                            <span>Aktif</span>
                        </label>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Petugas Piket</label>
                        <div class="teacher-picker">
                            @forelse($teachers as $teacher)
                                <label class="teacher-picker-item">
                                    <input
                                        type="checkbox"
                                        class="form-check-input"
                                        name="teacher_ids[]"
                                        value="{{ $teacher->id }}"
                                        @checked(in_array($teacher->id, old('teacher_ids', [])))
                                    >
                                    <span>
                                        <strong>{{ $teacher->nama_lengkap }}</strong>
                                        <small>{{ $teacher->user->nip ?? '-' }} - {{ $teacher->jabatan ?: 'Guru/Karyawan' }}</small>
                                    </span>
                                </label>
                            @empty
                                <div class="text-muted">Belum ada guru/karyawan aktif yang bisa dipilih.</div>
                            @endforelse
                        </div>
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
                    <span>Simpan Jadwal Piket</span>
                </button>
            </div>
        </div>
    </form>

    <div class="card">
        <div class="card-body">
            <div class="ui-section-title">
                <i class="bi bi-clipboard-check"></i>
                <span>Daftar Jadwal Piket</span>
            </div>

            <div class="table-responsive-mobile">
                <table class="table table-bordered table-striped align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Tanggal</th>
                            <th>Status</th>
                            <th>Petugas</th>
                            <th>Keterangan</th>
                            <th width="170">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($dutySchedules as $dutySchedule)
                            @php
                                $selectedTeacherIds = $dutySchedule->teachers->pluck('id')->all();
                            @endphp
                            <tr>
                                <td>
                                    <div class="fw-bold">{{ $dutySchedule->tanggal->translatedFormat('d F Y') }}</div>
                                    <small class="text-muted">{{ $dutySchedule->nama_piket ?: 'Jadwal piket' }}</small>
                                </td>
                                <td>
                                    <form action="{{ route('admin.duty-schedules.toggle', $dutySchedule) }}" method="POST" class="m-0" data-no-loading="true">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="duty-switch {{ $dutySchedule->status === 'aktif' ? 'is-active' : '' }}" aria-label="Ubah status piket">
                                            <span></span>
                                        </button>
                                    </form>
                                    <small class="d-block mt-1 {{ $dutySchedule->status === 'aktif' ? 'text-success' : 'text-muted' }}">
                                        {{ $dutySchedule->status === 'aktif' ? 'Aktif' : 'Nonaktif' }}
                                    </small>
                                </td>
                                <td>
                                    <div class="duty-teacher-list">
                                        @forelse($dutySchedule->teachers as $teacher)
                                            <span>{{ $teacher->nama_lengkap }}</span>
                                        @empty
                                            <span class="is-empty">Belum ada petugas</span>
                                        @endforelse
                                    </div>
                                </td>
                                <td>{{ $dutySchedule->keterangan ?: '-' }}</td>
                                <td>
                                    <div class="ui-inline-actions">
                                        <button type="button" class="btn btn-warning btn-sm" data-bs-toggle="modal" data-bs-target="#dutyEditModal{{ $dutySchedule->id }}">
                                            Edit
                                        </button>

                                        <form
                                            action="{{ route('admin.duty-schedules.destroy', $dutySchedule) }}"
                                            method="POST"
                                            class="d-inline"
                                            data-confirm-action="true"
                                            data-confirm-type="danger"
                                            data-confirm-icon="bi-trash3"
                                            data-confirm-title="Hapus jadwal piket?"
                                            data-confirm-message="Jadwal piket {{ $dutySchedule->tanggal->format('d/m/Y') }} akan dihapus dari sistem."
                                            data-confirm-submit="Hapus"
                                        >
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-danger btn-sm">Hapus</button>
                                        </form>
                                    </div>

                                    <div class="modal fade" id="dutyEditModal{{ $dutySchedule->id }}" tabindex="-1" aria-hidden="true">
                                        <div class="modal-dialog modal-dialog-centered modal-lg">
                                            <div class="modal-content schedule-setting-modal">
                                                <form action="{{ route('admin.duty-schedules.update', $dutySchedule) }}" method="POST">
                                                    @csrf
                                                    @method('PUT')
                                                    <div class="modal-header">
                                                        <div>
                                                            <h5 class="modal-title">Edit Jadwal Piket</h5>
                                                            <small class="text-muted">{{ $dutySchedule->tanggal->format('d/m/Y') }}</small>
                                                        </div>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <div class="row g-3">
                                                            <div class="col-md-4">
                                                                <label class="form-label">Tanggal Piket</label>
                                                                <input type="date" name="tanggal" class="form-control" value="{{ $dutySchedule->tanggal->format('Y-m-d') }}" required>
                                                            </div>
                                                            <div class="col-md-5">
                                                                <label class="form-label">Nama Jadwal</label>
                                                                <input type="text" name="nama_piket" class="form-control" value="{{ $dutySchedule->nama_piket }}">
                                                            </div>
                                                            <div class="col-md-3">
                                                                <label class="form-label d-block">Status Piket</label>
                                                                <input type="hidden" name="status" value="nonaktif">
                                                                <label class="form-switch-card">
                                                                    <input type="checkbox" name="status" value="aktif" @checked($dutySchedule->status === 'aktif')>
                                                                    <span class="form-switch-visual"></span>
                                                                    <span>Aktif</span>
                                                                </label>
                                                            </div>
                                                            <div class="col-12">
                                                                <label class="form-label">Petugas Piket</label>
                                                                <div class="teacher-picker">
                                                                    @foreach($teachers as $teacher)
                                                                        <label class="teacher-picker-item">
                                                                            <input
                                                                                type="checkbox"
                                                                                class="form-check-input"
                                                                                name="teacher_ids[]"
                                                                                value="{{ $teacher->id }}"
                                                                                @checked(in_array($teacher->id, $selectedTeacherIds))
                                                                            >
                                                                            <span>
                                                                                <strong>{{ $teacher->nama_lengkap }}</strong>
                                                                                <small>{{ $teacher->user->nip ?? '-' }} - {{ $teacher->jabatan ?: 'Guru/Karyawan' }}</small>
                                                                            </span>
                                                                        </label>
                                                                    @endforeach
                                                                </div>
                                                            </div>
                                                            <div class="col-12">
                                                                <label class="form-label">Keterangan</label>
                                                                <textarea name="keterangan" class="form-control" rows="3">{{ $dutySchedule->keterangan }}</textarea>
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
                                <td colspan="5" class="text-center text-muted">Belum ada jadwal piket yang diatur.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-3">
                {{ $dutySchedules->links('pagination::bootstrap-5') }}
            </div>
        </div>
    </div>
</div>
@endsection
