<?php

namespace App\Services;

use App\Jobs\SendWebPushNotification;
use App\Models\AppNotification;
use App\Models\Attendance;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class AppNotificationService
{
    public static function send(int $userId, string $title, ?string $message = null, string $type = 'info', ?string $url = null): void
    {
        if (! Schema::hasTable('app_notifications')) {
            return;
        }

        $notification = AppNotification::create([
            'user_id' => $userId,
            'title' => $title,
            'message' => $message,
            'type' => $type,
            'url' => $url,
        ]);

        if (config('pwa.push_enabled') && Schema::hasTable('push_subscriptions')) {
            SendWebPushNotification::dispatch($userId, [
                'title' => $notification->title,
                'body' => $notification->message,
                'type' => $notification->type,
                'url' => self::pushUrl($notification->url),
                'icon' => '/icons/icon-192.png',
                'badge' => '/icons/icon-192.png',
            ]);
        }
    }

    public static function sendToRoles(array $roles, string $title, ?string $message = null, string $type = 'info', ?string $url = null): void
    {
        if (! Schema::hasTable('app_notifications')) {
            return;
        }

        User::whereIn('role', $roles)
            ->where('status', 'aktif')
            ->pluck('id')
            ->each(function ($userId) use ($title, $message, $type, $url) {
                self::send((int) $userId, $title, $message, $type, $url);
            });
    }

    public static function forUser(User $user, int $limit = 8): Collection
    {
        if (! Schema::hasTable('app_notifications')) {
            return self::attendanceReminderForUser($user);
        }

        $stored = AppNotification::where('user_id', $user->id)
            ->latest()
            ->limit($limit)
            ->get()
            ->map(function (AppNotification $notification) {
                return (object) [
                    'id' => $notification->id,
                    'title' => $notification->title,
                    'message' => $notification->message,
                    'type' => $notification->type,
                    'url' => $notification->url,
                    'read_at' => $notification->read_at,
                    'created_at' => $notification->created_at,
                    'is_dynamic' => false,
                ];
            });

        return self::attendanceReminderForUser($user)
            ->merge($stored)
            ->take($limit)
            ->values();
    }

    public static function unreadCount(User $user): int
    {
        if (! Schema::hasTable('app_notifications')) {
            return self::attendanceReminderForUser($user)->count();
        }

        return AppNotification::where('user_id', $user->id)
            ->whereNull('read_at')
            ->count() + self::attendanceReminderForUser($user)->count();
    }

    public static function attendanceReminderForUser(User $user): Collection
    {
        if (! $user->hasAttendanceAccess()) {
            return collect();
        }

        $teacher = $user->teacher;

        if (! $teacher) {
            return collect();
        }

        $now = now(config('app.timezone'));
        $resolver = app(WorkScheduleResolver::class);
        $windowService = app(AttendanceWindowService::class);
        $dates = [$now->copy()->startOfDay(), $now->copy()->startOfDay()->subDay(), $now->copy()->startOfDay()->addDay()];

        foreach ($dates as $date) {
            foreach ($resolver->sessionsForDate($teacher, $date) as $session) {
                $attendance = Attendance::where('teacher_id', $teacher->id)
                    ->where('attendance_session_id', $session->id)
                    ->whereDate('tanggal', $date->toDateString())
                    ->first();

                $canCheckIn = $windowService->isCheckInOpen($session, $now, $date)
                    && (! $attendance || ! $attendance->check_in_time);

                if ($canCheckIn) {
                    return collect([
                        (object) [
                            'id' => null,
                            'session_id' => $session->id,
                            'reminder_date' => $date->toDateString(),
                            'reminder_type' => 'check_in',
                            'title' => 'Waktunya Check-in',
                            'message' => 'Silakan melakukan check-in untuk '.$session->nama_sesi.'.',
                            'type' => 'attendance',
                            'url' => route('attendance.index', [], false),
                            'read_at' => null,
                            'created_at' => now(),
                            'is_dynamic' => true,
                        ],
                    ]);
                }

                $canCheckOut = $attendance
                    && $attendance->check_in_time
                    && ! $attendance->check_out_time
                    && $windowService->isCheckOutOpen($session, $now, $date);

                if ($canCheckOut) {
                    return collect([
                        (object) [
                            'id' => null,
                            'session_id' => $session->id,
                            'reminder_date' => $date->toDateString(),
                            'reminder_type' => 'check_out',
                            'title' => 'Waktunya Check-out',
                            'message' => 'Silakan melakukan check-out untuk '.$session->nama_sesi.'.',
                            'type' => 'attendance',
                            'url' => route('attendance.index', [], false),
                            'read_at' => null,
                            'created_at' => now(),
                            'is_dynamic' => true,
                        ],
                    ]);
                }
            }
        }

        return collect();
    }

    private static function pushUrl(?string $url): string
    {
        if (! $url) {
            return route('dashboard', [], false);
        }

        if (str_starts_with($url, '/')) {
            return $url;
        }

        $parts = parse_url($url);
        $targetHost = $parts['host'] ?? null;
        $appHost = parse_url((string) config('app.url'), PHP_URL_HOST);
        if ($parts === false || ($targetHost && $appHost && strcasecmp($targetHost, $appHost) !== 0)) {
            return route('dashboard', [], false);
        }

        $relative = $parts['path'] ?? route('dashboard', [], false);
        if (isset($parts['query'])) {
            $relative .= '?'.$parts['query'];
        }

        return $relative;
    }

}
