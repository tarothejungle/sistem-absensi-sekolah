<?php

namespace App\Console\Commands;

use App\Jobs\SendWebPushNotification;
use App\Models\PushAttendanceReminder;
use App\Models\PushSubscription;
use App\Models\User;
use App\Services\AppNotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class SendAttendancePushReminders extends Command
{
    protected $signature = 'notifications:send-attendance-reminders';

    protected $description = 'Kirim pengingat push check-in/check-out tanpa duplikasi';

    public function handle(): int
    {
        if (! config('pwa.push_enabled') || ! Schema::hasTable('push_attendance_reminders')) {
            $this->info('Push reminder tidak aktif.');

            return self::SUCCESS;
        }

        $sent = 0;
        User::query()
            ->whereIn('role', User::ATTENDANCE_ROLES)
            ->where('status', 'aktif')
            ->with('teacher')
            ->chunkById(100, function ($users) use (&$sent): void {
                foreach ($users as $user) {
                    $reminder = AppNotificationService::attendanceReminderForUser($user)->first();
                    if (! $reminder || ! isset($reminder->session_id, $reminder->reminder_date, $reminder->reminder_type)) {
                        continue;
                    }

                    if (! PushSubscription::where('user_id', $user->id)->exists()) {
                        continue;
                    }

                    $delivery = PushAttendanceReminder::firstOrCreate([
                        'user_id' => $user->id,
                        'attendance_session_id' => $reminder->session_id,
                        'reminder_date' => $reminder->reminder_date,
                        'reminder_type' => $reminder->reminder_type,
                    ]);

                    $retryAfter = max(60, (int) config('pwa.push_retry_after_seconds', 300));
                    if ($delivery->sent_at || ($delivery->last_attempted_at && $delivery->last_attempted_at->gt(now()->subSeconds($retryAfter)))) {
                        continue;
                    }

                    $delivery->forceFill([
                        'last_attempted_at' => now(),
                        'attempt_count' => ((int) $delivery->attempt_count) + 1,
                    ])->save();

                    SendWebPushNotification::dispatch($user->id, [
                        'title' => $reminder->title,
                        'body' => $reminder->message,
                        'type' => 'attendance',
                        'url' => $reminder->url ?: route('attendance.index', [], false),
                        'icon' => '/icons/icon-192.png',
                        'badge' => '/icons/icon-192.png',
                        'tag' => 'attendance-'.$reminder->reminder_type.'-'.$reminder->session_id.'-'.$reminder->reminder_date,
                    ], $delivery->id);
                    $sent++;
                }
            });

        $this->info("{$sent} pengingat attendance dijadwalkan.");

        return self::SUCCESS;
    }
}
