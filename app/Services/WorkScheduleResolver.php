<?php

namespace App\Services;

use App\Models\AttendanceSession;
use App\Models\DutySchedule;
use App\Models\HolidaySetting;
use App\Models\Teacher;
use App\Models\WorkSchedule;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class WorkScheduleResolver
{
    public function sessionsForDate(Teacher $teacher, $date = null): Collection
    {
        $targetDate = $this->parseDate($date);
        $regularSessions = $this->regularSchedulesForDate($teacher, $targetDate)
            ->pluck('attendanceSession')->filter();
        $dutySessions = $this->dutySessionsForDate($teacher, $targetDate);

        if ($regularSessions->isEmpty() && ! WorkSchedule::where('teacher_id', $teacher->id)->exists()) {
            $regularSessions = $teacher->attendanceSessions()
                ->where('attendance_sessions.status', 'aktif')
                ->orderBy('attendance_sessions.jam_masuk')
                ->get();
        }

        if ($this->isHoliday($targetDate)) {
            return $dutySessions->sortBy('jam_masuk')->values();
        }

        return $regularSessions->merge($dutySessions)->unique('id')->sortBy('jam_masuk')->values();
    }

    public function scheduleForSession(Teacher $teacher, AttendanceSession $session, $date = null): ?WorkSchedule
    {
        return $this->regularSchedulesForDate($teacher, $date)
            ->firstWhere('attendance_session_id', $session->id);
    }

    public function regularSchedulesForDate(Teacher $teacher, $date = null): Collection
    {
        $targetDate = $this->parseDate($date);

        if ($teacher->relationLoaded('schedules')) {
            return $teacher->schedules
                ->where('hari', $this->hariFromDate($targetDate))
                ->where('status', 'aktif')
                ->filter(fn (WorkSchedule $schedule) => $schedule->attendanceSession?->status === 'aktif')
                ->values();
        }

        return WorkSchedule::with('attendanceSession')
            ->where('teacher_id', $teacher->id)
            ->where('hari', $this->hariFromDate($targetDate))
            ->where('status', 'aktif')
            ->whereHas('attendanceSession', fn ($query) => $query->where('status', 'aktif'))
            ->get();
    }

    public function hasAnySchedule(Teacher $teacher): bool
    {
        if ($teacher->relationLoaded('schedules')) {
            $hasWorkSchedule = $teacher->schedules
                ->where('status', 'aktif')
                ->whereNotNull('attendance_session_id')
                ->isNotEmpty();

            return $hasWorkSchedule || ($teacher->schedules->isEmpty()
                && $teacher->attendanceSessions()->where('attendance_sessions.status', 'aktif')->exists());
        }

        $hasWorkSchedule = WorkSchedule::where('teacher_id', $teacher->id)
            ->where('status', 'aktif')->whereNotNull('attendance_session_id')->exists();

        return $hasWorkSchedule || (! WorkSchedule::where('teacher_id', $teacher->id)->exists()
            && $teacher->attendanceSessions()->where('attendance_sessions.status', 'aktif')->exists());
    }

    public function isOnDuty(Teacher $teacher, $date = null): bool
    {
        return $this->dutyScheduleForDate($teacher, $this->parseDate($date)) !== null;
    }

    public function dutySessionsForDate(Teacher $teacher, $date = null): Collection
    {
        $dutySchedule = $this->dutyScheduleForDate($teacher, $this->parseDate($date));

        return $dutySchedule
            ? $dutySchedule->attendanceSessions->where('status', 'aktif')->values()
            : collect();
    }

    public function isHoliday($date = null): bool
    {
        $targetDate = $this->parseDate($date);

        return HolidaySetting::active()->whereDate('tanggal', $targetDate->toDateString())->exists();
    }

    public function hariFromDate($date = null): string
    {
        $targetDate = $this->parseDate($date);
        $days = [
            'monday' => 'senin', 'tuesday' => 'selasa', 'wednesday' => 'rabu',
            'thursday' => 'kamis', 'friday' => 'jumat', 'saturday' => 'sabtu', 'sunday' => 'minggu',
        ];

        return $days[strtolower($targetDate->englishDayOfWeek)] ?? 'senin';
    }

    private function dutyScheduleForDate(Teacher $teacher, Carbon $date): ?DutySchedule
    {
        return DutySchedule::with('attendanceSessions')->active()
            ->whereDate('tanggal', $date->toDateString())
            ->whereHas('teachers', fn ($query) => $query->where('teachers.id', $teacher->id))
            ->first();
    }

    private function parseDate($date = null): Carbon
    {
        if ($date instanceof Carbon) {
            return $date->copy()->timezone(config('app.timezone'))->startOfDay();
        }

        return $date
            ? Carbon::parse($date, config('app.timezone'))->startOfDay()
            : now(config('app.timezone'))->startOfDay();
    }
}
