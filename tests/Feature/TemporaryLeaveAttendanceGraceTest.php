<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\AttendanceSession;
use App\Models\LeaveRequest;
use App\Models\SchoolLocation;
use App\Models\Teacher;
use App\Models\TeacherFaceEnrollment;
use App\Models\User;
use App\Services\DailyAttendanceStatusService;
use App\Services\TemporaryLeaveAttendancePolicy;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\InteractsWithLiveness;
use Tests\TestCase;

class TemporaryLeaveAttendanceGraceTest extends TestCase
{
    use DatabaseTransactions;
    use InteractsWithLiveness;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_policy_enforces_boundaries_status_session_and_approval_timing(): void
    {
        Carbon::setTestNow(Carbon::parse('2037-08-03 10:30:00', 'Asia/Jakarta'));
        [$teacher] = $this->createTeacherUser();
        $covered = $this->createSession();
        $wrong = $this->createSession(['jam_masuk' => '13:00:00', 'jam_pulang' => '16:00:00']);
        $teacher->attendanceSessions()->attach([$covered->id, $wrong->id]);
        $leave = $this->createLeave($teacher, ['approved_at' => '2037-08-03 08:00:00']);
        $policy = app(TemporaryLeaveAttendancePolicy::class);

        $atDeadline = $policy->resolve($teacher, $covered, '2037-08-03', now('Asia/Jakarta'));

        $this->assertNotNull($atDeadline);
        $this->assertTrue($atDeadline['is_grace_active']);
        $this->assertSame('10:30:00', $atDeadline['grace_deadline']->format('H:i:s'));
        $this->assertFalse($policy->resolve($teacher, $covered, '2037-08-03', now('Asia/Jakarta')->addSecond())['is_grace_active']);
        $this->assertNull($policy->resolve($teacher, $wrong, '2037-08-03', now('Asia/Jakarta')));

        $leave->update(['status_pengajuan' => 'pending']);
        $this->assertNull($policy->resolve($teacher, $covered, '2037-08-03', now('Asia/Jakarta')));
        $leave->update(['status_pengajuan' => 'ditolak']);
        $this->assertNull($policy->resolve($teacher, $covered, '2037-08-03', now('Asia/Jakarta')));
        $leave->update(['status_pengajuan' => 'disetujui', 'approved_at' => '2037-08-03 10:30:01']);
        $this->assertNull($policy->resolve($teacher, $covered, '2037-08-03', now('Asia/Jakarta')->addMinute()));
    }

    public function test_policy_caps_grace_at_session_checkout_time(): void
    {
        Carbon::setTestNow(Carbon::parse('2037-08-03 16:00:00', 'Asia/Jakarta'));
        [$teacher] = $this->createTeacherUser();
        $session = $this->createSession(['jam_pulang' => '16:00:00']);
        $teacher->attendanceSessions()->attach($session->id);
        $this->createLeave($teacher, [
            'jam_selesai' => '15:45:00',
            'approved_at' => '2037-08-03 08:00:00',
        ]);

        $window = app(TemporaryLeaveAttendancePolicy::class)->resolve($teacher, $session, '2037-08-03');

        $this->assertSame('16:00:00', $window['grace_deadline']->format('H:i:s'));
        $this->assertTrue($window['is_grace_active']);
    }

    public function test_daily_sync_defers_alpha_cleans_stale_alpha_and_creates_after_grace(): void
    {
        Carbon::setTestNow(Carbon::parse('2037-08-03 09:00:00', 'Asia/Jakarta'));
        [$teacher] = $this->createTeacherUser();
        $session = $this->createSession();
        $teacher->attendanceSessions()->attach($session->id);
        $this->createLeave($teacher, ['approved_at' => '2037-08-03 08:00:00']);
        Attendance::create([
            'teacher_id' => $teacher->id,
            'attendance_session_id' => $session->id,
            'tanggal' => '2037-08-03',
            'status_kehadiran' => 'alfa',
            'verification_method' => 'face',
            'device_info' => 'system-auto-status',
        ]);
        $service = app(DailyAttendanceStatusService::class);

        $service->syncTeacherForDate($teacher, '2037-08-03');
        $this->assertDatabaseMissing('attendances', [
            'teacher_id' => $teacher->id,
            'attendance_session_id' => $session->id,
            'status_kehadiran' => 'alfa',
        ]);

        Carbon::setTestNow(Carbon::parse('2037-08-03 10:30:00', 'Asia/Jakarta'));
        $service->syncTeacherForDate($teacher, '2037-08-03');
        $this->assertSame(0, Attendance::where('teacher_id', $teacher->id)->count());

        Carbon::setTestNow(Carbon::parse('2037-08-03 10:30:01', 'Asia/Jakarta'));
        $service->syncTeacherForDate($teacher, '2037-08-03');
        $this->assertDatabaseHas('attendances', [
            'teacher_id' => $teacher->id,
            'attendance_session_id' => $session->id,
            'status_kehadiran' => 'alfa',
            'device_info' => 'system-auto-status',
        ]);
    }

