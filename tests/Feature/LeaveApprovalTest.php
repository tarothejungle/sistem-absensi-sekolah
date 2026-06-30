<?php

namespace Tests\Feature;

use App\Models\LeaveRequest;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class LeaveApprovalTest extends TestCase
{
    use DatabaseTransactions;

    public function test_approver_can_approve_leave_with_note(): void
    {
        $approver = $this->createUser('super_admin');
        [$teacher] = $this->createTeacherUser('guru');

        $leave = $this->createPendingLeave($teacher);

        $response = $this
            ->actingAs($approver)
            ->post(route('leave.approve', $leave), [
                'catatan_approval' => 'Disetujui karena dokumen lengkap.',
            ]);

        $response->assertSessionHas('success');

        $leave->refresh();

        $this->assertSame('disetujui', $leave->status_pengajuan);
        $this->assertSame($approver->id, $leave->approved_by);
        $this->assertSame('Disetujui karena dokumen lengkap.', $leave->catatan_approval);
        $this->assertNotNull($leave->approved_at);
    }

    public function test_approver_can_reject_leave_with_note(): void
    {
        $approver = $this->createUser('kepala_sekolah');
        [$teacher] = $this->createTeacherUser('guru');

        $leave = $this->createPendingLeave($teacher);

        $response = $this
            ->actingAs($approver)
            ->post(route('leave.reject', $leave), [
                'catatan_approval' => 'Tanggal bentrok dengan agenda sekolah.',
            ]);

        $response->assertSessionHas('success');

        $leave->refresh();

        $this->assertSame('ditolak', $leave->status_pengajuan);
        $this->assertSame('ditolak', $leave->status_infal);
        $this->assertSame($approver->id, $leave->approved_by);
        $this->assertSame('Tanggal bentrok dengan agenda sekolah.', $leave->catatan_approval);
        $this->assertNotNull($leave->approved_at);
    }

    public function test_regular_teacher_cannot_approve_leave(): void
    {
        [$requestTeacher] = $this->createTeacherUser('guru');
        [$otherTeacher, $otherUser] = $this->createTeacherUser('guru');

        $leave = $this->createPendingLeave($requestTeacher);

        $response = $this
            ->actingAs($otherUser)
            ->post(route('leave.approve', $leave), [
                'catatan_approval' => 'Tidak boleh diproses guru biasa.',
            ]);

        $response->assertForbidden();

        $leave->refresh();

        $this->assertSame('pending', $leave->status_pengajuan);
        $this->assertNull($leave->approved_by);
        $this->assertNull($leave->approved_at);

        $this->assertNotSame($requestTeacher->id, $otherTeacher->id);
    }

    public function test_infal_teacher_can_reject_with_note(): void
    {
        [$requestTeacher] = $this->createTeacherUser('guru');
        [$infalTeacher, $infalUser] = $this->createTeacherUser('guru');

        $leave = LeaveRequest::create([
            'teacher_id' => $requestTeacher->id,
            'infal_teacher_id' => $infalTeacher->id,
            'jenis_pengajuan' => 'izin',
            'tanggal_mulai' => now()->toDateString(),
            'tanggal_selesai' => now()->toDateString(),
            'alasan' => 'Keperluan keluarga.',
            'status_pengajuan' => 'disetujui',
            'status_infal' => 'pending',
        ]);

        $response = $this
            ->actingAs($infalUser)
            ->patch(route('leave.infal.reject', $leave), [
                'catatan_infal' => 'Saya sudah ada jadwal mengajar di kelas lain.',
            ]);

        $response->assertSessionHas('success');

        $leave->refresh();

        $this->assertSame('ditolak', $leave->status_infal);
        $this->assertSame('Saya sudah ada jadwal mengajar di kelas lain.', $leave->catatan_infal);
    }

    public function test_infal_teacher_approval_clears_previous_note(): void
    {
        [$requestTeacher] = $this->createTeacherUser('guru');
        [$infalTeacher, $infalUser] = $this->createTeacherUser('guru');

        $leave = LeaveRequest::create([
            'teacher_id' => $requestTeacher->id,
            'infal_teacher_id' => $infalTeacher->id,
            'jenis_pengajuan' => 'izin',
            'tanggal_mulai' => now()->toDateString(),
            'tanggal_selesai' => now()->toDateString(),
            'alasan' => 'Keperluan keluarga.',
            'status_pengajuan' => 'disetujui',
            'status_infal' => 'pending',
            'catatan_infal' => 'Catatan lama',
        ]);

        $response = $this
            ->actingAs($infalUser)
            ->patch(route('leave.infal.approve', $leave));

        $response->assertSessionHas('success');

        $leave->refresh();

        $this->assertSame('disetujui', $leave->status_infal);
        $this->assertNull($leave->catatan_infal);
    }

    private function createPendingLeave(Teacher $teacher): LeaveRequest
    {
        return LeaveRequest::create([
            'teacher_id' => $teacher->id,
            'jenis_pengajuan' => 'izin',
            'tanggal_mulai' => now()->toDateString(),
            'tanggal_selesai' => now()->toDateString(),
            'alasan' => 'Keperluan keluarga.',
            'status_pengajuan' => 'pending',
            'status_infal' => 'disetujui',
        ]);
    }

    private function createTeacherUser(string $role): array
    {
        $user = $this->createUser($role);

        $teacher = Teacher::create([
            'user_id' => $user->id,
            'nama_lengkap' => 'Guru Test ' . uniqid(),
            'email' => uniqid('guru_', true) . '@example.test',
        ]);

        return [$teacher, $user];
    }

    private function createUser(string $role): User
    {
        return User::create([
            'nip' => uniqid('nip_', true),
            'name' => 'User Test ' . uniqid(),
            'email' => uniqid('user_', true) . '@example.test',
            'password' => Hash::make('password'),
            'role' => $role,
            'status' => 'aktif',
        ]);
    }
}
