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
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class TemporaryLeaveRequestTest extends TestCase
{
    use DatabaseTransactions;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_teacher_can_submit_temporary_leave_request(): void
    {
        [$teacher, $user] = $this->createTeacherUser('guru', 'Guru Izin Sementara');

        $response = $this
            ->actingAs($user)
            ->post(route('leave.store'), [
                'jenis_pengajuan' => 'izin',
                'is_sementara' => '1',
                'tanggal_mulai' => '2099-10-11',
                'jam_mulai' => '08:15',
                'jam_selesai' => '10:00',
                'alasan' => 'Ban kendaraan bocor.',
            ]);

        $response->assertRedirect(route('leave.index'));
        $response->assertSessionHas('success');

        $leave = LeaveRequest::where('teacher_id', $teacher->id)->firstOrFail();

        $this->assertTrue($leave->is_sementara);
        $this->assertSame('2099-10-11', $leave->tanggal_mulai->toDateString());
        $this->assertSame('2099-10-11', $leave->tanggal_selesai->toDateString());
        $this->assertSame('08:15', substr($leave->jam_mulai, 0, 5));
        $this->assertSame('10:00', substr($leave->jam_selesai, 0, 5));
        $this->assertSame('disetujui', $leave->status_infal);
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
            'status_infal' => 'disetujui',
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
            'status_infal' => 'disetujui',
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
                'face_image' => 'data:image/jpeg;base64,' . base64_encode('fake-image'),
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
            'infal_teacher_id' => $infalTeacher->id,
            'jenis_pengajuan' => 'izin',
            'is_sementara' => true,
            'tanggal_mulai' => '2099-11-12',
            'tanggal_selesai' => '2099-11-12',
            'jam_mulai' => '08:00',
            'jam_selesai' => '10:00',
            'alasan' => 'Keperluan sementara.',
            'status_pengajuan' => 'disetujui',
            'status_infal' => 'disetujui',
            'approved_by' => $payrollUser->id,
            'approved_at' => now(),
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
    }

    private function createSession(): AttendanceSession
    {
        return AttendanceSession::create([
            'nama_sesi' => 'Sesi Test ' . uniqid(),
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
            'nama_lengkap' => $name . ' ' . uniqid(),
            'email' => uniqid('teacher_', true) . '@example.test',
        ]);

        return [$teacher, $user];
    }

    private function createUser(string $role, string $name = 'User Izin Sementara Test'): User
    {
        $user = User::create([
            'nip' => uniqid('nip_', true),
            'name' => $name . ' ' . uniqid(),
            'email' => uniqid('temporary_leave_', true) . '@example.test',
            'password' => Hash::make('password'),
        ]);

        // role & status tidak fillable — set eksplisit (defense-in-depth).
        $user->forceFill(['role' => $role, 'status' => 'aktif'])->save();

        return $user;
    }
}
