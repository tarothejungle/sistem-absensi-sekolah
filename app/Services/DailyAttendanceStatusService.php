<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\AttendanceSession;
use App\Models\DutySchedule;
use App\Models\HolidaySetting;
use App\Models\LeaveRequest;
use App\Models\Teacher;
use App\Models\User;
use App\Models\WorkSchedule;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class DailyAttendanceStatusService
{
    public function __construct(
        private readonly TemporaryLeaveAttendancePolicy $temporaryLeavePolicy,
        private readonly WorkScheduleResolver $scheduleResolver,
        private readonly AttendanceWindowService $windowService,
    ) {}

    public function syncForDate($date = null): Collection
    {
        $targetDate = $this->parseDate($date);
        $todayString = $targetDate->toDateString();
        $hari = $this->hariFromDate($targetDate);

        $teachers = $this->expectedTeachersForDate($targetDate);

        foreach ($teachers as $teacher) {
            $this->syncTeacherForDate($teacher, $targetDate);
        }

        $this->removeUnexpectedSystemAttendances($targetDate, $teachers);

        return Attendance::with(['teacher.user', 'attendanceSession'])
            ->whereDate('tanggal', $todayString)
            ->whereHas('teacher.user', function ($query) {
                $query
                    ->where('status', 'aktif')
                    ->whereIn('role', User::ATTENDANCE_ROLES);
            })
            ->join('teachers', 'attendances.teacher_id', '=', 'teachers.id')
            ->select('attendances.*')
            ->orderByRaw("CASE status_kehadiran WHEN 'hadir' THEN 1 WHEN 'terlambat' THEN 2 WHEN 'hadir_tidak_lengkap' THEN 3 WHEN 'sakit' THEN 4 WHEN 'izin' THEN 5 WHEN 'cuti' THEN 6 WHEN 'tugas_luar' THEN 7 WHEN 'alfa' THEN 8 ELSE 9 END")
            ->orderBy('teachers.nama_lengkap')
            ->get();
    }

    public function syncTeacherForDate(Teacher $teacher, $date = null): Collection
    {
        $targetDate = $this->parseDate($date);
        $todayString = $targetDate->toDateString();
        $hari = $this->hariFromDate($targetDate);
        $teacher->loadMissing(['schedules.attendanceSession']);
        $existingRows = Attendance::with(['attendanceSession', 'schedule'])
            ->where('teacher_id', $teacher->id)
            ->whereDate('tanggal', $todayString)
            ->get();
        $approvedLeave = $this->approvedFullDayLeaveForTeacherDate($teacher, $targetDate);
        $sessions = $this->activeSessions($teacher, $targetDate);

        $this->markIncompleteAttendances($existingRows, $teacher, $targetDate, $hari);

        if ($sessions->isEmpty()) {
            return $existingRows;
        }

        foreach ($sessions as $session) {
            $schedule = $session
                ? $this->scheduleResolver->scheduleForSession($teacher, $session, $targetDate)
                : null;
            $grace = $session ? $this->temporaryLeavePolicy->resolve($teacher, $session, $targetDate) : null;

            if ($grace && $grace['is_grace_active']) {
                Attendance::where('teacher_id', $teacher->id)
                    ->whereDate('tanggal', $todayString)
                    ->where('attendance_session_id', $session->id)
                    ->where('status_kehadiran', 'alfa')
                    ->where('device_info', 'system-auto-status')
                    ->whereNull('check_in_time')
                    ->whereNull('check_out_time')
                    ->delete();
                $existingRows = $existingRows->reject(fn (Attendance $attendance) => $attendance->attendance_session_id === $session->id
                    && $attendance->status_kehadiran === 'alfa'
                    && $attendance->device_info === 'system-auto-status'
                    && ! $attendance->check_in_time
                    && ! $attendance->check_out_time
                );
            }

            $attendance = $existingRows->firstWhere('attendance_session_id', $session?->id);

            if ($attendance?->check_in_time) {
                $this->markIncompleteAttendances(collect([$attendance]), $teacher, $targetDate, $hari);

                continue;
            }

            if ($approvedLeave) {
                $this->createOrUpdateSystemAttendance($teacher, $targetDate, $session, $schedule, $approvedLeave->jenis_pengajuan);

                continue;
            }

            if ($this->shouldMarkAlfa($teacher, $targetDate, $session, $schedule)) {
                $this->createOrUpdateSystemAttendance($teacher, $targetDate, $session, $schedule, 'alfa');
            }
        }

        return Attendance::with(['attendanceSession', 'schedule', 'temporaryLeaveRequest'])
            ->where('teacher_id', $teacher->id)
            ->whereDate('tanggal', $todayString)
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

        $scheduledTeachers = Teacher::with(['user', 'schedules.attendanceSession'])
            ->whereHas('user', function ($query) {
                $query
                    ->where('status', 'aktif')
                    ->whereIn('role', User::ATTENDANCE_ROLES);
            })
            ->orderBy('nama_lengkap')
            ->get()
            ->filter(fn ($teacher) => $this->scheduleResolver->sessionsForDate($teacher, $targetDate)->isNotEmpty()
                || Attendance::where('teacher_id', $teacher->id)
                    ->whereDate('tanggal', $targetDate->toDateString())
                    ->exists())
            ->values();

        return $scheduledTeachers
            ->merge($dutyTeachers)
            ->unique('id')
            ->sortBy('nama_lengkap')
            ->values();
    }

    public function missingAttendanceTeachersForDate(
        $date = null,
        ?Collection $expectedTeachers = null,
        ?Collection $dailyAttendances = null
    ): Collection {
        $targetDate = $this->parseDate($date);
        $todayString = $targetDate->toDateString();

        $expectedTeachers ??= $this->expectedTeachersForDate($targetDate);
        $dailyAttendances ??= Attendance::whereDate('tanggal', $todayString)
            ->whereHas('teacher.user', function ($query) {
                $query
                    ->where('status', 'aktif')
                    ->whereIn('role', User::ATTENDANCE_ROLES);
            })
            ->get();

        $attendancesByTeacher = $dailyAttendances->groupBy('teacher_id');
        $excusedStatuses = ['izin', 'sakit', 'cuti', 'tugas_luar'];

        return $expectedTeachers
            ->filter(function (Teacher $teacher) use ($attendancesByTeacher, $excusedStatuses, $targetDate) {
                $teacherAttendances = $attendancesByTeacher->get($teacher->id, collect());
                $sessionIds = $this->activeSessions($teacher, $targetDate)->pluck('id');

                if ($sessionIds->isNotEmpty()) {
                    return $sessionIds->contains(function ($sessionId) use ($teacherAttendances, $excusedStatuses) {
                        $attendance = $teacherAttendances->firstWhere('attendance_session_id', $sessionId);

                        if (! $attendance) {
                            return true;
                        }

                        if (in_array($attendance->status_kehadiran, $excusedStatuses, true)) {
                            return false;
                        }

                        return is_null($attendance->check_in_time);
                    });
                }

                if ($teacherAttendances->isEmpty()) {
                    return true;
                }

                if ($teacherAttendances->contains(fn (Attendance $attendance) => ! is_null($attendance->check_in_time))) {
                    return false;
                }

                return ! $teacherAttendances->contains(function (Attendance $attendance) use ($excusedStatuses) {
                    return in_array($attendance->status_kehadiran, $excusedStatuses, true);
                });
            })
            ->map(function (Teacher $teacher) use ($attendancesByTeacher) {
                $attendance = $attendancesByTeacher->get($teacher->id, collect())->first();

                $teacher->setAttribute('missing_attendance_status', $attendance?->status_kehadiran);

                return $teacher;
            })
            ->values();
    }

    public function approvedFullDayLeaveForTeacherDate(Teacher $teacher, $date = null): ?LeaveRequest
    {
        $targetDate = $this->parseDate($date);
        $todayString = $targetDate->toDateString();

        return LeaveRequest::where('teacher_id', $teacher->id)
            ->where('status_pengajuan', 'disetujui')
            ->where('is_sementara', false)
            ->whereDate('tanggal_mulai', '<=', $todayString)
            ->whereDate('tanggal_selesai', '>=', $todayString)
            ->latest('updated_at')
            ->first();
    }

    public function attendanceBlockMessage(
        Teacher $teacher,
        string $date,
        ?WorkSchedule $schedule,
        bool $hasAnyActiveSchedule,
        ?bool $hasSessionsToday = null
    ): ?string {
        $approvedFullDayLeave = $this->approvedFullDayLeaveForTeacherDate($teacher, $date);

        if ($approvedFullDayLeave) {
            return 'Karena pengajuan '.$this->leaveStatusLabel($approvedFullDayLeave->jenis_pengajuan)
                .' Anda sudah disetujui. Kewajiban absen hari ini otomatis digugurkan.';
        }

        $targetDate = Carbon::parse($date, 'Asia/Jakarta');
        $isOnDuty = $this->isTeacherOnActiveDuty($teacher, $date);

        if ($this->isActiveHoliday($targetDate) && ! $isOnDuty) {
            return 'Hari ini ditetapkan sebagai hari libur. Absensi hanya dibuka untuk guru/karyawan yang masuk jadwal piket aktif.';
        }

        $hasSessionsToday ??= $schedule !== null;

        if (! $hasAnyActiveSchedule && ! $isOnDuty) {
            return 'Jadwal kerja belum diatur. Hubungi admin sekolah.';
        }

        if ($hasAnyActiveSchedule && ! $hasSessionsToday && ! $isOnDuty) {
            return 'Hari ini belum termasuk jadwal absensi untuk akun Anda. Silakan hubungi admin apabila jadwal mengajar belum sesuai.';
        }

        return null;
    }

    private function leaveStatusLabel(?string $status): string
    {
        return match ($status) {
            'sakit' => 'sakit',
            'izin' => 'izin',
            'cuti' => 'cuti',
            'tugas_luar' => 'tugas luar',
            default => 'izin/cuti',
        };
    }

    private function isTeacherOnActiveDuty(Teacher $teacher, string $date): bool
    {
        return $this->scheduleResolver->isOnDuty($teacher, $date);
    }

    private function activeDutyTeachersForDate(Carbon $targetDate): Collection
    {
        $dutySchedule = DutySchedule::with(['teachers.user', 'teachers.schedules.attendanceSession'])
            ->active()
            ->whereDate('tanggal', $targetDate->toDateString())
            ->first();

        if (! $dutySchedule) {
            return collect();
        }

        return $dutySchedule->teachers
            ->filter(function ($teacher) {
                return $teacher->user
                    && $teacher->user->status === 'aktif'
                    && $teacher->user->hasAttendanceAccess();
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
        $query = Attendance::whereDate('tanggal', $targetDate->toDateString())
            ->where('device_info', 'system-auto-status')
            ->whereNull('check_in_time')
            ->whereNull('check_out_time');

        $expectedTeacherIds = $expectedTeachers->pluck('id')->filter()->values();
        if ($expectedTeacherIds->isNotEmpty()) {
            $query->whereNotIn('teacher_id', $expectedTeacherIds);
        }

        $query->delete();
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
            ->when(
                $session,
                fn ($query) => $query->where('attendance_session_id', $session->id),
                fn ($query) => $query->whereNull('attendance_session_id')
            )
            ->whereNull('check_in_time')
            ->whereNull('check_out_time')
            ->first();

        $payload = [
            'attendance_session_id' => $session?->id,
            'work_schedule_id' => $schedule?->id,
            'tanggal' => $todayString,
            'status_kehadiran' => $status,
            'keterlambatan_menit' => 0,
            'verification_method' => 'system_auto',
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
        if ($targetDate->gt(now('Asia/Jakarta')->startOfDay())) {
            return false;
        }

        $now = now('Asia/Jakarta');
        $cutoff = null;

        if ($session) {
            $cutoff = $this->windowService->resolve($session, $targetDate)['check_in_selesai'];
            $grace = $this->temporaryLeavePolicy->resolve($teacher, $session, $targetDate);
            if ($grace) {
                $cutoff = $grace['effective_deadline'];
            }
        } elseif ($schedule) {
            $cutoff = Carbon::parse($targetDate->toDateString().' '.$schedule->jam_masuk, 'Asia/Jakarta')
                ->addMinutes((int) $schedule->toleransi_terlambat);
        }

        if (! $cutoff) {
            // Fallback aman agar sistem tidak menandai alfa terlalu pagi.
            $cutoff = Carbon::parse($targetDate->toDateString().' 23:59:59', 'Asia/Jakarta');
        }

        return $now->gt($cutoff);
    }

    private function markIncompleteAttendances(Collection $attendances, Teacher $teacher, Carbon $targetDate, string $hari): void
    {
        foreach ($attendances as $attendance) {
            if (! $attendance->check_in_time || $attendance->check_out_time) {
                continue;
            }

            if (! in_array($attendance->status_kehadiran, ['hadir', 'terlambat'], true)) {
                continue;
            }

            $session = $attendance->attendanceSession ?: $this->firstActiveSession($teacher, $targetDate);
            $schedule = $attendance->schedule
                ?: ($session ? $this->scheduleResolver->scheduleForSession($teacher, $session, $targetDate) : null);

            if (! $this->shouldMarkIncomplete($targetDate, $session, $schedule)) {
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
            return $this->windowService->resolve($session, $targetDate)['check_out_selesai'];
        }

        if ($schedule) {
            return Carbon::parse($targetDate->toDateString().' '.$schedule->jam_pulang, 'Asia/Jakarta');
        }

        return Carbon::parse($targetDate->toDateString().' 23:59:59', 'Asia/Jakarta');
    }

    private function activeSessions(Teacher $teacher, $date = null): Collection
    {
        return $this->scheduleResolver->sessionsForDate($teacher, $date);
    }

    private function firstActiveSession(Teacher $teacher, $date = null): ?AttendanceSession
    {
        return $this->activeSessions($teacher, $date)->first();
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
