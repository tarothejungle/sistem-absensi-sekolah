<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // Status otomatis (alfa / izin / hadir tidak lengkap) tidak boleh bergantung
        // pada ada-tidaknya orang membuka dashboard. Dijadwalkan tiap 15 menit agar
        // cutoff per sesi tetap terkejar, plus sekali di penghujung hari.
        $schedule->command('attendance:sync-status')
            ->everyFifteenMinutes()
            ->withoutOverlapping();

        $schedule->command('attendance:sync-status')
            ->dailyAt('23:55')
            ->withoutOverlapping();

        $schedule->command('attendance:prune-photos')
            ->dailyAt('02:15')
            ->withoutOverlapping();

        $schedule->command('login-activities:prune')
            ->hourly()
            ->withoutOverlapping();

        $schedule->command('auth:clear-resets')
            ->dailyAt('02:30')
            ->withoutOverlapping();

        $schedule->command('biometrics:prune-liveness-challenges')
            ->everyFiveMinutes()
            ->withoutOverlapping();

        $schedule->command('notifications:send-attendance-reminders')
            ->everyFiveMinutes()
            ->withoutOverlapping();
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
