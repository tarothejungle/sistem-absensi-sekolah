<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Attendance extends Model
{
    protected $fillable = [
        'teacher_id',
        'attendance_session_id',
        'work_schedule_id',
        'temporary_leave_request_id',
        'tanggal',
        'check_in_time',
        'check_out_time',
        'check_in_latitude',
        'check_in_longitude',
        'check_out_latitude',
        'check_out_longitude',
        'check_in_face_photo',
        'check_in_face_match_score',
        'check_in_liveness_challenge_id',
        'check_out_face_photo',
        'check_out_face_match_score',
        'check_out_liveness_challenge_id',
        'verification_method',
        'status_kehadiran',
        'keterlambatan_menit',
        'device_info',
    ];

    protected $casts = ['tanggal' => 'date', 'check_in_time' => 'datetime', 'check_out_time' => 'datetime'];

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(WorkSchedule::class, 'work_schedule_id');
    }

    public function temporaryLeaveRequest(): BelongsTo
    {
        return $this->belongsTo(LeaveRequest::class, 'temporary_leave_request_id');
    }

    public function attendanceSession()
    {
        return $this->belongsTo(AttendanceSession::class);
    }

    public function checkInLivenessChallenge(): BelongsTo
    {
        return $this->belongsTo(BiometricLivenessChallenge::class, 'check_in_liveness_challenge_id');
    }

    public function checkOutLivenessChallenge(): BelongsTo
    {
        return $this->belongsTo(BiometricLivenessChallenge::class, 'check_out_liveness_challenge_id');
    }
}
