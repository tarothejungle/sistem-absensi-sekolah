<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class HolidaySetting extends Model
{
    protected $fillable = [
        'tanggal',
        'nama_libur',
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
}
