<?php

namespace App\Services;

use App\Exceptions\FaceVerificationException;
use App\Models\Attendance;
use App\Models\AttendanceSession;
use App\Models\BiometricLivenessChallenge;
use App\Models\SchoolLocation;
use App\Models\Teacher;
use App\Models\TeacherFaceEnrollment;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

/*
|--------------------------------------------------------------------------
| AttendanceService
|--------------------------------------------------------------------------
|
| Seluruh logic absensi non-wajah berada di sini dan WAJIB identik dengan
| project sistem-absensi-sekolah-hayyan. Satu-satunya perbedaan yang
| diizinkan adalah isi method verifyFace() dan persistFacePhoto():
|
|   - project ini            : pencocokan wajah terdaftar via FastAPI
|   - project hayyan         : face capture saja (tanpa pencocokan)
|
| Jangan menambahkan cabang logic lain di luar dua method tersebut.
|
*/
class AttendanceService
{
    public function __construct(
        private GeoFenceService $geo,
        private FaceVerificationService $face,
        private AttendanceWindowService $windowService,
        private DailyAttendanceStatusService $statusService,
        private TemporaryLeaveAttendancePolicy $temporaryLeavePolicy,
        private WorkScheduleResolver $scheduleResolver,
        private BiometricLivenessChallengeService $livenessChallenges,
    ) {}

    public function checkIn(Teacher $teacher, array $data): array
    {
        $now = now(config('app.timezone'));
        $context = $this->resolveSessionContext($teacher, $data['attendance_session_id'] ?? null, $now, 'check_in');
        $session = $context['session'] ?? null;

        if (! $session) {
            return $this->fail('Sesi absensi tidak valid untuk akun ini.');
        }

        $attendanceDate = $context['date'];
        $today = $attendanceDate->toDateString();
        $blockReason = $this->resolveAttendanceBlock($teacher, $today);

        if ($blockReason) {
            return $this->fail($blockReason);
        }

        $location = SchoolLocation::where('status', 'aktif')->first();

        if (! $location) {
            return $this->fail('Lokasi sekolah belum diatur admin.');
        }

        $gpsResult = $this->validateGps($data, $location);

        if (! $gpsResult['ok']) {
            return $gpsResult;
        }

        $grace = $this->temporaryLeavePolicy->resolve($teacher, $session, $today, $now);
        $isGraceActive = (bool) ($grace['is_grace_active'] ?? false);

        if (! $this->windowService->isCheckInOpen($session, $now, $attendanceDate) && ! $isGraceActive) {
            return $this->fail(
                'Check-in '.$session->nama_sesi.' hanya dapat dilakukan pada pukul '.
                substr($session->batas_check_in_mulai, 0, 5).' - '.
                substr($session->batas_check_in_selesai, 0, 5).'.'
            );
        }

        $alreadyCheckedIn = Attendance::where('teacher_id', $teacher->id)
            ->where('attendance_session_id', $session->id)
            ->whereDate('tanggal', $today)
            ->whereNotNull('check_in_time')
            ->exists();

        if ($alreadyCheckedIn) {
            return $this->fail('Anda sudah check-in untuk '.$session->nama_sesi.'.');
        }

        try {
            $verification = $this->verifyFace(
                $teacher,
                $data,
                BiometricLivenessChallenge::PURPOSE_CHECK_IN,
                $session->id,
            );
        } catch (FaceVerificationException $exception) {
            return $this->fail(
                $exception->getMessage(),
                $exception->reason,
                'biometric',
                $exception->retryable,
            );
        } catch (RuntimeException $exception) {
            return $this->fail($exception->getMessage(), 'verification_failed', 'biometric');
        }

        if (! $verification['ok']) {
            return $verification;
        }

        [$statusKehadiran, $keterlambatanMenit] = $this->resolveLateness($session, $now, $attendanceDate, $isGraceActive);

        $schedule = $this->scheduleResolver->scheduleForSession($teacher, $session, $today);
        $photo = null;

        try {
            $saved = DB::transaction(function () use (
                $teacher, $session, $today, $schedule, $now, $data, $verification,
                $statusKehadiran, $keterlambatanMenit, $grace, &$photo
            ): bool {
                Teacher::whereKey($teacher->id)->lockForUpdate()->firstOrFail();

                $attendance = Attendance::where('teacher_id', $teacher->id)
                    ->where('attendance_session_id', $session->id)
                    ->where('tanggal', $today)
                    ->lockForUpdate()
                    ->first();

                if ($attendance?->check_in_time) {
                    return false;
                }

                $photo = $this->persistFacePhoto($verification['audit_image'] ?? null);

                $leaveStatuses = ['izin', 'sakit', 'cuti', 'tugas_luar'];
                $finalStatus = $attendance && in_array($attendance->status_kehadiran, $leaveStatuses, true)
                    ? $attendance->status_kehadiran
                    : $statusKehadiran;

                $payload = array_merge([
                    'work_schedule_id' => $schedule?->id,
                    'temporary_leave_request_id' => $grace['leave']->id ?? null,
                    'tanggal' => $today,
                    'check_in_time' => $now,
                    'check_in_latitude' => $data['latitude'],
                    'check_in_longitude' => $data['longitude'],
                    'check_in_face_photo' => $photo,
                    'verification_method' => $this->verificationMethod(),
                    'status_kehadiran' => $finalStatus,
                    'keterlambatan_menit' => $finalStatus === $statusKehadiran ? $keterlambatanMenit : 0,
                    'device_info' => request()->userAgent(),
                ], $this->checkInVerificationColumns($verification));

                if ($attendance) {
                    $attendance->update($payload);
                } else {
                    Attendance::create(array_merge($payload, [
                        'teacher_id' => $teacher->id,
                        'attendance_session_id' => $session->id,
                    ]));
                }

                return true;
            });
        } catch (Throwable $exception) {
            $this->discardPhoto($photo);
            report($exception);

            return $this->fail('Check-in belum dapat disimpan. Silakan coba kembali.');
        }

        if (! $saved) {
            return $this->fail('Anda sudah check-in untuk '.$session->nama_sesi.'.');
        }

        if ($isGraceActive) {
            return $this->ok(
                'Check-in berhasil disimpan untuk '.$session->nama_sesi.'. Status: hadir dengan grace izin sementara disetujui.',
                $this->biometricSuccess()
            );
        }

        if ($statusKehadiran === 'terlambat') {
            return $this->ok(
                'Check-in berhasil disimpan untuk '.$session->nama_sesi.'. Status: terlambat '.$keterlambatanMenit.' menit.',
                $this->biometricSuccess()
            );
        }

        return $this->ok(
            'Check-in berhasil disimpan untuk '.$session->nama_sesi.'. Status: hadir.',
            $this->biometricSuccess()
        );
    }

