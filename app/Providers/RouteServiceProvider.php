<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * The path to your application's "home" route.
     *
     * Typically, users are redirected here after authentication.
     *
     * @var string
     */
    public const HOME = '/dashboard';

    /**
     * Define your route model bindings, pattern filters, and other route configuration.
     */
    public function boot(): void
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('pwa-push', function (Request $request) {
            return Limit::perMinute(max(1, (int) config('pwa.push_rate_limit_per_minute', 10)))
                ->by((string) ($request->user()?->getAuthIdentifier() ?: $request->ip()));
        });

        $actorKey = static fn (Request $request): string => (string) ($request->user()?->getAuthIdentifier() ?: $request->ip());
        $registerBiometricLimiter = static function (
            string $name,
            int $maxAttempts,
            string $message,
        ) use ($actorKey): void {
            RateLimiter::for($name, static function (Request $request) use ($actorKey, $maxAttempts, $message) {
                return Limit::perMinute(max(1, $maxAttempts))
                    ->by($actorKey($request))
                    ->response(static function (Request $request, array $headers) use ($message) {
                        $retryAfter = max(1, (int) ($headers['Retry-After'] ?? 60));

                        return response()->json([
                            'ok' => false,
                            'code' => 'rate_limited',
                            'message' => $message.' Tunggu '.$retryAfter.' detik lalu coba kembali.',
                            'retry_after' => $retryAfter,
                        ], 429, $headers);
                    });
            });
        };

        $registerBiometricLimiter(
            'biometric-status',
            (int) config('biometrics.status_rate_limit_per_minute', 60),
            'Pemeriksaan status biometrik dilakukan terlalu sering.',
        );
        $registerBiometricLimiter(
            'liveness-challenge',
            (int) config('biometrics.challenge_rate_limit_per_minute', 15),
            'Pembuatan challenge liveness dilakukan terlalu sering.',
        );
        $registerBiometricLimiter(
            'face-enrollment',
            (int) config('biometrics.enrollment_rate_limit_per_minute', 8),
            'Percobaan pendaftaran wajah dilakukan terlalu sering.',
        );
        $registerBiometricLimiter(
            'attendance-check-in',
            (int) config('biometrics.attendance_rate_limit_per_minute', 10),
            'Percobaan check-in dilakukan terlalu sering.',
        );
        $registerBiometricLimiter(
            'attendance-check-out',
            (int) config('biometrics.attendance_rate_limit_per_minute', 10),
            'Percobaan check-out dilakukan terlalu sering.',
        );

        $this->routes(function () {
            Route::middleware('api')
                ->prefix('api')
                ->group(base_path('routes/api.php'));

            Route::middleware('web')
                ->group(base_path('routes/web.php'));
        });
    }
}
