<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\AttendanceSession;
use App\Models\Teacher;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DashboardMissingAttendanceTest extends TestCase
{
    use DatabaseTransactions;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_super_admin_and_principal_dashboards_show_teachers_who_have_not_checked_in(): void
    {
        Carbon::setTestNow(Carbon::parse('2037-08-10 08:30:00', 'Asia/Jakarta'));

        $session = $this->createSession();
        [$missingTeacher] = $this->createTeacherUser('guru', 'Guru Belum Absen Dashboard');
        [$presentTeacher] = $this->createTeacherUser('guru', 'Guru Sudah Absen Dashboard');

        $missingTeacher->attendanceSessions()->attach($session->id);
        $presentTeacher->attendanceSessions()->attach($session->id);

        $this->createAttendance($presentTeacher, $session, [
            'check_in_time' => Carbon::parse('2037-08-10 07:00:00', 'Asia/Jakarta'),
            'check_out_time' => null,
            'status_kehadiran' => 'hadir',
            'keterlambatan_menit' => 0,
        ]);

        foreach (['super_admin', 'kepala_sekolah'] as $role) {
            $viewer = $this->createUser($role, 'Viewer Dashboard ' . $role);

            $response = $this->actingAs($viewer)->get(route('dashboard'));

            $response->assertOk();
            $response->assertSee('Guru Belum Absen Hari Ini');
            $response->assertSee($missingTeacher->nama_lengkap);

            $missingTeachers = $response->viewData('guruBelumAbsenHariIni');

            $this->assertSame(
                (int) $response->viewData('belumAbsenHariIni'),
                $missingTeachers->count()
            );
            $this->assertTrue($missingTeachers->contains('id', $missingTeacher->id));
            $this->assertFalse($missingTeachers->contains('id', $presentTeacher->id));
        }

        $missingAttendance = Attendance::where('teacher_id', $missingTeacher->id)
            ->whereDate('tanggal', '2037-08-10')
            ->firstOrFail();

        $this->assertSame('alfa', $missingAttendance->status_kehadiran);
    }

    private function createAttendance(Teacher $teacher, AttendanceSession $session, array $overrides): Attendance
    {
        return Attendance::create(array_merge([
            'teacher_id' => $teacher->id,
            'attendance_session_id' => $session->id,
            'tanggal' => '2037-08-10',
            'verification_method' => 'face',
            'device_info' => 'dashboard-missing-attendance-test',
        ], $overrides));
    }

    private function createSession(): AttendanceSession
    {
        return AttendanceSession::create([
            'nama_sesi' => 'Sesi Dashboard Belum Absen Test ' . uniqid(),
            'jam_masuk' => '07:00:00',
            'jam_pulang' => '16:00:00',
            'toleransi_terlambat' => 15,
            'batas_check_in_mulai' => '06:30:00',
            'batas_check_in_selesai' => '08:00:00',
            'batas_check_out_mulai' => '15:00:00',
            'batas_check_out_selesai' => '18:00:00',
            'status' => 'aktif',
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

    private function createUser(string $role, string $name = 'User Dashboard Belum Absen Test'): User
    {
        $user = User::create([
            'nip' => uniqid('nip_', true),
            'name' => $name . ' ' . uniqid(),
            'email' => uniqid('dashboard_', true) . '@example.test',
            'password' => Hash::make('password'),
        ]);

        // role & status tidak fillable — set eksplisit (defense-in-depth).
        $user->forceFill(['role' => $role, 'status' => 'aktif'])->save();

        return $user;
    }
}
