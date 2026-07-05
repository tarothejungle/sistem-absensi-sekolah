@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="ui-page-hero">
        <h3>{{ $user ? 'Edit Pengguna' : 'Tambah Pengguna' }}</h3>
        <p>Kelola akun bendahara, kepala sekolah, dan super admin.</p>
    </div>

    @if($errors->any())
        <div class="ui-error-summary">
            <strong><i class="bi bi-exclamation-triangle"></i> Data pengguna belum bisa disimpan</strong>
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
                action="{{ $user ? route('admin.users.update', $user) : route('admin.users.store') }}"
                method="POST"
            >
                @csrf

                @if($user)
                    @method('PUT')
                @endif

                <div class="ui-form-section">
                    <div class="ui-form-section-head">
                        <span class="ui-form-section-icon"><i class="bi bi-person-vcard"></i></span>
                        <div>
                            <h5 class="ui-form-section-title">Informasi Akun</h5>
                            <p class="ui-form-section-subtitle">Data ini digunakan untuk login dan hak akses pengguna.</p>
                        </div>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6 ui-field">
                            <label class="form-label">Username</label>
                            <input
                                type="text"
                                name="nip"
                                class="form-control"
                                value="{{ old('nip', $user->nip ?? '') }}"
                                placeholder="Masukkan username"
                                required
                            >
                        </div>

                        <div class="col-md-6 ui-field">
                            <label class="form-label">Nama Lengkap</label>
                            <input
                                type="text"
                                name="name"
                                class="form-control"
                                value="{{ old('name', $user->name ?? '') }}"
                                placeholder="Masukkan nama lengkap"
                                required
                            >
                        </div>

                        <div class="col-md-6 ui-field">
                            <label class="form-label">Email</label>
                            <input
                                type="email"
                                name="email"
                                class="form-control"
                                value="{{ old('email', $user->email ?? '') }}"
                                placeholder="contoh@email.com"
                                required
                            >
                        </div>

                        <div class="col-md-3 ui-field">
                            <label class="form-label">Role</label>
                            <select name="role" class="form-select" required>
                                <option value="">Pilih role</option>
                                <option value="bendahara" {{ old('role', $user->role ?? '') === 'bendahara' ? 'selected' : '' }}>
                                    Bendahara
                                </option>
                                <option value="kepala_sekolah" {{ old('role', $user->role ?? '') === 'kepala_sekolah' ? 'selected' : '' }}>
                                    Kepala Sekolah
                                </option>
                                <option value="super_admin" {{ old('role', $user->role ?? '') === 'super_admin' ? 'selected' : '' }}>
                                    Super Admin
                                </option>
                            </select>
                        </div>

                        <div class="col-md-3 ui-field">
                            <label class="form-label d-block">Status</label>
                            <input type="hidden" name="status" value="nonaktif">
                            <label class="form-switch-card">
                                <input type="checkbox" name="status" value="aktif" @checked(old('status', $user->status ?? 'aktif') === 'aktif')>
                                <span class="form-switch-visual"></span>
                                <span>Aktif</span>
                            </label>
                        </div>
                    </div>
                </div>

                <div class="ui-form-section">
                    <div class="ui-form-section-head">
                        <span class="ui-form-section-icon"><i class="bi bi-shield-lock"></i></span>
                        <div>
                            <h5 class="ui-form-section-title">Keamanan</h5>
                            <p class="ui-form-section-subtitle">{{ $user ? 'Kosongkan password jika tidak ingin mengubah akses login.' : 'Buat password awal untuk pengguna baru.' }}</p>
                        </div>
                    </div>

                    <div class="ui-field">
                        <label class="form-label">
                            Password {{ $user ? '(Opsional)' : '' }}
                        </label>

                        <div class="password-wrapper">
                            <input
                                type="password"
                                name="password"
                                class="form-control"
                                placeholder="{{ $user ? 'Kosongkan jika tidak ingin mengubah password' : 'Masukkan password' }}"
                                {{ $user ? '' : 'required' }}
                            >

                            <button
                                type="button"
                                class="password-toggle"
                                onclick="togglePasswordField(this)"
                                aria-label="Lihat password"
                            >
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <div class="ui-form-actions">
                    <a href="{{ route('admin.users') }}" class="btn btn-secondary">
                        <i class="bi bi-arrow-left"></i>
                        Kembali
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save"></i>
                        Simpan Pengguna
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
