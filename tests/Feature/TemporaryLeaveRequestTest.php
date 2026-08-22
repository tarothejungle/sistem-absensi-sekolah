<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\AttendanceSession;
use App\Models\LeaveRequest;
use App\Models\PayrollPeriod;
use App\Models\Teacher;
use App\Models\TeacherSalary;
use App\Models\User;
use App\Services\DailyAttendanceStatusService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\InteractsWithLiveness;
use Tests\TestCase;

class TemporaryLeaveRequestTest extends TestCase
{
    use DatabaseTransactions;
    use InteractsWithLiveness;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_teacher_can_submit_temporary_leave_request(): void
    {
        Storage::fake('local');
        [$teacher, $user] = $this->createTeacherUser('guru', 'Guru Izin Sementara');
        $session = $this->createSession();
        $teacher->attendanceSessions()->attach($session->id);

        $response = $this
            ->actingAs($user)
            ->post(route('leave.store'), [
                'is_sementara' => '1',
                'tanggal_mulai' => '2099-10-11',
                'jam_mulai' => '06:30',
                'jam_selesai' => '10:00',
                'alasan' => 'Ban kendaraan bocor.',
                'lampiran' => UploadedFile::fake()->create('bukti.pdf', 100, 'application/pdf'),
            ]);

        $response->assertRedirect(route('leave.index'));
        $response->assertSessionHas('success');

        $leave = LeaveRequest::where('teacher_id', $teacher->id)->firstOrFail();

        $this->assertTrue($leave->is_sementara);
        $this->assertSame('2099-10-11', $leave->tanggal_mulai->toDateString());
        $this->assertSame('2099-10-11', $leave->tanggal_selesai->toDateString());
        $this->assertSame('06:30', substr($leave->jam_mulai, 0, 5));
        $this->assertSame('10:00', substr($leave->jam_selesai, 0, 5));
        $this->assertSame('izin', $leave->jenis_pengajuan);
        $this->assertSame('Ban kendaraan bocor.', $leave->alasan);
        $this->assertSame(0, $leave->infalTeachers()->count());
        $this->assertNotNull($leave->lampiran);
        Storage::disk('local')->assertExists($leave->lampiran);
    }

    public function test_temporary_leave_requires_reason(): void
    {
        [$teacher, $user] = $this->createTeacherUser('guru', 'Guru Tanpa Alasan');
        $session = $this->createSession();
        $teacher->attendanceSessions()->attach($session->id);

        $this->actingAs($user)->post(route('leave.store'), [
            'is_sementara' => '1',
            'tanggal_mulai' => '2099-10-11',
            'jam_mulai' => '06:30',
            'jam_selesai' => '10:00',
        ])->assertSessionHasErrors('alasan');
    }

    public function test_temporary_leave_form_hides_only_infal_section(): void
    {
        [, $user] = $this->createTeacherUser('guru', 'Guru Form Sementara');

        $response = $this->actingAs($user)
            ->withSession(['_old_input' => ['is_sementara' => '1']])
            ->get(route('leave.create'));

        $response->assertOk();
        $html = $response->getContent();

        $this->assertMatchesRegularExpression(
            '/<div class="ui-form-section d-none" data-leave-infal-section>.*?<h5 class="ui-form-section-title">Guru Pengganti<\/h5>/s',
            $html
        );
        $this->assertMatchesRegularExpression(
            '/<div class="ui-form-section">\s*<div class="ui-form-section-head">.*?<h5 class="ui-form-section-title">Alasan dan Lampiran<\/h5>/s',
            $html
        );
        $this->assertStringNotContainsString('data-leave-full-day-section', $html);
    }

    public function test_temporary_leave_ignores_full_day_fields_and_infals(): void
    {
        [$teacher, $user] = $this->createTeacherUser('guru', 'Guru Payload Sementara');
        [$infalTeacher] = $this->createTeacherUser('guru', 'Guru Infal Tidak Dipakai');
        $session = $this->createSession();
        $teacher->attendanceSessions()->attach($session->id);

        $this->actingAs($user)->post(route('leave.store'), [
            'jenis_pengajuan' => 'cuti',
            'is_sementara' => '1',
            'tanggal_mulai' => '2099-10-12',
            'tanggal_selesai' => '2099-12-31',
            'jam_mulai' => '06:30',
            'jam_selesai' => '10:00',
            'alasan' => 'Nilai manipulatif',
            'infal_teacher_ids' => [$infalTeacher->id],
        ])->assertRedirect(route('leave.index'));

        $leave = LeaveRequest::where('teacher_id', $teacher->id)->firstOrFail();

        $this->assertSame('izin', $leave->jenis_pengajuan);
        $this->assertSame('Nilai manipulatif', $leave->alasan);
        $this->assertTrue($leave->tanggal_mulai->isSameDay($leave->tanggal_selesai));
        $this->assertSame(0, $leave->infalTeachers()->count());
    }

