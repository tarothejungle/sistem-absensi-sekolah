<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Lupa Password - Sistem Absensi Guru</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link 
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" 
        rel="stylesheet"
    >

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

        .forgot-card {
            width: 390px;
            background: #fff;
            padding: 30px 26px;
            border-radius: 18px;
            box-shadow: 0 12px 28px rgba(0, 19, 98, 0.18);
        }

        .forgot-title {
            text-align: center;
            color: #001362;
            font-weight: 700;
            font-size: 24px;
            margin-bottom: 8px;
        }

        .forgot-subtitle {
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

        .back-link {
            display: block;
            text-align: center;
            margin-top: 16px;
            color: #001362;
            text-decoration: none;
            font-weight: 600;
        }
    </style>
</head>
<body>

<div class="forgot-card">
    <div class="forgot-title">Lupa Password</div>
    <div class="forgot-subtitle">
        Masukkan email akun Anda. Sistem akan mengirim link reset password.
    </div>

    @if(session('success'))
        <div class="alert alert-success">
            {{ session('success') }}
        </div>
    @endif

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

    <form action="{{ route('password.email') }}" method="POST">
        @csrf

        <div class="mb-3">
            <label class="form-label">Email</label>
            <input 
                type="email" 
                name="email" 
                class="form-control" 
                value="{{ old('email') }}" 
                placeholder="Masukkan email akun"
                required
            >
        </div>

        <button type="submit" class="btn btn-primary btn-submit w-100">
            Kirim Link Reset Password
        </button>
    </form>

    <a href="{{ route('login') }}" class="back-link">
        Kembali ke Login
    </a>
</div>

</body>
</html>