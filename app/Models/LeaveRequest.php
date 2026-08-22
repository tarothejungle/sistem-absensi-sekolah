<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LeaveRequest extends Model
{
    protected $fillable = [
        'teacher_id',
        'jenis_pengajuan',
        'is_sementara',
        'tanggal_mulai',
        'tanggal_selesai',
        'jam_mulai',
        'jam_selesai',
        'alasan',
        'lampiran',
        'status_pengajuan',
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

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class, 'teacher_id');
    }

    public function infalTeachers(): BelongsToMany
    {
        return $this->belongsToMany(Teacher::class, 'leave_request_infal', 'leave_request_id', 'teacher_id')
            ->withPivot(['status_infal', 'catatan_infal'])
            ->withTimestamps();
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function graceAttendances(): HasMany
    {
        return $this->hasMany(Attendance::class, 'temporary_leave_request_id');
    }

    /**
     * Status infal agregat dari semua guru pengganti.
     * - 'disetujui' bila minimal satu menyetujui
     * - 'ditolak'   bila semua menolak
     * - 'pending'   bila masih ada yang pending
     * - null        bila tidak ada guru pengganti
     */
    public function statusInfalAgregat(): ?string
    {
        $infal = $this->infalTeachers;

        if ($infal->isEmpty()) {
            return null;
        }

        if ($infal->contains(fn ($teacher) => $teacher->pivot->status_infal === 'disetujui')) {
            return 'disetujui';
        }

        if ($infal->every(fn ($teacher) => $teacher->pivot->status_infal === 'ditolak')) {
            return 'ditolak';
        }

        return 'pending';
    }

    public function tanggalLabel(): string
    {
        if (! $this->tanggal_mulai || ! $this->tanggal_selesai) {
            return '-';
        }

        if ($this->is_sementara) {
            return $this->tanggal_mulai->format('d/m/Y').' '
                .$this->formatTime($this->jam_mulai)
                .' - '
                .$this->formatTime($this->jam_selesai);
        }

        return $this->tanggal_mulai->format('d/m/Y').' - '.$this->tanggal_selesai->format('d/m/Y');
    }

    private function formatTime(?string $time): string
    {
        return $time ? substr($time, 0, 5) : '--:--';
    }
}
