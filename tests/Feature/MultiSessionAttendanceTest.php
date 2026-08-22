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
use Tests\Concerns\InteractsWithLiveness;
use Tests\TestCase;

class MultiSessionAttendanceTest extends TestCase
{
    use DatabaseTransactions;
    use InteractsWithLiveness;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_two_sessions_rendered_on_index_page(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-23 07:00:00', 'Asia/Jakarta'));

        [$teacher, $user] = $this->createTeacherUser();
        $sessionA = $this->createSession('Sesi Pagi', '06:30:00', '08:00:00', '12:00:00', '18:00:00');
        $sessionB = $this->createSession('Sesi Siang', '12:00:00', '14:00:00', '15:00:00', '18:00:00');
        $teacher->attendanceSessions()->attach([$sessionA->id, $sessionB->id]);

        $this->activateOnlyTestLocation();

        $response = $this->actingAs($user)->get(route('attendance.index'));

        $response->assertStatus(200);

        // Desain halaman absensi menampilkan satu kartu sesi aktif saja, tetapi
        // seluruh sesi yang ditugaskan tetap dimuat agar pemilihan otomatis
        // dapat berpindah sesi sesuai window yang sedang terbuka.
        $sessions = $response->viewData('sessions');
        $this->assertCount(2, $sessions);
        $this->assertTrue($sessions->contains('id', $sessionA->id));
        $this->assertTrue($sessions->contains('id', $sessionB->id));

        // Pukul 07:00 hanya window check-in Sesi Pagi yang terbuka.
        $activeSession = $response->viewData('activeSession');
        $this->assertNotNull($activeSession);
        $this->assertSame($sessionA->id, $activeSession->id);
        $response->assertSee($sessionA->nama_sesi);
    }

    public function test_active_session_follows_open_window(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-23 13:00:00', 'Asia/Jakarta'));

        [$teacher, $user] = $this->createTeacherUser();
        $sessionA = $this->createSession('Sesi Pagi', '06:30:00', '08:00:00', '12:00:00', '18:00:00');
        $sessionB = $this->createSession('Sesi Siang', '12:00:00', '14:00:00', '15:00:00', '18:00:00');
        $teacher->attendanceSessions()->attach([$sessionA->id, $sessionB->id]);

        $this->activateOnlyTestLocation();

        $response = $this->actingAs($user)->get(route('attendance.index'));

        $response->assertStatus(200);

        // Pukul 13:00 window check-in Sesi Pagi sudah tutup dan Sesi Siang buka,
        // sehingga kartu yang ditampilkan harus berpindah ke Sesi Siang.
        $activeSession = $response->viewData('activeSession');
        $this->assertNotNull($activeSession);
        $this->assertSame($sessionB->id, $activeSession->id);
        $response->assertSee($sessionB->nama_sesi);
    }

    public function test_check_in_one_session_does_not_affect_another(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-23 07:00:00', 'Asia/Jakarta'));
        Storage::fake('local');
        $this->fakeFaceVerification();

        [$teacher, $user] = $this->createTeacherUser();
        $sessionA = $this->createSession('Sesi Pagi', '06:30:00', '08:00:00', '12:00:00', '18:00:00');
        $sessionB = $this->createSession('Sesi Siang', '12:00:00', '14:00:00', '15:00:00', '18:00:00');
        $teacher->attendanceSessions()->attach([$sessionA->id, $sessionB->id]);

        $this->activateOnlyTestLocation();

        $response = $this
            ->actingAs($user)
            ->post(route('attendance.checkin'), [
                'attendance_session_id' => $sessionA->id,
                'latitude' => -6.20000000,
                'longitude' => 106.81666600,
                'accuracy' => 15,
                ...$this->livenessPayload($user, $teacher, \App\Models\BiometricLivenessChallenge::PURPOSE_CHECK_IN, $sessionA->id),
            ]);

        $response->assertSessionHas('success');

        $attendanceA = Attendance::where('teacher_id', $teacher->id)
            ->where('attendance_session_id', $sessionA->id)
            ->whereDate('tanggal', '2026-07-23')
            ->first();
        $this->assertNotNull($attendanceA);
        $this->assertNotNull($attendanceA->check_in_time);

        $attendanceB = Attendance::where('teacher_id', $teacher->id)
            ->where('attendance_session_id', $sessionB->id)
            ->whereDate('tanggal', '2026-07-23')
            ->first();
        $this->assertNull($attendanceB);
    }

