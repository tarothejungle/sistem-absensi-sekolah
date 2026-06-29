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
        class="card card-body"
    >
        @csrf

        <div class="mb-3">
            <label>Jenis</label>
            <select name="jenis_pengajuan" class="form-select">
                <option value="sakit">Sakit</option>
                <option value="izin">Izin</option>
                <option value="cuti">Cuti</option>
                <option value="tugas_luar">Tugas Luar</option>
            </select>
        </div>

        <div class="mb-3">
            <label class="form-label">Guru Pengganti / Guru Infal</label>

            <select name="infal_teacher_id" class="form-control">
                <option value="">-- Tidak menggunakan guru pengganti --</option>

                @foreach($teachers as $teacher)
                    <option value="{{ $teacher->id }}" {{ old('infal_teacher_id') == $teacher->id ? 'selected' : '' }}>
                        {{ $teacher->nama_lengkap }}
                        @if($teacher->mapel)
                            - {{ $teacher->mapel }}
                        @endif
                    </option>
                @endforeach
            </select>

            <small class="text-muted">
                Kosongkan jika pengajuan bersifat sementara dan tidak membutuhkan guru pengganti.
            </small>

            @error('infal_teacher_id')
                <small class="text-danger d-block">{{ $message }}</small>
            @enderror
        </div>

        <div class="row">
            <div class="col-md-6 mb-3">
                <label>Tanggal Mulai</label>
                <input
                    type="date"
                    name="tanggal_mulai"
                    class="form-control"
                    required
                >
            </div>

            <div class="col-md-6 mb-3">
                <label>Tanggal Selesai</label>
                <input
                    type="date"
                    name="tanggal_selesai"
                    class="form-control"
                    required
                >
            </div>
        </div>

        <div class="mb-3">
            <label>Alasan</label>
            <textarea
                name="alasan"
                class="form-control"
                rows="4"
                required
            ></textarea>
        </div>

        <div class="mb-3">
            <label>Lampiran</label>
            <input
                type="file"
                name="lampiran"
                class="form-control"
                accept=".jpg,.jpeg,.png,.pdf"
            >
        </div>

        <button type="submit" class="btn btn-primary">
            Kirim Pengajuan
        </button>
    </form>
</div>
@endsection