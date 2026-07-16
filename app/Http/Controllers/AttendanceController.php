<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\DutySchedule;
use App\Models\HolidaySetting;
use App\Models\SchoolLocation;
use App\Models\WorkSchedule;
use App\Services\FaceVerificationService;
use App\Services\GeoFenceService;
use App\Services\DailyAttendanceStatusService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AttendanceController extends Controller
{
    private const MAX_GPS_ACCURACY_METERS = 100;

    public function index()
    {
        if (!in_array(auth()->user()->role, ['guru', 'bendahara', 'kepala_sekolah'])) {
            return redirect()
                ->route('dashboard')
                ->with('error', 'Halaman absensi hanya dapat diakses oleh guru.');
        }

        $teacher = auth()->user()->teacher;
        $perPage = $this->resolvePerPage(request());

        if (!$teacher) {
            return redirect()
                ->route('dashboard')
                ->with('error', 'Data guru belum tersedia untuk akun ini.');
        }

        $today = now('Asia/Jakarta')->toDateString();
        $nowTime = now('Asia/Jakarta')->format('H:i:s');
        $hariIni = $this->todayHari();

        // Sinkronkan status izin/sakit/cuti/tugas luar/alfa agar ikut tampil di riwayat absensi.
        app(DailyAttendanceStatusService::class)->syncForDate($today);

        $hasAnySchedule = WorkSchedule::where('teacher_id', $teacher->id)->exists();
        $todaySchedule = WorkSchedule::where('teacher_id', $teacher->id)
            ->where('hari', $hariIni)
            ->where('status', 'aktif')
            ->first();

        $sessions = collect();
        $activeSession = null;
        $scheduleMessage = null;
        $attendanceBlockMessage = $this->attendanceBlockMessage($teacher, $today, $todaySchedule, $hasAnySchedule);

        if ($attendanceBlockMessage) {
            $scheduleMessage = $attendanceBlockMessage;
        } else {
            $sessions = $this->getTeacherSessions($teacher)
                ->map(function ($session) use ($teacher, $today, $nowTime) {
                    $attendance = Attendance::where('teacher_id', $teacher->id)
                        ->where('attendance_session_id', $session->id)
                        ->whereDate('tanggal', $today)
                        ->first();

                    $session->todayAttendance = $attendance;

                    $session->canCheckIn = $this->isTimeInRangeString(
                        $nowTime,
                        $session->batas_check_in_mulai,
                        $session->batas_check_in_selesai
                    ) && (!$attendance || !$attendance->check_in_time);

                    $session->canCheckOut = $attendance
                        && $attendance->check_in_time
                        && !$attendance->check_out_time
                        && $this->isTimeInRangeString(
                            $nowTime,
                            $session->batas_check_out_mulai,
                            $session->batas_check_out_selesai
                        );

                    return $session;
                });

            $activeSession = $sessions->first(function ($session) {
                $attendance = $session->todayAttendance;

                return !$attendance || !$attendance->check_in_time || !$attendance->check_out_time;
            });

            if (!$activeSession) {
                $activeSession = $sessions->last();
            }
        }

        $attendances = Attendance::with(['attendanceSession', 'schedule'])
            ->where('teacher_id', $teacher->id)
            ->orderByDesc('tanggal')
            ->orderBy('check_in_time')
            ->paginate($perPage)
            ->withQueryString();

        return view('attendance.index', compact('sessions', 'activeSession', 'attendances', 'scheduleMessage', 'hariIni'));
    }

    private function getTeacherSessions($teacher)
    {
        return $teacher->attendanceSessions()
            ->where('attendance_sessions.status', 'aktif')
            ->orderBy('attendance_sessions.jam_masuk')
            ->get();
    }

    private function isTimeInRangeString($current, $start, $end)
    {
        $current = Carbon::parse($current);
        $start = Carbon::parse($start);
        $end = Carbon::parse($end);

        if ($start->lte($end)) {
            return $current->betweenIncluded($start, $end);
        }

        return $current->gte($start) || $current->lte($end);
    }

    private function todayHari(): string
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

        $todayName = strtolower(now('Asia/Jakarta')->englishDayOfWeek);

        return $dayMap[$todayName] ?? 'senin';
    }

    private function todaySchedule($teacher)
    {
        return WorkSchedule::where('teacher_id', $teacher->id)
            ->where('hari', $this->todayHari())
            ->where('status', 'aktif')
            ->first();
    }

    private function attendanceBlockMessage($teacher, string $date, ?WorkSchedule $schedule, bool $hasAnySchedule): ?string
    {
        $approvedFullDayLeave = app(DailyAttendanceStatusService::class)
            ->approvedFullDayLeaveForTeacherDate($teacher, $date);

        if ($approvedFullDayLeave) {
            return 'Karena pengajuan ' . $this->leaveStatusLabel($approvedFullDayLeave->jenis_pengajuan)
                . ' Anda sudah disetujui. Kewajiban absen hari ini otomatis digugurkan.';
        }

        $isOnDuty = $this->isTeacherOnActiveDuty($teacher, $date);

        if ($this->isActiveHoliday($date) && !$isOnDuty) {
            return 'Hari ini ditetapkan sebagai hari libur. Absensi hanya dibuka untuk guru/karyawan yang masuk jadwal piket aktif.';
        }

        if ($hasAnySchedule && !$schedule && !$isOnDuty) {
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

    private function isActiveHoliday(string $date): bool
    {
        return HolidaySetting::active()
            ->whereDate('tanggal', $date)
            ->exists();
    }

    private function isTeacherOnActiveDuty($teacher, string $date): bool
    {
        return DutySchedule::active()
            ->whereDate('tanggal', $date)
            ->whereHas('teachers', function ($query) use ($teacher) {
                $query->where('teachers.id', $teacher->id);
            })
            ->exists();
    }

    public function checkIn(
        Request $request,
        GeoFenceService $geo,
        FaceVerificationService $face
    ) {
        $request->validate([
            'attendance_session_id' => 'required|exists:attendance_sessions,id',
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
            'accuracy' => 'nullable|numeric',
            'face_image' => 'required|string|max:4000000',
        ]);

        $teacher = auth()->user()->teacher;

        if (!$teacher) {
            return back()->with('error', 'Profil guru belum tersedia.');
        }

        $schedule = $this->todaySchedule($teacher);
        $hasAnySchedule = WorkSchedule::where('teacher_id', $teacher->id)->exists();
        $today = now('Asia/Jakarta')->toDateString();
        $attendanceBlockMessage = $this->attendanceBlockMessage($teacher, $today, $schedule, $hasAnySchedule);

        if ($attendanceBlockMessage) {
            return back()->with('error', $attendanceBlockMessage);
        }

        $session = $this->getTeacherSessions($teacher)
            ->where('id', (int) $request->attendance_session_id)
            ->first();

        if (!$session) {
            return back()->with('error', 'Sesi absensi tidak valid untuk akun ini.');
        }

        $location = SchoolLocation::where('status', 'aktif')->first();

        if (!$location) {
            return back()->with('error', 'Lokasi sekolah belum diatur admin.');
        }

        $distance = $geo->distanceInMeters(
            (float) $request->latitude,
            (float) $request->longitude,
            (float) $location->latitude,
            (float) $location->longitude
        );

        $gpsAccuracy = (float) $request->input('accuracy', 0);

        if ($this->isGpsAccuracyTooLow($gpsAccuracy)) {
            return back()->with('error', $this->gpsAccuracyErrorMessage($gpsAccuracy));
        }

        $allowedRadius = (float) $location->radius_meter + min($gpsAccuracy, 100);

        if ($distance > $allowedRadius) {
            return back()->with(
                'error',
                'Absensi ditolak. Anda berada di luar radius sekolah. ' .
                'Jarak terdeteksi: ' . round($distance) . ' meter. ' .
                'Radius sekolah: ' . $location->radius_meter . ' meter. ' .
                'Akurasi GPS: ' . round($gpsAccuracy) . ' meter.'
            );
        }

        if (!$face->verify($request->face_image)) {
            return back()->with('error', 'Validasi wajah gagal. Pastikan kamera aktif.');
        }

        $now = now('Asia/Jakarta');
        $today = $now->toDateString();
        $jamSekarang = $now->format('H:i:s');

        if (!$this->isTimeInRangeString($jamSekarang, $session->batas_check_in_mulai, $session->batas_check_in_selesai)) {
            return back()->with(
                'error',
                'Check-in ' . $session->nama_sesi . ' hanya dapat dilakukan pada pukul ' .
                substr($session->batas_check_in_mulai, 0, 5) . ' - ' .
                substr($session->batas_check_in_selesai, 0, 5) . '.'
            );
        }

        $alreadyCheckedIn = Attendance::where('teacher_id', $teacher->id)
            ->where('attendance_session_id', $session->id)
            ->whereDate('tanggal', $today)
            ->whereNotNull('check_in_time')
            ->exists();

        if ($alreadyCheckedIn) {
            return back()->with('error', 'Anda sudah check-in untuk ' . $session->nama_sesi . '.');
        }

        $jamMasuk = Carbon::parse($session->jam_masuk);
        $batasTerlambat = $jamMasuk->copy()->addMinutes((int) $session->toleransi_terlambat);

        $statusKehadiran = 'hadir';
        $keterlambatanMenit = 0;

        if (Carbon::parse($jamSekarang)->gt($batasTerlambat)) {
            $statusKehadiran = 'terlambat';
            $keterlambatanMenit = $batasTerlambat->diffInMinutes(Carbon::parse($jamSekarang));
        }

        $photo = $face->saveBase64Image($request->face_image, 'attendance_faces');

        $existingSystemAttendance = Attendance::where('teacher_id', $teacher->id)
            ->where('attendance_session_id', $session->id)
            ->whereDate('tanggal', $today)
            ->first();

        $leaveStatuses = ['izin', 'sakit', 'cuti', 'tugas_luar'];
        $finalStatusKehadiran = $existingSystemAttendance && in_array($existingSystemAttendance->status_kehadiran, $leaveStatuses, true)
            ? $existingSystemAttendance->status_kehadiran
            : $statusKehadiran;

        $payload = [
            'work_schedule_id' => $schedule?->id,
            'tanggal' => $today,
            'check_in_time' => $now,
            'check_in_latitude' => $request->latitude,
            'check_in_longitude' => $request->longitude,
            'check_in_face_photo' => $photo,
            'verification_method' => 'face',
            'status_kehadiran' => $finalStatusKehadiran,
            'keterlambatan_menit' => $finalStatusKehadiran === $statusKehadiran ? $keterlambatanMenit : 0,
            'device_info' => $request->userAgent(),
        ];

        if ($existingSystemAttendance) {
            $existingSystemAttendance->update($payload);
        } else {
            Attendance::create(array_merge($payload, [
                'teacher_id' => $teacher->id,
                'attendance_session_id' => $session->id,
            ]));
        }

        if ($statusKehadiran === 'terlambat') {
            return back()->with('success', 'Check-in berhasil disimpan untuk ' . $session->nama_sesi . '. Status: terlambat ' . $keterlambatanMenit . ' menit.');
        }

        return back()->with('success', 'Check-in berhasil disimpan untuk ' . $session->nama_sesi . '. Status: hadir.');
    }

    public function checkOut(
        Request $request,
        GeoFenceService $geo,
        FaceVerificationService $face
    ) {
        $request->validate([
            'attendance_session_id' => 'required|exists:attendance_sessions,id',
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
            'accuracy' => 'nullable|numeric',
            'face_image' => 'required|string|max:4000000',
        ]);

        $teacher = auth()->user()->teacher;

        if (!$teacher) {
            return back()->with('error', 'Profil guru belum tersedia.');
        }

        $schedule = $this->todaySchedule($teacher);
        $hasAnySchedule = WorkSchedule::where('teacher_id', $teacher->id)->exists();
        $today = now('Asia/Jakarta')->toDateString();
        $attendanceBlockMessage = $this->attendanceBlockMessage($teacher, $today, $schedule, $hasAnySchedule);

        if ($attendanceBlockMessage) {
            return back()->with('error', $attendanceBlockMessage);
        }

        $session = $this->getTeacherSessions($teacher)
            ->where('id', (int) $request->attendance_session_id)
            ->first();

        if (!$session) {
            return back()->with('error', 'Sesi absensi tidak valid untuk akun ini.');
        }

        $location = SchoolLocation::where('status', 'aktif')->first();

        if (!$location) {
            return back()->with('error', 'Lokasi sekolah belum diatur admin.');
        }

        $distance = $geo->distanceInMeters(
            (float) $request->latitude,
            (float) $request->longitude,
            (float) $location->latitude,
            (float) $location->longitude
        );

        $gpsAccuracy = (float) $request->input('accuracy', 0);

        if ($this->isGpsAccuracyTooLow($gpsAccuracy)) {
            return back()->with('error', $this->gpsAccuracyErrorMessage($gpsAccuracy));
        }

        $allowedRadius = (float) $location->radius_meter + min($gpsAccuracy, 100);

        if ($distance > $allowedRadius) {
            return back()->with(
                'error',
                'Check-out ditolak. Anda berada di luar radius sekolah. ' .
                'Jarak terdeteksi: ' . round($distance) . ' meter. ' .
                'Radius sekolah: ' . $location->radius_meter . ' meter. ' .
                'Akurasi GPS: ' . round($gpsAccuracy) . ' meter.'
            );
        }

        if (!$face->verify($request->face_image)) {
            return back()->with('error', 'Validasi wajah gagal.');
        }

        $now = now('Asia/Jakarta');
        $today = $now->toDateString();
        $jamSekarang = $now->format('H:i:s');

        if (!$this->isTimeInRangeString($jamSekarang, $session->batas_check_out_mulai, $session->batas_check_out_selesai)) {
            return back()->with(
                'error',
                'Check-out ' . $session->nama_sesi . ' hanya dapat dilakukan pada pukul ' .
                substr($session->batas_check_out_mulai, 0, 5) . ' - ' .
                substr($session->batas_check_out_selesai, 0, 5) . '.'
            );
        }

        $attendance = Attendance::where('teacher_id', $teacher->id)
            ->where('attendance_session_id', $session->id)
            ->whereDate('tanggal', $today)
            ->first();

        if (!$attendance || !$attendance->check_in_time) {
            return back()->with('error', 'Anda belum check-in untuk ' . $session->nama_sesi . '.');
        }

        if ($attendance->check_out_time) {
            return back()->with('error', 'Anda sudah check-out untuk ' . $session->nama_sesi . '.');
        }

        $photo = $face->saveBase64Image($request->face_image, 'attendance_faces');

        $attendance->update([
            'check_out_time' => $now,
            'check_out_latitude' => $request->latitude,
            'check_out_longitude' => $request->longitude,
            'check_out_face_photo' => $photo,
        ]);

        return back()->with('success', 'Check-out berhasil disimpan untuk ' . $session->nama_sesi . '.');
    }

    public function photo(Attendance $attendance, string $type)
    {
        $user = auth()->user();
        $teacher = $user->teacher;

        $canViewReportPhoto = in_array($user->role, ['super_admin', 'kepala_sekolah'], true);
        $ownsAttendance = $teacher && (int) $attendance->teacher_id === (int) $teacher->id;

        abort_unless($canViewReportPhoto || $ownsAttendance, 403);

        $column = $type === 'check-out' ? 'check_out_face_photo' : 'check_in_face_photo';
        $path = $this->resolveAttendancePhotoPath($attendance->{$column});

        if (!$path) {
            abort(404, 'Foto absensi tidak ditemukan.');
        }

        return response()->file($path);
    }


    private function resolvePerPage(Request $request, int $default = 7): int
    {
        $allowed = [7, 14, 21, 28, 35, 70];
        $perPage = (int) $request->input('per_page', $default);

        return in_array($perPage, $allowed, true) ? $perPage : $default;
    }

    private function isGpsAccuracyTooLow(float $gpsAccuracy): bool
    {
        return $gpsAccuracy > self::MAX_GPS_ACCURACY_METERS;
    }

    private function gpsAccuracyErrorMessage(float $gpsAccuracy): string
    {
        return 'Absensi ditolak. Akurasi GPS terlalu rendah. ' .
            'Akurasi terdeteksi: ' . round($gpsAccuracy) . ' meter. ' .
            'Maksimal akurasi yang diizinkan: ' . self::MAX_GPS_ACCURACY_METERS . ' meter. ' .
            'Aktifkan mode akurasi tinggi GPS, mendekat ke area terbuka, lalu coba lagi.';
    }

    private function resolveAttendancePhotoPath(?string $relativePath): ?string
    {
        if (!$relativePath) {
            return null;
        }

        $relativePath = ltrim(str_replace('\\', '/', $relativePath), '/');

        if (str_starts_with($relativePath, 'public/')) {
            $relativePath = substr($relativePath, strlen('public/'));
        }

        if (str_starts_with($relativePath, 'storage/')) {
            $relativePath = substr($relativePath, strlen('storage/'));
        }

        if (str_contains($relativePath, '..') || !str_starts_with($relativePath, 'attendance_faces/')) {
            return null;
        }

        if (Storage::disk('local')->exists($relativePath)) {
            return Storage::disk('local')->path($relativePath);
        }

        if (Storage::disk('public')->exists($relativePath)) {
            return Storage::disk('public')->path($relativePath);
        }

        $legacyPublicPath = public_path($relativePath);

        return is_file($legacyPublicPath) ? $legacyPublicPath : null;
    }
}
