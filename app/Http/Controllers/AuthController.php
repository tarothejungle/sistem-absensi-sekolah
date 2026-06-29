<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\LoginActivity;

class AuthController extends Controller
{
    public function showLogin()
    {
        return view('auth.login');
    }

    public function login(Request $request)
{
    $credentials = $request->validate([
        'nip' => 'required',
        'password' => 'required',
    ]);

    $remember = $request->boolean('remember');

    $loginSuccess = Auth::attempt([
        'nip' => $credentials['nip'],
        'password' => $credentials['password'],
        'status' => 'aktif',
    ], $remember);

    if ($loginSuccess) {
        $request->session()->regenerate();

        $user = auth()->user();

        $user->update([
            'last_login' => now(),
        ]);

        LoginActivity::create([
            'user_id' => $user->id,
            'name' => $user->name ?? $user->nip,
            'role' => $user->role,
            'activity' => 'login kedalam aplikasi',
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return redirect()
            ->route('dashboard')
            ->with('success', 'Login berhasil.');
    }

    return back()
        ->withErrors([
            'nip' => 'NIP atau password tidak sesuai.',
        ])
        ->onlyInput('nip');
}

    public function logout(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}