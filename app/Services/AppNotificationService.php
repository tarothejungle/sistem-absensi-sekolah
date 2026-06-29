<?php

namespace App\Services;

use App\Models\AppNotification;
use App\Models\Attendance;
use App\Models\AttendanceSession;
use App\Models\User;
use App\Models\WorkSchedule;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class AppNotificationService
{
    public static function send(int $userId, string $title, ?string $message = null, string $type = 'info', ?string $url = null): void
    {
        if (!Schema::hasTable('app_notifications')) {
            return;
        }

        AppNotification::create([
            'user_id' => $userId,
            'title' => $title,
            'message' => $message,
            'type' => $type,
            'url' => $url,
        ]);
    }

    public static function sendToRoles(array $roles, string $title, ?string $message = null, string $type = 'info', ?string $url = null): void
    {
        if (!Schema::hasTable('app_notifications')) {
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
        if (!Schema::hasTable('app_notifications')) {
            return self::attendanceReminder($user);
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

        return self::attendanceReminder($user)
            ->merge($stored)
            ->take($limit)
            ->values();
    }

    public static function unreadCount(User $user): int
    {
        if (!Schema::hasTable('app_notifications')) {
            return self::attendanceReminder($user)->count();
        }

        return AppNotification::where('user_id', $user->id)
            ->whereNull('read_at')
            ->count() + self::attendanceReminder($user)->count();
    }

    private static function attendanceReminder(User $user): Collection
    {
        if (!in_array($user->role, ['guru', 'bendahara', 'kepala_sekolah'], true)) {
            return collect();
        }

        $teacher = $user->teacher;

        if (!$teacher) {
            return collect();
        }

        $today = now('Asia/Jakarta')->toDateString();
        $nowTime = now('Asia/Jakarta')->format('H:i:s');
        $hariIni = self::todayHari();

        $hasAnySchedule = WorkSchedule::where('teacher_id', $teacher->id)->exists();
        $todaySchedule = WorkSchedule::where('teacher_id', $teacher->id)
            ->where('hari', $hariIni)
            ->where('status', 'aktif')
            ->first();

        if ($hasAnySchedule && !$todaySchedule) {
            return collect();
        }

        $sessions = $teacher->attendanceSessions()
            ->where('attendance_sessions.status', 'aktif')
            ->orderBy('attendance_sessions.jam_masuk')
            ->get();

        if ($sessions->isEmpty() && $teacher->attendance_session_id) {
            $oldSession = AttendanceSession::where('id', $teacher->attendance_session_id)
                ->where('status', 'aktif')
                ->first();

            if ($oldSession) {
                $sessions = collect([$oldSession]);
            }
        }

        foreach ($sessions as $session) {
            $attendance = Attendance::where('teacher_id', $teacher->id)
                ->where('attendance_session_id', $session->id)
                ->whereDate('tanggal', $today)
                ->first();

            $canCheckIn = self::isTimeInRange($nowTime, $session->batas_check_in_mulai, $session->batas_check_in_selesai)
                && (!$attendance || !$attendance->check_in_time);

            if ($canCheckIn) {
                return collect([
                    (object) [
                        'id' => null,
                        'title' => 'Waktunya Check-in',
                        'message' => 'Silakan melakukan check-in untuk ' . $session->nama_sesi . '.',
                        'type' => 'attendance',
                        'url' => route('attendance.index'),
                        'read_at' => null,
                        'created_at' => now(),
                        'is_dynamic' => true,
                    ],
                ]);
            }

            $canCheckOut = $attendance
                && $attendance->check_in_time
                && !$attendance->check_out_time
                && self::isTimeInRange($nowTime, $session->batas_check_out_mulai, $session->batas_check_out_selesai);

            if ($canCheckOut) {
                return collect([
                    (object) [
                        'id' => null,
                        'title' => 'Waktunya Check-out',
                        'message' => 'Silakan melakukan check-out untuk ' . $session->nama_sesi . '.',
                        'type' => 'attendance',
                        'url' => route('attendance.index'),
                        'read_at' => null,
                        'created_at' => now(),
                        'is_dynamic' => true,
                    ],
                ]);
            }
        }

        return collect();
    }

    private static function isTimeInRange(string $current, string $start, string $end): bool
    {
        $current = Carbon::parse($current);
        $start = Carbon::parse($start);
        $end = Carbon::parse($end);

        if ($start->lte($end)) {
            return $current->betweenIncluded($start, $end);
        }

        return $current->gte($start) || $current->lte($end);
    }

    private static function todayHari(): string
    {
        $dayMap = [
            'monday' => 'senin',
            'tuesday' => 'selasa',
            'wednesday' => 'rabu',
            'thursday' => 'kamis',
            'friday' => 'jumat',
            'saturday' => 'sabtu',
            'sunday' => 'minggu',
        ];

        return $dayMap[strtolower(now('Asia/Jakarta')->englishDayOfWeek)] ?? 'senin';
    }
}
