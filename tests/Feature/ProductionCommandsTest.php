<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\LoginActivity;
use App\Models\Teacher;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProductionCommandsTest extends TestCase
{
    use DatabaseTransactions;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_interactive_command_creates_super_admin_with_teacher_profile(): void
    {
        $this->artisan('admin:create-super')
            ->expectsQuestion('NIP super admin', 'ADMIN-PRODUCTION-TEST')
            ->expectsQuestion('Nama lengkap', 'Admin Produksi Test')
            ->expectsQuestion('Email (opsional)', 'admin-production@example.com')
            ->expectsQuestion('Password', 'Strong!Password123')
            ->expectsQuestion('Ulangi password', 'Strong!Password123')
            ->expectsOutput('Super admin berhasil dibuat.')
            ->assertSuccessful();

        $user = User::where('nip', 'ADMIN-PRODUCTION-TEST')->firstOrFail();
        $this->assertSame('super_admin', $user->role);
        $this->assertSame('aktif', $user->status);
        $this->assertTrue(Hash::check('Strong!Password123', $user->password));
        $this->assertDatabaseHas('teachers', [
            'user_id' => $user->id,
            'nama_lengkap' => 'Admin Produksi Test',
        ]);
    }

    public function test_photo_pruning_only_removes_expired_safe_paths(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-22 03:00:00', 'Asia/Jakarta'));
        Storage::fake('local');

        $teacher = $this->createTeacher('RETENTION-TEACHER');
        Storage::disk('local')->put('attendance_faces/expired-in.jpg', 'photo');
        Storage::disk('local')->put('attendance_faces/expired-out.jpg', 'photo');
        Storage::disk('local')->put('attendance_faces/current.jpg', 'photo');
        Storage::disk('local')->put('leave_attachments/keep.pdf', 'private');

        $expired = Attendance::create([
            'teacher_id' => $teacher->id,
            'tanggal' => '2026-06-21',
            'check_in_face_photo' => 'attendance_faces/expired-in.jpg',
            'check_out_face_photo' => 'attendance_faces/expired-out.jpg',
        ]);
        $current = Attendance::create([
            'teacher_id' => $teacher->id,
            'tanggal' => '2026-06-22',
            'check_in_face_photo' => 'attendance_faces/current.jpg',
        ]);
        $unsafe = Attendance::create([
            'teacher_id' => $teacher->id,
            'tanggal' => '2026-06-20',
            'check_in_face_photo' => 'leave_attachments/keep.pdf',
        ]);

        $this->artisan('attendance:prune-face-photos', ['--days' => 30])
            ->assertSuccessful();

        Storage::disk('local')->assertMissing('attendance_faces/expired-in.jpg');
        Storage::disk('local')->assertMissing('attendance_faces/expired-out.jpg');
        Storage::disk('local')->assertExists('attendance_faces/current.jpg');
        Storage::disk('local')->assertExists('leave_attachments/keep.pdf');
        $this->assertNull($expired->fresh()->check_in_face_photo);
        $this->assertNull($expired->fresh()->check_out_face_photo);
        $this->assertSame('attendance_faces/current.jpg', $current->fresh()->check_in_face_photo);
        $this->assertSame('leave_attachments/keep.pdf', $unsafe->fresh()->check_in_face_photo);
    }

    public function test_login_activity_pruning_removes_only_records_at_least_24_hours_old(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-24 12:00:00', 'Asia/Jakarta'));

        $expired = LoginActivity::create(['activity' => 'expired']);
        $expired->timestamps = false;
        $expired->created_at = now()->subHours(24);
        $expired->save();

        $current = LoginActivity::create(['activity' => 'current']);
        $current->timestamps = false;
        $current->created_at = now()->subHours(23)->subMinutes(59);
        $current->save();

        $this->artisan('login-activities:prune')->assertSuccessful();

        $this->assertDatabaseMissing('login_activities', ['id' => $expired->id]);
        $this->assertDatabaseHas('login_activities', ['id' => $current->id]);
    }

    private function createTeacher(string $nip): Teacher
    {
        $user = new User([
            'nip' => $nip,
            'name' => 'Retention Teacher',
            'password' => 'Strong!Password123',
        ]);
        $user->role = 'guru';
        $user->status = 'aktif';
        $user->save();

        return Teacher::create([
            'user_id' => $user->id,
            'nama_lengkap' => 'Retention Teacher',
        ]);
    }
}
