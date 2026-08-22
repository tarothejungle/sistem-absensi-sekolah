<?php

namespace Tests\Feature;

use App\Models\AttendanceSession;
use App\Models\LoginActivity;
use App\Models\Teacher;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class DashboardRoleTest extends TestCase
{
    use DatabaseTransactions;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_staff_dashboards_receive_profile_and_active_session_data(): void
    {
        foreach (Teacher::DATA_GURU_ROLES as $role) {
            [$user, $teacher] = $this->createTeacherUser($role);
            $active = $this->createSession('Aktif '.$role, 'aktif');
            $inactive = $this->createSession('Nonaktif '.$role, 'nonaktif');
            $teacher->attendanceSessions()->attach([$active->id, $inactive->id]);

            $response = $this->actingAs($user)->get(route('dashboard'));
            $response->assertOk()->assertViewHasAll(['profileCompleteness', 'sessionWindows']);
            $sessions = $response->viewData('teacher')->attendanceSessions;
            $this->assertSame([$active->id], $sessions->pluck('id')->all(), $role);
            $this->assertArrayHasKey($active->id, $response->viewData('sessionWindows'));
            $this->assertArrayNotHasKey($inactive->id, $response->viewData('sessionWindows'));
            $response->assertSee('Batas hadir tepat waktu');
        }
    }

    public function test_super_admin_login_activity_is_limited_to_last_24_hours(): void
    {
        Carbon::setTestNow(Carbon::parse('2037-08-10 12:00:00', 'Asia/Jakarta'));
        $admin = $this->createUser('super_admin');
        $current = LoginActivity::create([
            'name' => 'Aktivitas Baru',
            'role' => 'tenaga_kependidikan',
        ]);
        $current->forceFill(['created_at' => now()->subHours(23), 'updated_at' => now()->subHours(23)])->save();
        $expired = LoginActivity::create(['name' => 'Aktivitas Lama']);
        $expired->forceFill(['created_at' => now()->subHours(25), 'updated_at' => now()->subHours(25)])->save();

        $response = $this->actingAs($admin)->get(route('dashboard'));

        $response
            ->assertOk()
            ->assertSee('Aktivitas Baru')
            ->assertSee('Tenaga Kependidikan')
            ->assertDontSee('tenaga_kependidikan')
            ->assertDontSee('Aktivitas Lama');
    }

    public function test_unknown_role_is_forbidden(): void
    {
        $user = $this->createUser('guru');
        $user->setAttribute('role', 'unknown');

        $this->actingAs($user)->get(route('dashboard'))->assertForbidden();
    }

    private function createTeacherUser(string $role): array
    {
        $user = $this->createUser($role);
        $teacher = Teacher::create([
            'user_id' => $user->id,
            'nama_lengkap' => 'Dashboard '.$role,
            'jenis_kelamin' => 'L',
            'no_hp' => '081234567890',
            'jabatan' => 'Staf',
            'mata_pelajaran' => $role === 'guru' ? 'Matematika' : null,
        ]);

        return [$user, $teacher];
    }

    private function createUser(string $role): User
    {
        $user = User::create([
            'username' => uniqid('user_', true),
            'name' => 'Dashboard User',
            'email' => uniqid('dashboard_', true).'@example.test',
            'password' => 'password',
            'profile_photo' => 'profile_photo/test.jpg',
            'instansi_mengajar' => 'MI Lantaburo',
            'tempat_lahir' => 'Tangerang',
            'tanggal_lahir' => '1990-01-15',
            'pendidikan_terakhir' => 'S1',
        ]);
        $user->forceFill(['role' => $role, 'status' => 'aktif'])->save();

        return $user;
    }

    private function createSession(string $name, string $status): AttendanceSession
    {
        return AttendanceSession::create([
            'nama_sesi' => $name,
            'jam_masuk' => '07:00:00',
            'jam_pulang' => '12:00:00',
            'toleransi_terlambat' => 15,
            'batas_check_in_mulai' => '06:30:00',
            'batas_check_in_selesai' => '07:30:00',
            'batas_check_out_mulai' => '12:00:00',
            'batas_check_out_selesai' => '13:00:00',
            'status' => $status,
        ]);
    }
}
