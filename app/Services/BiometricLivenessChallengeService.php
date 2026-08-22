<?php

namespace App\Services;

use App\Exceptions\FaceVerificationException;
use App\Models\BiometricLivenessChallenge;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class BiometricLivenessChallengeService
{
    public function issue(User $user, Teacher $teacher, string $purpose, ?int $attendanceSessionId): BiometricLivenessChallenge
    {
        $sequence = random_int(0, 1) === 0
            ? ['center', 'turn_left', 'turn_right', 'center']
            : ['center', 'turn_right', 'turn_left', 'center'];

        return BiometricLivenessChallenge::create([
            'user_id' => $user->id,
            'teacher_id' => $teacher->id,
            'attendance_session_id' => $attendanceSessionId,
            'purpose' => $purpose,
            'action_sequence' => $sequence,
            'method' => (string) config('biometrics.liveness_method'),
            'status' => BiometricLivenessChallenge::STATUS_PENDING,
            'expires_at' => now()->addSeconds(max(30, (int) config('biometrics.challenge_ttl_seconds', 90))),
        ]);
    }

    public function begin(
        User $user,
        Teacher $teacher,
        string $challengeId,
        string $purpose,
        ?int $attendanceSessionId,
    ): BiometricLivenessChallenge {
        return DB::transaction(function () use ($user, $teacher, $challengeId, $purpose, $attendanceSessionId): BiometricLivenessChallenge {
            $challenge = BiometricLivenessChallenge::query()->lockForUpdate()->find($challengeId);

            if (! $challenge
                || (int) $challenge->user_id !== (int) $user->id
                || (int) $challenge->teacher_id !== (int) $teacher->id
                || $challenge->purpose !== $purpose
                || (int) ($challenge->attendance_session_id ?? 0) !== (int) ($attendanceSessionId ?? 0)) {
                throw new FaceVerificationException(
                    'liveness_wrong_context',
                    'Challenge liveness tidak sesuai dengan akun atau tindakan absensi.',
                );
            }

            if ($challenge->expires_at->isPast()) {
                $challenge->update([
                    'status' => BiometricLivenessChallenge::STATUS_EXPIRED,
                    'failure_reason' => 'liveness_challenge_expired',
                    'completed_at' => now(),
                ]);

                throw new FaceVerificationException(
                    'liveness_challenge_expired',
                    'Challenge liveness telah kedaluwarsa. Mulai pemindaian ulang.',
                    true,
                );
            }

            if ($challenge->status !== BiometricLivenessChallenge::STATUS_PENDING || $challenge->consumed_at !== null) {
                throw new FaceVerificationException(
                    'liveness_challenge_used',
                    'Challenge liveness sudah digunakan. Mulai pemindaian ulang.',
                    true,
                );
            }

            $challenge->update(['status' => BiometricLivenessChallenge::STATUS_PROCESSING]);

            return $challenge->fresh();
        });
    }

    public function markPassed(BiometricLivenessChallenge $challenge): void
    {
        $challenge->update([
            'status' => BiometricLivenessChallenge::STATUS_PASSED,
            'failure_reason' => null,
            'completed_at' => now(),
            'consumed_at' => now(),
        ]);
    }

    public function markFailed(BiometricLivenessChallenge $challenge, string $reason): void
    {
        $challenge->update([
            'status' => BiometricLivenessChallenge::STATUS_FAILED,
            'failure_reason' => mb_substr($reason, 0, 100),
            'completed_at' => now(),
            'consumed_at' => now(),
        ]);
    }
}