    public function test_check_in_during_grace_still_verifies_evidence_and_stores_audit_reason(): void
    {
        Carbon::setTestNow(Carbon::parse('2037-08-03 10:30:00', 'Asia/Jakarta'));
        Storage::fake('local');
        $this->fakeFaceVerification();
        [$teacher, $user] = $this->createTeacherUser();
        $session = $this->createSession();
        $teacher->attendanceSessions()->attach($session->id);
        $leave = $this->createLeave($teacher, ['approved_at' => '2037-08-03 08:00:00']);
        $this->activateOnlyTestLocation();

        $this->actingAs($user)->post(route('attendance.checkin'), [
            'attendance_session_id' => $session->id,
            'latitude' => -6.20000000,
            'longitude' => 106.81666600,
            'accuracy' => 15,
            ...$this->livenessPayload($user, $teacher, \App\Models\BiometricLivenessChallenge::PURPOSE_CHECK_IN, $session->id),
        ])->assertSessionHas('success', fn (string $message) => str_contains($message, 'grace izin sementara'));

        $attendance = Attendance::where('teacher_id', $teacher->id)->firstOrFail();
        $this->assertSame('hadir', $attendance->status_kehadiran);
        $this->assertSame(0, $attendance->keterlambatan_menit);
        $this->assertTrue($attendance->temporaryLeaveRequest->is($leave));
        Http::assertSentCount(1);
    }

    public function test_attendance_page_prioritizes_grace_session_and_shows_consistent_audit_badge(): void
    {
        Carbon::setTestNow(Carbon::parse('2037-08-03 09:00:00', 'Asia/Jakarta'));
        [$teacher, $user] = $this->createTeacherUser();
        $graceSession = $this->createSession(['nama_sesi' => 'Sesi Grace']);
        $nativeSession = $this->createSession([
            'nama_sesi' => 'Sesi Native',
            'jam_masuk' => '09:00:00',
            'jam_pulang' => '12:00:00',
            'batas_check_in_mulai' => '08:30:00',
            'batas_check_in_selesai' => '09:30:00',
        ]);
        $teacher->attendanceSessions()->attach([$graceSession->id, $nativeSession->id]);
        $leave = $this->createLeave($teacher, ['approved_at' => '2037-08-03 08:00:00']);

        $this->actingAs($user)->get(route('attendance.index'))
            ->assertOk()
            ->assertViewHas('activeSession', fn ($session) => $session && $session->is($graceSession))
            ->assertSee('Izin sementara disetujui');

        Attendance::create([
            'teacher_id' => $teacher->id,
            'attendance_session_id' => $graceSession->id,
            'temporary_leave_request_id' => $leave->id,
            'tanggal' => '2037-08-03',
            'verification_method' => 'face',
            'status_kehadiran' => 'hadir',
        ]);

        $this->actingAs($user)->get(route('attendance.index'))
            ->assertOk()
            ->assertSee('Grace izin sementara #'.$leave->id);
    }

    public function test_approval_syncs_only_requested_teacher_and_date_while_grace_is_active(): void
    {
        Carbon::setTestNow(Carbon::parse('2037-08-03 09:00:00', 'Asia/Jakarta'));
        [$teacher] = $this->createTeacherUser();
        [$otherTeacher] = $this->createTeacherUser();
        $approver = User::create([
            'username' => uniqid('ap_'),
            'name' => 'Approver Grace',
            'email' => uniqid('approver_', true).'@example.test',
            'password' => Hash::make('password'),
        ]);
        $approver->forceFill(['role' => 'kepala_sekolah', 'status' => 'aktif'])->save();
        $session = $this->createSession();
        $teacher->attendanceSessions()->attach($session->id);
        $leave = $this->createLeave($teacher, [
            'status_pengajuan' => 'pending',
            'approved_at' => null,
        ]);

        foreach ([$teacher, $otherTeacher] as $subject) {
            Attendance::create([
                'teacher_id' => $subject->id,
                'attendance_session_id' => $session->id,
                'tanggal' => '2037-08-03',
                'status_kehadiran' => 'alfa',
                'verification_method' => 'face',
                'device_info' => 'system-auto-status',
            ]);
        }

        $this->actingAs($approver)->post(route('leave.approve', $leave))->assertSessionHas('success');

        $this->assertDatabaseMissing('attendances', [
            'teacher_id' => $teacher->id,
            'tanggal' => '2037-08-03',
            'status_kehadiran' => 'alfa',
        ]);
        $this->assertDatabaseHas('attendances', [
            'teacher_id' => $otherTeacher->id,
            'tanggal' => '2037-08-03',
            'status_kehadiran' => 'alfa',
        ]);
    }

