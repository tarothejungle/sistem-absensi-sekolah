<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Teacher;
use App\Models\User;
use App\Services\TeacherLifecycleService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class TeacherLifecycleTest extends TestCase
{
    use DatabaseTransactions;

    public function test_teacher_with_operational_history_is_archived(): void
    {
        [$teacher, $user] = $this->createTeacher('Guru Dengan Riwayat');
        Attendance::create([
            'teacher_id' => $teacher->id,
            'tanggal' => now()->toDateString(),
            'status_kehadiran' => 'hadir',
            'verification_method' => 'face',
        ]);

        $result = app(TeacherLifecycleService::class)->archiveOrDeleteTeacher($teacher);

        $this->assertSame('archived', $result['action']);
        $this->assertDatabaseHas('users', ['id' => $user->id, 'status' => 'nonaktif']);
        $this->assertDatabaseHas('teachers', ['id' => $teacher->id]);
        $this->assertDatabaseHas('attendances', ['teacher_id' => $teacher->id]);
    }

    public function test_teacher_without_operational_history_is_deleted(): void
    {
        [$teacher, $user] = $this->createTeacher('Guru Tanpa Riwayat');
        $teacher->salary()->create([
            'gaji_pokok' => 1000000,
            'potongan_per_absen' => 25000,
        ]);

        $result = app(TeacherLifecycleService::class)->archiveOrDeleteTeacher($teacher);

        $this->assertSame('deleted', $result['action']);
        $this->assertDatabaseMissing('teachers', ['id' => $teacher->id]);
        $this->assertDatabaseMissing('teacher_salaries', ['teacher_id' => $teacher->id]);
        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }

    private function createTeacher(string $name): array
    {
        $user = User::create([
            'username' => 'LC'.strtoupper(substr(uniqid(), -10)),
            'name' => $name,
            'email' => uniqid('lifecycle_', true).'@example.test',
            'password' => Hash::make('Password123!'),
        ]);
        $user->forceFill(['role' => 'guru', 'status' => 'aktif'])->save();

        $teacher = Teacher::create([
            'user_id' => $user->id,
            'nama_lengkap' => $name,
            'email' => $user->email,
        ]);

        return [$teacher, $user];
    }
}
