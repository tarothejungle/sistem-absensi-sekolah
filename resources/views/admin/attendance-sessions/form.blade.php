@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="ui-page-hero">
        <h3>{{ $session ? 'Edit Sesi Absensi' : 'Tambah Sesi Absensi' }}</h3>
        <p>Atur jam masuk, jam pulang, toleransi, dan batas tombol absensi.</p>
    </div>

    @if($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="card">
        <div class="card-body">
            <form 
                action="{{ $session ? route('admin.attendance-sessions.update', $session) : route('admin.attendance-sessions.store') }}" 
                method="POST"
            >
                @csrf

                @if($session)
                    @method('PUT')
                @endif

                <div class="mb-3">
                    <label class="form-label">Nama Sesi</label>
                    <input 
                        type="text" 
                        name="nama_sesi" 
                        class="form-control" 
                        value="{{ old('nama_sesi', $session->nama_sesi ?? '') }}" 
                        placeholder="Contoh: Sesi Pagi"
                        required
                    >
                </div>

                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Jam Masuk</label>
                        <input 
                            type="time" 
                            name="jam_masuk" 
                            class="form-control" 
                            value="{{ old('jam_masuk', $session ? substr($session->jam_masuk, 0, 5) : '') }}" 
                            required
                        >
                    </div>

                    <div class="col-md-4 mb-3">
                        <label class="form-label">Jam Pulang</label>
                        <input 
                            type="time" 
                            name="jam_pulang" 
                            class="form-control" 
                            value="{{ old('jam_pulang', $session ? substr($session->jam_pulang, 0, 5) : '') }}" 
                            required
                        >
                    </div>

                    <div class="col-md-4 mb-3">
                        <label class="form-label">Toleransi Terlambat Menit</label>
                        <input 
                            type="number" 
                            name="toleransi_terlambat" 
                            class="form-control" 
                            value="{{ old('toleransi_terlambat', $session->toleransi_terlambat ?? 15) }}" 
                            min="0" 
                            required
                        >
                    </div>
                </div>

                <hr>

                <h5>Batas Waktu Tombol Check-in</h5>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Check-in Mulai</label>
                        <input 
                            type="time" 
                            name="batas_check_in_mulai" 
                            class="form-control" 
                            value="{{ old('batas_check_in_mulai', $session ? substr($session->batas_check_in_mulai, 0, 5) : '') }}" 
                            required
                        >
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label">Check-in Selesai</label>
                        <input 
                            type="time" 
                            name="batas_check_in_selesai" 
                            class="form-control" 
                            value="{{ old('batas_check_in_selesai', $session ? substr($session->batas_check_in_selesai, 0, 5) : '') }}" 
                            required
                        >
                    </div>
                </div>

                <h5>Batas Waktu Tombol Check-out</h5>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Check-out Mulai</label>
                        <input 
                            type="time" 
                            name="batas_check_out_mulai" 
                            class="form-control" 
                            value="{{ old('batas_check_out_mulai', $session ? substr($session->batas_check_out_mulai, 0, 5) : '') }}" 
                            required
                        >
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label">Check-out Selesai</label>
                        <input 
                            type="time" 
                            name="batas_check_out_selesai" 
                            class="form-control" 
                            value="{{ old('batas_check_out_selesai', $session ? substr($session->batas_check_out_selesai, 0, 5) : '') }}" 
                            required
                        >
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-control" required>
                        <option value="aktif" {{ old('status', $session->status ?? 'aktif') === 'aktif' ? 'selected' : '' }}>
                            Aktif
                        </option>
                        <option value="nonaktif" {{ old('status', $session->status ?? '') === 'nonaktif' ? 'selected' : '' }}>
                            Nonaktif
                        </option>
                    </select>
                </div>

                <button type="submit" class="btn btn-primary w-100">
                    Simpan Sesi
                </button>
            </form>
        </div>
    </div>
</div>
@endsection