<?php

namespace App\Console\Commands;

use App\Models\BiometricLivenessChallenge;
use Illuminate\Console\Command;

class PruneBiometricLivenessChallenges extends Command
{
    protected $signature = 'biometrics:prune-liveness-challenges {--days= : Override retention days}';

    protected $description = 'Expire stale liveness challenges and prune old audit metadata';

    public function handle(): int
    {
        $now = now();
        $expired = BiometricLivenessChallenge::query()
            ->whereIn('status', [
                BiometricLivenessChallenge::STATUS_PENDING,
                BiometricLivenessChallenge::STATUS_PROCESSING,
            ])
            ->where('expires_at', '<', $now)
            ->update([
                'status' => BiometricLivenessChallenge::STATUS_EXPIRED,
                'failure_reason' => 'liveness_challenge_expired',
                'completed_at' => $now,
            ]);

        $days = max(1, (int) ($this->option('days') ?: config('biometrics.audit_retention_days', 90)));
        $pruned = BiometricLivenessChallenge::query()
            ->where('created_at', '<', $now->copy()->subDays($days))
            ->whereIn('status', [
                BiometricLivenessChallenge::STATUS_PASSED,
                BiometricLivenessChallenge::STATUS_FAILED,
                BiometricLivenessChallenge::STATUS_EXPIRED,
            ])
            ->delete();

        $this->info("{$expired} challenge kedaluwarsa ditandai; {$pruned} audit challenge lama dihapus.");

        return self::SUCCESS;
    }
}
