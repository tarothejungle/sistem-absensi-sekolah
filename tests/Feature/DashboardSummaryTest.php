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

class DashboardSummaryTest extends TestCase
{
    use DatabaseTransactions;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_super_admin_dashboard_counts_incomplete_attendance_separately(): void
    {
        Carbon::setTestNow(Carbon::parse('2037-08-10 18:30:00', 'Asia/Jakarta'));

        $admin = $this->createUser('super_admin', 'Admin Dashboard Test');
        $baseline = $this->actingAs($admin)->get(route('dashboard'));

        $baseline->assertOk();

        $baselineTotal = (int) $baseline->viewData('totalGuru');
        $baselinePresent = (int) $baseline->viewData('hadirHariIni');
        $baselineLate = (int) $baseline->viewData('terlambatHariIni');
        $baselineIncomplete = (int) $baseline->viewData('tidakLengkapHariIni');

        $session = $this->createSession();
        [$presentTeacher] = $this->createTeacherUser('guru', 'Guru Hadir Dashboard');
        [$lateTeacher] = $this->createTeacherUser('guru', 'Guru Terlambat Dashboard');
        [$incompleteTeacher] = $this->createTeacherUser('guru', 'Guru Tidak Lengkap Dashboard');

        $this->createAttendance($presentTeacher, $session, [
            'check_in_time' => Carbon::parse('2037-08-10 07:00:00', 'Asia/Jakarta'),
            'check_out_time' => Carbon::parse('2037-08-10 16:00:00', 'Asia/Jakarta'),
            'status_kehadiran' => 'hadir',
            'keterlambatan_menit' => 0,
        ]);

        $this->createAttendance($lateTeacher, $session, [
            'check_in_time' => Carbon::parse('2037-08-10 07:25:00', 'Asia/Jakarta'),
            'check_out_time' => Carbon::parse('2037-08-10 16:00:00', 'Asia/Jakarta'),
            'status_kehadiran' => 'terlambat',
            'keterlambatan_menit' => 10,
        ]);

        $this->createAttendance($incompleteTeacher, $session, [
            'check_in_time' => Carbon::parse('2037-08-10 07:00:00', 'Asia/Jakarta'),
            'check_out_time' => null,
            'status_kehadiran' => 'hadir',
            'keterlambatan_menit' => 0,
        ]);

        $response = $this->actingAs($admin)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('Tidak Lengkap');

        $this->assertSame($baselineTotal + 3, (int) $response->viewData('totalGuru'));
        $this->assertSame($baselinePresent + 1, (int) $response->viewData('hadirHariIni'));
        $this->assertSame($baselineLate + 1, (int) $response->viewData('terlambatHariIni'));
        $this->assertSame($baselineIncomplete + 1, (int) $response->viewData('tidakLengkapHariIni'));

        $incompleteTeacher->refresh();
        $incompleteAttendance = Attendance::where('teacher_id', $incompleteTeacher->id)
            ->whereDate('tanggal', '2037-08-10')
            ->firstOrFail();

        $this->assertSame('hadir_tidak_lengkap', $incompleteAttendance->status_kehadiran);
    }

    private function createAttendance(Teacher $teacher, AttendanceSession $session, array $overrides): Attendance
    {
        return Attendance::create(array_merge([
            'teacher_id' => $teacher->id,
            'attendance_session_id' => $session->id,
            'tanggal' => '2037-08-10',
            'verification_method' => 'face',
            'device_info' => 'dashboard-summary-test',
        ], $overrides));
    }

    private function createSession(): AttendanceSession
    {
        return AttendanceSession::create([
            'nama_sesi' => 'Sesi Dashboard Test ' . uniqid(),
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

    private function createUser(string $role, string $name = 'User Dashboard Test'): User
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
