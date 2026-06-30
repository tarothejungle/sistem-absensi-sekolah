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

    <div class="card">
        <div class="card-body">
            <form 
                action="{{ route('leave.update', $leave) }}" 
                method="POST" 
                enctype="multipart/form-data"
            >
                @csrf
                @method('PUT')

                <div class="mb-3">
                    <label class="form-label">Jenis</label>
                    <select 
                        name="jenis_pengajuan" 
                        class="form-select" 
                        required
                        {{ $modeGantiInfal ? 'disabled' : '' }}
                    >
                        <option value="sakit" {{ old('jenis_pengajuan', $leave->jenis_pengajuan) == 'sakit' ? 'selected' : '' }}>
                            Sakit
                        </option>
                        <option value="izin" {{ old('jenis_pengajuan', $leave->jenis_pengajuan) == 'izin' ? 'selected' : '' }}>
                            Izin
                        </option>
                        <option value="cuti" {{ old('jenis_pengajuan', $leave->jenis_pengajuan) == 'cuti' ? 'selected' : '' }}>
                            Cuti
                        </option>
                        <option value="tugas_luar" {{ old('jenis_pengajuan', $leave->jenis_pengajuan) == 'tugas_luar' ? 'selected' : '' }}>
                            Tugas Luar
                        </option>
                    </select>

                    @error('jenis_pengajuan')
                        <small class="text-danger">{{ $message }}</small>
                    @enderror
                </div>

                <div class="mb-3">
                    <label class="form-label">Guru Pengganti / Guru Infal</label>

                    <select name="infal_teacher_id" class="form-control">
                        <option value="">-- Tidak menggunakan guru pengganti --</option>

                        @foreach($teachers as $teacher)
                            <option value="{{ $teacher->id }}"
                                {{ old('infal_teacher_id', $leave->infal_teacher_id) == $teacher->id ? 'selected' : '' }}>
                                {{ $teacher->nama_lengkap }}
                            </option>
                        @endforeach
                    </select>

                    <small class="text-muted">
                        Kosongkan jika pengajuan tidak membutuhkan guru pengganti.
                    </small>

                    @error('infal_teacher_id')
                        <small class="text-danger d-block">{{ $message }}</small>
                    @enderror

                    @if($modeGantiInfal)
                        <small class="text-muted d-block mt-1">
                            Guru pengganti sebelumnya menolak. Silakan pilih guru pengganti baru, atau kosongkan jika tidak membutuhkan guru pengganti.
                        </small>
                    @endif
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
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

                    <div class="col-md-6 mb-3">
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

                <div class="mb-3">
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

                <div class="mb-3">
                    <label class="form-label">Lampiran</label>
                    <input 
                        type="file" 
                        name="lampiran" 
                        class="form-control"
                        {{ $modeGantiInfal ? 'disabled' : '' }}
                    >

                    <small class="text-muted">
                        Kosongkan jika tidak ingin mengganti lampiran. Format: JPG, JPEG, PNG, PDF. Maksimal 2MB.
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

                <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            {{ $modeGantiInfal ? 'Simpan Guru Pengganti' : 'Simpan Perubahan' }}
                        </button>

                    <a href="{{ route('leave.index') }}" class="btn btn-secondary">
                        Kembali
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
