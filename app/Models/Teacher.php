<?php

namespace App\Models;

use App\Models\TeacherSalary;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Teacher extends Model
{
    protected $fillable = ['user_id', 'attendance_session_id','nama_lengkap','jenis_kelamin','no_hp','email','jabatan','mata_pelajaran','foto'];

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function attendances(): HasMany { return $this->hasMany(Attendance::class); }
    public function schedules(): HasMany { return $this->hasMany(WorkSchedule::class); }
    public function leaves(): HasMany { return $this->hasMany(LeaveRequest::class); }
    public function salary(): HasOne { return $this->hasOne(TeacherSalary::class); }
    public function payrollItems(): HasMany { return $this->hasMany(PayrollItem::class); }
    public function dutySchedules(): BelongsToMany { return $this->belongsToMany(DutySchedule::class, 'duty_schedule_teacher')->withTimestamps(); }

    // public function leaveRequests()
    // {
    //     return $this->hasMany(\App\Models\LeaveRequest::class);
    // }
    public function attendanceSessions()
{
    return $this->belongsToMany(
        \App\Models\AttendanceSession::class,
        'attendance_session_teacher',
        'teacher_id',
        'attendance_session_id'
    );
}
}
