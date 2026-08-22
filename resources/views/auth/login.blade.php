<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#f6f4ef">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @include('partials.pwa-head')
    <title>Masuk - Sistem Absensi Guru MI Lantaburo</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="{{ asset('css/login-editorial.css') }}?v=20260730-ux1" rel="stylesheet">
</head>
<body class="auth-login">
    @php($turnstileEnabled = (bool) config('services.turnstile.enabled'))
    <main class="auth-workspace">
        <section class="auth-story" aria-labelledby="auth-story-title">
            <header class="auth-brand">
                <span class="auth-brand-mark"><img src="{{ asset('images/logo-MI.png') }}" alt=""></span>
                <span><strong>MI Lantaburo</strong><small>Sistem Absensi Guru</small></span>
            </header>

            <div class="auth-story-copy">
                <p class="auth-eyebrow">Ruang kerja digital sekolah</p>
                <h1 id="auth-story-title">Aktivitas guru,<br>lebih mudah dipantau.</h1>
                <p class="auth-lead">Satu ruang kerja untuk presensi, izin, laporan, dan payroll yang tertata serta mudah digunakan setiap hari.</p>
            </div>

            <div class="auth-modules" aria-label="Fitur utama sistem">
                <article class="auth-module auth-module-primary">
                    <span class="auth-module-icon"><i class="bi bi-person-bounding-box" aria-hidden="true"></i></span>
                    <div><small>Presensi</small><strong>Wajah + lokasi</strong><p>Validasi kehadiran yang lebih akurat.</p></div>
                    <span class="auth-module-status"><i class="bi bi-check2" aria-hidden="true"></i> Terintegrasi</span>
                </article>
                <article class="auth-module">
                    <span class="auth-module-icon"><i class="bi bi-calendar2-check" aria-hidden="true"></i></span>
                    <div><small>Perizinan</small><strong>Pengajuan digital</strong></div>
                </article>
                <article class="auth-module">
                    <span class="auth-module-icon"><i class="bi bi-bar-chart-line" aria-hidden="true"></i></span>
                    <div><small>Administrasi</small><strong>Infal & payroll</strong></div>
                </article>
            </div>

            <footer class="auth-story-footer">
                <span><i class="bi bi-shield-check" aria-hidden="true"></i> Akses aman</span>
                <span><i class="bi bi-activity" aria-hidden="true"></i> Aktivitas tercatat</span>
            </footer>
        </section>
        <section class="auth-form-panel" aria-labelledby="login-title">
            <div class="auth-form-shell">
                <header class="auth-mobile-brand">
                    <span class="auth-brand-mark"><img src="{{ asset('images/logo-MI.png') }}" alt=""></span>
                    <span><strong>MI Lantaburo</strong><small>Sistem Absensi Guru</small></span>
                </header>

                <div class="auth-heading">
                    <p>Portal guru & pengelola</p>
                    <h2 id="login-title">Selamat datang kembali</h2>
                    <span>Masukkan username dan kata sandi Anda untuk melanjutkan.</span>
                </div>

                @if(session('success'))
                    <div class="auth-alert auth-alert-success" role="status">
                        <i class="bi bi-check-circle" aria-hidden="true"></i><span>{{ session('success') }}</span>
                    </div>
                @endif

                @if(request()->boolean('expired'))
                    <div class="auth-alert auth-alert-danger" role="alert">
                        <i class="bi bi-clock-history" aria-hidden="true"></i>
                        <span>Sesi Anda telah berakhir karena tidak ada aktivitas. Silakan login kembali.</span>
                    </div>
                @endif

                @if(session('error') || $errors->has('credentials'))
                    <div class="auth-alert auth-alert-danger" id="credentialsError" role="alert">
                        <i class="bi bi-exclamation-circle" aria-hidden="true"></i>
                        <span>{{ session('error') ?: $errors->first('credentials') }}</span>
                    </div>
                @endif

                <form action="{{ route('login.process') }}" method="POST" class="auth-form" id="loginForm">
                    @csrf

                    <div class="auth-field">
                        <label for="username">Username</label>
                        <div class="auth-input-wrap">
                            <i class="bi bi-person" aria-hidden="true"></i>
                            <input
                                type="text"
                                id="username"
                                name="username"
                                value="{{ old('username') }}"
                                placeholder="Masukkan username"
                                autocomplete="username"
                                autocapitalize="none"
                                spellcheck="false"
                                required
                                autofocus
                                @if($errors->has('username') || $errors->has('credentials')) aria-invalid="true" @endif
                                @if($errors->has('username')) aria-describedby="usernameError"
                                @elseif($errors->has('credentials')) aria-describedby="credentialsError" @endif>
                        </div>
                        @error('username')
                            <p class="auth-field-error" id="usernameError"><i class="bi bi-exclamation-circle" aria-hidden="true"></i>{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="auth-field">
                        <div class="auth-label-row">
                            <label for="passwordInput">Kata sandi</label>
                        </div>
                        <div class="auth-input-wrap">
                            <i class="bi bi-lock" aria-hidden="true"></i>
                            <input
                                type="password"
                                id="passwordInput"
                                name="password"
                                placeholder="Masukkan kata sandi"
                                autocomplete="current-password"
                                required
                                @if($errors->has('password') || $errors->has('credentials')) aria-invalid="true" @endif
                                @if($errors->has('password')) aria-describedby="passwordError"
                                @elseif($errors->has('credentials')) aria-describedby="credentialsError" @endif>
                            <button type="button" class="auth-password-toggle" id="passwordToggle" aria-label="Tampilkan kata sandi" aria-pressed="false" title="Tampilkan kata sandi">
                                <i class="bi bi-eye" id="passwordIcon" aria-hidden="true"></i>
                            </button>
                        </div>
                        @error('password')
                            <p class="auth-field-error" id="passwordError"><i class="bi bi-exclamation-circle" aria-hidden="true"></i>{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="auth-options">
                        <label class="auth-remember" for="remember">
                            <input type="checkbox" name="remember" id="remember" {{ old('remember') ? 'checked' : '' }}>
                            <span>Ingat saya</span>
                        </label>
                        <a href="{{ route('password.request') }}">Lupa kata sandi?</a>
                    </div>

                    @if($turnstileEnabled)
                        <div class="auth-security">
                            <div class="auth-security-label"><i class="bi bi-shield-lock" aria-hidden="true"></i><span>Verifikasi keamanan</span></div>
                            <div
                                class="cf-turnstile"
                                data-sitekey="{{ config('services.turnstile.site_key') }}"
                                data-theme="light"
                                data-size="flexible"
                                data-action="login"
                                data-callback="turnstileSuccess"
                                data-expired-callback="turnstileExpired"
                                data-error-callback="turnstileError">
                            </div>
                            <p
                                class="auth-field-error auth-security-status"
                                id="turnstileStatus"
                                role="alert"
                                aria-live="assertive"
                                @unless($errors->has('cf-turnstile-response')) hidden @endunless>{{ $errors->first('cf-turnstile-response') }}</p>
                        </div>
                    @endif

                    <button type="submit" id="loginButton" class="auth-submit" @if($turnstileEnabled) aria-describedby="submitHint" disabled @endif>
                        <span class="auth-submit-label">Masuk ke sistem</span>
                        <span class="auth-submit-spinner" aria-hidden="true"></span>
                        <i class="bi bi-arrow-right auth-submit-icon" aria-hidden="true"></i>
                    </button>
                    @if($turnstileEnabled)
                        <p class="auth-submit-hint" id="submitHint">Selesaikan verifikasi keamanan untuk mengaktifkan tombol masuk.</p>
                    @endif
                </form>

                <p class="auth-privacy"><i class="bi bi-lock" aria-hidden="true"></i> Aktivitas masuk dicatat untuk menjaga keamanan akun.</p>
            </div>
        </section>
    </main>
    <script nonce='{{ request()->attributes->get('csp_nonce') }}'>
        const passwordInput = document.getElementById('passwordInput');
        const passwordToggle = document.getElementById('passwordToggle');
        const passwordIcon = document.getElementById('passwordIcon');
        const loginForm = document.getElementById('loginForm');
        const loginButton = document.getElementById('loginButton');
        const loginButtonLabel = loginButton.querySelector('.auth-submit-label');
        const submitHint = document.getElementById('submitHint');
        const turnstileStatus = document.getElementById('turnstileStatus');

        passwordToggle.addEventListener('click', () => {
            const showPassword = passwordInput.type === 'password';
            passwordInput.type = showPassword ? 'text' : 'password';
            passwordIcon.classList.toggle('bi-eye', !showPassword);
            passwordIcon.classList.toggle('bi-eye-slash', showPassword);
            passwordToggle.setAttribute('aria-pressed', String(showPassword));
            passwordToggle.setAttribute('aria-label', showPassword ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi');
            passwordToggle.title = showPassword ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi';
            passwordInput.focus({ preventScroll: true });
        });

        @if($turnstileEnabled)
            function turnstileSuccess() {
                loginButton.disabled = false;
                submitHint.hidden = true;
                turnstileStatus.hidden = true;
                turnstileStatus.textContent = '';
            }

            function turnstileExpired() {
                loginButton.disabled = true;
                submitHint.hidden = false;
                turnstileStatus.textContent = 'Verifikasi keamanan telah kedaluwarsa. Silakan verifikasi kembali.';
                turnstileStatus.hidden = false;
            }

            function turnstileError(errorCode) {
                console.error('Turnstile error:', errorCode);
                loginButton.disabled = true;
                submitHint.hidden = false;
                turnstileStatus.textContent = 'Verifikasi keamanan gagal dimuat. Periksa koneksi lalu coba lagi.';
                turnstileStatus.hidden = false;
            }
        @endif

        loginForm.addEventListener('submit', () => {
            loginForm.setAttribute('aria-busy', 'true');
            loginButton.disabled = true;
            loginButton.classList.add('is-loading');
            loginButtonLabel.textContent = 'Sedang masuk...';
            if (submitHint) {
                submitHint.hidden = true;
            }
        });

        const firstInvalidField = loginForm.querySelector('[aria-invalid="true"]');
        if (firstInvalidField) {
            firstInvalidField.focus();
        }
    </script>
    @include('partials.pwa-body')
    @if($turnstileEnabled)
        <script nonce='{{ request()->attributes->get('csp_nonce') }}' src="https://challenges.cloudflare.com/turnstile/v0/api.js" defer></script>
    @endif
</body>
</html>
