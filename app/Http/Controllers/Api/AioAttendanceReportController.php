<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\HolidaySetting;
use App\Models\Teacher;
use Carbon\Carbon;
use Illuminate\Http\Request;

class AioAttendanceReportController extends Controller
{
    private const MONTH_NAMES_ID = [
        1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
        5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
        9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
    ];

    public function __invoke(Request $request)
    {
        $data = $request->validate([
            'month' => ['required', 'integer', 'min:1', 'max:12'],
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
        ]);

        $month = (int) $data['month'];
        $year = (int) $data['year'];

        $timezone = 'Asia/Jakarta';
        $now = Carbon::now($timezone);

        // Build days in month
        $daysInMonth = Carbon::create($year, $month, 1, 0, 0, 0, $timezone)->daysInMonth;
        $days = [];
        $holidayMap = [];

        $holidays = HolidaySetting::active()
            ->whereYear('tanggal', $year)
            ->whereMonth('tanggal', $month)
            ->orderBy('tanggal')
            ->get();

        foreach ($holidays as $h) {
            $key = $h->tanggal->format('Y-m-d');
            $holidayMap[$key] = $h->nama_libur;
        }

        for ($d = 1; $d <= $daysInMonth; $d++) {
            $date = Carbon::create($year, $month, $d, 0, 0, 0, $timezone);
            $iso = $date->format('Y-m-d');
            // Label like "1-Jun-26"
            $label = $date->format('j-M-y');

            $days[] = [
                'date' => $iso,
                'label' => $label,
                'day_of_week' => $date->format('l'),
                'holiday' => $holidayMap[$iso] ?? null,
            ];
        }

        // Load teachers who are active PTK
        $teachers = Teacher::with([
            'user:id,username,name,email,role,status',
            'attendanceSessions' => function ($q) {
                $q->orderBy('jam_masuk')->withPivot('is_primary');
            },
            'schedules.attendanceSession',
        ])
            ->whereHas('user', function ($q) {
                $q->whereIn('role', Teacher::DATA_GURU_ROLES)
                    ->where('status', 'aktif');
            })
            ->orderBy('nama_lengkap')
            ->get();

        $teacherIds = $teachers->pluck('id')->all();

        // Pre-fetch attendances for the month, only for primary sessions
        // We need mapping teacher_id => primary_session_id
        $primarySessionMap = [];
        $warnings = [];
        foreach ($teachers as $teacher) {
            $scheduledSessions = $teacher->schedules
                ->where('status', 'aktif')
                ->pluck('attendanceSession')
                ->filter(fn ($session) => $session?->status === 'aktif')
                ->unique('id')
                ->sortBy('jam_masuk')
                ->values();

            if ($scheduledSessions->isEmpty() && $teacher->schedules->isEmpty()) {
                $scheduledSessions = $teacher->attendanceSessions
                    ->where('status', 'aktif')
                    ->sortBy('jam_masuk')
                    ->values();
            }
            $primary = $scheduledSessions
                ->firstWhere('id', $teacher->attendanceSessions->firstWhere('pivot.is_primary', true)?->id)
                ?? $scheduledSessions->first();
            if ($primary) {
                $primarySessionMap[$teacher->id] = $primary->id;
            } else {
                $primarySessionMap[$teacher->id] = null;
                $warnings[] = [
                    'teacher_id' => $teacher->id,
                    'teacher_name' => $teacher->nama_lengkap,
                    'code' => 'no_primary_session',
                    'message' => "PTK {$teacher->nama_lengkap} belum memiliki sesi utama.",
                ];
            }
        }

        $primaryTeacherIds = collect($primarySessionMap)->filter()->keys()->all();
        $primarySessionIds = array_values(array_filter(array_unique($primarySessionMap)));

        // Fetch attendances for this month for those teachers and sessions
        $attendances = collect();
        if (! empty($primaryTeacherIds)) {
            $attendances = Attendance::whereIn('teacher_id', $primaryTeacherIds)
                ->whereYear('tanggal', $year)
                ->whereMonth('tanggal', $month)
                // Filter to only primary session per teacher would require per-teacher filter;
                // we fetch all and then filter in memory to avoid N+1.
                ->get()
                ->groupBy('teacher_id');
        }

        // Helper to format time
        $formatTime = function ($dt) {
            if (! $dt) {
                return null;
            }
            if ($dt instanceof Carbon) {
                return $dt->format('H:i');
            }
            // If datetime string
            try {
                return Carbon::parse($dt)->format('H:i');
            } catch (\Exception $e) {
                return null;
            }
        };

        $statusLabels = [
            'hadir' => 'Hadir',
            'terlambat' => 'Terlambat',
            'hadir_tidak_lengkap' => 'Hadir Tidak Lengkap',
            'izin' => 'Izin',
            'sakit' => 'Sakit',
            'cuti' => 'Cuti',
            'tugas_luar' => 'Tugas Luar',
            'alfa' => 'Alfa',
        ];

        $staff = [];
        foreach ($teachers as $teacher) {
            $primarySessionId = $primarySessionMap[$teacher->id] ?? null;
            $primarySession = $teacher->attendanceSessions->firstWhere('id', $primarySessionId);

            // Attendance list for this teacher filtered to primary session if any
            $teacherAttendances = $attendances->get($teacher->id, collect());
            if ($primarySessionId) {
                $teacherAttendances = $teacherAttendances->filter(function ($att) use ($primarySessionId) {
                    return (int) $att->attendance_session_id === (int) $primarySessionId;
                });
            }

            // Map by date string for quick lookup
            $byDate = [];
            foreach ($teacherAttendances as $att) {
                $key = $att->tanggal instanceof Carbon ? $att->tanggal->format('Y-m-d') : Carbon::parse($att->tanggal)->format('Y-m-d');
                // If multiple attendances same date (should not happen for primary), keep latest
                $byDate[$key] = $att;
            }

            $attendanceRows = [];
            foreach ($days as $dayInfo) {
                $dateStr = $dayInfo['date'];
                $holidayName = $dayInfo['holiday'];

                if ($holidayName) {
                    $attendanceRows[] = [
                        'date' => $dateStr,
                        'check_in' => null,
                        'check_out' => null,
                        'status' => null,
                        'note' => $holidayName,
                        'is_holiday' => true,
                    ];

                    continue;
                }

                $att = $byDate[$dateStr] ?? null;
                if (! $att) {
                    $attendanceRows[] = [
                        'date' => $dateStr,
                        'check_in' => null,
                        'check_out' => null,
                        'status' => null,
                        'note' => null,
                        'is_holiday' => false,
                    ];

                    continue;
                }

                $status = $att->status_kehadiran;
                $noteBase = $statusLabels[$status] ?? ($status ? ucfirst(str_replace('_', ' ', $status)) : null);
                $note = $noteBase;

                if ($status === 'terlambat' && $att->keterlambatan_menit) {
                    $note = "Terlambat {$att->keterlambatan_menit} menit";
                }

                $attendanceRows[] = [
                    'date' => $dateStr,
                    'check_in' => $formatTime($att->check_in_time),
                    'check_out' => $formatTime($att->check_out_time),
                    'status' => $status,
                    'note' => $note,
                    'is_holiday' => false,
                ];
            }

            $staff[] = [
                'id' => $teacher->id,
                'name' => $teacher->nama_lengkap,
                'position' => $teacher->jabatan,
                'role' => $teacher->user?->role,
                'role_label' => Teacher::dataGuruRoleLabel($teacher->user?->role),
                'primary_session' => $primarySession ? [
                    'id' => $primarySession->id,
                    'name' => $primarySession->nama_sesi,
                    'jam_masuk' => substr($primarySession->jam_masuk, 0, 5),
                    'jam_pulang' => substr($primarySession->jam_pulang, 0, 5),
                ] : null,
                'attendance' => $attendanceRows,
            ];
        }

        // Determine headmaster name
        $headTeacher = $teachers->first(function ($t) {
            return $t->user && $t->user->role === 'kepala_sekolah';
        });
        $headName = $headTeacher?->nama_lengkap ?? config('school.head_title', 'Kepala Madrasah');

        // Academic year logic: if month >=7, academic year = year / (year+1), else (year-1)/year
        if ($month >= 7) {
            $academicYear = $year.'/'.($year + 1);
        } else {
            $academicYear = ($year - 1).'/'.$year;
        }

        return response()->json([
            'data' => [
                'period' => [
                    'month' => $month,
                    'year' => $year,
                    'month_name' => self::MONTH_NAMES_ID[$month],
                    'academic_year' => $academicYear,
                    'timezone' => $timezone,
                ],
                'school' => [
                    'name' => config('school.name'),
                    'city' => config('school.city'),
                    'head_name' => $headName,
                ],
                'days' => $days,
                'staff' => $staff,
                'warnings' => $warnings,
            ],
            'meta' => [
                'generated_at' => $now->toIso8601String(),
                'api_version' => 'v1',
                'total_staff' => count($staff),
                'total_days' => count($days),
            ],
        ]);
    }
}
