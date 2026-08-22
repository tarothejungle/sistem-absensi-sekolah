<?php

namespace Tests\Feature;

use App\Models\AttendanceSession;
use App\Services\AttendanceWindowService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class AttendanceWindowServiceTest extends TestCase
{
    use DatabaseTransactions;

    private AttendanceWindowService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(AttendanceWindowService::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_resolve_returns_correct_window_values(): void
    {
        $session = $this->createSession();

        $window = $this->service->resolve($session);

        $this->assertSame('07:00', $window['jam_masuk']->format('H:i'));
        $this->assertSame('12:00', $this->formatTime($window['jam_pulang']));
        $this->assertSame(15, $window['toleransi_menit']);
        $this->assertSame('07:15', $window['batas_tepat_waktu']->format('H:i'));
        $this->assertSame('06:30', $window['check_in_mulai']->format('H:i'));
        $this->assertSame('07:30', $this->formatTime($window['check_in_selesai']));
        $this->assertSame('12:00', $this->formatTime($window['check_out_mulai']));
        $this->assertSame('13:00', $this->formatTime($window['check_out_selesai']));
    }

    public function test_check_in_before_opening_is_closed(): void
    {
        $session = $this->createSession();

        $this->assertFalse($this->service->isCheckInOpen($session, '06:29:00'));
    }

    public function test_check_in_at_opening_is_open(): void
    {
        $session = $this->createSession();

        $this->assertTrue($this->service->isCheckInOpen($session, '06:30:00'));
    }

    public function test_check_in_at_07_00_is_open(): void
    {
        $session = $this->createSession();

        $this->assertTrue($this->service->isCheckInOpen($session, '07:00:00'));
    }

    public function test_check_in_at_07_15_is_still_open(): void
    {
        $session = $this->createSession();

        $this->assertTrue($this->service->isCheckInOpen($session, '07:15:00'));
    }

    public function test_check_in_at_07_29_is_open(): void
    {
        $session = $this->createSession();

        $this->assertTrue($this->service->isCheckInOpen($session, '07:29:00'));
    }

    public function test_check_in_at_07_30_is_open(): void
    {
        $session = $this->createSession();

        $this->assertTrue($this->service->isCheckInOpen($session, '07:30:00'));
    }

    public function test_check_in_after_closing_is_closed(): void
    {
        $session = $this->createSession();

        $this->assertFalse($this->service->isCheckInOpen($session, '07:31:00'));
    }

    public function test_check_in_at_08_00_is_closed(): void
    {
        $session = $this->createSession();

        $this->assertFalse($this->service->isCheckInOpen($session, '08:00:00'));
    }

    public function test_check_out_before_opening_is_closed(): void
    {
        $session = $this->createSession();

        $this->assertFalse($this->service->isCheckOutOpen($session, '11:59:00'));
    }

    public function test_check_out_at_opening_is_open(): void
    {
        $session = $this->createSession();

        $this->assertTrue($this->service->isCheckOutOpen($session, '12:00:00'));
    }

    public function test_check_out_at_13_00_is_open(): void
    {
        $session = $this->createSession();

        $this->assertTrue($this->service->isCheckOutOpen($session, '13:00:00'));
    }

    public function test_check_out_after_closing_is_closed(): void
    {
        $session = $this->createSession();

        $this->assertFalse($this->service->isCheckOutOpen($session, '13:01:00'));
    }

    public function test_is_on_time_at_jam_masuk(): void
    {
        $session = $this->createSession();

        $this->assertTrue($this->service->isOnTime($session, '07:00:00'));
    }

    public function test_is_on_time_at_tolerance_boundary(): void
    {
        $session = $this->createSession();

        $this->assertTrue($this->service->isOnTime($session, '07:15:00'));
    }

    public function test_is_late_after_tolerance_boundary(): void
    {
        $session = $this->createSession();

        $this->assertFalse($this->service->isOnTime($session, '07:15:01'));
    }

    public function test_is_late_at_07_30(): void
    {
        $session = $this->createSession();

        $this->assertFalse($this->service->isOnTime($session, '07:30:00'));
    }

    public function test_check_in_status_message_before_opening(): void
    {
        $session = $this->createSession();

        $message = $this->service->checkInStatusMessage($session, '06:00:00');

        $this->assertStringContainsString('06:30', $message);
        $this->assertStringContainsString('dibuka', $message);
    }

    public function test_check_in_status_message_while_on_time(): void
    {
        $session = $this->createSession();

        $message = $this->service->checkInStatusMessage($session, '07:00:00');

        $this->assertStringContainsString('07:30', $message);
        $this->assertStringNotContainsString('terlambat', $message);
    }

    public function test_check_in_status_message_while_late_but_still_open(): void
    {
        $session = $this->createSession();

        $message = $this->service->checkInStatusMessage($session, '07:20:00');

        $this->assertStringContainsString('07:30', $message);
        $this->assertStringContainsString('terlambat', $message);
    }

    public function test_check_in_status_message_after_closing(): void
    {
        $session = $this->createSession();

        $message = $this->service->checkInStatusMessage($session, '08:00:00');

        $this->assertStringContainsString('07:30', $message);
        $this->assertStringContainsString('berakhir', $message);
    }

    public function test_check_out_status_message_before_opening(): void
    {
        $session = $this->createSession();

        $message = $this->service->checkOutStatusMessage($session, '11:00:00');

        $this->assertStringContainsString('12:00', $message);
        $this->assertStringContainsString('dibuka', $message);
    }

    public function test_check_out_status_message_while_open(): void
    {
        $session = $this->createSession();

        $message = $this->service->checkOutStatusMessage($session, '12:30:00');

        $this->assertStringContainsString('13:00', $message);
        $this->assertStringContainsString('tersedia', $message);
    }

    public function test_check_out_status_message_after_closing(): void
    {
        $session = $this->createSession();

        $message = $this->service->checkOutStatusMessage($session, '14:00:00');

        $this->assertStringContainsString('13:00', $message);
        $this->assertStringContainsString('berakhir', $message);
    }

    public function test_check_in_at_exact_tolerance_boundary_one_second_before(): void
    {
        $session = $this->createSession();

        $this->assertTrue($this->service->isOnTime($session, '07:14:59'));
    }

    public function test_check_in_at_exact_tolerance_boundary_one_second_after(): void
    {
        $session = $this->createSession();

        $this->assertFalse($this->service->isOnTime($session, '07:15:01'));
    }

    public function test_sesi_siang_check_in_boundary(): void
    {
        $session = AttendanceSession::create([
            'nama_sesi' => 'Sesi Siang',
            'jam_masuk' => '12:30:00',
            'jam_pulang' => '17:00:00',
            'toleransi_terlambat' => 15,
            'batas_check_in_mulai' => '12:00:00',
            'batas_check_in_selesai' => '13:00:00',
            'batas_check_out_mulai' => '17:00:00',
            'batas_check_out_selesai' => '18:00:00',
            'status' => 'aktif',
        ]);

        $this->assertTrue($this->service->isCheckInOpen($session, '12:00:00'));
        $this->assertTrue($this->service->isCheckInOpen($session, '12:30:00'));
        $this->assertTrue($this->service->isCheckInOpen($session, '12:45:00'));
        $this->assertTrue($this->service->isCheckInOpen($session, '13:00:00'));
        $this->assertFalse($this->service->isCheckInOpen($session, '13:01:00'));

        $this->assertTrue($this->service->isOnTime($session, '12:30:00'));
        $this->assertTrue($this->service->isOnTime($session, '12:45:00'));
        $this->assertFalse($this->service->isOnTime($session, '12:45:01'));
    }

    private function createSession(): AttendanceSession
    {
        return AttendanceSession::create([
            'nama_sesi' => 'Sesi Test '.uniqid(),
            'jam_masuk' => '07:00:00',
            'jam_pulang' => '12:00:00',
            'toleransi_terlambat' => 15,
            'batas_check_in_mulai' => '06:30:00',
            'batas_check_in_selesai' => '07:30:00',
            'batas_check_out_mulai' => '12:00:00',
            'batas_check_out_selesai' => '13:00:00',
            'status' => 'aktif',
        ]);
    }

    private function formatTime(Carbon $time): string
    {
        return $time->format('H:i');
    }
}