    public function test_sync_creates_alfa_per_missed_session(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-23 19:00:00', 'Asia/Jakarta'));

        [$teacher] = $this->createTeacherUser();
        $sessionA = $this->createSession('Sesi Pagi', '06:30:00', '08:00:00', '12:00:00', '18:00:00');
        $sessionB = $this->createSession('Sesi Siang', '12:00:00', '14:00:00', '15:00:00', '18:00:00');
        $teacher->attendanceSessions()->attach([$sessionA->id, $sessionB->id]);

        $this->activateOnlyTestLocation();

        app(DailyAttendanceStatusService::class)->syncForDate('2026-07-23');

        $alfaA = Attendance::where('teacher_id', $teacher->id)
            ->where('attendance_session_id', $sessionA->id)
            ->whereDate('tanggal', '2026-07-23')
            ->where('status_kehadiran', 'alfa')
            ->first();
        $this->assertNotNull($alfaA);

        $alfaB = Attendance::where('teacher_id', $teacher->id)
            ->where('attendance_session_id', $sessionB->id)
            ->whereDate('tanggal', '2026-07-23')
            ->where('status_kehadiran', 'alfa')
            ->first();
        $this->assertNotNull($alfaB);
    }

    public function test_sync_only_marks_missed_session_not_attended_one(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-23 19:00:00', 'Asia/Jakarta'));

        [$teacher] = $this->createTeacherUser();
        $sessionA = $this->createSession('Sesi Pagi', '06:30:00', '08:00:00', '12:00:00', '18:00:00');
        $sessionB = $this->createSession('Sesi Siang', '12:00:00', '14:00:00', '15:00:00', '18:00:00');
        $teacher->attendanceSessions()->attach([$sessionA->id, $sessionB->id]);

        $this->activateOnlyTestLocation();

        Attendance::create([
            'teacher_id' => $teacher->id,
            'attendance_session_id' => $sessionA->id,
            'tanggal' => '2026-07-23',
            'check_in_time' => Carbon::parse('2026-07-23 07:00:00', 'Asia/Jakarta'),
            'check_out_time' => Carbon::parse('2026-07-23 12:00:00', 'Asia/Jakarta'),
            'verification_method' => 'face',
            'status_kehadiran' => 'hadir',
            'keterlambatan_menit' => 0,
        ]);

        app(DailyAttendanceStatusService::class)->syncForDate('2026-07-23');

        $attendanceA = Attendance::where('teacher_id', $teacher->id)
            ->where('attendance_session_id', $sessionA->id)
            ->whereDate('tanggal', '2026-07-23')
            ->first();
        $this->assertSame('hadir', $attendanceA->status_kehadiran);

        $attendanceB = Attendance::where('teacher_id', $teacher->id)
            ->where('attendance_session_id', $sessionB->id)
            ->whereDate('tanggal', '2026-07-23')
            ->first();
        $this->assertNotNull($attendanceB);
        $this->assertSame('alfa', $attendanceB->status_kehadiran);
    }

    public function test_sync_marks_incomplete_per_session(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-23 19:00:00', 'Asia/Jakarta'));

        [$teacher] = $this->createTeacherUser();
        $sessionA = $this->createSession('Sesi Pagi', '06:30:00', '08:00:00', '12:00:00', '18:00:00');
        $teacher->attendanceSessions()->attach([$sessionA->id]);

        $this->activateOnlyTestLocation();

        Attendance::create([
            'teacher_id' => $teacher->id,
            'attendance_session_id' => $sessionA->id,
            'tanggal' => '2026-07-23',
            'check_in_time' => Carbon::parse('2026-07-23 07:00:00', 'Asia/Jakarta'),
            'verification_method' => 'face',
            'status_kehadiran' => 'hadir',
            'keterlambatan_menit' => 0,
        ]);

        app(DailyAttendanceStatusService::class)->syncForDate('2026-07-23');

        $attendance = Attendance::where('teacher_id', $teacher->id)
            ->where('attendance_session_id', $sessionA->id)
            ->whereDate('tanggal', '2026-07-23')
            ->first();
        $this->assertSame('hadir_tidak_lengkap', $attendance->status_kehadiran);
    }

