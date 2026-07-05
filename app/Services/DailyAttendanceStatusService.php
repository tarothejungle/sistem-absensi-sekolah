<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\AttendanceSession;
use App\Models\DutySchedule;
use App\Models\HolidaySetting;
use App\Models\LeaveRequest;
use App\Models\Teacher;
use App\Models\WorkSchedule;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class DailyAttendanceStatusService
{
    public function syncForDate($date = null): Collection
    {
        $targetDate = $this->parseDate($date);
        $todayString = $targetDate->toDateString();
        $hari = $this->hariFromDate($targetDate);

        $teachers = $this->expectedTeachersForDate($targetDate);

        foreach ($teachers as $teacher) {
            $existingRows = Attendance::with(['attendanceSession', 'schedule'])
                ->where('teacher_id', $teacher->id)
                ->whereDate('tanggal', $todayString)
                ->get();

            $hasRealAttendance = $existingRows->contains(function ($attendance) {
                return !is_null($attendance->check_in_time);
            });

            if ($hasRealAttendance) {
                $this->markIncompleteAttendances($existingRows, $teacher, $targetDate, $hari);
                continue;
            }

            $approvedLeave = LeaveRequest::where('teacher_id', $teacher->id)
                ->where('status_pengajuan', 'disetujui')
                ->whereDate('tanggal_mulai', '<=', $todayString)
                ->whereDate('tanggal_selesai', '>=', $todayString)
                ->latest('updated_at')
                ->first();

            $schedule = $this->todaySchedule($teacher, $hari);
            $session = $this->firstActiveSession($teacher);

            if ($approvedLeave) {
                $this->createOrUpdateSystemAttendance(
                    $teacher,
                    $targetDate,
                    $session,
                    $schedule,
                    $approvedLeave->jenis_pengajuan
                );

                continue;
            }

            if ($this->shouldMarkAlfa($teacher, $targetDate, $session, $schedule)) {
                $this->createOrUpdateSystemAttendance(
                    $teacher,
                    $targetDate,
                    $session,
                    $schedule,
                    'alfa'
                );
            }
        }

        $this->removeUnexpectedSystemAttendances($targetDate, $teachers);

        return Attendance::with(['teacher.user', 'attendanceSession'])
            ->whereDate('tanggal', $todayString)
            ->whereHas('teacher.user', function ($query) {
                $query->where('status', 'aktif');
            })
            ->join('teachers', 'attendances.teacher_id', '=', 'teachers.id')
            ->select('attendances.*')
            ->orderByRaw("CASE status_kehadiran WHEN 'hadir' THEN 1 WHEN 'terlambat' THEN 2 WHEN 'hadir_tidak_lengkap' THEN 3 WHEN 'sakit' THEN 4 WHEN 'izin' THEN 5 WHEN 'cuti' THEN 6 WHEN 'tugas_luar' THEN 7 WHEN 'alfa' THEN 8 ELSE 9 END")
            ->orderBy('teachers.nama_lengkap')
            ->get();
    }

    public function expectedTeachersForDate($date = null): Collection
    {
        $targetDate = $this->parseDate($date);
        $hari = $this->hariFromDate($targetDate);
        $dutyTeachers = $this->activeDutyTeachersForDate($targetDate);

        if ($this->isActiveHoliday($targetDate)) {
            return $dutyTeachers;
        }

        $scheduledTeachers = Teacher::with(['user', 'schedules', 'attendanceSessions'])
            ->whereHas('user', function ($query) {
                $query->where('status', 'aktif');
            })
            ->orderBy('nama_lengkap')
            ->get()
            ->filter(function ($teacher) use ($hari) {
                $activeSchedules = $teacher->schedules->where('status', 'aktif');

                // Backward compatible: guru lama yang belum punya jadwal hari tetap dianggap masuk jadwal.
                if ($activeSchedules->isEmpty()) {
                    return true;
                }

                return $activeSchedules->contains('hari', $hari);
            })
            ->values();

        return $scheduledTeachers
            ->merge($dutyTeachers)
            ->unique('id')
            ->sortBy('nama_lengkap')
            ->values();
    }

    private function activeDutyTeachersForDate(Carbon $targetDate): Collection
    {
        $dutySchedule = DutySchedule::with(['teachers.user', 'teachers.schedules', 'teachers.attendanceSessions'])
            ->active()
            ->whereDate('tanggal', $targetDate->toDateString())
            ->first();

        if (!$dutySchedule) {
            return collect();
        }

        return $dutySchedule->teachers
            ->filter(function ($teacher) {
                return $teacher->user && $teacher->user->status === 'aktif';
            })
            ->sortBy('nama_lengkap')
            ->values();
    }

    private function isActiveHoliday(Carbon $targetDate): bool
    {
        return HolidaySetting::active()
            ->whereDate('tanggal', $targetDate->toDateString())
            ->exists();
    }

    private function removeUnexpectedSystemAttendances(Carbon $targetDate, Collection $expectedTeachers): void
    {
        $expectedTeacherIds = $expectedTeachers->pluck('id')->all();

        Attendance::whereDate('tanggal', $targetDate->toDateString())
            ->where('device_info', 'system-auto-status')
            ->whereNull('check_in_time')
            ->whereNull('check_out_time')
            ->when(
                !empty($expectedTeacherIds),
                fn ($query) => $query->whereNotIn('teacher_id', $expectedTeacherIds)
            )
            ->delete();
    }

    private function createOrUpdateSystemAttendance(
        Teacher $teacher,
        Carbon $targetDate,
        ?AttendanceSession $session,
        ?WorkSchedule $schedule,
        string $status
    ): Attendance {
        $todayString = $targetDate->toDateString();

        $attendance = Attendance::where('teacher_id', $teacher->id)
            ->whereDate('tanggal', $todayString)
            ->whereNull('check_in_time')
            ->whereNull('check_out_time')
            ->first();

        $payload = [
            'attendance_session_id' => $session?->id,
            'work_schedule_id' => $schedule?->id,
            'tanggal' => $todayString,
            'status_kehadiran' => $status,
            'keterlambatan_menit' => 0,
            'verification_method' => 'face',
            'device_info' => 'system-auto-status',
        ];

        if ($attendance) {
            $attendance->update($payload);
            return $attendance;
        }

        return Attendance::create(array_merge($payload, [
            'teacher_id' => $teacher->id,
        ]));
    }

    private function shouldMarkAlfa(
        Teacher $teacher,
        Carbon $targetDate,
        ?AttendanceSession $session,
        ?WorkSchedule $schedule
    ): bool {
        // Alfa untuk tanggal lampau boleh langsung dibuat.
        if ($targetDate->lt(now('Asia/Jakarta')->startOfDay())) {
            return true;
        }

        // Untuk hari ini, alfa baru dibuat setelah batas check-in terakhir lewat.
        if (!$targetDate->isSameDay(now('Asia/Jakarta'))) {
            return false;
        }

        $now = now('Asia/Jakarta');
        $cutoff = null;

        $sessions = $this->activeSessions($teacher);

        if ($sessions->isNotEmpty()) {
            foreach ($sessions as $item) {
                $candidate = Carbon::parse($targetDate->toDateString() . ' ' . $item->batas_check_in_selesai, 'Asia/Jakarta');

                if (!$cutoff || $candidate->gt($cutoff)) {
                    $cutoff = $candidate;
                }
            }
        } elseif ($schedule) {
            $cutoff = Carbon::parse($targetDate->toDateString() . ' ' . $schedule->jam_masuk, 'Asia/Jakarta')
                ->addMinutes((int) $schedule->toleransi_terlambat);
        }

        if (!$cutoff) {
            // Fallback aman agar sistem tidak menandai alfa terlalu pagi.
            $cutoff = Carbon::parse($targetDate->toDateString() . ' 23:59:59', 'Asia/Jakarta');
        }

        return $now->gt($cutoff);
    }

    private function markIncompleteAttendances(Collection $attendances, Teacher $teacher, Carbon $targetDate, string $hari): void
    {
        foreach ($attendances as $attendance) {
            if (!$attendance->check_in_time || $attendance->check_out_time) {
                continue;
            }

            if (!in_array($attendance->status_kehadiran, ['hadir', 'terlambat'], true)) {
                continue;
            }

            $session = $attendance->attendanceSession ?: $this->firstActiveSession($teacher);
            $schedule = $attendance->schedule ?: $this->todaySchedule($teacher, $hari);

            if (!$this->shouldMarkIncomplete($targetDate, $session, $schedule)) {
                continue;
            }

            $attendance->update([
                'status_kehadiran' => 'hadir_tidak_lengkap',
            ]);
        }
    }

    private function shouldMarkIncomplete(
        Carbon $targetDate,
        ?AttendanceSession $session,
        ?WorkSchedule $schedule
    ): bool {
        $today = now('Asia/Jakarta')->startOfDay();

        if ($targetDate->gt($today)) {
            return false;
        }

        $cutoff = $this->checkoutCutoffAt($targetDate, $session, $schedule);

        return now('Asia/Jakarta')->gt($cutoff);
    }

    private function checkoutCutoffAt(
        Carbon $targetDate,
        ?AttendanceSession $session,
        ?WorkSchedule $schedule
    ): Carbon {
        if ($session) {
            $start = Carbon::parse($targetDate->toDateString() . ' ' . $session->batas_check_out_mulai, 'Asia/Jakarta');
            $end = Carbon::parse($targetDate->toDateString() . ' ' . $session->batas_check_out_selesai, 'Asia/Jakarta');

            if ($end->lt($start)) {
                $end->addDay();
            }

            return $end;
        }

        if ($schedule) {
            return Carbon::parse($targetDate->toDateString() . ' ' . $schedule->jam_pulang, 'Asia/Jakarta');
        }

        return Carbon::parse($targetDate->toDateString() . ' 23:59:59', 'Asia/Jakarta');
    }

    private function activeSessions(Teacher $teacher): Collection
    {
        $sessions = $teacher->attendanceSessions
            ? $teacher->attendanceSessions->where('status', 'aktif')->sortBy('jam_masuk')->values()
            : collect();

        if ($sessions->isEmpty() && $teacher->attendance_session_id) {
            $oldSession = AttendanceSession::where('id', $teacher->attendance_session_id)
                ->where('status', 'aktif')
                ->first();

            if ($oldSession) {
                return collect([$oldSession]);
            }
        }

        return $sessions;
    }

    private function firstActiveSession(Teacher $teacher): ?AttendanceSession
    {
        return $this->activeSessions($teacher)->first();
    }

    private function todaySchedule(Teacher $teacher, string $hari): ?WorkSchedule
    {
        return $teacher->schedules
            ? $teacher->schedules->where('hari', $hari)->where('status', 'aktif')->first()
            : null;
    }

    private function parseDate($date = null): Carbon
    {
        if ($date instanceof Carbon) {
            return $date->copy()->timezone('Asia/Jakarta')->startOfDay();
        }

        return $date
            ? Carbon::parse($date, 'Asia/Jakarta')->startOfDay()
            : now('Asia/Jakarta')->startOfDay();
    }

    private function hariFromDate(Carbon $date): string
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

        return $dayMap[strtolower($date->englishDayOfWeek)] ?? 'senin';
    }
}
