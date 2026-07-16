<?php

namespace Tests\Feature;

use App\Exports\TeacherAccountsExport;
use App\Models\AttendanceSession;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminManagementTest extends TestCase
{
    use DatabaseTransactions;

    public function test_super_admin_can_create_attendance_session_with_validated_fields(): void
    {
        $admin = $this->createUser('super_admin', 'Admin Sesi Test');

        $response = $this
            ->actingAs($admin)
            ->post(route('admin.attendance-sessions.store'), [
                'nama_sesi' => 'Sesi Validasi Test',
                'jam_masuk' => '07:00',
                'jam_pulang' => '15:00',
                'toleransi_terlambat' => 15,
                'batas_check_in_mulai' => '06:30',
                'batas_check_in_selesai' => '08:00',
                'batas_check_out_mulai' => '12:00',
                'batas_check_out_selesai' => '16:00',
                'status' => 'aktif',
                'unexpected_field' => 'tidak boleh tersimpan',
            ]);

        $response->assertRedirect(route('admin.attendance-sessions.index'));

        $this->assertDatabaseHas('attendance_sessions', [
            'nama_sesi' => 'Sesi Validasi Test',
            'jam_masuk' => '07:00:00',
            'jam_pulang' => '15:00:00',
            'status' => 'aktif',
        ]);
    }

    public function test_attendance_session_rejects_invalid_time_format(): void
    {
        $admin = $this->createUser('super_admin', 'Admin Sesi Invalid Test');

        $response = $this
            ->actingAs($admin)
            ->from(route('admin.attendance-sessions.create'))
            ->post(route('admin.attendance-sessions.store'), [
                'nama_sesi' => 'Sesi Invalid Test',
                'jam_masuk' => '07.00',
                'jam_pulang' => '15:00',
                'toleransi_terlambat' => 15,
                'batas_check_in_mulai' => '06:30',
                'batas_check_in_selesai' => '08:00',
                'batas_check_out_mulai' => '12:00',
                'batas_check_out_selesai' => '16:00',
                'status' => 'aktif',
            ]);

        $response->assertRedirect(route('admin.attendance-sessions.create'));
        $response->assertSessionHasErrors('jam_masuk');
    }

    public function test_attendance_session_used_by_teacher_cannot_be_deleted(): void
    {
        $admin = $this->createUser('super_admin', 'Admin Hapus Sesi Test');
        $session = $this->createSession('Sesi Dipakai Guru Test');
        [$teacher] = $this->createTeacherUser('guru', 'Guru Pemakai Sesi Test');

        $teacher->attendanceSessions()->attach($session->id);

        $response = $this
            ->actingAs($admin)
            ->delete(route('admin.attendance-sessions.destroy', $session));

        $response->assertSessionHas('error');
        $this->assertDatabaseHas('attendance_sessions', [
            'id' => $session->id,
        ]);
    }

    public function test_teacher_account_exports_do_not_render_default_password(): void
    {
        $session = $this->createSession('Sesi Export Akun Test');
        [$teacher] = $this->createTeacherUser('guru', 'Guru Export Akun Test');
        $teacher->attendanceSessions()->attach($session->id);

        $html = (new TeacherAccountsExport)->view()->render();

        $this->assertStringNotContainsString('Lantaburo1', $html);
        $this->assertStringContainsString('Password tidak ditampilkan', $html);

        $pdfHtml = view('admin.exports.teacher_accounts_pdf', [
            'teachers' => Teacher::with(['user', 'attendanceSessions'])
                ->where('id', $teacher->id)
                ->get(),
        ])->render();

        $this->assertStringNotContainsString('Lantaburo1', $pdfHtml);
        $this->assertStringContainsString('Tidak ditampilkan', $pdfHtml);
    }

    public function test_data_guru_shows_all_non_admin_roles(): void
    {
        $admin = $this->createUser('super_admin', 'Admin Data Guru Role Test');
        $this->createTeacherUser('guru', 'Guru Muncul Data Guru Test');
        $this->createTeacherUser('kepala_sekolah', 'Kepala Sekolah Muncul Data Guru Test');
        $this->createTeacherUser('bendahara', 'Bendahara Muncul Data Guru Test');

        $response = $this
            ->actingAs($admin)
            ->get(route('admin.teachers', ['keyword' => 'Data Guru Test']));

        $response->assertOk();
        $response->assertSee('Guru Muncul Data Guru Test');
        $response->assertSee('Kepala Sekolah Muncul Data Guru Test');
        $response->assertSee('Guru');
        $response->assertSee('Kepala Sekolah');
        $response->assertSee('Bendahara Muncul Data Guru Test');
        $response->assertSee('Bendahara');
    }

    public function test_data_admin_only_shows_super_admin_accounts(): void
    {
        $admin = $this->createUser('super_admin', 'Super Admin Muncul Test');
        $this->createUser('kepala_sekolah', 'Kepala Sekolah Tidak Muncul Test');
        $this->createUser('bendahara', 'Bendahara Tidak Muncul Test');

        $response = $this->actingAs($admin)->get(route('admin.users'));

        $response->assertOk();
        $response->assertSee('Super Admin Muncul Test');
        $response->assertDontSee('Kepala Sekolah Tidak Muncul Test');
        $response->assertDontSee('Bendahara Tidak Muncul Test');
    }

    public function test_super_admin_can_create_bendahara_from_data_guru(): void
    {
        $admin = $this->createUser('super_admin', 'Admin Pembuat Bendahara Test');
        $session = $this->createSession('Sesi Bendahara Test');

        $response = $this->actingAs($admin)->post(route('admin.teachers.store'), [
            'nip' => 'bendahara_'.uniqid(),
            'password' => 'Aman123!',
            'nama_lengkap' => 'Bendahara Baru Test',
            'role' => 'bendahara',
            'jenis_kelamin' => 'P',
            'no_hp' => '081234567890',
            'email' => uniqid('bendahara_', true).'@example.test',
            'jabatan' => 'Guru Bidang',
            'mata_pelajaran' => 'Administrasi',
            'attendance_session_ids' => [$session->id],
            'attendance_days' => ['senin', 'selasa'],
        ]);

        $response->assertRedirect();
        $user = User::where('name', 'Bendahara Baru Test')->firstOrFail();
        $this->assertSame('bendahara', $user->role);
        $this->assertSame('aktif', $user->status);
        $this->assertNotNull($user->teacher);
        $this->assertTrue($user->teacher->attendanceSessions->contains($session));
    }

    public function test_admin_and_teacher_creation_reject_weak_passwords(): void
    {
        $admin = $this->createUser('super_admin', 'Admin Password Test');
        $session = $this->createSession('Sesi Password Test');

        $this->actingAs($admin)
            ->post(route('admin.users.store'), [
                'nip' => 'admin_weak_'.uniqid(),
                'name' => 'Admin Password Lemah',
                'email' => uniqid('admin_weak_', true).'@example.test',
                'password' => 'password',
            ])
            ->assertSessionHasErrors('password');

        $this->actingAs($admin)
            ->post(route('admin.teachers.store'), [
                'nip' => 'teacher_weak_'.uniqid(),
                'password' => 'password',
                'nama_lengkap' => 'Guru Password Lemah',
                'role' => 'guru',
                'jenis_kelamin' => 'L',
                'email' => uniqid('teacher_weak_', true).'@example.test',
                'jabatan' => 'Guru Kelas',
                'attendance_session_ids' => [$session->id],
                'attendance_days' => ['senin'],
            ])
            ->assertSessionHasErrors('password');
    }

    private function createSession(string $name): AttendanceSession
    {
        return AttendanceSession::create([
            'nama_sesi' => $name.' '.uniqid(),
            'jam_masuk' => '07:00:00',
            'jam_pulang' => '15:00:00',
            'toleransi_terlambat' => 15,
            'batas_check_in_mulai' => '06:30:00',
            'batas_check_in_selesai' => '08:00:00',
            'batas_check_out_mulai' => '12:00:00',
            'batas_check_out_selesai' => '16:00:00',
            'status' => 'aktif',
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

    private function createUser(string $role, string $name = 'User Admin Test'): User
    {
        $user = User::create([
            'nip' => uniqid('nip_', true),
            'name' => $name.' '.uniqid(),
            'email' => uniqid('admin_', true).'@example.test',
            'password' => Hash::make('password'),
        ]);

        // role & status tidak fillable — set eksplisit (defense-in-depth).
        $user->forceFill(['role' => $role, 'status' => 'aktif'])->save();

        return $user;
    }
}
