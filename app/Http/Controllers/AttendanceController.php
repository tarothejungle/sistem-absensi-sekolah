<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Services\AttendanceService;
use App\Services\AttendanceWindowService;
use App\Services\DailyAttendanceStatusService;
use App\Services\FaceVerificationService;
use App\Services\TemporaryLeaveAttendancePolicy;
use App\Services\WorkScheduleResolver;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class AttendanceController extends Controller
{
    public function index()
    {
        if (! auth()->user()->hasAttendanceAccess()) {
            return redirect()
                ->route('dashboard')
                ->with('error', 'Halaman absensi hanya dapat diakses oleh guru.');
        }

        $teacher = auth()->user()->teacher;
        $perPage = $this->resolvePerPage(request());

        if (! $teacher) {
            return redirect()
                ->route('dashboard')
                ->with('error', 'Data guru belum tersedia untuk akun ini.');
        }

        $teacher->load('faceEnrollment');
        $faceEnrollment = $teacher->faceEnrollment;
        $faceEnrollmentCompatible = $faceEnrollment?->isCompatible() ?? false;

        $now = now(config('app.timezone'));
        $today = $now->toDateString();

        $statusService = app(DailyAttendanceStatusService::class);
        $windowService = app(AttendanceWindowService::class);
        $temporaryLeavePolicy = app(TemporaryLeaveAttendancePolicy::class);

        $scheduleResolver = app(WorkScheduleResolver::class);
        $sessions = $this->buildCurrentSessionContexts(
            $teacher,
            $now,
            $scheduleResolver,
            $windowService,
            $temporaryLeavePolicy,
        );
        $activeSession = null;
        $scheduleMessage = null;
        $relevantDate = $sessions->first()?->attendanceDate ?? Carbon::parse($today, config('app.timezone'));
        $scheduledSessions = $scheduleResolver->sessionsForDate($teacher, $relevantDate);
        $hariIni = $scheduleResolver->hariFromDate($relevantDate);

        $attendanceBlockMessage = $statusService
            ->attendanceBlockMessage(
                $teacher,
                $relevantDate->toDateString(),
                null,
                $scheduleResolver->hasAnySchedule($teacher),
                $scheduledSessions->isNotEmpty()
            );

        if ($attendanceBlockMessage) {
            $scheduleMessage = $attendanceBlockMessage;
            $sessions = collect();
        } else {
            $activeSession = $sessions->first(fn ($session) => $session->canCheckIn && ($session->temporaryLeaveGrace['is_grace_active'] ?? false))
                ?? $sessions->first(fn ($session) => $session->canCheckOut)
                ?? $sessions->first(fn ($session) => $session->canCheckIn)
                ?? $sessions->first(function ($session) {
                    $attendance = $session->todayAttendance;

                    return ! $attendance || ! $attendance->check_in_time || ! $attendance->check_out_time;
                })
                ?? $sessions->last();
        }

        $attendances = Attendance::with(['attendanceSession', 'schedule', 'temporaryLeaveRequest'])
            ->where('teacher_id', $teacher->id)
            ->orderByDesc('tanggal')
            ->orderBy('check_in_time')
            ->paginate($perPage)
            ->withQueryString();

        return view('attendance.index', compact(
            'sessions', 'activeSession', 'attendances', 'scheduleMessage', 'hariIni',
            'faceEnrollment', 'faceEnrollmentCompatible'
        ));
    }

    private function buildCurrentSessionContexts(
        $teacher,
        Carbon $now,
        WorkScheduleResolver $scheduleResolver,
        AttendanceWindowService $windowService,
        TemporaryLeaveAttendancePolicy $temporaryLeavePolicy
    ) {
        $today = $now->copy()->startOfDay();
        $dates = [$today->copy(), $today->copy()->subDay(), $today->copy()->addDay()];

        return collect($dates)
            ->flatMap(function (Carbon $date) use (
                $teacher, $now, $today, $scheduleResolver, $windowService, $temporaryLeavePolicy
            ) {
                return $scheduleResolver->sessionsForDate($teacher, $date)
                    ->map(function ($session) use ($teacher, $date, $now, $today, $windowService, $temporaryLeavePolicy) {
                        $attendance = Attendance::where('teacher_id', $teacher->id)
                            ->where('attendance_session_id', $session->id)
                            ->whereDate('tanggal', $date->toDateString())
                            ->first();
                        $checkInOpen = $windowService->isCheckInOpen($session, $now, $date);
                        $checkOutOpen = $windowService->isCheckOutOpen($session, $now, $date);
                        $hasIncompleteAttendance = $attendance?->check_in_time && ! $attendance->check_out_time;

                        if (! $date->isSameDay($today) && ! $checkInOpen && ! $checkOutOpen && ! $hasIncompleteAttendance) {
                            return null;
                        }

                        $displaySession = clone $session;
                        $displaySession->todayAttendance = $attendance;
                        $displaySession->attendanceDate = $date->copy();
                        $displaySession->temporaryLeaveGrace = $temporaryLeavePolicy->resolve($teacher, $session, $date, $now);

                        $isGraceActive = (bool) ($displaySession->temporaryLeaveGrace['is_grace_active'] ?? false);
                        $displaySession->canCheckIn = ($checkInOpen || $isGraceActive)
                            && (! $attendance || ! $attendance->check_in_time);
                        $displaySession->canCheckOut = $attendance
                            && $attendance->check_in_time
                            && ! $attendance->check_out_time
                            && $checkOutOpen;
                        $displaySession->checkInMessage = $windowService->checkInStatusMessage($session, $now, $date);
                        $displaySession->checkOutMessage = $windowService->checkOutStatusMessage($session, $now, $date);
                        $displaySession->contextPriority = $displaySession->canCheckOut ? 0
                            : (($displaySession->canCheckIn && $isGraceActive) ? 1
                            : ($displaySession->canCheckIn ? 2
                            : ($date->isSameDay($today) ? 3 : 4)));

                        return $displaySession;
                    })
                    ->filter();
            })
            ->sortBy('contextPriority')
            ->unique('id')
            ->values();
    }

    public function biometricStatus(Request $request, FaceVerificationService $face)
    {
        $teacher = $request->user()->teacher;

        if (! $teacher) {
            return response()->json([
                'ready' => false,
                'message' => 'Profil guru belum tersedia.',
                'service' => [
                    'ready' => false,
                    'state' => 'profile_missing',
                    'message' => 'Profil guru belum tersedia.',
                    'model_version' => null,
                ],
                'enrollment' => [
                    'registered' => false,
                    'compatible' => false,
                    'model_version' => null,
                    'enrolled_at' => null,
                ],
            ], 422);
        }

        $teacher->load('faceEnrollment');

        return response()->json($face->readiness($teacher->faceEnrollment));
    }

    public function checkIn(Request $request, AttendanceService $service)
    {
        $teacher = auth()->user()->teacher;

        if (! $teacher) {
            return $this->attendanceResponse([
                'ok' => false,
                'message' => 'Profil guru belum tersedia.',
                'code' => 'profile_missing',
            ]);
        }

        $data = $request->validate([
            'attendance_session_id' => 'required|exists:attendance_sessions,id',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'accuracy' => 'required|numeric|gt:0',
            'face_image' => [Rule::requiredIf(! config('biometrics.liveness_required')), 'nullable', 'string', 'max:'.config('biometrics.max_frame_characters', 1000000)],
            'liveness_challenge_id' => [Rule::requiredIf(config('biometrics.liveness_required')), 'nullable', 'uuid'],
            'liveness_frames' => [Rule::requiredIf(config('biometrics.liveness_required')), 'nullable', 'array', 'size:'.config('biometrics.frame_count', 4)],
            'liveness_frames.*' => 'required|string|max:'.config('biometrics.max_frame_characters', 1000000),
        ]);

        $result = $service->checkIn($teacher, $data);

        return $this->attendanceResponse($result);
    }

    public function checkOut(Request $request, AttendanceService $service)
    {
        $teacher = auth()->user()->teacher;

        if (! $teacher) {
            return $this->attendanceResponse([
                'ok' => false,
                'message' => 'Profil guru belum tersedia.',
                'code' => 'profile_missing',
            ]);
        }

        $data = $request->validate([
            'attendance_session_id' => 'required|exists:attendance_sessions,id',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'accuracy' => 'required|numeric|gt:0',
            'face_image' => [Rule::requiredIf(! config('biometrics.liveness_required')), 'nullable', 'string', 'max:'.config('biometrics.max_frame_characters', 1000000)],
            'liveness_challenge_id' => [Rule::requiredIf(config('biometrics.liveness_required')), 'nullable', 'uuid'],
            'liveness_frames' => [Rule::requiredIf(config('biometrics.liveness_required')), 'nullable', 'array', 'size:'.config('biometrics.frame_count', 4)],
            'liveness_frames.*' => 'required|string|max:'.config('biometrics.max_frame_characters', 1000000),
        ]);

        $result = $service->checkOut($teacher, $data);

        return $this->attendanceResponse($result);
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

        if (! $path) {
            abort(404, 'Foto absensi tidak ditemukan.');
        }

        $response = response()->file($path);
        $response->setPrivate();
        $response->headers->set('Cache-Control', 'private, no-store, max-age=0, must-revalidate');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', '0');

        return $response;
    }

    private function attendanceResponse(array $result)
    {
        if (request()->expectsJson()) {
            return response()->json($result, $result['ok'] ? 200 : 422);
        }

        return back()->with($result['ok'] ? 'success' : 'error', $result['message']);
    }

    private function resolvePerPage(Request $request, int $default = 7): int
    {
        $allowed = [7, 14, 21, 28, 35, 70];
        $perPage = (int) $request->input('per_page', $default);

        return in_array($perPage, $allowed, true) ? $perPage : $default;
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

        $todayName = strtolower(now(config('app.timezone'))->englishDayOfWeek);

        return $dayMap[$todayName] ?? 'senin';
    }

    private function resolveAttendancePhotoPath(?string $relativePath): ?string
    {
        if (! $relativePath) {
            return null;
        }

        $relativePath = ltrim(str_replace('\\', '/', $relativePath), '/');

        if (str_starts_with($relativePath, 'public/')) {
            $relativePath = substr($relativePath, strlen('public/'));
        }

        if (str_starts_with($relativePath, 'storage/')) {
            $relativePath = substr($relativePath, strlen('storage/'));
        }

        if (str_contains($relativePath, '..') || ! str_starts_with($relativePath, 'attendance_faces/')) {
            return null;
        }

        if (Storage::disk('local')->exists($relativePath)) {
            return Storage::disk('local')->path($relativePath);
        }

        return null;
    }
}
