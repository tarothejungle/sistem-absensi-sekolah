<?php

namespace App\Providers;

use App\Services\AppNotificationService;
use App\Support\ConfigSanitizer;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use RuntimeException;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Paginator::useBootstrapFive();

        if (config('security.force_https', false)) {
            URL::forceScheme('https');
        }

        // Normalisasi config rawan salah-tulis di .env produksi (mis.
        // SESSION_DOMAIN=https://domain/ ) agar tidak membekukan sesi/CSRF.
        config([
            'app.url' => ConfigSanitizer::trimUrl(config('app.url')) ?? config('app.url'),
            'session.domain' => ConfigSanitizer::sessionDomain(config('session.domain')),
            'cors.allowed_origins' => ConfigSanitizer::corsOrigins(config('cors.allowed_origins')),
        ]);

        if (app()->environment('production')) {
            $unsafeProductionSettings = [];

            if (config('app.debug')) {
                $unsafeProductionSettings[] = 'APP_DEBUG=false';
            }
            if (! config('security.force_https') || ! str_starts_with((string) config('app.url'), 'https://')) {
                $unsafeProductionSettings[] = 'FORCE_HTTPS=true dan APP_URL=https://...';
            }
            if (! config('session.secure')) {
                $unsafeProductionSettings[] = 'SESSION_SECURE_COOKIE=true';
            }
            if (! config('session.encrypt')) {
                $unsafeProductionSettings[] = 'SESSION_ENCRYPT=true';
            }
            if (! config('biometrics.liveness_required')) {
                $unsafeProductionSettings[] = 'FACE_LIVENESS_REQUIRED=true';
            }
            if (! config('services.turnstile.enabled')) {
                $unsafeProductionSettings[] = 'TURNSTILE_ENABLED=true';
            }

            if ($unsafeProductionSettings) {
                throw new RuntimeException(
                    'Konfigurasi produksi tidak aman: '.implode(', ', $unsafeProductionSettings)
                );
            }
        }

        View::composer('layouts.app', function ($view) {
            $user = auth()->user();

            $view->with('appNotifications', collect());
            $view->with('appUnreadNotificationCount', 0);

            if ($user && Schema::hasTable('app_notifications')) {
                $view->with('appNotifications', AppNotificationService::forUser($user));
                $view->with('appUnreadNotificationCount', AppNotificationService::unreadCount($user));
            }
        });
    }
}
