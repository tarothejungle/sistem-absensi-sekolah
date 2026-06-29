<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AttendanceSession extends Model
{
    protected $fillable = [
        'nama_sesi',
        'jam_masuk',
        'jam_pulang',
        'toleransi_terlambat',
        'batas_check_in_mulai',
        'batas_check_in_selesai',
        'batas_check_out_mulai',
        'batas_check_out_selesai',
        'status',
    ];

    public function teachers()
{
    return $this->belongsToMany(
        \App\Models\Teacher::class,
        'attendance_session_teacher',
        'attendance_session_id',
        'teacher_id'
    );
}
}