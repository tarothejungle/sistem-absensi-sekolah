<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\AttendanceSession;
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
            $existingRows = Attendance::where('teacher_id', $teacher->id)
                ->whereDate('tanggal', $todayString)
                ->get();

            $hasRealAttendance = $existingRows->contains(function ($attendance) {
                return !is_null($attendance->check_in_time);
            });

            // Kalau guru sudah benar-benar check-in, status real absensi tidak boleh ditimpa.
            if ($hasRealAttendance) {
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

        return Attendance::with(['teacher.user', 'attendanceSession'])
            ->whereDate('tanggal', $todayString)
            ->whereHas('teacher.user', function ($query) {
                $query->where('status', 'aktif');
            })
            ->join('teachers', 'attendances.teacher_id', '=', 'teachers.id')
            ->select('attendances.*')
            ->orderByRaw("CASE status_kehadiran WHEN 'hadir' THEN 1 WHEN 'terlambat' THEN 2 WHEN 'sakit' THEN 3 WHEN 'izin' THEN 4 WHEN 'cuti' THEN 5 WHEN 'tugas_luar' THEN 6 WHEN 'alfa' THEN 7 ELSE 8 END")
            ->orderBy('teachers.nama_lengkap')
            ->get();
    }

    public function expectedTeachersForDate($date = null): Collection
    {
        $targetDate = $this->parseDate($date);
        $hari = $this->hariFromDate($targetDate);

        return Teacher::with(['user', 'schedules', 'attendanceSessions'])
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
