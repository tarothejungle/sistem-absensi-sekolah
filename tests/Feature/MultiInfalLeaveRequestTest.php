<?php

namespace Tests\Feature;

use App\Models\AppNotification;
use App\Models\LeaveRequest;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class MultiInfalLeaveRequestTest extends TestCase
{
    use DatabaseTransactions;

    public function test_requester_can_store_multiple_distinct_active_infals_without_notifying_them_before_main_approval(): void
    {
        [$requester, $requesterUser] = $this->createTeacherUser('guru');
        [$firstInfal, $firstUser] = $this->createTeacherUser('guru');
        [$secondInfal, $secondUser] = $this->createTeacherUser('tenaga_kependidikan');

        $this->actingAs($requesterUser)->post(route('leave.store'), [
            'jenis_pengajuan' => 'izin',
            'tanggal_mulai' => '2099-08-01',
            'tanggal_selesai' => '2099-08-01',
            'alasan' => 'Keperluan keluarga.',
            'infal_teacher_ids' => [$firstInfal->id, $secondInfal->id],
        ])->assertRedirect(route('leave.index'));

        $leave = LeaveRequest::where('teacher_id', $requester->id)->firstOrFail();
        $this->assertEqualsCanonicalizing([$firstInfal->id, $secondInfal->id], $leave->infalTeachers()->pluck('teachers.id')->all());
        $this->assertSame(0, AppNotification::whereIn('user_id', [$firstUser->id, $secondUser->id])->count());
    }

    public function test_infal_validation_rejects_duplicates_self_and_inactive_user(): void
    {
        [$requester, $requesterUser] = $this->createTeacherUser('guru');
        [$inactive] = $this->createTeacherUser('guru', 'nonaktif');

        $payload = [
            'jenis_pengajuan' => 'izin',
            'tanggal_mulai' => '2099-08-01',
            'tanggal_selesai' => '2099-08-01',
            'alasan' => 'Keperluan keluarga.',
        ];

        $this->actingAs($requesterUser)->post(route('leave.store'), $payload + [
            'infal_teacher_ids' => [$requester->id],
        ])->assertSessionHasErrors('infal_teacher_ids.0');

        $this->actingAs($requesterUser)->post(route('leave.store'), $payload + [
            'infal_teacher_ids' => [$inactive->id],
        ])->assertSessionHasErrors('infal_teacher_ids.0');

        $this->actingAs($requesterUser)->post(route('leave.store'), $payload + [
            'infal_teacher_ids' => [$inactive->id, $inactive->id],
        ])->assertSessionHasErrors('infal_teacher_ids.1');
    }

    public function test_replacing_rejected_infals_preserves_approved_infals(): void
    {
        [$requester, $requesterUser] = $this->createTeacherUser('guru');
        [$approved] = $this->createTeacherUser('guru');
        [$rejected] = $this->createTeacherUser('guru');
        [$replacement] = $this->createTeacherUser('guru');
        $leave = $this->approvedLeave($requester);
        $leave->infalTeachers()->attach([
            $approved->id => ['status_infal' => 'disetujui', 'catatan_infal' => null],
            $rejected->id => ['status_infal' => 'ditolak', 'catatan_infal' => 'Bentrok jadwal'],
        ]);

        $this->actingAs($requesterUser)->put(route('leave.update', $leave), [
            'infal_teacher_ids' => [$replacement->id],
        ])->assertRedirect(route('leave.index'));

        $statuses = $leave->infalTeachers()->get()->mapWithKeys(fn (Teacher $teacher) => [$teacher->id => $teacher->pivot->status_infal])->all();
        $this->assertSame([$approved->id => 'disetujui', $replacement->id => 'pending'], $statuses);
    }

    public function test_infal_cannot_approve_before_main_approval_and_rejection_requires_note(): void
    {
        [$requester] = $this->createTeacherUser('guru');
        [$infal, $infalUser] = $this->createTeacherUser('guru');
        $leave = $this->approvedLeave($requester, 'pending');
        $leave->infalTeachers()->attach($infal->id, ['status_infal' => 'pending']);

        $this->actingAs($infalUser)->patch(route('leave.infal.approve', $leave))->assertSessionHas('error');
        $leave->update(['status_pengajuan' => 'disetujui']);
        $this->actingAs($infalUser)->patch(route('leave.infal.reject', $leave))->assertSessionHasErrors('catatan_infal');
    }

    private function approvedLeave(Teacher $teacher, string $status = 'disetujui'): LeaveRequest
    {
        return LeaveRequest::create([
            'teacher_id' => $teacher->id,
            'jenis_pengajuan' => 'izin',
            'tanggal_mulai' => '2099-08-01',
            'tanggal_selesai' => '2099-08-01',
            'alasan' => 'Keperluan keluarga.',
            'status_pengajuan' => $status,
        ]);
    }

    private function createTeacherUser(string $role, string $status = 'aktif'): array
    {
        $user = User::create([
            'username' => uniqid('user_', true),
            'name' => 'User '.uniqid(),
            'email' => uniqid('user_', true).'@example.test',
            'password' => Hash::make('password'),
        ]);
        $user->forceFill(['role' => $role, 'status' => $status])->save();
        $teacher = Teacher::create(['user_id' => $user->id, 'nama_lengkap' => 'Guru '.uniqid()]);

        return [$teacher, $user];
    }
}
