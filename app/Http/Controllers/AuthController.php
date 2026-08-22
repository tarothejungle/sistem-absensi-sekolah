<?php

namespace App\Http\Controllers;

use App\Models\LoginActivity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function showLogin()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $rules = [
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ];

        if (config('services.turnstile.enabled')) {
            $rules['cf-turnstile-response'] = ['required', 'string'];
        }

        $validated = $request->validate($rules, [
            'username.required' => 'Masukkan username Anda.',
            'password.required' => 'Masukkan kata sandi Anda.',
            'cf-turnstile-response.required' => 'Silakan selesaikan verifikasi keamanan terlebih dahulu.',
        ]);

        if (config('services.turnstile.enabled')) {
            $this->validateTurnstile($request);
        }

        /*
        |--------------------------------------------------------------------------
        | Proses autentikasi
        |--------------------------------------------------------------------------
        */

        $loginSuccess = Auth::attempt([
            'username' => $validated['username'],
            'password' => $validated['password'],
            'status' => 'aktif',
        ], $request->boolean('remember'));

        if (! $loginSuccess) {
            return back()
                ->withErrors([
                    'credentials' => 'Username atau kata sandi tidak cocok. Periksa kembali lalu coba lagi.',
                ])
                ->onlyInput('username');
        }

        $request->session()->regenerate();
        $request->session()->put('auth.last_activity_at', time());

        $user = Auth::user();

        $user->update([
            'last_login' => now(),
        ]);

        LoginActivity::create([
            'user_id' => $user->id,
            'name' => $user->name ?? $user->username,
            'role' => $user->role,
            'activity' => 'login ke dalam aplikasi',
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return redirect()
            ->route('dashboard')
            ->with('success', 'Login berhasil.');
    }

    private function validateTurnstile(Request $request): void
    {
        try {
            $response = Http::asForm()
                ->connectTimeout(5)
                ->timeout(10)
                ->post(
                    'https://challenges.cloudflare.com/turnstile/v0/siteverify',
                    [
                        'secret' => config('services.turnstile.secret_key'),
                        'response' => $request->input('cf-turnstile-response'),
                        'remoteip' => $request->ip(),
                    ]
                );

            $result = $response->successful()
                ? $response->json()
                : [];
        } catch (\Throwable $exception) {
            report($exception);

            throw ValidationException::withMessages([
                'cf-turnstile-response' => 'Layanan verifikasi keamanan sedang tidak dapat dihubungi. Silakan coba kembali.',
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | Validasi hasil Turnstile
        |--------------------------------------------------------------------------
        */

        $turnstileValid = ($result['success'] ?? false) === true;

        if (app()->environment('production')) {
            $turnstileValid =
                $turnstileValid
                && ($result['hostname'] ?? null)
                    === config('services.turnstile.hostname')
                && ($result['action'] ?? null) === 'login';
        }

        if (! $turnstileValid) {
            logger()->warning('Turnstile validation failed', [
                'hostname' => $result['hostname'] ?? null,
                'action' => $result['action'] ?? null,
                'error_codes' => $result['error-codes'] ?? [],
                'ip_address' => $request->ip(),
            ]);

            throw ValidationException::withMessages([
                'cf-turnstile-response' => 'Verifikasi keamanan gagal atau telah kedaluwarsa. Silakan coba kembali.',
            ]);
        }
    }

    public function heartbeat()
    {
        return response()->noContent();
    }

    public function logout(Request $request)
    {
        $expired = $request->input('reason') === 'idle';

        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($request->expectsJson()) {
            return response()->noContent();
        }

        return redirect()->route('login', $expired ? ['expired' => 1] : []);
    }
}
