<?php

namespace App\Services;

use App\Models\AttendanceSession;
use App\Models\LeaveRequest;
use App\Models\Teacher;
use Carbon\Carbon;
use Carbon\CarbonInterface;

class TemporaryLeaveAttendancePolicy
{
    public function __construct(private readonly AttendanceWindowService $windowService) {}

    public function resolve(
        Teacher $teacher,
        AttendanceSession $session,
        CarbonInterface|string $date,
        ?CarbonInterface $at = null
    ): ?array {
        $date = Carbon::parse($date, 'Asia/Jakarta')->toDateString();
        $at = $at ? Carbon::instance($at)->timezone('Asia/Jakarta') : now('Asia/Jakarta');
        $window = $this->windowService->resolve($session, $date);
        $sessionStartsAt = $window['jam_masuk'];
        $sessionEndsAt = $window['jam_pulang'];
        $nativeStartsAt = $window['check_in_mulai'];
        $nativeDeadline = $window['check_in_selesai'];

        $leave = LeaveRequest::query()
            ->where('teacher_id', $teacher->id)
            ->where('is_sementara', true)
            ->where('status_pengajuan', 'disetujui')
            ->whereDate('tanggal_mulai', $date)
            ->whereNotNull('approved_at')
            ->where('approved_at', '<=', $at)
            ->orderByDesc('approved_at')
            ->get()
            ->first(function (LeaveRequest $leave) use ($date, $sessionStartsAt, $sessionEndsAt): bool {
                $leaveStartsAt = Carbon::parse($date.' '.$leave->jam_mulai, 'Asia/Jakarta');
                $leaveEndsAt = Carbon::parse($date.' '.$leave->jam_selesai, 'Asia/Jakarta');
                $graceDeadline = $leaveEndsAt->copy()->addMinutes(30)->min($sessionEndsAt);

                return $sessionStartsAt->betweenIncluded($leaveStartsAt, $leaveEndsAt)
                    && $leave->approved_at->lte($graceDeadline);
            });

        if (! $leave) {
            return null;
        }

        $leaveStartsAt = Carbon::parse($date.' '.$leave->jam_mulai, 'Asia/Jakarta');
        $leaveEndsAt = Carbon::parse($date.' '.$leave->jam_selesai, 'Asia/Jakarta');
        $graceDeadline = $leaveEndsAt->copy()->addMinutes(30)->min($sessionEndsAt);

        return [
            'leave' => $leave,
            'leave_starts_at' => $leaveStartsAt,
            'leave_ends_at' => $leaveEndsAt,
            'native_starts_at' => $nativeStartsAt,
            'native_deadline' => $nativeDeadline,
            'grace_deadline' => $graceDeadline,
            'effective_deadline' => $nativeDeadline->max($graceDeadline),
            'is_grace_active' => $at->betweenIncluded($nativeStartsAt, $graceDeadline),
        ];
    }
}
