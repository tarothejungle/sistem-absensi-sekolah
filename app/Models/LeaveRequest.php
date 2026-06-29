<?php

namespace App\Models;

use App\Models\Teacher;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeaveRequest extends Model
{
    protected $fillable = [
        'teacher_id',
        'jenis_pengajuan',
        'infal_teacher_id',
        'tanggal_mulai',
        'tanggal_selesai',
        'alasan',
        'lampiran',
        'status_pengajuan',
        'status_infal',
        'catatan_infal',
        'approved_by',
    ];
    protected $casts = ['tanggal_mulai' => 'date', 'tanggal_selesai' => 'date', 'approved_at' => 'datetime'];

    public function teacher()
    {
        return $this->belongsTo(Teacher::class, 'teacher_id');
    }

    public function infalTeacher()
    {
        return $this->belongsTo(Teacher::class, 'infal_teacher_id');
    }
    public function approver(): BelongsTo { return $this->belongsTo(User::class, 'approved_by'); }
}
