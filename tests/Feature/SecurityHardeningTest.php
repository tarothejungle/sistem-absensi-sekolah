<?php

namespace Tests\Feature;

use App\Models\AppNotification;
use App\Models\Attendance;
use App\Models\LeaveRequest;
use App\Models\Teacher;
use App\Models\User;
use App\Services\FaceVerificationService;
use App\Support\ExcelCell;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SecurityHardeningTest extends TestCase
{
    use DatabaseTransactions;

    public function test_processed_leave_cannot_be_updated_directly(): void
    {
        [$teacher, $user] = $this->createTeacherUser('guru');

        $leave = LeaveRequest::create([
            'teacher_id' => $teacher->id,
            'jenis_pengajuan' => 'izin',
            'tanggal_mulai' => '2026-07-01',
            'tanggal_selesai' => '2026-07-01',
            'alasan' => 'Alasan awal',
            'status_pengajuan' => 'disetujui',
            'status_infal' => 'disetujui',
        ]);

        $response = $this
            ->actingAs($user)
            ->put(route('leave.update', $leave), [
                'jenis_pengajuan' => 'cuti',
                'tanggal_mulai' => '2026-07-10',
                'tanggal_selesai' => '2026-07-10',
                'alasan' => 'Diubah setelah disetujui',
            ]);

        $response->assertRedirect(route('leave.index'));
        $response->assertSessionHas('error');

        $leave->refresh();

        $this->assertSame('izin', $leave->jenis_pengajuan);
        $this->assertSame('Alasan awal', $leave->alasan);
        $this->assertSame('disetujui', $leave->status_pengajuan);
    }

    public function test_attendance_photo_requires_owner_or_report_role(): void
    {
        Storage::fake('local');

        [$teacher, $owner] = $this->createTeacherUser('guru');
        [, $otherUser] = $this->createTeacherUser('guru');

        Storage::disk('local')->put('attendance_faces/test.jpg', 'private-photo');

        $attendance = Attendance::create([
            'teacher_id' => $teacher->id,
            'tanggal' => '2026-07-01',
            'check_in_face_photo' => 'attendance_faces/test.jpg',
            'verification_method' => 'face',
            'status_kehadiran' => 'hadir',
        ]);

        $this
            ->actingAs($otherUser)
            ->get(route('attendance.photo.show', [$attendance, 'check-in']))
            ->assertForbidden();

        $this
            ->actingAs($owner)
            ->get(route('attendance.photo.show', [$attendance, 'check-in']))
            ->assertOk();
    }

    public function test_notification_redirect_rejects_external_url(): void
    {
        $user = $this->createUser('guru');

        $notification = AppNotification::create([
            'user_id' => $user->id,
            'title' => 'Tes',
            'message' => 'Tes redirect',
            'type' => 'info',
            'url' => 'https://evil.example/phishing',
        ]);

        $this
            ->actingAs($user)
            ->get(route('notifications.read', $notification))
            ->assertRedirect(route('dashboard'));
    }

    public function test_forgot_password_does_not_disclose_registered_email(): void
    {
        $response = $this->post(route('password.email'), [
            'email' => 'tidak-ada@example.test',
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertSessionHas('success');
    }

    public function test_face_verification_rejects_non_image_payloads(): void
    {
        $service = new FaceVerificationService();

        $this->assertFalse($service->verify('data:image/jpeg;base64,' . base64_encode('not an image')));
    }

    public function test_excel_cells_escape_formula_like_values(): void
    {
        $this->assertSame("'=cmd|calc!A1", ExcelCell::escape('=cmd|calc!A1'));
        $this->assertSame('Nama Aman', ExcelCell::escape('Nama Aman'));
    }

    private function createTeacherUser(string $role): array
    {
        $user = $this->createUser($role);

        $teacher = Teacher::create([
            'user_id' => $user->id,
            'nama_lengkap' => 'Guru Security Test ' . uniqid(),
            'email' => uniqid('teacher_', true) . '@example.test',
        ]);

        return [$teacher, $user];
    }

    private function createUser(string $role): User
    {
        $user = User::create([
            'nip' => uniqid('nip_', true),
            'name' => 'User Security Test ' . uniqid(),
            'email' => uniqid('security_', true) . '@example.test',
            'password' => Hash::make('password'),
        ]);

        // role & status tidak fillable — set eksplisit (defense-in-depth).
        $user->forceFill(['role' => $role, 'status' => 'aktif'])->save();

        return $user;
    }
}
