<?php

namespace Tests\Feature;

use App\Models\PayrollPeriod;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PayrollFinalizationTest extends TestCase
{
    use DatabaseTransactions;

    public function test_bendahara_can_finalize_and_only_super_admin_can_reopen_with_audit_reason(): void
    {
        $bendahara = $this->createUser('bendahara');
        $superAdmin = $this->createUser('super_admin');
        $period = PayrollPeriod::create([
            'tahun' => 2098,
            'bulan' => 4,
            'tanggal_mulai' => '2098-04-01',
            'tanggal_selesai' => '2098-04-30',
            'status' => 'draft',
            'generated_by' => $bendahara->id,
        ]);

        $this->actingAs($bendahara)->post(route('payroll.finalize', $period))->assertSessionHas('success');
        $period->refresh();
        $this->assertSame('final', $period->status);
        $this->assertSame($bendahara->id, $period->finalized_by);

        $this->actingAs($bendahara)
            ->post(route('payroll.reopen', $period), ['reason' => 'Koreksi nilai'])
            ->assertForbidden();

        $this->actingAs($superAdmin)
            ->post(route('payroll.reopen', $period), ['reason' => 'Koreksi nilai potongan guru'])
            ->assertSessionHas('success');

        $this->assertDatabaseHas('payroll_periods', ['id' => $period->id, 'status' => 'draft']);
        $this->assertDatabaseHas('payroll_audit_logs', [
            'payroll_period_id' => $period->id,
            'user_id' => $superAdmin->id,
            'action' => 'reopened',
            'reason' => 'Koreksi nilai potongan guru',
        ]);
    }

    private function createUser(string $role): User
    {
        $user = User::create([
            'username' => strtoupper(substr($role, 0, 2)).strtoupper(substr(uniqid(), -10)),
            'name' => 'Payroll '.$role,
            'email' => uniqid('payroll_final_', true).'@example.test',
            'password' => Hash::make('Password123!'),
        ]);
        $user->forceFill(['role' => $role, 'status' => 'aktif'])->save();

        return $user;
    }
}
