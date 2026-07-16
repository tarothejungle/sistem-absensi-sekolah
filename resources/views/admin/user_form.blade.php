@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="ui-page-hero">
        <h3>{{ $user ? 'Edit Admin' : 'Tambah Admin' }}</h3>
        <p>Kelola akun super admin untuk administrasi sistem.</p>
    </div>

    @if($errors->any())
        <div class="ui-error-summary">
            <strong><i class="bi bi-exclamation-triangle"></i> Data admin belum bisa disimpan</strong>
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
                            <p class="ui-form-section-subtitle">Data ini digunakan untuk login super admin.</p>
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

                    <div class="col-md-6 ui-field">
                        <label class="form-label">
                            Password {{ $user ? '(Opsional)' : '' }}
                        </label>

                        <div class="password-wrapper">
                            <input
                                type="password"
                                name="password"
                                class="form-control password-input"
                                data-strong-password
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
                        <small class="text-muted d-block mt-2" data-password-requirements>Minimal 8 karakter, huruf besar-kecil, angka, dan simbol.</small>
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
                        Simpan Admin
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    document.querySelectorAll('[data-strong-password]').forEach(function (input) {
        input.addEventListener('input', function () {
            const valid = input.value.length >= 8
                && /[a-z]/.test(input.value)
                && /[A-Z]/.test(input.value)
                && /[0-9]/.test(input.value)
                && /[^A-Za-z0-9]/.test(input.value);
            input.setCustomValidity(input.value && !valid ? 'Password harus minimal 8 karakter dan berisi huruf besar, huruf kecil, angka, serta simbol.' : '');
        });
    });
</script>
@endpush
