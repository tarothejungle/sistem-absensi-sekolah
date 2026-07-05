@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="ui-page-hero">
        <h3>Ubah Profil</h3>
        <p>Perbarui data diri dan foto profil akun.</p>
    </div>

    @if($errors->any())
        <div class="ui-error-summary">
            <strong><i class="bi bi-exclamation-triangle"></i> Profil belum bisa disimpan</strong>
            <ul class="mb-0">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="card ui-form-card">
        <div class="card-body">
            <form action="{{ route('profile.update') }}" method="POST" enctype="multipart/form-data">
                @csrf

                <div class="profile-edit-preview">
                    @if($user->profile_photo)
                        <img
                            src="{{ asset($user->profile_photo) }}"
                            alt="Foto Profil"
                            class="profile-edit-avatar"
                        >
                    @else
                        <div class="profile-edit-avatar">
                            {{ strtoupper(substr($user->name ?? $user->nip, 0, 1)) }}
                        </div>
                    @endif

                    <div>
                        <h5 class="mb-1 fw-bold">Foto Profil</h5>
                        <p class="text-muted mb-0">Gunakan foto yang jelas agar identitas akun mudah dikenali di dashboard.</p>
                    </div>
                </div>

                <div class="ui-form-section">
                    <div class="ui-form-section-head">
                        <span class="ui-form-section-icon"><i class="bi bi-person-lines-fill"></i></span>
                        <div>
                            <h5 class="ui-form-section-title">Identitas Akun</h5>
                            <p class="ui-form-section-subtitle">Perbarui data profil yang tampil di dashboard dan laporan internal.</p>
                        </div>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6 ui-field">
                            <label class="form-label">Upload Foto Profil</label>
                            <input type="file" name="profile_photo" class="form-control" accept="image/*">
                            <small class="text-muted">Format: JPG, JPEG, PNG. Maksimal 2MB.</small>
                        </div>

                        <div class="col-md-6 ui-field">
                            <label class="form-label">Username</label>
                            <input type="text" class="form-control" value="{{ $user->nip }}" readonly>
                        </div>

                        <div class="col-md-6 ui-field">
                            <label class="form-label">Nama Lengkap</label>
                            <input
                                type="text"
                                name="name"
                                class="form-control"
                                value="{{ old('name', $user->name) }}"
                                required
                            >
                        </div>

                        <div class="col-md-6 ui-field">
                            <label class="form-label">Instansi Mengajar</label>
                            <input
                                type="text"
                                name="instansi_mengajar"
                                class="form-control"
                                value="{{ old('instansi_mengajar', $user->instansi_mengajar) }}"
                                placeholder="Contoh: MI Lantaburo"
                            >
                        </div>

                        <div class="col-md-6 ui-field">
                            <label class="form-label">Tempat Lahir</label>
                            <input
                                type="text"
                                name="tempat_lahir"
                                class="form-control"
                                value="{{ old('tempat_lahir', $user->tempat_lahir) }}"
                                placeholder="Contoh: Tangerang"
                            >
                        </div>

                        <div class="col-md-6 ui-field">
                            <label class="form-label">Tanggal Lahir</label>
                            <input
                                type="date"
                                name="tanggal_lahir"
                                class="form-control"
                                value="{{ old('tanggal_lahir', $user->tanggal_lahir) }}"
                            >
                        </div>

                        <div class="col-md-12 ui-field">
                            <label class="form-label">Pendidikan Terakhir</label>
                            <select name="pendidikan_terakhir" class="form-select">
                                <option value="">Pilih Pendidikan Terakhir</option>
                                @foreach(['SMA/SMK', 'D1', 'D2', 'D3', 'S1', 'S2', 'S3'] as $pendidikan)
                                    <option value="{{ $pendidikan }}"
                                        {{ old('pendidikan_terakhir', $user->pendidikan_terakhir) === $pendidikan ? 'selected' : '' }}>
                                        {{ $pendidikan }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>

                <div class="ui-form-actions">
                    <button type="submit" class="btn-add-primary">
                        <i class="bi bi-save"></i>
                        Simpan Profil
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
