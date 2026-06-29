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

        return redirect($notification->url ?: route('dashboard'));
    }

    public function readAll(): RedirectResponse
    {
        AppNotification::where('user_id', auth()->id())
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return back()->with('success', 'Semua notifikasi sudah ditandai dibaca.');
    }
}
