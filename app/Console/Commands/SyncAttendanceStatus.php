<?php

namespace App\Console\Commands;

use App\Services\DailyAttendanceStatusService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SyncAttendanceStatus extends Command
{
    protected $signature = 'attendance:sync-status {--date= : Tanggal target (Y-m-d), default hari ini}';

    protected $description = 'Sinkronkan status kehadiran otomatis (alfa, izin, hadir tidak lengkap) untuk tanggal tertentu';

    public function handle(DailyAttendanceStatusService $service): int
    {
        $date = $this->option('date');

        if ($date !== null) {
            try {
                $target = Carbon::createFromFormat('Y-m-d', $date, config('app.timezone'))->startOfDay();
            } catch (\Throwable) {
                $this->error('Format tanggal tidak valid. Gunakan Y-m-d.');

                return self::FAILURE;
            }
            $targets = collect([$target]);
        } else {
            $today = now(config('app.timezone'))->startOfDay();
            $targets = collect([$today->copy()->subDay(), $today]);
        }

        $reviewed = 0;
        foreach ($targets as $target) {
            $reviewed += $service->syncForDate($target->toDateString())->count();
        }

        $labels = $targets->map->toDateString()->join(', ');
        $this->info("Sinkronisasi status kehadiran {$labels} selesai. {$reviewed} baris absensi ditinjau.");

        return self::SUCCESS;
    }
}
