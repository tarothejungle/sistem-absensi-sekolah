<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Login - Sistem Absensi Sekolah</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="{{ asset('css/absensi-ui-final.css') }}?v=20260702-form-alert-polish" rel="stylesheet">
</head>
<body>
    <div class="login-shell-final">
        <section class="login-showcase">
            <div>
                <div class="d-inline-flex align-items-center gap-2 mb-4" style="background:rgba(255,255,255,.14);border:1px solid rgba(255,255,255,.18);border-radius:999px;padding:8px 14px;position:relative;z-index:1;">
                    <i class="bi bi-shield-check"></i>
                    <span class="fw-bold">MI Lantaburo</span>
                </div>
                <h1>Sistem absensi sekolah yang lebih cepat, rapi, dan terpantau.</h1>
                <p>Kelola check-in, check-out, laporan, izin/cuti, piket, serta rekap infal dalam satu sistem harian yang mudah digunakan.</p>
            </div>

            <div class="login-feature-grid">
                <div class="login-feature">
                    <i class="bi bi-camera-video"></i>
                    <strong>Face Capture</strong>
                    <div class="small mt-1" style="color:rgba(255,255,255,.82);">Absensi dengan kamera.</div>
                </div>
                <div class="login-feature">
                    <i class="bi bi-geo-alt"></i>
                    <strong>Geofence</strong>
                    <div class="small mt-1" style="color:rgba(255,255,255,.82);">Validasi radius sekolah.</div>
                </div>
                <div class="login-feature">
                    <i class="bi bi-file-earmark-bar-graph"></i>
                    <strong>Rekap</strong>
                    <div class="small mt-1" style="color:rgba(255,255,255,.82);">Laporan siap cetak.</div>
                </div>
            </div>
        </section>

        <section class="login-panel">
            <div class="login-card-final">
                <img src="{{ asset('images/logo-MI.png') }}" alt="Logo MI" class="login-logo">

                <div class="login-title">
                    Login Sistem Absensi Sekolah<br>MI Lantaburo
                </div>

                @if(session('success'))
                    <div class="alert alert-success">{{ session('success') }}</div>
                @endif

                @if(session('error'))
                    <div class="alert alert-danger">{{ session('error') }}</div>
                @endif

                @if($errors->any())
                    <div class="alert alert-danger">Username atau password tidak sesuai.</div>
                @endif

                <form action="{{ route('login.process') }}" method="POST">
                    @csrf

                    <div class="mb-3">
                        <label class="form-label">Username</label>
                        <input type="text" name="nip" class="form-control" value="{{ old('nip') }}" placeholder="Masukkan username" required autofocus>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Password</label>
                        <div class="password-wrapper">
                            <input type="password" name="password" id="passwordInput" class="form-control password-input" placeholder="Masukkan password" required>
                            <button type="button" class="password-toggle" onclick="togglePassword()" aria-label="Lihat password">
                                <i id="passwordIcon" class="bi bi-eye"></i>
                            </button>
                        </div>
                    </div>

                    <div class="login-options">
                        <label class="remember-wrap" for="remember">
                            <input type="checkbox" name="remember" id="remember" {{ old('remember') ? 'checked' : '' }}>
                            <span>Ingat Saya</span>
                        </label>

                        <a href="{{ route('password.request') }}" class="forgot-link">Lupa Password?</a>
                    </div>

                    <button type="submit" class="btn btn-login text-white w-100">
                        <i class="bi bi-box-arrow-in-right"></i>
                        Login
                    </button>
                </form>
            </div>
        </section>
    </div>

    <script>
        function togglePassword() {
            const passwordInput = document.getElementById('passwordInput');
            const passwordIcon = document.getElementById('passwordIcon');

            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                passwordIcon.classList.remove('bi-eye');
                passwordIcon.classList.add('bi-eye-slash');
            } else {
                passwordInput.type = 'password';
                passwordIcon.classList.remove('bi-eye-slash');
                passwordIcon.classList.add('bi-eye');
            }
        }
    </script>
</body>
</html>
