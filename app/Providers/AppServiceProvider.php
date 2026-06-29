<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\URL;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\View;
use App\Services\AppNotificationService;
use Illuminate\Support\Facades\Schema;

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