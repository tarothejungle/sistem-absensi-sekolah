<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AttendanceSetting extends Model
{
    protected $fillable = [
        'jam_masuk',
        'jam_pulang',
        'toleransi_terlambat',
        'batas_check_in_mulai',
        'batas_check_in_selesai',
        'batas_check_out_mulai',
        'batas_check_out_selesai',
        'status',
    ];
}