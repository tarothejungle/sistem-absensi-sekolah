<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Reset Password - Sistem Absensi Sekolah</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link 
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" 
        rel="stylesheet"
    >
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
        body {
            min-height: 100vh;
            margin: 0;
            background: #f3f4f6;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: Plus Jakarta Sans, sans-serif;
        }

        .reset-card {
            width: 390px;
            background: #fff;
            padding: 30px 26px;
            border-radius: 18px;
            box-shadow: 0 12px 28px rgba(0, 19, 98, 0.18);
        }

        .reset-title {
            text-align: center;
            color: #001362;
            font-weight: 700;
            font-size: 24px;
            margin-bottom: 8px;
        }

        .reset-subtitle {
            text-align: center;
            color: #6b7280;
            font-size: 14px;
            margin-bottom: 24px;
        }

        .form-label {
            font-weight: 600;
            color: #001362;
        }

        .form-control {
            height: 48px;
            border-radius: 12px;
        }

        .btn-submit {
            height: 48px;
            border-radius: 12px;
            background: #001362;
            border: none;
            font-weight: 600;
        }

        .btn-submit:hover {
            background: #00104f;
        }
    </style>
    <link href="{{ asset('css/absensi-ui-final.css') }}?v=20260702-form-alert-polish" rel="stylesheet">
</head>
<body>

<div class="reset-card">
    <img src="{{ asset('images/logo-MI.png') }}" alt="Logo MI" class="auth-mini-logo">
    <div class="reset-title">Reset Password</div>
    <div class="reset-subtitle">
        Masukkan password baru untuk akun Anda.
    </div>

    @if(session('error'))
        <div class="alert alert-danger">
            {{ session('error') }}
        </div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger">
            {{ $errors->first() }}
        </div>
    @endif

    <form action="{{ route('password.update') }}" method="POST">
        @csrf

        <input type="hidden" name="token" value="{{ $token }}">

        <div class="mb-3">
            <label class="form-label">Email</label>
            <input 
                type="email" 
                name="email" 
                class="form-control" 
                value="{{ old('email', $email) }}" 
                required
            >
        </div>

        <div class="mb-3">
            <label class="form-label">Password Baru</label>
            <input 
                type="password" 
                name="password" 
                class="form-control" 
                placeholder="Masukkan password baru"
                required
            >
        </div>

        <div class="mb-3">
            <label class="form-label">Konfirmasi Password Baru</label>
            <input 
                type="password" 
                name="password_confirmation" 
                class="form-control" 
                placeholder="Ulangi password baru"
                required
            >
        </div>

        <button type="submit" class="btn btn-primary btn-submit w-100">
            <i class="bi bi-check2-circle"></i>
            Simpan Password Baru
        </button>
    </form>
</div>

</body>
</html>