    public function test_payroll_deducts_alpha_once_per_day_across_sessions(): void
    {
        $payrollUser = $this->createUser('bendahara');
        [$teacher] = $this->createTeacherUser();
        $this->createSalary($teacher, 1500000, 30000);

        $sessionA = $this->createSession('Sesi Pagi', '06:30:00', '08:00:00', '12:00:00', '18:00:00');
        $sessionB = $this->createSession('Sesi Siang', '12:00:00', '14:00:00', '15:00:00', '18:00:00');
        $teacher->attendanceSessions()->attach([$sessionA->id, $sessionB->id]);

        $this->activateOnlyTestLocation();

        Carbon::setTestNow(Carbon::parse('2026-07-23 19:00:00', 'Asia/Jakarta'));
        app(DailyAttendanceStatusService::class)->syncForDate('2026-07-23');

        $response = $this
            ->actingAs($payrollUser)
            ->post(route('payroll.generate'), [
                'tahun' => 2026,
                'bulan' => 7,
            ]);

        $response->assertRedirect(route('payroll.index'));

        $period = \App\Models\PayrollPeriod::where('tahun', 2026)->where('bulan', 7)->firstOrFail();
        $item = \App\Models\PayrollItem::where('payroll_period_id', $period->id)
            ->where('teacher_id', $teacher->id)
            ->firstOrFail();

        $this->assertEquals(30000, (float) $item->potongan_absen);
        $this->assertSame(1, $item->jumlah_absen_diganti);
    }

    public function test_attendance_service_rejects_session_not_assigned_to_teacher(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-23 07:00:00', 'Asia/Jakarta'));
        Storage::fake('local');
        $this->fakeFaceVerification();

        [$teacher, $user] = $this->createTeacherUser();
        $sessionA = $this->createSession('Sesi Pagi', '06:30:00', '08:00:00', '12:00:00', '18:00:00');
        $sessionB = $this->createSession('Sesi Orang Lain', '06:30:00', '08:00:00', '12:00:00', '18:00:00');
        $teacher->attendanceSessions()->attach([$sessionA->id]);

        $this->activateOnlyTestLocation();

        $response = $this
            ->actingAs($user)
            ->post(route('attendance.checkin'), [
                'attendance_session_id' => $sessionB->id,
                'latitude' => -6.20000000,
                'longitude' => 106.81666600,
                'accuracy' => 15,
                ...$this->livenessPayload($user, $teacher, \App\Models\BiometricLivenessChallenge::PURPOSE_CHECK_IN, $sessionB->id),
            ]);

        $response->assertSessionHas('error');
    }

    public function test_full_day_leave_creates_leave_status_on_all_sessions(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-23 07:00:00', 'Asia/Jakarta'));

        [$teacher] = $this->createTeacherUser();
        $sessionA = $this->createSession('Sesi Pagi', '06:30:00', '08:00:00', '12:00:00', '18:00:00');
        $sessionB = $this->createSession('Sesi Siang', '12:00:00', '14:00:00', '15:00:00', '18:00:00');
        $teacher->attendanceSessions()->attach([$sessionA->id, $sessionB->id]);

        $this->activateOnlyTestLocation();

        \App\Models\LeaveRequest::create([
            'teacher_id' => $teacher->id,
            'jenis_pengajuan' => 'sakit',
            'tanggal_mulai' => '2026-07-23',
            'tanggal_selesai' => '2026-07-23',
            'alasan' => 'Sakit.',
            'status_pengajuan' => 'disetujui',
        ]);

        app(DailyAttendanceStatusService::class)->syncForDate('2026-07-23');

        $leaveA = Attendance::where('teacher_id', $teacher->id)
            ->where('attendance_session_id', $sessionA->id)
            ->whereDate('tanggal', '2026-07-23')
            ->first();
        $this->assertNotNull($leaveA);
        $this->assertSame('sakit', $leaveA->status_kehadiran);

        $leaveB = Attendance::where('teacher_id', $teacher->id)
            ->where('attendance_session_id', $sessionB->id)
            ->whereDate('tanggal', '2026-07-23')
            ->first();
        $this->assertNotNull($leaveB);
        $this->assertSame('sakit', $leaveB->status_kehadiran);
    }

