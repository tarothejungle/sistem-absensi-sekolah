@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="ui-page-hero">
        <h3>Ubah Password</h3>
        <p>Ganti password akun secara aman.</p>
    </div>

    @if($errors->any())
        <div class="ui-error-summary">
            <strong><i class="bi bi-exclamation-triangle"></i> Password belum bisa diubah</strong>
            <ul class="mb-0">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="card ui-form-card">
        <div class="card-body">
            <form action="{{ route('profile.password') }}" method="POST">
                @csrf

                <div class="ui-form-section">
                    <div class="ui-form-section-head">
                        <span class="ui-form-section-icon"><i class="bi bi-shield-lock"></i></span>
                        <div>
                            <h5 class="ui-form-section-title">Keamanan Akun</h5>
                            <p class="ui-form-section-subtitle">Gunakan password baru yang kuat dan berbeda dari password sebelumnya.</p>
                        </div>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-12 ui-field">
                            <label class="form-label">Password Lama</label>
                            <div class="password-wrapper">
                                <input type="password" name="old_password" class="form-control" required>
                                <button type="button" class="password-toggle" onclick="togglePasswordField(this)" aria-label="Lihat password lama">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                        </div>

                        <div class="col-md-6 ui-field">
                            <label class="form-label">Password Baru</label>
                            <div class="password-wrapper">
                                <input type="password" name="password" class="form-control" required>
                                <button type="button" class="password-toggle" onclick="togglePasswordField(this)" aria-label="Lihat password baru">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                        </div>

                        <div class="col-md-6 ui-field">
                            <label class="form-label">Konfirmasi Password Baru</label>
                            <div class="password-wrapper">
                                <input type="password" name="password_confirmation" class="form-control" required>
                                <button type="button" class="password-toggle" onclick="togglePasswordField(this)" aria-label="Lihat konfirmasi password">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="ui-form-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-key"></i>
                        Ubah Password
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
