<?php

namespace App\Services;

use App\Models\AttendanceSession;
use Carbon\Carbon;
use Carbon\CarbonInterface;

class AttendanceWindowService
{
    public function resolve(
        AttendanceSession $session,
        CarbonInterface|string|null $attendanceDate = null
    ): array {
        $date = $this->attendanceDate($attendanceDate);
        $jamMasuk = $this->at($date, $session->jam_masuk);
        $jamPulang = $this->at($date, $session->jam_pulang);

        if ($jamPulang->lte($jamMasuk)) {
            $jamPulang->addDay();
        }

        $checkInStart = $this->nearestOccurrence($date, $session->batas_check_in_mulai, $jamMasuk);
        $checkInEnd = $this->at($date, $session->batas_check_in_selesai);
        while ($checkInEnd->lt($checkInStart)) {
            $checkInEnd->addDay();
        }

        $checkOutStart = $this->nearestOccurrence($date, $session->batas_check_out_mulai, $jamPulang);
        $checkOutEnd = $this->at($date, $session->batas_check_out_selesai);
        while ($checkOutEnd->lt($checkOutStart)) {
            $checkOutEnd->addDay();
        }

        return [
            'attendance_date' => $date,
            'jam_masuk' => $jamMasuk,
            'jam_pulang' => $jamPulang,
            'toleransi_menit' => (int) $session->toleransi_terlambat,
            'batas_tepat_waktu' => $jamMasuk->copy()->addMinutes((int) $session->toleransi_terlambat),
            'check_in_mulai' => $checkInStart,
            'check_in_selesai' => $checkInEnd,
            'check_out_mulai' => $checkOutStart,
            'check_out_selesai' => $checkOutEnd,
        ];
    }

    public function isCheckInOpen(
        AttendanceSession $session,
        CarbonInterface|string|null $currentTime = null,
        CarbonInterface|string|null $attendanceDate = null
    ): bool {
        $window = $this->resolve($session, $attendanceDate);
        $now = $this->currentAt($currentTime, $window['attendance_date'], $window['check_in_mulai'], $window['check_in_selesai']);

        return $now->betweenIncluded($window['check_in_mulai'], $window['check_in_selesai']);
    }

    public function isCheckOutOpen(
        AttendanceSession $session,
        CarbonInterface|string|null $currentTime = null,
        CarbonInterface|string|null $attendanceDate = null
    ): bool {
        $window = $this->resolve($session, $attendanceDate);
        $now = $this->currentAt($currentTime, $window['attendance_date'], $window['check_out_mulai'], $window['check_out_selesai']);

        return $now->betweenIncluded($window['check_out_mulai'], $window['check_out_selesai']);
    }

    public function isOnTime(
        AttendanceSession $session,
        CarbonInterface|string $checkInTime,
        CarbonInterface|string|null $attendanceDate = null
    ): bool {
        $window = $this->resolve($session, $attendanceDate);
        $checkIn = $this->currentAt($checkInTime, $window['attendance_date'], $window['jam_masuk'], $window['batas_tepat_waktu']);

        return $checkIn->lte($window['batas_tepat_waktu']);
    }

    /**
     * @return array<int, Carbon>
     */
    public function candidateAttendanceDates(AttendanceSession $session, ?CarbonInterface $at = null): array
    {
        $now = $at ? Carbon::instance($at)->timezone(config('app.timezone')) : now(config('app.timezone'));
        $base = $now->copy()->startOfDay();
        $dates = [$base->copy(), $base->copy()->subDay(), $base->copy()->addDay()];

        usort($dates, function (Carbon $left, Carbon $right) use ($session, $now): int {
            $leftDistance = abs($this->resolve($session, $left)['jam_masuk']->diffInSeconds($now, false));
            $rightDistance = abs($this->resolve($session, $right)['jam_masuk']->diffInSeconds($now, false));

            return $leftDistance <=> $rightDistance;
        });

        return $dates;
    }

