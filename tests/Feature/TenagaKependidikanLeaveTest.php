<?php

namespace Tests\Feature;

use App\Models\LeaveRequest;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class TenagaKependidikanLeaveTest extends TestCase
{
    use DatabaseTransactions;

    // ──────────────────────────────────────────────
    // Pengajuan
    // ──────────────────────────────────────────────

    public function test_tenaga_kependidikan_can_create_leave(): void
    {
        [$teacher, $user] = $this->createTeacherUser('tenaga_kependidikan', 'Tendik Pengaju');

        $response = $this
            ->actingAs($user)
            ->post(route('leave.store'), [
                'jenis_pengajuan' => 'cuti',
                'tanggal_mulai' => '2099-12-01',
                'tanggal_selesai' => '2099-12-05',
                'alasan' => 'Keperluan keluarga.',
                'infal_teacher_ids' => [],
            ]);

        $response->assertRedirect(route('leave.index'));
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('leave_requests', [
            'teacher_id' => $teacher->id,
            'jenis_pengajuan' => 'cuti',
            'status_pengajuan' => 'pending',
        ]);
    }

    public function test_tenaga_kependidikan_can_edit_pending_leave(): void
    {
        [$teacher, $user] = $this->createTeacherUser('tenaga_kependidikan', 'Tendik Edit');

        $leave = LeaveRequest::create([
            'teacher_id' => $teacher->id,
            'jenis_pengajuan' => 'izin',
            'tanggal_mulai' => '2099-12-01',
            'tanggal_selesai' => '2099-12-01',
            'alasan' => 'Alasan lama.',
            'status_pengajuan' => 'pending',
        ]);

        $response = $this
            ->actingAs($user)
            ->put(route('leave.update', $leave), [
                'jenis_pengajuan' => 'cuti',
                'tanggal_mulai' => '2099-12-10',
                'tanggal_selesai' => '2099-12-10',
                'alasan' => 'Alasan baru.',
                'infal_teacher_ids' => [],
            ]);

        $response->assertRedirect(route('leave.index'));
        $response->assertSessionHas('success');

        $leave->refresh();
        $this->assertSame('cuti', $leave->jenis_pengajuan);
        $this->assertSame('Alasan baru.', $leave->alasan);
    }

    public function test_tenaga_kependidikan_can_delete_pending_leave(): void
    {
        [$teacher, $user] = $this->createTeacherUser('tenaga_kependidikan', 'Tendik Hapus');

        $leave = LeaveRequest::create([
            'teacher_id' => $teacher->id,
            'jenis_pengajuan' => 'izin',
            'tanggal_mulai' => '2099-12-01',
            'tanggal_selesai' => '2099-12-01',
            'alasan' => 'Akan dihapus.',
            'status_pengajuan' => 'pending',
        ]);

        $response = $this
            ->actingAs($user)
            ->delete(route('leave.destroy', $leave));

        $response->assertRedirect(route('leave.index'));
        $response->assertSessionHas('success');
        $this->assertDatabaseMissing('leave_requests', ['id' => $leave->id]);
    }

    // ──────────────────────────────────────────────
    // Sebagai infal / pengganti
    // ──────────────────────────────────────────────

    public function test_tenaga_kependidikan_can_approve_infal_after_parent_approved(): void
    {
        [$requester] = $this->createTeacherUser('guru', 'Guru Pengaju');
        [$infal, $infalUser] = $this->createTeacherUser('tenaga_kependidikan', 'Tendik Infal');

        $leave = LeaveRequest::create([
            'teacher_id' => $requester->id,
            'jenis_pengajuan' => 'izin',
            'tanggal_mulai' => '2099-12-01',
            'tanggal_selesai' => '2099-12-01',
            'alasan' => 'Keperluan.',
            'status_pengajuan' => 'disetujui',
        ]);
        $leave->infalTeachers()->sync([
            $infal->id => ['status_infal' => 'pending'],
        ]);

        $response = $this
            ->actingAs($infalUser)
            ->patch(route('leave.infal.approve', $leave));

        $response->assertSessionHas('success');

        $pivot = $leave->infalTeachers()->where('teachers.id', $infal->id)->firstOrFail()->pivot;
        $this->assertSame('disetujui', $pivot->status_infal);
    }

    public function test_tenaga_kependidikan_can_reject_infal_with_note(): void
    {
        [$requester] = $this->createTeacherUser('guru', 'Guru Pengaju Tolak');
        [$infal, $infalUser] = $this->createTeacherUser('tenaga_kependidikan', 'Tendik Tolak');

        $leave = LeaveRequest::create([
            'teacher_id' => $requester->id,
            'jenis_pengajuan' => 'cuti',
            'tanggal_mulai' => '2099-12-01',
            'tanggal_selesai' => '2099-12-05',
            'alasan' => 'Cuti panjang.',
            'status_pengajuan' => 'disetujui',
        ]);
        $leave->infalTeachers()->sync([
            $infal->id => ['status_infal' => 'pending'],
        ]);

        $response = $this
            ->actingAs($infalUser)
            ->patch(route('leave.infal.reject', $leave), [
                'catatan_infal' => 'Saya tidak bisa menggantikan.',
            ]);

        $response->assertSessionHas('success');

        $pivot = $leave->infalTeachers()->where('teachers.id', $infal->id)->firstOrFail()->pivot;
        $this->assertSame('ditolak', $pivot->status_infal);
        $this->assertSame('Saya tidak bisa menggantikan.', $pivot->catatan_infal);
    }

    public function test_infal_cannot_approve_before_parent_approved(): void
    {
        [$requester] = $this->createTeacherUser('guru', 'Guru Pending');
        [$infal, $infalUser] = $this->createTeacherUser('tenaga_kependidikan', 'Tendik Pending');

        $leave = LeaveRequest::create([
            'teacher_id' => $requester->id,
            'jenis_pengajuan' => 'izin',
            'tanggal_mulai' => '2099-12-01',
            'tanggal_selesai' => '2099-12-01',
            'alasan' => 'Belum disetujui.',
            'status_pengajuan' => 'pending',
        ]);
        $leave->infalTeachers()->sync([
            $infal->id => ['status_infal' => 'pending'],
        ]);

        $response = $this
            ->actingAs($infalUser)
            ->patch(route('leave.infal.approve', $leave));

        $response->assertSessionHas('error');

        $pivot = $leave->infalTeachers()->where('teachers.id', $infal->id)->firstOrFail()->pivot;
        $this->assertSame('pending', $pivot->status_infal);
    }

    public function test_infal_cannot_reject_before_parent_approved(): void
    {
        [$requester] = $this->createTeacherUser('guru', 'Guru Pending Tolak');
        [$infal, $infalUser] = $this->createTeacherUser('tenaga_kependidikan', 'Tendik Pending Tolak');

        $leave = LeaveRequest::create([
            'teacher_id' => $requester->id,
            'jenis_pengajuan' => 'izin',
            'tanggal_mulai' => '2099-12-01',
            'tanggal_selesai' => '2099-12-01',
            'alasan' => 'Belum disetujui.',
            'status_pengajuan' => 'pending',
        ]);
        $leave->infalTeachers()->sync([
            $infal->id => ['status_infal' => 'pending'],
        ]);

        $response = $this
            ->actingAs($infalUser)
            ->patch(route('leave.infal.reject', $leave), [
                'catatan_infal' => 'Menolak sebelum approval.',
            ]);

        $response->assertSessionHas('error');

        $pivot = $leave->infalTeachers()->where('teachers.id', $infal->id)->firstOrFail()->pivot;
        $this->assertSame('pending', $pivot->status_infal);
    }

    public function test_non_pivot_teacher_cannot_approve_infal(): void
    {
        [$requester] = $this->createTeacherUser('guru', 'Guru Non Pivot');
        [, $outsiderUser] = $this->createTeacherUser('tenaga_kependidikan', 'Tendik Luar');

        $leave = LeaveRequest::create([
            'teacher_id' => $requester->id,
            'jenis_pengajuan' => 'izin',
            'tanggal_mulai' => '2099-12-01',
            'tanggal_selesai' => '2099-12-01',
            'alasan' => 'Tanpa infal.',
            'status_pengajuan' => 'disetujui',
        ]);

        $response = $this
            ->actingAs($outsiderUser)
            ->patch(route('leave.infal.approve', $leave));

        $response->assertForbidden();
    }

    public function test_infal_cannot_reprocess_already_approved_pivot(): void
    {
        [$requester] = $this->createTeacherUser('guru', 'Guru Sudah Proses');
        [$infal, $infalUser] = $this->createTeacherUser('tenaga_kependidikan', 'Tendik Sudah Setuju');

        $leave = LeaveRequest::create([
            'teacher_id' => $requester->id,
            'jenis_pengajuan' => 'izin',
            'tanggal_mulai' => '2099-12-01',
            'tanggal_selesai' => '2099-12-01',
            'alasan' => 'Sudah diproses.',
            'status_pengajuan' => 'disetujui',
        ]);
        $leave->infalTeachers()->sync([
            $infal->id => ['status_infal' => 'disetujui'],
        ]);

        $response = $this
            ->actingAs($infalUser)
            ->patch(route('leave.infal.approve', $leave));

        $response->assertSessionHas('error');
    }

    // ──────────────────────────────────────────────
    // Index visibility
    // ──────────────────────────────────────────────

    public function test_tenaga_kependidikan_sees_own_and_infal_requests_on_index(): void
    {
        [$ownTeacher, $ownUser] = $this->createTeacherUser('tenaga_kependidikan', 'Tendik Index Own');
        [$otherTeacher] = $this->createTeacherUser('guru', 'Guru Index Other');

        LeaveRequest::create([
            'teacher_id' => $ownTeacher->id,
            'jenis_pengajuan' => 'izin',
            'tanggal_mulai' => '2099-12-01',
            'tanggal_selesai' => '2099-12-01',
            'alasan' => 'Milik sendiri.',
            'status_pengajuan' => 'pending',
        ]);

        $infalLeave = LeaveRequest::create([
            'teacher_id' => $otherTeacher->id,
            'jenis_pengajuan' => 'cuti',
            'tanggal_mulai' => '2099-12-10',
            'tanggal_selesai' => '2099-12-10',
            'alasan' => 'Sebagai infal.',
            'status_pengajuan' => 'disetujui',
        ]);
        $infalLeave->infalTeachers()->sync([
            $ownTeacher->id => ['status_infal' => 'pending'],
        ]);

        $response = $this
            ->actingAs($ownUser)
            ->get(route('leave.index'));

        $response->assertOk();
        $response->assertSee('Milik sendiri.');
        $response->assertSee('Sebagai infal.');
    }

    // ──────────────────────────────────────────────
    // Payroll integration
    // ──────────────────────────────────────────────

    public function test_tenaga_kependidikan_infal_receives_payroll_tambahan(): void
    {
        [$requester] = $this->createTeacherUser('guru', 'Guru Payroll Tendik');
        [$infal] = $this->createTeacherUser('tenaga_kependidikan', 'Tendik Payroll Infal');
        [, $bendahara] = $this->createTeacherUser('bendahara', 'Bendahara Payroll Test');

        $leave = LeaveRequest::create([
            'teacher_id' => $requester->id,
            'jenis_pengajuan' => 'izin',
            'tanggal_mulai' => '2099-12-10',
            'tanggal_selesai' => '2099-12-10',
            'alasan' => 'Payroll tendik.',
            'status_pengajuan' => 'disetujui',
        ]);
        $leave->infalTeachers()->sync([
            $infal->id => ['status_infal' => 'disetujui'],
        ]);

        $this->actingAs($bendahara)
            ->post(route('payroll.generate'), [
                'tahun' => 2099,
                'bulan' => 12,
            ]);

        $this->assertDatabaseHas('payroll_items', [
            'teacher_id' => $infal->id,
        ]);
    }

    // ──────────────────────────────────────────────
    // Non-active / orphan teacher excluded from candidates
    // ──────────────────────────────────────────────

    public function test_inactive_teacher_not_shown_as_candidate(): void
    {
        [, $user] = $this->createTeacherUser('guru', 'Guru Candidate Check');
        $inactiveUser = User::create([
            'username' => uniqid('user_', true),
            'name' => 'Inactive Teacher',
            'email' => uniqid('inactive_', true).'@example.test',
            'password' => Hash::make('password'),
        ]);
        $inactiveUser->forceFill(['role' => 'guru', 'status' => 'nonaktif'])->save();
        Teacher::create([
            'user_id' => $inactiveUser->id,
            'nama_lengkap' => 'Inactive Teacher '.uniqid(),
            'email' => uniqid('inactive_t_', true).'@example.test',
        ]);

        $response = $this
            ->actingAs($user)
            ->get(route('leave.create'));

        $response->assertOk();
        $response->assertDontSee($inactiveUser->name);
    }

    // ──────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────

    private function createTeacherUser(string $role, string $name): array
    {
        $user = User::create([
            'username' => uniqid('user_', true),
            'name' => $name.' '.uniqid(),
            'email' => uniqid('user_', true).'@example.test',
            'password' => Hash::make('password'),
        ]);
        $user->forceFill(['role' => $role, 'status' => 'aktif'])->save();

        $teacher = Teacher::create([
            'user_id' => $user->id,
            'nama_lengkap' => $name.' '.uniqid(),
            'email' => uniqid('teacher_', true).'@example.test',
        ]);

        return [$teacher, $user];
    }
}
