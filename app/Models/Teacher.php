<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Teacher extends Model
{
    public const DATA_GURU_ROLES = ['guru', 'kepala_sekolah', 'bendahara'];

    public const DATA_GURU_ROLE_LABELS = [
        'guru' => 'Guru',
        'kepala_sekolah' => 'Kepala Sekolah',
        'bendahara' => 'Bendahara',
    ];

    protected $fillable = ['user_id', 'nama_lengkap', 'jenis_kelamin', 'no_hp', 'jabatan', 'mata_pelajaran'];

    public static function dataGuruRoleLabel(?string $role): string
    {
        if (! $role) {
            return '-';
        }

        return self::DATA_GURU_ROLE_LABELS[$role] ?? ucwords(str_replace('_', ' ', $role));
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(WorkSchedule::class);
    }

    public function leaves(): HasMany
    {
        return $this->hasMany(LeaveRequest::class);
    }

    public function salary(): HasOne
    {
        return $this->hasOne(TeacherSalary::class);
    }

    public function payrollItems(): HasMany
    {
        return $this->hasMany(PayrollItem::class);
    }

    public function dutySchedules(): BelongsToMany
    {
        return $this->belongsToMany(DutySchedule::class, 'duty_schedule_teacher')->withTimestamps();
    }

    public function attendanceSessions()
    {
        return $this->belongsToMany(
            AttendanceSession::class,
            'attendance_session_teacher',
            'teacher_id',
            'attendance_session_id'
        );
    }
}