    public function test_temporary_leave_validation_rejects_past_unassigned_overlap_and_full_day_equivalent(): void
    {
        Carbon::setTestNow(Carbon::parse('2037-08-03 06:00:00', 'Asia/Jakarta'));
        [$teacher, $user] = $this->createTeacherUser();
        $session = $this->createSession(['jam_pulang' => '10:00:00']);
        $teacher->attendanceSessions()->attach($session->id);
        $base = [
            'jenis_pengajuan' => 'izin',
            'is_sementara' => '1',
            'tanggal_mulai' => '2037-08-03',
            'jam_mulai' => '06:30',
            'jam_selesai' => '08:00',
            'alasan' => 'Keperluan test.',
        ];

        $this->actingAs($user)->post(route('leave.store'), array_replace($base, ['tanggal_mulai' => '2037-08-02']))
            ->assertSessionHasErrors('tanggal_mulai');
        $this->actingAs($user)->post(route('leave.store'), array_replace($base, ['jam_mulai' => '08:15', 'jam_selesai' => '09:00']))
            ->assertSessionHasErrors('jam_mulai');
        $this->actingAs($user)->post(route('leave.store'), $base)->assertRedirect(route('leave.index'));
        $this->actingAs($user)->post(route('leave.store'), array_replace($base, ['jam_mulai' => '06:45', 'jam_selesai' => '08:30']))
            ->assertSessionHasErrors('jam_mulai');
        $this->actingAs($user)->post(route('leave.store'), array_replace($base, ['jam_selesai' => '09:30']))
            ->assertSessionHasErrors('jam_selesai');
    }

    public function test_null_on_delete_keeps_attendance_and_removes_audit_reference(): void
    {
        [$teacher] = $this->createTeacherUser();
        $session = $this->createSession();
        $leave = $this->createLeave($teacher);
        $attendance = Attendance::create([
            'teacher_id' => $teacher->id,
            'attendance_session_id' => $session->id,
            'temporary_leave_request_id' => $leave->id,
            'tanggal' => '2037-08-03',
            'verification_method' => 'face',
            'status_kehadiran' => 'hadir',
        ]);

        $leave->delete();

        $this->assertNull($attendance->fresh()->temporary_leave_request_id);
    }

    public function test_existing_past_temporary_leave_can_be_edited_without_new_session_rules(): void
    {
        Carbon::setTestNow(Carbon::parse('2037-08-03 09:00:00', 'Asia/Jakarta'));
        [$teacher, $user] = $this->createTeacherUser();
        $leave = $this->createLeave($teacher, [
            'tanggal_mulai' => '2037-08-02',
            'tanggal_selesai' => '2037-08-02',
            'status_pengajuan' => 'pending',
            'approved_at' => null,
        ]);

        $this->actingAs($user)->put(route('leave.update', $leave), [
            'is_sementara' => '1',
            'tanggal_mulai' => '2037-08-02',
            'jam_mulai' => '08:00',
            'jam_selesai' => '09:00',
            'alasan' => 'Alasan lama diperbarui.',
        ])->assertRedirect(route('leave.index'));

        $this->assertSame('Alasan lama diperbarui.', $leave->fresh()->alasan);
    }

    private function createLeave(Teacher $teacher, array $overrides = []): LeaveRequest
    {
        return LeaveRequest::create(array_merge([
            'teacher_id' => $teacher->id,
            'jenis_pengajuan' => 'izin',
            'is_sementara' => true,
            'tanggal_mulai' => '2037-08-03',
            'tanggal_selesai' => '2037-08-03',
            'jam_mulai' => '06:30:00',
            'jam_selesai' => '10:00:00',
            'alasan' => 'Izin sementara test.',
            'status_pengajuan' => 'disetujui',
            'approved_at' => '2037-08-03 07:00:00',
        ], $overrides));
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
            'batas_check_out_mulai' => '16:00:00',
            'batas_check_out_selesai' => '18:00:00',
            'status' => 'aktif',
        ], $overrides));
    }

    private function createTeacherUser(): array
    {
        $user = User::create([
            'username' => uniqid('user_', true),
            'name' => 'Guru Grace Test',
            'email' => uniqid('grace_', true).'@example.test',
            'password' => Hash::make('password'),
        ]);
        $user->forceFill(['role' => 'guru', 'status' => 'aktif'])->save();
        $teacher = Teacher::create([
            'user_id' => $user->id,
            'nama_lengkap' => 'Guru Grace '.uniqid(),
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
            'nama_lokasi' => 'Sekolah Grace Test',
            'latitude' => -6.20000000,
            'longitude' => 106.81666600,
            'radius_meter' => 150,
            'status' => 'aktif',
        ]);
    }

}