    public function test_pending_full_day_leave_can_be_changed_to_temporary_without_infal(): void
    {
        [$teacher, $user] = $this->createTeacherUser('guru', 'Guru Konversi Sementara');
        [$infalTeacher] = $this->createTeacherUser('guru', 'Guru Infal Konversi');
        $session = $this->createSession();
        $teacher->attendanceSessions()->attach($session->id);
        $leave = LeaveRequest::create([
            'teacher_id' => $teacher->id,
            'jenis_pengajuan' => 'cuti',
            'is_sementara' => false,
            'tanggal_mulai' => '2099-10-13',
            'tanggal_selesai' => '2099-10-14',
            'alasan' => 'Cuti penuh.',
            'status_pengajuan' => 'pending',
        ]);
        $leave->infalTeachers()->attach($infalTeacher->id, ['status_infal' => 'pending']);

        $this->actingAs($user)->put(route('leave.update', $leave), [
            'is_sementara' => '1',
            'tanggal_mulai' => '2099-10-13',
            'jam_mulai' => '06:30',
            'jam_selesai' => '10:00',
            'alasan' => 'Kendala perjalanan.',
        ])->assertRedirect(route('leave.index'));

        $leave->refresh();

        $this->assertTrue($leave->is_sementara);
        $this->assertSame('izin', $leave->jenis_pengajuan);
        $this->assertSame('Kendala perjalanan.', $leave->alasan);
        $this->assertSame(0, $leave->infalTeachers()->count());
    }

    public function test_temporary_leave_requires_end_time_after_start_time(): void
    {
        [$teacher, $user] = $this->createTeacherUser('guru', 'Guru Jam Tidak Valid');

        $response = $this
            ->actingAs($user)
            ->from(route('leave.create'))
            ->post(route('leave.store'), [
                'jenis_pengajuan' => 'izin',
                'is_sementara' => '1',
                'tanggal_mulai' => '2099-10-11',
                'jam_mulai' => '10:00',
                'jam_selesai' => '09:00',
                'alasan' => 'Keperluan sementara.',
            ]);

        $response->assertRedirect(route('leave.create'));
        $response->assertSessionHasErrors('jam_selesai');

        $this->assertSame(0, LeaveRequest::where('teacher_id', $teacher->id)->count());
    }

    public function test_approved_temporary_leave_does_not_create_full_day_attendance_status(): void
    {
        [$teacher] = $this->createTeacherUser('guru', 'Guru Absensi Sementara');
        $session = $this->createSession();
        $teacher->attendanceSessions()->attach($session->id);

        LeaveRequest::create([
            'teacher_id' => $teacher->id,
            'jenis_pengajuan' => 'izin',
            'is_sementara' => true,
            'tanggal_mulai' => '2099-10-11',
            'tanggal_selesai' => '2099-10-11',
            'jam_mulai' => '08:00',
            'jam_selesai' => '10:00',
            'alasan' => 'Keperluan sementara.',
            'status_pengajuan' => 'disetujui',
        ]);

        app(DailyAttendanceStatusService::class)->syncForDate('2099-10-11');

        $this->assertDatabaseMissing('attendances', [
            'teacher_id' => $teacher->id,
            'tanggal' => '2099-10-11',
            'status_kehadiran' => 'izin',
            'device_info' => 'system-auto-status',
        ]);

        $this->assertSame(0, Attendance::where('teacher_id', $teacher->id)->count());
    }

    public function test_approved_full_day_leave_waives_attendance_requirement(): void
    {
        Carbon::setTestNow(Carbon::parse('2037-10-11 07:15:00', 'Asia/Jakarta'));

        [$teacher, $user] = $this->createTeacherUser('guru', 'Guru Izin Seharian');
        $session = $this->createSession();
        $teacher->attendanceSessions()->attach($session->id);

        LeaveRequest::create([
            'teacher_id' => $teacher->id,
            'jenis_pengajuan' => 'sakit',
            'is_sementara' => false,
            'tanggal_mulai' => '2037-10-11',
            'tanggal_selesai' => '2037-10-11',
            'alasan' => 'Sakit seharian.',
            'status_pengajuan' => 'disetujui',
        ]);

        app(DailyAttendanceStatusService::class)->syncForDate('2037-10-11');

        $attendance = Attendance::where('teacher_id', $teacher->id)
            ->whereDate('tanggal', '2037-10-11')
            ->firstOrFail();

        $this->assertSame('sakit', $attendance->status_kehadiran);
        $this->assertNull($attendance->check_in_time);

        $response = $this->actingAs($user)->get(route('attendance.index'));

        $response->assertOk();
        $response->assertSee('Kewajiban absen hari ini otomatis digugurkan');
        $response->assertViewHas('sessions', fn ($sessions) => $sessions->isEmpty());

        $this->actingAs($user)
            ->post(route('attendance.checkin'), [
                'attendance_session_id' => $session->id,
                'latitude' => -6.20000000,
                'longitude' => 106.81666600,
                'accuracy' => 10,
                ...$this->livenessPayload($user, $teacher, \App\Models\BiometricLivenessChallenge::PURPOSE_CHECK_IN, $session->id),
            ])
            ->assertSessionHas('error');

        $attendance->refresh();

        $this->assertSame('sakit', $attendance->status_kehadiran);
        $this->assertNull($attendance->check_in_time);
    }

