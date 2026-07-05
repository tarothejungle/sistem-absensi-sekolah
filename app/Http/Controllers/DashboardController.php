<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Teacher;
use App\Models\LoginActivity;
use App\Services\DailyAttendanceStatusService;

class DashboardController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        $role = $user->role;
        $dailyAttendance = app(DailyAttendanceStatusService::class);
        $today = now('Asia/Jakarta')->toDateString();

        // DASHBOARD KHUSUS GURU
        if ($role === 'guru') {
            $teacher = $user->teacher;

            if ($teacher) {
                $teacher->load(['attendanceSessions', 'user']);
            }

            return view('dashboard.index', [
                'role' => $role,
                'teacher' => $teacher,
                'loginActivities' => collect(),
            ]);
        }

        // DASHBOARD KHUSUS BENDAHARA DAN KEPALA SEKOLAH
        if (in_array($role, ['bendahara', 'kepala_sekolah'], true)) {
            $teacher = $user->teacher;

            if ($teacher) {
                $teacher->load(['attendanceSessions', 'user']);
            }

            $data = [
                'role' => $role,
                'teacher' => $teacher,
                'loginActivities' => collect(),
            ];

            if ($role === 'kepala_sekolah') {
                $data['rekapHariIni'] = $dailyAttendance->syncForDate($today);
            }

            return view('dashboard.index', $data);
        }

        // DASHBOARD UNTUK ADMIN / SUPER ADMIN
        $expectedTeachers = $dailyAttendance->expectedTeachersForDate($today);
        $totalGuru = Teacher::whereHas('user', function ($query) {
            $query->where('status', 'aktif');
        })->count();

        // Sekaligus membuat status otomatis untuk sakit/izin/cuti/tugas luar/alfa bila syaratnya terpenuhi.
        $rekapHariIni = $dailyAttendance->syncForDate($today);

        $hadirHariIni = $rekapHariIni->where('status_kehadiran', 'hadir')->unique('teacher_id')->count();
        $terlambatHariIni = $rekapHariIni->where('status_kehadiran', 'terlambat')->unique('teacher_id')->count();
        $tidakLengkapHariIni = $rekapHariIni->where('status_kehadiran', 'hadir_tidak_lengkap')->unique('teacher_id')->count();

        // Belum absen dihitung dari guru yang memang wajib absen hari ini, bukan total guru aktif.
        $guruYangSudahAdaStatus = $rekapHariIni->pluck('teacher_id')->unique()->count();
        $belumAbsenHariIni = max($expectedTeachers->count() - $guruYangSudahAdaStatus, 0);

        // AKTIVITAS LOGIN USER
        $loginActivities = LoginActivity::latest()
            ->limit(10)
            ->get();

        return view('dashboard.index', [
            'role' => $role,
            'totalGuru' => $totalGuru,
            'hadirHariIni' => $hadirHariIni,
            'terlambatHariIni' => $terlambatHariIni,
            'tidakLengkapHariIni' => $tidakLengkapHariIni,
            'belumAbsenHariIni' => $belumAbsenHariIni,
            'rekapHariIni' => $rekapHariIni,
            'loginActivities' => $loginActivities,
        ]);
    }
}
