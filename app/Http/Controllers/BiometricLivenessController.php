<?php

namespace App\Http\Controllers;

use App\Models\AttendanceSession;
use App\Models\BiometricLivenessChallenge;
use App\Models\Teacher;
use App\Services\AttendanceWindowService;
use App\Services\BiometricLivenessChallengeService;
use App\Services\WorkScheduleResolver;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BiometricLivenessController extends Controller
{
    public function store(
        Request $request,
        BiometricLivenessChallengeService $service,
        AttendanceWindowService $windowService,
        WorkScheduleResolver $scheduleResolver,
    ) {
        $user = $request->user();
        abort_unless($user && in_array($user->role, Teacher::DATA_GURU_ROLES, true) && $user->teacher, 403);

        $data = $request->validate([
            'purpose' => ['required', Rule::in([
                BiometricLivenessChallenge::PURPOSE_ENROLLMENT,
                BiometricLivenessChallenge::PURPOSE_CHECK_IN,
                BiometricLivenessChallenge::PURPOSE_CHECK_OUT,
            ])],
            'attendance_session_id' => [
                Rule::requiredIf($request->input('purpose') !== BiometricLivenessChallenge::PURPOSE_ENROLLMENT),
                Rule::prohibitedIf($request->input('purpose') === BiometricLivenessChallenge::PURPOSE_ENROLLMENT),
                'nullable',
                'integer',
                'exists:attendance_sessions,id',
            ],
        ]);

        $attendanceSessionId = isset($data['attendance_session_id']) ? (int) $data['attendance_session_id'] : null;
        if ($attendanceSessionId !== null) {
            $assigned = collect($windowService->candidateAttendanceDates(
                AttendanceSession::findOrFail($attendanceSessionId),
                now(config('app.timezone')),
            ))->contains(fn ($date): bool => $scheduleResolver
                ->sessionsForDate($user->teacher, $date)
                ->contains('id', $attendanceSessionId));
            abort_unless($assigned, 403);
        }

        $challenge = $service->issue($user, $user->teacher, $data['purpose'], $attendanceSessionId);

        return response()->json([
            'challenge_id' => $challenge->id,
            'method' => $challenge->method,
            'steps' => $challenge->action_sequence,
            'expires_at' => $challenge->expires_at->toIso8601String(),
        ]);
    }
}
