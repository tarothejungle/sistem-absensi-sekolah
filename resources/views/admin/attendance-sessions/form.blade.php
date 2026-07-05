@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="ui-page-hero">
        <h3>{{ $session ? 'Edit Sesi Absensi' : 'Tambah Sesi Absensi' }}</h3>
        <p>Atur jam masuk, jam pulang, toleransi, dan batas tombol absensi.</p>
    </div>

    @if($errors->any())
        <div class="ui-error-summary">
            <strong><i class="bi bi-exclamation-triangle"></i> Sesi absensi belum bisa disimpan</strong>
            <ul class="mb-0">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="card ui-form-card">
        <div class="card-body">
            <form
                action="{{ $session ? route('admin.attendance-sessions.update', $session) : route('admin.attendance-sessions.store') }}"
                method="POST"
            >
                @csrf

                @if($session)
                    @method('PUT')
                @endif

                <div class="ui-form-section">
                    <div class="ui-form-section-head">
                        <span class="ui-form-section-icon"><i class="bi bi-clock-history"></i></span>
                        <div>
                            <h5 class="ui-form-section-title">Identitas Sesi</h5>
                            <p class="ui-form-section-subtitle">Nama sesi dan jam utama yang akan dipakai pada jadwal guru.</p>
                        </div>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6 ui-field">
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

                        <div class="col-md-3 ui-field">
                            <label class="form-label">Jam Masuk</label>
                            <input
                                type="time"
                                name="jam_masuk"
                                class="form-control"
                                value="{{ old('jam_masuk', $session ? substr($session->jam_masuk, 0, 5) : '') }}"
                                required
                            >
                        </div>

                        <div class="col-md-3 ui-field">
                            <label class="form-label">Jam Pulang</label>
                            <input
                                type="time"
                                name="jam_pulang"
                                class="form-control"
                                value="{{ old('jam_pulang', $session ? substr($session->jam_pulang, 0, 5) : '') }}"
                                required
                            >
                        </div>

                        <div class="col-md-6 ui-field">
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

                        <div class="col-md-6 ui-field">
                            <label class="form-label d-block">Status</label>
                            <input type="hidden" name="status" value="nonaktif">
                            <label class="form-switch-card">
                                <input type="checkbox" name="status" value="aktif" @checked(old('status', $session->status ?? 'aktif') === 'aktif')>
                                <span class="form-switch-visual"></span>
                                <span>Aktif</span>
                            </label>
                        </div>
                    </div>
                </div>

                <div class="ui-form-section">
                    <div class="ui-form-section-head">
                        <span class="ui-form-section-icon"><i class="bi bi-box-arrow-in-right"></i></span>
                        <div>
                            <h5 class="ui-form-section-title">Batas Tombol Check-in</h5>
                            <p class="ui-form-section-subtitle">Rentang waktu tombol check-in boleh digunakan guru.</p>
                        </div>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6 ui-field">
                            <label class="form-label">Check-in Mulai</label>
                            <input
                                type="time"
                                name="batas_check_in_mulai"
                                class="form-control"
                                value="{{ old('batas_check_in_mulai', $session ? substr($session->batas_check_in_mulai, 0, 5) : '') }}"
                                required
                            >
                        </div>

                        <div class="col-md-6 ui-field">
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
                </div>

                <div class="ui-form-section">
                    <div class="ui-form-section-head">
                        <span class="ui-form-section-icon"><i class="bi bi-box-arrow-right"></i></span>
                        <div>
                            <h5 class="ui-form-section-title">Batas Tombol Check-out</h5>
                            <p class="ui-form-section-subtitle">Rentang waktu tombol check-out boleh digunakan guru.</p>
                        </div>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6 ui-field">
                            <label class="form-label">Check-out Mulai</label>
                            <input
                                type="time"
                                name="batas_check_out_mulai"
                                class="form-control"
                                value="{{ old('batas_check_out_mulai', $session ? substr($session->batas_check_out_mulai, 0, 5) : '') }}"
                                required
                            >
                        </div>

                        <div class="col-md-6 ui-field">
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
                </div>

                <div class="ui-form-actions">
                    <a href="{{ route('admin.attendance-sessions.index') }}" class="btn btn-secondary">
                        <i class="bi bi-arrow-left"></i>
                        Kembali
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save"></i>
                        Simpan Sesi
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
