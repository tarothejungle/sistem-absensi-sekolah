@extends('layouts.app')

@section('content')

@php
    $modeGantiInfal = $leave->infal_teacher_id
        && $leave->status_pengajuan === 'disetujui'
        && $leave->status_infal === 'ditolak';
@endphp

<div class="container-fluid">
    <div class="ui-page-hero">
        <h3>{{ $modeGantiInfal ? 'Ganti Guru Pengganti' : 'Edit Pengajuan' }}</h3>
        <p>Perbarui data pengajuan sesuai alur persetujuan izin/cuti.</p>
    </div>

    <div class="card ui-form-card">
        <div class="card-body">
            <form
                action="{{ route('leave.update', $leave) }}"
                method="POST"
                enctype="multipart/form-data"
            >
                @csrf
                @method('PUT')

                <div class="ui-form-section">
                    <div class="ui-form-section-head">
                        <span class="ui-form-section-icon"><i class="bi bi-calendar2-week"></i></span>
                        <div>
                            <h5 class="ui-form-section-title">Detail Pengajuan</h5>
                            <p class="ui-form-section-subtitle">Perbarui jenis pengajuan dan rentang tanggal sesuai kebutuhan.</p>
                        </div>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-4 ui-field">
                            <label class="form-label">Jenis</label>
                            <select
                                name="jenis_pengajuan"
                                class="form-select"
                                required
                                {{ $modeGantiInfal ? 'disabled' : '' }}
                            >
                                <option value="sakit" {{ old('jenis_pengajuan', $leave->jenis_pengajuan) == 'sakit' ? 'selected' : '' }}>Sakit</option>
                                <option value="izin" {{ old('jenis_pengajuan', $leave->jenis_pengajuan) == 'izin' ? 'selected' : '' }}>Izin</option>
                                <option value="cuti" {{ old('jenis_pengajuan', $leave->jenis_pengajuan) == 'cuti' ? 'selected' : '' }}>Cuti</option>
                                <option value="tugas_luar" {{ old('jenis_pengajuan', $leave->jenis_pengajuan) == 'tugas_luar' ? 'selected' : '' }}>Tugas Luar</option>
                            </select>

                            @error('jenis_pengajuan')
                                <small class="text-danger">{{ $message }}</small>
                            @enderror
                        </div>

                        <div class="col-md-4 ui-field">
                            <label class="form-label">Tanggal Mulai</label>
                            <input
                                type="date"
                                name="tanggal_mulai"
                                class="form-control"
                                value="{{ old('tanggal_mulai', $leave->tanggal_mulai->format('Y-m-d')) }}"
                                required
                                {{ $modeGantiInfal ? 'disabled' : '' }}
                            >

                            @error('tanggal_mulai')
                                <small class="text-danger">{{ $message }}</small>
                            @enderror
                        </div>

                        <div class="col-md-4 ui-field">
                            <label class="form-label">Tanggal Selesai</label>
                            <input
                                type="date"
                                name="tanggal_selesai"
                                class="form-control"
                                value="{{ old('tanggal_selesai', $leave->tanggal_selesai->format('Y-m-d')) }}"
                                required
                                {{ $modeGantiInfal ? 'disabled' : '' }}
                            >

                            @error('tanggal_selesai')
                                <small class="text-danger">{{ $message }}</small>
                            @enderror
                        </div>
                    </div>
                </div>

                <div class="ui-form-section">
                    <div class="ui-form-section-head">
                        <span class="ui-form-section-icon"><i class="bi bi-person-check"></i></span>
                        <div>
                            <h5 class="ui-form-section-title">Guru Pengganti</h5>
                            <p class="ui-form-section-subtitle">
                                {{ $modeGantiInfal ? 'Guru pengganti sebelumnya menolak. Pilih pengganti baru atau kosongkan.' : 'Kosongkan jika pengajuan tidak membutuhkan guru pengganti.' }}
                            </p>
                        </div>
                    </div>

                    <div class="ui-field">
                        <label class="form-label">Guru Pengganti / Guru Infal</label>

                        <select name="infal_teacher_id" class="form-select">
                            <option value="">Tidak menggunakan guru pengganti</option>

                            @foreach($teachers as $teacher)
                                <option value="{{ $teacher->id }}"
                                    {{ old('infal_teacher_id', $leave->infal_teacher_id) == $teacher->id ? 'selected' : '' }}>
                                    {{ $teacher->nama_lengkap }}
                                </option>
                            @endforeach
                        </select>

                        @error('infal_teacher_id')
                            <small class="text-danger d-block">{{ $message }}</small>
                        @enderror
                    </div>
                </div>

                <div class="ui-form-section">
                    <div class="ui-form-section-head">
                        <span class="ui-form-section-icon"><i class="bi bi-card-text"></i></span>
                        <div>
                            <h5 class="ui-form-section-title">Alasan dan Lampiran</h5>
                            <p class="ui-form-section-subtitle">Perbarui alasan atau ganti lampiran jika diperlukan.</p>
                        </div>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-8 ui-field">
                            <label class="form-label">Alasan</label>
                            <textarea
                                name="alasan"
                                class="form-control"
                                rows="4"
                                required
                                {{ $modeGantiInfal ? 'disabled' : '' }}
                            >{{ old('alasan', $leave->alasan) }}</textarea>

                            @error('alasan')
                                <small class="text-danger">{{ $message }}</small>
                            @enderror
                        </div>

                        <div class="col-md-4 ui-field">
                            <label class="form-label">Lampiran</label>
                            <input
                                type="file"
                                name="lampiran"
                                class="form-control"
                                {{ $modeGantiInfal ? 'disabled' : '' }}
                            >

                            <small class="text-muted">
                                Format JPG, JPEG, PNG, PDF. Maksimal 2MB.
                            </small>

                            @if($leave->lampiran)
                                <div class="mt-2">
                                    <a href="{{ route('leave.attachment.show', $leave) }}" target="_blank">
                                        Lihat lampiran saat ini
                                    </a>
                                </div>
                            @endif

                            @error('lampiran')
                                <small class="text-danger d-block">{{ $message }}</small>
                            @enderror
                        </div>
                    </div>
                </div>

                <div class="ui-form-actions">
                    <a href="{{ route('leave.index') }}" class="btn btn-secondary">
                        <i class="bi bi-arrow-left"></i>
                        Kembali
                    </a>

                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save"></i>
                        {{ $modeGantiInfal ? 'Simpan Guru Pengganti' : 'Simpan Perubahan' }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
