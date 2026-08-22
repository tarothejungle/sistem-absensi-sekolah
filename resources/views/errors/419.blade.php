<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#f6f4ef">
    <title>Sesi Berakhir - Sistem Absensi Sekolah</title>
    @include('partials.pwa-head')
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            display: grid;
            place-items: center;
            padding: 24px;
            color: #172033;
            background: radial-gradient(circle at 12% 8%, rgba(0, 19, 98, .09), transparent 28rem), linear-gradient(145deg, #f8f5ee, #f3f6fb);
            font-family: "Plus Jakarta Sans", sans-serif;
        }
        .expired-card {
            width: min(100%, 620px);
            padding: clamp(28px, 6vw, 52px);
            text-align: center;
            background: #fffdf8;
            border: 1px solid #e4dfd5;
            border-radius: 28px;
            box-shadow: 0 26px 70px rgba(23, 32, 51, .12);
        }
        .expired-brand {
            display: inline-flex;
            align-items: center;
            gap: 11px;
            margin-bottom: 32px;
            font-weight: 800;
        }
        .expired-brand img { width: 44px; height: 44px; object-fit: contain; }
        .expired-code {
            margin: 0 0 10px;
            color: #001362;
            font-size: clamp(4rem, 16vw, 7rem);
            line-height: .9;
            letter-spacing: -.09em;
            font-weight: 800;
        }
        h1 { margin: 0; font-size: clamp(1.55rem, 5vw, 2.2rem); letter-spacing: -.035em; }
        .expired-copy { max-width: 470px; margin: 16px auto 28px; color: #677085; line-height: 1.7; }
        .expired-action {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            width: min(100%, 310px);
            padding: 14px 20px;
            color: #fff;
            background: #001362;
            border-radius: 13px;
            text-decoration: none;
            font-weight: 700;
            box-shadow: 0 12px 28px rgba(0, 19, 98, .22);
        }
        .expired-action:hover, .expired-action:focus-visible { color: #fff; background: #112b8b; outline: 3px solid rgba(17, 43, 139, .18); outline-offset: 3px; }
        .expired-redirect { margin: 18px 0 0; color: #677085; font-size: .88rem; }
    </style>
</head>
<body>
    @php($loginUrl = $loginUrl ?? route('login', ['expired' => 1]))
    <main class="expired-card">
        <div class="expired-brand">
            <img src="{{ asset('images/logo-MI.png') }}" alt="">
            <span>MI Lantaburo</span>
        </div>
        <p class="expired-code" aria-label="Kode error 419">419</p>
        <h1>Sesi Anda telah berakhir</h1>
        <p class="expired-copy">Demi keamanan akun, sesi ditutup setelah tidak ada aktivitas. Silakan masuk kembali untuk melanjutkan pekerjaan Anda.</p>
        <a class="expired-action" href="{{ $loginUrl }}">
            <i class="bi bi-box-arrow-in-right" aria-hidden="true"></i>
            Kembali ke Halaman Login
        </a>
        <p class="expired-redirect" aria-live="polite">Anda akan diarahkan otomatis dalam <strong id="redirectCountdown">5</strong> detik.</p>
    </main>
    <script nonce='{{ request()->attributes->get('csp_nonce') }}'>
        (function() {
            var redirectUrl = @json($loginUrl);
            var countdown = document.getElementById('redirectCountdown');
            var remaining = 5;
            window.setInterval(function() {
                remaining -= 1;
                countdown.textContent = Math.max(remaining, 0);
                if (remaining <= 0) {
                    window.location.replace(redirectUrl);
                }
            }, 1000);
        })();
    </script>
</body>
</html>
