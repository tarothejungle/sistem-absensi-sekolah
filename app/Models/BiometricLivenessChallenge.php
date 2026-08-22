<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BiometricLivenessChallenge extends Model
{
    use HasUuids;

    public const PURPOSE_ENROLLMENT = 'enrollment';
    public const PURPOSE_CHECK_IN = 'check_in';
    public const PURPOSE_CHECK_OUT = 'check_out';

    public const STATUS_PENDING = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_PASSED = 'passed';
    public const STATUS_FAILED = 'failed';
    public const STATUS_EXPIRED = 'expired';

    protected $fillable = [
        'user_id',
        'teacher_id',
        'attendance_session_id',
        'purpose',
        'action_sequence',
        'method',
        'status',
        'failure_reason',
        'expires_at',
        'completed_at',
        'consumed_at',
    ];

    protected function casts(): array
    {
        return [
            'action_sequence' => 'array',
            'expires_at' => 'datetime',
            'completed_at' => 'datetime',
            'consumed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }

    public function attendanceSession(): BelongsTo
    {
        return $this->belongsTo(AttendanceSession::class);
    }
}
