<?php

namespace Tests\Feature;

use App\Models\Teacher;
use App\Models\TeacherFaceEnrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Test khusus fitur pendaftaran wajah dari sisi admin.
 *
 * Berkas ini SENGAJA hanya ada di project sistem-absensi-sekolah karena
 * pencocokan wajah membutuhkan layanan FastAPI. Project
 * sistem-absensi-sekolah-hayyan memakai face capture saja sehingga tidak
 * memiliki tabel teacher_face_enrollments maupun route reset wajah.
 *
 * Sisa test manajemen admin berada di AdminManagementTest dan wajib
 * identik di kedua project.
 */
class AdminFaceEnrollmentTest extends TestCase
{
    use DatabaseTransactions;

    public function test_super_admin_can_reset_teacher_face_enrollment(): void
    {
        $admin = $this->createUser('super_admin', 'Admin Enrollment Wajah Test');
        [$teacher] = $this->createTeacherUser('guru', 'Guru Enrollment Wajah Test');

        TeacherFaceEnrollment::create([
            'teacher_id' => $teacher->id,
            'face_descriptor' => array_fill(0, TeacherFaceEnrollment::EMBEDDING_DIMENSIONS, 0.02),
            'model_version' => TeacherFaceEnrollment::MODEL_VERSION,
            'recapture_count' => 3,
            'enrolled_by' => $admin->id,
            'enrolled_at' => now(),
        ]);

        $this->actingAs($admin)
            ->delete(route('admin.teachers.face-enrollment.delete', $teacher))
            ->assertRedirect(route('admin.teachers.edit', $teacher));

        $this->assertDatabaseMissing('teacher_face_enrollments', [
            'teacher_id' => $teacher->id,
        ]);
    }

    public function test_super_admin_edit_page_shows_locked_status_and_reset_action(): void
    {
        $admin = $this->createUser('super_admin', 'Admin Status Enrollment');
        [$teacher, $teacherUser] = $this->createTeacherUser('tenaga_kependidikan', 'Tendik Status Enrollment');

        TeacherFaceEnrollment::create([
            'teacher_id' => $teacher->id,
            'face_descriptor' => array_fill(0, TeacherFaceEnrollment::EMBEDDING_DIMENSIONS, 0.02),
            'model_version' => TeacherFaceEnrollment::MODEL_VERSION,
            'recapture_count' => 0,
            'enrolled_by' => $teacherUser->id,
            'enrolled_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get(route('admin.teachers.edit', $teacher))
            ->assertOk()
            ->assertSee('Data Wajah Absensi')
            ->assertSee('sudah terdaftar dan dikunci')
            ->assertSee('Reset Data Wajah');
    }

    private function createTeacherUser(string $role, string $name): array
    {
        $user = $this->createUser($role, $name);

        $teacher = Teacher::create([
            'user_id' => $user->id,
            'nama_lengkap' => $name,
            'email' => uniqid('teacher_', true).'@example.test',
        ]);

        return [$teacher, $user];
    }

    private function createUser(string $role, string $name): User
    {
        $user = User::create([
            'username' => uniqid('user_', true),
            'name' => $name,
            'email' => uniqid('user_', true).'@example.test',
            'password' => Hash::make('Strong!Password123'),
        ]);

        $user->forceFill(['role' => $role, 'status' => 'aktif'])->save();

        return $user;
    }
}
