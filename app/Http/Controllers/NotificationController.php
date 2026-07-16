<?php

namespace App\Http\Controllers;

use App\Models\AppNotification;
use Illuminate\Http\RedirectResponse;

class NotificationController extends Controller
{
    public function read(AppNotification $notification): RedirectResponse
    {
        abort_unless((int) $notification->user_id === (int) auth()->id(), 403);

        if (!$notification->read_at) {
            $notification->update(['read_at' => now()]);
        }

        return redirect($this->safeRedirectUrl($notification->url));
    }

    public function readAll(): RedirectResponse
    {
        AppNotification::where('user_id', auth()->id())
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return back()->with('success', 'Semua notifikasi sudah ditandai dibaca.');
    }

    private function safeRedirectUrl(?string $url): string
    {
        if (!$url) {
            return route('dashboard');
        }

        $url = trim($url);

        if (str_starts_with($url, '/')) {
            return $url;
        }

        $appHost = parse_url(config('app.url'), PHP_URL_HOST);
        $targetHost = parse_url($url, PHP_URL_HOST);

        if ($targetHost && $appHost && strcasecmp($targetHost, $appHost) === 0) {
            return $url;
        }

        return route('dashboard');
    }
}
