<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeacherSalary extends Model
{
    protected $fillable = [
        'teacher_id',
        'gaji_pokok',
        'potongan_per_absen',
        'keterangan',
    ];

    protected $casts = [
        'gaji_pokok' => 'decimal:2',
        'potongan_per_absen' => 'decimal:2',
    ];

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }
}