    public function attendanceDateForWindow(
        AttendanceSession $session,
        string $windowType,
        ?CarbonInterface $at = null
    ): ?Carbon {
        $now = $at ? Carbon::instance($at)->timezone(config('app.timezone')) : now(config('app.timezone'));

        foreach ($this->candidateAttendanceDates($session, $now) as $date) {
            $window = $this->resolve($session, $date);
            $start = $windowType === 'check_out' ? $window['check_out_mulai'] : $window['check_in_mulai'];
            $end = $windowType === 'check_out' ? $window['check_out_selesai'] : $window['check_in_selesai'];

            if ($now->betweenIncluded($start, $end)) {
                return $date;
            }
        }

        return null;
    }

    public function checkInStatusMessage(
        AttendanceSession $session,
        CarbonInterface|string|null $currentTime = null,
        CarbonInterface|string|null $attendanceDate = null
    ): string {
        $window = $this->resolve($session, $attendanceDate);
        $now = $this->currentAt($currentTime, $window['attendance_date'], $window['check_in_mulai'], $window['check_in_selesai']);

        if ($now->lt($window['check_in_mulai'])) {
            return 'Check-in akan dibuka pukul '.$window['check_in_mulai']->format('H:i').'.';
        }

        if ($now->lte($window['batas_tepat_waktu'])) {
            return 'Check-in tersedia sampai pukul '.$window['check_in_selesai']->format('H:i').'.';
        }

        if ($now->lte($window['check_in_selesai'])) {
            return 'Check-in masih tersedia sampai pukul '.$window['check_in_selesai']->format('H:i').
                '. Status akan tercatat terlambat.';
        }

        return 'Batas check-in berakhir pukul '.$window['check_in_selesai']->format('H:i').'.';
    }

    public function checkOutStatusMessage(
        AttendanceSession $session,
        CarbonInterface|string|null $currentTime = null,
        CarbonInterface|string|null $attendanceDate = null
    ): string {
        $window = $this->resolve($session, $attendanceDate);
        $now = $this->currentAt($currentTime, $window['attendance_date'], $window['check_out_mulai'], $window['check_out_selesai']);

        if ($now->lt($window['check_out_mulai'])) {
            return 'Check-out akan dibuka pukul '.$window['check_out_mulai']->format('H:i').'.';
        }

        if ($now->lte($window['check_out_selesai'])) {
            return 'Check-out tersedia sampai pukul '.$window['check_out_selesai']->format('H:i').'.';
        }

        return 'Batas check-out berakhir pukul '.$window['check_out_selesai']->format('H:i').'.';
    }

    private function attendanceDate(CarbonInterface|string|null $date): Carbon
    {
        if ($date instanceof CarbonInterface) {
            return Carbon::instance($date)->timezone(config('app.timezone'))->startOfDay();
        }

        return $date
            ? Carbon::parse($date, config('app.timezone'))->startOfDay()
            : now(config('app.timezone'))->startOfDay();
    }

    private function at(CarbonInterface $date, string $time): Carbon
    {
        return Carbon::parse($date->toDateString().' '.$time, config('app.timezone'));
    }

    private function nearestOccurrence(Carbon $date, string $time, CarbonInterface $reference): Carbon
    {
        return collect([-1, 0, 1])
            ->map(fn (int $offset) => $this->at($date->copy()->addDays($offset), $time))
            ->sortBy(fn (Carbon $candidate) => abs($candidate->diffInSeconds($reference, false)))
            ->first();
    }

    private function currentAt(
        CarbonInterface|string|null $current,
        Carbon $attendanceDate,
        Carbon $windowStart,
        Carbon $windowEnd
    ): Carbon {
        if ($current instanceof CarbonInterface) {
            return Carbon::instance($current)->timezone(config('app.timezone'));
        }

        if ($current === null) {
            return now(config('app.timezone'));
        }

        if (preg_match('/\d{4}-\d{2}-\d{2}/', $current) === 1) {
            return Carbon::parse($current, config('app.timezone'));
        }

        $midpoint = $windowStart->copy()->addSeconds((int) floor($windowStart->diffInSeconds($windowEnd) / 2));

        return collect([-1, 0, 1])
            ->map(fn (int $offset) => $this->at($attendanceDate->copy()->addDays($offset), $current))
            ->sortBy(fn (Carbon $candidate) => abs($candidate->diffInSeconds($midpoint, false)))
            ->first();
    }
}
