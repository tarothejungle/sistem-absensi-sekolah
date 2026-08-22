<?php

namespace Tests\Unit;

use App\Models\AttendanceSession;
use App\Services\AttendanceWindowService;
use Carbon\Carbon;
use Tests\TestCase;

class OvernightAttendanceWindowTest extends TestCase
{
    public function test_overnight_checkout_uses_session_start_date(): void
    {
        $service = app(AttendanceWindowService::class);
        $session = $this->overnightSession();
        $date = Carbon::parse('2026-08-15', config('app.timezone'));
        $window = $service->resolve($session, $date);

        $this->assertSame('2026-08-15 22:00', $window['jam_masuk']->format('Y-m-d H:i'));
        $this->assertSame('2026-08-16 06:00', $window['jam_pulang']->format('Y-m-d H:i'));
        $this->assertSame('2026-08-16 05:00', $window['check_out_mulai']->format('Y-m-d H:i'));
        $this->assertSame('2026-08-16 07:00', $window['check_out_selesai']->format('Y-m-d H:i'));

        $attendanceDate = $service->attendanceDateForWindow(
            $session,
            'check_out',
            Carbon::parse('2026-08-16 05:30:00', config('app.timezone')),
        );

        $this->assertSame('2026-08-15', $attendanceDate?->toDateString());
    }

    public function test_pre_midnight_checkin_can_belong_to_next_session_date(): void
    {
        $service = app(AttendanceWindowService::class);
        $session = new AttendanceSession([
            'jam_masuk' => '00:30:00',
            'jam_pulang' => '08:00:00',
            'toleransi_terlambat' => 15,
            'batas_check_in_mulai' => '23:30:00',
            'batas_check_in_selesai' => '01:00:00',
            'batas_check_out_mulai' => '07:30:00',
            'batas_check_out_selesai' => '09:00:00',
        ]);

        $at = Carbon::parse('2026-08-15 23:45:00', config('app.timezone'));
        $attendanceDate = $service->attendanceDateForWindow($session, 'check_in', $at);

        $this->assertSame('2026-08-16', $attendanceDate?->toDateString());
        $this->assertTrue($service->isCheckInOpen($session, $at, $attendanceDate));
    }

    private function overnightSession(): AttendanceSession
    {
        return new AttendanceSession([
            'jam_masuk' => '22:00:00',
            'jam_pulang' => '06:00:00',
            'toleransi_terlambat' => 15,
            'batas_check_in_mulai' => '21:00:00',
            'batas_check_in_selesai' => '23:00:00',
            'batas_check_out_mulai' => '05:00:00',
            'batas_check_out_selesai' => '07:00:00',
        ]);
    }
}
