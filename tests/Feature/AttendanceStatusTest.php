<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\AttendanceSession;
use App\Models\SchoolLocation;
use App\Models\Teacher;
use App\Models\TeacherFaceEnrollment;
use App\Models\User;
use App\Services\DailyAttendanceStatusService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AttendanceStatusTest extends TestCase
{
    use DatabaseTransactions;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_check_in_rejects_low_gps_accuracy(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-30 07:00:00', 'Asia/Jakarta'));

        [$teacher, $user] = $this->createTeacherUser();
        $session = $this->createSession();
        $teacher->attendanceSessions()->attach($session->id);

        SchoolLocation::create([
            'nama_lokasi' => 'Sekolah Test',
            'latitude' => -6.20000000,
            'longitude' => 106.81666600,
            'radius_meter' => 150,
            'status' => 'aktif',
        ]);

        $response = $this
            ->actingAs($user)
            ->post(route('attendance.checkin'), [
                'attendance_session_id' => $session->id,
                'latitude' => -6.20000000,
                'longitude' => 106.81666600,
                'accuracy' => 250,
                'face_image' => 'data:image/jpeg;base64,'.base64_encode('fake-image'),
                'face_match_score' => 0.2,
            ]);

        $response->assertSessionHas('error');

        $this->assertDatabaseMissing('attendances', [
            'teacher_id' => $teacher->id,
            'attendance_session_id' => $session->id,
            'tanggal' => '2026-06-30',
        ]);
    }

    public function test_check_in_requires_valid_gps_evidence(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-30 07:00:00', 'Asia/Jakarta'));

        [$teacher, $user] = $this->createTeacherUser();
        $session = $this->createSession();
        $teacher->attendanceSessions()->attach($session->id);
        $this->activateOnlyTestLocation();

        $this->actingAs($user)
            ->post(route('attendance.checkin'), [
                'attendance_session_id' => $session->id,
                'latitude' => -91,
                'longitude' => 181,
                'face_image' => $this->validFaceImage(),
            ])
            ->assertSessionHasErrors(['latitude', 'longitude', 'accuracy']);

        Http::assertNothingSent();
        $this->assertDatabaseMissing('attendances', [
            'teacher_id' => $teacher->id,
            'attendance_session_id' => $session->id,
        ]);
    }

    public function test_gps_accuracy_does_not_expand_school_radius(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-30 07:00:00', 'Asia/Jakarta'));
        Storage::fake('local');
        $this->fakeMatchedFace(0.2);

        [$teacher, $user] = $this->createTeacherUser();
        $session = $this->createSession();
        $teacher->attendanceSessions()->attach($session->id);

        SchoolLocation::where('status', 'aktif')->update(['status' => 'nonaktif']);
        SchoolLocation::create([
            'nama_lokasi' => 'Sekolah Radius Ketat',
            'latitude' => -6.20000000,
            'longitude' => 106.81666600,
            'radius_meter' => 50,
            'max_gps_accuracy' => 100,
            'status' => 'aktif',
        ]);

        $this->actingAs($user)
            ->post(route('attendance.checkin'), [
                'attendance_session_id' => $session->id,
                'latitude' => -6.19950000,
                'longitude' => 106.81666600,
                'accuracy' => 100,
                'face_image' => $this->validFaceImage(),
            ])
            ->assertSessionHas('error');

        $this->assertDatabaseMissing('attendances', [
            'teacher_id' => $teacher->id,
            'attendance_session_id' => $session->id,
        ]);
    }

    public function test_attendance_post_rejects_wrong_role_and_inactive_user(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-30 07:00:00', 'Asia/Jakarta'));

        $admin = User::create([
            'nip' => uniqid('admin_', true),
            'name' => 'Admin Test',
            'email' => uniqid('admin_', true).'@example.test',
            'password' => Hash::make('password'),
        ]);
        $admin->forceFill(['role' => 'super_admin', 'status' => 'aktif'])->save();

        $this->actingAs($admin)
            ->post(route('attendance.checkin'))
            ->assertForbidden();

        [$teacher, $inactiveUser] = $this->createTeacherUser();
        $inactiveUser->forceFill(['status' => 'nonaktif'])->save();

        $this->actingAs($inactiveUser)
            ->post(route('attendance.checkin'))
            ->assertRedirect(route('login'))
            ->assertSessionHas('error', 'Akun Anda sudah tidak aktif.');

        $this->assertGuest();
        $this->assertDatabaseMissing('attendances', ['teacher_id' => $teacher->id]);
    }

    public function test_duplicate_check_in_is_rejected_before_face_verification(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-30 07:00:00', 'Asia/Jakarta'));

        [$teacher, $user] = $this->createTeacherUser();
        $session = $this->createSession();
        $teacher->attendanceSessions()->attach($session->id);
        $this->activateOnlyTestLocation();
        Attendance::create([
            'teacher_id' => $teacher->id,
            'attendance_session_id' => $session->id,
            'tanggal' => '2026-06-30',
            'check_in_time' => Carbon::parse('2026-06-30 06:55:00', 'Asia/Jakarta'),
            'verification_method' => 'face',
            'status_kehadiran' => 'hadir',
            'keterlambatan_menit' => 0,
        ]);
        Http::fake();

        $this->actingAs($user)
            ->post(route('attendance.checkin'), [
                'attendance_session_id' => $session->id,
                'latitude' => -6.20000000,
                'longitude' => 106.81666600,
                'accuracy' => 15,
                'face_image' => $this->validFaceImage(),
            ])
            ->assertSessionHas('error', 'Anda sudah check-in untuk '.$session->nama_sesi.'.');

        Http::assertNothingSent();
        $this->assertSame(1, Attendance::where('teacher_id', $teacher->id)->count());
    }

    public function test_check_in_success_stores_attendance(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-30 07:00:00', 'Asia/Jakarta'));
        Storage::fake('local');
        $this->fakeMatchedFace(0.2345);

        [$teacher, $user] = $this->createTeacherUser();
        $session = $this->createSession();
        $teacher->attendanceSessions()->attach($session->id);

        $this->activateOnlyTestLocation();

        $response = $this
            ->actingAs($user)
            ->post(route('attendance.checkin'), [
                'attendance_session_id' => $session->id,
                'latitude' => -6.20000000,
                'longitude' => 106.81666600,
                'accuracy' => 15,
                'face_image' => $this->validFaceImage(),
            ]);

        $response->assertSessionHas('success');
        $response->assertSessionMissing('error');

        $attendance = Attendance::where('teacher_id', $teacher->id)
            ->where('attendance_session_id', $session->id)
            ->whereDate('tanggal', '2026-06-30')
            ->first();

        $this->assertNotNull($attendance);
        $this->assertNotNull($attendance->check_in_time);
        $this->assertSame('hadir', $attendance->status_kehadiran);
        $this->assertSame('face_match_fastapi', $attendance->verification_method);
        $this->assertEquals(0.2345, $attendance->check_in_face_match_score);
        Http::assertSent(fn ($request) => $request->url() === config('services.face.url').'/api/v1/faces/verify'
            && $request->hasHeader('X-API-Key')
            && count($request['embedding']) === TeacherFaceEnrollment::EMBEDDING_DIMENSIONS);
    }

    public function test_check_out_success_after_check_in(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-30 12:30:00', 'Asia/Jakarta'));
        Storage::fake('local');
        $this->fakeMatchedFace(0.1987);

        [$teacher, $user] = $this->createTeacherUser();
        $session = $this->createSession();
        $teacher->attendanceSessions()->attach($session->id);

        $this->activateOnlyTestLocation();

        $attendance = Attendance::create([
            'teacher_id' => $teacher->id,
            'attendance_session_id' => $session->id,
            'tanggal' => '2026-06-30',
            'check_in_time' => Carbon::parse('2026-06-30 07:00:00', 'Asia/Jakarta'),
            'verification_method' => 'face',
            'status_kehadiran' => 'hadir',
            'keterlambatan_menit' => 0,
        ]);

        $response = $this
            ->actingAs($user)
            ->post(route('attendance.checkout'), [
                'attendance_session_id' => $session->id,
                'latitude' => -6.20000000,
                'longitude' => 106.81666600,
                'accuracy' => 15,
                'face_image' => $this->validFaceImage(),
            ]);

        $response->assertSessionHas('success');
        $response->assertSessionMissing('error');

        $attendance->refresh();

        $this->assertNotNull($attendance->check_out_time);
        $this->assertEquals(0.1987, $attendance->check_out_face_match_score);
    }

    public function test_check_in_rejects_user_without_face_enrollment(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-30 07:00:00', 'Asia/Jakarta'));
        [$teacher, $user] = $this->createTeacherUser();
        $teacher->faceEnrollment()->delete();
        $session = $this->createSession();
        $teacher->attendanceSessions()->attach($session->id);
        $this->activateOnlyTestLocation();

        $this->actingAs($user)
            ->post(route('attendance.checkin'), [
                'attendance_session_id' => $session->id,
                'latitude' => -6.20000000,
                'longitude' => 106.81666600,
                'accuracy' => 15,
                'face_image' => $this->validFaceImage(),
                'face_match_score' => 0.2,
            ])
            ->assertSessionHas('error', 'Wajah belum didaftarkan. Hubungi admin untuk pendaftaran wajah.');
    }

    public function test_check_in_rejects_face_mismatch(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-30 07:00:00', 'Asia/Jakarta'));
        Storage::fake('local');
        config([
            'services.face.url' => 'http://127.0.0.1:8001',
            'services.face.api_key' => 'test-secret',
        ]);
        Http::fake([
            '*' => Http::response([
                'matched' => false,
                'distance' => 0.81,
                'threshold' => 0.45,
                'faces_detected' => 1,
                'model_version' => TeacherFaceEnrollment::MODEL_VERSION,
            ]),
        ]);

        [$teacher, $user] = $this->createTeacherUser();
        $session = $this->createSession();
        $teacher->attendanceSessions()->attach($session->id);
        $this->activateOnlyTestLocation();

        $this->actingAs($user)
            ->post(route('attendance.checkin'), [
                'attendance_session_id' => $session->id,
                'latitude' => -6.20000000,
                'longitude' => 106.81666600,
                'accuracy' => 15,
                'face_image' => $this->validFaceImage(),
                'face_match_score' => 0.01,
            ])
            ->assertSessionHas('error', 'Wajah tidak cocok dengan data terdaftar. Pastikan wajah menghadap kamera lalu coba lagi.');

        $this->assertDatabaseMissing('attendances', [
            'teacher_id' => $teacher->id,
            'attendance_session_id' => $session->id,
            'tanggal' => '2026-06-30',
        ]);
    }

    public function test_check_in_rejects_when_face_service_is_unavailable(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-30 07:00:00', 'Asia/Jakarta'));
        config([
            'services.face.url' => 'http://127.0.0.1:8001',
            'services.face.api_key' => 'test-secret',
        ]);
        Http::fake(fn () => Http::failedConnection());

        [$teacher, $user] = $this->createTeacherUser();
        $session = $this->createSession();
        $teacher->attendanceSessions()->attach($session->id);
        $this->activateOnlyTestLocation();

        $this->actingAs($user)
            ->post(route('attendance.checkin'), [
                'attendance_session_id' => $session->id,
                'latitude' => -6.20000000,
                'longitude' => 106.81666600,
                'accuracy' => 15,
                'face_image' => $this->validFaceImage(),
            ])
            ->assertSessionHas('error', 'Layanan verifikasi wajah sedang tidak tersedia. Silakan coba kembali.');

        $this->assertDatabaseMissing('attendances', [
            'teacher_id' => $teacher->id,
            'attendance_session_id' => $session->id,
            'tanggal' => '2026-06-30',
        ]);
    }

    public function test_sync_marks_checked_in_without_checkout_as_incomplete_after_checkout_cutoff(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-30 18:30:00', 'Asia/Jakarta'));

        [$teacher] = $this->createTeacherUser();
        $session = $this->createSession();
        $teacher->attendanceSessions()->attach($session->id);

        $attendance = Attendance::create([
            'teacher_id' => $teacher->id,
            'attendance_session_id' => $session->id,
            'tanggal' => '2026-06-30',
            'check_in_time' => Carbon::parse('2026-06-30 07:00:00', 'Asia/Jakarta'),
            'verification_method' => 'face',
            'status_kehadiran' => 'hadir',
            'keterlambatan_menit' => 0,
        ]);

        app(DailyAttendanceStatusService::class)->syncForDate('2026-06-30');

        $attendance->refresh();

        $this->assertSame('hadir_tidak_lengkap', $attendance->status_kehadiran);
        $this->assertNull($attendance->check_out_time);
    }

    public function test_sync_keeps_checked_in_without_checkout_open_before_checkout_cutoff(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-30 12:30:00', 'Asia/Jakarta'));

        [$teacher] = $this->createTeacherUser();
        $session = $this->createSession();
        $teacher->attendanceSessions()->attach($session->id);

        $attendance = Attendance::create([
            'teacher_id' => $teacher->id,
            'attendance_session_id' => $session->id,
            'tanggal' => '2026-06-30',
            'check_in_time' => Carbon::parse('2026-06-30 07:00:00', 'Asia/Jakarta'),
            'verification_method' => 'face',
            'status_kehadiran' => 'hadir',
            'keterlambatan_menit' => 0,
        ]);

        app(DailyAttendanceStatusService::class)->syncForDate('2026-06-30');

        $attendance->refresh();

        $this->assertSame('hadir', $attendance->status_kehadiran);
        $this->assertNull($attendance->check_out_time);
    }

    public function test_attendance_page_prioritizes_currently_actionable_session(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-30 13:00:00', 'Asia/Jakarta'));

        [$teacher, $user] = $this->createTeacherUser();
        $morning = $this->createSession([
            'nama_sesi' => 'Sesi Pagi Terlewat',
            'jam_masuk' => '07:00:00',
            'jam_pulang' => '10:00:00',
            'batas_check_in_mulai' => '06:30:00',
            'batas_check_in_selesai' => '08:00:00',
            'batas_check_out_mulai' => '09:30:00',
            'batas_check_out_selesai' => '10:30:00',
        ]);
        $afternoon = $this->createSession([
            'nama_sesi' => 'Sesi Siang Aktif',
            'jam_masuk' => '13:00:00',
            'jam_pulang' => '16:00:00',
            'batas_check_in_mulai' => '12:30:00',
            'batas_check_in_selesai' => '13:30:00',
            'batas_check_out_mulai' => '15:30:00',
            'batas_check_out_selesai' => '16:30:00',
        ]);
        $teacher->attendanceSessions()->attach([$morning->id, $afternoon->id]);

        $this->actingAs($user)
            ->get(route('attendance.index'))
            ->assertOk()
            ->assertViewHas('activeSession', fn ($session) => $session && $session->is($afternoon));
    }

    public function test_sync_marks_each_missed_required_session_as_alfa(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-30 18:30:00', 'Asia/Jakarta'));

        [$teacher] = $this->createTeacherUser();
        $morning = $this->createSession(['nama_sesi' => 'Sesi Pagi']);
        $afternoon = $this->createSession([
            'nama_sesi' => 'Sesi Siang',
            'jam_masuk' => '13:00:00',
            'jam_pulang' => '17:00:00',
            'batas_check_in_mulai' => '12:30:00',
            'batas_check_in_selesai' => '14:00:00',
            'batas_check_out_mulai' => '16:00:00',
            'batas_check_out_selesai' => '18:00:00',
        ]);
        $teacher->attendanceSessions()->attach([$morning->id, $afternoon->id]);

        Attendance::create([
            'teacher_id' => $teacher->id,
            'attendance_session_id' => $morning->id,
            'tanggal' => '2026-06-30',
            'check_in_time' => Carbon::parse('2026-06-30 07:00:00', 'Asia/Jakarta'),
            'check_out_time' => Carbon::parse('2026-06-30 12:30:00', 'Asia/Jakarta'),
            'verification_method' => 'face',
            'status_kehadiran' => 'hadir',
            'keterlambatan_menit' => 0,
        ]);

        app(DailyAttendanceStatusService::class)->syncForDate('2026-06-30');

        $this->assertDatabaseHas('attendances', [
            'teacher_id' => $teacher->id,
            'attendance_session_id' => $afternoon->id,
            'tanggal' => '2026-06-30',
            'status_kehadiran' => 'alfa',
            'device_info' => 'system-auto-status',
        ]);

        $missingTeachers = app(DailyAttendanceStatusService::class)
            ->missingAttendanceTeachersForDate('2026-06-30');

        $this->assertTrue($missingTeachers->contains('id', $teacher->id));
    }

    private function activateOnlyTestLocation(): void
    {
        // DB test = DB dev (DatabaseTransactions), bisa sudah ada lokasi aktif
        // lain. Nonaktifkan dulu agar lokasi test yang dipakai controller.
        SchoolLocation::where('status', 'aktif')->update(['status' => 'nonaktif']);

        SchoolLocation::create([
            'nama_lokasi' => 'Sekolah Test',
            'latitude' => -6.20000000,
            'longitude' => 106.81666600,
            'radius_meter' => 150,
            'status' => 'aktif',
        ]);
    }

    private function validFaceImage(): string
    {
        // PNG 1x1 transparan yang valid agar lolos FaceVerificationService::verify().
        $png = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAAC0lEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg=='
        );

        return 'data:image/png;base64,'.base64_encode($png);
    }

    private function fakeMatchedFace(float $distance): void
    {
        config([
            'services.face.url' => 'http://127.0.0.1:8001',
            'services.face.api_key' => 'test-secret',
        ]);

        Http::fake([
            '*' => Http::response([
                'matched' => true,
                'distance' => $distance,
                'threshold' => 0.45,
                'faces_detected' => 1,
                'model_version' => TeacherFaceEnrollment::MODEL_VERSION,
            ]),
        ]);
    }

    private function createSession(array $overrides = []): AttendanceSession
    {
        return AttendanceSession::create(array_merge([
            'nama_sesi' => 'Sesi Test '.uniqid(),
            'jam_masuk' => '07:00:00',
            'jam_pulang' => '17:00:00',
            'toleransi_terlambat' => 15,
            'batas_check_in_mulai' => '06:30:00',
            'batas_check_in_selesai' => '08:00:00',
            'batas_check_out_mulai' => '12:00:00',
            'batas_check_out_selesai' => '18:00:00',
            'status' => 'aktif',
        ], $overrides));
    }

    private function createTeacherUser(): array
    {
        $user = User::create([
            'nip' => uniqid('nip_', true),
            'name' => 'Guru Absensi Test',
            'email' => uniqid('attendance_', true).'@example.test',
            'password' => Hash::make('password'),
        ]);

        $user->forceFill(['role' => 'guru', 'status' => 'aktif'])->save();

        $teacher = Teacher::create([
            'user_id' => $user->id,
            'nama_lengkap' => 'Guru Absensi Test '.uniqid(),
            'email' => uniqid('teacher_', true).'@example.test',
        ]);

        TeacherFaceEnrollment::create([
            'teacher_id' => $teacher->id,
            'face_descriptor' => array_fill(0, TeacherFaceEnrollment::EMBEDDING_DIMENSIONS, 0.01),
            'model_version' => TeacherFaceEnrollment::MODEL_VERSION,
            'enrolled_by' => $user->id,
            'enrolled_at' => now(),
        ]);

        return [$teacher, $user];
    }
}
