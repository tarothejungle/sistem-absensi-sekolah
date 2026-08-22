<?php

namespace Tests\Feature;

use App\Exports\InfalReportExport;
use App\Models\LeaveRequest;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class InfalReportTest extends TestCase
{
    use DatabaseTransactions;

    public function test_index_shows_only_approved_infal_records_within_date_range(): void
    {
        $reportUser = $this->createUser('bendahara');
        [$mainTeacher] = $this->createTeacherUser('guru', 'Guru Utama Rekap Infal');
        [$infalTeacher] = $this->createTeacherUser('guru', 'Guru Pengganti Rekap Infal');
        [$outsideTeacher] = $this->createTeacherUser('guru', 'Guru Luar Rentang Infal');

        $includedLeave = $this->createLeave($mainTeacher, [$infalTeacher->id => 'disetujui'], [
            'tanggal_mulai' => '2099-08-10',
            'tanggal_selesai' => '2099-08-10',
        ]);

        $this->createLeave($outsideTeacher, [$infalTeacher->id => 'disetujui'], [
            'tanggal_mulai' => '2099-09-01',
            'tanggal_selesai' => '2099-09-01',
        ]);

        $this->createLeave($mainTeacher, [$infalTeacher->id => 'pending'], [
            'tanggal_mulai' => '2099-08-11',
            'tanggal_selesai' => '2099-08-11',
        ]);

        $this->createLeave($mainTeacher, [], [
            'tanggal_mulai' => '2099-08-12',
            'tanggal_selesai' => '2099-08-12',
        ]);

        $response = $this
            ->actingAs($reportUser)
            ->get(route('infal.report.index', [
                'tanggal_mulai' => '2099-08-01',
                'tanggal_selesai' => '2099-08-31',
            ]));

        $response->assertOk();

        $items = $response->viewData('items')->getCollection();

        $this->assertCount(1, $items);
        $this->assertSame($includedLeave->id, (int) $items->first()->leave_request_id);
        $response->assertSee($mainTeacher->nama_lengkap);
        $response->assertDontSee($outsideTeacher->nama_lengkap);
    }

    public function test_index_lists_each_approved_pivot_and_includes_overlapping_leave(): void
    {
        $reportUser = $this->createUser('bendahara');
        [$mainTeacher] = $this->createTeacherUser('guru', 'Guru Utama Overlap');
        [$firstInfal] = $this->createTeacherUser('guru', 'Guru Infal Overlap Satu');
        [$secondInfal] = $this->createTeacherUser('guru', 'Guru Infal Overlap Dua');
        [$pendingInfal] = $this->createTeacherUser('guru', 'Guru Infal Pending');

        $leave = $this->createLeave($mainTeacher, [
            $firstInfal->id => 'disetujui',
            $secondInfal->id => 'disetujui',
            $pendingInfal->id => 'pending',
        ], [
            'tanggal_mulai' => '2099-07-28',
            'tanggal_selesai' => '2099-08-03',
        ]);

        $response = $this->actingAs($reportUser)->get(route('infal.report.index', [
            'tanggal_mulai' => '2099-08-01',
            'tanggal_selesai' => '2099-08-31',
        ]))->assertOk();

        $items = $response->viewData('items')->getCollection();

        $this->assertCount(2, $items);
        $this->assertEqualsCanonicalizing(
            [$firstInfal->nama_lengkap, $secondInfal->nama_lengkap],
            $items->pluck('guru_infal')->all()
        );
        $this->assertSame([$leave->id, $leave->id], $items->pluck('leave_request_id')->map(fn ($id) => (int) $id)->all());
    }

    public function test_regular_teacher_cannot_access_infal_report(): void
    {
        [, $teacherUser] = $this->createTeacherUser('guru', 'Guru Tanpa Akses Rekap');

        $response = $this
            ->actingAs($teacherUser)
            ->get(route('infal.report.index'));

        $response->assertForbidden();
    }

    public function test_index_shows_filter_reset_and_export_actions_in_order(): void
    {
        $reportUser = $this->createUser('bendahara');

        $response = $this->actingAs($reportUser)
            ->get(route('infal.report.index'))
            ->assertOk();

        $response->assertSeeInOrder([
            'Filter',
            'Reset',
            'Export',
            'Cetak PDF',
            'Export Excel',
        ]);
        $response->assertSee('app-export-dropdown', false);
    }

    public function test_infal_report_export_headings_match_mapped_columns(): void
    {
        [$mainTeacher] = $this->createTeacherUser('guru', 'Guru Export Infal');
        [$infalTeacher] = $this->createTeacherUser('guru', 'Guru Pengganti Export Infal');

        $this->createLeave($mainTeacher, [$infalTeacher->id => 'disetujui'], [
            'tanggal_mulai' => '2099-08-15',
            'tanggal_selesai' => '2099-08-15',
            'alasan' => 'Keperluan keluarga.',
        ]);

        $export = new InfalReportExport('2099-08-01', '2099-08-31');
        $items = $export->collection();
        $row = $export->map($items->first());

        $this->assertCount(1, $items);
        $this->assertCount(count($export->headings()), $row);
        $this->assertSame(1, $row[0]);
        $this->assertSame('15/08/2099 - 15/08/2099', $row[1]);
        $this->assertSame($mainTeacher->nama_lengkap, $row[2]);
        $this->assertSame($infalTeacher->nama_lengkap, $row[3]);
        $this->assertSame('Izin', $row[4]);
        $this->assertSame('Keperluan keluarga.', $row[5]);
        $this->assertSame('Disetujui', $row[6]);
        $this->assertSame('Disetujui', $row[7]);
    }

    private function createLeave(Teacher $teacher, array $infals, array $overrides = []): LeaveRequest
    {
        $leave = LeaveRequest::create(array_merge([
            'teacher_id' => $teacher->id,
            'jenis_pengajuan' => 'izin',
            'tanggal_mulai' => '2099-08-10',
            'tanggal_selesai' => '2099-08-10',
            'alasan' => 'Keperluan keluarga.',
            'status_pengajuan' => 'disetujui',
            'approved_by' => null,
            'approved_at' => now(),
        ], $overrides));

        foreach ($infals as $teacherId => $status) {
            $leave->infalTeachers()->attach($teacherId, ['status_infal' => $status]);
        }

        return $leave;
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

    private function createUser(string $role, string $name = 'User Rekap Infal'): User
    {
        $user = User::create([
            'username' => uniqid('user_', true),
            'name' => $name.' '.uniqid(),
            'email' => uniqid('infal_report_', true).'@example.test',
            'password' => Hash::make('password'),
        ]);

        // role & status tidak fillable — set eksplisit (defense-in-depth).
        $user->forceFill(['role' => $role, 'status' => 'aktif'])->save();

        return $user;
    }
}
