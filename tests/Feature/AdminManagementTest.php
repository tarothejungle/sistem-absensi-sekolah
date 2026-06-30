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

        $html = (new TeacherAccountsExport())->view()->render();

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

    private function createSession(string $name): AttendanceSession
    {
        return AttendanceSession::create([
            'nama_sesi' => $name . ' ' . uniqid(),
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
            'nama_lengkap' => $name . ' ' . uniqid(),
            'email' => uniqid('teacher_', true) . '@example.test',
        ]);

        return [$teacher, $user];
    }

    private function createUser(string $role, string $name = 'User Admin Test'): User
    {
        return User::create([
            'nip' => uniqid('nip_', true),
            'name' => $name . ' ' . uniqid(),
            'email' => uniqid('admin_', true) . '@example.test',
            'password' => Hash::make('password'),
            'role' => $role,
            'status' => 'aktif',
        ]);
    }
}