    public function test_check_in_duplicate_returns_error_not_500(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-23 07:00:00', 'Asia/Jakarta'));
        Storage::fake('local');
        $this->fakeFaceVerification();

        [$teacher, $user] = $this->createTeacherUser();
        $session = $this->createSession();
        $teacher->attendanceSessions()->attach($session->id);

        $this->activateOnlyTestLocation();

        $this->actingAs($user)->post(route('attendance.checkin'), [
            'attendance_session_id' => $session->id,
            'latitude' => -6.20000000,
            'longitude' => 106.81666600,
            'accuracy' => 15,
            ...$this->livenessPayload($user, $teacher, \App\Models\BiometricLivenessChallenge::PURPOSE_CHECK_IN, $session->id),
        ])->assertSessionHas('success');

        $response = $this
            ->actingAs($user)
            ->post(route('attendance.checkin'), [
                'attendance_session_id' => $session->id,
                'latitude' => -6.20000000,
                'longitude' => 106.81666600,
                'accuracy' => 15,
                ...$this->livenessPayload($user, $teacher, \App\Models\BiometricLivenessChallenge::PURPOSE_CHECK_IN, $session->id),
            ]);

        $response->assertSessionHas('error');
    }

    public function test_missing_attendance_detection_per_session(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-23 19:00:00', 'Asia/Jakarta'));

        [$teacher] = $this->createTeacherUser();
        $sessionA = $this->createSession('Sesi Pagi', '06:30:00', '08:00:00', '12:00:00', '18:00:00');
        $sessionB = $this->createSession('Sesi Siang', '12:00:00', '14:00:00', '15:00:00', '18:00:00');
        $teacher->attendanceSessions()->attach([$sessionA->id, $sessionB->id]);

        $this->activateOnlyTestLocation();

        Attendance::create([
            'teacher_id' => $teacher->id,
            'attendance_session_id' => $sessionA->id,
            'tanggal' => '2026-07-23',
            'check_in_time' => Carbon::parse('2026-07-23 07:00:00', 'Asia/Jakarta'),
            'check_out_time' => Carbon::parse('2026-07-23 12:00:00', 'Asia/Jakarta'),
            'verification_method' => 'face',
            'status_kehadiran' => 'hadir',
            'keterlambatan_menit' => 0,
        ]);

        $service = app(DailyAttendanceStatusService::class);
        $missing = $service->missingAttendanceTeachersForDate('2026-07-23');

        $this->assertTrue($missing->contains('id', $teacher->id));
    }

    private function createSession(
        string $name = 'Sesi Test',
        string $checkInStart = '06:30:00',
        string $checkInEnd = '08:00:00',
        string $checkOutStart = '12:00:00',
        string $checkOutEnd = '18:00:00'
    ): AttendanceSession {
        return AttendanceSession::create([
            'nama_sesi' => $name.' '.uniqid(),
            'jam_masuk' => $checkInStart,
            'jam_pulang' => $checkOutEnd,
            'toleransi_terlambat' => 15,
            'batas_check_in_mulai' => $checkInStart,
            'batas_check_in_selesai' => $checkInEnd,
            'batas_check_out_mulai' => $checkOutStart,
            'batas_check_out_selesai' => $checkOutEnd,
            'status' => 'aktif',
        ]);
    }

    private function createTeacherUser(): array
    {
        $user = User::create([
            'username' => uniqid('user_', true),
            'name' => 'Guru Multi Sesi Test',
            'email' => uniqid('multisession_', true).'@example.test',
            'password' => Hash::make('password'),
        ]);

        $user->forceFill(['role' => 'guru', 'status' => 'aktif'])->save();

        $teacher = Teacher::create([
            'user_id' => $user->id,
            'nama_lengkap' => 'Guru Multi Sesi Test '.uniqid(),
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

    private function activateOnlyTestLocation(): void
    {
        SchoolLocation::where('status', 'aktif')->update(['status' => 'nonaktif']);

        SchoolLocation::create([
            'nama_lokasi' => 'Sekolah Test',
            'latitude' => -6.20000000,
            'longitude' => 106.81666600,
            'radius_meter' => 150,
            'status' => 'aktif',
        ]);
    }

    private function createSalary(Teacher $teacher, float $gajiPokok, float $potonganPerAbsen): \App\Models\TeacherSalary
    {
        return \App\Models\TeacherSalary::create([
            'teacher_id' => $teacher->id,
            'gaji_pokok' => $gajiPokok,
            'potongan_per_absen' => $potonganPerAbsen,
            'keterangan' => 'Data test multi-session.',
        ]);
    }

    private function createUser(string $role = 'bendahara'): User
    {
        $user = User::create([
            'username' => uniqid('user_', true),
            'name' => 'User Payroll Multi Test '.uniqid(),
            'email' => uniqid('payroll_', true).'@example.test',
            'password' => Hash::make('password'),
        ]);

        $user->forceFill(['role' => $role, 'status' => 'aktif'])->save();

        return $user;
    }
}
