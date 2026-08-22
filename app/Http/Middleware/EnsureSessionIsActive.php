<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureSessionIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $guard = Auth::guard();

        if (! $guard->check()) {
            return $next($request);
        }

        // Let the dedicated active-account middleware produce its own message.
        if ($guard->user()->status !== 'aktif') {
            return $next($request);
        }

        $timeoutSeconds = max(60, (int) config('session.idle_timeout', 10) * 60);
        $lastActivity = (int) $request->session()->get('auth.last_activity_at', 0);
        $restoredFromRememberCookie = $guard->viaRemember() && $lastActivity === 0;
        $hasExpired = $lastActivity > 0 && (time() - $lastActivity) >= $timeoutSeconds;

        if ($restoredFromRememberCookie || $hasExpired) {
            $guard->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Sesi Anda telah berakhir karena tidak ada aktivitas.',
                    'redirect' => route('login', ['expired' => 1]),
                ], 401);
            }

            return redirect()->route('login', ['expired' => 1]);
        }

        $passwordHash = (string) $guard->user()->getAuthPassword();
        $storedPasswordHash = $request->session()->get('auth.password_hash');
        $storedPasswordUserId = $request->session()->get('auth.password_user_id');
        if ($storedPasswordUserId !== null
            && (int) $storedPasswordUserId === (int) $guard->id()
            && $storedPasswordHash !== null
            && ! hash_equals((string) $storedPasswordHash, $passwordHash)) {
            $guard->logoutCurrentDevice();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Sesi Anda telah berakhir karena password berubah. Silakan login kembali.',
                    'redirect' => route('login', ['expired' => 1]),
                ], 401);
            }

            return redirect()->route('login', ['expired' => 1]);
        }

        $request->session()->put([
            'auth.password_hash' => $passwordHash,
            'auth.password_user_id' => $guard->id(),
        ]);

        $request->session()->put('auth.last_activity_at', time());

        return $next($request);
    }
}