    public function checkOut(Teacher $teacher, array $data): array
    {
        $now = now(config('app.timezone'));
        $context = $this->resolveSessionContext($teacher, $data['attendance_session_id'] ?? null, $now, 'check_out');
        $session = $context['session'] ?? null;

        if (! $session) {
            return $this->fail('Sesi absensi tidak valid untuk akun ini.');
        }

        $attendanceDate = $context['date'];
        $today = $attendanceDate->toDateString();
        $blockReason = $this->resolveAttendanceBlock($teacher, $today);

        if ($blockReason) {
            return $this->fail($blockReason);
        }

        $location = SchoolLocation::where('status', 'aktif')->first();

        if (! $location) {
            return $this->fail('Lokasi sekolah belum diatur admin.');
        }

        $gpsResult = $this->validateGps($data, $location, 'Check-out');

        if (! $gpsResult['ok']) {
            return $gpsResult;
        }

        if (! $this->windowService->isCheckOutOpen($session, $now, $attendanceDate)) {
            return $this->fail(
                'Check-out '.$session->nama_sesi.' hanya dapat dilakukan pada pukul '.
                substr($session->batas_check_out_mulai, 0, 5).' - '.
                substr($session->batas_check_out_selesai, 0, 5).'.'
            );
        }

        $attendance = Attendance::where('teacher_id', $teacher->id)
            ->where('attendance_session_id', $session->id)
            ->whereDate('tanggal', $today)
            ->first();

        if (! $attendance || ! $attendance->check_in_time) {
            return $this->fail('Anda belum check-in untuk '.$session->nama_sesi.'.');
        }

        if ($attendance->check_out_time) {
            return $this->fail('Anda sudah check-out untuk '.$session->nama_sesi.'.');
        }

        try {
            $verification = $this->verifyFace(
                $teacher,
                $data,
                BiometricLivenessChallenge::PURPOSE_CHECK_OUT,
                $session->id,
            );
        } catch (FaceVerificationException $exception) {
            return $this->fail(
                $exception->getMessage(),
                $exception->reason,
                'biometric',
                $exception->retryable,
            );
        } catch (RuntimeException $exception) {
            return $this->fail($exception->getMessage(), 'verification_failed', 'biometric');
        }

        if (! $verification['ok']) {
            return $verification;
        }

        $photo = null;

        try {
            $saved = DB::transaction(function () use ($teacher, $session, $today, $now, $data, $verification, &$photo): bool {
                Teacher::whereKey($teacher->id)->lockForUpdate()->firstOrFail();

                $attendance = Attendance::where('teacher_id', $teacher->id)
                    ->where('attendance_session_id', $session->id)
                    ->where('tanggal', $today)
                    ->lockForUpdate()
                    ->first();

                if (! $attendance?->check_in_time || $attendance->check_out_time) {
                    return false;
                }

                $photo = $this->persistFacePhoto($verification['audit_image'] ?? null);

                $attendance->update(array_merge([
                    'check_out_time' => $now,
                    'check_out_latitude' => $data['latitude'],
                    'check_out_longitude' => $data['longitude'],
                    'check_out_face_photo' => $photo,
                    'verification_method' => $this->verificationMethod(),
                ], $this->checkOutVerificationColumns($verification)));

                return true;
            });
        } catch (Throwable $exception) {
            $this->discardPhoto($photo);
            report($exception);

            return $this->fail('Check-out belum dapat disimpan. Silakan coba kembali.');
        }

        if (! $saved) {
            return $this->fail('Status absensi sudah berubah. Muat ulang halaman lalu coba kembali.');
        }

        return $this->ok(
            'Check-out berhasil disimpan untuk '.$session->nama_sesi.'.',
            $this->biometricSuccess()
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Titik batas fitur wajah — HANYA bagian di bawah ini yang boleh berbeda
    |--------------------------------------------------------------------------
    */

    private function verifyFace(Teacher $teacher, array $data, string $purpose, int $attendanceSessionId): array
    {
        if (! $teacher->faceEnrollment) {
            return $this->fail(
                'Wajah belum didaftarkan. Hubungi admin untuk pendaftaran wajah.',
                'face_not_enrolled',
                'biometric',
            );
        }

        if (config('biometrics.liveness_required')) {
            $challenge = null;
            try {
                $challenge = $this->livenessChallenges->begin(
                    $teacher->user,
                    $teacher,
                    (string) ($data['liveness_challenge_id'] ?? ''),
                    $purpose,
                    $attendanceSessionId,
                );
                $frames = $data['liveness_frames'] ?? [];
                $result = $this->face->verifyLiveness($frames, $teacher->faceEnrollment, $challenge);
                if (! $result['matched']) {
                    throw new FaceVerificationException(
                        'face_mismatch',
                        'Wajah tidak cocok dengan data terdaftar. Pastikan wajah menghadap kamera lalu coba lagi.',
                        true,
                    );
                }
                $this->livenessChallenges->markPassed($challenge);

                return [
                    'ok' => true,
                    'message' => '',
                    'distance' => $result['distance'],
                    'audit_image' => $frames[$result['audit_frame_index']] ?? null,
                    'liveness_challenge_id' => $challenge->id,
                ];
            } catch (FaceVerificationException $exception) {
                if ($challenge) {
                    $this->livenessChallenges->markFailed($challenge, $exception->reason);
                }
                throw $exception;
            }
        }

        $auditImage = is_string($data['face_image'] ?? null) ? $data['face_image'] : null;
        $result = $this->face->verify($auditImage, $teacher->faceEnrollment);

        if (! $result['matched']) {
            return $this->fail(
                'Wajah tidak cocok dengan data terdaftar. Pastikan wajah menghadap kamera lalu coba lagi.',
                'face_mismatch',
                'biometric',
                true,
            );
        }

        return [
            'ok' => true,
            'message' => '',
            'distance' => $result['distance'],
            'audit_image' => $auditImage,
        ];
    }

    private function persistFacePhoto(mixed $faceImage): ?string
    {
        return $this->face->saveBase64Image($faceImage, 'attendance_faces');
    }

    private function verificationMethod(): string
    {
        return config('biometrics.liveness_required') ? 'face_liveness_fastapi' : 'face_match_fastapi';
    }

    private function checkInVerificationColumns(array $verification): array
    {
        return [
            'check_in_face_match_score' => $verification['distance'] ?? null,
            'check_in_liveness_challenge_id' => $verification['liveness_challenge_id'] ?? null,
        ];
    }

    private function checkOutVerificationColumns(array $verification): array
    {
        return [
            'check_out_face_match_score' => $verification['distance'] ?? null,
            'check_out_liveness_challenge_id' => $verification['liveness_challenge_id'] ?? null,
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Logic bersama — wajib identik di kedua project
    |--------------------------------------------------------------------------
    */

    private function resolveLateness(
        AttendanceSession $session,
        Carbon $now,
        Carbon $attendanceDate,
        bool $isGraceActive
    ): array {
        if ($isGraceActive) {
            return ['hadir', 0];
        }

        if ($this->windowService->isOnTime($session, $now, $attendanceDate)) {
            return ['hadir', 0];
        }

        // Keterlambatan dihitung dari jam_masuk, bukan dari batas toleransi,
        // agar konsisten dengan label "Terlambat X menit" di semua laporan.
        $jamMasuk = $this->windowService->resolve($session, $attendanceDate)['jam_masuk'];

        return ['terlambat', (int) floor($jamMasuk->diffInMinutes($now))];
    }

    /**
     * @return array{session: AttendanceSession, date: Carbon}|null
     */
    private function resolveSessionContext(
        Teacher $teacher,
        mixed $sessionId,
        Carbon $now,
        string $action
    ): ?array {
        if ($sessionId === null) {
            return null;
        }

        $session = AttendanceSession::query()
            ->whereKey((int) $sessionId)
            ->where('status', 'aktif')
            ->first();

        if (! $session) {
            return null;
        }

        $contexts = collect($this->windowService->candidateAttendanceDates($session, $now))
            ->filter(fn (Carbon $date) => $this->scheduleResolver
                ->sessionsForDate($teacher, $date)
                ->contains('id', $session->id))
            ->map(fn (Carbon $date) => ['session' => $session, 'date' => $date])
            ->values();

        $active = $contexts->first(function (array $context) use ($session, $now, $action): bool {
            return $action === 'check_out'
                ? $this->windowService->isCheckOutOpen($session, $now, $context['date'])
                : $this->windowService->isCheckInOpen($session, $now, $context['date']);
        });

        return $active ?? $contexts->first();
    }

    private function resolveAttendanceBlock(Teacher $teacher, string $date): ?string
    {
        $sessions = $this->scheduleResolver->sessionsForDate($teacher, $date);

        return $this->statusService->attendanceBlockMessage(
            $teacher,
            $date,
            null,
            $this->scheduleResolver->hasAnySchedule($teacher),
            $sessions->isNotEmpty()
        );
    }

    private function validateGps(array $data, SchoolLocation $location, string $label = 'Absensi'): array
    {
        $latitude = $data['latitude'] ?? null;
        $longitude = $data['longitude'] ?? null;

        if ($latitude === null || $longitude === null) {
            return $this->fail('GPS tidak terbaca. Aktifkan lokasi dan coba lagi.');
        }

        $gpsAccuracy = (float) ($data['accuracy'] ?? 0);

        if ($gpsAccuracy < 0) {
            return $this->fail('Akurasi GPS tidak valid.');
        }

        $maxGpsAccuracy = $this->maxGpsAccuracy($location);

        if ($gpsAccuracy > $maxGpsAccuracy) {
            return $this->fail(
                $label.' ditolak. Akurasi GPS terlalu rendah. '.
                'Akurasi terdeteksi: '.round($gpsAccuracy).' meter. '.
                'Maksimal akurasi yang diizinkan: '.round($maxGpsAccuracy).' meter. '.
                'Aktifkan mode akurasi tinggi GPS, mendekat ke area terbuka, lalu coba lagi.'
            );
        }

        $distance = $this->geo->distanceInMeters(
            (float) $latitude,
            (float) $longitude,
            (float) $location->latitude,
            (float) $location->longitude
        );

        /*
        | Akurasi GPS TIDAK boleh memperlebar radius sekolah. Menambahkan
        | toleransi akurasi ke radius memperbesar amplop false-accept menjadi
        | (radius + akurasi), sehingga absensi dari luar area bisa lolos.
        | Akurasi hanya dipakai sebagai gerbang kualitas di atas.
        */
        if ($distance > (float) $location->radius_meter) {
            return $this->fail(
                $label.' ditolak. Anda berada di luar radius sekolah. '.
                'Jarak terdeteksi: '.round($distance).' meter. '.
                'Radius sekolah: '.$location->radius_meter.' meter. '.
                'Akurasi GPS: '.round($gpsAccuracy).' meter.'
            );
        }

        return ['ok' => true, 'message' => ''];
    }

    private function maxGpsAccuracy(SchoolLocation $location): float
    {
        // Nilai per-lokasi menang bila diisi admin; jika tidak, pakai config.
        $perLocation = $location->max_gps_accuracy ?? null;

        if ($perLocation !== null && (float) $perLocation > 0) {
            return (float) $perLocation;
        }

        return (float) config('attendance.max_gps_accuracy_meters', 100);
    }

    private function discardPhoto(?string $photo): void
    {
        if ($photo) {
            Storage::disk('local')->delete($photo);
        }
    }

    private function biometricSuccess(): array
    {
        return [
            'biometric' => [
                'verified' => true,
                'method' => $this->verificationMethod(),
                'model_version' => TeacherFaceEnrollment::MODEL_VERSION,
            ],
        ];
    }

    private function ok(string $message, array $extra = []): array
    {
        return array_merge(['ok' => true, 'message' => $message], $extra);
    }

    private function fail(
        string $message,
        ?string $code = null,
        ?string $stage = null,
        bool $retryable = false,
    ): array {
        return array_filter([
            'ok' => false,
            'message' => $message,
            'code' => $code,
            'stage' => $stage,
            'retryable' => $retryable,
        ], fn ($value) => $value !== null);
    }
}
