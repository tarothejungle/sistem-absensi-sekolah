<?php

namespace App\Http\Controllers;

use App\Models\LoginActivity;
use App\Models\Teacher;
use App\Services\AttendanceWindowService;
use App\Services\DailyAttendanceStatusService;
use App\Services\UserProfileCompletenessService;

class DashboardController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        $role = $user->role;
        $dailyAttendance = app(DailyAttendanceStatusService::class);
        $today = now('Asia/Jakarta')->toDateString();

        if (in_array($role, Teacher::DATA_GURU_ROLES, true)) {
            $teacher = $user->teacher;

            if ($teacher) {
                $teacher->load([
                    'attendanceSessions' => fn ($query) => $query->where('attendance_sessions.status', 'aktif'),
                    'user',
                ]);
            }

            $data = [
                'role' => $role,
                'teacher' => $teacher,
                'loginActivities' => collect(),
                'profileCompleteness' => app(UserProfileCompletenessService::class)->check($user),
                'sessionWindows' => $this->buildSessionWindows($teacher),
            ];

            if ($role === 'kepala_sekolah') {
                $rekapHariIni = $dailyAttendance->syncForDate($today);
                $guruBelumAbsenHariIni = $dailyAttendance->missingAttendanceTeachersForDate(
                    $today,
                    null,
                    $rekapHariIni
                );

                $data['rekapHariIni'] = $rekapHariIni;
                $data['guruBelumAbsenHariIni'] = $guruBelumAbsenHariIni;
                $data['belumAbsenHariIni'] = $guruBelumAbsenHariIni->count();
            }

            return view('dashboard.index', $data);
        }

        abort_unless($role === 'super_admin', 403);

        $expectedTeachers = $dailyAttendance->expectedTeachersForDate($today);
        $totalGuru = Teacher::whereHas('user', function ($query) {
            $query
                ->where('status', 'aktif')
                ->whereIn('role', Teacher::DATA_GURU_ROLES);
        })->count();

        // Sekaligus membuat status otomatis untuk sakit/izin/cuti/tugas luar/alfa bila syaratnya terpenuhi.
        $rekapHariIni = $dailyAttendance->syncForDate($today);
        $guruBelumAbsenHariIni = $dailyAttendance->missingAttendanceTeachersForDate(
            $today,
            $expectedTeachers,
            $rekapHariIni
        );

        $hadirHariIni = $rekapHariIni->where('status_kehadiran', 'hadir')->unique('teacher_id')->count();
        $terlambatHariIni = $rekapHariIni->where('status_kehadiran', 'terlambat')->unique('teacher_id')->count();
        $tidakLengkapHariIni = $rekapHariIni->where('status_kehadiran', 'hadir_tidak_lengkap')->unique('teacher_id')->count();

        // Belum absen dihitung dari guru yang memang wajib absen hari ini, bukan total guru aktif.
        $belumAbsenHariIni = $guruBelumAbsenHariIni->count();

        // AKTIVITAS LOGIN USER
        $loginActivities = LoginActivity::latest()
            ->where('created_at', '>', now()->subHours(24))
            ->limit(10)
            ->get();

        return view('dashboard.index', [
            'role' => $role,
            'totalGuru' => $totalGuru,
            'hadirHariIni' => $hadirHariIni,
            'terlambatHariIni' => $terlambatHariIni,
            'tidakLengkapHariIni' => $tidakLengkapHariIni,
            'belumAbsenHariIni' => $belumAbsenHariIni,
            'guruBelumAbsenHariIni' => $guruBelumAbsenHariIni,
            'rekapHariIni' => $rekapHariIni,
            'loginActivities' => $loginActivities,
        ]);
    }

    private function buildSessionWindows(?Teacher $teacher): array
    {
        if (! $teacher || $teacher->attendanceSessions->isEmpty()) {
            return [];
        }

        $windowService = app(AttendanceWindowService::class);

        return $teacher->attendanceSessions
            ->mapWithKeys(fn ($session) => [$session->id => $windowService->resolve($session)])
            ->all();
    }
}
