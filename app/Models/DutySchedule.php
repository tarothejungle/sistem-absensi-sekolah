<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class DutySchedule extends Model
{
    protected $fillable = [
        'tanggal',
        'nama_piket',
        'keterangan',
        'status',
    ];

    protected $casts = [
        'tanggal' => 'date',
    ];

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'aktif');
    }

    public function teachers(): BelongsToMany
    {
        return $this->belongsToMany(Teacher::class, 'duty_schedule_teacher')
            ->withTimestamps();
    }
}
