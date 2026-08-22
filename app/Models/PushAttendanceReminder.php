<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PushAttendanceReminder extends Model
{
    protected $fillable = [
        'user_id',
        'attendance_session_id',
        'reminder_date',
        'reminder_type',
        'last_attempted_at',
        'attempt_count',
        'sent_at',
    ];

    protected $casts = [
        'reminder_date' => 'date',
        'last_attempted_at' => 'datetime',
        'attempt_count' => 'integer',
        'sent_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function attendanceSession(): BelongsTo
    {
        return $this->belongsTo(AttendanceSession::class);
    }
}
