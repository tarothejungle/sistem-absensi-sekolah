<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use App\Models\LoginActivity;

class AuthController extends Controller
{
    public function showLogin()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $validated = $request->validate([
            'nip' => ['required', 'string'],
            'password' => ['required', 'string'],
            'cf-turnstile-response' => ['required', 'string'],
        ], [
            'nip.required' => 'Username wajib diisi.',
            'password.required' => 'Password wajib diisi.',
            'cf-turnstile-response.required' =>
                'Silakan selesaikan verifikasi keamanan terlebih dahulu.',
        ]);

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
                'cf-turnstile-response' =>
                    'Layanan verifikasi keamanan sedang tidak dapat dihubungi. Silakan coba kembali.',
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
                'cf-turnstile-response' =>
                    'Verifikasi keamanan gagal atau telah kedaluwarsa. Silakan coba kembali.',
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | Proses autentikasi
        |--------------------------------------------------------------------------
        */

        $loginSuccess = Auth::attempt([
            'nip' => $validated['nip'],
            'password' => $validated['password'],
            'status' => 'aktif',
        ], $request->boolean('remember'));

        if (! $loginSuccess) {
            return back()
                ->withErrors([
                    'nip' => 'Username atau password tidak sesuai.',
                ])
                ->onlyInput('nip');
        }

        $request->session()->regenerate();

        $user = Auth::user();

        $user->update([
            'last_login' => now(),
        ]);

        LoginActivity::create([
            'user_id' => $user->id,
            'name' => $user->name ?? $user->nip,
            'role' => $user->role,
            'activity' => 'login ke dalam aplikasi',
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return redirect()
            ->route('dashboard')
            ->with('success', 'Login berhasil.');
    }

    public function logout(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}