    public function test_payroll_ignores_temporary_leave_for_full_day_deduction(): void
    {
        $payrollUser = $this->createUser('bendahara', 'Bendahara Payroll Sementara');
        [$mainTeacher] = $this->createTeacherUser('guru', 'Guru Utama Sementara');
        [$infalTeacher] = $this->createTeacherUser('guru', 'Guru Infal Sementara');

        $this->createSalary($mainTeacher, 1500000, 30000);
        $this->createSalary($infalTeacher, 800000, 20000);

        LeaveRequest::create([
            'teacher_id' => $mainTeacher->id,
            'jenis_pengajuan' => 'izin',
            'is_sementara' => true,
            'tanggal_mulai' => '2099-11-12',
            'tanggal_selesai' => '2099-11-12',
            'jam_mulai' => '08:00',
            'jam_selesai' => '10:00',
            'alasan' => 'Keperluan sementara.',
            'status_pengajuan' => 'disetujui',
            'approved_by' => $payrollUser->id,
            'approved_at' => now(),
        ]);
        $leave = LeaveRequest::where('teacher_id', $mainTeacher->id)->latest('id')->firstOrFail();
        $leave->infalTeachers()->sync([$infalTeacher->id => ['status_infal' => 'disetujui']]);
        $session = $this->createSession();
        Attendance::create([
            'teacher_id' => $mainTeacher->id,
            'attendance_session_id' => $session->id,
            'temporary_leave_request_id' => $leave->id,
            'tanggal' => '2099-11-12',
            'check_in_time' => Carbon::parse('2099-11-12 10:15:00', 'Asia/Jakarta'),
            'verification_method' => 'face',
            'status_kehadiran' => 'hadir',
            'keterlambatan_menit' => 0,
        ]);

        $this
            ->actingAs($payrollUser)
            ->post(route('payroll.generate'), [
                'tahun' => 2099,
                'bulan' => 11,
            ])
            ->assertRedirect(route('payroll.index'));

        $period = PayrollPeriod::where('tahun', 2099)->where('bulan', 11)->firstOrFail();
        $mainItem = $period->items()->where('teacher_id', $mainTeacher->id)->firstOrFail();
        $infalItem = $period->items()->where('teacher_id', $infalTeacher->id)->firstOrFail();

        $this->assertEquals(0, (float) $mainItem->potongan_absen);
        $this->assertEquals(0, (float) $infalItem->tambahan_infal);
        $this->assertSame(0, $mainItem->jumlah_absen_diganti);
        $this->assertSame(0, $infalItem->jumlah_mengganti);
        $this->assertDatabaseMissing('attendances', [
            'teacher_id' => $mainTeacher->id,
            'tanggal' => '2099-11-12',
            'status_kehadiran' => 'alfa',
        ]);
    }

    private function createSession(): AttendanceSession
    {
        return AttendanceSession::create([
            'nama_sesi' => 'Sesi Test '.uniqid(),
            'jam_masuk' => '07:00:00',
            'jam_pulang' => '17:00:00',
            'toleransi_terlambat' => 15,
            'batas_check_in_mulai' => '06:30:00',
            'batas_check_in_selesai' => '08:00:00',
            'batas_check_out_mulai' => '12:00:00',
            'batas_check_out_selesai' => '18:00:00',
            'status' => 'aktif',
        ]);
    }

    private function createSalary(Teacher $teacher, float $gajiPokok, float $potonganPerAbsen): TeacherSalary
    {
        return TeacherSalary::create([
            'teacher_id' => $teacher->id,
            'gaji_pokok' => $gajiPokok,
            'potongan_per_absen' => $potonganPerAbsen,
            'keterangan' => 'Data test izin sementara.',
        ]);
    }

    private function createTeacherUser(string $role, string $name): array
    {
        $user = $this->createUser($role, $name);

        $teacher = Teacher::create([
            'user_id' => $user->id,
            'nama_lengkap' => $name.' '.uniqid(),
            'email' => uniqid('teacher_', true).'@example.test',
        ]);

        return [$teacher, $user];
    }

    private function createUser(string $role, string $name = 'User Izin Sementara Test'): User
    {
        $user = User::create([
            'username' => uniqid('user_', true),
            'name' => $name.' '.uniqid(),
            'email' => uniqid('temporary_leave_', true).'@example.test',
            'password' => Hash::make('password'),
        ]);

        // role & status tidak fillable — set eksplisit (defense-in-depth).
        $user->forceFill(['role' => $role, 'status' => 'aktif'])->save();

        return $user;
    }
}
