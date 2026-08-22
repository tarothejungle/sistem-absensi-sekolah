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

        $response = $this
            ->actingAs($owner)
            ->get(route('attendance.photo.show', [$attendance, 'check-in']))
            ->assertOk()
            ->assertHeader('Cache-Control');

        $cacheControl = (string) $response->headers->get('Cache-Control');
        foreach (['private', 'no-store', 'max-age=0', 'must-revalidate'] as $directive) {
            $this->assertStringContainsString($directive, $cacheControl);
        }
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
            ->post(route('notifications.read', $notification))
            ->assertRedirect(route('dashboard'));
    }

    public function test_notification_redirect_rejects_protocol_relative_url(): void
    {
        $user = $this->createUser('guru');
        $notification = AppNotification::create([
            'user_id' => $user->id,
            'title' => 'Tes',
            'message' => 'Tes redirect',
            'type' => 'info',
            'url' => '//evil.example/phishing',
        ]);

        $this->actingAs($user)
            ->post(route('notifications.read', $notification))
            ->assertRedirect(route('dashboard'));
    }

    public function test_legacy_public_teacher_api_is_not_available(): void
    {
        $this->getJson('/api/guru')->assertNotFound();
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
        $service = new FaceVerificationService;

        $this->assertFalse($service->isValidImage('data:image/jpeg;base64,'.base64_encode('not an image')));
    }

    public function test_excel_cells_escape_formula_like_values(): void
    {
        $this->assertSame("'=cmd|calc!A1", ExcelCell::escape('=cmd|calc!A1'));
        $this->assertSame('Nama Aman', ExcelCell::escape('Nama Aman'));
    }

    public function test_app_layout_uses_csp_safe_event_listeners(): void
    {
        $layout = (string) file_get_contents(resource_path('views/layouts/app.blade.php'));

        $this->assertStringNotContainsString('onclick=', $layout);
        $this->assertStringContainsString(
            "notificationToggleButton.addEventListener('click', toggleNotificationDropdown)",
            $layout
        );
        $this->assertStringContainsString(
            "profileToggleButton.addEventListener('click', toggleProfileDropdown)",
            $layout
        );
        $this->assertStringContainsString(
            "button.addEventListener('click', toggleSidebar)",
            $layout
        );
    }

    private function createTeacherUser(string $role): array
    {
        $user = $this->createUser($role);

        $teacher = Teacher::create([
            'user_id' => $user->id,
            'nama_lengkap' => 'Guru Security Test '.uniqid(),
            'email' => uniqid('teacher_', true).'@example.test',
        ]);

        return [$teacher, $user];
    }

    private function createUser(string $role): User
    {
        $user = User::create([
            'username' => uniqid('user_', true),
            'name' => 'User Security Test '.uniqid(),
            'email' => uniqid('security_', true).'@example.test',
            'password' => Hash::make('password'),
        ]);

        // role & status tidak fillable — set eksplisit (defense-in-depth).
        $user->forceFill(['role' => $role, 'status' => 'aktif'])->save();

        return $user;
    }
}
