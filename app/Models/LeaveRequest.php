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
        'is_sementara',
        'infal_teacher_id',
        'tanggal_mulai',
        'tanggal_selesai',
        'jam_mulai',
        'jam_selesai',
        'alasan',
        'lampiran',
        'status_pengajuan',
        'status_infal',
        'catatan_infal',
        'catatan_approval',
        'approved_by',
        'approved_at',
    ];
    protected $casts = [
        'is_sementara' => 'boolean',
        'tanggal_mulai' => 'date',
        'tanggal_selesai' => 'date',
        'approved_at' => 'datetime',
    ];

    public function teacher()
    {
        return $this->belongsTo(Teacher::class, 'teacher_id');
    }

    public function infalTeacher()
    {
        return $this->belongsTo(Teacher::class, 'infal_teacher_id');
    }
    public function approver(): BelongsTo { return $this->belongsTo(User::class, 'approved_by'); }

    public function tanggalLabel(): string
    {
        if (!$this->tanggal_mulai || !$this->tanggal_selesai) {
            return '-';
        }

        if ($this->is_sementara) {
            return $this->tanggal_mulai->format('d/m/Y') . ' '
                . $this->formatTime($this->jam_mulai)
                . ' - '
                . $this->formatTime($this->jam_selesai);
        }

        return $this->tanggal_mulai->format('d/m/Y') . ' - ' . $this->tanggal_selesai->format('d/m/Y');
    }

    private function formatTime(?string $time): string
    {
        return $time ? substr($time, 0, 5) : '--:--';
    }
}
