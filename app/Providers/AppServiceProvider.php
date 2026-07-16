<?php

namespace App\Providers;

use App\Services\AppNotificationService;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Paginator::useBootstrapFive();

        if (env('FORCE_HTTPS', false)) {
            URL::forceScheme('https');
        }

        // Normalisasi config rawan salah-tulis di .env produksi (mis.
        // SESSION_DOMAIN=https://domain/ ) agar tidak membekukan sesi/CSRF.
        // Helper App\Support\ConfigSanitizer sengaja tidak disertakan di repository
        // publik; bila tersedia, normalisasi otomatis dijalankan.
        if (class_exists(\App\Support\ConfigSanitizer::class)) {
            $sanitizer = \App\Support\ConfigSanitizer::class;

            config([
                'app.url' => $sanitizer::trimUrl(config('app.url')) ?? config('app.url'),
                'session.domain' => $sanitizer::sessionDomain(config('session.domain')),
                'cors.allowed_origins' => $sanitizer::corsOrigins(config('cors.allowed_origins')),
            ]);
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
