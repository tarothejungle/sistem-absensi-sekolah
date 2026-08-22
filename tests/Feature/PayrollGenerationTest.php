<?php

namespace Tests\Feature;

use App\Models\LeaveRequest;
use App\Models\PayrollItem;
use App\Models\PayrollPeriod;
use App\Models\Teacher;
use App\Models\TeacherSalary;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PayrollGenerationTest extends TestCase
{
    use DatabaseTransactions;

    public function test_generate_payroll_deducts_absent_teacher_and_rewards_infal_teacher(): void
    {
        $payrollUser = $this->createUser('bendahara');
        [$mainTeacher] = $this->createTeacherUser('guru', 'Guru Utama Payroll');
        [$infalTeacher] = $this->createTeacherUser('guru', 'Guru Infal Payroll');

        $this->createSalary($mainTeacher, 1500000, 20000);
        $this->createSalary($infalTeacher, 800000, 20000);

        $leave = LeaveRequest::create([
            'teacher_id' => $mainTeacher->id,
            'jenis_pengajuan' => 'izin',
            'tanggal_mulai' => '2099-05-10',
            'tanggal_selesai' => '2099-05-10',
            'alasan' => 'Keperluan keluarga.',
            'status_pengajuan' => 'disetujui',
            'approved_by' => $payrollUser->id,
            'approved_at' => now(),
        ]);
        $leave->infalTeachers()->sync([
            $infalTeacher->id => ['status_infal' => 'disetujui'],
        ]);

        $response = $this
            ->actingAs($payrollUser)
            ->post(route('payroll.generate'), [
                'tahun' => 2099,
                'bulan' => 5,
            ]);

        $response->assertRedirect(route('payroll.index'));
        $response->assertSessionHas('success');

        $period = PayrollPeriod::where('tahun', 2099)->where('bulan', 5)->firstOrFail();
        $mainItem = $this->payrollItem($period, $mainTeacher);
        $infalItem = $this->payrollItem($period, $infalTeacher);

        $this->assertSame($payrollUser->id, $period->generated_by);
        $this->assertEquals(1500000, (float) $mainItem->gaji_pokok);
        $this->assertEquals(20000, (float) $mainItem->potongan_absen);
        $this->assertEquals(1480000, (float) $mainItem->gaji_bersih);
        $this->assertSame(1, $mainItem->jumlah_absen_diganti);

        $this->assertEquals(800000, (float) $infalItem->gaji_pokok);
        $this->assertEquals(20000, (float) $infalItem->tambahan_infal);
        $this->assertEquals(820000, (float) $infalItem->gaji_bersih);
        $this->assertSame(1, $infalItem->jumlah_mengganti);

        $mainDetail = $mainItem->details()->where('tipe', 'potongan_absen')->firstOrFail();
        $infalDetail = $infalItem->details()->where('tipe', 'tambahan_infal')->firstOrFail();

        $this->assertSame($leave->id, $mainDetail->leave_request_id);
        $this->assertSame('2099-05-10', $mainDetail->tanggal_event->toDateString());
        $this->assertEquals(20000, (float) $mainDetail->nominal);
        $this->assertSame($leave->id, $infalDetail->leave_request_id);
        $this->assertEquals(20000, (float) $infalDetail->nominal);
        $this->assertEquals(20000, (float) $mainTeacher->salary()->firstOrFail()->potongan_per_absen);
    }

    public function test_generate_payroll_splits_deduction_evenly_between_two_infal(): void
    {
        $payrollUser = $this->createUser('bendahara');
        [$mainTeacher] = $this->createTeacherUser('guru', 'Guru Utama Split');
        [$infalB] = $this->createTeacherUser('guru', 'Guru Infal B');
        [$infalC] = $this->createTeacherUser('guru', 'Guru Infal C');

        // Gaji > 1jt → potongan 30.000, dibagi 2 → tiap infal 15.000.
        $this->createSalary($mainTeacher, 1500000, 30000);
        $this->createSalary($infalB, 800000, 20000);
        $this->createSalary($infalC, 800000, 20000);

        $leave = LeaveRequest::create([
            'teacher_id' => $mainTeacher->id,
            'jenis_pengajuan' => 'izin',
            'tanggal_mulai' => '2099-05-10',
            'tanggal_selesai' => '2099-05-10',
            'alasan' => 'Keperluan keluarga.',
            'status_pengajuan' => 'disetujui',
            'approved_by' => $payrollUser->id,
            'approved_at' => now(),
        ]);
        $leave->infalTeachers()->sync([
            $infalB->id => ['status_infal' => 'disetujui'],
            $infalC->id => ['status_infal' => 'disetujui'],
        ]);

        $this->actingAs($payrollUser)
            ->post(route('payroll.generate'), ['tahun' => 2099, 'bulan' => 5])
            ->assertRedirect(route('payroll.index'));

        $period = PayrollPeriod::where('tahun', 2099)->where('bulan', 5)->firstOrFail();
        $mainItem = $this->payrollItem($period, $mainTeacher);
        $itemB = $this->payrollItem($period, $infalB);
        $itemC = $this->payrollItem($period, $infalC);

        // Guru utama dipotong penuh, tidak dibagi.
        $this->assertEquals(30000, (float) $mainItem->potongan_absen);
        $this->assertSame(1, $mainItem->jumlah_absen_diganti);

        // Tiap infal menerima setengah.
        $this->assertEquals(15000, (float) $itemB->tambahan_infal);
        $this->assertEquals(15000, (float) $itemC->tambahan_infal);
        $this->assertSame(1, $itemB->jumlah_mengganti);
        $this->assertSame(1, $itemC->jumlah_mengganti);

        // Total tambahan == total potongan.
        $this->assertEquals(
            (float) $mainItem->potongan_absen,
            (float) $itemB->tambahan_infal + (float) $itemC->tambahan_infal
        );
    }

    public function test_generate_payroll_splits_deduction_as_raw_decimal_among_three_infal(): void
    {
        $payrollUser = $this->createUser('bendahara');
        [$mainTeacher] = $this->createTeacherUser('guru', 'Guru Utama Ganjil');
        [$infalA] = $this->createTeacherUser('guru', 'Guru Infal A3');
        [$infalB] = $this->createTeacherUser('guru', 'Guru Infal B3');
        [$infalC] = $this->createTeacherUser('guru', 'Guru Infal C3');

        // Gaji < 1jt → potongan 20.000, dibagi 3 → 6.666,67 tiap infal (desimal apa adanya).
        $this->createSalary($mainTeacher, 900000, 20000);
        $this->createSalary($infalA, 800000, 20000);
        $this->createSalary($infalB, 800000, 20000);
        $this->createSalary($infalC, 800000, 20000);

        $leave = LeaveRequest::create([
            'teacher_id' => $mainTeacher->id,
            'jenis_pengajuan' => 'izin',
            'tanggal_mulai' => '2099-05-10',
            'tanggal_selesai' => '2099-05-10',
            'alasan' => 'Keperluan keluarga.',
            'status_pengajuan' => 'disetujui',
            'approved_by' => $payrollUser->id,
            'approved_at' => now(),
        ]);
        $leave->infalTeachers()->sync([
            $infalA->id => ['status_infal' => 'disetujui'],
            $infalB->id => ['status_infal' => 'disetujui'],
            $infalC->id => ['status_infal' => 'disetujui'],
        ]);

        $this->actingAs($payrollUser)
            ->post(route('payroll.generate'), ['tahun' => 2099, 'bulan' => 5])
            ->assertRedirect(route('payroll.index'));

        $period = PayrollPeriod::where('tahun', 2099)->where('bulan', 5)->firstOrFail();
        $mainItem = $this->payrollItem($period, $mainTeacher);

        $this->assertEquals(20000, (float) $mainItem->potongan_absen);

        $shares = [];
        foreach ([$infalA, $infalB, $infalC] as $infal) {
            $item = $this->payrollItem($period, $infal);
            // 20000 / 3 = 6666.666... disimpan ke decimal(15,2) → 6666.67.
            $shares[] = (float) $item->tambahan_infal;
        }
        $this->assertSame([6666.67, 6666.67, 6666.66], $shares);
        $this->assertEquals(20000, array_sum($shares));
    }

    public function test_generate_payroll_splits_only_among_approved_infal(): void
    {
        $payrollUser = $this->createUser('bendahara');
        [$mainTeacher] = $this->createTeacherUser('guru', 'Guru Utama Partial');
        [$infalB] = $this->createTeacherUser('guru', 'Guru Infal Setuju');
        [$infalC] = $this->createTeacherUser('guru', 'Guru Infal Tolak');

        $this->createSalary($mainTeacher, 1500000, 30000);
        $this->createSalary($infalB, 800000, 20000);
        $this->createSalary($infalC, 800000, 20000);

        $leave = LeaveRequest::create([
            'teacher_id' => $mainTeacher->id,
            'jenis_pengajuan' => 'izin',
            'tanggal_mulai' => '2099-05-10',
            'tanggal_selesai' => '2099-05-10',
            'alasan' => 'Keperluan keluarga.',
            'status_pengajuan' => 'disetujui',
            'approved_by' => $payrollUser->id,
            'approved_at' => now(),
        ]);
        // C menolak → hanya B yang menerima, penuh 30.000.
        $leave->infalTeachers()->sync([
            $infalB->id => ['status_infal' => 'disetujui'],
            $infalC->id => ['status_infal' => 'ditolak'],
        ]);

        $this->actingAs($payrollUser)
            ->post(route('payroll.generate'), ['tahun' => 2099, 'bulan' => 5])
            ->assertRedirect(route('payroll.index'));

        $period = PayrollPeriod::where('tahun', 2099)->where('bulan', 5)->firstOrFail();
        $mainItem = $this->payrollItem($period, $mainTeacher);
        $itemB = $this->payrollItem($period, $infalB);
        $itemC = $this->payrollItem($period, $infalC);

        $this->assertEquals(30000, (float) $mainItem->potongan_absen);
        $this->assertEquals(30000, (float) $itemB->tambahan_infal);
        $this->assertEquals(0, (float) $itemC->tambahan_infal);
        $this->assertSame(0, $itemC->jumlah_mengganti);
    }

    public function test_generate_payroll_ignores_tugas_luar_and_super_admin_teacher_records(): void
    {
        $payrollUser = $this->createUser('bendahara');
        [$mainTeacher] = $this->createTeacherUser('guru', 'Guru Tugas Luar');
        [$infalTeacher] = $this->createTeacherUser('guru', 'Guru Infal Tugas Luar');
        [$superAdminTeacher] = $this->createTeacherUser('super_admin', 'Super Admin Bukan Item Payroll');

        $this->createSalary($mainTeacher, 1500000, 30000);
        $this->createSalary($infalTeacher, 800000, 20000);
        $this->createSalary($superAdminTeacher, 2500000, 30000);

        LeaveRequest::create([
            'teacher_id' => $mainTeacher->id,
            'jenis_pengajuan' => 'tugas_luar',
            'tanggal_mulai' => '2099-06-12',
            'tanggal_selesai' => '2099-06-12',
            'alasan' => 'Rapat dinas.',
            'status_pengajuan' => 'disetujui',
            'approved_by' => $payrollUser->id,
            'approved_at' => now(),
        ])->infalTeachers()->sync([
            $infalTeacher->id => ['status_infal' => 'disetujui'],
        ]);

        $response = $this
            ->actingAs($payrollUser)
            ->post(route('payroll.generate'), [
                'tahun' => 2099,
                'bulan' => 6,
            ]);

        $response->assertRedirect(route('payroll.index'));

        $period = PayrollPeriod::where('tahun', 2099)->where('bulan', 6)->firstOrFail();
        $mainItem = $this->payrollItem($period, $mainTeacher);
        $infalItem = $this->payrollItem($period, $infalTeacher);

        $this->assertEquals(0, (float) $mainItem->potongan_absen);
        $this->assertEquals(0, (float) $infalItem->tambahan_infal);
        $this->assertSame(0, $mainItem->details()->count());
        $this->assertSame(0, $infalItem->details()->count());
        $this->assertNull(PayrollItem::where('payroll_period_id', $period->id)
            ->where('teacher_id', $superAdminTeacher->id)
            ->first());
    }

    public function test_regular_teacher_cannot_generate_payroll(): void
    {
        [, $teacherUser] = $this->createTeacherUser('guru', 'Guru Tanpa Akses Payroll');

        $response = $this
            ->actingAs($teacherUser)
            ->post(route('payroll.generate'), [
                'tahun' => 2099,
                'bulan' => 7,
            ]);

        $response->assertForbidden();
    }

    private function payrollItem(PayrollPeriod $period, Teacher $teacher): PayrollItem
    {
        return PayrollItem::where('payroll_period_id', $period->id)
            ->where('teacher_id', $teacher->id)
            ->firstOrFail();
    }

    private function createSalary(Teacher $teacher, float $gajiPokok, float $potonganPerAbsen): TeacherSalary
    {
        return TeacherSalary::create([
            'teacher_id' => $teacher->id,
            'gaji_pokok' => $gajiPokok,
            'potongan_per_absen' => $potonganPerAbsen,
            'keterangan' => 'Data test payroll.',
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

    private function createUser(string $role, string $name = 'User Payroll Test'): User
    {
        $user = User::create([
            'username' => uniqid('user_', true),
            'name' => $name . ' ' . uniqid(),
            'email' => uniqid('payroll_', true) . '@example.test',
            'password' => Hash::make('password'),
        ]);

        // role & status tidak fillable — set eksplisit (defense-in-depth).
        $user->forceFill(['role' => $role, 'status' => 'aktif'])->save();

        return $user;
    }
}
