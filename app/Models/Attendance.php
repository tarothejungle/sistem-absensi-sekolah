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
        'tanggal',
        'check_in_time',
        'check_out_time',
        'check_in_latitude',
        'check_in_longitude',
        'check_out_latitude',
        'check_out_longitude',
        'check_in_face_photo',
        'check_out_face_photo',
        'verification_method',
        'status_kehadiran',
        'keterlambatan_menit',
        'device_info',
    ];
    protected $casts = ['tanggal' => 'date', 'check_in_time' => 'datetime', 'check_out_time' => 'datetime'];

    public function teacher(): BelongsTo { return $this->belongsTo(Teacher::class); }
    public function schedule(): BelongsTo { return $this->belongsTo(WorkSchedule::class, 'work_schedule_id'); }
    public function attendanceSession(){ return $this->belongsTo(\App\Models\AttendanceSession::class);
}
}
