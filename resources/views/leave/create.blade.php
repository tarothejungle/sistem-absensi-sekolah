@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="ui-page-hero">
        <h3>Form Pengajuan Izin/Cuti</h3>
        <p>Ajukan izin atau cuti beserta guru pengganti/infal.</p>
    </div>

    <form
        method="POST"
        action="{{ route('leave.store') }}"
        enctype="multipart/form-data"
        class="card card-body ui-form-card"
    >
        @csrf

        <div class="ui-form-section">
            <div class="ui-form-section-head">
                <span class="ui-form-section-icon"><i class="bi bi-calendar2-week"></i></span>
                <div>
                    <h5 class="ui-form-section-title">Detail Pengajuan</h5>
                    <p class="ui-form-section-subtitle">Pilih jenis pengajuan dan rentang tanggal izin/cuti.</p>
                </div>
            </div>

            <div class="row g-3">
                <div class="col-md-4 ui-field">
                    <label>Jenis</label>
                    <select name="jenis_pengajuan" class="form-select">
                        <option value="sakit">Sakit</option>
                        <option value="izin">Izin</option>
                        <option value="cuti">Cuti</option>
                        <option value="tugas_luar">Tugas Luar</option>
                    </select>
                </div>

                <div class="col-md-4 ui-field">
                    <label>Tanggal Mulai</label>
                    <input
                        type="date"
                        name="tanggal_mulai"
                        class="form-control"
                        required
                    >
                </div>

                <div class="col-md-4 ui-field">
                    <label>Tanggal Selesai</label>
                    <input
                        type="date"
                        name="tanggal_selesai"
                        class="form-control"
                        required
                    >
                </div>
            </div>
        </div>

        <div class="ui-form-section">
            <div class="ui-form-section-head">
                <span class="ui-form-section-icon"><i class="bi bi-person-check"></i></span>
                <div>
                    <h5 class="ui-form-section-title">Guru Pengganti</h5>
                    <p class="ui-form-section-subtitle">Kosongkan jika pengajuan tidak membutuhkan guru pengganti/infal.</p>
                </div>
            </div>

            <div class="ui-field">
                <label class="form-label">Guru Pengganti / Guru Infal</label>

                <select name="infal_teacher_id" class="form-select">
                    <option value="">Tidak menggunakan guru pengganti</option>

                    @foreach($teachers as $teacher)
                        <option value="{{ $teacher->id }}" {{ old('infal_teacher_id') == $teacher->id ? 'selected' : '' }}>
                            {{ $teacher->nama_lengkap }}
                            @if($teacher->mapel)
                                - {{ $teacher->mapel }}
                            @endif
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
                    <p class="ui-form-section-subtitle">Tulis alasan singkat dan lampirkan bukti jika diperlukan.</p>
                </div>
            </div>

            <div class="row g-3">
                <div class="col-md-8 ui-field">
                    <label>Alasan</label>
                    <textarea
                        name="alasan"
                        class="form-control"
                        rows="4"
                        placeholder="Tuliskan alasan pengajuan"
                        required
                    ></textarea>
                </div>

                <div class="col-md-4 ui-field">
                    <label>Lampiran</label>
                    <input
                        type="file"
                        name="lampiran"
                        class="form-control"
                        accept=".jpg,.jpeg,.png,.pdf"
                    >
                    <small class="text-muted">Format JPG, PNG, atau PDF.</small>
                </div>
            </div>
        </div>

        <div class="ui-form-actions">
            <a href="{{ route('leave.index') }}" class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i>
                Kembali
            </a>
            <button type="submit" class="btn btn-primary">
                <i class="bi bi-send"></i>
                Kirim Pengajuan
            </button>
        </div>
    </form>
</div>
@endsection
